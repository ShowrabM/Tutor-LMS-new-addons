<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STM_Tutor_Testimonial_Manager {
    const POST_TYPE = 'tdl_testimonial';
    const NONCE_KEY = 'tdl_testimonial_save';

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'handle_save' ) );
        add_action( 'init', array( $this, 'handle_delete' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 99 );
        add_action( 'wp_footer', array( $this, 'render_upload_modal' ) );

        add_shortcode( 'tdl_testimonial_form', array( $this, 'render_form' ) );
        add_shortcode( 'tdl_testimonial_carousel', array( $this, 'render_carousel' ) );
    }

    public function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'          => 'Testimonials',
                    'singular_name' => 'Testimonial',
                ),
                'public'              => false,
                'show_ui'             => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title', 'author' ),
            )
        );
    }

    public function maybe_enqueue_assets() {
        if ( is_admin() ) {
            return;
        }

        $allowed_user = $this->user_is_allowed();
        $has_form     = $this->page_has_shortcode( 'tdl_testimonial_form' );
        $has_carousel = $this->page_has_shortcode( 'tdl_testimonial_carousel' );

        if ( ! $allowed_user && ! $has_form && ! $has_carousel ) {
            return;
        }

        wp_enqueue_style(
            'stm-tutor-testimonial',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-testimonial.css',
            array(),
            STM_TUTOR_CUSTOMIZATION_VERSION
        );

        wp_enqueue_script(
            'stm-tutor-testimonial',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-testimonial.js',
            array( 'jquery' ),
            STM_TUTOR_CUSTOMIZATION_VERSION,
            true
        );

        wp_localize_script(
            'stm-tutor-testimonial',
            'stmTutorTestimonial',
            array(
                'isAllowed'          => $allowed_user,
                'saved'              => isset( $_GET['tdl_saved'] ),
                'confirmDelete'      => 'Delete this testimonial?',
                'selectImageTitle'   => 'Select Logo or Image',
                'selectImageButton'  => 'Use this image',
                'selectVideoTitle'   => 'Select Video',
                'selectVideoButton'  => 'Use this video',
                'selectThumbTitle'   => 'Select Thumbnail',
                'selectThumbButton'  => 'Use this image',
            )
        );

        if ( $allowed_user ) {
            wp_enqueue_media();
        }
    }

    public function handle_save() {
        if ( ! isset( $_POST['tdl_testimonial_save'] ) || ! $this->user_is_allowed() ) {
            return;
        }

        $nonce = isset( $_POST['tdl_t_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tdl_t_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_KEY ) ) {
            return;
        }

        $type      = isset( $_POST['tdl_t_type'] ) ? sanitize_key( wp_unslash( $_POST['tdl_t_type'] ) ) : 'text';
        $name      = isset( $_POST['tdl_t_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tdl_t_name'] ) ) : '';
        $company   = isset( $_POST['tdl_t_company'] ) ? sanitize_text_field( wp_unslash( $_POST['tdl_t_company'] ) ) : '';
        $website   = isset( $_POST['tdl_t_website'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_t_website'] ) ) : '';
        $quote     = isset( $_POST['tdl_t_quote'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tdl_t_quote'] ) ) : '';
        $rating    = isset( $_POST['tdl_t_rating'] ) ? absint( wp_unslash( $_POST['tdl_t_rating'] ) ) : 5;
        $logo      = isset( $_POST['tdl_t_logo'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_t_logo'] ) ) : '';
        $video_url = isset( $_POST['tdl_t_video'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_t_video'] ) ) : '';
        $thumbnail = isset( $_POST['tdl_t_thumbnail'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_t_thumbnail'] ) ) : '';

        if ( empty( $name ) ) {
            return;
        }

        if ( 'text' === $type && empty( $quote ) ) {
            return;
        }

        if ( 'video' === $type && empty( $video_url ) ) {
            return;
        }

        $rating = max( 1, min( 5, $rating ) );

        $post_id = wp_insert_post(
            array(
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $name,
                'post_author' => get_current_user_id(),
            ),
            true
        );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return;
        }

        update_post_meta( $post_id, '_tdl_t_type', $type );
        update_post_meta( $post_id, '_tdl_t_company', $company );
        update_post_meta( $post_id, '_tdl_t_website', $website );
        update_post_meta( $post_id, '_tdl_t_quote', $quote );
        update_post_meta( $post_id, '_tdl_t_rating', $rating );
        update_post_meta( $post_id, '_tdl_t_logo', $logo );
        update_post_meta( $post_id, '_tdl_t_video', $video_url );
        update_post_meta( $post_id, '_tdl_t_thumbnail', $thumbnail );

        wp_safe_redirect( add_query_arg( 'tdl_saved', '1', $this->get_current_url() ) );
        exit;
    }

    public function handle_delete() {
        if ( ! isset( $_GET['tdl_t_delete'] ) || ! $this->user_is_allowed() ) {
            return;
        }

        $post_id = absint( wp_unslash( $_GET['tdl_t_delete'] ) );
        if ( ! $post_id ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tdl_t_delete_' . $post_id ) ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return;
        }

        if ( (int) $post->post_author !== (int) get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_delete_post( $post_id, true );
        wp_safe_redirect( remove_query_arg( array( 'tdl_t_delete', '_wpnonce' ), $this->get_current_url() ) );
        exit;
    }

    public function render_form() {
        if ( ! $this->user_is_allowed() ) {
            return '<p>You do not have permission to add testimonials.</p>';
        }

        ob_start();

        if ( isset( $_GET['tdl_saved'] ) ) {
            echo '<div class="tdl-t-msg">Testimonial saved successfully.</div>';
        }

        echo $this->get_form_markup( true );

        return ob_get_clean();
    }

    public function render_upload_modal() {
        if ( ! $this->user_is_allowed() || $this->page_has_shortcode( 'tdl_testimonial_form' ) ) {
            return;
        }
        ?>
        <div class="tdl-t-modal" id="tdl-testimonial-modal" aria-hidden="true">
            <div class="tdl-t-modal-backdrop" data-tdl-t-modal-close="1"></div>
            <div class="tdl-t-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tdl-testimonial-modal-title">
                <button type="button" class="tdl-t-modal-close" data-tdl-t-modal-close="1" aria-label="Close">x</button>
                <h2 class="tdl-t-modal-title" id="tdl-testimonial-modal-title">Add Testimonial</h2>
                <?php if ( isset( $_GET['tdl_saved'] ) ) : ?>
                    <div class="tdl-t-msg">Testimonial saved successfully.</div>
                <?php endif; ?>
                <?php echo $this->get_form_markup( true ); ?>
            </div>
        </div>
        <?php
    }

    public function render_carousel( $atts ) {
        $atts = shortcode_atts(
            array(
                'limit'    => 50,
                'autoplay' => 'false',
                'speed'    => 5000,
            ),
            $atts,
            'tdl_testimonial_carousel'
        );

        $posts = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => absint( $atts['limit'] ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        if ( empty( $posts ) ) {
            return '<p>No testimonials found.</p>';
        }

        $uid      = 'tdltc_' . wp_rand( 1000, 9999 );
        $autoplay = 'true' === strtolower( $atts['autoplay'] );
        $speed    = absint( $atts['speed'] );

        ob_start();
        ?>
        <div class="tdl-tc-wrap" id="<?php echo esc_attr( $uid ); ?>" data-tdl-tc-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>" data-tdl-tc-speed="<?php echo esc_attr( $speed ); ?>">
            <button class="tdl-tc-arrow prev" aria-label="Previous">
                <svg viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
            <div class="tdl-tc-track">
                <?php foreach ( $posts as $post ) : ?>
                    <?php echo $this->get_carousel_card_markup( $post ); ?>
                <?php endforeach; ?>
            </div>
            <button class="tdl-tc-arrow next" aria-label="Next">
                <svg viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
            <div class="tdl-tc-dots"></div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function stars_html( $rating ) {
        $rating = max( 0, min( 5, (int) $rating ) );
        $html   = '';

        for ( $i = 1; $i <= 5; $i++ ) {
            $html .= $i <= $rating ? '&#9733;' : '<span class="tdl-tc-star-off">&#9733;</span>';
        }

        return $html;
    }

    private function user_is_allowed() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user    = wp_get_current_user();
        $allowed = array( 'administrator', 'instructor', 'tutor_instructor', 'teacher', 'author' );

        return ! empty( array_intersect( (array) $user->roles, $allowed ) );
    }

    private function page_has_shortcode( $shortcode_tag ) {
        if ( ! is_singular() ) {
            return false;
        }

        $post = get_post();
        if ( ! $post instanceof WP_Post ) {
            return false;
        }

        return has_shortcode( $post->post_content, $shortcode_tag );
    }

    private function get_current_url() {
        global $wp;

        if ( isset( $wp->request ) ) {
            return home_url( add_query_arg( array(), $wp->request ) );
        }

        return home_url( '/' );
    }

    private function get_form_markup( $include_table ) {
        ob_start();
        ?>
        <form method="post" class="tdl-t-form" data-tdl-testimonial-form="1">
            <?php wp_nonce_field( self::NONCE_KEY, 'tdl_t_nonce' ); ?>

            <div class="tdl-t-field">
                <label for="tdl_t_type">Testimonial Type</label>
                <select name="tdl_t_type" id="tdl_t_type">
                    <option value="text">Text testimonial</option>
                    <option value="video">Video testimonial</option>
                </select>
            </div>

            <div class="tdl-t-field">
                <label for="tdl_t_name">Client Name</label>
                <input type="text" name="tdl_t_name" id="tdl_t_name" placeholder="e.g. Jonathan Marks" required>
            </div>

            <div class="tdl-t-field">
                <label for="tdl_t_company">Company / Organisation</label>
                <input type="text" name="tdl_t_company" id="tdl_t_company" placeholder="e.g. JM Tennis Academy">
            </div>

            <div class="tdl-t-field">
                <label for="tdl_t_website">Website (optional)</label>
                <input type="url" name="tdl_t_website" id="tdl_t_website" placeholder="https://www.example.com">
            </div>

            <div class="tdl-t-field">
                <label>Rating</label>
                <div class="tdl-stars">
                    <span class="tdl-star active" data-val="1">&#9733;</span>
                    <span class="tdl-star active" data-val="2">&#9733;</span>
                    <span class="tdl-star active" data-val="3">&#9733;</span>
                    <span class="tdl-star active" data-val="4">&#9733;</span>
                    <span class="tdl-star active" data-val="5">&#9733;</span>
                </div>
                <input type="hidden" name="tdl_t_rating" id="tdl_t_rating" value="5">
            </div>

            <div class="tdl-t-field">
                <label for="tdl_t_logo">Company Logo or Client Photo</label>
                <div class="tdl-t-row">
                    <input type="text" name="tdl_t_logo" id="tdl_t_logo" placeholder="Select or paste image URL">
                    <button type="button" class="tdl-t-btn secondary tdl-logo-btn">Choose Image</button>
                </div>
                <img id="tdl-logo-preview" src="" alt="Logo preview">
            </div>

            <div class="tdl-t-field tdl-t-field-text">
                <label for="tdl_t_quote">Testimonial Quote</label>
                <textarea name="tdl_t_quote" id="tdl_t_quote" placeholder="Paste the client's testimonial text here..."></textarea>
            </div>

            <div class="tdl-t-field tdl-t-field-video" style="display:none;">
                <label for="tdl_t_video">Video File URL</label>
                <div class="tdl-t-row">
                    <input type="text" name="tdl_t_video" id="tdl_t_video" placeholder="Select or paste video URL">
                    <button type="button" class="tdl-t-btn secondary tdl-video-btn">Choose Video</button>
                </div>
            </div>

            <div class="tdl-t-field tdl-t-field-video" style="display:none;">
                <label for="tdl_t_thumbnail">Video Thumbnail Image</label>
                <div class="tdl-t-row">
                    <input type="text" name="tdl_t_thumbnail" id="tdl_t_thumbnail" placeholder="Select or paste thumbnail URL">
                    <button type="button" class="tdl-t-btn secondary tdl-thumb-btn">Choose Thumbnail</button>
                </div>
                <img id="tdl-t-thumb-preview" src="" alt="Thumbnail preview">
            </div>

            <div class="tdl-t-field">
                <button type="submit" name="tdl_testimonial_save" class="tdl-t-btn">Save Testimonial</button>
            </div>
        </form>
        <?php if ( $include_table ) : ?>
            <?php echo $this->get_my_testimonials_markup(); ?>
        <?php endif; ?>
        <?php

        return ob_get_clean();
    }

    private function get_my_testimonials_markup() {
        $posts = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'author'         => get_current_user_id(),
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        if ( empty( $posts ) ) {
            return '';
        }

        ob_start();
        ?>
        <h3 class="tdl-t-list-title">My Testimonials</h3>
        <table class="tdl-t-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Company</th>
                    <th>Type</th>
                    <th>Rating</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $posts as $post ) : ?>
                    <?php
                    $type       = get_post_meta( $post->ID, '_tdl_t_type', true );
                    $company    = get_post_meta( $post->ID, '_tdl_t_company', true );
                    $rating     = (int) get_post_meta( $post->ID, '_tdl_t_rating', true );
                    $delete_url = wp_nonce_url(
                        add_query_arg( 'tdl_t_delete', $post->ID, $this->get_current_url() ),
                        'tdl_t_delete_' . $post->ID
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $post->post_title ); ?></td>
                        <td><?php echo esc_html( $company ); ?></td>
                        <td><span class="tdl-t-badge"><?php echo esc_html( $type ); ?></span></td>
                        <td class="tdl-t-rating"><?php echo wp_kses_post( $this->stars_html( $rating ) ); ?></td>
                        <td><a class="tdl-t-del" href="<?php echo esc_url( $delete_url ); ?>" data-tdl-t-delete="1">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return ob_get_clean();
    }

    private function get_carousel_card_markup( $post ) {
        $type      = get_post_meta( $post->ID, '_tdl_t_type', true );
        $quote     = get_post_meta( $post->ID, '_tdl_t_quote', true );
        $rating    = (int) get_post_meta( $post->ID, '_tdl_t_rating', true );
        $logo      = get_post_meta( $post->ID, '_tdl_t_logo', true );
        $company   = get_post_meta( $post->ID, '_tdl_t_company', true );
        $website   = get_post_meta( $post->ID, '_tdl_t_website', true );
        $video_url = get_post_meta( $post->ID, '_tdl_t_video', true );
        $thumbnail = get_post_meta( $post->ID, '_tdl_t_thumbnail', true );
        $delete_url = '';

        if ( $this->can_delete_testimonial( $post->ID ) ) {
            $delete_url = wp_nonce_url(
                add_query_arg( 'tdl_t_delete', $post->ID, $this->get_current_url() ),
                'tdl_t_delete_' . $post->ID
            );
        }

        ob_start();
        ?>
        <div class="tdl-tc-card">
            <?php if ( 'video' === $type && ! empty( $video_url ) ) : ?>
                <div class="tdl-tc-video-media">
                    <?php if ( ! empty( $thumbnail ) ) : ?>
                        <img class="tdl-tc-thumb" src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="tdl-tc-no-thumb">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"/>
                                <polygon points="10,8 16,12 10,16" fill="#555" stroke="none"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div class="tdl-tc-play-btn">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="12" fill="rgba(255,255,255,0.22)"/>
                            <polygon points="9.5,7 18,12 9.5,17" fill="white"/>
                        </svg>
                    </div>
                    <video controls controlsList="nodownload" preload="none">
                        <source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4">
                    </video>
                </div>
            <?php endif; ?>

            <div class="tdl-tc-body">
                <?php if ( ! empty( $quote ) ) : ?>
                    <p class="tdl-tc-quote"><?php echo esc_html( $quote ); ?></p>
                <?php endif; ?>
                <div class="tdl-tc-stars"><?php echo wp_kses_post( $this->stars_html( $rating ) ); ?></div>
                <div class="tdl-tc-footer">
                    <?php if ( ! empty( $logo ) ) : ?>
                        <img class="tdl-tc-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $company ); ?>">
                    <?php endif; ?>
                    <div class="tdl-tc-meta">
                        <div class="tdl-tc-name"><?php echo esc_html( $post->post_title ); ?></div>
                        <?php if ( ! empty( $company ) ) : ?>
                            <div class="tdl-tc-company">
                                <?php if ( ! empty( $website ) ) : ?>
                                    <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $company ); ?> - <?php echo esc_html( $website ); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html( $company ); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( ! empty( $delete_url ) ) : ?>
                    <a class="tdl-tc-delete" href="<?php echo esc_url( $delete_url ); ?>" data-tdl-t-delete="1">Delete</a>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function can_delete_testimonial( $post_id ) {
        return $this->user_is_allowed() && (
            (int) get_post_field( 'post_author', $post_id ) === (int) get_current_user_id() ||
            current_user_can( 'manage_options' )
        );
    }
}
