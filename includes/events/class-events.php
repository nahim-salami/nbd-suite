<?php
/**
 * Module Événements — Custom Post Type + helpers communs.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_Events {

    const CPT = 'nbd_event';

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'register_meta' ) );
    }

    public function register_cpt() {
        $slug = get_option( 'nbd_events_slug', 'evenements' );

        register_post_type( self::CPT, array(
            'labels' => array(
                'name'                => __( 'Événements', 'nbd-masterclass' ),
                'singular_name'       => __( 'Événement', 'nbd-masterclass' ),
                'add_new'             => __( 'Ajouter', 'nbd-masterclass' ),
                'add_new_item'        => __( 'Ajouter un événement', 'nbd-masterclass' ),
                'edit_item'           => __( 'Modifier l\'événement', 'nbd-masterclass' ),
                'all_items'           => __( 'Tous les événements', 'nbd-masterclass' ),
                'menu_name'           => __( 'Événements', 'nbd-masterclass' ),
            ),
            'public'              => true,
            'has_archive'         => false,
            'rewrite'             => array( 'slug' => $slug, 'with_front' => false ),
            'show_in_rest'        => true,
            'menu_position'       => 26,
            'menu_icon'           => 'dashicons-calendar-alt',
            'show_in_menu'        => false, // Géré par notre menu custom
            'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'elementor', 'page-attributes' ),
            'taxonomies'          => array(),
        ) );
    }

    public function register_meta() {
        foreach ( self::fields() as $key => $config ) {
            register_post_meta( self::CPT, $key, array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ) );
        }
    }

    public static function fields() {
        return array(
            '_nbd_event_image'          => array( 'type' => 'image',    'label' => 'Affiche / Image' ),
            '_nbd_event_date_start'     => array( 'type' => 'datetime', 'label' => 'Date de début' ),
            '_nbd_event_date_end'       => array( 'type' => 'datetime', 'label' => 'Date de fin (optionnelle)' ),
            '_nbd_event_time_start'     => array( 'type' => 'text',     'label' => 'Heure de début' ),
            '_nbd_event_time_end'       => array( 'type' => 'text',     'label' => 'Heure de fin' ),
            '_nbd_event_location'       => array( 'type' => 'text',     'label' => 'Lieu' ),
            '_nbd_event_format'         => array( 'type' => 'select',   'label' => 'Format', 'options' => array(
                'presentiel' => 'Présentiel', 'online' => 'En ligne', 'hybride' => 'Hybride',
            ) ),
            '_nbd_event_type'           => array( 'type' => 'select',   'label' => 'Type', 'options' => array(
                'conference' => 'Conférence', 'webinaire' => 'Webinaire', 'salon' => 'Salon',
                'formation'  => 'Formation', 'gala' => 'Gala', 'autre' => 'Autre',
            ) ),
            '_nbd_event_role'           => array( 'type' => 'select',   'label' => 'Rôle du Dr', 'options' => array(
                'intervenante'  => 'Intervenante', 'animatrice' => 'Animatrice',
                'formatrice'    => 'Formatrice',   'participante' => 'Participante',
                'organisatrice' => 'Organisatrice',
            ) ),
            '_nbd_event_short_desc'     => array( 'type' => 'textarea', 'label' => 'Description courte' ),
            '_nbd_event_register_url'   => array( 'type' => 'url',      'label' => 'URL d\'inscription (externe)' ),
            '_nbd_event_register_label' => array( 'type' => 'text',     'label' => 'Texte bouton inscription', 'default' => 'Réserver une place' ),
            '_nbd_event_featured'       => array( 'type' => 'flag',     'label' => 'À la une (hero home)' ),
            '_nbd_event_seo_title'      => array( 'type' => 'text',     'label' => 'Titre SEO' ),
            '_nbd_event_seo_description'=> array( 'type' => 'textarea', 'label' => 'Description SEO' ),
        );
    }

    public static function get_meta( $post_id, $key, $default = '' ) {
        $value = get_post_meta( $post_id, $key, true );
        if ( $value === '' ) {
            $f = self::fields();
            if ( isset( $f[ $key ]['default'] ) ) return $f[ $key ]['default'];
            return $default;
        }
        return $value;
    }

    /* -------------------------------------------------
       FORMATAGE DATES (i18n FR)
       ------------------------------------------------- */
    public static function date_day( $post_id ) {
        $d = self::get_meta( $post_id, '_nbd_event_date_start' );
        if ( ! $d ) return '';
        return date_i18n( 'j', strtotime( $d ) );
    }

    public static function date_month_short( $post_id ) {
        $d = self::get_meta( $post_id, '_nbd_event_date_start' );
        if ( ! $d ) return '';
        $months = array(
            1 => 'Janv', 2 => 'Fév', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
        );
        $m = (int) date( 'n', strtotime( $d ) );
        return $months[ $m ] ?? '';
    }

    public static function date_full( $post_id ) {
        $d = self::get_meta( $post_id, '_nbd_event_date_start' );
        if ( ! $d ) return '';
        return date_i18n( get_option( 'date_format', 'j F Y' ), strtotime( $d ) );
    }

    public static function is_past( $post_id ) {
        $d = self::get_meta( $post_id, '_nbd_event_date_end' );
        if ( ! $d ) $d = self::get_meta( $post_id, '_nbd_event_date_start' );
        if ( ! $d ) return false;
        return strtotime( $d ) < strtotime( 'today' );
    }

    /* -------------------------------------------------
       LABELS lisibles
       ------------------------------------------------- */
    public static function format_label( $key ) {
        $labels = array( 'presentiel' => 'Présentiel', 'online' => 'En ligne', 'hybride' => 'Hybride' );
        return $labels[ $key ] ?? '';
    }

    public static function format_icon( $key ) {
        return array( 'presentiel' => '📍', 'online' => '💻', 'hybride' => '🔀' )[ $key ] ?? '';
    }

    public static function role_label( $key ) {
        $labels = array(
            'intervenante'  => 'Intervenante',
            'animatrice'    => 'Animatrice',
            'formatrice'    => 'Formatrice',
            'participante'  => 'Participante',
            'organisatrice' => 'Organisatrice',
        );
        return $labels[ $key ] ?? '';
    }

    public static function role_icon( $key ) {
        return array(
            'intervenante'  => '⭐', 'animatrice' => '🎤', 'formatrice' => '🎓',
            'participante'  => '👤', 'organisatrice' => '🏆',
        )[ $key ] ?? '';
    }

    public static function type_label( $key ) {
        $labels = array(
            'conference' => 'Conférence', 'webinaire' => 'Webinaire', 'salon' => 'Salon',
            'formation'  => 'Formation',  'gala'      => 'Gala',     'autre' => 'Autre',
        );
        return $labels[ $key ] ?? '';
    }

    /* -------------------------------------------------
       QUERY HELPERS
       ------------------------------------------------- */
    public static function query_upcoming( $args = array() ) {
        $defaults = array(
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'meta_key'       => '_nbd_event_date_start',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_nbd_event_date_start',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        );
        return new WP_Query( wp_parse_args( $args, $defaults ) );
    }

    public static function query_past( $args = array() ) {
        $defaults = array(
            'post_type'      => self::CPT,
            'posts_per_page' => 6,
            'meta_key'       => '_nbd_event_date_start',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => '_nbd_event_date_start',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => '<',
                    'type'    => 'DATE',
                ),
            ),
        );
        return new WP_Query( wp_parse_args( $args, $defaults ) );
    }

    public static function get_next_featured() {
        $q = self::query_upcoming( array(
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_nbd_event_date_start',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
                array(
                    'key'     => '_nbd_event_featured',
                    'value'   => '1',
                ),
            ),
        ) );
        if ( $q->have_posts() ) return $q->posts[0];

        // Fallback : prochain événement quel qu'il soit
        $q2 = self::query_upcoming( array( 'posts_per_page' => 1 ) );
        return $q2->have_posts() ? $q2->posts[0] : null;
    }
}
