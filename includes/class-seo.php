<?php
/**
 * Synchronisation SEO :
 *  - Sync vers Yoast (_yoast_wpseo_title / _yoast_wpseo_metadesc)
 *  - Sync vers Rank Math (rank_math_title / rank_math_description)
 *  - Open Graph fallback (si aucun plugin SEO actif)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_SEO {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'updated_post_meta', array( $this, 'sync_seo_plugins' ), 10, 4 );
        add_action( 'added_post_meta', array( $this, 'sync_seo_plugins' ), 10, 4 );
        add_action( 'wp_head', array( $this, 'output_og' ), 4 );
    }

    public function sync_seo_plugins( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( get_post_meta( $post_id, '_nbd_mc_is_masterclass', true ) !== '1' ) return;

        if ( $meta_key === '_nbd_mc_seo_title' ) {
            if ( defined( 'WPSEO_VERSION' ) ) update_post_meta( $post_id, '_yoast_wpseo_title', $meta_value );
            if ( class_exists( 'RankMath' ) ) update_post_meta( $post_id, 'rank_math_title', $meta_value );
        }
        if ( $meta_key === '_nbd_mc_seo_description' ) {
            if ( defined( 'WPSEO_VERSION' ) ) update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_value );
            if ( class_exists( 'RankMath' ) ) update_post_meta( $post_id, 'rank_math_description', $meta_value );
        }
        if ( $meta_key === '_nbd_mc_og_image' && $meta_value ) {
            $url = wp_get_attachment_image_url( $meta_value, 'full' );
            if ( defined( 'WPSEO_VERSION' ) ) update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', $url );
            if ( class_exists( 'RankMath' ) ) update_post_meta( $post_id, 'rank_math_facebook_image', $url );
        }
    }

    public function output_og() {
        if ( get_option( 'nbd_mc_enable_og', '1' ) !== '1' ) return;
        // Skip si Yoast/Rank Math gère déjà l'OG
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) return;
        if ( ! is_singular( 'page' ) ) return;
        $pid = get_queried_object_id();
        if ( get_post_meta( $pid, '_nbd_mc_is_masterclass', true ) !== '1' ) return;

        $title = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_seo_title' ) ?: get_the_title( $pid );
        $desc  = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_seo_description' )
                 ?: NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_short_description' );
        $img   = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_og_image' )
                 ?: NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_hero_image' );
        $img_url = $img ? wp_get_attachment_image_url( $img, 'full' ) : '';
        $url   = get_permalink( $pid );

        echo "\n<!-- NBD Masterclass OG -->\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $desc )    echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
        if ( $img_url ) echo '<meta property="og:image" content="' . esc_url( $img_url ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    }
}
