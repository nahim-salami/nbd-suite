<?php
/**
 * Plugin Name: NBD Suite
 * Plugin URI: https://naturebiodental-pro.com
 * Description: Suite tout-en-un pour NatureBioDental : gestion des masterclass (avec card sticky d'achat) et des événements (agenda automatique). Édition WordPress + Elementor, SEO et schema.org intégrés.
 * Version: 1.0.4
 * Author: Nahim Salami
 * Author URI: https://ahime.net
 * Text Domain: nbd-suite
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NBD_MC_VERSION', '1.0.4' );
define( 'NBD_MC_FILE', __FILE__ );
define( 'NBD_MC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NBD_MC_URL', plugin_dir_url( __FILE__ ) );
define( 'NBD_MC_BASENAME', plugin_basename( __FILE__ ) );

// Module Masterclass
require_once NBD_MC_PATH . 'includes/class-meta-fields.php';
require_once NBD_MC_PATH . 'includes/class-admin.php';
require_once NBD_MC_PATH . 'includes/class-page-builder.php';
require_once NBD_MC_PATH . 'includes/class-shortcodes.php';
require_once NBD_MC_PATH . 'includes/class-schema.php';
require_once NBD_MC_PATH . 'includes/class-seo.php';
require_once NBD_MC_PATH . 'includes/class-elementor.php';
require_once NBD_MC_PATH . 'includes/class-edit-bar.php';

// Module Événements
require_once NBD_MC_PATH . 'includes/events/class-events.php';
require_once NBD_MC_PATH . 'includes/events/class-events-admin.php';
require_once NBD_MC_PATH . 'includes/events/class-events-shortcodes.php';
require_once NBD_MC_PATH . 'includes/events/class-events-schema.php';

final class NBD_Masterclass_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( NBD_MC_FILE, array( $this, 'activate' ) );
    }

    public function init() {
        load_plugin_textdomain( 'nbd-masterclass', false, dirname( NBD_MC_BASENAME ) . '/languages' );

        // Masterclass
        NBD_MC_Meta_Fields::instance();
        NBD_MC_Admin::instance();
        NBD_MC_Page_Builder::instance();
        NBD_MC_Shortcodes::instance();
        NBD_MC_Schema::instance();
        NBD_MC_SEO::instance();

        // Événements
        NBD_Events::instance();
        NBD_Events_Admin::instance();
        NBD_Events_Shortcodes::instance();
        NBD_Events_Schema::instance();

        // Bouton "Modifier" frontend
        NBD_MC_Edit_Bar::instance();

        // Charger l'intégration Elementor uniquement si Elementor est actif
        if ( did_action( 'elementor/loaded' ) ) {
            NBD_MC_Elementor::instance();
            add_action( 'elementor/widgets/register', array( $this, 'register_event_widgets' ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

        // Auto-flush rewrite rules après changement de version (corrige 404 events)
        add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ), 99 );

        // Ajoute body class pour cibler les pages plugin (masque titre thème)
        add_filter( 'body_class', array( $this, 'add_body_classes' ) );

        // Force inline CSS + JS sur les pages plugin (override Hello Elementor)
        add_action( 'wp_head', array( $this, 'inline_force_styles' ), 99 );
        add_action( 'wp_footer', array( $this, 'inline_force_js' ), 99 );
    }

    /**
     * Vérifie si la page courante est gérée par le plugin
     */
    public function is_plugin_page() {
        if ( is_admin() ) return false;
        if ( is_singular( 'nbd_event' ) ) return true;
        $pid = get_queried_object_id();
        if ( ! $pid ) return false;
        return get_post_meta( $pid, '_nbd_mc_is_masterclass', true ) === '1'
            || get_post_meta( $pid, '_nbd_is_catalog_page', true ) === '1';
    }

    /**
     * Injecte des règles CSS inline dans le <head>, avec spécificité maximale.
     * Force le masquage du titre Hello Elementor + sticky de la colonne.
     */
    public function inline_force_styles() {
        if ( ! $this->is_plugin_page() ) return;
        ?>
        <style id="nbd-force-styles">
            /* ========= CONTAINER / MARGES LATÉRALES ========= */
            /* Hello Elementor ne contraint pas .wp-block-columns ni nos sections custom :
               on impose un max-width + padding latéral pour les pages plugin. */
            body.nbd-mc-page .wp-block-columns.nbd-mc-layout {
                max-width: 1200px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                padding-left: 20px !important;
                padding-right: 20px !important;
                box-sizing: border-box !important;
            }
            /* Section "Vous pourriez aussi aimer" : fond pleine largeur mais contenu contraint */
            body.nbd-mc-page .wp-block-group.nbd-related-section {
                margin-left: calc(50% - 50vw) !important;
                margin-right: calc(50% - 50vw) !important;
                padding-left: calc(50vw - 50% + 20px) !important;
                padding-right: calc(50vw - 50% + 20px) !important;
            }
            body.nbd-mc-page .wp-block-group.nbd-related-section > * {
                max-width: 1200px;
                margin-left: auto;
                margin-right: auto;
            }
            /* Mobile : padding réduit */
            @media (max-width: 781px) {
                body.nbd-mc-page .wp-block-columns.nbd-mc-layout {
                    padding-left: 16px !important;
                    padding-right: 16px !important;
                }
                body.nbd-mc-page .wp-block-group.nbd-related-section {
                    padding-left: calc(50vw - 50% + 16px) !important;
                    padding-right: calc(50vw - 50% + 16px) !important;
                }
            }

            /* ========= MASQUER LE TITRE DU THÈME (Hello Elementor & co) ========= */
            body.nbd-mc-page .page-header,
            body.nbd-mc-page .entry-header,
            body.nbd-mc-page > .site-main > .entry-header,
            body.nbd-mc-page main > .entry-header,
            body.nbd-mc-page main > article > .entry-header,
            body.nbd-mc-page main.site-main > .entry-header,
            body.nbd-mc-page main.site-main > article > .entry-header,
            body.nbd-mc-page .site-main > article > .entry-header,
            body.nbd-mc-page .page-header h1,
            body.nbd-mc-page .entry-header h1,
            body.nbd-mc-page article > header.entry-header,
            body.nbd-mc-page main h1.entry-title,
            body.nbd-mc-page article h1.entry-title { display: none !important; }

            /* ========= FORCER LE STICKY DE LA COLONNE DROITE ========= */
            body.nbd-mc-page .elementor-section.nbd-elementor-product-section { overflow: visible !important; }
            body.nbd-mc-page .elementor-section.nbd-elementor-product-section > .elementor-container {
                align-items: flex-start !important;
                overflow: visible !important;
            }
            body.nbd-mc-page .elementor-column.nbd-elementor-sticky-col {
                position: -webkit-sticky !important;
                position: sticky !important;
                top: 30px !important;
                align-self: flex-start !important;
                -webkit-align-self: flex-start !important;
                height: -moz-fit-content !important;
                height: fit-content !important;
                overflow: visible !important;
            }
            body.nbd-mc-page .nbd-elementor-sticky-col > .elementor-widget-wrap,
            body.nbd-mc-page .nbd-elementor-sticky-col > .elementor-element-populated {
                overflow: visible !important;
                height: auto !important;
                position: relative !important;
            }
            /* Annule tous les overflow:hidden des parents qui casseraient le sticky */
            body.nbd-mc-page .site-main,
            body.nbd-mc-page main,
            body.nbd-mc-page article,
            body.nbd-mc-page .entry-content,
            body.nbd-mc-page .elementor,
            body.nbd-mc-page .elementor-section { overflow: visible !important; }

            /* Pas de scrollbar sur la card (corrige bug Chrome) */
            body.nbd-mc-page .nbd-purchase-card {
                overflow: hidden !important;
                max-height: none !important;
            }

            /* Image hero : frame visuel + même margin-top que la card sticky */
            body.nbd-mc-page .nbd-product-hero-image,
            body.nbd-mc-page .elementor-widget-image.nbd-product-hero-image {
                border-radius: 16px !important;
                overflow: hidden !important;
                background: white !important;
                border: 1px solid #E9D5FF !important;
                box-shadow: 0 4px 20px rgba(107, 44, 145, 0.08) !important;
                margin-top: 24px !important;
                margin-bottom: 24px !important;
            }
            body.nbd-mc-page .nbd-product-hero-image img,
            body.nbd-mc-page .elementor-widget-image.nbd-product-hero-image img {
                border-radius: 16px !important;
                display: block !important;
                width: 100% !important;
                height: auto !important;
            }
            /* Les 2 colonnes démarrent au MÊME top : 1re widget margin-top: 24px des deux côtés */
            body.nbd-mc-page .nbd-elementor-product-section .elementor-column .elementor-widget-wrap > :first-child,
            body.nbd-mc-page .nbd-elementor-product-section .elementor-column .elementor-element-populated > :first-child,
            body.nbd-mc-page .wp-block-columns.nbd-mc-layout > .wp-block-column > :first-child {
                margin-top: 24px !important;
                padding-top: 0 !important;
            }

            /* Mobile : désactive le sticky */
            @media (max-width: 781px) {
                body.nbd-mc-page .elementor-column.nbd-elementor-sticky-col {
                    position: static !important;
                    top: auto !important;
                }
            }
        </style>
        <?php
    }

    /**
     * JS de secours : auto-détecte la sticky card + masque titre thème
     * Fonctionne MÊME SANS régénération du contenu.
     */
    public function inline_force_js() {
        if ( ! $this->is_plugin_page() ) return;
        ?>
        <script id="nbd-force-js">
        (function(){
            if (!document.body.classList.contains('nbd-mc-page')) return;

            /* ========= 1. MASQUER LE TITRE DU THÈME ========= */
            function hideThemeTitle() {
                // Cible TOUS les titres du thème (h1.entry-title) hors Elementor
                document.querySelectorAll('h1.entry-title').forEach(function(h1){
                    if (h1.closest('.elementor-widget')) return; // skip notre H1
                    h1.style.setProperty('display', 'none', 'important');
                    var header = h1.closest('.entry-header, .page-header, header');
                    if (header && !header.closest('.elementor-widget')) {
                        header.style.setProperty('display', 'none', 'important');
                    }
                });
                // Cible aussi les wrappers vides
                ['.entry-header', '.page-header'].forEach(function(sel){
                    document.querySelectorAll(sel).forEach(function(el){
                        if (el.closest('.elementor-widget')) return;
                        el.style.setProperty('display', 'none', 'important');
                    });
                });
            }

            /* ========= 1.5. ALIGNEMENT IMAGE HERO + CARD ========= */
            function alignColumns() {
                // Trouve la section produit (celle qui contient la sticky card)
                var card = document.querySelector('.nbd-purchase-card');
                if (!card) return;
                var section = card.closest('.elementor-section, .e-con');
                if (!section) return;

                // Force le container à aligner ses colonnes en haut
                var containers = section.querySelectorAll('.elementor-container, .e-con-inner, .wp-block-columns');
                containers.forEach(function(c){
                    c.style.setProperty('align-items', 'flex-start', 'important');
                });

                // Force le margin-top de la 1re widget selon la colonne
                var columns = section.querySelectorAll('.elementor-column, .e-con-inner > div, .wp-block-column');
                columns.forEach(function(col){
                    var isSticky = col.classList.contains('nbd-elementor-sticky-col')
                                || col.querySelector('.nbd-purchase-card');
                    var wrap = col.querySelector('.elementor-widget-wrap, .elementor-element-populated') || col;
                    var first = wrap.firstElementChild;
                    while (first) {
                        var cs = window.getComputedStyle(first);
                        if (cs.display === 'none') {
                            first = first.nextElementSibling;
                            continue;
                        }
                        // Les 2 colonnes démarrent au même top (24px de marge)
                        first.style.setProperty('margin-top', '24px', 'important');
                        first.style.setProperty('padding-top', '0', 'important');
                        break;
                    }
                });

                // Force style "frame" sur l'image hero
                section.querySelectorAll('.nbd-product-hero-image').forEach(function(img){
                    img.style.setProperty('margin-top', '24px', 'important');
                    img.style.setProperty('border-radius', '16px', 'important');
                    img.style.setProperty('overflow', 'hidden', 'important');
                    img.style.setProperty('background', 'white', 'important');
                    img.style.setProperty('border', '1px solid #E9D5FF', 'important');
                    img.style.setProperty('box-shadow', '0 4px 20px rgba(107,44,145,.08)', 'important');
                });
            }

            /* ========= 2. AUTO-DÉTECTION + STICKY DE LA CARD ========= */
            function applySticky() {
                // Trouve la card sticky dans le DOM (générée par le shortcode)
                var cards = document.querySelectorAll('.nbd-purchase-card');
                cards.forEach(function(card){
                    // Trouve la colonne Elementor parente
                    var col = card.closest('.elementor-column');
                    if (!col) return;

                    // Ajoute la classe + applique sticky
                    col.classList.add('nbd-elementor-sticky-col');
                    col.style.setProperty('position', 'sticky', 'important');
                    col.style.setProperty('top', '30px', 'important');
                    col.style.setProperty('align-self', 'flex-start', 'important');
                    col.style.setProperty('height', 'fit-content', 'important');
                    col.style.setProperty('overflow', 'visible', 'important');

                    // Remonte la chaîne des parents et corrige les overflow:hidden
                    var p = col.parentElement;
                    while (p && p !== document.body) {
                        var cs = window.getComputedStyle(p);
                        if (cs.overflow === 'hidden' || cs.overflowY === 'hidden' || cs.overflowX === 'hidden') {
                            p.style.setProperty('overflow', 'visible', 'important');
                        }
                        // align-items flex-start sur le container Elementor
                        if (p.classList.contains('elementor-container') || p.classList.contains('e-con')) {
                            p.style.setProperty('align-items', 'flex-start', 'important');
                        }
                        // Si on a trouvé la section parente, on s'arrête
                        if (p.classList.contains('elementor-section') || p.classList.contains('e-con-full')) {
                            p.style.setProperty('overflow', 'visible', 'important');
                            break;
                        }
                        p = p.parentElement;
                    }
                });

                // En mode Gutenberg (sans Elementor) — cible directement la card
                document.querySelectorAll('.wp-block-column').forEach(function(col){
                    if (col.querySelector('.nbd-purchase-card')) {
                        col.style.setProperty('position', 'sticky', 'important');
                        col.style.setProperty('top', '30px', 'important');
                        col.style.setProperty('align-self', 'flex-start', 'important');
                        var parent = col.parentElement;
                        if (parent && parent.classList.contains('wp-block-columns')) {
                            parent.style.setProperty('align-items', 'flex-start', 'important');
                        }
                    }
                });
            }

            var isRunning = false;
            function runAll(){
                if (isRunning) return; // protection anti-récursion
                isRunning = true;
                try {
                    hideThemeTitle();
                    alignColumns();
                    applySticky();
                } finally {
                    isRunning = false;
                }
            }

            // Exécution : initial + après chargement (2 retries seulement, pas d'observer)
            runAll();
            window.addEventListener('load', function(){
                runAll();
                setTimeout(runAll, 500);
                setTimeout(runAll, 1500);
            });

            // Re-applique au resize (avec debounce)
            var resizeTimer;
            window.addEventListener('resize', function(){
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(runAll, 200);
            });
        })();
        </script>
        <?php
    }

    /**
     * Ajoute des classes au body pour identifier les pages générées par le plugin.
     * Permet de masquer le titre du thème (Hello Elementor, etc.) sur ces pages.
     */
    public function add_body_classes( $classes ) {
        if ( is_admin() ) return $classes;
        if ( is_singular( 'nbd_event' ) ) {
            $classes[] = 'nbd-mc-page';
            $classes[] = 'nbd-event-page';
        }
        if ( is_singular( 'page' ) ) {
            $pid = get_queried_object_id();
            if ( $pid && get_post_meta( $pid, '_nbd_mc_is_masterclass', true ) === '1' ) {
                $classes[] = 'nbd-mc-page';
                $classes[] = 'nbd-product-page';
            }
            if ( $pid && get_post_meta( $pid, '_nbd_is_catalog_page', true ) === '1' ) {
                $classes[] = 'nbd-mc-page';
                $classes[] = 'nbd-catalog-page';
            }
        }
        return $classes;
    }

    /**
     * Détecte un changement de version du plugin et force le flush
     * des rewrite rules — corrige les 404 sur le CPT événements.
     */
    public function maybe_flush_rewrites() {
        $stored = get_option( 'nbd_mc_rewrite_version' );
        $current = NBD_MC_VERSION . '_' . get_option( 'nbd_events_slug', 'evenements' );
        if ( $stored !== $current ) {
            flush_rewrite_rules( false );
            update_option( 'nbd_mc_rewrite_version', $current );
        }
    }

    public function enqueue_frontend() {
        // Chargé partout : les shortcodes événements peuvent être inclus dans n'importe quelle page.
        // Le fichier est léger (~15ko gzip).
        wp_enqueue_style(
            'nbd-masterclass',
            NBD_MC_URL . 'assets/css/frontend.css',
            array(),
            NBD_MC_VERSION
        );
        wp_enqueue_script(
            'nbd-masterclass',
            NBD_MC_URL . 'assets/js/frontend.js',
            array(),
            NBD_MC_VERSION,
            true
        );
    }

    public function enqueue_admin( $hook ) {
        // Détecter via $_GET['page'] : toutes nos pages admin commencent par "nbd-"
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( strpos( $page, 'nbd-' ) !== 0 ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style(
            'nbd-masterclass-admin',
            NBD_MC_URL . 'assets/css/admin.css',
            array(),
            NBD_MC_VERSION
        );
        wp_enqueue_script(
            'nbd-masterclass-admin',
            NBD_MC_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            NBD_MC_VERSION,
            true
        );
    }

    public function is_masterclass_page() {
        // Toujours charger sur les pages d'événements (CPT)
        if ( is_singular( 'nbd_event' ) ) return true;

        $id = get_queried_object_id();
        if ( ! $id ) return false;
        return get_post_meta( $id, '_nbd_mc_is_masterclass', true ) === '1'
            || get_post_meta( $id, '_nbd_is_masterclass_archive', true ) === '1';
    }

    public function register_event_widgets( $widgets_manager ) {
        require_once NBD_MC_PATH . 'includes/events/widgets/widget-event-hero.php';
        require_once NBD_MC_PATH . 'includes/events/widgets/widget-events-grid.php';
        $widgets_manager->register( new NBD_Widget_Event_Hero() );
        $widgets_manager->register( new NBD_Widget_Events_Grid() );
    }

    public function activate() {
        if ( false === get_option( 'nbd_mc_archive_page_id' ) ) {
            add_option( 'nbd_mc_archive_page_id', 0 );
        }
        flush_rewrite_rules();
    }
}

NBD_Masterclass_Plugin::instance();
