<?php
/**
 * Shortcodes — rendent dynamiquement les éléments d'une masterclass.
 * Ils lisent les post_meta à la volée → modifier les méta-données dans l'admin
 * met automatiquement à jour le rendu sans toucher au _elementor_data.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Shortcodes {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'nbd_mc_hero',        array( $this, 'sc_hero' ) );
        add_shortcode( 'nbd_mc_title',       array( $this, 'sc_title' ) );
        add_shortcode( 'nbd_mc_badge',       array( $this, 'sc_badge' ) );
        add_shortcode( 'nbd_mc_short_description', array( $this, 'sc_short_description' ) );
        add_shortcode( 'nbd_mc_sticky_card', array( $this, 'sc_sticky_card' ) );
        add_shortcode( 'nbd_mc_price',       array( $this, 'sc_price' ) );
        add_shortcode( 'nbd_mc_buy_button',  array( $this, 'sc_buy_button' ) );
        add_shortcode( 'nbd_mc_learnings',   array( $this, 'sc_learnings' ) );
        add_shortcode( 'nbd_mc_included',    array( $this, 'sc_included' ) );
        add_shortcode( 'nbd_mc_trainer',     array( $this, 'sc_trainer' ) );
        add_shortcode( 'nbd_mc_grid',        array( $this, 'sc_grid' ) );
        add_shortcode( 'nbd_catalog',        array( $this, 'sc_catalog' ) );
        add_shortcode( 'nbd_mc_testimonials',array( $this, 'sc_testimonials' ) );
        add_shortcode( 'nbd_mc_modules',     array( $this, 'sc_modules' ) );
        add_shortcode( 'nbd_mc_bonus',       array( $this, 'sc_bonus' ) );
    }

    /* ------------- MODULES ------------- */
    public function sc_modules( $atts ) {
        return $this->render_module_list( $this->pid(), '_nbd_mc_modules', 'nbd-modules-list' );
    }

    /* ------------- BONUS ------------- */
    public function sc_bonus( $atts ) {
        return $this->render_module_list( $this->pid(), '_nbd_mc_bonus', 'nbd-bonus-list' );
    }

    private function render_module_list( $pid, $meta_key, $list_class ) {
        if ( ! $pid ) return '';
        $items = NBD_MC_Meta_Fields::get( $pid, $meta_key, array() );
        if ( ! is_array( $items ) ) $items = array();
        $items = array_values( array_filter( $items, function( $m ){
            return is_array( $m ) && ( ! empty( $m['title'] ) || ! empty( $m['description'] ) );
        } ) );
        if ( empty( $items ) ) return '';

        ob_start(); ?>
        <div class="nbd-masterclass">
            <div class="<?php echo esc_attr( $list_class ); ?> nbd-modules-grid">
            <?php foreach ( $items as $i => $m ) :
                $title = $m['title'] ?? '';
                $desc  = $m['description'] ?? '';
            ?>
                <div class="nbd-module-card">
                    <div class="nbd-module-number"><?php echo intval( $i + 1 ); ?></div>
                    <div class="nbd-module-content">
                        <?php if ( $title ) : ?>
                            <h3 class="nbd-module-title"><?php echo esc_html( $title ); ?></h3>
                        <?php endif; ?>
                        <?php if ( $desc ) : ?>
                            <p class="nbd-module-desc"><?php echo esc_html( $desc ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /* ------------- TÉMOIGNAGES (grille sans titre, pour Elementor) ------------- */
    public function sc_testimonials( $atts ) {
        $pid = $this->pid();
        if ( ! $pid ) return '';
        $testimonials = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_testimonials', array() );
        if ( ! is_array( $testimonials ) ) $testimonials = array();
        $testimonials = array_values( array_filter( $testimonials, function( $t ){
            return is_array( $t ) && ( ! empty( $t['name'] ) || ! empty( $t['text'] ) );
        } ) );
        if ( empty( $testimonials ) ) return '';

        ob_start(); ?>
        <div class="nbd-masterclass">
            <div class="nbd-testimonials-grid">
            <?php foreach ( $testimonials as $t ) :
                $t_name   = $t['name']   ?? '';
                $t_role   = $t['role']   ?? '';
                $t_text   = $t['text']   ?? '';
                $t_rating = intval( $t['rating'] ?? 5 );
                $initials = '';
                if ( $t_name ) {
                    $parts = explode( ' ', trim( $t_name ) );
                    $initials = strtoupper( substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? substr( $parts[1], 0, 1 ) : '' ) );
                }
            ?>
                <div class="nbd-testimonial-card">
                    <?php if ( $t_rating > 0 ) : ?>
                        <div class="nbd-testimonial-rating"><?php echo str_repeat( '★', $t_rating ) . str_repeat( '☆', 5 - $t_rating ); ?></div>
                    <?php endif; ?>
                    <?php if ( $t_text ) : ?>
                        <blockquote class="nbd-testimonial-text"><?php echo esc_html( $t_text ); ?></blockquote>
                    <?php endif; ?>
                    <div class="nbd-testimonial-author">
                        <?php if ( $initials ) : ?>
                            <div class="nbd-testimonial-avatar"><?php echo esc_html( $initials ); ?></div>
                        <?php endif; ?>
                        <div class="nbd-testimonial-meta">
                            <?php if ( $t_name ) : ?><strong><?php echo esc_html( $t_name ); ?></strong><?php endif; ?>
                            <?php if ( $t_role ) : ?><span><?php echo esc_html( $t_role ); ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /* ------------- CATALOGUE (4 layouts + filtre catégorie) ------------- */
    public function sc_catalog( $atts ) {
        $atts = shortcode_atts( array(
            'layout'           => get_option( 'nbd_mc_catalog_layout', 'A' ),
            'category'         => '',
            'hide_page_title'  => '0',
        ), $atts );
        $layout = in_array( strtoupper( $atts['layout'] ), array( 'A', 'B', 'C', 'D' ), true )
            ? strtoupper( $atts['layout'] ) : 'A';

        $category_filter = NBD_MC_Meta_Fields::parse_categories( $atts['category'] );

        $sections = $this->get_catalog_sections( $category_filter );
        $hero_title    = get_option( 'nbd_mc_catalog_hero_title',    'Notre catalogue' );
        $hero_subtitle = get_option( 'nbd_mc_catalog_hero_subtitle', '' );

        ob_start();

        // Option : masquer le titre de page généré par le thème WordPress
        if ( in_array( strtolower( (string) $atts['hide_page_title'] ), array( '1', 'true', 'yes' ), true ) ) {
            ?>
            <style>
                /* Masquer le titre de page injecté par le thème */
                .entry-title,
                .page-title,
                h1.entry-title,
                .post-title,
                header.entry-header > .entry-title,
                .elementor-page-title,
                .single-page-title,
                .page-header h1,
                .wp-block-post-title,
                .page header.entry-header h1 { display: none !important; }
                /* Réduit le padding du header devenu vide */
                header.entry-header:empty,
                .page-header:empty { display: none !important; padding: 0 !important; margin: 0 !important; }
            </style>
            <?php
        }
        ?>
        <div class="nbd-catalog">
            <div class="nbd-catalog-container">
                <?php
                $method = 'render_catalog_' . strtolower( $layout );
                echo $this->$method( $sections, $hero_title, $hero_subtitle );
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_catalog_sections( $category_filter = array() ) {
        $types = array( 'formation', 'masterclass', 'autre' );
        $sections = array();
        foreach ( $types as $t ) {
            if ( get_option( 'nbd_mc_catalog_' . $t . '_enabled', '1' ) !== '1' ) continue;
            $sections[ $t ] = array(
                'title'    => get_option( 'nbd_mc_catalog_' . $t . '_title',    '' ),
                'subtitle' => get_option( 'nbd_mc_catalog_' . $t . '_subtitle', '' ),
                'posts'    => $this->query_products_by_type( $t, $category_filter ),
            );
        }
        return $sections;
    }

    private function query_products_by_type( $type, $category_filter = array() ) {
        $meta_query = array( array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ) );
        if ( $type === 'masterclass' ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => '_nbd_mc_product_type', 'value' => 'masterclass' ),
                array( 'key' => '_nbd_mc_product_type', 'compare' => 'NOT EXISTS' ),
            );
        } else {
            $meta_query[] = array( 'key' => '_nbd_mc_product_type', 'value' => $type );
        }

        // Filtre catégorie (OR entre catégories)
        if ( ! empty( $category_filter ) ) {
            $cat_clause = NBD_MC_Meta_Fields::category_meta_query( $category_filter );
            if ( $cat_clause ) $meta_query[] = $cat_clause;
        }

        return get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
    }

    /* === LAYOUT A : Sections verticales === */
    private function render_catalog_a( $sections, $hero_title, $hero_subtitle ) {
        ob_start(); ?>
        <section class="nbd-catalog-hero">
            <h1><?php echo esc_html( $hero_title ); ?></h1>
            <?php if ( $hero_subtitle ) : ?><p class="subtitle"><?php echo esc_html( $hero_subtitle ); ?></p><?php endif; ?>
            <div class="nbd-catalog-anchors">
                <?php foreach ( $sections as $type => $s ) : ?>
                    <a href="#nbd-section-<?php echo esc_attr( $type ); ?>" class="nbd-catalog-anchor"><?php echo esc_html( $s['title'] ); ?></a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php foreach ( $sections as $type => $s ) :
            $count = count( $s['posts'] );
            $bg = ( $type === 'masterclass' ) ? 'background:#FAF5FF;border-radius:24px;padding:40px 24px;' : '';
        ?>
            <section class="nbd-catalog-section" id="nbd-section-<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $bg ); ?>">
                <div class="nbd-catalog-section-header">
                    <div class="nbd-catalog-section-title-block">
                        <h2><?php echo esc_html( $s['title'] ); ?> <span class="count"><?php echo $count; ?></span></h2>
                        <?php if ( $s['subtitle'] ) : ?><p class="subtitle"><?php echo esc_html( $s['subtitle'] ); ?></p><?php endif; ?>
                    </div>
                </div>
                <?php if ( $count > 0 ) : ?>
                    <div class="nbd-catalog-grid nbd-cols-3">
                        <?php foreach ( $s['posts'] as $p ) echo $this->render_catalog_card( $p, $type ); ?>
                    </div>
                <?php else : ?>
                    <div class="nbd-catalog-empty-section">
                        <p>✨ <strong>Bientôt disponible</strong></p>
                        <p>Restez à l'écoute, du contenu arrive très prochainement dans cette catégorie.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach;
        return ob_get_clean();
    }

    /* === LAYOUT B : Onglets === */
    private function render_catalog_b( $sections, $hero_title, $hero_subtitle ) {
        ob_start(); ?>
        <section class="nbd-catalog-hero">
            <h1><?php echo esc_html( $hero_title ); ?></h1>
            <?php if ( $hero_subtitle ) : ?><p class="subtitle"><?php echo esc_html( $hero_subtitle ); ?></p><?php endif; ?>
        </section>

        <div class="nbd-catalog-tabs">
            <?php $first = true; foreach ( $sections as $type => $s ) : ?>
                <button class="nbd-catalog-tab <?php echo $first ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $type ); ?>">
                    <?php echo esc_html( $s['title'] ); ?> <span class="count"><?php echo count( $s['posts'] ); ?></span>
                </button>
            <?php $first = false; endforeach; ?>
        </div>

        <?php $first = true; foreach ( $sections as $type => $s ) : ?>
            <div class="nbd-catalog-tab-panel <?php echo $first ? 'active' : ''; ?>" id="nbd-panel-<?php echo esc_attr( $type ); ?>">
                <div class="nbd-catalog-tab-intro">
                    <h2><?php echo esc_html( $s['title'] ); ?></h2>
                    <?php if ( $s['subtitle'] ) : ?><p><?php echo esc_html( $s['subtitle'] ); ?></p><?php endif; ?>
                </div>
                <div class="nbd-catalog-grid">
                    <?php if ( empty( $s['posts'] ) ) : ?>
                        <p style="grid-column:1/-1;text-align:center;color:#6B7280">Aucun produit dans cette catégorie pour le moment.</p>
                    <?php else : foreach ( $s['posts'] as $p ) echo $this->render_catalog_card( $p, $type ); endif; ?>
                </div>
            </div>
        <?php $first = false; endforeach;
        return ob_get_clean();
    }

    /* === LAYOUT C : Filtres pills === */
    private function render_catalog_c( $sections, $hero_title, $hero_subtitle ) {
        $all_posts = array();
        foreach ( $sections as $type => $s ) {
            foreach ( $s['posts'] as $p ) $all_posts[] = array( 'type' => $type, 'post' => $p );
        }
        $total = count( $all_posts );

        ob_start(); ?>
        <section class="nbd-catalog-hero">
            <h1><?php echo esc_html( $hero_title ); ?></h1>
            <?php if ( $hero_subtitle ) : ?><p class="subtitle"><?php echo esc_html( $hero_subtitle ); ?></p><?php endif; ?>
        </section>

        <div class="nbd-catalog-filters">
            <button class="nbd-catalog-filter-btn active" data-filter="all">Tout <span class="count"><?php echo $total; ?></span></button>
            <?php foreach ( $sections as $type => $s ) : ?>
                <button class="nbd-catalog-filter-btn" data-filter="<?php echo esc_attr( $type ); ?>">
                    <?php echo esc_html( $s['title'] ); ?> <span class="count"><?php echo count( $s['posts'] ); ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="nbd-catalog-grid">
            <?php foreach ( $all_posts as $item ) echo $this->render_catalog_card( $item['post'], $item['type'] ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* === LAYOUT D : Combiné === */
    private function render_catalog_d( $sections, $hero_title, $hero_subtitle ) {
        $all_posts = array();
        foreach ( $sections as $type => $s ) {
            foreach ( $s['posts'] as $p ) $all_posts[] = array( 'type' => $type, 'post' => $p );
        }
        $total = count( $all_posts );

        ob_start(); ?>
        <div class="nbd-catalog-d-wrapper" data-view="sections">
            <section class="nbd-catalog-hero">
                <h1><?php echo esc_html( $hero_title ); ?></h1>
                <?php if ( $hero_subtitle ) : ?><p class="subtitle"><?php echo esc_html( $hero_subtitle ); ?></p><?php endif; ?>
                <div style="margin-top:20px">
                    <div class="nbd-catalog-view-switcher">
                        <button type="button" class="nbd-catalog-view-btn active" data-view="sections">📋 Par section</button>
                        <button type="button" class="nbd-catalog-view-btn" data-view="unified">🔍 Vue filtrée</button>
                    </div>
                </div>
            </section>

            <div class="nbd-catalog-filters">
                <button class="nbd-catalog-filter-btn active" data-filter="all">Tout <span class="count"><?php echo $total; ?></span></button>
                <?php foreach ( $sections as $type => $s ) : ?>
                    <button class="nbd-catalog-filter-btn" data-filter="<?php echo esc_attr( $type ); ?>">
                        <?php echo esc_html( $s['title'] ); ?> <span class="count"><?php echo count( $s['posts'] ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="nbd-catalog-grid nbd-catalog-grid-unified">
                <?php foreach ( $all_posts as $item ) echo $this->render_catalog_card( $item['post'], $item['type'] ); ?>
            </div>

            <?php foreach ( $sections as $type => $s ) :
                $count = count( $s['posts'] );
                $bg = ( $type === 'masterclass' ) ? 'background:#FAF5FF;border-radius:24px;padding:40px 24px;' : '';
            ?>
                <section class="nbd-catalog-section" style="<?php echo esc_attr( $bg ); ?>">
                    <div class="nbd-catalog-section-header">
                        <div class="nbd-catalog-section-title-block">
                            <h2><?php echo esc_html( $s['title'] ); ?> <span class="count"><?php echo $count; ?></span></h2>
                            <?php if ( $s['subtitle'] ) : ?><p class="subtitle"><?php echo esc_html( $s['subtitle'] ); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php if ( $count > 0 ) : ?>
                        <div class="nbd-catalog-grid nbd-cols-3">
                            <?php foreach ( $s['posts'] as $p ) echo $this->render_catalog_card( $p, $type ); ?>
                        </div>
                    <?php else : ?>
                        <div class="nbd-catalog-empty-section">
                            <p>✨ <strong>Bientôt disponible</strong></p>
                            <p>Restez à l'écoute, du contenu arrive très prochainement.</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* === Card commune pour le catalogue (cliquable) === */
    private function render_catalog_card( $post, $type ) {
        $id      = $post->ID;
        $img_id  = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_card_image' )
                   ?: NBD_MC_Meta_Fields::get( $id, '_nbd_mc_hero_image' );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
        $pill    = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_badge_pill' );
        $old     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_price_old' );
        $cur     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_price_current' );
        $sym     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_currency' );
        $link    = get_permalink( $id );

        ob_start(); ?>
        <article class="nbd-product-card nbd-card-linked" data-type="<?php echo esc_attr( $type ); ?>">
            <div class="nbd-product-card-image <?php echo esc_attr( $type ); ?>">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>">
                <?php endif; ?>
            </div>
            <div class="nbd-product-card-body">
                <?php if ( $pill ) : ?><span class="nbd-product-card-pill <?php echo esc_attr( $type ); ?>"><?php echo esc_html( $pill ); ?></span><?php endif; ?>
                <h3 class="nbd-product-card-title">
                    <a href="<?php echo esc_url( $link ); ?>" class="nbd-card-overlay-link"><?php echo esc_html( $post->post_title ); ?></a>
                </h3>
                <div class="nbd-product-card-footer">
                    <div class="nbd-product-card-price">
                        <?php if ( $old ) : ?><span class="strike"><?php echo esc_html( $old . $sym ); ?></span><?php endif; ?>
                        <?php if ( $cur ) : ?><span class="current"><?php echo esc_html( $cur . $sym ); ?></span><?php endif; ?>
                    </div>
                    <a href="<?php echo esc_url( $link ); ?>" class="nbd-btn-view nbd-card-z-up">Voir 👁</a>
                </div>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    /* ------------- TITRE ------------- */
    public function sc_title( $atts ) {
        $atts = shortcode_atts( array( 'tag' => 'h1', 'class' => 'nbd-product-title' ), $atts );
        $pid = $this->pid();
        if ( ! $pid ) return '';
        $tag = preg_match( '/^h[1-6]$/', $atts['tag'] ) ? $atts['tag'] : 'h1';
        return '<div class="nbd-masterclass"><' . $tag . ' class="' . esc_attr( $atts['class'] ) . '">' . esc_html( get_the_title( $pid ) ) . '</' . $tag . '></div>';
    }

    /* ------------- DESCRIPTION COURTE (HTML) ------------- */
    public function sc_short_description( $atts ) {
        $pid = $this->pid();
        $desc = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_short_description' );
        if ( ! $desc ) return '';
        return '<div class="nbd-masterclass"><div class="nbd-product-section">' . wpautop( wp_kses_post( $desc ) ) . '</div></div>';
    }

    private function pid() {
        return get_the_ID() ?: ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 );
    }

    /* ------------- HERO IMAGE ------------- */
    public function sc_hero( $atts ) {
        $pid = $this->pid();
        $img_id = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_hero_image' );
        $url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
        $alt = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : get_the_title( $pid );

        ob_start(); ?>
        <div class="nbd-masterclass">
            <div class="nbd-product-hero-image">
                <?php if ( $url ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
                <?php else : ?>
                    <span style="color:#fff">Image principale non définie</span>
                <?php endif; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /* ------------- BADGE PILL ------------- */
    public function sc_badge( $atts ) {
        $pid = $this->pid();
        $badge = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_badge_pill' );
        if ( ! $badge ) return '';
        return '<div class="nbd-masterclass"><span class="nbd-product-badge-pill">' . esc_html( $badge ) . '</span></div>';
    }

    /* ------------- STICKY CARD ------------- */
    public function sc_sticky_card( $atts ) {
        $pid = $this->pid();
        if ( ! $pid ) return '';

        $img_id  = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_card_image' );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
        $badges  = (array) NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_card_badges', array() );
        $pill    = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_badge_pill' );
        $title   = get_the_title( $pid );
        $old     = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_price_old' );
        $cur     = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_price_current' );
        $sym     = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_currency' );
        $url     = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_buy_url' );
        $label   = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_buy_label' );

        ob_start(); ?>
        <div class="nbd-masterclass">
            <div class="nbd-purchase-card">
                <?php if ( $img_url ) : ?>
                    <div class="nbd-purchase-card-image">
                        <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                    </div>
                <?php endif; ?>
                <div class="nbd-purchase-card-body">
                    <?php if ( ! empty( $badges ) ) : ?>
                        <div class="nbd-purchase-card-badges">
                            <?php foreach ( $badges as $b ) :
                                if ( empty( $b['label'] ) && empty( $b['icon'] ) ) continue; ?>
                                <div class="nbd-purchase-card-badge">
                                    <div class="icon"><?php echo esc_html( $b['icon'] ?? '' ); ?></div>
                                    <div><?php echo wp_kses_post( nl2br( esc_html( $b['label'] ?? '' ) ) ); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $pill ) : ?>
                        <span class="nbd-purchase-card-pill"><?php echo esc_html( $pill ); ?></span>
                    <?php endif; ?>

                    <h3 class="nbd-purchase-card-title"><?php echo esc_html( $title ); ?></h3>

                    <?php if ( $cur || $old ) : ?>
                        <div class="nbd-purchase-card-price">
                            <?php if ( $old ) : ?><span class="nbd-price-strike"><?php echo esc_html( $old . $sym ); ?></span><?php endif; ?>
                            <?php if ( $cur ) : ?><span class="nbd-price-current"><?php echo esc_html( $cur . $sym ); ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $url ) : ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="nbd-btn-buy">
                            <?php echo esc_html( $label ?: __( 'Voir plus', 'nbd-masterclass' ) ); ?>
                            <span>👁</span>
                        </a>
                    <?php endif; ?>

                    <p class="nbd-purchase-card-footer"><?php esc_html_e( 'Paiement sécurisé · Garantie 14 jours', 'nbd-masterclass' ); ?></p>
                </div>
            </div>

            <!-- Barre mobile fixe -->
            <div class="nbd-mobile-buy-bar">
                <div>
                    <?php if ( $old ) : ?><div class="nbd-price-strike" style="font-size:13px"><?php echo esc_html( $old . $sym ); ?></div><?php endif; ?>
                    <div class="nbd-price-current" style="color:#4A1D66"><?php echo esc_html( $cur . $sym ); ?></div>
                </div>
                <?php if ( $url ) : ?>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="nbd-btn-buy">
                        <?php esc_html_e( 'Acheter', 'nbd-masterclass' ); ?> <span>👁</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /* ------------- PRICE (standalone) ------------- */
    public function sc_price( $atts ) {
        $pid = $this->pid();
        $old = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_price_old' );
        $cur = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_price_current' );
        $sym = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_currency' );
        if ( ! $cur && ! $old ) return '';
        $out = '<div class="nbd-masterclass"><div class="nbd-purchase-card-price">';
        if ( $old ) $out .= '<span class="nbd-price-strike">' . esc_html( $old . $sym ) . '</span>';
        if ( $cur ) $out .= '<span class="nbd-price-current">' . esc_html( $cur . $sym ) . '</span>';
        $out .= '</div></div>';
        return $out;
    }

    /* ------------- BUY BUTTON (standalone) ------------- */
    public function sc_buy_button( $atts ) {
        $pid   = $this->pid();
        $url   = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_buy_url' );
        $label = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_buy_label' );
        if ( ! $url ) return '';
        return '<div class="nbd-masterclass"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="nbd-btn-buy">' . esc_html( $label ?: 'Voir plus' ) . ' <span>👁</span></a></div>';
    }

    /* ------------- LEARNINGS ------------- */
    public function sc_learnings( $atts ) {
        $pid = $this->pid();
        $items = (array) NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_learnings', array() );
        return $this->render_check_list( $items );
    }

    /* ------------- INCLUDED ------------- */
    public function sc_included( $atts ) {
        $pid = $this->pid();
        $items = (array) NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_included', array() );
        return $this->render_check_list( $items );
    }

    private function render_check_list( $items ) {
        $items = array_filter( array_map( 'trim', $items ) );
        if ( empty( $items ) ) return '';
        $out = '<div class="nbd-masterclass"><ul class="nbd-check-list">';
        foreach ( $items as $i ) $out .= '<li>' . esc_html( $i ) . '</li>';
        $out .= '</ul></div>';
        return $out;
    }

    /* ------------- TRAINER ------------- */
    public function sc_trainer( $atts ) {
        $pid    = $this->pid();
        $name   = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_trainer_name' );
        $bio    = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_trainer_bio' );
        $avatar = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_trainer_avatar' );
        if ( ! $name && ! $bio ) return '';

        $avatar_url = $avatar ? wp_get_attachment_image_url( $avatar, 'thumbnail' ) : '';
        $initials   = $name ? strtoupper( substr( $name, 0, 1 ) . substr( strrchr( $name, ' ' ), 1, 1 ) ) : '';

        ob_start(); ?>
        <div class="nbd-masterclass">
            <div class="nbd-formateur-card">
                <div class="avatar">
                    <?php if ( $avatar_url ) : ?>
                        <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                    <?php else : ?>
                        <?php echo esc_html( $initials ); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ( $name ) : ?><h3><?php echo esc_html( $name ); ?></h3><?php endif; ?>
                    <?php if ( $bio ) : ?><div class="nbd-formateur-bio"><?php echo wpautop( wp_kses_post( $bio ) ); ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /* ------------- GRID (page archive + related) ------------- */
    public function sc_grid( $atts ) {
        $atts = shortcode_atts( array(
            'limit'           => -1,
            'category'        => '',
            'exclude_current' => '0',
            'show_filters'    => '0',
            'columns'         => 3,
        ), $atts, 'nbd_mc_grid' );

        $args = array(
            'post_type'      => 'page',
            'posts_per_page' => intval( $atts['limit'] ),
            'meta_query'     => array(
                array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        );
        if ( $atts['exclude_current'] === '1' ) {
            $args['post__not_in'] = array( get_the_ID() );
        }
        if ( $atts['category'] ) {
            $cat_clause = NBD_MC_Meta_Fields::category_meta_query( $atts['category'] );
            if ( $cat_clause ) $args['meta_query'][] = $cat_clause;
        }

        $q = new WP_Query( $args );
        if ( ! $q->have_posts() ) return '<p>' . esc_html__( 'Aucune masterclass disponible.', 'nbd-masterclass' ) . '</p>';

        // Filters
        $filters_html = '';
        if ( $atts['show_filters'] === '1' ) {
            global $wpdb;
            $cats = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_nbd_mc_category' AND meta_value != '' ORDER BY meta_value" );
            if ( ! empty( $cats ) ) {
                $filters_html .= '<div class="nbd-archive-filters">';
                $filters_html .= '<button class="nbd-filter-btn active" data-filter="all">' . esc_html__( 'Toutes', 'nbd-masterclass' ) . '</button>';
                foreach ( $cats as $c ) {
                    $filters_html .= '<button class="nbd-filter-btn" data-filter="' . esc_attr( sanitize_title( $c ) ) . '">' . esc_html( $c ) . '</button>';
                }
                $filters_html .= '</div>';
            }
        }

        ob_start(); ?>
        <div class="nbd-masterclass">
            <?php echo $filters_html; ?>
            <div class="nbd-archive-grid nbd-cols-<?php echo absint( $atts['columns'] ); ?>">
                <?php while ( $q->have_posts() ) : $q->the_post();
                    $id      = get_the_ID();
                    $img_id  = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_card_image' );
                    $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
                    $pill    = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_badge_pill' );
                    $old     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_price_old' );
                    $cur     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_price_current' );
                    $sym     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_currency' );
                    $badges  = (array) NBD_MC_Meta_Fields::get( $id, '_nbd_mc_card_badges', array() );
                    $cat     = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_category' );
                ?>
                <article class="nbd-product-card nbd-card-linked" data-category="<?php echo esc_attr( sanitize_title( $cat ) ); ?>">
                    <div class="nbd-product-card-image">
                        <?php if ( $img_url ) : ?>
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php the_title_attribute(); ?>">
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $badges ) ) : ?>
                        <div class="nbd-product-card-badges">
                            <?php foreach ( $badges as $b ) :
                                if ( empty( $b['label'] ) && empty( $b['icon'] ) ) continue; ?>
                                <div class="nbd-product-card-badge">
                                    <div class="icon"><?php echo esc_html( $b['icon'] ?? '' ); ?></div>
                                    <div><?php echo wp_kses_post( nl2br( esc_html( $b['label'] ?? '' ) ) ); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="nbd-product-card-body">
                        <?php if ( $pill ) : ?><span class="nbd-product-card-pill"><?php echo esc_html( $pill ); ?></span><?php endif; ?>
                        <h3 class="nbd-product-card-title">
                            <a href="<?php the_permalink(); ?>" class="nbd-card-overlay-link"><?php the_title(); ?></a>
                        </h3>
                        <div class="nbd-product-card-footer">
                            <div class="nbd-product-card-price">
                                <?php if ( $old ) : ?><span class="strike"><?php echo esc_html( $old . $sym ); ?></span><?php endif; ?>
                                <?php if ( $cur ) : ?><span class="current"><?php echo esc_html( $cur . $sym ); ?></span><?php endif; ?>
                            </div>
                            <a href="<?php the_permalink(); ?>" class="nbd-btn-view nbd-card-z-up"><?php esc_html_e( 'Voir plus', 'nbd-masterclass' ); ?> 👁</a>
                        </div>
                    </div>
                </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
