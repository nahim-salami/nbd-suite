<?php
/**
 * Intégration Elementor : widgets personnalisés "Card sticky" et "Grille masterclass".
 * Permet le drag & drop dans n'importe quelle page Elementor.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Elementor {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
    }

    public function register_category( $elements_manager ) {
        $elements_manager->add_category( 'nbd-masterclass', array(
            'title' => __( 'NBD Masterclass', 'nbd-masterclass' ),
            'icon'  => 'fa fa-graduation-cap',
        ) );
    }

    public function register_widgets( $widgets_manager ) {
        require_once NBD_MC_PATH . 'includes/widgets/widget-sticky-card.php';
        require_once NBD_MC_PATH . 'includes/widgets/widget-grid.php';

        $widgets_manager->register( new NBD_MC_Widget_Sticky_Card() );
        $widgets_manager->register( new NBD_MC_Widget_Grid() );
    }
}
