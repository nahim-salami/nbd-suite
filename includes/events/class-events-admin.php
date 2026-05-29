<?php
/**
 * Admin événements : sous-menu intégré au menu "Masterclass NBD".
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_Events_Admin {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 20 );
        add_action( 'admin_post_nbd_event_save', array( $this, 'handle_save' ) );
        add_action( 'admin_post_nbd_event_delete', array( $this, 'handle_delete' ) );
        add_action( 'admin_post_nbd_event_rebuild', array( $this, 'handle_rebuild' ) );
    }

    public function handle_rebuild() {
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission refusée' );
        $post_id = absint( $_GET['post_id'] ?? 0 );
        check_admin_referer( 'nbd_event_rebuild_' . $post_id );
        $mode = ( $_GET['mode'] ?? '' ) === 'elementor' ? 'elementor' : 'wp';
        if ( $post_id && get_post( $post_id ) ) {
            $this->build_event_elementor_template( $post_id, $mode === 'elementor' );
            wp_safe_redirect( admin_url( 'admin.php?page=nbd-events-edit&post_id=' . $post_id . '&rebuilt=' . $mode ) );
            exit;
        }
        wp_safe_redirect( admin_url( 'admin.php?page=nbd-events' ) );
        exit;
    }

    public function menu() {
        // Sous-menus dans "NBD Suite"
        add_submenu_page( 'nbd-masterclass', __( 'Événements', 'nbd-masterclass' ),
            '📅 ' . __( 'Événements', 'nbd-masterclass' ), 'edit_posts',
            'nbd-events', array( $this, 'page_list' ) );
        add_submenu_page( 'nbd-masterclass', __( 'Créer un événement', 'nbd-masterclass' ),
            '➕ ' . __( 'Créer un événement', 'nbd-masterclass' ), 'edit_posts',
            'nbd-events-edit', array( $this, 'page_edit' ) );
    }

    public function page_list() {
        $q = new WP_Query( array(
            'post_type'      => NBD_Events::CPT,
            'posts_per_page' => 50,
            'meta_key'       => '_nbd_event_date_start',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'post_status'    => array( 'publish', 'draft' ),
        ) );
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1 class="wp-heading-inline">📅 <?php esc_html_e( 'Événements', 'nbd-masterclass' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nbd-events-edit' ) ); ?>" class="page-title-action">
                + <?php esc_html_e( 'Ajouter un événement', 'nbd-masterclass' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ( isset( $_GET['saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>✓ <?php esc_html_e( 'Événement enregistré.', 'nbd-masterclass' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>✓ <?php esc_html_e( 'Événement supprimé.', 'nbd-masterclass' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['rebuilt'] ) ) :
                $mode = $_GET['rebuilt'] === 'elementor' ? 'Elementor' : 'WordPress';
                ?>
                <div class="notice notice-success is-dismissible"><p>🔄 Page régénérée en mode <?php echo esc_html( $mode ); ?> avec succès.</p></div>
            <?php endif; ?>

            <?php if ( ! $q->have_posts() ) : ?>
                <div class="nbd-mc-empty">
                    <p><strong><?php esc_html_e( 'Aucun événement pour le moment.', 'nbd-masterclass' ); ?></strong></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80"></th>
                            <th><?php esc_html_e( 'Titre', 'nbd-masterclass' ); ?></th>
                            <th width="140"><?php esc_html_e( 'Date', 'nbd-masterclass' ); ?></th>
                            <th width="120"><?php esc_html_e( 'Format', 'nbd-masterclass' ); ?></th>
                            <th width="110"><?php esc_html_e( 'Statut', 'nbd-masterclass' ); ?></th>
                            <th width="260"><?php esc_html_e( 'Actions', 'nbd-masterclass' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ( $q->have_posts() ) : $q->the_post(); $id = get_the_ID();
                        $img  = NBD_Events::get_meta( $id, '_nbd_event_image' );
                        $is_past = NBD_Events::is_past( $id );
                        $featured = NBD_Events::get_meta( $id, '_nbd_event_featured' );
                    ?>
                        <tr<?php echo $is_past ? ' style="opacity:0.6"' : ''; ?>>
                            <td>
                                <?php if ( $img ) echo wp_get_attachment_image( $img, array(60,60), false, array('style' => 'border-radius:8px') ); ?>
                            </td>
                            <td>
                                <strong>
                                    <?php if ( $featured ) echo '⭐ '; ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=nbd-events-edit&post_id=' . $id ) ); ?>"><?php the_title(); ?></a>
                                </strong>
                                <div class="row-actions">
                                    <span><a href="<?php the_permalink(); ?>" target="_blank">Voir</a> | </span>
                                    <span><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ); ?>">Elementor</a></span>
                                </div>
                            </td>
                            <td><?php echo esc_html( NBD_Events::date_full( $id ) ); ?></td>
                            <td>
                                <?php
                                $fmt = NBD_Events::get_meta( $id, '_nbd_event_format' );
                                echo NBD_Events::format_icon( $fmt ) . ' ' . esc_html( NBD_Events::format_label( $fmt ) );
                                ?>
                            </td>
                            <td>
                                <?php if ( $is_past ) : ?>
                                    <span style="color:#9CA3AF">Passé</span>
                                <?php else : ?>
                                    <span style="color:#10B981;font-weight:600">À venir</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nbd-events-edit&post_id=' . $id ) ); ?>" class="button button-small">Métas</a>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>" class="button button-small">✏️ WP</a>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ); ?>" class="button button-small">🎨 Elementor</a>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_event_delete&post_id=' . $id ), 'nbd_event_delete_' . $id ) ); ?>"
                                   onclick="return confirm('Supprimer cet événement ?')" class="button button-small button-link-delete">Supprimer</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function page_edit() {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $is_edit = $post_id > 0;
        $post    = $is_edit ? get_post( $post_id ) : null;

        $get = function( $key, $default = '' ) use ( $post_id ) {
            return $post_id ? NBD_Events::get_meta( $post_id, $key, $default ) : $default;
        };

        $img_id = $get( '_nbd_event_image' );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
        ?>
        <div class="wrap nbd-mc-wrap">
            <h1>
                📅 <?php echo $is_edit ? esc_html__( 'Modifier l\'événement', 'nbd-masterclass' ) : esc_html__( 'Créer un événement', 'nbd-masterclass' ); ?>
                <?php if ( $is_edit ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" class="page-title-action">Voir la page</a>
                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ); ?>" class="page-title-action">✏️ Modifier (WordPress)</a>
                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ); ?>" class="page-title-action">🎨 Modifier (Elementor)</a>
                <?php endif; ?>
            </h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nbd-mc-form">
                <?php wp_nonce_field( 'nbd_event_save', 'nbd_event_nonce' ); ?>
                <input type="hidden" name="action" value="nbd_event_save">
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">

                <div class="nbd-mc-grid">
                    <div class="nbd-mc-col-main">

                        <div class="nbd-mc-card">
                            <h2>Informations principales</h2>
                            <div class="nbd-mc-field">
                                <label>Titre de l'événement *</label>
                                <input type="text" name="post_title" required value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" placeholder="Ex: Congrès International d'Odontologie Holistique">
                            </div>
                            <div class="nbd-mc-field nbd-mc-field-editor">
                                <label>Description (éditeur complet)</label>
                                <p class="description nbd-mc-editor-hint">
                                    💡 <strong>Titres</strong> : menu déroulant <em>« Paragraphe »</em> en haut à gauche. <strong>Couleurs</strong> : A▾ et 🖍 dans la barre principale. <strong>Médias</strong> : bouton « Ajouter un média ».
                                </p>
                                <?php
                                wp_editor(
                                    $get( '_nbd_event_short_desc' ),
                                    'nbdeventshortdesc',
                                    array(
                                        'textarea_name' => 'nbd_event_short_desc',
                                        'textarea_rows' => 14,
                                        'media_buttons' => true,
                                        'teeny'         => false,
                                        'drag_drop_upload' => true,
                                        'tinymce'       => array(
                                            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,alignjustify,link,unlink,wp_more,fullscreen,wp_adv',
                                            'toolbar2' => 'forecolor,backcolor,fontsizeselect,pastetext,removeformat,charmap,outdent,indent,hr,subscript,superscript,table,undo,redo,wp_help',
                                            'wpautop'  => true,
                                            'block_formats' => 'Paragraphe=p;Titre 1=h1;Titre 2=h2;Titre 3=h3;Titre 4=h4;Titre 5=h5;Titre 6=h6;Préformaté=pre',
                                            'plugins'  => 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview,table',
                                        ),
                                        'quicktags'     => array(
                                            'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close,h2,h3,h4',
                                        ),
                                    )
                                );
                                ?>
                            </div>
                            <div class="nbd-mc-field">
                                <label>Lieu</label>
                                <input type="text" name="nbd_event_location" value="<?php echo esc_attr( $get( '_nbd_event_location' ) ); ?>" placeholder="Palais des Congrès, Paris">
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>Affiche / Image</h2>
                            <div class="nbd-mc-field">
                                <div class="nbd-mc-media-picker">
                                    <div class="nbd-mc-preview"><?php if ( $img_url ) echo '<img src="' . esc_url( $img_url ) . '">'; ?></div>
                                    <input type="hidden" name="nbd_event_image" value="<?php echo esc_attr( $img_id ); ?>">
                                    <button type="button" class="button nbd-mc-upload-btn">Choisir une image</button>
                                    <button type="button" class="button nbd-mc-remove-btn">Retirer</button>
                                </div>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>SEO</h2>
                            <div class="nbd-mc-field">
                                <label>Titre SEO</label>
                                <input type="text" name="nbd_event_seo_title" value="<?php echo esc_attr( $get( '_nbd_event_seo_title' ) ); ?>" maxlength="60">
                            </div>
                            <div class="nbd-mc-field">
                                <label>Description SEO</label>
                                <textarea name="nbd_event_seo_description" rows="3" maxlength="160"><?php echo esc_textarea( $get( '_nbd_event_seo_description' ) ); ?></textarea>
                            </div>
                        </div>

                    </div>

                    <div class="nbd-mc-col-side">

                        <div class="nbd-mc-card nbd-mc-card-primary">
                            <h2>Publication</h2>
                            <div class="nbd-mc-field">
                                <label>Statut</label>
                                <select name="post_status">
                                    <option value="publish" <?php selected( $post ? $post->post_status : 'publish', 'publish' ); ?>>Publié</option>
                                    <option value="draft" <?php selected( $post ? $post->post_status : '', 'draft' ); ?>>Brouillon</option>
                                </select>
                            </div>
                            <div class="nbd-mc-field">
                                <label>
                                    <input type="checkbox" name="nbd_event_featured" value="1" <?php checked( $get( '_nbd_event_featured' ), '1' ); ?>>
                                    ⭐ Mettre à la une (hero home)
                                </label>
                            </div>
                            <?php if ( ! $is_edit ) : ?>
                                <div class="nbd-mc-field" style="background:#FAF5FF;padding:10px;border-radius:6px;margin-bottom:12px;border:1px solid #E9D5FF">
                                    <label style="margin-bottom:6px;display:block"><strong>🎨 Éditeur par défaut</strong></label>
                                    <label style="display:block;font-weight:normal;margin-bottom:4px">
                                        <input type="radio" name="nbd_event_editor_mode" value="wordpress" checked>
                                        <strong>WordPress</strong> (recommandé)
                                    </label>
                                    <label style="display:block;font-weight:normal">
                                        <input type="radio" name="nbd_event_editor_mode" value="elementor">
                                        <strong>Elementor</strong>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="button button-primary button-hero" style="width:100%">
                                <?php echo $is_edit ? '💾 Enregistrer' : '✨ Créer l\'événement'; ?>
                            </button>
                            <?php if ( $is_edit ) : ?>
                                <div style="margin-top:14px;padding:10px;background:#F9FAFB;border-radius:6px;border:1px solid #E5E7EB">
                                    <strong style="font-size:12px;display:block;margin-bottom:6px">🔄 Régénérer le contenu</strong>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_event_rebuild&mode=wp&post_id=' . $post_id ), 'nbd_event_rebuild_' . $post_id ) ); ?>"
                                       onclick="return confirm('Cela écrasera le contenu (les méta-données sont conservées). Continuer ?')"
                                       class="button button-small" style="width:100%;text-align:center;margin-bottom:4px">
                                        ✏️ Mode WordPress
                                    </a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbd_event_rebuild&mode=elementor&post_id=' . $post_id ), 'nbd_event_rebuild_' . $post_id ) ); ?>"
                                       onclick="return confirm('Cela basculera en mode Elementor. Continuer ?')"
                                       class="button button-small" style="width:100%;text-align:center">
                                        🎨 Mode Elementor
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>📅 Date &amp; heure</h2>
                            <div class="nbd-mc-field">
                                <label>Date de début *</label>
                                <input type="date" name="nbd_event_date_start" required value="<?php echo esc_attr( $get( '_nbd_event_date_start' ) ); ?>">
                            </div>
                            <div class="nbd-mc-field">
                                <label>Date de fin (optionnelle)</label>
                                <input type="date" name="nbd_event_date_end" value="<?php echo esc_attr( $get( '_nbd_event_date_end' ) ); ?>">
                            </div>
                            <div class="nbd-mc-field-row">
                                <div class="nbd-mc-field">
                                    <label>Heure début</label>
                                    <input type="time" name="nbd_event_time_start" value="<?php echo esc_attr( $get( '_nbd_event_time_start' ) ); ?>">
                                </div>
                                <div class="nbd-mc-field">
                                    <label>Heure fin</label>
                                    <input type="time" name="nbd_event_time_end" value="<?php echo esc_attr( $get( '_nbd_event_time_end' ) ); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>Catégorisation</h2>
                            <div class="nbd-mc-field">
                                <label>Type</label>
                                <select name="nbd_event_type">
                                    <?php
                                    $types = NBD_Events::fields()['_nbd_event_type']['options'];
                                    $current = $get( '_nbd_event_type' );
                                    foreach ( $types as $k => $v ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $current, $k ); ?>><?php echo esc_html( $v ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="nbd-mc-field">
                                <label>Format</label>
                                <select name="nbd_event_format">
                                    <?php
                                    $formats = NBD_Events::fields()['_nbd_event_format']['options'];
                                    $current = $get( '_nbd_event_format' );
                                    foreach ( $formats as $k => $v ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $current, $k ); ?>><?php echo esc_html( NBD_Events::format_icon( $k ) . ' ' . $v ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="nbd-mc-field">
                                <label>Rôle du Dr</label>
                                <select name="nbd_event_role">
                                    <?php
                                    $roles = NBD_Events::fields()['_nbd_event_role']['options'];
                                    $current = $get( '_nbd_event_role' );
                                    foreach ( $roles as $k => $v ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $current, $k ); ?>><?php echo esc_html( NBD_Events::role_icon( $k ) . ' ' . $v ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="nbd-mc-card">
                            <h2>🎟️ Inscription</h2>
                            <div class="nbd-mc-field">
                                <label>URL d'inscription (externe)</label>
                                <input type="url" name="nbd_event_register_url" value="<?php echo esc_attr( $get( '_nbd_event_register_url' ) ); ?>" placeholder="https://...">
                                <p class="description">Eventbrite, System.io, site organisateur...</p>
                            </div>
                            <div class="nbd-mc-field">
                                <label>Texte du bouton</label>
                                <input type="text" name="nbd_event_register_label" value="<?php echo esc_attr( $get( '_nbd_event_register_label' ) ); ?>" placeholder="Réserver une place">
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function handle_save() {
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission refusée' );
        check_admin_referer( 'nbd_event_save', 'nbd_event_nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $title   = sanitize_text_field( $_POST['post_title'] ?? '' );
        $status  = in_array( $_POST['post_status'] ?? 'publish', array( 'publish', 'draft' ), true )
                    ? $_POST['post_status'] : 'publish';

        if ( empty( $title ) ) {
            wp_safe_redirect( wp_get_referer() );
            exit;
        }

        $is_new = ! $post_id;

        if ( $is_new ) {
            $post_id = wp_insert_post( array(
                'post_type'   => NBD_Events::CPT,
                'post_title'  => $title,
                'post_status' => $status,
            ) );
        } else {
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $title, 'post_status' => $status ) );
        }

        $fields_map = array(
            'nbd_event_image'           => array( '_nbd_event_image',         'int' ),
            'nbd_event_date_start'      => array( '_nbd_event_date_start',    'text' ),
            'nbd_event_date_end'        => array( '_nbd_event_date_end',      'text' ),
            'nbd_event_time_start'      => array( '_nbd_event_time_start',    'text' ),
            'nbd_event_time_end'        => array( '_nbd_event_time_end',      'text' ),
            'nbd_event_location'        => array( '_nbd_event_location',      'text' ),
            'nbd_event_format'          => array( '_nbd_event_format',        'text' ),
            'nbd_event_type'            => array( '_nbd_event_type',          'text' ),
            'nbd_event_role'            => array( '_nbd_event_role',          'text' ),
            'nbd_event_short_desc'      => array( '_nbd_event_short_desc',    'html' ),
            'nbd_event_register_url'    => array( '_nbd_event_register_url',  'url' ),
            'nbd_event_register_label'  => array( '_nbd_event_register_label','text' ),
            'nbd_event_seo_title'       => array( '_nbd_event_seo_title',     'text' ),
            'nbd_event_seo_description' => array( '_nbd_event_seo_description','textarea' ),
        );

        foreach ( $fields_map as $form_key => list( $meta_key, $type ) ) {
            if ( ! array_key_exists( $form_key, $_POST ) ) continue;
            $v = is_string( $_POST[ $form_key ] ) ? wp_unslash( $_POST[ $form_key ] ) : $_POST[ $form_key ];
            switch ( $type ) {
                case 'int':       $v = absint( $v ); break;
                case 'url':       $v = esc_url_raw( $v ); break;
                case 'textarea':  $v = sanitize_textarea_field( $v ); break;
                case 'html':      $v = wp_kses_post( $v ); break;
                default:          $v = sanitize_text_field( $v );
            }
            update_post_meta( $post_id, $meta_key, $v );
        }
        update_post_meta( $post_id, '_nbd_event_featured', isset( $_POST['nbd_event_featured'] ) ? '1' : '' );

        // Première création : générer le contenu (WordPress par défaut, Elementor en option)
        if ( $is_new ) {
            $use_elementor = isset( $_POST['nbd_event_editor_mode'] ) && $_POST['nbd_event_editor_mode'] === 'elementor';
            $this->build_event_elementor_template( $post_id, $use_elementor );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=nbd-events-edit&post_id=' . $post_id . '&saved=1' ) );
        exit;
    }

    public function handle_delete() {
        if ( ! current_user_can( 'delete_posts' ) ) wp_die( 'Permission refusée' );
        $post_id = absint( $_GET['post_id'] ?? 0 );
        check_admin_referer( 'nbd_event_delete_' . $post_id );
        wp_trash_post( $post_id );
        wp_safe_redirect( admin_url( 'admin.php?page=nbd-events&deleted=1' ) );
        exit;
    }

    private function eid() { return substr( md5( uniqid( '', true ) ), 0, 8 ); }

    /**
     * Template Elementor pour la page détail d'un événement (single).
     * Utilise les shortcodes dynamiques + fallback post_content.
     */
    public function build_event_elementor_template( $post_id, $use_elementor = false ) {
        $data = array(
            // Hero (image + date + meta + description + CTA)
            array(
                'id' => $this->eid(),
                'elType' => 'section',
                'settings' => array( 'structure' => '10' ),
                'elements' => array( array(
                    'id' => $this->eid(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 100 ),
                    'elements' => array( array(
                        'id' => $this->eid(),
                        'elType' => 'widget',
                        'widgetType' => 'shortcode',
                        'settings' => array( 'shortcode' => '[nbd_event_hero]' ),
                    ) ),
                ) ),
            ),
            // Contenu long éditable
            array(
                'id' => $this->eid(),
                'elType' => 'section',
                'settings' => array( 'structure' => '10' ),
                'elements' => array( array(
                    'id' => $this->eid(),
                    'elType' => 'column',
                    'settings' => array( '_column_size' => 100 ),
                    'elements' => array(
                        array(
                            'id' => $this->eid(),
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => array( 'title' => 'À propos de l\'événement', 'header_size' => 'h2', 'title_color' => '#4A1D66' ),
                        ),
                        array(
                            'id' => $this->eid(),
                            'elType' => 'widget',
                            'widgetType' => 'text-editor',
                            'settings' => array( 'editor' => '<p>Décrivez ici l\'événement en détail. Contenu librement modifiable dans Elementor.</p>' ),
                        ),
                    ),
                ) ),
            ),
        );

        // Préparer Elementor data (utilisable si l'utilisateur choisit Elementor)
        update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $post_id, '_elementor_version', '3.18.0' );
        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

        if ( $use_elementor ) {
            update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        } else {
            delete_post_meta( $post_id, '_elementor_edit_mode' );
        }

        // post_content au format BLOC GUTENBERG (édition WP native)
        $content  = "<!-- wp:shortcode -->\n[nbd_event_hero show_label=\"0\"]\n<!-- /wp:shortcode -->\n\n";
        $content .= "<!-- wp:heading -->\n<h2>À propos de l'événement</h2>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>Ajoutez ici plus de détails sur l'événement, le programme, les intervenants, etc.</p>\n<!-- /wp:paragraph -->\n";
        wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
    }
}
