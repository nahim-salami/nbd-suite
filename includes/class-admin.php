<?php
/**
 * Interface d'administration : menu, liste, formulaire de création/édition.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_MC_Admin {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Priorité 10 : menu principal + Formations + Masterclass + Autres
        add_action( 'admin_menu', array( $this, 'menu' ), 10 );
        // Priorité 25 : Catalogue (après événements à 20)
        add_action( 'admin_menu', array( $this, 'menu_catalog' ), 25 );
        // Priorité 30 : Réglages
        add_action( 'admin_menu', array( $this, 'menu_settings' ), 30 );

        add_action( 'admin_post_nbd_mc_save', array( $this, 'handle_save' ) );
        add_action( 'admin_post_nbd_mc_delete', array( $this, 'handle_delete' ) );
        add_action( 'admin_post_nbd_mc_rebuild', array( $this, 'handle_rebuild' ) );
        add_action( 'admin_post_nbd_mc_save_catalog', array( $this, 'handle_save_catalog' ) );
        add_action( 'admin_post_nbd_mc_create_catalog', array( $this, 'handle_create_catalog' ) );
        add_action( 'admin_post_nbd_mc_bulk_rebuild', array( $this, 'handle_bulk_rebuild' ) );
        add_action( 'admin_post_nbd_mc_restore', array( $this, 'handle_restore' ) );
        add_action( 'admin_post_nbd_mc_bulk_restore', array( $this, 'handle_bulk_restore' ) );
        add_action( 'admin_notices', array( $this, 'notices' ) );
    }

    public function menu() {
        // Icône SVG dent (sera teintée automatiquement par WP avec la couleur du menu admin)
        $tooth_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><path d="M16 2C13.7 2 12 3 12 3S10.3 2 8 2C5.79 2 4 3.79 4 6c0 1.5.5 3 1 5s.5 4 .5 6C5.5 19.5 6.5 22 8 22c1.2 0 1.5-2 1.7-4 .2-1.7.3-3 1.3-3h2c1 0 1.1 1.3 1.3 3 .2 2 .5 4 1.7 4 1.5 0 2.5-2.5 2.5-5 0-2 0-4 .5-6s1-3.5 1-5c0-2.21-1.79-4-4-4z"/></svg>';
        $icon_data_uri = 'data:image/svg+xml;base64,' . base64_encode( $tooth_svg );

        add_menu_page(
            __( 'NBD Suite', 'nbd-masterclass' ),
            __( 'NBD Suite', 'nbd-masterclass' ),
            'edit_pages',
            'nbd-masterclass',
            array( $this, 'page_dashboard' ),
            $icon_data_uri,
            25
        );

        // === TABLEAU DE BORD === (override de la 1re entrée auto-générée)
        add_submenu_page( 'nbd-masterclass', __( 'Tableau de bord', 'nbd-masterclass' ),
            '📊 ' . __( 'Tableau de bord', 'nbd-masterclass' ), 'edit_pages',
            'nbd-masterclass', array( $this, 'page_dashboard' ) );

        // === FORMATIONS ===
        add_submenu_page( 'nbd-masterclass', __( 'Formations', 'nbd-masterclass' ),
            '🎓 ' . __( 'Formations', 'nbd-masterclass' ), 'edit_pages',
            'nbd-formations', array( $this, 'page_list_formations' ) );
        add_submenu_page( 'nbd-masterclass', __( 'Créer une formation', 'nbd-masterclass' ),
            '➕ ' . __( 'Créer une formation', 'nbd-masterclass' ), 'edit_pages',
            'nbd-formations-edit', array( $this, 'page_edit_formation' ) );

        // === MASTERCLASS ===
        add_submenu_page( 'nbd-masterclass', __( 'Masterclass', 'nbd-masterclass' ),
            '📺 ' . __( 'Masterclass', 'nbd-masterclass' ), 'edit_pages', 'nbd-masterclass-list', array( $this, 'page_list' ) );
        add_submenu_page( 'nbd-masterclass', __( 'Créer / Éditer', 'nbd-masterclass' ),
            '➕ ' . __( 'Créer une masterclass', 'nbd-masterclass' ), 'edit_pages', 'nbd-masterclass-edit', array( $this, 'page_edit' ) );
        add_submenu_page( 'nbd-masterclass', __( 'Page archive', 'nbd-masterclass' ),
            '🗂️ ' . __( 'Page archive', 'nbd-masterclass' ), 'edit_pages', 'nbd-masterclass-archive', array( $this, 'page_archive' ) );

        // === AUTRES PRODUITS ===
        add_submenu_page( 'nbd-masterclass', __( 'Autres produits', 'nbd-masterclass' ),
            '🛒 ' . __( 'Autres produits', 'nbd-masterclass' ), 'edit_pages',
            'nbd-autres', array( $this, 'page_list_autres' ) );
        add_submenu_page( 'nbd-masterclass', __( 'Créer un produit', 'nbd-masterclass' ),
            '➕ ' . __( 'Créer un produit', 'nbd-masterclass' ), 'edit_pages',
            'nbd-autres-edit', array( $this, 'page_edit_autre' ) );
    }

    /**
     * Configuration TinyMCE complète partagée par tous les éditeurs du plugin.
     * - Couleurs (forecolor + backcolor) visibles dès la barre 1
     * - Palette personnalisée NBD (violets) + couleurs standards
     * - Tableaux, médias, sous/exposant, etc.
     */
    public static function tinymce_full_config() {
        $palette = array(
            // Palette NBD (violet)
            '6B2C91', 'Violet NBD',
            '4A1D66', 'Violet foncé',
            '9333EA', 'Violet vif',
            'F3E8FF', 'Violet clair',
            'FAF5FF', 'Violet pâle',
            // Couleurs sémantiques
            'DC2626', 'Rouge',
            'F59E0B', 'Orange',
            '10B981', 'Vert',
            '3B82F6', 'Bleu',
            // Neutres
            '000000', 'Noir',
            '1F2937', 'Gris foncé',
            '6B7280', 'Gris',
            '9CA3AF', 'Gris clair',
            'D1D5DB', 'Gris très clair',
            'FFFFFF', 'Blanc',
            // Couleurs étendues
            'B91C1C', 'Rouge foncé',
            'EF4444', 'Rouge clair',
            'D97706', 'Orange foncé',
            'FBBF24', 'Jaune',
            '059669', 'Vert foncé',
            '34D399', 'Vert clair',
            '1E40AF', 'Bleu foncé',
            '60A5FA', 'Bleu clair',
            'EC4899', 'Rose',
            '7C3AED', 'Indigo',
        );

        return array(
            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,blockquote,alignleft,aligncenter,alignright,alignjustify,link,unlink,fullscreen,wp_adv',
            'toolbar2' => 'fontsizeselect,pastetext,removeformat,charmap,outdent,indent,hr,subscript,superscript,wp_more,undo,redo,wp_help',
            'wpautop'  => true,
            'block_formats' => 'Paragraphe=p;Titre 1=h1;Titre 2=h2;Titre 3=h3;Titre 4=h4;Titre 5=h5;Titre 6=h6;Préformaté=pre;Adresse=address',
            // Palette de couleurs personnalisée
            'textcolor_map' => wp_json_encode( $palette ),
            'textcolor_rows' => 5,
            'textcolor_cols' => 6,
            'custom_colors' => true,
            'fontsize_formats' => '11px 12px 13px 14px 16px 18px 20px 24px 28px 32px 36px 48px 60px 72px',
        );
    }

    /* ============================================
       TABLEAU DE BORD
       ============================================ */
    public function page_dashboard() {
        // Compteurs
        $count = function( $type ) {
            $mq = array( array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ) );
            if ( $type === 'masterclass' ) {
                $mq[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_nbd_mc_product_type', 'value' => 'masterclass' ),
                    array( 'key' => '_nbd_mc_product_type', 'compare' => 'NOT EXISTS' ),
                );
            } else {
                $mq[] = array( 'key' => '_nbd_mc_product_type', 'value' => $type );
            }
            return count( get_posts( array(
                'post_type'      => 'page',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => $mq,
                'post_status'    => array( 'publish', 'draft' ),
            ) ) );
        };

        $n_formations  = $count( 'formation' );
        $n_masterclass = $count( 'masterclass' );
        $n_autres      = $count( 'autre' );

        $n_events_up = class_exists( 'NBD_Events' ) ? NBD_Events::query_upcoming( array( 'fields' => 'ids' ) )->found_posts : 0;
        $n_events_past = class_exists( 'NBD_Events' ) ? NBD_Events::query_past( array( 'fields' => 'ids', 'posts_per_page' => -1 ) )->found_posts : 0;

        // Derniers produits (tous types)
        $recent_products = get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => 5,
            'meta_query'     => array( array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ) ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        // Prochains événements
        $upcoming_events = class_exists( 'NBD_Events' )
            ? NBD_Events::query_upcoming( array( 'posts_per_page' => 5 ) )->posts
            : array();

        // Système — détection
        $sys_elementor      = did_action( 'elementor/loaded' );
        $sys_elementor_pro  = defined( 'ELEMENTOR_PRO_VERSION' );
        $sys_yoast          = defined( 'WPSEO_VERSION' );
        $sys_rankmath       = class_exists( 'RankMath' );
        $catalog_id         = (int) get_option( 'nbd_mc_catalog_page_id' );
        $catalog_layout     = get_option( 'nbd_mc_catalog_layout', 'A' );
        $archive_id         = (int) get_option( 'nbd_mc_archive_page_id' );

        // Liste des shortcodes
        $shortcodes = array(
            array( 'code' => '[nbd_catalog]',                          'desc' => 'Catalogue complet (formations + masterclass + autres)' ),
            array( 'code' => '[nbd_catalog layout="A"]',               'desc' => 'Forcer un layout (A, B, C ou D)' ),
            array( 'code' => '[nbd_catalog category="pro-sante"]',     'desc' => 'Catalogue filtré par catégorie (ex: pro-sante, dentiste)' ),
            array( 'code' => '[nbd_catalog category="pro-sante,dentiste"]', 'desc' => 'Filtre multi-catégories (OR entre elles)' ),
            array( 'code' => '[nbd_catalog hide_page_title="1"]',          'desc' => 'Masque le titre de page WP injecté par le thème' ),
            array( 'code' => '[nbd_catalog category="dentiste" hide_page_title="1"]', 'desc' => 'Combinaison : filtre + titre masqué' ),
            array( 'code' => '[nbd_mc_grid]',             'desc' => 'Grille de masterclass (avec filtres)' ),
            array( 'code' => '[nbd_mc_sticky_card]',      'desc' => 'Card sticky d\'achat (single masterclass)' ),
            array( 'code' => '[nbd_mc_title]',            'desc' => 'Titre dynamique du produit' ),
            array( 'code' => '[nbd_mc_short_description]','desc' => 'Description courte du produit' ),
            array( 'code' => '[nbd_mc_learnings]',        'desc' => 'Liste "Ce que vous allez apprendre"' ),
            array( 'code' => '[nbd_mc_included]',         'desc' => 'Liste "Ce qui est inclus"' ),
            array( 'code' => '[nbd_mc_trainer]',          'desc' => 'Bloc formateur (avatar + bio)' ),
            array( 'code' => '[nbd_event_next_featured]', 'desc' => 'Hero du prochain événement à la une (home)' ),
            array( 'code' => '[nbd_events_upcoming]',     'desc' => 'Grille des prochains événements' ),
            array( 'code' => '[nbd_events_archive]',      'desc' => 'Page actualité complète (à venir + passés)' ),
        );

        $url = function( $page ) { return admin_url( 'admin.php?page=' . $page ); };
        ?>
        <div class="wrap nbd-mc-wrap nbd-dashboard">

            <!-- HEADER -->
            <div class="nbd-dashboard-header">
                <div>
                    <h1>🦷 Bienvenue dans NBD Suite</h1>
                    <p>Gérez vos formations, masterclass, produits et événements depuis un point unique.</p>
                </div>
                <div class="nbd-dashboard-version">
                    <span>Version</span>
                    <strong><?php echo esc_html( NBD_MC_VERSION ); ?></strong>
                </div>
            </div>

            <!-- STATS -->
            <div class="nbd-dashboard-stats">
                <a href="<?php echo esc_url( $url( 'nbd-formations' ) ); ?>" class="nbd-stat-card">
                    <div class="nbd-stat-icon">🎓</div>
                    <div class="nbd-stat-value"><?php echo esc_html( $n_formations ); ?></div>
                    <div class="nbd-stat-label">Formations</div>
                </a>
                <a href="<?php echo esc_url( $url( 'nbd-masterclass-list' ) ); ?>" class="nbd-stat-card">
                    <div class="nbd-stat-icon">📺</div>
                    <div class="nbd-stat-value"><?php echo esc_html( $n_masterclass ); ?></div>
                    <div class="nbd-stat-label">Masterclass</div>
                </a>
                <a href="<?php echo esc_url( $url( 'nbd-autres' ) ); ?>" class="nbd-stat-card">
                    <div class="nbd-stat-icon">🛒</div>
                    <div class="nbd-stat-value"><?php echo esc_html( $n_autres ); ?></div>
                    <div class="nbd-stat-label">Autres produits</div>
                </a>
                <a href="<?php echo esc_url( $url( 'nbd-events' ) ); ?>" class="nbd-stat-card">
                    <div class="nbd-stat-icon">📅</div>
                    <div class="nbd-stat-value"><?php echo esc_html( $n_events_up ); ?></div>
                    <div class="nbd-stat-label">Événements à venir</div>
                </a>
                <a href="<?php echo esc_url( $url( 'nbd-events' ) ); ?>" class="nbd-stat-card nbd-stat-card-muted">
                    <div class="nbd-stat-icon">🗓️</div>
                    <div class="nbd-stat-value"><?php echo esc_html( $n_events_past ); ?></div>
                    <div class="nbd-stat-label">Événements passés</div>
                </a>
            </div>

            <!-- ACTIONS RAPIDES -->
            <div class="nbd-dashboard-section">
                <h2>⚡ Actions rapides</h2>
                <div class="nbd-quick-actions">
                    <a href="<?php echo esc_url( $url( 'nbd-formations-edit' ) ); ?>" class="nbd-quick-btn">
                        <span class="icon">🎓</span>
                        <span>Créer une formation</span>
                    </a>
                    <a href="<?php echo esc_url( $url( 'nbd-masterclass-edit' ) ); ?>" class="nbd-quick-btn">
                        <span class="icon">📺</span>
                        <span>Créer une masterclass</span>
                    </a>
                    <a href="<?php echo esc_url( $url( 'nbd-autres-edit' ) ); ?>" class="nbd-quick-btn">
                        <span class="icon">🛒</span>
                        <span>Créer un produit</span>
                    </a>
                    <a href="<?php echo esc_url( $url( 'nbd-events-edit' ) ); ?>" class="nbd-quick-btn">
                        <span class="icon">📅</span>
                        <span>Créer un événement</span>
                    </a>
                    <a href="<?php echo esc_url( $url( 'nbd-catalog' ) ); ?>" class="nbd-quick-btn">
                        <span class="icon">📋</span>
                        <span>Page catalogue</span>
                    </a>
                </div>
            </div>

            <!-- DERNIERS PRODUITS + PROCHAINS ÉVÉNEMENTS -->
            <div class="nbd-dashboard-row">

                <div class="nbd-dashboard-section">
                    <h2>📝 Derniers produits créés</h2>
                    <?php if ( empty( $recent_products ) ) : ?>
                        <p class="nbd-empty">Aucun produit pour le moment. Créez-en un avec les actions rapides ci-dessus.</p>
                    <?php else : ?>
                        <ul class="nbd-recent-list">
                            <?php foreach ( $recent_products as $p ) :
                                $type = NBD_MC_Meta_Fields::get( $p->ID, '_nbd_mc_product_type', 'masterclass' );
                                $conf = NBD_MC_Meta_Fields::product_type_labels()[ $type ] ?? array( 'icon' => '📺' );
                                $edit_url = $url( $this->edit_slug_for( $type ) ) . '&post_id=' . $p->ID;
                            ?>
                            <li>
                                <span class="nbd-recent-icon"><?php echo esc_html( $conf['icon'] ); ?></span>
                                <div class="nbd-recent-info">
                                    <a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $p->post_title ); ?></strong></a>
                                    <small><?php echo esc_html( get_the_date( '', $p ) ); ?></small>
                                </div>
                                <div class="nbd-recent-actions">
                                    <a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank">👁</a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="nbd-dashboard-section">
                    <h2>📅 Prochains événements</h2>
                    <?php if ( empty( $upcoming_events ) ) : ?>
                        <p class="nbd-empty">Aucun événement à venir. Créez-en un pour qu'il apparaisse sur le site.</p>
                    <?php else : ?>
                        <ul class="nbd-recent-list">
                            <?php foreach ( $upcoming_events as $p ) :
                                $edit_url = $url( 'nbd-events-edit' ) . '&post_id=' . $p->ID;
                                $day   = NBD_Events::date_day( $p->ID );
                                $month = NBD_Events::date_month_short( $p->ID );
                            ?>
                            <li>
                                <span class="nbd-recent-date">
                                    <strong><?php echo esc_html( $day ); ?></strong>
                                    <small><?php echo esc_html( $month ); ?></small>
                                </span>
                                <div class="nbd-recent-info">
                                    <a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $p->post_title ); ?></strong></a>
                                    <small><?php echo esc_html( NBD_Events::get_meta( $p->ID, '_nbd_event_location' ) ?: 'Pas de lieu' ); ?></small>
                                </div>
                                <div class="nbd-recent-actions">
                                    <a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank">👁</a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            </div>

            <!-- SHORTCODES -->
            <div class="nbd-dashboard-section">
                <h2>🏷️ Catégories existantes</h2>
                <?php $all_cats = NBD_MC_Meta_Fields::get_all_categories(); ?>
                <?php if ( empty( $all_cats ) ) : ?>
                    <p class="nbd-empty">Aucune catégorie pour le moment. Ajoutez-en lors de la création/modification d'un produit pour filtrer le catalogue.</p>
                <?php else : ?>
                    <p class="description">Cliquez pour copier le shortcode filtré correspondant.</p>
                    <div class="nbd-mc-cat-pills" style="margin-top:8px">
                        <?php foreach ( $all_cats as $slug => $count ) : ?>
                            <button type="button"
                                class="nbd-mc-cat-pill nbd-copy-btn"
                                data-copy='[nbd_catalog category="<?php echo esc_attr( $slug ); ?>"]'
                                title="Copier le shortcode filtré">
                                <?php echo esc_html( $slug ); ?> <span>(<?php echo intval( $count ); ?>)</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="nbd-dashboard-section">
                <h2>📜 Shortcodes disponibles</h2>
                <p class="description">Cliquez sur un shortcode pour le copier dans le presse-papier.</p>
                <table class="nbd-shortcodes-table">
                    <thead>
                        <tr>
                            <th>Shortcode</th>
                            <th>Description</th>
                            <th width="60"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $shortcodes as $sc ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $sc['code'] ); ?></code></td>
                            <td><?php echo esc_html( $sc['desc'] ); ?></td>
                            <td>
                                <button type="button" class="button button-small nbd-copy-btn" data-copy="<?php echo esc_attr( $sc['code'] ); ?>">📋</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- RÉGÉNÉRATION EN MASSE -->
            <?php
            $total_products = $n_formations + $n_masterclass + $n_autres;
            // Compte les produits avec une sauvegarde
            $backup_count = $total_products ? count( get_posts( array(
                'post_type'      => 'page',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'meta_query'     => array(
                    array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ),
                    array( 'key' => '_nbd_mc_content_backup', 'compare' => 'EXISTS' ),
                ),
            ) ) ) : 0;
            ?>
            <?php if ( $total_products > 0 ) : ?>
            <div class="nbd-dashboard-section nbd-bulk-rebuild-section">
                <h2>🔄 Régénérer le contenu de tous les produits</h2>
                <p class="description">
                    Applique le dernier modèle (titre, description, vidéos, témoignages, sticky card…) à <strong><?php echo $total_products; ?> produit<?php echo $total_products > 1 ? 's' : ''; ?></strong> en une seule action.
                    <br>
                    🛟 <strong>Sauvegarde automatique</strong> : le contenu actuel est sauvegardé avant régénération. Vous pouvez restaurer à tout moment.
                </p>
                <div class="nbd-bulk-rebuild-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
                        <?php wp_nonce_field( 'nbd_mc_bulk_rebuild' ); ?>
                        <input type="hidden" name="action" value="nbd_mc_bulk_rebuild">
                        <input type="hidden" name="mode" value="wp">
                        <input type="hidden" name="return" value="dashboard">
                        <button type="submit" class="button button-primary"
                            onclick="return confirm('Régénérer TOUS les <?php echo $total_products; ?> produits en mode WordPress ?\n\nUne sauvegarde sera créée automatiquement.\nContinuer ?')">
                            ✏️ Tout régénérer en mode WordPress
                        </button>
                    </form>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
                        <?php wp_nonce_field( 'nbd_mc_bulk_rebuild' ); ?>
                        <input type="hidden" name="action" value="nbd_mc_bulk_rebuild">
                        <input type="hidden" name="mode" value="elementor">
                        <input type="hidden" name="return" value="dashboard">
                        <button type="submit" class="button"
                            onclick="return confirm('Régénérer TOUS les <?php echo $total_products; ?> produits en mode Elementor ?\n\nUne sauvegarde sera créée automatiquement.\nContinuer ?')">
                            🎨 Tout régénérer en mode Elementor
                        </button>
                    </form>
                </div>
                <?php if ( $backup_count > 0 ) : ?>
                    <div style="margin-top:16px;padding:12px;background:#FEF3C7;border-radius:8px;border:1px solid #FCD34D">
                        <p style="margin:0 0 8px 0;font-size:13px;color:#92400E">
                            🛟 <strong><?php echo $backup_count; ?> sauvegarde<?php echo $backup_count > 1 ? 's' : ''; ?> disponible<?php echo $backup_count > 1 ? 's' : ''; ?></strong> — vous pouvez restaurer le contenu d'avant la dernière régénération.
                        </p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
                            <?php wp_nonce_field( 'nbd_mc_bulk_restore' ); ?>
                            <input type="hidden" name="action" value="nbd_mc_bulk_restore">
                            <input type="hidden" name="return" value="dashboard">
                            <button type="submit" class="button button-small"
                                onclick="return confirm('Restaurer le contenu sauvegardé de <?php echo $backup_count; ?> produit(s) ?\n\nCela annulera la dernière régénération.')">
                                ↩️ Restaurer la dernière version
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- LIENS UTILES -->
            <div class="nbd-dashboard-row">

                <div class="nbd-dashboard-section">
                    <h2>🔗 Pages générées par le plugin</h2>
                    <ul class="nbd-links-list">
                        <li>
                            <strong>📋 Page catalogue</strong>
                            <?php if ( $catalog_id && get_post( $catalog_id ) ) : ?>
                                <span class="nbd-badge-ok">Active · Layout <?php echo esc_html( $catalog_layout ); ?></span>
                                <a href="<?php echo esc_url( get_permalink( $catalog_id ) ); ?>" target="_blank">Voir</a> ·
                                <a href="<?php echo esc_url( $url( 'nbd-catalog' ) ); ?>">Configurer</a>
                            <?php else : ?>
                                <span class="nbd-badge-warn">Non créée</span>
                                <a href="<?php echo esc_url( $url( 'nbd-catalog' ) ); ?>">Créer maintenant</a>
                            <?php endif; ?>
                        </li>
                        <li>
                            <strong>🗂️ Page archive masterclass</strong>
                            <?php if ( $archive_id && get_post( $archive_id ) ) : ?>
                                <span class="nbd-badge-ok">Active</span>
                                <a href="<?php echo esc_url( get_permalink( $archive_id ) ); ?>" target="_blank">Voir</a>
                            <?php else : ?>
                                <span class="nbd-badge-warn">Non créée</span>
                                <a href="<?php echo esc_url( $url( 'nbd-masterclass-archive' ) ); ?>">Créer</a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>

                <div class="nbd-dashboard-section">
                    <h2>ℹ️ Statut du système</h2>
                    <ul class="nbd-status-list">
                        <li><?php echo $sys_elementor ? '✅' : '⚠️'; ?> Elementor <?php echo $sys_elementor ? 'actif' : 'non détecté'; ?></li>
                        <li><?php echo $sys_elementor_pro ? '✅' : 'ℹ️'; ?> Elementor Pro <?php echo $sys_elementor_pro ? 'actif' : 'non détecté'; ?></li>
                        <li><?php echo $sys_yoast ? '✅' : ( $sys_rankmath ? '✅' : 'ℹ️' ); ?>
                            <?php
                            if ( $sys_yoast ) echo 'Yoast SEO actif';
                            elseif ( $sys_rankmath ) echo 'Rank Math actif';
                            else echo 'Aucun plugin SEO détecté';
                            ?>
                        </li>
                        <li><?php echo get_option( 'nbd_mc_enable_schema', '1' ) === '1' ? '✅' : '❌'; ?> Schema.org <?php echo get_option( 'nbd_mc_enable_schema', '1' ) === '1' ? 'activé' : 'désactivé'; ?></li>
                        <li><?php echo get_option( 'nbd_mc_enable_og', '1' ) === '1' ? '✅' : '❌'; ?> Open Graph <?php echo get_option( 'nbd_mc_enable_og', '1' ) === '1' ? 'activé' : 'désactivé'; ?></li>
                    </ul>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="nbd-dashboard-footer">
                <p>Plugin développé par <a href="https://ahime.net" target="_blank">Nahim Salami</a> · Besoin d'aide ? Consultez le fichier <code>INSTALL.md</code>.</p>
            </div>

        </div>

        <script>
        document.addEventListener('click', function(e){
            if (!e.target.matches('.nbd-copy-btn')) return;
            e.preventDefault();
            var code = e.target.dataset.copy;
            navigator.clipboard.writeText(code).then(function(){
                var orig = e.target.textContent;
                e.target.textContent = '✓';
                setTimeout(function(){ e.target.textContent = orig; }, 1500);
            });
        });
        </script>
        <?php
    }

    public function menu_catalog() {
        add_submenu_page( 'nbd-masterclass', __( 'Page catalogue', 'nbd-masterclass' ),
            '📋 ' . __( 'Page catalogue', 'nbd-masterclass' ), 'edit_pages',
            'nbd-catalog', array( $this, 'page_catalog' ) );
    }

    /* Wrappers par type — partagent le code de la masterclass */
    public function page_list_formations() { $this->page_list( 'formation' ); }
    public function page_list_autres()     { $this->page_list( 'autre' ); }
    public function page_edit_formation()  { $this->page_edit( 'formation' ); }
    public function page_edit_autre()      { $this->page_edit( 'autre' ); }

    /** Retourne le slug de la page d'édition pour un type donné */
    public function edit_slug_for( $type ) {
        return array(
            'formation'   => 'nbd-formations-edit',
            'masterclass' => 'nbd-masterclass-edit',
            'autre'       => 'nbd-autres-edit',
        )[ $type ] ?? 'nbd-masterclass-edit';
    }

    public function list_slug_for( $type ) {
        return array(
            'formation'   => 'nbd-formations',
            'masterclass' => 'nbd-masterclass-list',
            'autre'       => 'nbd-autres',
        )[ $type ] ?? 'nbd-masterclass-list';
    }

    public function menu_settings() {
        // Enregistré en priorité 30 → apparaît en dernier
        add_submenu_page( 'nbd-masterclass', __( 'Réglages NBD Suite', 'nbd-masterclass' ),
            '⚙️ ' . __( 'Réglages', 'nbd-masterclass' ), 'manage_options', 'nbd-masterclass-settings', array( $this, 'page_settings' ) );
    }

    /* --------------------------------------------------
       PAGE 1 : Liste des produits (filtrée par type)
       -------------------------------------------------- */
    public function page_list( $type = 'masterclass' ) {
        // Validation : forcer un type valide
        if ( ! in_array( $type, array( 'formation', 'masterclass', 'autre' ), true ) ) {
            $type = 'masterclass';
        }

        $types  = NBD_MC_Meta_Fields::product_type_labels();
        $config = $types[ $type ];

        $create_label = array(
            'formation'   => 'une formation',
            'masterclass' => 'une masterclass',
            'autre'       => 'un produit',
        )[ $type ];

        $meta_query = array( array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ) );
        if ( $type === 'masterclass' ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => '_nbd_mc_product_type', 'value' => 'masterclass' ),
                array( 'key' => '_nbd_mc_product_type', 'compare' => 'NOT EXISTS' ),
            );
        } else {
            $meta_query[] = array( 'key' => '_nbd_mc_product_type', 'value' => $type );
        }

        $q = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 50,
            'meta_query'     => $meta_query,
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        $edit_slug = $this->edit_slug_for( $type );

        // Compte total tous types pour diagnostic
        $total_all = count( get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ) ),
        ) ) );
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html( $config['icon'] . ' ' . $config['plural'] ); ?>
                <span style="font-size:14px;font-weight:400;color:#6B7280">(<?php echo intval( $q->found_posts ); ?>)</span>
            </h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $edit_slug ) ); ?>" class="page-title-action">
                + <?php echo esc_html( 'Créer ' . $create_label ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ( ! $q->have_posts() ) : ?>
                <div class="nbd-mc-empty">
                    <p><strong><?php printf( esc_html__( 'Aucun(e) %s pour le moment.', 'nbd-masterclass' ), esc_html( strtolower( $config['plural'] ) ) ); ?></strong></p>
                    <p>
                        <?php printf(
                            esc_html__( 'Cliquez sur « + Créer %s » en haut pour commencer.', 'nbd-masterclass' ),
                            esc_html( $create_label )
                        ); ?>
                    </p>
                    <?php if ( $total_all > 0 && $type !== 'masterclass' ) : ?>
                        <p style="margin-top:16px;padding:12px;background:#FEF3C7;border-radius:6px;font-size:13px;color:#92400E">
                            💡 <strong><?php echo intval( $total_all ); ?> produits</strong> existent au total dans la base.
                            Pour les voir : <a href="<?php echo esc_url( admin_url( 'admin.php?page=nbd-masterclass-list' ) ); ?>">📺 Masterclass</a>.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80"><?php esc_html_e( 'Image', 'nbd-masterclass' ); ?></th>
                            <th><?php esc_html_e( 'Titre', 'nbd-masterclass' ); ?></th>
                            <th width="120"><?php esc_html_e( 'Prix', 'nbd-masterclass' ); ?></th>
                            <th width="100"><?php esc_html_e( 'Statut', 'nbd-masterclass' ); ?></th>
                            <th width="240"><?php esc_html_e( 'Actions', 'nbd-masterclass' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ( $q->have_posts() ) : $q->the_post(); $id = get_the_ID(); ?>
                        <tr>
                            <td>
                                <?php
                                $img_id = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_card_image' );
                                if ( $img_id ) {
                                    echo wp_get_attachment_image( $img_id, array( 60, 60 ), false, array( 'style' => 'border-radius:8px' ) );
                                }
                                ?>
                            </td>
                            <td>
                                <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $edit_slug . '&post_id=' . $id ) ); ?>"><?php the_title(); ?></a></strong>
                                <div class="row-actions">
                                    <span><a href="<?php the_permalink(); ?>" target="_blank"><?php esc_html_e( 'Voir', 'nbd-masterclass' ); ?></a> | </span>
                                    <span><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ); ?>"><?php esc_html_e( 'Modifier dans Elementor', 'nbd-masterclass' ); ?></a></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $old = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_price_old' );
                                $cur = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_price_current' );
                                $sym = NBD_MC_Meta_Fields::get( $id, '_nbd_mc_currency' );
                                if ( $old ) echo '<s style="color:#dc2626">' . esc_html( $old . $sym ) . '</s> ';
                                if ( $cur ) echo '<strong>' . esc_html( $cur . $sym ) . '</strong>';
                                ?>
                            </td>
                            <td><?php echo esc_html( ucfirst( get_post_status() ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $edit_slug . '&post_id=' . $id ) ); ?>" class="button button-small"><?php esc_html_e( 'Métas', 'nbd-masterclass' ); ?></a>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>" class="button button-small">✏️ WP</a>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ); ?>" class="button button-small">🎨 Elementor</a>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_mc_delete&post_id=' . $id ), 'nbd_mc_delete_' . $id ) ); ?>"
                                   onclick="return confirm('Supprimer cette masterclass ?')" class="button button-small button-link-delete"><?php esc_html_e( 'Supprimer', 'nbd-masterclass' ); ?></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; wp_reset_postdata(); ?>
        </div>
        <?php
    }

    /* --------------------------------------------------
       PAGE 2 : Formulaire création / édition
       -------------------------------------------------- */
    public function page_edit( $default_type = 'masterclass' ) {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $is_edit = $post_id > 0;
        $post    = $is_edit ? get_post( $post_id ) : null;
        $current_type = $is_edit
            ? ( NBD_MC_Meta_Fields::get( $post_id, '_nbd_mc_product_type', $default_type ) ?: $default_type )
            : $default_type;
        $types = NBD_MC_Meta_Fields::product_type_labels();

        $get = function( $key, $default = '' ) use ( $post_id ) {
            return $post_id ? NBD_MC_Meta_Fields::get( $post_id, $key, $default ) : $default;
        };

        $get_image = function( $id ) {
            if ( ! $id ) return array( 'url' => '', 'id' => 0 );
            return array( 'url' => wp_get_attachment_image_url( $id, 'medium' ), 'id' => $id );
        };

        $hero      = $get_image( $get( '_nbd_mc_hero_image' ) );
        $card_img  = $get_image( $get( '_nbd_mc_card_image' ) );
        $og_img    = $get_image( $get( '_nbd_mc_og_image' ) );
        $trainer_avatar = $get_image( $get( '_nbd_mc_trainer_avatar' ) );

        $learnings  = (array) $get( '_nbd_mc_learnings', array() );
        $included   = (array) $get( '_nbd_mc_included', array() );
        $card_badges = (array) $get( '_nbd_mc_card_badges', array() );
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1>
                <?php echo $is_edit ? esc_html__( 'Modifier la masterclass', 'nbd-masterclass' ) : esc_html__( 'Créer une masterclass', 'nbd-masterclass' ); ?>
                <?php if ( $is_edit ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" class="page-title-action"><?php esc_html_e( 'Voir la page', 'nbd-masterclass' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ); ?>" class="page-title-action"><?php esc_html_e( '✏️ Modifier (WordPress)', 'nbd-masterclass' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ); ?>" class="page-title-action"><?php esc_html_e( '🎨 Modifier (Elementor)', 'nbd-masterclass' ); ?></a>
                <?php endif; ?>
            </h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nbd-mc-form">
                <?php wp_nonce_field( 'nbd_mc_save', 'nbd_mc_nonce' ); ?>
                <input type="hidden" name="action" value="nbd_mc_save">
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">

                <div class="nbd-mc-grid">

                    <!-- COLONNE PRINCIPALE -->
                    <div class="nbd-mc-col-main">

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Informations principales', 'nbd-masterclass' ); ?></h2>

                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Type de produit', 'nbd-masterclass' ); ?> *</label>
                                <select name="nbd_mc_product_type" required>
                                    <?php foreach ( $types as $key => $conf ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_type, $key ); ?>>
                                            <?php echo esc_html( $conf['icon'] . ' ' . $conf['label'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Titre', 'nbd-masterclass' ); ?> *</label>
                                <input type="text" name="post_title" required value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" placeholder="Ex: La Médecine Traditionnelle Chinoise en odontostomatologie">
                            </div>

                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Badge / Pill (au-dessus du titre)', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_badge_pill" value="<?php echo esc_attr( $get( '_nbd_mc_badge_pill' ) ); ?>" placeholder="Replay Masterclass">
                            </div>

                            <div class="nbd-mc-field nbd-mc-field-editor">
                                <label><?php esc_html_e( 'Description (éditeur complet)', 'nbd-masterclass' ); ?></label>
                                <p class="description nbd-mc-editor-hint">
                                    💡 <strong>Titres</strong> : menu déroulant <em>« Paragraphe »</em> en haut à gauche (Titre 1 à Titre 6 + Paragraphe + Préformaté). <strong>Couleurs</strong> : A▾ et 🖍 dans la barre principale. <strong>Médias</strong> : bouton « Ajouter un média ».
                                </p>
                                <?php
                                wp_editor(
                                    $get( '_nbd_mc_short_description' ),
                                    'nbdmcshortdesc',
                                    array(
                                        'textarea_name' => 'nbd_mc_short_description',
                                        'textarea_rows' => 14,
                                        'media_buttons' => true,
                                        'teeny'         => false,
                                        'drag_drop_upload' => true,
                                        'tinymce'       => $this->tinymce_full_config(),
                                        'quicktags'     => array(
                                            'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close,h2,h3,h4',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Catégories (filtrage)', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_category"
                                    id="nbd-mc-category-input"
                                    value="<?php echo esc_attr( $get( '_nbd_mc_category' ) ); ?>"
                                    placeholder="pro-sante, dentiste, patient...">
                                <p class="description">
                                    Séparez les catégories par des virgules. Elles seront utilisées pour filtrer dans <code>[nbd_catalog category="..."]</code>.
                                </p>
                                <?php
                                $existing_cats = NBD_MC_Meta_Fields::get_all_categories();
                                if ( ! empty( $existing_cats ) ) :
                                ?>
                                    <div class="nbd-mc-cat-suggestions">
                                        <strong>📌 Catégories existantes (cliquez pour ajouter) :</strong>
                                        <div class="nbd-mc-cat-pills">
                                            <?php foreach ( $existing_cats as $slug => $count ) : ?>
                                                <button type="button" class="nbd-mc-cat-pill" data-slug="<?php echo esc_attr( $slug ); ?>">
                                                    <?php echo esc_html( $slug ); ?> <span>(<?php echo intval( $count ); ?>)</span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Visuels', 'nbd-masterclass' ); ?></h2>

                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Image principale (hero, 1200×675)', 'nbd-masterclass' ); ?></label>
                                <div class="nbd-mc-media-picker" data-target="nbd_mc_hero_image">
                                    <div class="nbd-mc-preview"><?php if ( $hero['url'] ) echo '<img src="' . esc_url( $hero['url'] ) . '">'; ?></div>
                                    <input type="hidden" name="nbd_mc_hero_image" value="<?php echo esc_attr( $hero['id'] ); ?>">
                                    <button type="button" class="button nbd-mc-upload-btn"><?php esc_html_e( 'Choisir une image', 'nbd-masterclass' ); ?></button>
                                    <button type="button" class="button nbd-mc-remove-btn"><?php esc_html_e( 'Retirer', 'nbd-masterclass' ); ?></button>
                                </div>
                            </div>

                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Image de la card sticky (480×360)', 'nbd-masterclass' ); ?></label>
                                <div class="nbd-mc-media-picker" data-target="nbd_mc_card_image">
                                    <div class="nbd-mc-preview"><?php if ( $card_img['url'] ) echo '<img src="' . esc_url( $card_img['url'] ) . '">'; ?></div>
                                    <input type="hidden" name="nbd_mc_card_image" value="<?php echo esc_attr( $card_img['id'] ); ?>">
                                    <button type="button" class="button nbd-mc-upload-btn"><?php esc_html_e( 'Choisir une image', 'nbd-masterclass' ); ?></button>
                                    <button type="button" class="button nbd-mc-remove-btn"><?php esc_html_e( 'Retirer', 'nbd-masterclass' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Ce que vous allez apprendre', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-repeater" data-name="nbd_mc_learnings">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $items = ! empty( $learnings ) ? $learnings : array( '' );
                                foreach ( $items as $item ) :
                                ?>
                                    <div class="nbd-mc-repeater-item">
                                        <input type="text" name="nbd_mc_learnings[]" value="<?php echo esc_attr( $item ); ?>">
                                        <button type="button" class="button nbd-mc-remove-item">×</button>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter un point', 'nbd-masterclass' ); ?></button>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Ce qui est inclus', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-repeater" data-name="nbd_mc_included">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $items = ! empty( $included ) ? $included : array( '' );
                                foreach ( $items as $item ) :
                                ?>
                                    <div class="nbd-mc-repeater-item">
                                        <input type="text" name="nbd_mc_included[]" value="<?php echo esc_attr( $item ); ?>">
                                        <button type="button" class="button nbd-mc-remove-item">×</button>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter un élément', 'nbd-masterclass' ); ?></button>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>📚 <?php esc_html_e( 'Section Modules', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Titre de la section', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_modules_title" value="<?php echo esc_attr( $get( '_nbd_mc_modules_title' ) ); ?>" placeholder="Modules">
                            </div>
                            <?php
                            $modules = $get( '_nbd_mc_modules', array() );
                            if ( ! is_array( $modules ) ) $modules = array();
                            ?>
                            <div class="nbd-mc-repeater nbd-mc-modules-repeater" data-name="nbd_mc_modules">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $items = ! empty( $modules ) ? $modules : array( array() );
                                foreach ( $items as $m ) :
                                    if ( ! is_array( $m ) ) $m = array();
                                    $m_title = $m['title'] ?? '';
                                    $m_desc  = $m['description'] ?? '';
                                ?>
                                    <div class="nbd-mc-repeater-item nbd-mc-module-item">
                                        <div class="nbd-mc-field-row" style="gap:6px;margin:0 0 6px 0">
                                            <input type="text" name="nbd_mc_modules[__i__][title]" value="<?php echo esc_attr( $m_title ); ?>" placeholder="Titre du module (ex: Module 1 : Introduction)">
                                            <button type="button" class="button nbd-mc-remove-item">×</button>
                                        </div>
                                        <textarea name="nbd_mc_modules[__i__][description]" rows="2" placeholder="Description courte (optionnelle)" style="width:100%"><?php echo esc_textarea( $m_desc ); ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter un module', 'nbd-masterclass' ); ?></button>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>🎁 <?php esc_html_e( 'Section Bonus', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Titre de la section', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_bonus_title" value="<?php echo esc_attr( $get( '_nbd_mc_bonus_title' ) ); ?>" placeholder="Bonus">
                            </div>
                            <?php
                            $bonuses = $get( '_nbd_mc_bonus', array() );
                            if ( ! is_array( $bonuses ) ) $bonuses = array();
                            ?>
                            <div class="nbd-mc-repeater nbd-mc-bonus-repeater" data-name="nbd_mc_bonus">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $items = ! empty( $bonuses ) ? $bonuses : array( array() );
                                foreach ( $items as $b ) :
                                    if ( ! is_array( $b ) ) $b = array();
                                    $b_title = $b['title'] ?? '';
                                    $b_desc  = $b['description'] ?? '';
                                ?>
                                    <div class="nbd-mc-repeater-item nbd-mc-bonus-item">
                                        <div class="nbd-mc-field-row" style="gap:6px;margin:0 0 6px 0">
                                            <input type="text" name="nbd_mc_bonus[__i__][title]" value="<?php echo esc_attr( $b_title ); ?>" placeholder="Titre du bonus (ex: Bonus 1 : E-book offert)">
                                            <button type="button" class="button nbd-mc-remove-item">×</button>
                                        </div>
                                        <textarea name="nbd_mc_bonus[__i__][description]" rows="2" placeholder="Description courte (optionnelle)" style="width:100%"><?php echo esc_textarea( $b_desc ); ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter un bonus', 'nbd-masterclass' ); ?></button>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>🎬 <?php esc_html_e( 'Section Vidéos', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Titre de la section', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_video_title" value="<?php echo esc_attr( $get( '_nbd_mc_video_title' ) ); ?>" placeholder="Vidéos">
                            </div>
                            <?php
                            $videos = (array) $get( '_nbd_mc_videos', array() );
                            // Migration : si l'ancien champ _nbd_mc_video_url existe et qu'il n'y a pas encore de vidéos, le récupérer
                            if ( empty( $videos ) ) {
                                $legacy_url = $get( '_nbd_mc_video_url' );
                                if ( $legacy_url ) {
                                    $videos = array( array( 'title' => 'Aperçu vidéo', 'url' => $legacy_url ) );
                                }
                            }
                            ?>
                            <div class="nbd-mc-repeater nbd-mc-videos-repeater" data-name="nbd_mc_videos">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $items = ! empty( $videos ) ? $videos : array( array() );
                                foreach ( $items as $v ) :
                                    $v_title = $v['title'] ?? '';
                                    $v_url   = $v['url']   ?? '';
                                ?>
                                    <div class="nbd-mc-repeater-item nbd-mc-video-item">
                                        <div class="nbd-mc-field" style="margin-bottom:6px">
                                            <input type="text" name="nbd_mc_videos[__i__][title]" value="<?php echo esc_attr( $v_title ); ?>" placeholder="Titre de la vidéo (ex: Introduction, Démo cas clinique...)">
                                        </div>
                                        <div class="nbd-mc-field-row" style="gap:6px;margin:0">
                                            <input type="url" name="nbd_mc_videos[__i__][url]" value="<?php echo esc_attr( $v_url ); ?>" placeholder="https://www.youtube.com/watch?v=...">
                                            <button type="button" class="button nbd-mc-remove-item">×</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter une vidéo', 'nbd-masterclass' ); ?></button>
                                <p class="description" style="margin-top:8px"><?php esc_html_e( 'YouTube, Vimeo, TikTok... Laissez vide pour masquer la section.', 'nbd-masterclass' ); ?></p>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>💬 <?php esc_html_e( 'Section Témoignages', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Titre de la section', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_testimonials_title" value="<?php echo esc_attr( $get( '_nbd_mc_testimonials_title' ) ); ?>" placeholder="Ce qu'ils en disent">
                            </div>
                            <?php $testimonials = (array) $get( '_nbd_mc_testimonials', array() ); ?>
                            <div class="nbd-mc-repeater nbd-mc-testimonials-repeater" data-name="nbd_mc_testimonials">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $items = ! empty( $testimonials ) ? $testimonials : array( array() );
                                foreach ( $items as $t ) :
                                    $t_name = $t['name'] ?? '';
                                    $t_role = $t['role'] ?? '';
                                    $t_text = $t['text'] ?? '';
                                    $t_rating = $t['rating'] ?? '5';
                                ?>
                                    <div class="nbd-mc-repeater-item nbd-mc-testimonial-item">
                                        <div class="nbd-mc-field-row" style="gap:6px;margin:0 0 6px 0">
                                            <input type="text" name="nbd_mc_testimonials[__i__][name]" value="<?php echo esc_attr( $t_name ); ?>" placeholder="Nom (ex: Dr Martin)">
                                            <input type="text" name="nbd_mc_testimonials[__i__][role]" value="<?php echo esc_attr( $t_role ); ?>" placeholder="Fonction (ex: Dentiste)">
                                            <select name="nbd_mc_testimonials[__i__][rating]" style="width:80px">
                                                <?php for ( $r = 5; $r >= 1; $r-- ) : ?>
                                                    <option value="<?php echo $r; ?>" <?php selected( $t_rating, (string) $r ); ?>><?php echo str_repeat( '★', $r ); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <button type="button" class="button nbd-mc-remove-item">×</button>
                                        </div>
                                        <textarea name="nbd_mc_testimonials[__i__][text]" rows="3" placeholder="Témoignage..." style="width:100%"><?php echo esc_textarea( $t_text ); ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter un témoignage', 'nbd-masterclass' ); ?></button>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Formateur', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Nom du formateur', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_trainer_name" value="<?php echo esc_attr( $get( '_nbd_mc_trainer_name' ) ); ?>">
                            </div>
                            <div class="nbd-mc-field nbd-mc-field-editor">
                                <label><?php esc_html_e( 'Bio du formateur (éditeur complet)', 'nbd-masterclass' ); ?></label>
                                <?php
                                wp_editor(
                                    $get( '_nbd_mc_trainer_bio' ),
                                    'nbdmctrainerbio',
                                    array(
                                        'textarea_name' => 'nbd_mc_trainer_bio',
                                        'textarea_rows' => 10,
                                        'media_buttons' => true,
                                        'teeny'         => false,
                                        'drag_drop_upload' => true,
                                        'tinymce'       => $this->tinymce_full_config(),
                                        'quicktags'     => array(
                                            'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close,h2,h3,h4',
                                        ),
                                    )
                                );
                                ?>
                            </div>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Avatar du formateur', 'nbd-masterclass' ); ?></label>
                                <div class="nbd-mc-media-picker">
                                    <div class="nbd-mc-preview"><?php if ( $trainer_avatar['url'] ) echo '<img src="' . esc_url( $trainer_avatar['url'] ) . '">'; ?></div>
                                    <input type="hidden" name="nbd_mc_trainer_avatar" value="<?php echo esc_attr( $trainer_avatar['id'] ); ?>">
                                    <button type="button" class="button nbd-mc-upload-btn"><?php esc_html_e( 'Choisir', 'nbd-masterclass' ); ?></button>
                                    <button type="button" class="button nbd-mc-remove-btn"><?php esc_html_e( 'Retirer', 'nbd-masterclass' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'SEO', 'nbd-masterclass' ); ?></h2>
                            <p class="description"><?php esc_html_e( 'Ces champs sont auto-synchronisés avec Yoast / Rank Math si l\'un de ces plugins est actif.', 'nbd-masterclass' ); ?></p>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Titre SEO', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_seo_title" value="<?php echo esc_attr( $get( '_nbd_mc_seo_title' ) ); ?>" maxlength="60">
                                <p class="description"><?php esc_html_e( '60 caractères max', 'nbd-masterclass' ); ?></p>
                            </div>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Description SEO', 'nbd-masterclass' ); ?></label>
                                <textarea name="nbd_mc_seo_description" rows="3" maxlength="160"><?php echo esc_textarea( $get( '_nbd_mc_seo_description' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( '160 caractères max', 'nbd-masterclass' ); ?></p>
                            </div>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Image Open Graph (partage social)', 'nbd-masterclass' ); ?></label>
                                <div class="nbd-mc-media-picker">
                                    <div class="nbd-mc-preview"><?php if ( $og_img['url'] ) echo '<img src="' . esc_url( $og_img['url'] ) . '">'; ?></div>
                                    <input type="hidden" name="nbd_mc_og_image" value="<?php echo esc_attr( $og_img['id'] ); ?>">
                                    <button type="button" class="button nbd-mc-upload-btn"><?php esc_html_e( 'Choisir', 'nbd-masterclass' ); ?></button>
                                    <button type="button" class="button nbd-mc-remove-btn"><?php esc_html_e( 'Retirer', 'nbd-masterclass' ); ?></button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- COLONNE LATÉRALE -->
                    <div class="nbd-mc-col-side">

                        <div class="nbd-mc-card nbd-mc-card-primary">
                            <h2><?php esc_html_e( 'Publication', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Statut', 'nbd-masterclass' ); ?></label>
                                <select name="post_status">
                                    <option value="publish" <?php selected( $post ? $post->post_status : 'publish', 'publish' ); ?>><?php esc_html_e( 'Publié', 'nbd-masterclass' ); ?></option>
                                    <option value="draft" <?php selected( $post ? $post->post_status : '', 'draft' ); ?>><?php esc_html_e( 'Brouillon', 'nbd-masterclass' ); ?></option>
                                    <option value="private" <?php selected( $post ? $post->post_status : '', 'private' ); ?>><?php esc_html_e( 'Privé', 'nbd-masterclass' ); ?></option>
                                </select>
                            </div>
                            <?php if ( ! $is_edit ) : ?>
                                <div class="nbd-mc-field" style="background:#FAF5FF;padding:10px;border-radius:6px;margin-bottom:12px;border:1px solid #E9D5FF">
                                    <label style="margin-bottom:6px;display:block">
                                        <strong>🎨 Éditeur par défaut</strong>
                                    </label>
                                    <label style="display:block;font-weight:normal;margin-bottom:4px">
                                        <input type="radio" name="nbd_mc_editor_mode" value="wordpress" checked>
                                        <strong>WordPress</strong> (recommandé)
                                    </label>
                                    <label style="display:block;font-weight:normal">
                                        <input type="radio" name="nbd_mc_editor_mode" value="elementor">
                                        <strong>Elementor</strong>
                                    </label>
                                    <p class="description" style="margin-top:6px">Vous pourrez basculer à tout moment.</p>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="button button-primary button-hero" style="width:100%">
                                <?php echo $is_edit ? esc_html__( '💾 Enregistrer', 'nbd-masterclass' ) : esc_html__( '✨ Créer la masterclass', 'nbd-masterclass' ); ?>
                            </button>
                            <?php if ( ! $is_edit ) : ?>
                                <p class="description" style="margin-top:8px"><?php esc_html_e( 'Vous pourrez modifier le contenu avec WordPress ou Elementor.', 'nbd-masterclass' ); ?></p>
                            <?php else :
                                $has_backup = ! empty( get_post_meta( $post_id, '_nbd_mc_content_backup', true ) );
                            ?>
                                <div style="margin-top:14px;padding:10px;background:#F9FAFB;border-radius:6px;border:1px solid #E5E7EB">
                                    <strong style="font-size:12px;display:block;margin-bottom:6px">🔄 Régénérer le contenu</strong>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_mc_rebuild&mode=wp&post_id=' . $post_id ), 'nbd_mc_rebuild_' . $post_id ) ); ?>"
                                       onclick="return confirm('Cela écrasera le contenu (sauvegarde automatique créée). Continuer ?')"
                                       class="button button-small" style="width:100%;text-align:center;margin-bottom:4px">
                                        ✏️ Mode WordPress
                                    </a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_mc_rebuild&mode=elementor&post_id=' . $post_id ), 'nbd_mc_rebuild_' . $post_id ) ); ?>"
                                       onclick="return confirm('Cela basculera en mode Elementor (sauvegarde automatique créée). Continuer ?')"
                                       class="button button-small" style="width:100%;text-align:center">
                                        🎨 Mode Elementor
                                    </a>
                                    <p class="description" style="margin-top:6px;font-size:11px;margin-bottom:0">
                                        🛟 Sauvegarde auto avant régénération.
                                    </p>
                                </div>

                                <?php if ( $has_backup ) : ?>
                                <div style="margin-top:10px;padding:10px;background:#FEF3C7;border-radius:6px;border:1px solid #FCD34D">
                                    <strong style="font-size:12px;display:block;margin-bottom:6px;color:#92400E">↩️ Sauvegarde disponible</strong>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_mc_restore&post_id=' . $post_id ), 'nbd_mc_restore_' . $post_id ) ); ?>"
                                       onclick="return confirm('Restaurer le contenu de la dernière version sauvegardée ?')"
                                       class="button button-small" style="width:100%;text-align:center">
                                        ↩️ Restaurer la version précédente
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Prix & Bouton', 'nbd-masterclass' ); ?></h2>
                            <div class="nbd-mc-field-row">
                                <div class="nbd-mc-field">
                                    <label><?php esc_html_e( 'Prix barré', 'nbd-masterclass' ); ?></label>
                                    <input type="number" step="0.01" name="nbd_mc_price_old" value="<?php echo esc_attr( $get( '_nbd_mc_price_old' ) ); ?>">
                                </div>
                                <div class="nbd-mc-field">
                                    <label><?php esc_html_e( 'Prix actuel', 'nbd-masterclass' ); ?></label>
                                    <input type="number" step="0.01" name="nbd_mc_price_current" value="<?php echo esc_attr( $get( '_nbd_mc_price_current' ) ); ?>">
                                </div>
                                <div class="nbd-mc-field" style="flex:0 0 70px">
                                    <label><?php esc_html_e( 'Devise', 'nbd-masterclass' ); ?></label>
                                    <input type="text" name="nbd_mc_currency" value="<?php echo esc_attr( $get( '_nbd_mc_currency' ) ); ?>">
                                </div>
                            </div>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'URL d\'achat (System.io)', 'nbd-masterclass' ); ?> *</label>
                                <input type="url" name="nbd_mc_buy_url" value="<?php echo esc_attr( $get( '_nbd_mc_buy_url' ) ); ?>" placeholder="https://systeme.io/...">
                            </div>
                            <div class="nbd-mc-field">
                                <label><?php esc_html_e( 'Texte du bouton', 'nbd-masterclass' ); ?></label>
                                <input type="text" name="nbd_mc_buy_label" value="<?php echo esc_attr( $get( '_nbd_mc_buy_label' ) ); ?>">
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2><?php esc_html_e( 'Badges de la card (icônes)', 'nbd-masterclass' ); ?></h2>
                            <p class="description"><?php esc_html_e( 'Les 3 badges sous l\'image (accès, format, formateur).', 'nbd-masterclass' ); ?></p>
                            <div class="nbd-mc-repeater nbd-mc-badges-repeater" data-name="nbd_mc_card_badges">
                                <div class="nbd-mc-repeater-items">
                                <?php
                                $defaults = array(
                                    array( 'icon' => '📥', 'label' => 'Accès instantané' ),
                                    array( 'icon' => '🎥', 'label' => 'Format vidéo' ),
                                    array( 'icon' => '👤', 'label' => 'Par Dr Catherine ROSSI' ),
                                );
                                $items = ! empty( $card_badges ) ? $card_badges : $defaults;
                                foreach ( $items as $b ) :
                                    $icon  = isset( $b['icon'] ) ? $b['icon'] : '';
                                    $label = isset( $b['label'] ) ? $b['label'] : '';
                                ?>
                                    <div class="nbd-mc-repeater-item nbd-mc-badge-item">
                                        <input type="text" name="nbd_mc_card_badges[__i__][icon]" value="<?php echo esc_attr( $icon ); ?>" placeholder="📥" style="width:50px">
                                        <input type="text" name="nbd_mc_card_badges[__i__][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="Accès instantané">
                                        <button type="button" class="button nbd-mc-remove-item">×</button>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <button type="button" class="button nbd-mc-add-item">+ <?php esc_html_e( 'Ajouter un badge', 'nbd-masterclass' ); ?></button>
                            </div>
                        </div>

                    </div>

                </div>
            </form>
        </div>
        <?php
    }

    /* --------------------------------------------------
       PAGE CATALOGUE : choix layout + textes modifiables
       -------------------------------------------------- */
    public function page_catalog() {
        $catalog_id = (int) get_option( 'nbd_mc_catalog_page_id' );
        $page_exists = $catalog_id && get_post( $catalog_id );
        $layout = get_option( 'nbd_mc_catalog_layout', 'A' );
        $slug   = get_option( 'nbd_mc_catalog_slug', 'catalogue' );

        $sections = array(
            'formation'   => array(
                'title'    => get_option( 'nbd_mc_catalog_formation_title',    '🎓 Formations' ),
                'subtitle' => get_option( 'nbd_mc_catalog_formation_subtitle', 'Programmes longs et certifiants pour praticiens' ),
                'enabled'  => get_option( 'nbd_mc_catalog_formation_enabled', '1' ),
            ),
            'masterclass' => array(
                'title'    => get_option( 'nbd_mc_catalog_masterclass_title',    '📺 Masterclass' ),
                'subtitle' => get_option( 'nbd_mc_catalog_masterclass_subtitle', 'Replays vidéo experts, accessibles immédiatement' ),
                'enabled'  => get_option( 'nbd_mc_catalog_masterclass_enabled', '1' ),
            ),
            'autre'       => array(
                'title'    => get_option( 'nbd_mc_catalog_autre_title',    '🛒 Autres produits' ),
                'subtitle' => get_option( 'nbd_mc_catalog_autre_subtitle', 'Livres, kits et accessoires exclusifs Dr Rossi' ),
                'enabled'  => get_option( 'nbd_mc_catalog_autre_enabled', '1' ),
            ),
        );

        $hero_title    = get_option( 'nbd_mc_catalog_hero_title',    'Notre catalogue' );
        $hero_subtitle = get_option( 'nbd_mc_catalog_hero_subtitle', 'Formations certifiantes, masterclass replay et produits exclusifs pour une pratique de dentisterie holistique.' );

        $layouts = array(
            'A' => array( 'label' => 'Sections verticales',  'desc' => 'Les 3 catégories l\'une sous l\'autre. Recommandé SEO.', 'icon' => '📋' ),
            'B' => array( 'label' => 'Onglets interactifs',  'desc' => '1 catégorie à la fois. Moins de scroll.', 'icon' => '🗂️' ),
            'C' => array( 'label' => 'Filtres pills',        'desc' => 'Grille unique + boutons-filtres en haut.', 'icon' => '🔘' ),
            'D' => array( 'label' => 'Combiné',              'desc' => 'Sections + bouton "Vue filtrée" pour basculer.', 'icon' => '🎛️' ),
        );
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1>📋 <?php esc_html_e( 'Page catalogue', 'nbd-masterclass' ); ?></h1>

            <?php if ( $page_exists ) : ?>
                <div class="notice notice-success inline" style="margin:20px 0">
                    <p>
                        ✓ <?php esc_html_e( 'Page catalogue active :', 'nbd-masterclass' ); ?>
                        <strong><a href="<?php echo esc_url( get_permalink( $catalog_id ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $catalog_id ) ); ?></a></strong>
                        ·
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $catalog_id . '&action=edit' ) ); ?>">✏️ Modifier (WordPress)</a> ·
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $catalog_id . '&action=elementor' ) ); ?>">🎨 Modifier (Elementor)</a>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'nbd_mc_save_catalog' ); ?>
                <input type="hidden" name="action" value="nbd_mc_save_catalog">

                <div class="nbd-mc-grid">
                    <div class="nbd-mc-col-main">

                        <!-- ===== CHOIX DU LAYOUT ===== -->
                        <div class="nbd-mc-card">
                            <h2>🎨 Choisir un layout</h2>
                            <p class="description"><?php esc_html_e( 'Activez le mode de présentation qui vous convient. Vous pouvez changer à tout moment.', 'nbd-masterclass' ); ?></p>

                            <div class="nbd-catalog-layout-choices">
                                <?php foreach ( $layouts as $key => $l ) : ?>
                                    <label class="nbd-catalog-layout-choice <?php echo $layout === $key ? 'active' : ''; ?>">
                                        <input type="radio" name="layout" value="<?php echo esc_attr( $key ); ?>" <?php checked( $layout, $key ); ?>>
                                        <div class="layout-icon"><?php echo esc_html( $l['icon'] ); ?></div>
                                        <div class="layout-info">
                                            <strong>Variante <?php echo esc_html( $key ); ?> · <?php echo esc_html( $l['label'] ); ?></strong>
                                            <span><?php echo esc_html( $l['desc'] ); ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- ===== HERO ===== -->
                        <div class="nbd-mc-card">
                            <h2>🎯 Bandeau d'accueil</h2>
                            <div class="nbd-mc-field">
                                <label>Titre principal</label>
                                <input type="text" name="hero_title" value="<?php echo esc_attr( $hero_title ); ?>">
                            </div>
                            <div class="nbd-mc-field">
                                <label>Sous-titre</label>
                                <textarea name="hero_subtitle" rows="2"><?php echo esc_textarea( $hero_subtitle ); ?></textarea>
                            </div>
                        </div>

                        <!-- ===== SECTIONS ===== -->
                        <?php foreach ( $sections as $type => $section ) :
                            $conf = NBD_MC_Meta_Fields::product_type_labels()[ $type ]; ?>
                            <div class="nbd-mc-card">
                                <h2><?php echo esc_html( $conf['icon'] . ' Section ' . $conf['plural'] ); ?></h2>
                                <div class="nbd-mc-field">
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr( $type ); ?>_enabled" value="1" <?php checked( $section['enabled'], '1' ); ?>>
                                        Afficher cette section
                                    </label>
                                </div>
                                <div class="nbd-mc-field">
                                    <label>Titre de la section</label>
                                    <input type="text" name="<?php echo esc_attr( $type ); ?>_title" value="<?php echo esc_attr( $section['title'] ); ?>">
                                </div>
                                <div class="nbd-mc-field">
                                    <label>Sous-titre / Description courte</label>
                                    <textarea name="<?php echo esc_attr( $type ); ?>_subtitle" rows="2"><?php echo esc_textarea( $section['subtitle'] ); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>

                    <!-- COLONNE LATÉRALE -->
                    <div class="nbd-mc-col-side">

                        <div class="nbd-mc-card nbd-mc-card-primary">
                            <h2>💾 Actions</h2>
                            <button type="submit" class="button button-primary button-hero" style="width:100%">
                                💾 Enregistrer les réglages
                            </button>
                            <p class="description" style="margin-top:8px">
                                Les modifications sont appliquées immédiatement sur la page catalogue.
                            </p>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>🔗 URL de la page</h2>
                            <div class="nbd-mc-field">
                                <label>Slug</label>
                                <input type="text" name="catalog_slug" value="<?php echo esc_attr( $slug ); ?>">
                                <p class="description">URL : <code><?php echo esc_html( home_url( '/' . $slug . '/' ) ); ?></code></p>
                            </div>
                        </div>

                        <?php if ( ! $page_exists ) : ?>
                            <div class="nbd-mc-card" style="background:#FEF3C7;border-color:#FCD34D">
                                <h2>⚠️ Page non créée</h2>
                                <p style="font-size:13px">Vous devez d'abord créer la page catalogue.</p>
                            </div>
                        <?php endif; ?>

                        <div class="nbd-mc-card">
                            <h2>🔄 <?php esc_html_e( 'Page', 'nbd-masterclass' ); ?></h2>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_mc_create_catalog' ), 'nbd_mc_create_catalog' ) ); ?>"
                               onclick="return confirm('<?php echo $page_exists ? 'Régénérer la page écrasera son contenu. Continuer ?' : 'Créer la page catalogue ?'; ?>')"
                               class="button" style="width:100%;text-align:center">
                                <?php echo $page_exists ? '🔄 Régénérer la page' : '✨ Créer la page catalogue'; ?>
                            </a>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>📝 <?php esc_html_e( 'Shortcode', 'nbd-masterclass' ); ?></h2>
                            <p style="font-size:13px">Pour insérer le catalogue ailleurs :</p>
                            <code style="display:block;background:#1F2937;color:#A7F3D0;padding:10px;border-radius:6px;font-size:12px">[nbd_catalog layout="<?php echo esc_html( $layout ); ?>"]</code>
                        </div>

                    </div>
                </div>
            </form>
        </div>

        <style>
        .nbd-catalog-layout-choices { display: grid; gap: 10px; }
        .nbd-catalog-layout-choice {
            display: flex; gap: 14px; align-items: center;
            padding: 14px 18px; border: 2px solid #E5E7EB; border-radius: 10px;
            cursor: pointer; transition: all 0.2s; background: white;
        }
        .nbd-catalog-layout-choice:hover { border-color: #C4B5FD; }
        .nbd-catalog-layout-choice.active { border-color: #6B2C91; background: #FAF5FF; }
        .nbd-catalog-layout-choice input[type="radio"] { margin: 0; }
        .nbd-catalog-layout-choice .layout-icon { font-size: 28px; }
        .nbd-catalog-layout-choice .layout-info { display: flex; flex-direction: column; gap: 2px; }
        .nbd-catalog-layout-choice .layout-info strong { color: #4A1D66; font-size: 14px; }
        .nbd-catalog-layout-choice .layout-info span { font-size: 12px; color: #6B7280; }
        </style>
        <?php
    }

    public function handle_save_catalog() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        check_admin_referer( 'nbd_mc_save_catalog' );

        $layout = in_array( $_POST['layout'] ?? 'A', array( 'A', 'B', 'C', 'D' ), true ) ? $_POST['layout'] : 'A';
        update_option( 'nbd_mc_catalog_layout', $layout );
        update_option( 'nbd_mc_catalog_slug',  sanitize_title( $_POST['catalog_slug'] ?? 'catalogue' ) );

        update_option( 'nbd_mc_catalog_hero_title',    sanitize_text_field( $_POST['hero_title'] ?? '' ) );
        update_option( 'nbd_mc_catalog_hero_subtitle', sanitize_textarea_field( $_POST['hero_subtitle'] ?? '' ) );

        foreach ( array( 'formation', 'masterclass', 'autre' ) as $type ) {
            update_option( 'nbd_mc_catalog_' . $type . '_title',    sanitize_text_field( $_POST[ $type . '_title' ] ?? '' ) );
            update_option( 'nbd_mc_catalog_' . $type . '_subtitle', sanitize_textarea_field( $_POST[ $type . '_subtitle' ] ?? '' ) );
            update_option( 'nbd_mc_catalog_' . $type . '_enabled',  isset( $_POST[ $type . '_enabled' ] ) ? '1' : '0' );
        }

        // Si la page existe, mettre à jour son slug
        $catalog_id = (int) get_option( 'nbd_mc_catalog_page_id' );
        if ( $catalog_id && get_post( $catalog_id ) ) {
            $new_slug = get_option( 'nbd_mc_catalog_slug' );
            if ( get_post_field( 'post_name', $catalog_id ) !== $new_slug ) {
                wp_update_post( array( 'ID' => $catalog_id, 'post_name' => $new_slug ) );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=nbd-catalog&saved=1' ) );
        exit;
    }

    public function handle_create_catalog() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        check_admin_referer( 'nbd_mc_create_catalog' );
        NBD_MC_Page_Builder::instance()->create_or_update_catalog_page();
        wp_safe_redirect( admin_url( 'admin.php?page=nbd-catalog&catalog_created=1' ) );
        exit;
    }

    /* --------------------------------------------------
       PAGE 3 : Page archive
       -------------------------------------------------- */
    public function page_archive() {
        $archive_id = (int) get_option( 'nbd_mc_archive_page_id' );
        if ( isset( $_POST['nbd_mc_create_archive'] ) && check_admin_referer( 'nbd_mc_create_archive' ) ) {
            $archive_id = NBD_MC_Page_Builder::instance()->create_or_update_archive_page();
            update_option( 'nbd_mc_archive_page_id', $archive_id );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Page archive créée / mise à jour avec succès.', 'nbd-masterclass' ) . '</p></div>';
        }
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1><?php esc_html_e( 'Page archive (grille de toutes les masterclass)', 'nbd-masterclass' ); ?></h1>
            <div class="nbd-mc-card">
                <?php if ( $archive_id && get_post( $archive_id ) ) : ?>
                    <p><?php esc_html_e( 'Page archive actuelle :', 'nbd-masterclass' ); ?>
                        <strong><a href="<?php echo esc_url( get_permalink( $archive_id ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $archive_id ) ); ?></a></strong>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $archive_id . '&action=elementor' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Modifier dans Elementor', 'nbd-masterclass' ); ?></a>
                        <a href="<?php echo esc_url( get_permalink( $archive_id ) ); ?>" target="_blank" class="button"><?php esc_html_e( 'Voir la page', 'nbd-masterclass' ); ?></a>
                    </p>
                <?php else : ?>
                    <p><?php esc_html_e( 'Aucune page archive créée pour le moment.', 'nbd-masterclass' ); ?></p>
                <?php endif; ?>

                <form method="post" style="margin-top:20px">
                    <?php wp_nonce_field( 'nbd_mc_create_archive' ); ?>
                    <button type="submit" name="nbd_mc_create_archive" class="button button-secondary">
                        <?php echo $archive_id ? esc_html__( 'Régénérer la page archive', 'nbd-masterclass' ) : esc_html__( 'Créer la page archive', 'nbd-masterclass' ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /* --------------------------------------------------
       PAGE 4 : Réglages
       -------------------------------------------------- */
    public function page_settings() {
        if ( isset( $_POST['nbd_mc_settings'] ) && check_admin_referer( 'nbd_mc_settings' ) ) {
            update_option( 'nbd_mc_archive_slug', sanitize_title( $_POST['archive_slug'] ?? 'masterclass' ) );
            update_option( 'nbd_mc_enable_schema', isset( $_POST['enable_schema'] ) ? '1' : '0' );
            update_option( 'nbd_mc_enable_og', isset( $_POST['enable_og'] ) ? '1' : '0' );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Réglages enregistrés.', 'nbd-masterclass' ) . '</p></div>';
        }
        $archive_slug   = get_option( 'nbd_mc_archive_slug', 'masterclass' );
        $enable_schema  = get_option( 'nbd_mc_enable_schema', '1' );
        $enable_og      = get_option( 'nbd_mc_enable_og', '1' );

        // Compteur produits (pour bouton bulk)
        $total_products = count( get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'meta_query'     => array( array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ) ),
        ) ) );
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1><?php esc_html_e( 'Réglages', 'nbd-masterclass' ); ?></h1>

            <form method="post" class="nbd-mc-card">
                <?php wp_nonce_field( 'nbd_mc_settings' ); ?>
                <input type="hidden" name="nbd_mc_settings" value="1">

                <div class="nbd-mc-field">
                    <label><?php esc_html_e( 'Slug de la page archive', 'nbd-masterclass' ); ?></label>
                    <input type="text" name="archive_slug" value="<?php echo esc_attr( $archive_slug ); ?>">
                    <p class="description"><?php esc_html_e( 'URL : ', 'nbd-masterclass' ); ?><code><?php echo esc_html( home_url( '/' . $archive_slug . '/' ) ); ?></code></p>
                </div>

                <div class="nbd-mc-field">
                    <label><input type="checkbox" name="enable_schema" <?php checked( $enable_schema, '1' ); ?>> <?php esc_html_e( 'Activer le Schema.org (Course/Product) sur les pages masterclass', 'nbd-masterclass' ); ?></label>
                </div>

                <div class="nbd-mc-field">
                    <label><input type="checkbox" name="enable_og" <?php checked( $enable_og, '1' ); ?>> <?php esc_html_e( 'Activer les balises Open Graph (Facebook/LinkedIn)', 'nbd-masterclass' ); ?></label>
                </div>

                <button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'nbd-masterclass' ); ?></button>
            </form>

            <?php
            $backup_count = $total_products ? count( get_posts( array(
                'post_type'      => 'page',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'meta_query'     => array(
                    array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ),
                    array( 'key' => '_nbd_mc_content_backup', 'compare' => 'EXISTS' ),
                ),
            ) ) ) : 0;
            ?>
            <?php if ( $total_products > 0 ) : ?>
            <div class="nbd-mc-card nbd-bulk-rebuild-section" style="margin-top:24px">
                <h2 style="border-bottom:0;padding-bottom:0">🔄 <?php esc_html_e( 'Régénération en masse', 'nbd-masterclass' ); ?></h2>
                <p class="description" style="margin:8px 0 16px">
                    <?php
                    printf(
                        esc_html__( 'Applique le dernier modèle (titre, description, vidéos, témoignages, sticky card…) à %d produit(s) en une seule action.', 'nbd-masterclass' ),
                        $total_products
                    );
                    ?>
                    <br>
                    🛟 <strong>Sauvegarde automatique</strong> — le contenu actuel est sauvegardé avant régénération, restaurable à tout moment.
                </p>
                <div class="nbd-bulk-rebuild-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
                        <?php wp_nonce_field( 'nbd_mc_bulk_rebuild' ); ?>
                        <input type="hidden" name="action" value="nbd_mc_bulk_rebuild">
                        <input type="hidden" name="mode" value="wp">
                        <input type="hidden" name="return" value="settings">
                        <button type="submit" class="button button-primary"
                            onclick="return confirm('Régénérer TOUS les <?php echo $total_products; ?> produits en mode WordPress ?\n\nUne sauvegarde sera créée.\nContinuer ?')">
                            ✏️ <?php esc_html_e( 'Tout régénérer en mode WordPress', 'nbd-masterclass' ); ?>
                        </button>
                    </form>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
                        <?php wp_nonce_field( 'nbd_mc_bulk_rebuild' ); ?>
                        <input type="hidden" name="action" value="nbd_mc_bulk_rebuild">
                        <input type="hidden" name="mode" value="elementor">
                        <input type="hidden" name="return" value="settings">
                        <button type="submit" class="button"
                            onclick="return confirm('Régénérer TOUS les <?php echo $total_products; ?> produits en mode Elementor ?\n\nUne sauvegarde sera créée.\nContinuer ?')">
                            🎨 <?php esc_html_e( 'Tout régénérer en mode Elementor', 'nbd-masterclass' ); ?>
                        </button>
                    </form>
                </div>
                <?php if ( $backup_count > 0 ) : ?>
                    <div style="margin-top:16px;padding:12px;background:#FEF3C7;border-radius:8px;border:1px solid #FCD34D">
                        <p style="margin:0 0 8px 0;font-size:13px;color:#92400E">
                            🛟 <strong><?php echo $backup_count; ?> sauvegarde<?php echo $backup_count > 1 ? 's' : ''; ?> disponible<?php echo $backup_count > 1 ? 's' : ''; ?></strong> — restaurez si la régénération ne convient pas.
                        </p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
                            <?php wp_nonce_field( 'nbd_mc_bulk_restore' ); ?>
                            <input type="hidden" name="action" value="nbd_mc_bulk_restore">
                            <input type="hidden" name="return" value="settings">
                            <button type="submit" class="button button-small"
                                onclick="return confirm('Restaurer le contenu sauvegardé de <?php echo $backup_count; ?> produit(s) ?\n\nCela annulera la dernière régénération.')">
                                ↩️ Restaurer la dernière version
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* --------------------------------------------------
       Handlers (save / delete)
       -------------------------------------------------- */
    public function handle_save() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        check_admin_referer( 'nbd_mc_save', 'nbd_mc_nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $title   = sanitize_text_field( $_POST['post_title'] ?? '' );
        $status  = in_array( $_POST['post_status'] ?? 'publish', array( 'publish', 'draft', 'private' ), true )
                    ? $_POST['post_status'] : 'publish';

        if ( empty( $title ) ) {
            wp_safe_redirect( add_query_arg( 'nbd_mc_error', 'no_title', wp_get_referer() ) );
            exit;
        }

        $is_new = ! $post_id;

        if ( $is_new ) {
            $post_id = wp_insert_post( array(
                'post_type'   => 'page',
                'post_title'  => $title,
                'post_status' => $status,
            ) );
            update_post_meta( $post_id, '_nbd_mc_is_masterclass', '1' );
        } else {
            wp_update_post( array(
                'ID'          => $post_id,
                'post_title'  => $title,
                'post_status' => $status,
            ) );
        }

        // Sanitize repeater arrays
        if ( isset( $_POST['nbd_mc_card_badges'] ) && is_array( $_POST['nbd_mc_card_badges'] ) ) {
            $_POST['nbd_mc_card_badges'] = array_map( function( $b ){
                return array(
                    'icon'  => sanitize_text_field( $b['icon'] ?? '' ),
                    'label' => sanitize_text_field( $b['label'] ?? '' ),
                );
            }, $_POST['nbd_mc_card_badges'] );
        }
        if ( isset( $_POST['nbd_mc_learnings'] ) ) {
            $_POST['nbd_mc_learnings'] = array_map( 'sanitize_text_field', (array) $_POST['nbd_mc_learnings'] );
        }
        if ( isset( $_POST['nbd_mc_included'] ) ) {
            $_POST['nbd_mc_included'] = array_map( 'sanitize_text_field', (array) $_POST['nbd_mc_included'] );
        }
        foreach ( array( 'nbd_mc_modules', 'nbd_mc_bonus' ) as $rep ) {
            if ( isset( $_POST[ $rep ] ) && is_array( $_POST[ $rep ] ) ) {
                $_POST[ $rep ] = array_values( array_filter( array_map( function( $m ){
                    $title = sanitize_text_field( $m['title'] ?? '' );
                    $desc  = sanitize_textarea_field( wp_unslash( $m['description'] ?? '' ) );
                    if ( $title === '' && $desc === '' ) return null;
                    return array( 'title' => $title, 'description' => $desc );
                }, $_POST[ $rep ] ) ) );
            }
        }
        if ( isset( $_POST['nbd_mc_videos'] ) && is_array( $_POST['nbd_mc_videos'] ) ) {
            $_POST['nbd_mc_videos'] = array_values( array_filter( array_map( function( $v ){
                $url   = esc_url_raw( $v['url'] ?? '' );
                $title = sanitize_text_field( $v['title'] ?? '' );
                if ( ! $url ) return null;
                return array( 'title' => $title, 'url' => $url );
            }, $_POST['nbd_mc_videos'] ) ) );
        }
        if ( isset( $_POST['nbd_mc_testimonials'] ) && is_array( $_POST['nbd_mc_testimonials'] ) ) {
            $_POST['nbd_mc_testimonials'] = array_values( array_filter( array_map( function( $t ){
                $name = sanitize_text_field( $t['name'] ?? '' );
                $text = sanitize_textarea_field( wp_unslash( $t['text'] ?? '' ) );
                if ( $name === '' && $text === '' ) return null;
                return array(
                    'name'   => $name,
                    'role'   => sanitize_text_field( $t['role'] ?? '' ),
                    'text'   => $text,
                    'rating' => max( 1, min( 5, intval( $t['rating'] ?? 5 ) ) ),
                );
            }, $_POST['nbd_mc_testimonials'] ) ) );
        }

        // Save meta (mode "intelligent" : ne touche qu'aux champs présents dans le POST)
        NBD_MC_Meta_Fields::save( $post_id, $_POST );

        // Détecter le type sauvegardé (pour redirection et badge par défaut)
        $product_type = sanitize_text_field( $_POST['nbd_mc_product_type'] ?? 'masterclass' );
        if ( ! in_array( $product_type, array( 'formation', 'masterclass', 'autre' ), true ) ) {
            $product_type = 'masterclass';
        }
        update_post_meta( $post_id, '_nbd_mc_product_type', $product_type );

        // À la création : badge_pill par défaut selon le type
        if ( $is_new ) {
            $existing_badge = get_post_meta( $post_id, '_nbd_mc_badge_pill', true );
            if ( empty( $existing_badge ) || $existing_badge === 'Replay Masterclass' ) {
                $defaults = array(
                    'formation'   => 'Formation certifiante',
                    'masterclass' => 'Replay Masterclass',
                    'autre'       => 'Produit',
                );
                update_post_meta( $post_id, '_nbd_mc_badge_pill', $defaults[ $product_type ] );
            }
            $use_elementor = isset( $_POST['nbd_mc_editor_mode'] ) && $_POST['nbd_mc_editor_mode'] === 'elementor';
            NBD_MC_Page_Builder::instance()->build_elementor_template( $post_id, $use_elementor );
        } else {
            // Pour les posts existants : régénération AUTO (préserve le mode actuel)
            // Une sauvegarde automatique est créée → ↩️ Restaurer disponible
            $current_mode = get_post_meta( $post_id, '_elementor_edit_mode', true ) === 'builder';
            NBD_MC_Page_Builder::instance()->build_elementor_template( $post_id, $current_mode );
        }

        $edit_slug = $this->edit_slug_for( $product_type );
        wp_safe_redirect( admin_url( 'admin.php?page=' . $edit_slug . '&post_id=' . $post_id . '&nbd_mc_saved=1' ) );
        exit;
    }

    public function handle_rebuild() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        $post_id = absint( $_GET['post_id'] ?? 0 );
        check_admin_referer( 'nbd_mc_rebuild_' . $post_id );
        $mode = ( $_GET['mode'] ?? '' ) === 'elementor' ? 'elementor' : 'wp';
        if ( $post_id && get_post( $post_id ) ) {
            NBD_MC_Page_Builder::instance()->build_elementor_template( $post_id, $mode === 'elementor' );
            wp_safe_redirect( admin_url( 'admin.php?page=nbd-masterclass-edit&post_id=' . $post_id . '&nbd_mc_rebuilt=' . $mode ) );
            exit;
        }
        wp_safe_redirect( admin_url( 'admin.php?page=nbd-masterclass' ) );
        exit;
    }

    /**
     * Régénère le contenu de TOUS les produits (masterclass / formations / autres)
     * en une seule action. Permet de basculer tout le contenu vers Elementor ou WordPress.
     */
    public function handle_bulk_rebuild() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        check_admin_referer( 'nbd_mc_bulk_rebuild' );

        $mode = ( $_POST['mode'] ?? $_GET['mode'] ?? '' ) === 'elementor' ? 'elementor' : 'wp';
        $return = $_POST['return'] ?? $_GET['return'] ?? 'dashboard';

        $posts = get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'meta_query'     => array(
                array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ),
            ),
        ) );

        $count = 0;
        foreach ( $posts as $pid ) {
            NBD_MC_Page_Builder::instance()->build_elementor_template( $pid, $mode === 'elementor' );
            $count++;
        }

        $redirect = ( $return === 'settings' )
            ? admin_url( 'admin.php?page=nbd-masterclass-settings&nbd_bulk_done=' . $count . '&nbd_bulk_mode=' . $mode )
            : admin_url( 'admin.php?page=nbd-masterclass&nbd_bulk_done=' . $count . '&nbd_bulk_mode=' . $mode );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Restaure le contenu d'un seul produit depuis la sauvegarde.
     */
    public function handle_restore() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        $post_id = absint( $_GET['post_id'] ?? 0 );
        check_admin_referer( 'nbd_mc_restore_' . $post_id );
        if ( $post_id && get_post( $post_id ) ) {
            $ok = NBD_MC_Page_Builder::instance()->restore_content( $post_id );
            $type = get_post_meta( $post_id, '_nbd_mc_product_type', true ) ?: 'masterclass';
            $slug = $this->edit_slug_for( $type );
            wp_safe_redirect( admin_url( 'admin.php?page=' . $slug . '&post_id=' . $post_id . '&nbd_restored=' . ( $ok ? '1' : '0' ) ) );
            exit;
        }
        wp_safe_redirect( admin_url( 'admin.php?page=nbd-masterclass' ) );
        exit;
    }

    /**
     * Restaure tous les produits depuis leurs sauvegardes (rollback bulk rebuild).
     */
    public function handle_bulk_restore() {
        if ( ! current_user_can( 'edit_pages' ) ) wp_die( 'Permission refusée' );
        check_admin_referer( 'nbd_mc_bulk_restore' );

        $return = $_POST['return'] ?? 'dashboard';
        $posts = get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'meta_query'     => array(
                array( 'key' => '_nbd_mc_is_masterclass', 'value' => '1' ),
                array( 'key' => '_nbd_mc_content_backup', 'compare' => 'EXISTS' ),
            ),
        ) );

        $count = 0;
        foreach ( $posts as $pid ) {
            if ( NBD_MC_Page_Builder::instance()->restore_content( $pid ) ) $count++;
        }

        $redirect = ( $return === 'settings' )
            ? admin_url( 'admin.php?page=nbd-masterclass-settings&nbd_bulk_restored=' . $count )
            : admin_url( 'admin.php?page=nbd-masterclass&nbd_bulk_restored=' . $count );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_delete() {
        if ( ! current_user_can( 'delete_pages' ) ) wp_die( 'Permission refusée' );
        $post_id = absint( $_GET['post_id'] ?? 0 );
        check_admin_referer( 'nbd_mc_delete_' . $post_id );
        wp_trash_post( $post_id );
        wp_safe_redirect( admin_url( 'admin.php?page=nbd-masterclass&nbd_mc_deleted=1' ) );
        exit;
    }

    public function notices() {
        if ( isset( $_GET['nbd_mc_saved'] ) ) {
            $pid = absint( $_GET['post_id'] ?? 0 );
            $rebuild_status = $pid ? get_transient( 'nbd_mc_last_rebuild_' . $pid ) : null;
            if ( is_array( $rebuild_status ) && empty( $rebuild_status['ok'] ) ) {
                echo '<div class="notice notice-error is-dismissible"><p>⚠️ ' . esc_html__( 'Masterclass enregistrée MAIS la régénération du contenu a échoué.', 'nbd-masterclass' ) . '<br><strong>Erreur :</strong> <code>' . esc_html( $rebuild_status['msg'] ?? 'inconnue' ) . '</code></p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>✓ ' . esc_html__( 'Masterclass enregistrée et contenu de la page régénéré (sauvegarde de l\'ancien disponible via ↩️ Restaurer).', 'nbd-masterclass' ) . '</p></div>';
            }
            if ( $pid ) delete_transient( 'nbd_mc_last_rebuild_' . $pid );
        }
        if ( isset( $_GET['saved'] ) && isset( $_GET['page'] ) && $_GET['page'] === 'nbd-catalog' ) {
            echo '<div class="notice notice-success is-dismissible"><p>✓ Réglages du catalogue enregistrés.</p></div>';
        }
        if ( isset( $_GET['catalog_created'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>✓ Page catalogue créée / régénérée avec succès.</p></div>';
        }
        if ( isset( $_GET['nbd_mc_rebuilt'] ) ) {
            $mode = $_GET['nbd_mc_rebuilt'] === 'elementor' ? 'Elementor' : 'WordPress';
            echo '<div class="notice notice-success is-dismissible"><p>🔄 ' . sprintf( esc_html__( 'Page régénérée en mode %s avec succès.', 'nbd-masterclass' ), esc_html( $mode ) ) . '</p></div>';
        }
        if ( isset( $_GET['nbd_bulk_done'] ) ) {
            $count = intval( $_GET['nbd_bulk_done'] );
            $mode  = ( $_GET['nbd_bulk_mode'] ?? '' ) === 'elementor' ? 'Elementor' : 'WordPress';
            echo '<div class="notice notice-success is-dismissible"><p>✨ ' . sprintf(
                esc_html__( '%d produit(s) régénéré(s) en mode %s avec succès. Une sauvegarde du contenu précédent a été créée.', 'nbd-masterclass' ),
                $count,
                esc_html( $mode )
            ) . '</p></div>';
        }
        if ( isset( $_GET['nbd_bulk_restored'] ) ) {
            $count = intval( $_GET['nbd_bulk_restored'] );
            echo '<div class="notice notice-success is-dismissible"><p>↩️ ' . sprintf(
                esc_html__( '%d produit(s) restauré(s) depuis leur sauvegarde.', 'nbd-masterclass' ),
                $count
            ) . '</p></div>';
        }
        if ( isset( $_GET['nbd_restored'] ) ) {
            if ( $_GET['nbd_restored'] === '1' ) {
                echo '<div class="notice notice-success is-dismissible"><p>↩️ ' . esc_html__( 'Contenu restauré avec succès.', 'nbd-masterclass' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>⚠️ ' . esc_html__( 'Aucune sauvegarde trouvée pour ce produit.', 'nbd-masterclass' ) . '</p></div>';
            }
        }
        if ( isset( $_GET['nbd_mc_deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Masterclass déplacée dans la corbeille.', 'nbd-masterclass' ) . '</p></div>';
        }
        if ( isset( $_GET['nbd_mc_error'] ) && $_GET['nbd_mc_error'] === 'no_title' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Le titre est obligatoire.', 'nbd-masterclass' ) . '</p></div>';
        }
    }
}
