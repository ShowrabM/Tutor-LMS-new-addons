<?php
/**
 * STM Tutor Popup Maker
 * 
 * Production-ready Popup Maker module for Tutor LMS Customization.
 * Features:
 * - Module A: Custom Post Type `popup_maker` with native wp.media Meta Box & Display Controls.
 * - Module B: Dedicated custom table `{$wpdb->prefix}popup_leads` via dbDelta on activation.
 * - Module C: Dynamic, accessible frontend popup engine with pure Vanilla JS & CSS (0 jQuery).
 * - Module D: Secure AJAX lead capture & CSV export engine.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STM_Tutor_Popup_Maker {
    const POST_TYPE    = 'popup_maker';
    const TABLE_NAME   = 'popup_leads';
    const DB_VERSION   = '1.0.0';
    const NONCE_ACTION = 'stm_popup_maker_meta_save';
    const NONCE_NAME   = 'stm_popup_maker_nonce';
    const LEAD_NONCE   = 'stm_popup_lead_nonce';

    public function __construct() {
        // Module A: Custom Post Type & Admin UI
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box_data' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Admin Custom Columns
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );

        // Module B: DB Table check
        add_action( 'admin_init', array( $this, 'maybe_create_table' ) );

        // Module C: Frontend Display Engine
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_frontend_popup' ) );

        // Module D: Lead Submission & CSV Export
        add_action( 'wp_ajax_stm_submit_popup_lead', array( $this, 'handle_submit_lead' ) );
        add_action( 'wp_ajax_nopriv_stm_submit_popup_lead', array( $this, 'handle_submit_lead' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_submenus' ) );
        add_action( 'admin_post_download_popup_leads_csv', array( $this, 'handle_download_csv' ) );

        // Tutor LMS Dashboard Integration
        add_action( 'wp_ajax_stm_save_dashboard_popup_settings', array( $this, 'handle_save_dashboard_settings' ) );
        add_action( 'wp_ajax_stm_pm_dashboard_upload_image', array( $this, 'handle_dashboard_image_upload' ) );
    }

    /* ==========================================================================
       MODULE A: Custom Post Type & Admin UI
       ========================================================================== */

    /**
     * Register Custom Post Type: popup_maker
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Popup Maker', 'Post type general name', 'tutor' ),
            'singular_name'         => _x( 'Popup', 'Post type singular name', 'tutor' ),
            'menu_name'             => _x( 'Popup Maker', 'Admin Menu text', 'tutor' ),
            'name_admin_bar'        => _x( 'Popup', 'Add New on Toolbar', 'tutor' ),
            'add_new'               => __( 'Add New Popup', 'tutor' ),
            'add_new_item'          => __( 'Add New Popup', 'tutor' ),
            'new_item'              => __( 'New Popup', 'tutor' ),
            'edit_item'             => __( 'Edit Popup', 'tutor' ),
            'view_item'             => __( 'View Popup', 'tutor' ),
            'all_items'             => __( 'All Popups', 'tutor' ),
            'search_items'          => __( 'Search Popups', 'tutor' ),
            'not_found'             => __( 'No popups found.', 'tutor' ),
            'not_found_in_trash'    => __( 'No popups found in Trash.', 'tutor' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 27,
            'menu_icon'          => 'dashicons-slides',
            'supports'           => array( 'title' ),
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Register Meta Box: Popup Builder Settings
     */
    public function register_meta_boxes() {
        add_meta_box(
            'stm_popup_builder_settings',
            __( 'Popup Builder Settings', 'tutor' ),
            array( $this, 'render_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the Meta Box UI (Media uploader, checkboxes, display controls)
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

        $banner_url     = get_post_meta( $post->ID, '_stm_pm_banner_url', true );
        $banner_id      = get_post_meta( $post->ID, '_stm_pm_banner_id', true );
        $subtitle       = get_post_meta( $post->ID, '_stm_pm_subtitle', true );
        $field_name     = get_post_meta( $post->ID, '_stm_pm_field_name', true );
        $field_phone    = get_post_meta( $post->ID, '_stm_pm_field_phone', true );
        $field_message  = get_post_meta( $post->ID, '_stm_pm_field_message', true );
        $delay_sec      = get_post_meta( $post->ID, '_stm_pm_delay_sec', true );
        $frequency_days = get_post_meta( $post->ID, '_stm_pm_frequency_days', true );
        $cta_text       = get_post_meta( $post->ID, '_stm_pm_cta_text', true );

        // Defaults for new post
        if ( '' === $delay_sec ) {
            $delay_sec = 2;
        }
        if ( '' === $frequency_days ) {
            $frequency_days = 7;
        }
        if ( '' === $cta_text ) {
            $cta_text = __( 'Submit', 'tutor' );
        }
        ?>
        <div class="stm-pm-metabox-wrapper">
            
            <!-- 1. Media Uploader Section -->
            <div class="stm-pm-section">
                <h4 class="stm-pm-section-title">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e( 'Media Uploader (Banner Image)', 'tutor' ); ?>
                </h4>
                <p class="stm-pm-section-desc"><?php esc_html_e( 'Upload or select a banner image to be prominently displayed at the top of the popup modal.', 'tutor' ); ?></p>

                <div class="stm-pm-media-uploader">
                    <input type="hidden" name="_stm_pm_banner_url" id="stm_pm_banner_url" value="<?php echo esc_url( $banner_url ); ?>">
                    <input type="hidden" name="_stm_pm_banner_id" id="stm_pm_banner_id" value="<?php echo esc_attr( $banner_id ); ?>">

                    <div class="stm-pm-banner-preview-box" id="stm_pm_banner_preview_box" style="<?php echo empty( $banner_url ) ? 'display:none;' : ''; ?>">
                        <img src="<?php echo esc_url( $banner_url ); ?>" id="stm_pm_banner_preview_img" class="stm-pm-banner-preview-img" alt="Banner Preview">
                        <div class="stm-pm-media-actions">
                            <button type="button" class="button button-secondary" id="stm_pm_remove_banner_btn">
                                <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top:-2px;"></span>
                                <?php esc_html_e( 'Remove Image', 'tutor' ); ?>
                            </button>
                        </div>
                    </div>

                    <div>
                        <button type="button" class="button button-primary" id="stm_pm_upload_banner_btn">
                            <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top:-2px;"></span>
                            <?php echo empty( $banner_url ) ? esc_html__( 'Upload / Select Banner Image', 'tutor' ) : esc_html__( 'Change Banner Image', 'tutor' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. Content & Text Settings -->
            <div class="stm-pm-section">
                <h4 class="stm-pm-section-title">
                    <span class="dashicons dashicons-editor-paragraph"></span>
                    <?php esc_html_e( 'Popup Subtitle / Description', 'tutor' ); ?>
                </h4>
                <p class="stm-pm-section-desc"><?php esc_html_e( 'The post title is used as the popup headline. Enter an optional subtitle or short offer pitch below.', 'tutor' ); ?></p>

                <div class="stm-pm-control-group">
                    <label for="stm_pm_subtitle"><?php esc_html_e( 'Subtitle / Offer Text', 'tutor' ); ?></label>
                    <input type="text" name="_stm_pm_subtitle" id="stm_pm_subtitle" value="<?php echo esc_attr( $subtitle ); ?>" placeholder="<?php esc_attr_e( 'e.g. Join our tennis academy today and receive 15% off your first coaching session!', 'tutor' ); ?>">
                </div>
            </div>

            <!-- 3. Form Fields Checklist -->
            <div class="stm-pm-section">
                <h4 class="stm-pm-section-title">
                    <span class="dashicons dashicons-forms"></span>
                    <?php esc_html_e( 'Form Fields Checklist', 'tutor' ); ?>
                </h4>
                <p class="stm-pm-section-desc"><?php esc_html_e( 'Select which form fields will be generated dynamically inside the popup modal. Email is always required.', 'tutor' ); ?></p>

                <div class="stm-pm-checklist">
                    <!-- Name Field -->
                    <label class="stm-pm-checkbox-item">
                        <input type="checkbox" name="_stm_pm_field_name" value="1" <?php checked( 1, $field_name ); ?>>
                        <span><strong><?php esc_html_e( 'Name Field', 'tutor' ); ?></strong> &mdash; <?php esc_html_e( 'Text input for full visitor name', 'tutor' ); ?></span>
                    </label>

                    <!-- Email Field (Default Checked and Enforced Required) -->
                    <label class="stm-pm-checkbox-item" style="cursor: default; background: #f0fdf4; border-color: #bbf7d0;">
                        <input type="checkbox" name="_stm_pm_field_email" id="stm_pm_field_email" value="1" checked onclick="return false;">
                        <span><strong><?php esc_html_e( 'Email Field', 'tutor' ); ?></strong> &mdash; <?php esc_html_e( 'Email address input (Strictly Required)', 'tutor' ); ?></span>
                        <span class="stm-pm-badge" style="background:#dcfce7; color:#15803d;"><?php esc_html_e( 'Required', 'tutor' ); ?></span>
                    </label>

                    <!-- Phone Number Field -->
                    <label class="stm-pm-checkbox-item">
                        <input type="checkbox" name="_stm_pm_field_phone" value="1" <?php checked( 1, $field_phone ); ?>>
                        <span><strong><?php esc_html_e( 'Phone Number Field', 'tutor' ); ?></strong> &mdash; <?php esc_html_e( 'Telephone input for contact number', 'tutor' ); ?></span>
                    </label>

                    <!-- Message / Note Field -->
                    <label class="stm-pm-checkbox-item">
                        <input type="checkbox" name="_stm_pm_field_message" value="1" <?php checked( 1, $field_message ); ?>>
                        <span><strong><?php esc_html_e( 'Message / Note Field', 'tutor' ); ?></strong> &mdash; <?php esc_html_e( 'Textarea for visitor inquiry or comments', 'tutor' ); ?></span>
                    </label>
                </div>
            </div>

            <!-- 4. Basic Display Controls -->
            <div class="stm-pm-section">
                <h4 class="stm-pm-section-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Display Controls & CTA', 'tutor' ); ?>
                </h4>
                <p class="stm-pm-section-desc"><?php esc_html_e( 'Configure behavior, delay timing, frequency suppression, and button label.', 'tutor' ); ?></p>

                <div class="stm-pm-grid-2">
                    <div class="stm-pm-control-group">
                        <label for="stm_pm_delay_sec"><?php esc_html_e( 'Delay Trigger (Seconds)', 'tutor' ); ?></label>
                        <input type="number" name="_stm_pm_delay_sec" id="stm_pm_delay_sec" value="<?php echo esc_attr( $delay_sec ); ?>" min="0" max="120" step="1">
                        <span class="description"><?php esc_html_e( 'Seconds to wait after page load before displaying the popup (e.g. 2, 5, 10).', 'tutor' ); ?></span>
                    </div>

                    <div class="stm-pm-control-group">
                        <label for="stm_pm_frequency_days"><?php esc_html_e( 'Frequency Cap (Days)', 'tutor' ); ?></label>
                        <input type="number" name="_stm_pm_frequency_days" id="stm_pm_frequency_days" value="<?php echo esc_attr( $frequency_days ); ?>" min="0" max="365" step="1">
                        <span class="description"><?php esc_html_e( 'Days to suppress popup after dismissal or submission via localStorage (e.g. 7). Set to 0 to show on every visit.', 'tutor' ); ?></span>
                    </div>
                </div>

                <div class="stm-pm-control-group" style="margin-top: 10px;">
                    <label for="stm_pm_cta_text"><?php esc_html_e( 'Button CTA Text', 'tutor' ); ?></label>
                    <input type="text" name="_stm_pm_cta_text" id="stm_pm_cta_text" value="<?php echo esc_attr( $cta_text ); ?>" placeholder="<?php esc_attr_e( 'Submit', 'tutor' ); ?>">
                    <span class="description"><?php esc_html_e( 'The text displayed on the main submission button (default: "Submit").', 'tutor' ); ?></span>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Save Meta Box Data on `save_post_popup_maker`
     */
    public function save_meta_box_data( $post_id, $post ) {
        // Nonce check
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            return;
        }

        // Autosave check
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Capability check
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitize and save fields
        $banner_url = isset( $_POST['_stm_pm_banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['_stm_pm_banner_url'] ) ) : '';
        update_post_meta( $post_id, '_stm_pm_banner_url', $banner_url );

        $banner_id = isset( $_POST['_stm_pm_banner_id'] ) ? absint( $_POST['_stm_pm_banner_id'] ) : 0;
        update_post_meta( $post_id, '_stm_pm_banner_id', $banner_id );

        $subtitle = isset( $_POST['_stm_pm_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['_stm_pm_subtitle'] ) ) : '';
        update_post_meta( $post_id, '_stm_pm_subtitle', $subtitle );

        $field_name = ! empty( $_POST['_stm_pm_field_name'] ) ? 1 : 0;
        update_post_meta( $post_id, '_stm_pm_field_name', $field_name );

        // Email is enforced required
        update_post_meta( $post_id, '_stm_pm_field_email', 1 );

        $field_phone = ! empty( $_POST['_stm_pm_field_phone'] ) ? 1 : 0;
        update_post_meta( $post_id, '_stm_pm_field_phone', $field_phone );

        $field_message = ! empty( $_POST['_stm_pm_field_message'] ) ? 1 : 0;
        update_post_meta( $post_id, '_stm_pm_field_message', $field_message );

        $delay_sec = isset( $_POST['_stm_pm_delay_sec'] ) ? absint( $_POST['_stm_pm_delay_sec'] ) : 2;
        update_post_meta( $post_id, '_stm_pm_delay_sec', $delay_sec );

        $frequency_days = isset( $_POST['_stm_pm_frequency_days'] ) ? absint( $_POST['_stm_pm_frequency_days'] ) : 7;
        update_post_meta( $post_id, '_stm_pm_frequency_days', $frequency_days );

        $cta_text = ! empty( $_POST['_stm_pm_cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['_stm_pm_cta_text'] ) ) : __( 'Submit', 'tutor' );
        update_post_meta( $post_id, '_stm_pm_cta_text', $cta_text );
    }

    /**
     * Enqueue Admin Assets for the Meta Box & WP Media
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;

        if ( self::POST_TYPE !== $post_type ) {
            return;
        }

        // Native WordPress Media Uploader scripts
        wp_enqueue_media();

        wp_enqueue_style(
            'stm-popup-admin-css',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-popup-admin.css',
            array(),
            STM_TUTOR_CUSTOMIZATION_VERSION
        );

        wp_enqueue_script(
            'stm-popup-admin-js',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-popup-admin.js',
            array( 'jquery' ),
            STM_TUTOR_CUSTOMIZATION_VERSION,
            true
        );
    }

    /**
     * Custom columns in post list
     */
    public function set_custom_columns( $columns ) {
        $new_columns = array();
        $new_columns['cb']          = $columns['cb'];
        $new_columns['banner']      = __( 'Banner', 'tutor' );
        $new_columns['title']       = $columns['title'];
        $new_columns['fields']      = __( 'Form Fields', 'tutor' );
        $new_columns['delay']       = __( 'Delay', 'tutor' );
        $new_columns['frequency']   = __( 'Frequency', 'tutor' );
        $new_columns['leads_count'] = __( 'Leads Captured', 'tutor' );
        $new_columns['date']        = $columns['date'];
        return $new_columns;
    }

    /**
     * Render Custom Column Data
     */
    public function render_custom_columns( $column, $post_id ) {
        global $wpdb;

        switch ( $column ) {
            case 'banner':
                $banner_url = get_post_meta( $post_id, '_stm_pm_banner_url', true );
                if ( ! empty( $banner_url ) ) {
                    echo '<img src="' . esc_url( $banner_url ) . '" style="width:60px;height:40px;object-fit:cover;border-radius:4px;display:block;">';
                } else {
                    echo '<span style="color:#94a3b8;">&mdash;</span>';
                }
                break;

            case 'fields':
                $badges = array();
                if ( get_post_meta( $post_id, '_stm_pm_field_name', true ) ) {
                    $badges[] = 'Name';
                }
                $badges[] = 'Email*';
                if ( get_post_meta( $post_id, '_stm_pm_field_phone', true ) ) {
                    $badges[] = 'Phone';
                }
                if ( get_post_meta( $post_id, '_stm_pm_field_message', true ) ) {
                    $badges[] = 'Message';
                }
                echo '<span style="font-size:12px;color:#334155;">' . esc_html( implode( ', ', $badges ) ) . '</span>';
                break;

            case 'delay':
                $delay = get_post_meta( $post_id, '_stm_pm_delay_sec', true );
                echo esc_html( ( '' !== $delay ? $delay : 2 ) . 's' );
                break;

            case 'frequency':
                $freq = get_post_meta( $post_id, '_stm_pm_frequency_days', true );
                echo esc_html( ( '' !== $freq ? $freq : 7 ) . ' days' );
                break;

            case 'leads_count':
                $table_name = $wpdb->prefix . self::TABLE_NAME;
                $count = 0;
                if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
                    $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE popup_id = %d", $post_id ) );
                }
                echo '<strong>' . esc_html( $count ) . '</strong>';
                break;
        }
    }

    /* ==========================================================================
       MODULE B: Database Table & Activation Hook
       ========================================================================== */

    /**
     * Create dedicated custom table: {$wpdb->prefix}popup_leads
     */
    public static function activate() {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            popup_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(120) NOT NULL DEFAULT '',
            email varchar(120) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            message text NOT NULL,
            page_url varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY popup_id (popup_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'stm_popup_leads_db_version', self::DB_VERSION );
    }

    /**
     * Automatic fallback table check on admin_init
     */
    public function maybe_create_table() {
        if ( get_option( 'stm_popup_leads_db_version' ) !== self::DB_VERSION ) {
            self::activate();
        }
    }

    /* ==========================================================================
       MODULE C: Dynamic Frontend Display Engine
       ========================================================================== */

    /**
     * Helper to get active published popup
     */
    private function get_active_popup() {
        $popups = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        return ! empty( $popups ) ? $popups[0] : null;
    }

    /**
     * Enqueue Frontend Assets (Vanilla JS & CSS only when an active popup exists)
     */
    public function enqueue_frontend_assets() {
        if ( is_admin() ) {
            return;
        }

        // Enqueue Tutor LMS Dashboard assets when an administrator visits the dashboard
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $is_dashboard = ( function_exists( 'stm_is_tutor_dashboard_page' ) && stm_is_tutor_dashboard_page() ) || ( 'popup-maker' === get_query_var( 'tutor_dashboard_page' ) );
            if ( $is_dashboard ) {
                wp_enqueue_media();
                wp_enqueue_style( 'media-views' );
                wp_enqueue_style( 'buttons' );
                wp_enqueue_style( 'dashicons' );
                wp_enqueue_style( 'common' );
                wp_enqueue_style( 'forms' );
                wp_enqueue_style(
                    'stm-popup-admin-css',
                    STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-popup-admin.css',
                    array(),
                    STM_TUTOR_CUSTOMIZATION_VERSION
                );
            }
        }

        $popup = $this->get_active_popup();
        if ( ! $popup ) {
            return;
        }

        wp_enqueue_style(
            'stm-popup-frontend-css',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-popup-frontend.css',
            array(),
            STM_TUTOR_CUSTOMIZATION_VERSION
        );

        // Zero jQuery dependency on the frontend!
        wp_enqueue_script(
            'stm-popup-frontend-js',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-popup-frontend.js',
            array(),
            STM_TUTOR_CUSTOMIZATION_VERSION,
            true
        );

        wp_localize_script(
            'stm-popup-frontend-js',
            'stmPopupMakerData',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            )
        );
    }

    /**
     * Render the Live Modal on `wp_footer`
     */
    public function render_frontend_popup() {
        if ( is_admin() ) {
            return;
        }

        $popup = $this->get_active_popup();
        if ( ! $popup ) {
            return;
        }

        $popup_id       = $popup->ID;
        $title          = get_the_title( $popup_id );
        $banner_url     = get_post_meta( $popup_id, '_stm_pm_banner_url', true );
        $subtitle       = get_post_meta( $popup_id, '_stm_pm_subtitle', true );
        $field_name     = get_post_meta( $popup_id, '_stm_pm_field_name', true );
        $field_phone    = get_post_meta( $popup_id, '_stm_pm_field_phone', true );
        $field_message  = get_post_meta( $popup_id, '_stm_pm_field_message', true );
        $delay_sec      = get_post_meta( $popup_id, '_stm_pm_delay_sec', true );
        $frequency_days = get_post_meta( $popup_id, '_stm_pm_frequency_days', true );
        $cta_text       = get_post_meta( $popup_id, '_stm_pm_cta_text', true );

        $delay_sec      = ( '' !== $delay_sec ) ? absint( $delay_sec ) : 2;
        $frequency_days = ( '' !== $frequency_days ) ? absint( $frequency_days ) : 7;
        $cta_text       = ! empty( $cta_text ) ? $cta_text : __( 'Submit', 'tutor' );
        ?>
        <!-- STM Tutor Popup Maker Modal Root -->
        <div id="stm-popup-maker-modal" 
             class="stm-pm-modal-root" 
             data-popup-id="<?php echo esc_attr( $popup_id ); ?>" 
             data-delay="<?php echo esc_attr( $delay_sec ); ?>" 
             data-frequency="<?php echo esc_attr( $frequency_days ); ?>" 
             role="dialog" 
             aria-modal="true" 
             aria-hidden="true" 
             aria-labelledby="stm-pm-title">

            <!-- 100% Viewport Overlay Backdrop -->
            <div class="stm-pm-modal-backdrop" tabindex="-1"></div>

            <!-- Centered Modal Container -->
            <div class="stm-pm-modal-dialog">
                
                <!-- Prominent Close Button -->
                <button type="button" class="stm-pm-modal-close" aria-label="<?php esc_attr_e( 'Close popup', 'tutor' ); ?>">&times;</button>

                <!-- Banner Image (if configured) -->
                <?php if ( ! empty( $banner_url ) ) : ?>
                    <div class="stm-pm-banner-wrap">
                        <img src="<?php echo esc_url( $banner_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="stm-pm-banner-img">
                    </div>
                <?php endif; ?>

                <!-- Modal Body -->
                <div class="stm-pm-modal-body">
                    <?php if ( ! empty( $title ) ) : ?>
                        <h3 class="stm-pm-title" id="stm-pm-title"><?php echo esc_html( $title ); ?></h3>
                    <?php endif; ?>

                    <?php if ( ! empty( $subtitle ) ) : ?>
                        <p class="stm-pm-subtitle"><?php echo esc_html( $subtitle ); ?></p>
                    <?php endif; ?>

                    <!-- Inline Feedback Notices -->
                    <div id="stm-pm-error-notice" class="stm-pm-notice stm-pm-notice-error" style="display: none;" role="alert"></div>
                    <div id="stm-pm-success-notice" class="stm-pm-notice stm-pm-notice-success" style="display: none;" role="status"></div>

                    <!-- Dynamic Lead Form -->
                    <form id="stm-pm-lead-form" class="stm-pm-form" method="post">
                        <input type="hidden" name="popup_id" value="<?php echo esc_attr( $popup_id ); ?>">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( self::LEAD_NONCE ) ); ?>">

                        <!-- Name Field (Optional) -->
                        <?php if ( ! empty( $field_name ) ) : ?>
                            <div class="stm-pm-field-group">
                                <label for="stm_pm_lead_name" class="stm-pm-field-label"><?php esc_html_e( 'Full Name', 'tutor' ); ?></label>
                                <input type="text" name="lead_name" id="stm_pm_lead_name" class="stm-pm-input" placeholder="<?php esc_attr_e( 'Enter your full name', 'tutor' ); ?>">
                            </div>
                        <?php endif; ?>

                        <!-- Email Field (Always required) -->
                        <div class="stm-pm-field-group">
                            <label for="stm_pm_lead_email" class="stm-pm-field-label">
                                <?php esc_html_e( 'Email Address', 'tutor' ); ?>
                                <span class="stm-pm-required">*</span>
                            </label>
                            <input type="email" name="lead_email" id="stm_pm_lead_email" class="stm-pm-input" placeholder="<?php esc_attr_e( 'Enter your email address', 'tutor' ); ?>" required>
                        </div>

                        <!-- Phone Field (Optional) -->
                        <?php if ( ! empty( $field_phone ) ) : ?>
                            <div class="stm-pm-field-group">
                                <label for="stm_pm_lead_phone" class="stm-pm-field-label"><?php esc_html_e( 'Phone Number', 'tutor' ); ?></label>
                                <input type="tel" name="lead_phone" id="stm_pm_lead_phone" class="stm-pm-input" placeholder="<?php esc_attr_e( 'Enter your phone number', 'tutor' ); ?>">
                            </div>
                        <?php endif; ?>

                        <!-- Message Field (Optional) -->
                        <?php if ( ! empty( $field_message ) ) : ?>
                            <div class="stm-pm-field-group">
                                <label for="stm_pm_lead_message" class="stm-pm-field-label"><?php esc_html_e( 'Message / Note', 'tutor' ); ?></label>
                                <textarea name="lead_message" id="stm_pm_lead_message" class="stm-pm-textarea" rows="3" placeholder="<?php esc_attr_e( 'Enter your message or note...', 'tutor' ); ?>"></textarea>
                            </div>
                        <?php endif; ?>

                        <!-- Submit CTA Button -->
                        <button type="submit" class="stm-pm-submit-btn">
                            <span class="stm-pm-btn-spinner" style="display: none;"></span>
                            <span class="stm-pm-btn-text"><?php echo esc_html( $cta_text ); ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /* ==========================================================================
       MODULE D: Lead Submission & CSV Export System
       ========================================================================== */

    /**
     * AJAX handler to submit opt-in leads into custom table
     */
    public function handle_submit_lead() {
        if ( ! check_ajax_referer( self::LEAD_NONCE, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security verification failed. Please refresh the page.', 'tutor' ) ) );
        }

        global $wpdb;

        $popup_id = isset( $_POST['popup_id'] ) ? absint( $_POST['popup_id'] ) : 0;
        $name     = isset( $_POST['lead_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_name'] ) ) : '';
        $email    = isset( $_POST['lead_email'] ) ? sanitize_email( wp_unslash( $_POST['lead_email'] ) ) : '';
        $phone    = isset( $_POST['lead_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_phone'] ) ) : '';
        $message  = isset( $_POST['lead_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lead_message'] ) ) : '';
        $page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

        // Validation: Email is strictly enforced
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'tutor' ) ) );
        }

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'popup_id'   => $popup_id,
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'message'    => $message,
                'page_url'   => $page_url,
                'created_at' => current_time( 'mysql' ),
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if ( false === $inserted ) {
            wp_send_json_error( array( 'message' => __( 'Could not record lead at this time. Please try again.', 'tutor' ) ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Thank you! Your information has been submitted successfully.', 'tutor' ),
        ) );
    }

    /**
     * Register Submenu Page under `popup_maker`
     */
    public function register_admin_submenus() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __( 'Export Leads', 'tutor' ),
            __( 'Export Leads', 'tutor' ),
            'manage_options',
            'stm-popup-export-leads',
            array( $this, 'render_export_leads_page' )
        );
    }

    /**
     * Render the Export Leads Admin Submenu Page
     */
    public function render_export_leads_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tutor' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Ensure table exists
        $this->maybe_create_table();

        $total_leads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        $recent_leads = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 100" );

        $csv_download_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=download_popup_leads_csv' ),
            'download_popup_leads_csv'
        );
        ?>
        <div class="wrap stm-pm-export-page">
            <div class="stm-pm-header-bar">
                <div>
                    <h1><?php esc_html_e( 'Popup Maker &mdash; Captured Leads', 'tutor' ); ?></h1>
                    <p style="margin:4px 0 0 0; color:#64748b;"><?php esc_html_e( 'View captured opt-in leads and export all records to a clean CSV spreadsheet.', 'tutor' ); ?></p>
                </div>
                <div>
                    <a href="<?php echo esc_url( $csv_download_url ); ?>" class="button button-primary button-hero" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                        <?php esc_html_e( 'Download All Leads (.CSV)', 'tutor' ); ?>
                    </a>
                </div>
            </div>

            <!-- Stats Card -->
            <div class="stm-pm-stats-card">
                <span class="stm-pm-stats-num"><?php echo esc_html( number_format_i18n( $total_leads ) ); ?></span>
                <span class="stm-pm-stats-label"><?php esc_html_e( 'Total Captured Leads', 'tutor' ); ?></span>
            </div>

            <!-- Leads Table -->
            <div class="stm-pm-table-card">
                <?php if ( empty( $recent_leads ) ) : ?>
                    <div class="stm-pm-empty-state">
                        <span class="dashicons dashicons-email-alt" style="font-size: 48px; width: 48px; height: 48px;"></span>
                        <h3><?php esc_html_e( 'No leads recorded yet', 'tutor' ); ?></h3>
                        <p><?php esc_html_e( 'When visitors submit their info via the live popup modal, their records will automatically appear here.', 'tutor' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 60px;"><?php esc_html_e( 'ID', 'tutor' ); ?></th>
                                <th scope="col" style="width: 140px;"><?php esc_html_e( 'Popup Title', 'tutor' ); ?></th>
                                <th scope="col" style="width: 130px;"><?php esc_html_e( 'Name', 'tutor' ); ?></th>
                                <th scope="col" style="width: 180px;"><?php esc_html_e( 'Email', 'tutor' ); ?></th>
                                <th scope="col" style="width: 130px;"><?php esc_html_e( 'Phone', 'tutor' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Message', 'tutor' ); ?></th>
                                <th scope="col" style="width: 140px;"><?php esc_html_e( 'Page URL', 'tutor' ); ?></th>
                                <th scope="col" style="width: 150px;"><?php esc_html_e( 'Submitted Date', 'tutor' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_leads as $lead ) : ?>
                                <?php
                                $popup_title = ! empty( $lead->popup_id ) ? get_the_title( $lead->popup_id ) : __( 'General', 'tutor' );
                                ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html( $lead->id ); ?></strong></td>
                                    <td><?php echo esc_html( $popup_title ); ?></td>
                                    <td><?php echo ! empty( $lead->name ) ? esc_html( $lead->name ) : '&mdash;'; ?></td>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr( $lead->email ); ?>" style="font-weight: 600;">
                                            <?php echo esc_html( $lead->email ); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $lead->phone ) ) : ?>
                                            <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9\+]/', '', $lead->phone ) ); ?>">
                                                <?php echo esc_html( $lead->phone ); ?>
                                            </a>
                                        <?php else : ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo ! empty( $lead->message ) ? esc_html( wp_trim_words( $lead->message, 15, '...' ) ) : '&mdash;'; ?></td>
                                    <td>
                                        <?php if ( ! empty( $lead->page_url ) ) : ?>
                                            <a href="<?php echo esc_url( $lead->page_url ); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( wp_trim_words( $lead->page_url, 5, '...' ) ); ?>
                                            </a>
                                        <?php else : ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $lead->created_at ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * CSV Generation Engine
     * Hooked to `admin_post_download_popup_leads_csv`
     */
    public function handle_download_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tutor' ) );
        }

        check_admin_referer( 'download_popup_leads_csv' );

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Query all rows
        $leads = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC" );

        $filename = 'popup-leads-' . gmdate( 'Y-m-d_H-i-s' ) . '.csv';

        // Clean HTTP headers for CSV download
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Open output stream
        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM for Microsoft Excel compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // Format CSV Column Headers
        $headers = array(
            'ID',
            'Popup ID',
            'Name',
            'Email',
            'Phone',
            'Message',
            'Page URL',
            'Submission Date',
        );
        fputcsv( $output, $headers );

        // Output rows
        if ( ! empty( $leads ) ) {
            foreach ( $leads as $lead ) {
                $row = array(
                    $lead->id,
                    $lead->popup_id,
                    $lead->name,
                    $lead->email,
                    $lead->phone,
                    $lead->message,
                    $lead->page_url,
                    $lead->created_at,
                );
                fputcsv( $output, $row );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Helper to get or initialize primary popup settings for Tutor LMS Dashboard
     */
    public static function get_primary_popup_data() {
        $popups = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( ! empty( $popups ) ) {
            $p = $popups[0];
            return array(
                'id'             => $p->ID,
                'title'          => $p->post_title,
                'status'         => $p->post_status, // 'publish' or 'draft'
                'banner_url'     => get_post_meta( $p->ID, '_stm_pm_banner_url', true ),
                'banner_id'      => get_post_meta( $p->ID, '_stm_pm_banner_id', true ),
                'subtitle'       => get_post_meta( $p->ID, '_stm_pm_subtitle', true ),
                'field_name'     => get_post_meta( $p->ID, '_stm_pm_field_name', true ),
                'field_email'    => 1, // Enforced required
                'field_phone'    => get_post_meta( $p->ID, '_stm_pm_field_phone', true ),
                'field_message'  => get_post_meta( $p->ID, '_stm_pm_field_message', true ),
                'delay_sec'      => get_post_meta( $p->ID, '_stm_pm_delay_sec', true ) !== '' ? (int) get_post_meta( $p->ID, '_stm_pm_delay_sec', true ) : 2,
                'frequency_days' => get_post_meta( $p->ID, '_stm_pm_frequency_days', true ) !== '' ? (int) get_post_meta( $p->ID, '_stm_pm_frequency_days', true ) : 7,
                'cta_text'       => get_post_meta( $p->ID, '_stm_pm_cta_text', true ) ?: __( 'Submit', 'tutor' ),
            );
        }

        return array(
            'id'             => 0,
            'title'          => __( 'Exclusive Tennis Coaching Offer', 'tutor' ),
            'status'         => 'publish',
            'banner_url'     => '',
            'banner_id'      => 0,
            'subtitle'       => __( 'Enter your details below to get instant access and special lesson discounts!', 'tutor' ),
            'field_name'     => 1,
            'field_email'    => 1,
            'field_phone'    => 0,
            'field_message'  => 0,
            'delay_sec'      => 2,
            'frequency_days' => 7,
            'cta_text'       => __( 'Submit', 'tutor' ),
        );
    }

    /**
     * AJAX handler to save popup settings directly from Tutor LMS dashboard
     */
    public function handle_save_dashboard_settings() {
        if ( ! check_ajax_referer( 'stm_popup_dashboard_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security verification failed. Please refresh the page.', 'tutor' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to modify popup settings.', 'tutor' ) ) );
        }

        $popup_id = isset( $_POST['popup_id'] ) ? absint( $_POST['popup_id'] ) : 0;
        $title    = ! empty( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Exclusive Offer', 'tutor' );
        $enabled  = ! empty( $_POST['enabled'] ) ? 'publish' : 'draft';

        if ( $popup_id > 0 && get_post_type( $popup_id ) === self::POST_TYPE ) {
            wp_update_post( array(
                'ID'          => $popup_id,
                'post_title'  => $title,
                'post_status' => $enabled,
            ) );
        } else {
            $popup_id = wp_insert_post( array(
                'post_type'   => self::POST_TYPE,
                'post_title'  => $title,
                'post_status' => $enabled,
            ) );
        }

        if ( is_wp_error( $popup_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Could not save popup settings. Please try again.', 'tutor' ) ) );
        }

        $banner_url     = isset( $_POST['banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['banner_url'] ) ) : '';
        $banner_id      = isset( $_POST['banner_id'] ) ? absint( $_POST['banner_id'] ) : 0;

        // Process file upload if an image was selected directly in the form
        if ( ! empty( $_FILES['banner_file']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $upload_id = media_handle_upload( 'banner_file', $popup_id );
            if ( ! is_wp_error( $upload_id ) ) {
                $banner_id  = $upload_id;
                $banner_url = wp_get_attachment_url( $upload_id );
            }
        }

        $subtitle       = isset( $_POST['subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['subtitle'] ) ) : '';
        $field_name     = ! empty( $_POST['field_name'] ) ? 1 : 0;
        $field_phone    = ! empty( $_POST['field_phone'] ) ? 1 : 0;
        $field_message  = ! empty( $_POST['field_message'] ) ? 1 : 0;
        $delay_sec      = isset( $_POST['delay_sec'] ) ? absint( $_POST['delay_sec'] ) : 2;
        $frequency_days = isset( $_POST['frequency_days'] ) ? absint( $_POST['frequency_days'] ) : 7;
        $cta_text       = ! empty( $_POST['cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_text'] ) ) : __( 'Submit', 'tutor' );

        update_post_meta( $popup_id, '_stm_pm_banner_url', $banner_url );
        update_post_meta( $popup_id, '_stm_pm_banner_id', $banner_id );
        update_post_meta( $popup_id, '_stm_pm_subtitle', $subtitle );
        update_post_meta( $popup_id, '_stm_pm_field_name', $field_name );
        update_post_meta( $popup_id, '_stm_pm_field_email', 1 ); // Always required
        update_post_meta( $popup_id, '_stm_pm_field_phone', $field_phone );
        update_post_meta( $popup_id, '_stm_pm_field_message', $field_message );
        update_post_meta( $popup_id, '_stm_pm_delay_sec', $delay_sec );
        update_post_meta( $popup_id, '_stm_pm_frequency_days', $frequency_days );
        update_post_meta( $popup_id, '_stm_pm_cta_text', $cta_text );

        wp_send_json_success( array(
            'message'    => __( 'Popup settings saved successfully!', 'tutor' ),
            'popup_id'   => $popup_id,
            'banner_url' => $banner_url,
            'banner_id'  => $banner_id,
        ) );
    }

    /**
     * AJAX handler for direct instant image upload from Tutor LMS dashboard
     */
    public function handle_dashboard_image_upload() {
        if ( ! check_ajax_referer( 'stm_popup_dashboard_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security verification failed. Please refresh the page.', 'tutor' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to upload files.', 'tutor' ) ) );
        }

        if ( empty( $_FILES['banner_file']['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file was selected. Please choose a photo.', 'tutor' ) ) );
        }

        $file = $_FILES['banner_file'];

        // Strict image validation
        $filetype = wp_check_filetype( $file['name'], null );
        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
        );

        if ( ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file format! Strictly images only: JPG, PNG, WEBP, or GIF.', 'tutor' ) ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $att_id = media_handle_upload( 'banner_file', 0 );

        if ( is_wp_error( $att_id ) ) {
            wp_send_json_error( array( 'message' => $att_id->get_error_message() ) );
        }

        $image_url = wp_get_attachment_url( $att_id );

        wp_send_json_success( array(
            'message' => __( 'Photo uploaded successfully!', 'tutor' ),
            'url'     => $image_url,
            'id'      => $att_id,
        ) );
    }
}
