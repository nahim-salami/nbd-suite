<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class NBD_Widget_Event_Hero extends Widget_Base {

    public function get_name() { return 'nbd_event_hero'; }
    public function get_title() { return __( 'Prochain événement (hero)', 'nbd-masterclass' ); }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return array( 'nbd-masterclass' ); }
    public function get_keywords() { return array( 'événement', 'event', 'hero', 'agenda' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Source', 'nbd-masterclass' ) ) );

        $this->add_control( 'source', array(
            'label'   => __( 'Quel événement afficher ?', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'featured',
            'options' => array(
                'featured' => __( 'Prochain "à la une" (auto)', 'nbd-masterclass' ),
                'next'     => __( 'Prochain événement quel qu\'il soit', 'nbd-masterclass' ),
                'manual'   => __( 'Choisir un événement précis', 'nbd-masterclass' ),
                'current'  => __( 'Événement courant (page détail)', 'nbd-masterclass' ),
            ),
        ) );

        $this->add_control( 'event_id', array(
            'label'   => __( 'Événement', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->get_event_options(),
            'condition' => array( 'source' => 'manual' ),
        ) );

        $this->add_control( 'show_label', array(
            'label'   => __( 'Afficher le label "Prochain événement"', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );

        $this->end_controls_section();
    }

    private function get_event_options() {
        $opts = array( '' => __( '— Sélectionner —', 'nbd-masterclass' ) );
        $events = get_posts( array( 'post_type' => NBD_Events::CPT, 'numberposts' => -1 ) );
        foreach ( $events as $e ) $opts[ $e->ID ] = $e->post_title;
        return $opts;
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $show_label = $s['show_label'] === 'yes' ? '1' : '0';

        switch ( $s['source'] ) {
            case 'featured':
                echo do_shortcode( '[nbd_event_next_featured show_label="' . $show_label . '"]' );
                break;
            case 'next':
                $q = NBD_Events::query_upcoming( array( 'posts_per_page' => 1 ) );
                if ( $q->have_posts() ) {
                    echo do_shortcode( '[nbd_event_hero id="' . $q->posts[0]->ID . '" show_label="' . $show_label . '"]' );
                }
                break;
            case 'manual':
                if ( ! empty( $s['event_id'] ) ) {
                    echo do_shortcode( '[nbd_event_hero id="' . absint( $s['event_id'] ) . '" show_label="' . $show_label . '"]' );
                }
                break;
            case 'current':
                echo do_shortcode( '[nbd_event_hero show_label="' . $show_label . '"]' );
                break;
        }
    }
}
