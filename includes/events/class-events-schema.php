<?php
/**
 * Schema.org Event JSON-LD pour les pages détail d'événements.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_Events_Schema {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output' ), 6 );
    }

    public function output() {
        if ( ! is_singular( NBD_Events::CPT ) ) return;
        $pid = get_queried_object_id();

        $title    = get_the_title( $pid );
        $desc     = NBD_Events::get_meta( $pid, '_nbd_event_seo_description' )
                    ?: NBD_Events::get_meta( $pid, '_nbd_event_short_desc' );
        $start    = NBD_Events::get_meta( $pid, '_nbd_event_date_start' );
        $end      = NBD_Events::get_meta( $pid, '_nbd_event_date_end' );
        $t_start  = NBD_Events::get_meta( $pid, '_nbd_event_time_start' );
        $t_end    = NBD_Events::get_meta( $pid, '_nbd_event_time_end' );
        $location = NBD_Events::get_meta( $pid, '_nbd_event_location' );
        $format   = NBD_Events::get_meta( $pid, '_nbd_event_format' );
        $img_id   = NBD_Events::get_meta( $pid, '_nbd_event_image' );
        $img_url  = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
        $url      = NBD_Events::get_meta( $pid, '_nbd_event_register_url' );

        $start_iso = $start ? ( $start . ( $t_start ? 'T' . $t_start : '' ) ) : '';
        $end_iso   = $end   ? ( $end   . ( $t_end   ? 'T' . $t_end   : '' ) ) : '';

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'Event',
            'name'       => $title,
            'description'=> wp_strip_all_tags( $desc ),
            'startDate'  => $start_iso,
            'url'        => get_permalink( $pid ),
        );

        if ( $end_iso ) $schema['endDate'] = $end_iso;
        if ( $img_url ) $schema['image']   = $img_url;

        // Mode (online / offline / mixed)
        if ( $format === 'online' ) {
            $schema['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
            $schema['location'] = array(
                '@type' => 'VirtualLocation',
                'url'   => $url ?: get_permalink( $pid ),
            );
        } elseif ( $format === 'hybride' ) {
            $schema['eventAttendanceMode'] = 'https://schema.org/MixedEventAttendanceMode';
            if ( $location ) $schema['location'] = array(
                '@type' => 'Place', 'name' => $location, 'address' => $location,
            );
        } else {
            $schema['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
            if ( $location ) $schema['location'] = array(
                '@type' => 'Place', 'name' => $location, 'address' => $location,
            );
        }

        $schema['eventStatus'] = 'https://schema.org/EventScheduled';
        $schema['organizer'] = array(
            '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url(),
        );

        if ( $url ) {
            $schema['offers'] = array(
                '@type'       => 'Offer', 'url' => $url,
                'availability'=> 'https://schema.org/InStock',
                'validFrom'   => current_time( 'c' ),
            );
        }

        echo "\n<!-- NBD Event schema -->\n";
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}
