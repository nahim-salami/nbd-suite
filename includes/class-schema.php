<?php
/**
 * Schema.org JSON-LD (Course / Product) injecté en <head>.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Schema {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output' ), 5 );
    }

    public function output() {
        if ( get_option( 'nbd_mc_enable_schema', '1' ) !== '1' ) return;
        if ( ! is_singular( 'page' ) ) return;
        $pid = get_queried_object_id();
        if ( get_post_meta( $pid, '_nbd_mc_is_masterclass', true ) !== '1' ) return;

        $title       = get_the_title( $pid );
        $url         = get_permalink( $pid );
        $desc        = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_seo_description' )
                       ?: NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_short_description' );
        $price       = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_price_current' );
        $currency    = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_currency' );
        $currency_iso = $this->currency_to_iso( $currency );
        $img_id      = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_hero_image' );
        $img_url     = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
        $trainer     = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_trainer_name' );
        $buy_url     = NBD_MC_Meta_Fields::get( $pid, '_nbd_mc_buy_url' );

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Course',
            'name'        => $title,
            'description' => wp_strip_all_tags( $desc ),
            'url'         => $url,
            'provider'    => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url(),
            ),
            'hasCourseInstance' => array(
                '@type'        => 'CourseInstance',
                'courseMode'   => 'online',
                'courseWorkload' => 'PT4H',
            ),
        );

        if ( $img_url ) $schema['image'] = $img_url;
        if ( $trainer ) {
            $schema['instructor'] = array(
                '@type' => 'Person',
                'name'  => $trainer,
            );
        }
        if ( $price ) {
            $schema['offers'] = array(
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $currency_iso,
                'availability'  => 'https://schema.org/InStock',
                'url'           => $buy_url ?: $url,
            );
        }

        echo "\n<!-- NBD Masterclass schema -->\n";
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    private function currency_to_iso( $symbol ) {
        $map = array( '€' => 'EUR', '$' => 'USD', '£' => 'GBP', 'CHF' => 'CHF', '¥' => 'JPY' );
        return $map[ $symbol ] ?? 'EUR';
    }
}
