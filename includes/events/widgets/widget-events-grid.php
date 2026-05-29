<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class NBD_Widget_Events_Grid extends Widget_Base {

    public function get_name() { return 'nbd_events_grid'; }
    public function get_title() { return __( 'Grille événements', 'nbd-masterclass' ); }
    public function get_icon() { return 'eicon-posts-grid'; }
    public function get_categories() { return array( 'nbd-masterclass' ); }
    public function get_keywords() { return array( 'événements', 'agenda', 'archive', 'liste' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Réglages', 'nbd-masterclass' ) ) );

        $this->add_control( 'mode', array(
            'label'   => __( 'Mode d\'affichage', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'upcoming',
            'options' => array(
                'upcoming' => __( 'Prochains événements uniquement', 'nbd-masterclass' ),
                'archive'  => __( 'Archive complète (à venir + passés)', 'nbd-masterclass' ),
            ),
        ) );

        $this->add_control( 'limit', array(
            'label'   => __( 'Nombre max', 'nbd-masterclass' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => 4,
            'condition' => array( 'mode' => 'upcoming' ),
        ) );

        $this->add_control( 'columns', array(
            'label'   => __( 'Colonnes', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
        ) );

        $this->add_control( 'show_filters', array(
            'label'   => __( 'Afficher les filtres', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'no',
        ) );

        $this->add_control( 'show_past', array(
            'label'   => __( 'Inclure les événements passés', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => array( 'mode' => 'archive' ),
        ) );

        $this->add_control( 'type', array(
            'label'   => __( 'Filtrer par type (optionnel)', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => array(
                ''           => __( '— Tous —', 'nbd-masterclass' ),
                'conference' => 'Conférence',
                'webinaire'  => 'Webinaire',
                'salon'      => 'Salon',
                'formation'  => 'Formation',
                'gala'       => 'Gala',
                'autre'      => 'Autre',
            ),
            'condition' => array( 'mode' => 'upcoming' ),
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        if ( $s['mode'] === 'archive' ) {
            $atts = sprintf(
                '[nbd_events_archive columns="%d" show_filters="%s" show_past="%s"]',
                intval( $s['columns'] ),
                $s['show_filters'] === 'yes' ? '1' : '0',
                $s['show_past'] === 'yes' ? '1' : '0'
            );
        } else {
            $atts = sprintf(
                '[nbd_events_upcoming limit="%d" columns="%d" show_filters="%s" type="%s"]',
                intval( $s['limit'] ),
                intval( $s['columns'] ),
                $s['show_filters'] === 'yes' ? '1' : '0',
                esc_attr( $s['type'] )
            );
        }
        echo do_shortcode( $atts );
    }
}
