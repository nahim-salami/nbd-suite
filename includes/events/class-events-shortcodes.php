<?php
/**
 * Shortcodes événements.
 *
 *   [nbd_event_hero]               → hero détail de l'événement courant
 *   [nbd_event_next_featured]      → bloc home : prochain événement à la une
 *   [nbd_events_upcoming limit=4]  → grille des prochains événements
 *   [nbd_events_archive show_past=1 show_filters=1]  → page actualité complète
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NBD_Events_Shortcodes {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'nbd_event_hero',           array( $this, 'sc_event_hero' ) );
        add_shortcode( 'nbd_event_next_featured',  array( $this, 'sc_next_featured' ) );
        add_shortcode( 'nbd_events_upcoming',      array( $this, 'sc_events_upcoming' ) );
        add_shortcode( 'nbd_events_archive',       array( $this, 'sc_events_archive' ) );
    }

    private function pid() {
        return get_the_ID() ?: ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 );
    }

    /* -------------------------------------------------
       Hero événement (utilisé sur la page détail + home featured)
       ------------------------------------------------- */
    public function sc_event_hero( $atts, $content = '', $tag = '' ) {
        $atts = shortcode_atts( array( 'id' => 0, 'show_label' => '1' ), $atts );
        $pid = $atts['id'] ? absint( $atts['id'] ) : $this->pid();
        if ( ! $pid || get_post_type( $pid ) !== NBD_Events::CPT ) return '';
        return $this->render_hero( $pid, $atts['show_label'] === '1' );
    }

    public function sc_next_featured( $atts ) {
        $event = NBD_Events::get_next_featured();
        if ( ! $event ) return '';
        return $this->render_hero( $event->ID, true );
    }

    private function render_hero( $pid, $show_label = true ) {
        $img_id  = NBD_Events::get_meta( $pid, '_nbd_event_image' );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
        $title   = get_the_title( $pid );
        $location = NBD_Events::get_meta( $pid, '_nbd_event_location' );
        $desc    = NBD_Events::get_meta( $pid, '_nbd_event_short_desc' );
        $format  = NBD_Events::get_meta( $pid, '_nbd_event_format' );
        $role    = NBD_Events::get_meta( $pid, '_nbd_event_role' );
        $t_start = NBD_Events::get_meta( $pid, '_nbd_event_time_start' );
        $t_end   = NBD_Events::get_meta( $pid, '_nbd_event_time_end' );
        $url     = NBD_Events::get_meta( $pid, '_nbd_event_register_url' );
        $label   = NBD_Events::get_meta( $pid, '_nbd_event_register_label', 'Réserver une place' );
        $day     = NBD_Events::date_day( $pid );
        $month   = NBD_Events::date_month_short( $pid );

        $time_display = '';
        if ( $t_start && $t_end )      $time_display = $t_start . ' - ' . $t_end;
        elseif ( $t_start )            $time_display = $t_start;

        ob_start(); ?>
        <div class="nbd-events">
          <div class="nbd-event-hero">
            <?php if ( $show_label ) : ?>
              <span class="nbd-event-hero-label">📅 Prochain événement</span>
            <?php endif; ?>

            <div class="nbd-event-hero-grid">

              <div class="nbd-event-hero-image">
                <?php if ( $img_url ) : ?>
                  <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                <?php else : ?>
                  <span style="color:#fff">📅</span>
                <?php endif; ?>
                <?php if ( $day && $month ) : ?>
                  <div class="nbd-event-hero-date-badge">
                    <span class="day"><?php echo esc_html( $day ); ?></span>
                    <span class="month"><?php echo esc_html( $month ); ?></span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="nbd-event-hero-content">

                <?php if ( $role || $format || $time_display ) : ?>
                  <div class="nbd-event-hero-meta">
                    <?php if ( $role ) : ?>
                      <span class="nbd-event-meta-item role"><?php echo esc_html( NBD_Events::role_icon( $role ) . ' ' . NBD_Events::role_label( $role ) ); ?></span>
                    <?php endif; ?>
                    <?php if ( $format ) : ?>
                      <span class="nbd-event-meta-item format-<?php echo esc_attr( $format ); ?>">
                        <?php echo esc_html( NBD_Events::format_icon( $format ) . ' ' . NBD_Events::format_label( $format ) ); ?>
                      </span>
                    <?php endif; ?>
                    <?php if ( $time_display ) : ?>
                      <span class="nbd-event-meta-item">🕐 <?php echo esc_html( $time_display ); ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <h2><?php echo esc_html( $title ); ?></h2>

                <?php if ( $location ) : ?>
                  <p style="font-size:14px;color:#6B7280;margin:0 0 16px 0;">
                    <strong>📍 <?php echo esc_html( $location ); ?></strong>
                  </p>
                <?php endif; ?>

                <?php if ( $desc ) : ?>
                  <div class="nbd-event-hero-description"><?php echo wpautop( wp_kses_post( $desc ) ); ?></div>
                <?php endif; ?>

                <div class="nbd-event-cta-group">
                  <?php if ( $url ) : ?>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="nbd-event-btn-primary">
                      <?php echo esc_html( $label ); ?> <span>→</span>
                    </a>
                  <?php endif; ?>
                  <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" class="nbd-event-btn-secondary">
                    Plus de détails
                  </a>
                </div>

              </div>

            </div>
          </div>
        </div>
        <?php return ob_get_clean();
    }

    /* -------------------------------------------------
       Grille événements à venir (utilisable partout)
       ------------------------------------------------- */
    public function sc_events_upcoming( $atts ) {
        $atts = shortcode_atts( array(
            'limit'        => 4,
            'columns'      => 3,
            'show_filters' => '0',
            'type'         => '',
        ), $atts );

        $q_args = array( 'posts_per_page' => intval( $atts['limit'] ) );
        if ( $atts['type'] ) {
            $q_args['meta_query'] = array(
                'relation' => 'AND',
                array( 'key' => '_nbd_event_date_start', 'value' => current_time('Y-m-d'), 'compare' => '>=', 'type' => 'DATE' ),
                array( 'key' => '_nbd_event_type', 'value' => $atts['type'] ),
            );
        }
        $q = NBD_Events::query_upcoming( $q_args );

        if ( ! $q->have_posts() ) return '<div class="nbd-events"><p>Aucun événement à venir pour le moment.</p></div>';

        ob_start(); ?>
        <div class="nbd-events">
            <?php if ( $atts['show_filters'] === '1' ) echo $this->render_filters(); ?>
            <div class="nbd-events-grid nbd-cols-<?php echo absint($atts['columns']); ?>">
                <?php while ( $q->have_posts() ) : $q->the_post();
                    echo $this->render_card( get_the_ID(), false );
                endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /* -------------------------------------------------
       Page Actualité complète (à venir + passés + filtres)
       ------------------------------------------------- */
    public function sc_events_archive( $atts ) {
        $atts = shortcode_atts( array(
            'show_past'    => '1',
            'show_filters' => '1',
            'columns'      => 3,
            'past_limit'   => 6,
        ), $atts );

        $upcoming = NBD_Events::query_upcoming();
        $past     = $atts['show_past'] === '1' ? NBD_Events::query_past( array( 'posts_per_page' => intval( $atts['past_limit'] ) ) ) : null;

        ob_start(); ?>
        <div class="nbd-events">
          <div class="nbd-events-container">

            <?php if ( $atts['show_filters'] === '1' ) echo $this->render_filters(); ?>

            <?php if ( $upcoming->have_posts() ) : ?>
              <h2 class="nbd-events-section-title">
                À venir <span class="count"><?php echo $upcoming->found_posts; ?> événement<?php echo $upcoming->found_posts > 1 ? 's' : ''; ?></span>
              </h2>
              <div class="nbd-events-grid nbd-cols-<?php echo absint($atts['columns']); ?>">
                <?php while ( $upcoming->have_posts() ) : $upcoming->the_post();
                    echo $this->render_card( get_the_ID(), false );
                endwhile; wp_reset_postdata(); ?>
              </div>
            <?php else : ?>
              <p style="text-align:center;padding:40px;color:#6B7280">Aucun événement à venir pour le moment. Revenez bientôt !</p>
            <?php endif; ?>

            <?php if ( $past && $past->have_posts() ) : ?>
              <h2 class="nbd-events-section-title">
                Événements passés <span class="count"><?php echo $past->found_posts; ?> événement<?php echo $past->found_posts > 1 ? 's' : ''; ?></span>
              </h2>
              <div class="nbd-events-grid nbd-cols-<?php echo absint($atts['columns']); ?>">
                <?php while ( $past->have_posts() ) : $past->the_post();
                    echo $this->render_card( get_the_ID(), true );
                endwhile; wp_reset_postdata(); ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
        <?php return ob_get_clean();
    }

    /* -------------------------------------------------
       Helpers : card + filtres
       ------------------------------------------------- */
    private function render_card( $pid, $is_past = false ) {
        $img_id  = NBD_Events::get_meta( $pid, '_nbd_event_image' );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
        $title   = get_the_title( $pid );
        $location = NBD_Events::get_meta( $pid, '_nbd_event_location' );
        $desc    = NBD_Events::get_meta( $pid, '_nbd_event_short_desc' );
        $format  = NBD_Events::get_meta( $pid, '_nbd_event_format' );
        $role    = NBD_Events::get_meta( $pid, '_nbd_event_role' );
        $type    = NBD_Events::get_meta( $pid, '_nbd_event_type' );
        $url     = NBD_Events::get_meta( $pid, '_nbd_event_register_url' );
        $label   = NBD_Events::get_meta( $pid, '_nbd_event_register_label', 'Réserver' );
        $day     = NBD_Events::date_day( $pid );
        $month   = NBD_Events::date_month_short( $pid );

        $classes = 'nbd-event-card nbd-card-linked';
        if ( $is_past ) $classes .= ' past';
        $internal_url = get_permalink( $pid );

        ob_start(); ?>
        <article class="<?php echo esc_attr( $classes ); ?>" data-category="<?php echo esc_attr( $type ); ?>">
            <div class="nbd-event-card-image">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                <?php endif; ?>
                <?php if ( $day && $month ) : ?>
                    <div class="nbd-event-card-date">
                        <span class="day"><?php echo esc_html( $day ); ?></span>
                        <span class="month"><?php echo esc_html( $month ); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( $format ) : ?>
                    <span class="nbd-event-card-format <?php echo esc_attr( $format ); ?>">
                        <?php echo esc_html( NBD_Events::format_icon( $format ) . ' ' . NBD_Events::format_label( $format ) ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="nbd-event-card-body">
                <?php if ( $role ) : ?>
                    <span class="nbd-event-card-role"><?php echo esc_html( NBD_Events::role_icon( $role ) . ' ' . NBD_Events::role_label( $role ) ); ?></span>
                <?php endif; ?>
                <h3 class="nbd-event-card-title">
                    <a href="<?php echo esc_url( $internal_url ); ?>" class="nbd-card-overlay-link"><?php echo esc_html( $title ); ?></a>
                </h3>
                <?php if ( $location ) : ?>
                    <p class="nbd-event-card-location">📍 <?php echo esc_html( $location ); ?></p>
                <?php endif; ?>
                <?php if ( $desc ) : ?>
                    <p class="nbd-event-card-description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $desc ), 18 ) ); ?></p>
                <?php endif; ?>
                <div class="nbd-event-card-footer">
                    <?php if ( $is_past ) : ?>
                        <a href="<?php echo esc_url( $internal_url ); ?>" class="nbd-event-card-btn nbd-event-card-btn-outline nbd-card-z-up">Voir →</a>
                    <?php else : ?>
                        <?php if ( $url ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="nbd-event-card-btn nbd-card-z-up"><?php echo esc_html( $label ); ?> →</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $internal_url ); ?>" class="nbd-event-card-btn nbd-event-card-btn-outline nbd-card-z-up">Détails</a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    private function render_filters() {
        global $wpdb;
        $types_in_db = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
            '_nbd_event_type'
        ) );
        if ( empty( $types_in_db ) ) return '';

        $html  = '<div class="nbd-events-filters">';
        $html .= '<button class="nbd-events-filter-btn active" data-filter="all">Tous</button>';
        foreach ( $types_in_db as $t ) {
            $html .= '<button class="nbd-events-filter-btn" data-filter="' . esc_attr( $t ) . '">' . esc_html( NBD_Events::type_label( $t ) ) . '</button>';
        }
        $html .= '</div>';
        return $html;
    }
}
