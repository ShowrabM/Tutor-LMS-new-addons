<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STM_Tutor_Demo_Manager {
    const POST_TYPE = 'tdl_demo';

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'handle_save' ) );
        add_action( 'init', array( $this, 'handle_delete' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 99 );
        add_action( 'wp_footer', array( $this, 'render_upload_modal' ) );

        add_shortcode( 'tutor_demo_upload_form', array( $this, 'render_form' ) );
        add_shortcode( 'tutor_demo_gallery', array( $this, 'render_gallery' ) );
    }

    public function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'          => 'Tutor Demos',
                    'singular_name' => 'Tutor Demo',
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

        $has_upload_shortcode  = $this->page_has_shortcode( 'tutor_demo_upload_form' );
        $has_gallery_shortcode = $this->page_has_shortcode( 'tutor_demo_gallery' );
        $allowed_user          = $this->user_is_allowed();

        if ( ! $has_gallery_shortcode && ! $has_upload_shortcode && ! $allowed_user ) {
            return;
        }

        wp_enqueue_style(
            'stm-tutor-demo',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-demo.css',
            array(),
            STM_TUTOR_CUSTOMIZATION_VERSION
        );

        wp_enqueue_script(
            'stm-tutor-demo',
            STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-demo.js',
            array( 'jquery' ),
            STM_TUTOR_CUSTOMIZATION_VERSION,
            true
        );

        wp_localize_script(
            'stm-tutor-demo',
            'stmTutorDemo',
            array(
                'isAllowed'     => $allowed_user,
                'saved'         => isset( $_GET['saved'] ),
                'confirmDelete' => 'Delete this demo?',
                'selectFile'    => 'Select File',
                'useFile'       => 'Use this file',
                'selectImage'   => 'Select Thumbnail',
                'useImage'      => 'Use this image',
            )
        );

        if ( $allowed_user ) {
            wp_enqueue_media();
        }
    }

    public function handle_save() {
        if ( ! isset( $_POST['tdl_save'] ) ) {
            return;
        }

        if ( ! $this->user_is_allowed() ) {
            return;
        }

        $nonce = isset( $_POST['tdl_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tdl_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tdl_save_demo' ) ) {
            return;
        }

        $type      = isset( $_POST['tdl_demo_type'] ) ? sanitize_key( wp_unslash( $_POST['tdl_demo_type'] ) ) : '';
        $title     = isset( $_POST['tdl_demo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tdl_demo_title'] ) ) : '';
        $desc      = isset( $_POST['tdl_demo_short_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tdl_demo_short_desc'] ) ) : '';
        $file      = isset( $_POST['tdl_demo_file'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_demo_file'] ) ) : '';
        $embed     = isset( $_POST['tdl_demo_embed'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_demo_embed'] ) ) : '';
        $thumbnail = isset( $_POST['tdl_demo_thumbnail'] ) ? esc_url_raw( wp_unslash( $_POST['tdl_demo_thumbnail'] ) ) : '';

        $allowed_types = array( 'video', 'pdf', 'embed' );
        if ( empty( $title ) || ! in_array( $type, $allowed_types, true ) ) {
            return;
        }

        if ( in_array( $type, array( 'video', 'pdf' ), true ) && empty( $file ) ) {
            return;
        }

        if ( 'embed' === $type && empty( $embed ) ) {
            return;
        }

        $desc = $this->truncate_description( $desc, 220 );

        $post_id = wp_insert_post(
            array(
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
            ),
            true
        );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return;
        }

        update_post_meta( $post_id, '_tdl_demo_type', $type );
        update_post_meta( $post_id, '_tdl_demo_short_desc', $desc );
        update_post_meta( $post_id, '_tdl_demo_file', $file );
        update_post_meta( $post_id, '_tdl_demo_embed', $embed );
        update_post_meta( $post_id, '_tdl_demo_thumbnail', $thumbnail );

        wp_safe_redirect( add_query_arg( 'saved', '1', $this->get_current_url() ) );
        exit;
    }

    public function handle_delete() {
        if ( ! isset( $_GET['tdl_delete'] ) ) {
            return;
        }

        if ( ! $this->user_is_allowed() ) {
            return;
        }

        $post_id = absint( wp_unslash( $_GET['tdl_delete'] ) );
        if ( ! $post_id ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tdl_delete_demo_' . $post_id ) ) {
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
        wp_safe_redirect( remove_query_arg( array( 'tdl_delete', '_wpnonce' ), $this->get_current_url() ) );
        exit;
    }

    public function render_form() {
        if ( ! $this->user_is_allowed() ) {
            return '<p>You do not have permission to upload demos.</p>';
        }

        ob_start();
        ?>
        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="tdl-msg">Demo saved successfully.</div>
        <?php endif; ?>

        <?php echo $this->get_upload_form_markup(); ?>
        <?php

        return ob_get_clean();
    }

    public function render_upload_modal() {
        if ( ! $this->user_is_allowed() || $this->page_has_shortcode( 'tutor_demo_upload_form' ) ) {
            return;
        }
        ?>
        <div class="tdl-modal" id="tdl-upload-modal" aria-hidden="true">
            <div class="tdl-modal-backdrop" data-tdl-modal-close="1"></div>
            <div class="tdl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tdl-upload-modal-title">
                <button type="button" class="tdl-modal-close" data-tdl-modal-close="1" aria-label="Close">x</button>
                <h2 class="tdl-modal-title" id="tdl-upload-modal-title">Upload Demo</h2>
                <?php if ( isset( $_GET['saved'] ) ) : ?>
                    <div class="tdl-msg">Demo saved successfully.</div>
                <?php endif; ?>
                <?php echo $this->get_upload_form_markup(); ?>
            </div>
        </div>
        <?php
    }

    public function render_gallery( $atts ) {
        $atts = shortcode_atts(
            array(
                'posts_per_page' => 9,
            ),
            $atts,
            'tutor_demo_gallery'
        );

        $paged = max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' ) );
        $query = new WP_Query(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => max( 1, absint( $atts['posts_per_page'] ) ),
                'paged'          => $paged,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        ob_start();

        if ( ! $query->have_posts() ) {
            echo '<div class="tdl-no-items">No demo items found.</div>';
            return ob_get_clean();
        }

        echo '<div class="tdl-gallery-grid">';

        while ( $query->have_posts() ) {
            $query->the_post();
            $this->render_gallery_card();
        }

        echo '</div>';

        $pagination = paginate_links(
            array(
                'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'format'    => '?paged=%#%',
                'current'   => $paged,
                'total'     => $query->max_num_pages,
                'type'      => 'array',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            )
        );

        if ( ! empty( $pagination ) ) {
            echo '<div class="tdl-pagination">';
            foreach ( $pagination as $link ) {
                echo wp_kses_post( $link );
            }
            echo '</div>';
        }

        wp_reset_postdata();

        return ob_get_clean();
    }

    private function render_gallery_card() {
        $post_id   = get_the_ID();
        $type      = get_post_meta( $post_id, '_tdl_demo_type', true );
        $desc      = get_post_meta( $post_id, '_tdl_demo_short_desc', true );
        $file      = get_post_meta( $post_id, '_tdl_demo_file', true );
        $embed     = $this->normalize_embed_url( get_post_meta( $post_id, '_tdl_demo_embed', true ) );
        $thumbnail = get_post_meta( $post_id, '_tdl_demo_thumbnail', true );

        echo '<div class="tdl-card-item">';
        echo '<div class="tdl-card-media">';

        if ( 'pdf' === $type ) {
            $this->render_pdf_media( $thumbnail, $file );
        } elseif ( 'video' === $type && ! empty( $file ) ) {
            $this->render_video_media( $thumbnail, $file );
        } elseif ( 'embed' === $type && ! empty( $embed ) ) {
            $this->render_embed_media( $thumbnail, $embed );
        } else {
            echo '<div class="tdl-placeholder">' . $this->get_video_icon() . '<span>No preview</span></div>';
        }

        echo '</div>';

        if ( 'pdf' === $type && ! empty( $file ) ) {
            echo '<div class="tdl-card-actions">';
            echo '<a class="tdl-pdf-open" href="' . esc_url( $file ) . '" target="_blank" rel="noopener">Open PDF</a>';
            echo '</div>';
        }

        echo '<div class="tdl-card-body">';
        echo '<span class="tdl-type-badge">' . esc_html( $type ) . '</span>';
        echo '<h3 class="tdl-card-title">' . esc_html( get_the_title() ) . '</h3>';
        echo '<p class="tdl-card-desc">' . esc_html( $desc ) . '</p>';

        if ( $this->can_delete_demo( $post_id ) ) {
            $delete_url = wp_nonce_url(
                add_query_arg( 'tdl_delete', $post_id, $this->get_current_url() ),
                'tdl_delete_demo_' . $post_id
            );

            echo '<a class="tdl-delete-btn" href="' . esc_url( $delete_url ) . '" data-tdl-delete="1">Delete</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    private function get_upload_form_markup() {
        ob_start();
        ?>
        <form method="post" class="tdl-form" data-tdl-upload-form="1">
            <?php wp_nonce_field( 'tdl_save_demo', 'tdl_nonce' ); ?>

            <div class="tdl-field">
                <label for="tdl_demo_type">Demo Type</label>
                <select name="tdl_demo_type" id="tdl_demo_type">
                    <option value="video">Video</option>
                    <option value="pdf">PDF</option>
                    <option value="embed">Embedded Link (YouTube / Vimeo)</option>
                </select>
            </div>

            <div class="tdl-field">
                <label for="tdl_demo_title">Title</label>
                <input type="text" name="tdl_demo_title" id="tdl_demo_title" placeholder="Demo title" required>
            </div>

            <div class="tdl-field">
                <label for="tdl_demo_short_desc">Short Description</label>
                <textarea name="tdl_demo_short_desc" id="tdl_demo_short_desc" maxlength="220" placeholder="Write a short description (max 220 chars)"></textarea>
            </div>

            <div class="tdl-field">
                <label for="tdl_demo_thumbnail">Thumbnail / Cover Image</label>
                <div class="tdl-file-row">
                    <input type="text" name="tdl_demo_thumbnail" id="tdl_demo_thumbnail" placeholder="Select or paste image URL">
                    <button type="button" class="tdl-btn tdl-btn-secondary tdl-thumb-btn">Choose Image</button>
                </div>
                <img id="tdl-thumb-preview" src="" alt="Thumbnail preview">
            </div>

            <div class="tdl-field tdl-field-file">
                <label for="tdl_demo_file">File URL</label>
                <div class="tdl-file-row">
                    <input type="text" name="tdl_demo_file" id="tdl_demo_file" placeholder="Select or paste file URL">
                    <button type="button" class="tdl-btn tdl-btn-secondary tdl-upload-btn">Choose File</button>
                </div>
            </div>

            <div class="tdl-field tdl-field-embed" style="display:none;">
                <label for="tdl_demo_embed">Embed URL</label>
                <input type="url" name="tdl_demo_embed" id="tdl_demo_embed" placeholder="https://www.youtube.com/watch?v=...">
            </div>

            <div class="tdl-field">
                <button type="submit" name="tdl_save" class="tdl-btn">Save Demo</button>
            </div>
        </form>
        <?php echo $this->get_my_demos_markup(); ?>
        <?php

        return ob_get_clean();
    }

    private function get_my_demos_markup() {
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
        <h3 class="tdl-list-title">My Demos</h3>
        <table class="tdl-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $posts as $post ) : ?>
                    <?php
                    $type       = get_post_meta( $post->ID, '_tdl_demo_type', true );
                    $delete_url = wp_nonce_url(
                        add_query_arg( 'tdl_delete', $post->ID, $this->get_current_url() ),
                        'tdl_delete_demo_' . $post->ID
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $post->post_title ); ?></td>
                        <td><span class="tdl-type-badge"><?php echo esc_html( $type ); ?></span></td>
                        <td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
                        <td><a class="tdl-table-delete" href="<?php echo esc_url( $delete_url ); ?>" data-tdl-delete="1">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return ob_get_clean();
    }

    private function render_pdf_media( $thumbnail, $file ) {
        if ( ! empty( $thumbnail ) ) {
            echo '<img class="tdl-thumb-img" src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy">';
            if ( ! empty( $file ) ) {
                echo '<a class="tdl-play-overlay" href="' . esc_url( $file ) . '" target="_blank" rel="noopener">' . $this->get_play_icon() . '</a>';
            }
            return;
        }

        echo '<div class="tdl-placeholder">' . $this->get_pdf_icon() . '<span>PDF</span></div>';
    }

    private function render_video_media( $thumbnail, $file ) {
        if ( ! empty( $thumbnail ) ) {
            echo '<img class="tdl-thumb-img" src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy">';
        } else {
            echo '<div class="tdl-placeholder">' . $this->get_video_icon() . '<span>Video</span></div>';
        }

        echo '<div class="tdl-play-overlay">' . $this->get_play_icon() . '</div>';
        echo '<video controls controlsList="nodownload" preload="metadata">';
        echo '<source src="' . esc_url( $file ) . '" type="video/mp4">';
        echo '</video>';
    }

    private function render_embed_media( $thumbnail, $embed ) {
        if ( ! empty( $thumbnail ) ) {
            echo '<img class="tdl-thumb-img" src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy">';
        } else {
            $youtube_thumbnail = $this->get_youtube_thumbnail( $embed );
            if ( $youtube_thumbnail ) {
                echo '<img class="tdl-thumb-img" src="' . esc_url( $youtube_thumbnail ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy">';
            } else {
                echo '<div class="tdl-placeholder">' . $this->get_embed_icon() . '<span>Video</span></div>';
            }
        }

        echo '<div class="tdl-play-overlay">' . $this->get_play_icon() . '</div>';
        echo '<iframe src="' . esc_url( $embed ) . '" loading="lazy" allowfullscreen allow="autoplay"></iframe>';
    }

    private function can_delete_demo( $post_id ) {
        return $this->user_is_allowed() && (
            (int) get_post_field( 'post_author', $post_id ) === (int) get_current_user_id() ||
            current_user_can( 'manage_options' )
        );
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

    private function truncate_description( $text, $length ) {
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $text, 0, $length );
        }

        return substr( $text, 0, $length );
    }

    private function normalize_embed_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return '';
        }

        if ( preg_match( '#youtube\.com/watch\?v=([^&]+)#', $url, $matches ) ) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if ( preg_match( '#youtu\.be/([^?&/]+)#', $url, $matches ) ) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if ( preg_match( '#vimeo\.com/(\d+)#', $url, $matches ) ) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        return $url;
    }

    private function get_youtube_thumbnail( $embed_url ) {
        if ( preg_match( '#youtube\.com/embed/([^?&/]+)#', $embed_url, $matches ) ) {
            return 'https://img.youtube.com/vi/' . $matches[1] . '/hqdefault.jpg';
        }

        return '';
    }

    private function get_play_icon() {
        return '<svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="12" fill="rgba(255,255,255,0.25)"/><polygon points="9.5,7 18,12 9.5,17" fill="white"/></svg>';
    }

    private function get_video_icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="5" width="15" height="14" rx="2"/><polygon points="17,9 22,6 22,18 17,15"/></svg>';
    }

    private function get_pdf_icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>';
    }

    private function get_embed_icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><polygon points="10,8 16,12 10,16"/></svg>';
    }
}
