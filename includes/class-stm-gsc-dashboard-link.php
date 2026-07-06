<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STM_GSC_Dashboard_Link {
    const OPTION_NAME = 'stm_gsc_dashboard_link_options';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ), 100 );

        add_shortcode( 'stm_gsc_dashboard_link', array( $this, 'render_shortcode' ) );
    }

    public function register_settings_page() {
        add_options_page(
            'GSC Dashboard Link',
            'GSC Dashboard',
            'manage_options',
            'stm-gsc-dashboard',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            'stm_gsc_dashboard_link',
            self::OPTION_NAME,
            array(
                'sanitize_callback' => array( $this, 'sanitize_options' ),
                'default'           => $this->get_default_options(),
            )
        );

        add_settings_section(
            'stm_gsc_dashboard_link_main',
            'Google Search Console',
            '__return_false',
            'stm-gsc-dashboard'
        );

        add_settings_field(
            'enabled',
            'Show one-click link',
            array( $this, 'render_enabled_field' ),
            'stm-gsc-dashboard',
            'stm_gsc_dashboard_link_main'
        );

        add_settings_field(
            'gsc_url',
            'Search Console URL',
            array( $this, 'render_url_field' ),
            'stm-gsc-dashboard',
            'stm_gsc_dashboard_link_main'
        );

        add_settings_field(
            'account_label',
            'Account label',
            array( $this, 'render_account_label_field' ),
            'stm-gsc-dashboard',
            'stm_gsc_dashboard_link_main'
        );

        add_settings_field(
            'button_label',
            'Button label',
            array( $this, 'render_button_label_field' ),
            'stm-gsc-dashboard',
            'stm_gsc_dashboard_link_main'
        );
    }

    public function sanitize_options( $options ) {
        $defaults = $this->get_default_options();
        $options  = is_array( $options ) ? $options : array();

        return array(
            'enabled'       => empty( $options['enabled'] ) ? 0 : 1,
            'gsc_url'       => isset( $options['gsc_url'] ) ? esc_url_raw( trim( wp_unslash( $options['gsc_url'] ) ) ) : '',
            'account_label' => isset( $options['account_label'] ) ? sanitize_text_field( wp_unslash( $options['account_label'] ) ) : $defaults['account_label'],
            'button_label'  => isset( $options['button_label'] ) ? sanitize_text_field( wp_unslash( $options['button_label'] ) ) : $defaults['button_label'],
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>GSC Dashboard Link</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'stm_gsc_dashboard_link' );
                do_settings_sections( 'stm-gsc-dashboard' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_enabled_field() {
        $options = $this->get_options();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $options['enabled'] ); ?>>
            Show the GSC shortcut on dashboards.
        </label>
        <?php
    }

    public function render_url_field() {
        $options = $this->get_options();
        ?>
        <input type="url"
               class="regular-text"
               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[gsc_url]"
               value="<?php echo esc_attr( $options['gsc_url'] ); ?>"
               placeholder="https://search.google.com/search-console">
        <p class="description">Paste your Google Search Console property or account URL.</p>
        <?php
    }

    public function render_account_label_field() {
        $options = $this->get_options();
        ?>
        <input type="text"
               class="regular-text"
               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[account_label]"
               value="<?php echo esc_attr( $options['account_label'] ); ?>"
               placeholder="My GSC Account">
        <?php
    }

    public function render_button_label_field() {
        $options = $this->get_options();
        ?>
        <input type="text"
               class="regular-text"
               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[button_label]"
               value="<?php echo esc_attr( $options['button_label'] ); ?>"
               placeholder="Open GSC">
        <?php
    }

    public function register_dashboard_widget() {
        if ( ! $this->should_show_link() ) {
            return;
        }

        wp_add_dashboard_widget(
            'stm_gsc_dashboard_link',
            'Google Search Console',
            array( $this, 'render_dashboard_widget' )
        );
    }

    public function render_dashboard_widget() {
        echo $this->get_link_markup( 'stm-gsc-admin-widget-link' );
    }

    public function maybe_enqueue_frontend_assets() {
        if ( is_admin() || ! $this->should_show_link() || ! $this->user_is_allowed() || ! $this->is_tutor_dashboard_context() ) {
            return;
        }

        wp_enqueue_style(
            'stm-gsc-dashboard',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-gsc-dashboard.css',
            array(),
            STM_TUTOR_CUSTOMIZATION_VERSION
        );

        wp_enqueue_script(
            'stm-gsc-dashboard',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-gsc-dashboard.js',
            array( 'jquery' ),
            STM_TUTOR_CUSTOMIZATION_VERSION,
            true
        );

        $options = $this->get_options();
        wp_localize_script(
            'stm-gsc-dashboard',
            'stmGscDashboard',
            array(
                'url'          => esc_url( $options['gsc_url'] ),
                'buttonLabel'  => $options['button_label'],
                'accountLabel' => $options['account_label'],
                'isInstructor' => function_exists( 'stm_is_current_user_instructor' ) && stm_is_current_user_instructor(),
            )
        );
    }

    public function render_shortcode() {
        if ( ! $this->should_show_link() || ! $this->user_is_allowed() ) {
            return '';
        }

        return $this->get_link_markup( 'stm-gsc-shortcode-link' );
    }

    private function get_link_markup( $class = '' ) {
        $options = $this->get_options();

        if ( empty( $options['gsc_url'] ) ) {
            return '<p>Set your Google Search Console URL in Settings > GSC Dashboard.</p>';
        }

        ob_start();
        ?>
        <div class="stm-gsc-link-wrap">
            <?php if ( ! empty( $options['account_label'] ) ) : ?>
                <p class="stm-gsc-account-label"><?php echo esc_html( $options['account_label'] ); ?></p>
            <?php endif; ?>
            <a class="button button-primary <?php echo esc_attr( $class ); ?>"
               href="<?php echo esc_url( $options['gsc_url'] ); ?>"
               target="_blank"
               rel="noopener noreferrer">
                <?php echo esc_html( $options['button_label'] ); ?>
            </a>
        </div>
        <?php

        return ob_get_clean();
    }

    private function should_show_link() {
        $options = $this->get_options();

        return ! empty( $options['enabled'] ) && ! empty( $options['gsc_url'] );
    }

    private function user_is_allowed() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user    = wp_get_current_user();
        $allowed = array( 'administrator', 'instructor', 'tutor_instructor', 'teacher', 'author' );

        return ! empty( array_intersect( (array) $user->roles, $allowed ) );
    }

    private function is_tutor_dashboard_context() {
        if ( is_singular() ) {
            $post = get_post();

            if ( $post && has_shortcode( $post->post_content, 'tutor_dashboard' ) ) {
                return true;
            }
        }

        $dashboard_page_id = (int) get_option( 'tutor_dashboard_page_id' );

        if ( $dashboard_page_id && is_page( $dashboard_page_id ) ) {
            return true;
        }

        $tutor_options = get_option( 'tutor_option', array() );

        if ( is_array( $tutor_options ) && ! empty( $tutor_options['tutor_dashboard_page_id'] ) && is_page( (int) $tutor_options['tutor_dashboard_page_id'] ) ) {
            return true;
        }

        return false;
    }

    private function get_options() {
        return wp_parse_args(
            get_option( self::OPTION_NAME, array() ),
            $this->get_default_options()
        );
    }

    private function get_default_options() {
        return array(
            'enabled'       => 0,
            'gsc_url'       => '',
            'account_label' => 'My GSC Account',
            'button_label'  => 'Open GSC',
        );
    }
}
