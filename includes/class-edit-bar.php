<?php
/**
 * Bouton "Modifier" frontend
 *
 * - Bouton flottant en bas à droite sur les pages du plugin
 * - Entrée dans l'admin bar WP (en haut)
 * - Visible uniquement pour les utilisateurs avec la capacité edit_pages
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Edit_Bar {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 90 );
        add_action( 'wp_footer',      array( $this, 'render_floating_button' ) );
    }

    /**
     * Détecte le contexte courant et retourne le lien admin approprié.
     *
     * @return array|null array( 'url' => ..., 'label' => ..., 'icon' => ... ) ou null
     */
    private function get_edit_link() {
        if ( is_admin() ) return null;
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) return null;

        // Page détail d'un événement (CPT)
        if ( is_singular( 'nbd_event' ) ) {
            return array(
                'url'   => admin_url( 'admin.php?page=nbd-events-edit&post_id=' . get_queried_object_id() ),
                'label' => __( 'Modifier l\'événement', 'nbd-masterclass' ),
                'icon'  => '📅',
            );
        }

        if ( ! is_singular( 'page' ) ) return null;
        $pid = get_queried_object_id();
        if ( ! $pid ) return null;

        // Masterclass / Formation / Autre produit
        if ( get_post_meta( $pid, '_nbd_mc_is_masterclass', true ) === '1' ) {
            $type = get_post_meta( $pid, '_nbd_mc_product_type', true ) ?: 'masterclass';
            $slug = array(
                'formation'   => 'nbd-formations-edit',
                'masterclass' => 'nbd-masterclass-edit',
                'autre'       => 'nbd-autres-edit',
            )[ $type ] ?? 'nbd-masterclass-edit';

            $labels = array(
                'formation'   => array( 'icon' => '🎓', 'label' => 'Modifier la formation' ),
                'masterclass' => array( 'icon' => '📺', 'label' => 'Modifier la masterclass' ),
                'autre'       => array( 'icon' => '🛒', 'label' => 'Modifier le produit' ),
            );

            return array(
                'url'   => admin_url( 'admin.php?page=' . $slug . '&post_id=' . $pid ),
                'label' => $labels[ $type ]['label'],
                'icon'  => $labels[ $type ]['icon'],
            );
        }

        // Page catalogue
        if ( get_post_meta( $pid, '_nbd_is_catalog_page', true ) === '1' ) {
            return array(
                'url'   => admin_url( 'admin.php?page=nbd-catalog' ),
                'label' => __( 'Modifier le catalogue', 'nbd-masterclass' ),
                'icon'  => '📋',
            );
        }

        // Page archive masterclass
        if ( get_post_meta( $pid, '_nbd_is_masterclass_archive', true ) === '1' ) {
            return array(
                'url'   => admin_url( 'admin.php?page=nbd-masterclass-archive' ),
                'label' => __( 'Modifier l\'archive', 'nbd-masterclass' ),
                'icon'  => '🗂️',
            );
        }

        return null;
    }

    /**
     * Ajoute un nœud dans la barre d'admin WordPress (en haut de la page).
     */
    public function add_admin_bar_node( $admin_bar ) {
        $link = $this->get_edit_link();
        if ( ! $link ) return;

        $admin_bar->add_node( array(
            'id'    => 'nbd-edit-bar',
            'title' => '<span class="ab-icon" style="margin-right:6px">' . $link['icon'] . '</span>' . esc_html( $link['label'] ),
            'href'  => $link['url'],
            'meta'  => array(
                'title' => __( 'Modifier dans NBD Suite', 'nbd-masterclass' ),
                'class' => 'nbd-admin-bar-edit',
            ),
        ) );

        // Sous-menu : raccourcis utiles
        $admin_bar->add_node( array(
            'id'     => 'nbd-edit-dashboard',
            'parent' => 'nbd-edit-bar',
            'title'  => '📊 ' . __( 'Tableau de bord NBD', 'nbd-masterclass' ),
            'href'   => admin_url( 'admin.php?page=nbd-masterclass' ),
        ) );

        $admin_bar->add_node( array(
            'id'     => 'nbd-edit-wp',
            'parent' => 'nbd-edit-bar',
            'title'  => '✏️ ' . __( 'Modifier (éditeur WordPress)', 'nbd-masterclass' ),
            'href'   => admin_url( 'post.php?post=' . get_queried_object_id() . '&action=edit' ),
        ) );

        if ( did_action( 'elementor/loaded' ) ) {
            $admin_bar->add_node( array(
                'id'     => 'nbd-edit-elementor',
                'parent' => 'nbd-edit-bar',
                'title'  => '🎨 ' . __( 'Modifier (Elementor)', 'nbd-masterclass' ),
                'href'   => admin_url( 'post.php?post=' . get_queried_object_id() . '&action=elementor' ),
            ) );
        }
    }

    /**
     * Bouton flottant en bas à droite de la page.
     */
    public function render_floating_button() {
        $link = $this->get_edit_link();
        if ( ! $link ) return;
        ?>
        <div class="nbd-floating-edit-wrapper" id="nbd-floating-edit">
            <button type="button" class="nbd-floating-edit-toggle" aria-label="Ouvrir les options de modification">
                <?php echo esc_html( $link['icon'] ); ?>
            </button>
            <div class="nbd-floating-edit-menu">
                <div class="nbd-floating-edit-header">
                    <strong>NBD Suite</strong>
                    <span><?php echo esc_html( $link['label'] ); ?></span>
                </div>
                <a href="<?php echo esc_url( $link['url'] ); ?>" class="nbd-floating-edit-item nbd-floating-edit-primary">
                    <?php echo esc_html( $link['icon'] ); ?> <?php echo esc_html( $link['label'] ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . get_queried_object_id() . '&action=edit' ) ); ?>" class="nbd-floating-edit-item">
                    ✏️ Modifier (WordPress)
                </a>
                <?php if ( did_action( 'elementor/loaded' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . get_queried_object_id() . '&action=elementor' ) ); ?>" class="nbd-floating-edit-item">
                        🎨 Modifier (Elementor)
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nbd-masterclass' ) ); ?>" class="nbd-floating-edit-item">
                    📊 Tableau de bord NBD
                </a>
            </div>
        </div>
        <style>
        .nbd-floating-edit-wrapper {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999998;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .nbd-floating-edit-toggle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6B2C91, #4A1D66);
            color: white;
            border: 3px solid white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(107, 44, 145, 0.4);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .nbd-floating-edit-toggle:hover {
            transform: scale(1.08);
            box-shadow: 0 8px 28px rgba(107, 44, 145, 0.55);
        }
        .nbd-floating-edit-menu {
            display: none;
            position: absolute;
            bottom: 70px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            min-width: 260px;
            overflow: hidden;
            border: 1px solid #E9D5FF;
        }
        .nbd-floating-edit-wrapper.is-open .nbd-floating-edit-menu {
            display: block;
            animation: nbdFloatIn 0.18s ease-out;
        }
        @keyframes nbdFloatIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .nbd-floating-edit-header {
            background: linear-gradient(135deg, #6B2C91, #4A1D66);
            color: white;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .nbd-floating-edit-header strong {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .nbd-floating-edit-header span {
            font-size: 11px;
            opacity: 0.85;
        }
        .nbd-floating-edit-item {
            display: block;
            padding: 12px 16px;
            text-decoration: none !important;
            color: #1F2937 !important;
            font-size: 13px;
            font-weight: 500;
            border-bottom: 1px solid #F3F4F6;
            transition: background 0.15s;
        }
        .nbd-floating-edit-item:last-child { border-bottom: 0; }
        .nbd-floating-edit-item:hover {
            background: #FAF5FF;
            color: #6B2C91 !important;
        }
        .nbd-floating-edit-primary {
            background: #F3E8FF;
            color: #4A1D66 !important;
            font-weight: 700 !important;
        }
        .nbd-floating-edit-primary:hover {
            background: #E9D5FF !important;
        }
        @media (max-width: 600px) {
            .nbd-floating-edit-wrapper {
                bottom: 16px;
                right: 16px;
            }
            .nbd-floating-edit-toggle {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
            .nbd-floating-edit-menu {
                min-width: 220px;
                right: 0;
                bottom: 60px;
            }
        }
        </style>
        <script>
        (function(){
            var wrapper = document.getElementById('nbd-floating-edit');
            if (!wrapper) return;
            var toggle = wrapper.querySelector('.nbd-floating-edit-toggle');
            toggle.addEventListener('click', function(e){
                e.preventDefault();
                wrapper.classList.toggle('is-open');
            });
            document.addEventListener('click', function(e){
                if (!wrapper.contains(e.target)) wrapper.classList.remove('is-open');
            });
        })();
        </script>
        <?php
    }
}
