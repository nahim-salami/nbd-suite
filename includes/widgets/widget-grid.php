<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class NBD_MC_Widget_Grid extends Widget_Base {

    public function get_name() { return 'nbd_mc_grid'; }
    public function get_title() { return __( 'Grille Masterclass', 'nbd-masterclass' ); }
    public function get_icon() { return 'eicon-posts-grid'; }
    public function get_categories() { return array( 'nbd-masterclass' ); }
    public function get_keywords() { return array( 'masterclass', 'grille', 'archive', 'liste' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array(
            'label' => __( 'Réglages', 'nbd-masterclass' ),
        ) );

        $this->add_control( 'limit', array(
            'label'   => __( 'Nombre de masterclass', 'nbd-masterclass' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => -1,
            'description' => __( '-1 = toutes', 'nbd-masterclass' ),
        ) );

        $this->add_control( 'columns', array(
            'label'   => __( 'Colonnes', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'options' => array( '2' => '2', '3' => '3', '4' => '4' ),
        ) );

        $this->add_control( 'show_filters', array(
            'label'   => __( 'Afficher les filtres', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'no',
        ) );

        $this->add_control( 'category', array(
            'label'   => __( 'Filtrer par catégorie (optionnel)', 'nbd-masterclass' ),
            'type'    => Controls_Manager::TEXT,
        ) );

        $this->add_control( 'exclude_current', array(
            'label'   => __( 'Exclure la page courante', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'no',
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $atts = sprintf(
            '[nbd_mc_grid limit="%d" columns="%d" show_filters="%s" category="%s" exclude_current="%s"]',
            intval( $s['limit'] ),
            intval( $s['columns'] ),
            $s['show_filters'] === 'yes' ? '1' : '0',
            esc_attr( $s['category'] ),
            $s['exclude_current'] === 'yes' ? '1' : '0'
        );
        echo do_shortcode( $atts );
    }
}
