<?php
/**
 * Définition centralisée des champs meta d'une page Masterclass.
 * Toutes les données dynamiques (prix, image, URL...) sont stockées en post_meta
 * pour permettre le mode "édition intelligent" : modifier ces champs ne touche pas
 * au contenu Elementor (qui est dans _elementor_data).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Meta_Fields {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Liste des champs gérés par le plugin.
     * Chaque clé = meta_key WordPress (préfixée _nbd_mc_)
     */
    public static function fields() {
        return array(
            // Identifiant
            '_nbd_mc_is_masterclass'   => array( 'type' => 'flag',   'label' => 'Marqueur masterclass' ),

            // Type de produit : formation / masterclass / autre
            '_nbd_mc_product_type'     => array( 'type' => 'text',   'label' => 'Type de produit', 'default' => 'masterclass' ),

            // Visuels
            '_nbd_mc_hero_image'       => array( 'type' => 'image',  'label' => 'Image principale (hero)' ),
            '_nbd_mc_card_image'       => array( 'type' => 'image',  'label' => 'Image de la card sticky' ),

            // Pricing & CTA
            '_nbd_mc_badge_pill'       => array( 'type' => 'text',   'label' => 'Badge (pill)', 'default' => 'Replay Masterclass' ),
            '_nbd_mc_price_old'        => array( 'type' => 'number', 'label' => 'Prix barré' ),
            '_nbd_mc_price_current'    => array( 'type' => 'number', 'label' => 'Prix actuel' ),
            '_nbd_mc_currency'         => array( 'type' => 'text',   'label' => 'Devise', 'default' => '€' ),
            '_nbd_mc_buy_url'          => array( 'type' => 'url',    'label' => 'URL d\'achat (System.io)' ),
            '_nbd_mc_buy_label'        => array( 'type' => 'text',   'label' => 'Texte bouton', 'default' => 'Voir plus' ),

            // Descriptions (HTML autorisé via wp_editor)
            '_nbd_mc_short_description' => array( 'type' => 'html', 'label' => 'Description courte (card)' ),

            // Badges de la card (3 icônes)
            '_nbd_mc_card_badges'      => array( 'type' => 'badges_repeater', 'label' => 'Badges card (icône + texte)' ),

            // Listes
            '_nbd_mc_learnings'        => array( 'type' => 'list', 'label' => 'Ce que vous allez apprendre' ),
            '_nbd_mc_included'         => array( 'type' => 'list', 'label' => 'Ce qui est inclus' ),

            // Formateur
            '_nbd_mc_trainer_name'     => array( 'type' => 'text', 'label' => 'Nom du formateur', 'default' => 'Dr Catherine ROSSI' ),
            '_nbd_mc_trainer_bio'      => array( 'type' => 'html', 'label' => 'Bio formateur' ),
            '_nbd_mc_trainer_avatar'   => array( 'type' => 'image', 'label' => 'Avatar formateur' ),

            // Catégorie / taxonomie
            '_nbd_mc_category'         => array( 'type' => 'text', 'label' => 'Catégorie' ),

            // Vidéos (repeater : plusieurs vidéos avec titre + URL)
            '_nbd_mc_video_title'      => array( 'type' => 'text', 'label' => 'Titre section vidéos', 'default' => 'Vidéos' ),
            '_nbd_mc_videos'           => array( 'type' => 'videos_repeater', 'label' => 'Vidéos (titre + URL)' ),
            // Compat ancien champ — sera migré au save
            '_nbd_mc_video_url'        => array( 'type' => 'url',  'label' => 'URL vidéo (legacy)' ),

            // Témoignages (repeater)
            '_nbd_mc_testimonials_title' => array( 'type' => 'text',     'label' => 'Titre section témoignages', 'default' => 'Ce qu\'ils en disent' ),
            '_nbd_mc_testimonials'       => array( 'type' => 'testimonials_repeater', 'label' => 'Témoignages' ),

            // Modules (repeater : titre + description par module)
            '_nbd_mc_modules_title'      => array( 'type' => 'text',     'label' => 'Titre section modules', 'default' => 'Modules' ),
            '_nbd_mc_modules'            => array( 'type' => 'modules_repeater', 'label' => 'Modules' ),

            // Bonus (repeater : titre + description par bonus)
            '_nbd_mc_bonus_title'        => array( 'type' => 'text',     'label' => 'Titre section bonus', 'default' => 'Bonus' ),
            '_nbd_mc_bonus'              => array( 'type' => 'modules_repeater', 'label' => 'Bonus' ),

            // SEO
            '_nbd_mc_seo_title'        => array( 'type' => 'text',     'label' => 'Titre SEO' ),
            '_nbd_mc_seo_description'  => array( 'type' => 'textarea', 'label' => 'Description SEO' ),
            '_nbd_mc_og_image'         => array( 'type' => 'image',    'label' => 'Image Open Graph' ),
        );
    }

    public static function get( $post_id, $key, $default = '' ) {
        $value = get_post_meta( $post_id, $key, true );
        if ( $value === '' || $value === null ) {
            $fields = self::fields();
            if ( isset( $fields[ $key ]['default'] ) ) {
                return $fields[ $key ]['default'];
            }
            return $default;
        }
        return $value;
    }

    public static function save( $post_id, $data ) {
        foreach ( self::fields() as $key => $config ) {
            // skip si non fourni — mode "édition intelligent" : ne touche qu'aux champs présents
            $form_key = ltrim( $key, '_' );
            if ( ! array_key_exists( $form_key, $data ) ) continue;

            // wp_unslash : WP slash automatiquement $_POST ; on dé-slash avant traitement
            $value = is_string( $data[ $form_key ] ) ? wp_unslash( $data[ $form_key ] ) : $data[ $form_key ];

            // Cas spécial : catégories — normalisation slug + dédoublonnage
            if ( $key === '_nbd_mc_category' ) {
                $value = self::format_categories_storage( self::parse_categories( $value ) );
                update_post_meta( $post_id, $key, $value );
                continue;
            }

            switch ( $config['type'] ) {
                case 'number':
                    $value = is_numeric( $value ) ? floatval( $value ) : '';
                    break;
                case 'url':
                    $value = esc_url_raw( $value );
                    break;
                case 'image':
                    $value = absint( $value );
                    break;
                case 'list':
                case 'badges_repeater':
                case 'testimonials_repeater':
                case 'videos_repeater':
                case 'modules_repeater':
                    $value = is_array( $value ) ? array_values( array_filter( $value, function( $v ){ return ! empty( $v ) && $v !== array(); } ) ) : array();
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field( $value );
                    break;
                case 'html':
                    // Accepte le HTML rich-text (wp_editor) tout en filtrant XSS
                    $value = wp_kses_post( $value );
                    break;
                case 'flag':
                    $value = $value ? '1' : '';
                    break;
                default:
                    $value = sanitize_text_field( $value );
            }

            update_post_meta( $post_id, $key, $value );
        }
    }

    public function __construct() {
        add_action( 'init', array( $this, 'register_meta' ) );
        add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
    }

    /**
     * Migration : tous les posts plugin existants reçoivent product_type='masterclass'
     * S'exécute à chaque admin_init mais ne traite que les posts sans product_type
     * (auto-rattrape les posts créés sans le champ).
     */
    public function maybe_migrate() {
        // Trouve tous les posts marqués masterclass mais sans product_type défini
        $posts = get_posts( array(
            'post_type'   => 'page',
            'numberposts' => -1,
            'meta_query'  => array(
                'relation' => 'AND',
                array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ),
                array(
                    'relation' => 'OR',
                    array( 'key' => '_nbd_mc_product_type', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_nbd_mc_product_type', 'value' => '' ),
                ),
            ),
            'fields' => 'ids',
            'post_status' => array( 'publish', 'draft', 'private' ),
        ) );
        foreach ( $posts as $pid ) {
            update_post_meta( $pid, '_nbd_mc_product_type', 'masterclass' );
        }
    }

    /* ----------------------------------------
       CATÉGORIES TRANSVERSALES (slugs séparés par virgules)
       Ex: "pro-sante,dentiste" sur une masterclass
       ---------------------------------------- */

    /** Sanitise une catégorie (slug-safe) */
    public static function sanitize_category( $raw ) {
        return sanitize_title( trim( $raw ) );
    }

    /** Convertit un texte saisi en tableau de slugs uniques */
    public static function parse_categories( $input ) {
        if ( is_array( $input ) ) $input = implode( ',', $input );
        $parts = preg_split( '/[,;\n]+/', (string) $input );
        $out = array();
        foreach ( $parts as $p ) {
            $slug = self::sanitize_category( $p );
            if ( $slug !== '' && ! in_array( $slug, $out, true ) ) {
                $out[] = $slug;
            }
        }
        return $out;
    }

    /** Format de stockage : "slug1,slug2" (sans espaces) */
    public static function format_categories_storage( $cats_array ) {
        return implode( ',', $cats_array );
    }

    /** Retourne le tableau des catégories d'un post */
    public static function get_categories_for_post( $post_id ) {
        $raw = get_post_meta( $post_id, '_nbd_mc_category', true );
        return self::parse_categories( $raw );
    }

    /** Liste toutes les catégories existantes avec leur compteur */
    public static function get_all_categories() {
        global $wpdb;
        $values = $wpdb->get_col( "
            SELECT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_nbd_mc_is_masterclass' AND pm2.meta_value = '1'
            WHERE pm.meta_key = '_nbd_mc_category' AND pm.meta_value != ''
              AND p.post_status IN ('publish','draft','private')
        " );
        $counts = array();
        foreach ( $values as $v ) {
            foreach ( self::parse_categories( $v ) as $slug ) {
                $counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
            }
        }
        ksort( $counts );
        return $counts;
    }

    /** Construit un meta_query partiel pour filtrer par catégorie(s) */
    public static function category_meta_query( $cats ) {
        if ( is_string( $cats ) ) $cats = self::parse_categories( $cats );
        if ( empty( $cats ) ) return null;

        $clauses = array( 'relation' => 'OR' );
        foreach ( $cats as $c ) {
            // Stocké en "a,b,c" — on cherche au début, milieu ou fin
            $clauses[] = array(
                'key'     => '_nbd_mc_category',
                'value'   => '(^|,)' . preg_quote( $c, '/' ) . '(,|$)',
                'compare' => 'REGEXP',
            );
        }
        return $clauses;
    }

    /** Labels des types */
    public static function product_type_labels() {
        return array(
            'formation'   => array( 'icon' => '🎓', 'label' => 'Formation',     'plural' => 'Formations',      'pill' => 'Formation' ),
            'masterclass' => array( 'icon' => '📺', 'label' => 'Masterclass',   'plural' => 'Masterclass',     'pill' => 'Replay Masterclass' ),
            'autre'       => array( 'icon' => '🛒', 'label' => 'Autre produit', 'plural' => 'Autres produits', 'pill' => 'Produit' ),
        );
    }

    public function register_meta() {
        foreach ( self::fields() as $key => $config ) {
            register_post_meta( 'page', $key, array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => in_array( $config['type'], array( 'list', 'badges_repeater' ), true ) ? 'array' : 'string',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ) );
        }
    }
}
