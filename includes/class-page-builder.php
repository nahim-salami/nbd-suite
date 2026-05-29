<?php
/**
 * Génère le contenu Elementor pré-rempli lors de la création d'une masterclass.
 *
 * Stratégie "édition intelligente" :
 * - Les données dynamiques (prix, image, URL, badges) ne sont PAS écrites en dur
 *   dans le _elementor_data. Elles sont injectées via des shortcodes qui lisent
 *   le post_meta à chaque rendu.
 * - Donc l'utilisateur peut :
 *     1. Modifier les méta-données depuis le formulaire admin → la card se met à jour seule
 *     2. Réorganiser/styliser les sections dans Elementor → ses modifs sont préservées
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Page_Builder {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Rendu LIVE du contenu single masterclass : on (re)génère le contenu depuis
        // les post_meta à chaque affichage. Priorité très haute pour passer APRÈS
        // Elementor (qui filtre the_content à 10) et garantir un affichage à jour,
        // sans dépendre d'un _elementor_data figé ni d'une régénération manuelle.
        add_filter( 'the_content', array( $this, 'maybe_render_live_content' ), 99999 );
    }

    /**
     * Si on est sur la page single d'une masterclass (frontend, requête principale),
     * remplace le contenu par le rendu live construit depuis les méta-données.
     */
    public function maybe_render_live_content( $content ) {
        if ( is_admin() ) return $content;
        if ( ! is_singular( 'page' ) ) return $content;
        if ( ! in_the_loop() || ! is_main_query() ) return $content;
        // Ne pas interférer avec l'éditeur / la preview Elementor
        if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) return $content;
        if ( did_action( 'elementor/editor/before_enqueue_scripts' ) ) return $content;

        $pid = get_the_ID();
        if ( ! $pid || get_post_meta( $pid, '_nbd_mc_is_masterclass', true ) !== '1' ) return $content;

        static $rendering = false;
        if ( $rendering ) return $content; // anti-récursion
        $rendering = true;
        try {
            // Ordre important : blocs Gutenberg d'abord, puis shortcodes
            // (on est appelé APRÈS le do_shortcode natif de the_content, donc il faut le rejouer)
            $html = do_blocks( $this->build_post_content_string( $pid ) );
            $html = do_shortcode( $html );
        } catch ( \Throwable $e ) {
            error_log( '[NBD Suite] Live render error post ' . $pid . ' : ' . $e->getMessage() );
            $rendering = false;
            return $content; // fallback : contenu stocké
        }
        $rendering = false;
        return $html;
    }

    /**
     * ID Elementor : génère un ID unique court (8 chars)
     */
    private function eid() {
        return substr( md5( uniqid( '', true ) ), 0, 8 );
    }

    /**
     * Sauvegarde le contenu actuel (post_content + _elementor_data + edit_mode)
     * avant une régénération. Permet de restaurer via restore_content().
     */
    public function backup_content( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return false;

        $backup = array(
            'date'           => current_time( 'mysql' ),
            'post_content'   => $post->post_content,
            'elementor_data' => get_post_meta( $post_id, '_elementor_data', true ),
            'elementor_mode' => get_post_meta( $post_id, '_elementor_edit_mode', true ),
        );
        update_post_meta( $post_id, '_nbd_mc_content_backup', $backup );
        return true;
    }

    /**
     * Restaure la dernière sauvegarde du contenu.
     * Retourne true si OK, false si pas de sauvegarde.
     */
    public function restore_content( $post_id ) {
        $backup = get_post_meta( $post_id, '_nbd_mc_content_backup', true );
        if ( ! is_array( $backup ) || empty( $backup ) ) return false;

        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => $backup['post_content'] ?? '',
        ) );

        if ( ! empty( $backup['elementor_data'] ) ) {
            update_post_meta( $post_id, '_elementor_data', wp_slash( $backup['elementor_data'] ) );
        } else {
            delete_post_meta( $post_id, '_elementor_data' );
        }

        if ( ! empty( $backup['elementor_mode'] ) ) {
            update_post_meta( $post_id, '_elementor_edit_mode', $backup['elementor_mode'] );
        } else {
            delete_post_meta( $post_id, '_elementor_edit_mode' );
        }

        return true;
    }

    /**
     * Détecte le type de vidéo (youtube / vimeo / hosted)
     */
    private function detect_video_type( $url ) {
        if ( preg_match( '#youtu\.?be#i', $url ) ) return 'youtube';
        if ( preg_match( '#vimeo\.com#i', $url ) ) return 'vimeo';
        return 'hosted';
    }

    /**
     * Construit le tableau _elementor_data pour la page masterclass.
     * Toutes les valeurs dynamiques sont des shortcodes [nbd_mc_*]
     * → ainsi modifier le prix dans l'admin se reflète automatiquement.
     */
    /**
     * Construit le contenu de la page.
     *
     * @param int  $post_id    ID de la page
     * @param bool $use_elementor Si true, active aussi le mode Elementor (par défaut : false = mode WordPress)
     */
    public function build_elementor_template( $post_id, $use_elementor = false ) {
        try {
            $this->backup_content( $post_id );
            $result = $this->build_elementor_template_inner( $post_id, $use_elementor );
            // Stocke un transient pour affichage notice admin (succès)
            set_transient( 'nbd_mc_last_rebuild_' . $post_id, array( 'ok' => true ), 60 );
            return $result;
        } catch ( \Throwable $e ) {
            $msg = $e->getMessage();
            $trace_short = explode( "\n", $e->getTraceAsString() );
            $trace_short = implode( "\n", array_slice( $trace_short, 0, 5 ) );
            error_log( '[NBD Suite] Build error pour post ' . $post_id . ' : ' . $msg . "\n" . $trace_short );
            set_transient( 'nbd_mc_last_rebuild_' . $post_id, array( 'ok' => false, 'msg' => $msg ), 60 );
            return false;
        }
    }

    private function build_elementor_template_inner( $post_id, $use_elementor ) {
        // Toujours préparer le contenu Gutenberg (post_content) — c'est ce que WP lit par défaut
        $this->update_post_content_fallback( $post_id );

        // Préparer aussi le _elementor_data pour l'utilisateur qui veut éditer avec Elementor
        $data = $this->get_template_structure( $post_id );
        update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $post_id, '_elementor_version', '3.18.0' );
        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

        if ( $use_elementor ) {
            // Mode Elementor : Elementor prend le contrôle du rendu
            update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $post_id, '_wp_page_template', 'default' );
        } else {
            // Mode WordPress : on retire le flag Elementor pour que WP édite normalement
            delete_post_meta( $post_id, '_elementor_edit_mode' );
        }

        // Le _elementor_data a changé hors du flux Elementor : on purge son cache CSS
        // (sinon la page peut continuer à afficher l'ancien rendu).
        $this->clear_caches_for_post( $post_id );
    }

    /**
     * Purge les caches susceptibles de servir un ancien rendu après régénération :
     * - cache CSS Elementor du post (meta + fichier)
     * - cache de page des plugins courants (WP Rocket, LiteSpeed, W3TC, WP Super Cache…)
     */
    private function clear_caches_for_post( $post_id ) {
        // ----- Elementor -----
        delete_post_meta( $post_id, '_elementor_css' );
        if ( class_exists( '\Elementor\Plugin' ) ) {
            try {
                $el = \Elementor\Plugin::instance();
                if ( isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
                    $el->files_manager->clear_cache();
                }
            } catch ( \Throwable $e ) {
                error_log( '[NBD Suite] Elementor cache clear failed: ' . $e->getMessage() );
            }
        }

        // ----- Caches de page tiers -----
        if ( function_exists( 'rocket_clean_post' ) )            rocket_clean_post( $post_id );           // WP Rocket
        if ( function_exists( 'wp_cache_post_change' ) )         wp_cache_post_change( $post_id );        // WP Super Cache
        if ( function_exists( 'w3tc_flush_post' ) )              w3tc_flush_post( $post_id );             // W3 Total Cache
        if ( has_action( 'litespeed_purge_post' ) )              do_action( 'litespeed_purge_post', $post_id ); // LiteSpeed
        if ( function_exists( 'sg_cachepress_purge_cache' ) )    sg_cachepress_purge_cache();             // SiteGround

        clean_post_cache( $post_id );
    }

    /**
     * Génère le contenu de la page au format Gutenberg avec des BLOCS NATIFS éditables.
     * - Image, titre, description, listes : blocs WP standards (modifiables directement)
     * - Carte sticky d'achat : bloc HTML pré-rempli (modifiable en mode HTML)
     * - Section "Vous pourriez aussi aimer" : shortcode dynamique (auto-mis à jour)
     */
    public function update_post_content_fallback( $post_id ) {
        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => $this->build_post_content_string( $post_id ),
        ) );
    }

    /**
     * Construit et retourne la chaîne de contenu (blocs Gutenberg) d'une masterclass
     * à partir des post_meta. Utilisé à la fois pour stocker post_content (régénération)
     * et pour le rendu LIVE en frontend (filtre the_content) — ainsi l'affichage colle
     * toujours aux données enregistrées, sans dépendre d'un snapshot figé.
     */
    public function build_post_content_string( $post_id ) {
        // Récupération de toutes les données
        $hero_image_id     = (int) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_hero_image' );
        $card_image_id     = (int) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_card_image' );
        $badge_pill        = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_badge_pill' );
        $description       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_short_description' );
        $learnings         = array_filter( array_map( 'trim', (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_learnings', array() ) ) );
        $included          = array_filter( array_map( 'trim', (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_included', array() ) ) );
        $trainer_name      = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_trainer_name' );
        $trainer_bio       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_trainer_bio' );
        $trainer_avatar_id = (int) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_trainer_avatar' );
        $price_old         = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_price_old' );
        $price_current     = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_price_current' );
        $currency          = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_currency', '€' );
        $buy_url           = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_buy_url' );
        $buy_label         = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_buy_label', 'Voir plus' );
        $card_badges       = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_card_badges', array() );
        $page_title        = get_the_title( $post_id );

        // Modules + Bonus
        $modules           = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_modules', array() );
        $modules_title     = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_modules_title', 'Modules' );
        $bonus             = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_bonus', array() );
        $bonus_title       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_bonus_title', 'Bonus' );

        // Vidéos (repeater + fallback legacy)
        $videos            = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_videos', array() );
        $video_title       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_video_title', 'Vidéos' );
        // Fallback ancien champ legacy
        if ( empty( $videos ) ) {
            $legacy_url = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_video_url' );
            if ( $legacy_url ) {
                $videos = array( array( 'title' => '', 'url' => $legacy_url ) );
            }
        }

        // Témoignages
        $testimonials      = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_testimonials', array() );
        $testimonials_title= NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_testimonials_title', 'Ce qu\'ils en disent' );

        $content = '';

        /* ============ LAYOUT 2 COLONNES ============ */
        $content .= "<!-- wp:columns {\"className\":\"nbd-mc-layout\"} -->\n";
        $content .= "<div class=\"wp-block-columns nbd-mc-layout\">\n";

        /* ===== Colonne gauche (66%) ===== */
        $content .= "<!-- wp:column {\"width\":\"66.66%\"} -->\n";
        $content .= "<div class=\"wp-block-column\" style=\"flex-basis:66.66%\">\n";

        /* Image hero (dans la colonne gauche) */
        if ( $hero_image_id ) {
            $hero_url = wp_get_attachment_image_url( $hero_image_id, 'large' );
            if ( $hero_url ) {
                $content .= "<!-- wp:image {\"id\":{$hero_image_id},\"sizeSlug\":\"large\",\"className\":\"nbd-product-hero-image\"} -->\n";
                $content .= "<figure class=\"wp-block-image size-large nbd-product-hero-image\"><img src=\"" . esc_url( $hero_url ) . "\" alt=\"" . esc_attr( $page_title ) . "\" class=\"wp-image-{$hero_image_id}\"/></figure>\n";
                $content .= "<!-- /wp:image -->\n\n";
            }
        }

        // Badge pill
        if ( $badge_pill ) {
            $content .= "<!-- wp:paragraph {\"className\":\"nbd-product-badge-pill\"} -->\n";
            $content .= "<p class=\"nbd-product-badge-pill\">" . esc_html( $badge_pill ) . "</p>\n";
            $content .= "<!-- /wp:paragraph -->\n\n";
        }

        // Titre H1
        $content .= "<!-- wp:heading {\"level\":1,\"className\":\"nbd-product-title\"} -->\n";
        $content .= "<h1 class=\"wp-block-heading nbd-product-title\">" . esc_html( $page_title ) . "</h1>\n";
        $content .= "<!-- /wp:heading -->\n\n";

        // Description (sans titre "Description")
        if ( $description ) {
            $content .= "<!-- wp:html -->\n" . $description . "\n<!-- /wp:html -->\n\n";
        } else {
            $content .= "<!-- wp:paragraph -->\n<p>Ajoutez ici la description complète de votre masterclass.</p>\n<!-- /wp:paragraph -->\n\n";
        }

        // Ce que vous allez apprendre
        if ( ! empty( $learnings ) ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Ce que vous allez apprendre</h2>\n<!-- /wp:heading -->\n\n";
            $content .= "<!-- wp:list -->\n<ul>";
            foreach ( $learnings as $item ) {
                $content .= '<!-- wp:list-item --><li>' . esc_html( $item ) . '</li><!-- /wp:list-item -->';
            }
            $content .= "</ul>\n<!-- /wp:list -->\n\n";
        }

        // À propos du formateur (groupe avec avatar + bio)
        if ( $trainer_name || $trainer_bio ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">À propos du formateur</h2>\n<!-- /wp:heading -->\n\n";

            $content .= "<!-- wp:group {\"className\":\"nbd-formateur-card\"} -->\n";
            $content .= "<div class=\"wp-block-group nbd-formateur-card\">\n";

            if ( $trainer_avatar_id ) {
                $avatar_url = wp_get_attachment_image_url( $trainer_avatar_id, 'thumbnail' );
                if ( $avatar_url ) {
                    $content .= "<!-- wp:image {\"id\":{$trainer_avatar_id},\"width\":80,\"height\":80,\"sizeSlug\":\"thumbnail\",\"className\":\"is-style-rounded nbd-formateur-avatar\"} -->\n";
                    $content .= "<figure class=\"wp-block-image size-thumbnail is-resized is-style-rounded nbd-formateur-avatar\"><img src=\"" . esc_url( $avatar_url ) . "\" alt=\"" . esc_attr( $trainer_name ) . "\" class=\"wp-image-{$trainer_avatar_id}\" style=\"width:80px;height:80px;object-fit:cover\"/></figure>\n";
                    $content .= "<!-- /wp:image -->\n\n";
                }
            }

            $content .= "<!-- wp:group -->\n<div class=\"wp-block-group\">\n";
            if ( $trainer_name ) {
                $content .= "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $trainer_name ) . "</h3>\n<!-- /wp:heading -->\n\n";
            }
            if ( $trainer_bio ) {
                $content .= "<!-- wp:html -->\n" . $trainer_bio . "\n<!-- /wp:html -->\n";
            }
            $content .= "</div>\n<!-- /wp:group -->\n";
            $content .= "</div>\n<!-- /wp:group -->\n\n";
        }

        // Modules
        $valid_modules = array_values( array_filter( $modules, function( $m ){
            return is_array( $m ) && ( ! empty( $m['title'] ) || ! empty( $m['description'] ) );
        } ) );
        if ( ! empty( $valid_modules ) ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $modules_title ) . "</h2>\n<!-- /wp:heading -->\n\n";
            $content .= "<!-- wp:shortcode -->\n[nbd_mc_modules]\n<!-- /wp:shortcode -->\n\n";
        }

        // Bonus
        $valid_bonus = array_values( array_filter( $bonus, function( $b ){
            return is_array( $b ) && ( ! empty( $b['title'] ) || ! empty( $b['description'] ) );
        } ) );
        if ( ! empty( $valid_bonus ) ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $bonus_title ) . "</h2>\n<!-- /wp:heading -->\n\n";
            $content .= "<!-- wp:shortcode -->\n[nbd_mc_bonus]\n<!-- /wp:shortcode -->\n\n";
        }

        // Ce qui est inclus (avec titre)
        if ( ! empty( $included ) ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Ce qui est inclus</h2>\n<!-- /wp:heading -->\n\n";
            $content .= "<!-- wp:list -->\n<ul>";
            foreach ( $included as $item ) {
                $content .= '<!-- wp:list-item --><li>' . esc_html( $item ) . '</li><!-- /wp:list-item -->';
            }
            $content .= "</ul>\n<!-- /wp:list -->\n\n";
        }

        /* ============ SECTION VIDÉOS ============ */
        // Filtre les entrées valides
        $videos = array_values( array_filter( $videos, function( $v ){
            return is_array( $v ) && ! empty( $v['url'] );
        } ) );

        if ( ! empty( $videos ) ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $video_title ) . "</h2>\n<!-- /wp:heading -->\n\n";

            $videos_html = '<div class="nbd-videos-grid' . ( count( $videos ) > 1 ? ' nbd-videos-multi' : ' nbd-videos-single' ) . '">';
            foreach ( $videos as $i => $v ) {
                if ( ! is_array( $v ) ) continue;
                $v_url   = $v['url']   ?? '';
                $v_title = $v['title'] ?? '';
                if ( ! $v_url ) continue;

                $embed = wp_oembed_get( $v_url );
                $videos_html .= '<div class="nbd-video-item">';
                if ( $v_title ) {
                    $videos_html .= '<h3 class="nbd-video-item-title">' . esc_html( $v_title ) . '</h3>';
                }
                $videos_html .= '<div class="nbd-video-embed">';
                if ( $embed ) {
                    $videos_html .= $embed;
                } else {
                    // Fallback : lien si oEmbed échoue
                    $videos_html .= '<a href="' . esc_url( $v_url ) . '" target="_blank" rel="noopener" class="nbd-video-fallback">▶ ' . esc_html( $v_url ) . '</a>';
                }
                $videos_html .= '</div>';
                $videos_html .= '</div>';
            }
            $videos_html .= '</div>';

            $content .= "<!-- wp:html -->\n" . $videos_html . "\n<!-- /wp:html -->\n\n";
        }

        /* ============ SECTION TÉMOIGNAGES ============ */
        if ( ! empty( $testimonials ) ) {
            $content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $testimonials_title ) . "</h2>\n<!-- /wp:heading -->\n\n";

            $testim_html = '<div class="nbd-testimonials-grid">';
            foreach ( $testimonials as $t ) {
                if ( ! is_array( $t ) ) continue;
                $t_name   = $t['name']   ?? '';
                $t_role   = $t['role']   ?? '';
                $t_text   = $t['text']   ?? '';
                $t_rating = intval( $t['rating'] ?? 5 );
                if ( empty( $t_name ) && empty( $t_text ) ) continue;

                $initials = '';
                if ( $t_name ) {
                    $parts = explode( ' ', trim( $t_name ) );
                    $initials = strtoupper( substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? substr( $parts[1], 0, 1 ) : '' ) );
                }

                $testim_html .= '<div class="nbd-testimonial-card">';
                if ( $t_rating > 0 ) {
                    $testim_html .= '<div class="nbd-testimonial-rating">' . str_repeat( '★', $t_rating ) . str_repeat( '☆', 5 - $t_rating ) . '</div>';
                }
                if ( $t_text ) {
                    $testim_html .= '<blockquote class="nbd-testimonial-text">' . esc_html( $t_text ) . '</blockquote>';
                }
                $testim_html .= '<div class="nbd-testimonial-author">';
                if ( $initials ) {
                    $testim_html .= '<div class="nbd-testimonial-avatar">' . esc_html( $initials ) . '</div>';
                }
                $testim_html .= '<div class="nbd-testimonial-meta">';
                if ( $t_name ) $testim_html .= '<strong>' . esc_html( $t_name ) . '</strong>';
                if ( $t_role ) $testim_html .= '<span>' . esc_html( $t_role ) . '</span>';
                $testim_html .= '</div></div>';
                $testim_html .= '</div>';
            }
            $testim_html .= '</div>';

            $content .= "<!-- wp:html -->\n" . $testim_html . "\n<!-- /wp:html -->\n\n";
        }

        $content .= "</div>\n<!-- /wp:column -->\n\n";

        /* ===== Colonne droite : sticky card (33%) ===== */
        $content .= "<!-- wp:column {\"width\":\"33.33%\",\"className\":\"nbd-mc-sticky-col\"} -->\n";
        $content .= "<div class=\"wp-block-column nbd-mc-sticky-col\" style=\"flex-basis:33.33%\">\n";
        $content .= $this->build_sticky_card_blocks( $post_id, array(
            'card_image_id' => $card_image_id,
            'card_badges'   => $card_badges,
            'badge_pill'    => $badge_pill,
            'title'         => $page_title,
            'price_old'     => $price_old,
            'price_current' => $price_current,
            'currency'      => $currency,
            'buy_url'       => $buy_url,
            'buy_label'     => $buy_label,
        ) );
        $content .= "</div>\n<!-- /wp:column -->\n\n";

        $content .= "</div>\n<!-- /wp:columns -->\n\n";

        // ===== Section RELATED full-width (en dehors des colonnes) =====
        $content .= "<!-- wp:group {\"className\":\"nbd-related-section\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"60px\"}},\"color\":{\"background\":\"#FAF5FF\"}}} -->\n";
        $content .= "<div class=\"wp-block-group nbd-related-section\" style=\"background-color:#FAF5FF;padding-top:60px;padding-bottom:60px\">\n";
        $content .= "<!-- wp:heading {\"textAlign\":\"center\"} -->\n<h2 class=\"wp-block-heading has-text-align-center\">Vous pourriez aussi aimer</h2>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:shortcode -->\n[nbd_mc_grid limit=\"3\" exclude_current=\"1\"]\n<!-- /wp:shortcode -->\n";
        $content .= "</div>\n<!-- /wp:group -->\n";

        return $content;
    }

    /**
     * Construit la card sticky en HTML brut (un seul bloc wp:html).
     * Évite les conflits de style avec les blocs Gutenberg imbriqués.
     */
    private function build_sticky_card_blocks( $post_id, $data ) {
        extract( $data );

        $h = '<div class="nbd-purchase-card">';

        // Image
        if ( $card_image_id ) {
            $card_image_url = wp_get_attachment_image_url( $card_image_id, 'large' );
            if ( $card_image_url ) {
                $h .= '<div class="nbd-purchase-card-image">';
                $h .= '<img src="' . esc_url( $card_image_url ) . '" alt="' . esc_attr( $title ) . '">';
                $h .= '</div>';
            }
        }

        $h .= '<div class="nbd-purchase-card-body">';

        // Badges (3 icones)
        if ( ! empty( $card_badges ) ) {
            $h .= '<div class="nbd-purchase-card-badges">';
            foreach ( $card_badges as $b ) {
                if ( empty( $b['label'] ) && empty( $b['icon'] ) ) continue;
                $h .= '<div class="nbd-purchase-card-badge">';
                $h .= '<div class="icon">' . esc_html( $b['icon'] ?? '' ) . '</div>';
                $h .= '<div>' . nl2br( esc_html( $b['label'] ?? '' ) ) . '</div>';
                $h .= '</div>';
            }
            $h .= '</div>';
        }

        // Pill
        if ( $badge_pill ) {
            $h .= '<span class="nbd-purchase-card-pill">' . esc_html( $badge_pill ) . '</span>';
        }

        // Titre
        $h .= '<h3 class="nbd-purchase-card-title">' . esc_html( $title ) . '</h3>';

        // Prix
        if ( $price_current || $price_old ) {
            $h .= '<div class="nbd-purchase-card-price">';
            if ( $price_old )     $h .= '<span class="nbd-price-strike">' . esc_html( $price_old . $currency ) . '</span>';
            if ( $price_current ) $h .= '<span class="nbd-price-current">' . esc_html( $price_current . $currency ) . '</span>';
            $h .= '</div>';
        }

        // Bouton d'achat (lien simple, pas de wp:buttons block)
        if ( $buy_url ) {
            $h .= '<a href="' . esc_url( $buy_url ) . '" target="_blank" rel="noopener" class="nbd-btn-buy">';
            $h .= esc_html( $buy_label ) . ' <span>👁</span>';
            $h .= '</a>';
        }

        // Footer
        $h .= '<p class="nbd-purchase-card-footer">Paiement sécurisé · Garantie 14 jours</p>';

        $h .= '</div></div>';

        return "<!-- wp:html -->\n" . $h . "\n<!-- /wp:html -->\n";
    }

    private function get_template_structure( $post_id ) {
        // ===== Récupération des données réelles =====
        $hero_image_id     = (int) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_hero_image' );
        $badge_pill        = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_badge_pill' );
        $description       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_short_description' );
        $learnings         = array_filter( array_map( 'trim', (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_learnings', array() ) ) );
        $included          = array_filter( array_map( 'trim', (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_included', array() ) ) );
        $trainer_name      = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_trainer_name' );
        $trainer_bio       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_trainer_bio' );
        $trainer_avatar_id = (int) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_trainer_avatar' );
        $videos            = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_videos', array() );
        $video_title       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_video_title', 'Vidéos' );
        $testimonials      = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_testimonials', array() );
        $testimonials_title= NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_testimonials_title', 'Ce qu\'ils en disent' );
        $modules           = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_modules', array() );
        $modules_title     = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_modules_title', 'Modules' );
        $bonus             = (array) NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_bonus', array() );
        $bonus_title       = NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_bonus_title', 'Bonus' );
        $page_title        = get_the_title( $post_id );

        // ===== Widgets pour la COLONNE GAUCHE (tous éditables nativement) =====
        // L'image hero est DANS la colonne gauche (pas une section séparée full-width)
        $left_widgets = array();

        // Image hero (au début de la colonne gauche)
        if ( $hero_image_id ) {
            $hero_url = wp_get_attachment_image_url( $hero_image_id, 'large' );
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'image',
                'settings' => array(
                    'image' => array( 'url' => $hero_url, 'id' => $hero_image_id ),
                    'image_size' => 'large',
                    '_css_classes' => 'nbd-product-hero-image',
                ),
            );
        }

        // Badge pill (text-editor avec class)
        if ( $badge_pill ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => array(
                    'editor' => '<p class="nbd-product-badge-pill">' . esc_html( $badge_pill ) . '</p>',
                ),
            );
        }

        // Titre H1 — le titre du thème (Hello Elementor) est masqué automatiquement via CSS body class
        $left_widgets[] = array(
            'id' => $this->eid(),
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => array(
                'title' => $page_title,
                'header_size' => 'h1',
                'title_color' => '#4A1D66',
                'typography_typography' => 'custom',
                'typography_font_size' => array( 'unit' => 'px', 'size' => 36 ),
                'typography_font_weight' => '800',
                '_css_classes' => 'nbd-product-title',
            ),
        );

        // Description (text-editor avec HTML)
        if ( $description ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => array( 'editor' => $description ),
            );
        }

        // Ce que vous allez apprendre (heading + liste HTML)
        if ( ! empty( $learnings ) ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => 'Ce que vous allez apprendre',
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            $html = '<ul class="nbd-check-list">';
            foreach ( $learnings as $item ) {
                $html .= '<li>' . esc_html( $item ) . '</li>';
            }
            $html .= '</ul>';
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => array( 'editor' => $html ),
            );
        }

        // À propos du formateur (heading + shortcode pour la card stylée originale)
        if ( $trainer_name || $trainer_bio ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => 'À propos du formateur',
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'shortcode',
                'settings' => array( 'shortcode' => '[nbd_mc_trainer]' ),
            );
        }

        // ===== MODULES (heading + cartes) =====
        $valid_modules = array_values( array_filter( $modules, function( $m ){
            return is_array( $m ) && ( ! empty( $m['title'] ) || ! empty( $m['description'] ) );
        } ) );
        if ( ! empty( $valid_modules ) ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => $modules_title,
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'shortcode',
                'settings' => array( 'shortcode' => '[nbd_mc_modules]' ),
            );
        }

        // ===== BONUS (heading + cartes) =====
        $valid_bonus = array_values( array_filter( $bonus, function( $b ){
            return is_array( $b ) && ( ! empty( $b['title'] ) || ! empty( $b['description'] ) );
        } ) );
        if ( ! empty( $valid_bonus ) ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => $bonus_title,
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'shortcode',
                'settings' => array( 'shortcode' => '[nbd_mc_bonus]' ),
            );
        }

        // Ce qui est inclus (heading + liste HTML)
        if ( ! empty( $included ) ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => 'Ce qui est inclus',
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            $html = '<ul class="nbd-check-list">';
            foreach ( $included as $item ) {
                $html .= '<li>' . esc_html( $item ) . '</li>';
            }
            $html .= '</ul>';
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => array( 'editor' => $html ),
            );
        }

        // Vidéos (widget video natif Elementor)
        $videos = array_values( array_filter( $videos, function( $v ){
            return is_array( $v ) && ! empty( $v['url'] );
        } ) );
        if ( ! empty( $videos ) ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => $video_title,
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            foreach ( $videos as $v ) {
                if ( ! is_array( $v ) || empty( $v['url'] ) ) continue;
                if ( ! empty( $v['title'] ) ) {
                    $left_widgets[] = array(
                        'id' => $this->eid(),
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => array(
                            'title' => $v['title'],
                            'header_size' => 'h3',
                            'title_color' => '#4A1D66',
                        ),
                    );
                }
                $vtype = $this->detect_video_type( $v['url'] );
                $vsettings = array( 'video_type' => $vtype );
                if ( $vtype === 'youtube' )      $vsettings['youtube_url'] = $v['url'];
                elseif ( $vtype === 'vimeo' )    $vsettings['vimeo_url']   = $v['url'];
                else                              $vsettings['hosted_url']  = array( 'url' => $v['url'] );
                $left_widgets[] = array(
                    'id' => $this->eid(),
                    'elType' => 'widget',
                    'widgetType' => 'video',
                    'settings' => $vsettings,
                );
            }
        }

        // Témoignages (heading + shortcode pour la grille — design complexe)
        $valid_testimonials = array_values( array_filter( $testimonials, function( $t ){
            return is_array( $t ) && ( ! empty( $t['name'] ) || ! empty( $t['text'] ) );
        } ) );
        if ( ! empty( $valid_testimonials ) ) {
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => $testimonials_title,
                    'header_size' => 'h2',
                    'title_color' => '#4A1D66',
                ),
            );
            $left_widgets[] = array(
                'id' => $this->eid(),
                'elType' => 'widget',
                'widgetType' => 'shortcode',
                'settings' => array( 'shortcode' => '[nbd_mc_testimonials]' ),
            );
        }

        // (related grid déplacé dans une section séparée full-width — voir $related_section ci-dessous)

        // ===== SECTION 2 : Layout 2 colonnes (contenu + card sticky) =====
        $main_section = array(
            'id' => $this->eid(),
            'elType' => 'section',
            'settings' => array(
                'structure'      => '20',
                'content_width'  => array( 'size' => 1200, 'unit' => 'px' ),
                'gap'            => 'extended',
                '_css_classes'   => 'nbd-elementor-product-section',
                'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '20', 'bottom' => '60', 'left' => '20', 'isLinked' => false ),
                'padding_tablet' => array( 'unit' => 'px', 'top' => '0', 'right' => '16', 'bottom' => '40', 'left' => '16', 'isLinked' => false ),
                'padding_mobile' => array( 'unit' => 'px', 'top' => '0', 'right' => '12', 'bottom' => '30', 'left' => '12', 'isLinked' => false ),
            ),
            'elements' => array(
                // Colonne gauche — widgets natifs éditables
                array(
                    'id' => $this->eid(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 66, '_inline_size' => 66 ),
                    'elements' => $left_widgets,
                ),
                // Colonne droite — card sticky (shortcode car dynamique : prix, bouton, badges)
                array(
                    'id' => $this->eid(),
                    'elType' => 'column',
                    'settings' => array(
                        '_column_size'   => 33,
                        '_inline_size'   => 33,
                        '_css_classes'   => 'nbd-elementor-sticky-col',
                        // Sticky Elementor Pro (si dispo)
                        'sticky'         => 'top',
                        'sticky_on'      => array( 'desktop', 'tablet' ),
                        'sticky_offset'  => 100,
                    ),
                    'elements' => array( array(
                        'id' => $this->eid(),
                        'elType' => 'widget',
                        'widgetType' => 'shortcode',
                        'settings' => array( 'shortcode' => '[nbd_mc_sticky_card]' ),
                    ) ),
                ),
            ),
        );

        // SECTION 3 : Related
        // Section "Vous pourriez aussi aimer" — pleine largeur après main
        $related_section = array(
            'id' => $this->eid(),
            'elType' => 'section',
            'settings' => array(
                'structure'             => '10',
                'content_width'         => array( 'size' => 1200, 'unit' => 'px' ),
                'background_background' => 'classic',
                'background_color'      => '#FAF5FF',
                'padding'               => array( 'unit' => 'px', 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'isLinked' => false ),
                'padding_tablet'        => array( 'unit' => 'px', 'top' => '40', 'right' => '16', 'bottom' => '40', 'left' => '16', 'isLinked' => false ),
                'padding_mobile'        => array( 'unit' => 'px', 'top' => '30', 'right' => '12', 'bottom' => '30', 'left' => '12', 'isLinked' => false ),
            ),
            'elements' => array( array(
                'id' => $this->eid(),
                'elType' => 'column',
                'settings' => array( '_column_size' => 100 ),
                'elements' => array(
                    array(
                        'id' => $this->eid(),
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => array(
                            'title' => 'Vous pourriez aussi aimer',
                            'header_size' => 'h2',
                            'align' => 'center',
                            'title_color' => '#4A1D66',
                        ),
                    ),
                    array(
                        'id' => $this->eid(),
                        'elType' => 'widget',
                        'widgetType' => 'shortcode',
                        'settings' => array( 'shortcode' => '[nbd_mc_grid limit="3" exclude_current="1"]' ),
                    ),
                ),
            ) ),
        );

        // 2 sections : main (image+contenu+sticky) puis related full-width (sticky s'arrête ici)
        return array( $main_section, $related_section );
    }

    /**
     * Page CATALOGUE : 4 layouts au choix (lit l'option nbd_mc_catalog_layout)
     */
    public function create_or_update_catalog_page() {
        $existing_id = (int) get_option( 'nbd_mc_catalog_page_id' );
        $slug        = get_option( 'nbd_mc_catalog_slug', 'catalogue' );
        $title       = get_option( 'nbd_mc_catalog_hero_title', 'Notre catalogue' );

        if ( $existing_id && get_post( $existing_id ) ) {
            $page_id = $existing_id;
            wp_update_post( array(
                'ID'         => $page_id,
                'post_title' => $title,
                'post_name'  => $slug,
            ) );
        } else {
            $page_id = wp_insert_post( array(
                'post_type'   => 'page',
                'post_title'  => $title,
                'post_name'   => $slug,
                'post_status' => 'publish',
            ) );
            update_option( 'nbd_mc_catalog_page_id', $page_id );
        }

        update_post_meta( $page_id, '_nbd_is_catalog_page', '1' );

        // Contenu Gutenberg : un seul shortcode qui rend selon le layout choisi
        $content  = "<!-- wp:shortcode -->\n[nbd_catalog]\n<!-- /wp:shortcode -->\n";
        wp_update_post( array( 'ID' => $page_id, 'post_content' => $content ) );

        // Mode WordPress par défaut (pas d'Elementor edit_mode)
        delete_post_meta( $page_id, '_elementor_edit_mode' );

        return $page_id;
    }

    /**
     * Page archive : grille de toutes les masterclass.
     */
    public function create_or_update_archive_page() {
        $existing_id = (int) get_option( 'nbd_mc_archive_page_id' );
        $slug        = get_option( 'nbd_mc_archive_slug', 'masterclass' );

        if ( $existing_id && get_post( $existing_id ) ) {
            $page_id = $existing_id;
            wp_update_post( array(
                'ID'         => $page_id,
                'post_title' => __( 'Nos Masterclass', 'nbd-masterclass' ),
                'post_name'  => $slug,
            ) );
        } else {
            $page_id = wp_insert_post( array(
                'post_type'   => 'page',
                'post_title'  => __( 'Nos Masterclass', 'nbd-masterclass' ),
                'post_name'   => $slug,
                'post_status' => 'publish',
            ) );
            update_option( 'nbd_mc_archive_page_id', $page_id );
        }

        update_post_meta( $page_id, '_nbd_is_masterclass_archive', '1' );

        // Construire l'archive Elementor
        $archive_data = array(
            // HERO
            array(
                'id' => $this->eid(),
                'elType' => 'section',
                'settings' => array(
                    'structure' => '10',
                    'background_background' => 'classic',
                    'background_color' => '#FAF5FF',
                    'padding' => array( 'unit' => 'px', 'top' => '60', 'right' => '0', 'bottom' => '40', 'left' => '0', 'isLinked' => false ),
                ),
                'elements' => array( array(
                    'id' => $this->eid(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 100 ),
                    'elements' => array(
                        array(
                            'id' => $this->eid(),
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => array(
                                'title' => 'Nos Masterclass',
                                'header_size' => 'h1',
                                'align' => 'center',
                                'title_color' => '#4A1D66',
                            ),
                        ),
                        array(
                            'id' => $this->eid(),
                            'elType' => 'widget',
                            'widgetType' => 'text-editor',
                            'settings' => array(
                                'editor' => '<p style="text-align:center;font-size:18px;">Des formations vidéo expertes pour une dentisterie holistique.</p>',
                                'align' => 'center',
                            ),
                        ),
                    ),
                ) ),
            ),
            // GRID
            array(
                'id' => $this->eid(),
                'elType' => 'section',
                'settings' => array(
                    'structure' => '10',
                    'padding' => array( 'unit' => 'px', 'top' => '40', 'right' => '0', 'bottom' => '80', 'left' => '0', 'isLinked' => false ),
                ),
                'elements' => array( array(
                    'id' => $this->eid(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 100 ),
                    'elements' => array( array(
                        'id' => $this->eid(),
                        'elType' => 'widget',
                        'widgetType' => 'shortcode',
                        'settings' => array( 'shortcode' => '[nbd_mc_grid limit="-1" show_filters="1"]' ),
                    ) ),
                ) ),
            ),
        );

        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $page_id, '_elementor_version', '3.18.0' );
        update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $archive_data ) ) );

        return $page_id;
    }
}
