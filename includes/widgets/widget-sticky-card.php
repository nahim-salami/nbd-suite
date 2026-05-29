<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class NBD_MC_Widget_Sticky_Card extends Widget_Base {

    public function get_name() { return 'nbd_mc_sticky_card'; }
    public function get_title() { return __( 'Card achat Masterclass', 'nbd-masterclass' ); }
    public function get_icon() { return 'eicon-price-table'; }
    public function get_categories() { return array( 'nbd-masterclass' ); }
    public function get_keywords() { return array( 'masterclass', 'card', 'sticky', 'achat', 'prix' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array(
            'label' => __( 'Source des données', 'nbd-masterclass' ),
        ) );

        $this->add_control( 'source', array(
            'label'   => __( 'Page source', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'current',
            'options' => array(
                'current' => __( 'Page courante (auto)', 'nbd-masterclass' ),
                'manual'  => __( 'Choisir une masterclass', 'nbd-masterclass' ),
            ),
        ) );

        $this->add_control( 'masterclass_id', array(
            'label'   => __( 'Masterclass', 'nbd-masterclass' ),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->get_masterclass_options(),
            'condition' => array( 'source' => 'manual' ),
        ) );

        $this->add_control( 'sticky_info', array(
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => '<div style="background:#F3E8FF;padding:12px;border-radius:6px;font-size:12px">💡 Pour activer le sticky scroll, configurez la <strong>colonne parente</strong> en mode "Sticky" dans Elementor Pro (Avancé → Effets de mouvement → Sticky).</div>',
        ) );

        $this->end_controls_section();
    }

    private function get_masterclass_options() {
        $options = array( '' => __( '— Sélectionner —', 'nbd-masterclass' ) );
        $q = get_posts( array(
            'post_type'   => 'page',
            'numberposts' => -1,
            'meta_key'    => '_nbd_mc_is_masterclass',
            'meta_value'  => '1',
        ) );
        foreach ( $q as $p ) $options[ $p->ID ] = $p->post_title;
        return $options;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if ( $settings['source'] === 'manual' && ! empty( $settings['masterclass_id'] ) ) {
            // Forcer le contexte pour le shortcode
            global $post;
            $original = $post;
            $post = get_post( $settings['masterclass_id'] );
            setup_postdata( $post );
            echo do_shortcode( '[nbd_mc_sticky_card]' );
            wp_reset_postdata();
            $post = $original;
        } else {
            echo do_shortcode( '[nbd_mc_sticky_card]' );
        }
    }
}
