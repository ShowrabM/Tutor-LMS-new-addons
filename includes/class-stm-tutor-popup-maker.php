<?php
/** Popup campaigns, lead capture and gift delivery for the Tutor dashboard. */
defined( 'ABSPATH' ) || exit;

class STM_Tutor_Popup_Maker {
    const POST_TYPE = 'popup_maker';
    const TABLE_NAME = 'popup_leads';
    const DB_VERSION = '1.0.0';
    const LEAD_NONCE = 'stm_popup_lead_nonce';
    const DASH_NONCE = 'stm_popup_dashboard_nonce';

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'maybe_initialize' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_frontend_popup' ) );
        add_action( 'template_redirect', array( $this, 'render_preview' ), 1 );
        add_action( 'admin_init', array( $this, 'prepare_ajax_response' ), -100 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_submenus' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'invalidate_popup_cache' ) );
        add_filter( 'tutor_dashboard/nav_items_all', array( $this, 'dashboard_nav' ) );
        add_filter( 'load_dashboard_template_part_from_other_location', array( $this, 'dashboard_template' ) );
        add_filter( 'body_class', array( $this, 'dashboard_body_class' ) );
        foreach ( array(
            'stm_save_dashboard_popup_settings' => 'handle_save_dashboard_settings',
            'stm_pm_dashboard_upload_image' => 'handle_dashboard_image_upload',
            'stm_pm_upload_asset' => 'handle_dashboard_image_upload',
            'stm_pm_campaign_action' => 'handle_campaign_action',
            'stm_submit_popup_lead' => 'handle_submit_lead',
        ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( $this, $method ) );
        }
        add_action( 'wp_ajax_nopriv_stm_submit_popup_lead', array( $this, 'handle_submit_lead' ) );
        add_action( 'admin_post_download_popup_leads_csv', array( $this, 'handle_download_csv' ) );
        add_action( 'admin_post_stm_popup_download_gift', array( $this, 'handle_download_gift' ) );
        add_action( 'admin_post_nopriv_stm_popup_download_gift', array( $this, 'handle_download_gift' ) );
    }

    public static function dashboard_url() {
        return function_exists( 'tutor_utils' ) ? trailingslashit( tutor_utils()->get_tutor_dashboard_page_permalink() ) . 'popup-maker/' : home_url( '/dashboard/popup-maker/' );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array( 'name' => 'Popups', 'singular_name' => 'Popup', 'add_new_item' => 'Add popup' ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-megaphone', 'supports' => array( 'title' ),
            'capabilities' => array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_options' ),
            'map_meta_cap' => false, 'rewrite' => false,
        ) );
    }

    public function dashboard_nav( $items ) {
        $items['popup-maker'] = array( 'title' => 'Popup & Leads', 'auth_cap' => 'manage_options', 'icon' => 'tutor-icon-email', 'show_ui' => current_user_can( 'manage_options' ) );
        return $items;
    }

    public function dashboard_template( $location ) {
        return 'popup-maker' === get_query_var( 'tutor_dashboard_page' ) ? STM_TUTOR_CUSTOMIZATION_DIR . 'templates/dashboard/popup-maker.php' : $location;
    }

    public function dashboard_body_class( $classes ) {
        if ( 'popup-maker' === get_query_var( 'tutor_dashboard_page' ) && current_user_can( 'manage_options' ) ) { $classes[] = 'stm-pm-managing'; }
        return $classes;
    }

    public function invalidate_popup_cache() {
        // Campaigns appear site-wide, so a status change must refresh cached pages too.
        $content = realpath( WP_CONTENT_DIR );
        $cache = realpath( WP_CONTENT_DIR . '/cache' );
        if ( $content && $cache && 0 === strpos( wp_normalize_path( $cache ), trailingslashit( wp_normalize_path( $content ) ) ) ) {
            do_action( 'wpfc_clear_all_cache' );
        }
    }

    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE {$table} (
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
        ) {$charset};" );
        update_option( 'stm_popup_leads_db_version', self::DB_VERSION );
    }

    public function maybe_initialize() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        if ( self::DB_VERSION !== get_option( 'stm_popup_leads_db_version' ) ) { self::activate(); }
        if ( '1' !== get_option( 'stm_popup_dashboard_route_version' ) ) {
            flush_rewrite_rules( false );
            update_option( 'stm_popup_dashboard_route_version', '1', false );
        }
    }

    public function prepare_ajax_response() {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
        $actions = array( 'stm_pm_upload_asset', 'stm_pm_dashboard_upload_image', 'stm_pm_campaign_action', 'stm_save_dashboard_popup_settings', 'stm_submit_popup_lead', 'download_popup_leads_csv', 'stm_popup_download_gift' );
        $media_actions = array( 'query-attachments', 'get-attachment', 'upload-attachment', 'save-attachment', 'save-attachment-compat', 'send-attachment-to-editor' );
        $media_request = current_user_can( 'manage_options' ) && in_array( $action, $media_actions, true ) && 0 === strpos( (string) ( $_SERVER['HTTP_REFERER'] ?? '' ), self::dashboard_url() );
        if ( in_array( $action, $actions, true ) || $media_request ) {
            global $wpdb;
            $wpdb->hide_errors(); // Keep diagnostics in logs, not JSON or document downloads.
        }
    }

    public static function document_mimes() {
        return array( 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'odt' => 'application/vnd.oasis.opendocument.text', 'txt' => 'text/plain' );
    }

    public static function get_popup_data( $id = 0 ) {
        $defaults = array(
            'id' => 0, 'title' => '', 'status' => 'draft', 'banner_id' => 0, 'banner_url' => '',
            'subtitle' => '', 'field_name' => 1, 'field_phone' => 0, 'field_message' => 0,
            'delay_sec' => 3, 'frequency_days' => 7, 'cta_text' => 'Get my free guide',
            'gift_id' => 0, 'gift_label' => 'Download your gift', 'success_message' => 'Thank you! Your submission has been received.',
            'priority' => 0,
        );
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) { return $defaults; }
        $defaults['id'] = (int) $post->ID;
        $defaults['title'] = $post->post_title;
        $defaults['status'] = $post->post_status;
        foreach ( $defaults as $key => $value ) {
            if ( in_array( $key, array( 'id', 'title', 'status' ), true ) ) { continue; }
            if ( metadata_exists( 'post', $post->ID, '_stm_pm_' . $key ) ) {
                $meta = get_post_meta( $post->ID, '_stm_pm_' . $key, true );
                $defaults[ $key ] = is_int( $value ) ? (int) $meta : (string) $meta;
            }
        }
        return $defaults;
    }

    public static function get_primary_popup_data() {
        $posts = self::get_campaigns();
        return self::get_popup_data( $posts ? $posts[0]->ID : 0 );
    }

    public static function get_campaigns( $status = array( 'publish', 'draft', 'pending', 'private' ) ) {
        return get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => $status, 'posts_per_page' => -1, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ) ) );
    }

    private function active_campaigns() {
        $posts = self::get_campaigns( 'publish' );
        usort( $posts, static function( $a, $b ) {
            $priority = (int) get_post_meta( $b->ID, '_stm_pm_priority', true ) <=> (int) get_post_meta( $a->ID, '_stm_pm_priority', true );
            return $priority ?: $b->ID <=> $a->ID;
        } );
        return $posts;
    }

    public function enqueue_frontend_assets() {
        if ( is_admin() ) { return; }
        $dashboard = function_exists( 'stm_is_tutor_dashboard_page' ) && stm_is_tutor_dashboard_page();
        if ( 'popup-maker' === get_query_var( 'tutor_dashboard_page' ) && current_user_can( 'manage_options' ) ) {
            wp_enqueue_media();
            wp_enqueue_style( 'stm-popup-dashboard', STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-popup-dashboard.css', array(), STM_TUTOR_CUSTOMIZATION_VERSION );
            wp_enqueue_script( 'stm-popup-dashboard', STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-popup-dashboard.js', array( 'media-views' ), STM_TUTOR_CUSTOMIZATION_VERSION, true );
            wp_localize_script( 'stm-popup-dashboard', 'stmPopupDashboard', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( self::DASH_NONCE ), 'baseUrl' => self::dashboard_url() ) );
        }
        if ( $dashboard || ! $this->active_campaigns() ) { return; }
        wp_enqueue_style( 'stm-popup-frontend-css', STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-popup-frontend.css', array(), STM_TUTOR_CUSTOMIZATION_VERSION );
        wp_enqueue_script( 'stm-popup-frontend-js', STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-popup-frontend.js', array(), STM_TUTOR_CUSTOMIZATION_VERSION, true );
        wp_localize_script( 'stm-popup-frontend-js', 'stmPopupMakerData', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
    }

    public function render_frontend_popup() {
        if ( is_admin() || ( function_exists( 'stm_is_tutor_dashboard_page' ) && stm_is_tutor_dashboard_page() ) ) { return; }
        foreach ( $this->active_campaigns() as $post ) { $this->render_popup_markup( self::get_popup_data( $post->ID ) ); }
    }

    public function render_popup_markup( $popup, $preview = false ) {
        include STM_TUTOR_CUSTOMIZATION_DIR . 'templates/popup-offer.php';
    }

    public static function preview_url( $id ) {
        return wp_nonce_url( add_query_arg( 'stm_pm_preview', absint( $id ), home_url( '/' ) ), 'stm_pm_preview_' . absint( $id ) );
    }

    public function render_preview() {
        if ( ! isset( $_GET['stm_pm_preview'] ) ) { return; }
        $id = absint( $_GET['stm_pm_preview'] );
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'stm_pm_preview_' . $id ) ) { wp_die( 'You do not have permission to preview this popup.', '', array( 'response' => 403 ) ); }
        $popup = self::get_popup_data( $id );
        if ( ! $popup['id'] ) { wp_die( 'Popup not found.', '', array( 'response' => 404 ) ); }
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
        ?>
        <!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Popup preview</title><link rel="stylesheet" href="<?php echo esc_url( STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-popup-frontend.css?ver=' . STM_TUTOR_CUSTOMIZATION_VERSION ); ?>"></head><body style="margin:0;background:#eef1ed;font-family:system-ui"><div style="padding:18px;color:#34473d">Preview · No leads will be recorded</div><?php $this->render_popup_markup( $popup, true ); ?><script>window.stmPopupMakerData=<?php echo wp_json_encode( array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) ); ?>;</script><script src="<?php echo esc_url( STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-popup-frontend.js?ver=' . STM_TUTOR_CUSTOMIZATION_VERSION ); ?>"></script></body></html>
        <?php
        exit;
    }

    private function require_manager() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Only administrators can manage popups.' ), 403 ); }
        if ( ! check_ajax_referer( self::DASH_NONCE, 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Your session has expired. Refresh the page and try again.' ), 403 ); }
    }

    private function valid_document( $id ) {
        $file = $id ? get_attached_file( $id ) : false;
        return $file && is_file( $file ) && in_array( get_post_mime_type( $id ), self::document_mimes(), true );
    }

    public function handle_dashboard_image_upload() {
        $this->require_manager();
        if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error( array( 'message' => 'You cannot upload media.' ), 403 ); }
        $kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'image';
        $key = isset( $_FILES['asset_file'] ) ? 'asset_file' : 'banner_file';
        $file = $_FILES[ $key ] ?? null;
        if ( ! $file || ! empty( $file['error'] ) ) { wp_send_json_error( array( 'message' => 'The upload failed. Check the file size and try again.' ), 400 ); }
        $mimes = 'document' === $kind ? self::document_mimes() : array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
        $type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
        if ( empty( $type['type'] ) || ( 'image' === $kind && ! in_array( wp_get_image_mime( $file['tmp_name'] ), $mimes, true ) ) ) { wp_send_json_error( array( 'message' => 'document' === $kind ? 'Choose a PDF, DOC, DOCX, ODT or TXT document.' : 'Choose a JPG, PNG, WebP or GIF image.' ), 400 ); }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $id = media_handle_upload( $key, 0, array(), array( 'test_form' => false, 'mimes' => $mimes ) );
        if ( is_wp_error( $id ) ) { wp_send_json_error( array( 'message' => $id->get_error_message() ), 400 ); }
        wp_send_json_success( array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'filename' => basename( get_attached_file( $id ) ), 'mime' => get_post_mime_type( $id ) ) );
    }

    public function handle_save_dashboard_settings() {
        $this->require_manager();
        $id = absint( $_POST['popup_id'] ?? 0 );
        if ( $id && ( self::POST_TYPE !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) ) { wp_send_json_error( array( 'message' => 'Popup not found. Return to the popup list.' ), 404 ); }
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        if ( '' === trim( $title ) ) { wp_send_json_error( array( 'message' => 'Add a headline for this popup.' ), 400 ); }
        $banner = absint( $_POST['banner_id'] ?? 0 );
        $banner_url = esc_url_raw( wp_unslash( $_POST['banner_url'] ?? '' ) );
        if ( $banner ) {
            if ( ! wp_attachment_is_image( $banner ) || ! preg_match( '/^image\/(jpeg|png|webp|gif)$/', get_post_mime_type( $banner ) ) ) { wp_send_json_error( array( 'message' => 'Choose a valid banner image from the Media Library.' ), 400 ); }
            $banner_url = wp_get_attachment_url( $banner );
        }
        $gift = absint( $_POST['gift_id'] ?? 0 );
        if ( $gift && ! $this->valid_document( $gift ) ) { wp_send_json_error( array( 'message' => 'The gift document is unavailable. Choose a PDF, DOC, DOCX, ODT or TXT file from the Media Library.' ), 400 ); }
        $post = array( 'post_type' => self::POST_TYPE, 'post_title' => mb_substr( $title, 0, 180 ), 'post_status' => '1' === (string) ( $_POST['enabled'] ?? '0' ) ? 'publish' : 'draft' );
        if ( $id ) { $post['ID'] = $id; }
        $id = $id ? wp_update_post( $post, true ) : wp_insert_post( $post, true );
        if ( is_wp_error( $id ) || ! $id ) { wp_send_json_error( array( 'message' => 'Could not save this popup. Please try again.' ), 500 ); }
        $meta = array(
            'banner_id' => $banner, 'banner_url' => $banner_url, 'gift_id' => $gift,
            'subtitle' => sanitize_textarea_field( wp_unslash( $_POST['subtitle'] ?? '' ) ),
            'field_name' => empty( $_POST['field_name'] ) ? 0 : 1, 'field_phone' => empty( $_POST['field_phone'] ) ? 0 : 1, 'field_message' => empty( $_POST['field_message'] ) ? 0 : 1,
            'delay_sec' => min( 120, absint( $_POST['delay_sec'] ?? 3 ) ), 'frequency_days' => min( 365, absint( $_POST['frequency_days'] ?? 7 ) ),
            'priority' => min( 100, absint( $_POST['priority'] ?? 0 ) ),
            'cta_text' => sanitize_text_field( wp_unslash( $_POST['cta_text'] ?? '' ) ) ?: 'Get my free guide',
            'gift_label' => sanitize_text_field( wp_unslash( $_POST['gift_label'] ?? '' ) ) ?: 'Download your gift',
            'success_message' => sanitize_textarea_field( wp_unslash( $_POST['success_message'] ?? '' ) ) ?: 'Thank you! Your submission has been received.',
        );
        foreach ( $meta as $key => $value ) { update_post_meta( $id, '_stm_pm_' . $key, $value ); }
        $this->invalidate_popup_cache();
        wp_send_json_success( array( 'popup_id' => $id, 'message' => 'Popup saved.', 'preview_url' => self::preview_url( $id ) ) );
    }

    public function handle_campaign_action() {
        $this->require_manager();
        $id = absint( $_POST['popup_id'] ?? 0 );
        if ( self::POST_TYPE !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) { wp_send_json_error( array( 'message' => 'Popup not found.' ), 404 ); }
        $operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
        if ( 'duplicate' === $operation ) {
            $copy = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_title' => get_the_title( $id ) . ' (copy)', 'post_status' => 'draft' ), true );
            if ( is_wp_error( $copy ) || ! $copy ) { wp_send_json_error( array( 'message' => 'Could not duplicate this popup.' ), 500 ); }
            foreach ( get_post_meta( $id ) as $key => $values ) {
                if ( 0 === strpos( $key, '_stm_pm_' ) ) { update_post_meta( $copy, $key, maybe_unserialize( $values[0] ) ); }
            }
            wp_send_json_success( array( 'popup_id' => $copy, 'operation' => 'duplicated' ) );
        }
        if ( 'trash' === $operation ) {
            if ( ! wp_trash_post( $id ) ) { wp_send_json_error( array( 'message' => 'Could not move this popup to trash.' ), 500 ); }
        } elseif ( in_array( $operation, array( 'activate', 'pause' ), true ) ) {
            $saved = wp_update_post( array( 'ID' => $id, 'post_status' => 'activate' === $operation ? 'publish' : 'draft' ), true );
            if ( is_wp_error( $saved ) ) { wp_send_json_error( array( 'message' => 'Could not change popup status.' ), 500 ); }
        } else { wp_send_json_error( array( 'message' => 'Unknown popup action.' ), 400 ); }
        $this->invalidate_popup_cache();
        wp_send_json_success( array( 'popup_id' => $id, 'operation' => $operation ) );
    }

    public function handle_submit_lead() {
        if ( ! check_ajax_referer( self::LEAD_NONCE, 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Refresh the page and try again.' ), 403 ); }
        $id = absint( $_POST['popup_id'] ?? 0 );
        if ( self::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) { wp_send_json_error( array( 'message' => 'This offer is no longer active.' ), 400 ); }
        if ( ! empty( $_POST['company_website'] ) ) { wp_send_json_error( array( 'message' => 'Please try again.' ), 400 ); }
        $email = sanitize_email( wp_unslash( $_POST['lead_email'] ?? '' ) );
        if ( ! is_email( $email ) || strlen( $email ) > 120 ) { wp_send_json_error( array( 'message' => 'Enter a valid email address.' ), 400 ); }
        $popup = self::get_popup_data( $id );
        global $wpdb;
        $record = array(
            'popup_id' => $id,
            'name' => $popup['field_name'] ? mb_substr( sanitize_text_field( wp_unslash( $_POST['lead_name'] ?? '' ) ), 0, 120 ) : '',
            'email' => $email,
            'phone' => $popup['field_phone'] ? mb_substr( sanitize_text_field( wp_unslash( $_POST['lead_phone'] ?? '' ) ), 0, 50 ) : '',
            'message' => $popup['field_message'] ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['lead_message'] ?? '' ) ), 0, 5000 ) : '',
            'page_url' => mb_substr( esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) ), 0, 255 ), 'created_at' => current_time( 'mysql' ),
        );
        if ( false === $wpdb->insert( $wpdb->prefix . self::TABLE_NAME, $record, array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) ) ) { wp_send_json_error( array( 'message' => 'We could not save your details. Please try again.' ), 500 ); }
        $lead_id = (int) $wpdb->insert_id;
        $result = array( 'message' => $popup['success_message'] ?: 'Thank you! Your submission has been received.' );
        if ( $popup['gift_id'] && $this->valid_document( $popup['gift_id'] ) ) {
            $result['gift_url'] = $this->gift_url( $lead_id, $popup['gift_id'] );
            $result['gift_label'] = $popup['gift_label'];
            $result['gift_name'] = basename( get_attached_file( $popup['gift_id'] ) );
        }
        wp_send_json_success( $result );
    }

    private function gift_url( $lead, $file ) {
        $expires = time() + DAY_IN_SECONDS;
        $token = hash_hmac( 'sha256', $lead . '|' . $file . '|' . $expires, wp_salt( 'auth' ) );
        return add_query_arg( array( 'action' => 'stm_popup_download_gift', 'lead' => $lead, 'file' => $file, 'expires' => $expires, 'token' => $token ), admin_url( 'admin-post.php' ) );
    }

    public function handle_download_gift() {
        $lead = absint( $_GET['lead'] ?? 0 ); $file = absint( $_GET['file'] ?? 0 ); $expires = absint( $_GET['expires'] ?? 0 );
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
        $expected = hash_hmac( 'sha256', $lead . '|' . $file . '|' . $expires, wp_salt( 'auth' ) );
        global $wpdb;
        if ( ! $lead || $expires < time() || $expires > time() + DAY_IN_SECONDS + 60 || ! hash_equals( $expected, $token ) || ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . self::TABLE_NAME . ' WHERE id=%d', $lead ) ) || ! $this->valid_document( $file ) ) { wp_die( 'This gift link is invalid or has expired. Please submit the offer form again.', '', array( 'response' => 403 ) ); }
        $path = realpath( get_attached_file( $file ) ); $uploads = realpath( wp_upload_dir()['basedir'] );
        if ( ! $path || ! $uploads || 0 !== strpos( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $uploads ) ) ) ) { wp_die( 'Document unavailable.', '', array( 'response' => 404 ) ); }
        nocache_headers(); header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: ' . get_post_mime_type( $file ) );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path ); exit;
    }

    public function handle_download_csv() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator access required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'download_popup_leads_csv' );
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME; $id = absint( $_GET['campaign'] ?? 0 );
        $rows = $wpdb->get_results( $id ? $wpdb->prepare( "SELECT * FROM {$table} WHERE popup_id=%d ORDER BY id DESC", $id ) : "SELECT * FROM {$table} ORDER BY id DESC" );
        nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="popup-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'ID', 'Popup', 'Name', 'Email', 'Phone', 'Message', 'Page URL', 'Submitted' ) );
        foreach ( $rows as $row ) {
            $cells = array( $row->id, get_the_title( $row->popup_id ), $row->name, $row->email, $row->phone, $row->message, $row->page_url, $row->created_at );
            fputcsv( $out, array_map( static function( $value ) { $value = (string) $value; return preg_match( '/^[\s]*[=+@-]|^[\t\r\n]/u', $value ) ? "'" . $value : $value; }, $cells ) );
        }
        fclose( $out ); exit;
    }

    public function register_meta_boxes() {
        add_meta_box( 'stm-popup-manager', 'Popup design & gift', function( $post ) {
            echo '<p>Manage the banner, gift document, form and display settings in Popup &amp; Leads.</p><a class="button button-primary" href="' . esc_url( add_query_arg( 'popup', $post->ID, self::dashboard_url() ) ) . '">Open popup editor</a>';
        }, self::POST_TYPE, 'normal', 'high' );
    }

    public function register_admin_submenus() {
        add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'Popup & Leads', 'Popup & Leads', 'manage_options', 'stm-popup-manager', function() {
            echo '<div class="wrap"><h1>Popup &amp; Leads</h1><p>Manage all campaigns, documents and captured leads from your dashboard.</p><a class="button button-primary" href="' . esc_url( self::dashboard_url() ) . '">Open popup manager</a></div>';
        } );
    }
}
