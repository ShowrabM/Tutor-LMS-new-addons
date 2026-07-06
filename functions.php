<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'tutor_dashboard_my_courses_filter', 'stm_render_dashboard_course_search' );
add_action( 'wp_enqueue_scripts', 'stm_enqueue_dashboard_course_search_assets', 101 );
add_action( 'wp_ajax_stm_instructor_course_suggestions', 'stm_instructor_course_suggestions' );
add_filter( 'tutor_get_template_path', 'stm_override_tutor_dashboard_my_courses_template', 20, 2 );

function stm_is_tutor_dashboard_page() {
    if ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'is_tutor_frontend_dashboard' ) && tutor_utils()->is_tutor_frontend_dashboard() ) {
        return true;
    }

    if ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'is_tutor_dashboard' ) && tutor_utils()->is_tutor_dashboard() ) {
        return true;
    }

    return has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'tutor_dashboard' );
}

/**
 * Whether the current user is an approved Tutor LMS instructor.
 */
function stm_is_current_user_instructor() {
    if ( ! is_user_logged_in() || ! function_exists( 'tutor_utils' ) ) {
        return false;
    }

    $can_create_courses = function_exists( 'tutor' ) && current_user_can( tutor()->instructor_role );

    return $can_create_courses || current_user_can( 'manage_options' ) || (bool) tutor_utils()->is_instructor( get_current_user_id(), true );
}

function stm_enqueue_dashboard_course_search_assets() {
    if ( is_admin() || ! stm_is_tutor_dashboard_page() || ! stm_is_current_user_instructor() ) {
        return;
    }

    wp_enqueue_style(
        'stm-tutor-dashboard-search',
        STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-tutor-dashboard-search.css',
        array(),
        STM_TUTOR_CUSTOMIZATION_VERSION
    );

    wp_enqueue_script(
        'stm-tutor-dashboard-search',
        STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-tutor-dashboard-search.js',
        array( 'jquery' ),
        STM_TUTOR_CUSTOMIZATION_VERSION,
        true
    );

    wp_localize_script(
        'stm-tutor-dashboard-search',
        'stmTutorDashboardSearch',
        array(
            'showHeaderSearch' => true,
            'myCoursesUrl'    => tutor_utils()->get_tutor_dashboard_page_permalink( 'my-courses' ),
            'searchParam'     => 'stm_course_search',
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'stm_instructor_course_search' ),
        )
    );
}

/**
 * Return live course-title suggestions owned or co-authored by this instructor.
 */
function stm_instructor_course_suggestions() {
    check_ajax_referer( 'stm_instructor_course_search', 'nonce' );

    if ( ! stm_is_current_user_instructor() || ! class_exists( '\\Tutor\\Models\\CourseModel' ) ) {
        wp_send_json_error( array( 'message' => 'You are not allowed to search instructor courses.' ), 403 );
    }

    $query = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';
    if ( strlen( $query ) < 2 ) {
        wp_send_json_success( array() );
    }

    if ( current_user_can( 'manage_options' ) ) {
        $courses = get_posts(
            array(
                'post_type'      => tutor()->course_post_type,
                'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
                'posts_per_page' => 8,
                's'              => $query,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
    } else {
        $courses = \Tutor\Models\CourseModel::get_courses_by_instructor(
            get_current_user_id(),
            array( 'publish', 'pending', 'draft', 'future' ),
            0,
            PHP_INT_MAX,
            false,
            array( tutor()->course_post_type )
        );
    }
    $suggestions = array();

    foreach ( (array) $courses as $course ) {
        if ( ! current_user_can( 'manage_options' ) && false === stripos( $course->post_title, $query ) ) {
            continue;
        }

        $suggestions[] = array(
            'title'  => $course->post_title,
            'status' => ucfirst( $course->post_status ),
            'url'    => tutor_utils()->course_edit_link( $course->ID, tutor()->has_pro ? 'frontend' : 'backend' ),
        );

        if ( 8 <= count( $suggestions ) ) {
            break;
        }
    }

    wp_send_json_success( $suggestions );
}

function stm_render_dashboard_course_search() {
    if ( ! stm_is_tutor_dashboard_page() || ! stm_is_current_user_instructor() ) {
        return;
    }
    $search_value = isset( $_GET['stm_course_search'] )
        ? sanitize_text_field( wp_unslash( $_GET['stm_course_search'] ) )
        : '';
    ?>
    <div class="stm-tutor-course-search-wrap">
        <label class="stm-tutor-course-search-label" for="stm-tutor-course-search">Search courses</label>
        <input type="search" id="stm-tutor-course-search" class="stm-tutor-course-search" placeholder="Search courses" value="<?php echo esc_attr( $search_value ); ?>" autocomplete="off" />
    </div>
    <?php
}

/**
 * Check whether an active Paid Memberships Pro level covers a course.
 * Supports Tutor's full-site model and PMPro category selections.
 */
function stm_user_has_pmpro_course_access( $course_id, $user_id = 0 ) {
    if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) || ! function_exists( 'pmpro_getMembershipCategories' ) ) {
        return false;
    }

    $user_id   = $user_id ? absint( $user_id ) : get_current_user_id();
    $course_id = absint( $course_id );
    $levels    = pmpro_getMembershipLevelsForUser( $user_id );

    if ( ! $user_id || ! is_array( $levels ) || empty( $levels ) ) {
        return false;
    }

    $course_category_ids = wp_get_post_terms( $course_id, 'course-category', array( 'fields' => 'ids' ) );
    if ( is_wp_error( $course_category_ids ) ) {
        $course_category_ids = array();
    }

    // A parent category selected in PMPro should also cover courses in its children.
    foreach ( $course_category_ids as $category_id ) {
        $course_category_ids = array_merge(
            $course_category_ids,
            get_ancestors( $category_id, 'course-category', 'taxonomy' )
        );
    }
    $course_category_ids = array_unique( array_map( 'absint', $course_category_ids ) );

    foreach ( $levels as $level ) {
        $level_id = isset( $level->id ) ? absint( $level->id ) : ( isset( $level->ID ) ? absint( $level->ID ) : 0 );
        if ( ! $level_id ) {
            continue;
        }

        $membership_model = function_exists( 'get_pmpro_membership_level_meta' )
            ? get_pmpro_membership_level_meta( $level_id, 'tutor_pmpro_membership_model', true )
            : '';

        if ( 'full_website_membership' === $membership_model ) {
            return true;
        }

        $membership_category_ids = array_map( 'absint', (array) pmpro_getMembershipCategories( $level_id ) );
        if ( array_intersect( $course_category_ids, $membership_category_ids ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Treat active membership access as enrolled for the custom course archive.
 * Tutor creates the course enrollment when the member opens/enrolls in a course.
 */
function stm_user_has_course_access( $course_id, $user_id = 0 ) {
    if ( ! is_user_logged_in() || ! function_exists( 'tutor_utils' ) ) {
        return false;
    }

    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    $course_id = absint( $course_id );

    if ( tutor_utils()->is_enrolled( $course_id, $user_id ) ) {
        return true;
    }

    if ( stm_user_has_pmpro_course_access( $course_id, $user_id ) ) {
        return true;
    }

    // Tutor LMS Pro's native subscription/membership system.
    if ( class_exists( '\\TutorPro\\Subscription\\Models\\SubscriptionModel' ) ) {
        try {
            $subscription_model = new \TutorPro\Subscription\Models\SubscriptionModel();
            if ( $subscription_model->has_course_access( $course_id, $user_id ) ) {
                return true;
            }
        } catch ( Throwable $exception ) {
            // Keep the archive available if the subscription add-on is disabled/misconfigured.
        }
    }

    // Final compatibility fallback for older versions of Tutor's PMPro integration.
    if ( 'pmpro' === tutor_utils()->get_option( 'monetize_by' ) ) {
        $previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
        $GLOBALS['post'] = get_post( $course_id );
        $access_label = apply_filters( 'tutor-loop-default-price', '' );
        $GLOBALS['post'] = $previous_post;

        if ( '' !== trim( wp_strip_all_tags( (string) $access_label ) ) ) {
            return true;
        }
    }

    return false;
}

function stm_override_tutor_dashboard_my_courses_template( $template_location, $template ) {
    if ( 'dashboard/my-courses' !== $template ) {
        return $template_location;
    }

    $override = STM_TUTOR_CUSTOMIZATION_DIR . 'templates/dashboard/my-courses.php';

    if ( file_exists( $override ) ) {
        return $override;
    }

    return $template_location;
}

add_filter( 'theme_page_templates', 'stm_register_course_archive_template' );
function stm_register_course_archive_template( $templates ) {
    $templates['stm-course-archive.php'] = 'Tutor LMS Customization';

    return $templates;
}

add_filter( 'template_include', 'stm_load_course_archive_template' );
function stm_load_course_archive_template( $template ) {
    if ( ! is_singular( 'page' ) ) {
        return $template;
    }

    $page_id = get_queried_object_id();
    if ( ! $page_id ) {
        return $template;
    }

    if ( 'stm-course-archive.php' !== get_page_template_slug( $page_id ) ) {
        return $template;
    }

    return STM_TUTOR_CUSTOMIZATION_DIR . 'stm-course-archive.php';
}

add_shortcode( 'stm_tutor_courses', 'stm_course_archive_shortcode' );
function stm_course_archive_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'posts_per_page'     => -1,
            'title'              => 'All courses',
            'include_categories' => '',
            'exclude_categories' => '',
            'show_all_tab'       => 'true',
            'show_demo_panel'    => 'true',
            'demo_limit'         => 8,
            'demo_title'         => 'Watch Library Demo',
            'demo_button_label'  => 'Upload Demo',
        ),
        $atts,
        'stm_tutor_courses'
    );

    stm_enqueue_course_assets();

    return stm_get_course_archive_markup( $atts );
}

add_action( 'wp_enqueue_scripts', 'stm_enqueue_course_assets' );
function stm_enqueue_course_assets() {
    static $assets_enqueued = false;

    if ( $assets_enqueued || ! stm_should_load_course_assets() ) {
        return;
    }

    wp_enqueue_style(
        'stm-course-archive',
        STM_TUTOR_CUSTOMIZATION_URL . 'asset/css/stm-course-archive.css',
        array(),
        STM_TUTOR_CUSTOMIZATION_VERSION
    );

    wp_enqueue_script(
        'stm-course-filter',
        STM_TUTOR_CUSTOMIZATION_URL . 'asset/js/stm-filter.js',
        array( 'jquery' ),
        STM_TUTOR_CUSTOMIZATION_VERSION,
        true
    );

    wp_localize_script(
        'stm-course-filter',
        'stmAjax',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'stm_course_filter' ),
        )
    );

    $assets_enqueued = true;
}

add_filter( 'tutor_dashboard/bottom_nav_items', 'stm_add_fluentaffiliate_dashboard_nav_item' );
function stm_add_fluentaffiliate_dashboard_nav_item( $nav_items ) {
    if ( ! defined( 'FLUENT_AFFILIATE_VERSION' ) ) {
        return $nav_items;
    }

    $affiliate_nav_item = array(
        'title'    => 'Affiliate approval',
        'icon'     => 'tutor-icon-user-bold',
        'url'      => admin_url( 'admin.php?page=fluent-affiliate#/affiliates' ),
        'auth_cap' => 'manage_options',
    );

    $updated_nav_items = array();
    $item_added        = false;

    foreach ( $nav_items as $key => $nav_item ) {
        if ( ! $item_added && 'separator-2' === $key ) {
            $updated_nav_items['stm-fluentaffiliate'] = $affiliate_nav_item;
            $item_added                               = true;
        }

        $updated_nav_items[ $key ] = $nav_item;
    }

    if ( ! $item_added ) {
        $updated_nav_items['stm-fluentaffiliate'] = $affiliate_nav_item;
    }

    return $updated_nav_items;
}

function stm_should_load_course_assets() {
    if ( is_admin() ) {
        return false;
    }

    if ( is_page_template( 'stm-course-archive.php' ) ) {
        return true;
    }

    if ( ! is_singular() ) {
        return false;
    }

    $post = get_post();
    if ( ! $post instanceof WP_Post ) {
        return false;
    }

    return has_shortcode( $post->post_content, 'stm_tutor_courses' );
}

function stm_normalize_posts_per_page( $posts_per_page ) {
    $posts_per_page = intval( $posts_per_page );

    if ( 0 === $posts_per_page || $posts_per_page < -1 ) {
        return -1;
    }

    return $posts_per_page;
}

function stm_parse_term_ids( $value ) {
    if ( is_array( $value ) ) {
        $raw_ids = $value;
    } else {
        $raw_ids = explode( ',', (string) $value );
    }

    $term_ids = array_map( 'absint', $raw_ids );
    $term_ids = array_filter( $term_ids );

    return array_values( array_unique( $term_ids ) );
}

function stm_to_bool( $value ) {
    if ( is_bool( $value ) ) {
        return $value;
    }

    return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
}

function stm_get_archive_context( $args = array() ) {
    $defaults = array(
        'posts_per_page'     => -1,
        'title'              => 'All courses',
        'include_categories' => array(),
        'exclude_categories' => array(),
        'search'             => '',
        'show_all_tab'       => true,
        'show_demo_panel'    => true,
        'demo_limit'         => 8,
        'demo_title'         => 'Watch Library Demo',
        'demo_button_label'  => 'Upload Demo',
    );

    $args = wp_parse_args( $args, $defaults );

    return array(
        'posts_per_page'     => stm_normalize_posts_per_page( $args['posts_per_page'] ),
        'title'              => sanitize_text_field( $args['title'] ),
        'include_categories' => stm_parse_term_ids( $args['include_categories'] ),
        'exclude_categories' => stm_parse_term_ids( $args['exclude_categories'] ),
        'search'             => sanitize_text_field( $args['search'] ),
        'show_all_tab'       => stm_to_bool( $args['show_all_tab'] ),
        'show_demo_panel'    => stm_to_bool( $args['show_demo_panel'] ),
        'demo_limit'         => max( 1, absint( $args['demo_limit'] ) ),
        'demo_title'         => sanitize_text_field( $args['demo_title'] ),
        'demo_button_label'  => sanitize_text_field( $args['demo_button_label'] ),
    );
}

function stm_user_can_manage_demos() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user    = wp_get_current_user();
    $allowed = array( 'administrator', 'instructor', 'tutor_instructor', 'teacher', 'author' );

    return ! empty( array_intersect( (array) $user->roles, $allowed ) );
}

function stm_get_demo_posts( $limit = 8 ) {
    return get_posts(
        array(
            'post_type'      => 'tdl_demo',
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, absint( $limit ) ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        )
    );
}

function stm_get_demo_item_url( $post_id ) {
    $type = get_post_meta( $post_id, '_tdl_demo_type', true );

    if ( 'embed' === $type ) {
        return get_post_meta( $post_id, '_tdl_demo_embed', true );
    }

    return get_post_meta( $post_id, '_tdl_demo_file', true );
}

function stm_get_demo_panel_markup( $args = array() ) {
    $context     = stm_get_archive_context( $args );
    $demo_posts  = stm_get_demo_posts( $context['demo_limit'] );
    $can_upload  = stm_user_can_manage_demos();
    $panel_title = '' !== $context['demo_title'] ? $context['demo_title'] : 'Watch Library Demo';
    $button_text = '' !== $context['demo_button_label'] ? $context['demo_button_label'] : 'Upload Demo';

    ob_start();
    ?>
    <aside class="stm-demo-sidebar">
      <div class="stm-demo-panel">
        <div class="stm-demo-panel-header">
          <h3 class="stm-demo-panel-title"><?php echo esc_html( $panel_title ); ?></h3>
          <?php if ( $can_upload ) : ?>
            <a href="#" class="tdl-dashboard-upload-btn stm-demo-sidebar-button"><?php echo esc_html( $button_text ); ?></a>
          <?php endif; ?>
        </div>
        <?php if ( ! empty( $demo_posts ) ) : ?>
          <ol class="stm-demo-list">
            <?php foreach ( $demo_posts as $demo_post ) : ?>
              <?php $demo_url = stm_get_demo_item_url( $demo_post->ID ); ?>
              <li class="stm-demo-item">
                <?php if ( ! empty( $demo_url ) ) : ?>
                  <a href="<?php echo esc_url( $demo_url ); ?>" class="stm-demo-link" target="_blank" rel="noopener">
                    <?php echo esc_html( get_the_title( $demo_post ) ); ?>
                  </a>
                <?php else : ?>
                  <span class="stm-demo-link is-static"><?php echo esc_html( get_the_title( $demo_post ) ); ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else : ?>
          <p class="stm-demo-empty">No demos added yet.</p>
        <?php endif; ?>
      </div>
    </aside>
    <?php

    return ob_get_clean();
}

function stm_get_current_month_date_query() {
    $month_start = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp', true ) );

    return array(
        array(
            'column'    => 'post_date_gmt',
            'after'     => $month_start,
            'inclusive' => true,
        ),
    );
}

function stm_is_course_new( $post_id ) {
    $post_date_gmt = get_post_field( 'post_date_gmt', $post_id );

    if ( empty( $post_date_gmt ) ) {
        return false;
    }

    $month_start_timestamp = strtotime( gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp', true ) ) );
    $post_timestamp        = strtotime( $post_date_gmt . ' UTC' );

    return false !== $post_timestamp && $post_timestamp >= $month_start_timestamp;
}

function stm_get_new_course_counts_by_category( $args = array() ) {
    $categories = stm_get_filtered_categories( $args );
    $query_args = stm_get_course_query_args( 0, $args );
    $query_args['posts_per_page'] = -1;
    $query_args['fields']         = 'ids';
    $query_args['no_found_rows']  = true;
    $query_args['date_query']     = stm_get_current_month_date_query();

    $course_ids = get_posts( $query_args );
    $counts     = array(
        0 => count( $course_ids ),
    );

    if ( ! is_wp_error( $categories ) ) {
        foreach ( $categories as $category ) {
            $counts[ $category->term_id ] = 0;
        }
    }

    foreach ( $course_ids as $course_id ) {
        $terms = get_the_terms( $course_id, 'course-category' );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        $counted_terms = array();

        foreach ( $terms as $term ) {
            $term_ids = array_merge(
                array( $term->term_id ),
                get_ancestors( $term->term_id, 'course-category', 'taxonomy' )
            );

            foreach ( $term_ids as $term_id ) {
                $term_id = absint( $term_id );

                if ( isset( $counted_terms[ $term_id ] ) ) {
                    continue;
                }

                if ( ! isset( $counts[ $term_id ] ) ) {
                    $counts[ $term_id ] = 0;
                }

                $counts[ $term_id ]++;
                $counted_terms[ $term_id ] = true;
            }
        }
    }

    return $counts;
}

function stm_get_course_counts_by_category( $args = array() ) {
    $categories = stm_get_filtered_categories( $args );
    $counts     = array( 0 => 0 );

    if ( is_wp_error( $categories ) ) {
        return $counts;
    }

    foreach ( $categories as $category ) {
        $counts[ $category->term_id ] = 0;
    }

    $query_args = stm_get_course_query_args( 0, $args );
    $query_args['posts_per_page'] = -1;
    $query_args['fields']         = 'ids';
    $query_args['no_found_rows']  = true;

    $course_ids = get_posts( $query_args );
    $counts[0]  = count( $course_ids );

    foreach ( $course_ids as $course_id ) {
        $terms = get_the_terms( $course_id, 'course-category' );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        $counted_terms = array();

        foreach ( $terms as $term ) {
            $term_ids = array_merge(
                array( $term->term_id ),
                get_ancestors( $term->term_id, 'course-category', 'taxonomy' )
            );

            foreach ( $term_ids as $term_id ) {
                $term_id = absint( $term_id );

                if ( ! isset( $counts[ $term_id ] ) || isset( $counted_terms[ $term_id ] ) ) {
                    continue;
                }

                $counts[ $term_id ]++;
                $counted_terms[ $term_id ] = true;
            }
        }
    }

    return $counts;
}

function stm_build_category_tax_query( $selected_cat_id, $include_categories, $exclude_categories ) {
    $selected_cat_id    = absint( $selected_cat_id );
    $include_categories = stm_parse_term_ids( $include_categories );
    $exclude_categories = stm_parse_term_ids( $exclude_categories );
    $tax_query          = array();

    if ( $selected_cat_id > 0 ) {
        if ( ! empty( $include_categories ) && ! in_array( $selected_cat_id, $include_categories, true ) ) {
            return array(
                array(
                    'taxonomy' => 'course-category',
                    'field'    => 'term_id',
                    'terms'    => array( 0 ),
                ),
            );
        }

        if ( in_array( $selected_cat_id, $exclude_categories, true ) ) {
            return array(
                array(
                    'taxonomy' => 'course-category',
                    'field'    => 'term_id',
                    'terms'    => array( 0 ),
                ),
            );
        }

        $tax_query[] = array(
            'taxonomy'         => 'course-category',
            'field'            => 'term_id',
            'terms'            => $selected_cat_id,
            'include_children' => true,
        );
    } elseif ( ! empty( $include_categories ) ) {
        $tax_query[] = array(
            'taxonomy'         => 'course-category',
            'field'            => 'term_id',
            'terms'            => $include_categories,
            'include_children' => true,
        );
    }

    if ( ! empty( $exclude_categories ) ) {
        $tax_query[] = array(
            'taxonomy'         => 'course-category',
            'field'            => 'term_id',
            'terms'            => $exclude_categories,
            'operator'         => 'NOT IN',
            'include_children' => true,
        );
    }

    if ( count( $tax_query ) > 1 ) {
        $tax_query['relation'] = 'AND';
    }

    return $tax_query;
}

function stm_get_course_query_args( $selected_cat_id = 0, $args = array() ) {
    $context  = stm_get_archive_context( $args );
    $stm_args = array(
        'post_type'      => 'courses',
        'posts_per_page' => $context['posts_per_page'],
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( ! empty( $context['search'] ) ) {
        $stm_args['s'] = $context['search'];
    }

    $tax_query = stm_build_category_tax_query(
        $selected_cat_id,
        $context['include_categories'],
        $context['exclude_categories']
    );

    if ( ! empty( $tax_query ) ) {
        $stm_args['tax_query'] = $tax_query;
    }

    return $stm_args;
}

function stm_get_courses_grid_markup( $selected_cat_id = 0, $args = array() ) {
    global $stm_course_archive_context;

    $stm_course_archive_context = stm_get_archive_context( $args );
    $stm_query = new WP_Query( stm_get_course_query_args( $selected_cat_id, $args ) );

    ob_start();

    if ( $stm_query->have_posts() ) {
        while ( $stm_query->have_posts() ) {
            $stm_query->the_post();
            stm_render_course_card();
        }
    } else {
        echo '<p class="stm-no-courses">No courses found.</p>';
    }

    wp_reset_postdata();

    return array(
        'html'  => ob_get_clean(),
        'count' => intval( $stm_query->found_posts ),
    );
}

function stm_get_filtered_categories( $args = array() ) {
    $context      = stm_get_archive_context( $args );
    $term_args    = array(
        'taxonomy'   => 'course-category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    );

    if ( ! empty( $context['include_categories'] ) ) {
        $term_args['include'] = $context['include_categories'];
    }

    if ( ! empty( $context['exclude_categories'] ) ) {
        $term_args['exclude'] = $context['exclude_categories'];
    }

    return get_terms( $term_args );
}

function stm_get_new_courses_count( $args = array() ) {
    $counts = stm_get_new_course_counts_by_category( $args );

    return isset( $counts[0] ) ? (int) $counts[0] : 0;
}

function stm_get_selected_new_course_count( $selected_cat_id, $args = array() ) {
    $counts = stm_get_new_course_counts_by_category( $args );

    if ( 0 === absint( $selected_cat_id ) ) {
        return isset( $counts[0] ) ? (int) $counts[0] : 0;
    }

    $term_id = absint( $selected_cat_id );

    return isset( $counts[ $term_id ] ) ? (int) $counts[ $term_id ] : 0;
}

function stm_get_course_archive_markup( $args = array() ) {
    $context            = stm_get_archive_context( $args );
    $categories         = stm_get_filtered_categories( $context );
    $course_counts      = stm_get_course_counts_by_category( $context );
    $new_course_counts  = stm_get_new_course_counts_by_category( $context );
    $initial_category   = 0;
    $display_title      = $context['title'];
    $mobile_filter_id   = wp_unique_id( 'stm-cat-select-' );
    $search_id          = wp_unique_id( 'stm-course-search-' );
    $search_value       = isset( $context['search'] ) ? $context['search'] : '';

    if ( ! $context['show_all_tab'] && ! is_wp_error( $categories ) && ! empty( $categories ) ) {
        $initial_category = (int) $categories[0]->term_id;
        $display_title    = $categories[0]->name;
    }

    $courses = stm_get_courses_grid_markup( $initial_category, $context );
    $show_demo_panel = $context['show_demo_panel'];

    ob_start();
    ?>
    <div class="stm-course-archive"
         data-posts-per-page="<?php echo esc_attr( $context['posts_per_page'] ); ?>"
         data-default-title="<?php echo esc_attr( $context['title'] ); ?>"
         data-include-categories="<?php echo esc_attr( implode( ',', $context['include_categories'] ) ); ?>"
         data-exclude-categories="<?php echo esc_attr( implode( ',', $context['exclude_categories'] ) ); ?>">
      <div class="stm-archive-wrap">
        <aside class="stm-cat-sidebar">
          <h3 class="stm-sidebar-title">Categories</h3>
          <div class="stm-cat-mobile-filter">
            <label class="stm-cat-mobile-label" for="<?php echo esc_attr( $mobile_filter_id ); ?>">Filter by category</label>
            <select class="stm-cat-select" id="<?php echo esc_attr( $mobile_filter_id ); ?>">
              <?php if ( $context['show_all_tab'] ) : ?>
                <option value="0" selected>
                  <?php echo esc_html( sprintf( 'All courses (%d)', absint( $course_counts[0] ) ) ); ?>
                </option>
              <?php endif; ?>

              <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
                <?php foreach ( $categories as $stm_index => $stm_cat ) : ?>
                  <option value="<?php echo esc_attr( $stm_cat->term_id ); ?>" <?php selected( ! $context['show_all_tab'] && 0 === $stm_index ); ?>>
                    <?php echo esc_html( sprintf( '%s (%d)', $stm_cat->name, absint( $course_counts[ $stm_cat->term_id ] ) ) ); ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <ul class="stm-cat-list">
            <?php if ( $context['show_all_tab'] ) : ?>
              <li class="stm-cat-item">
                <a href="#" class="stm-cat-link active" data-cat-id="0">
                  <span class="stm-cat-name-wrap">
                    <span class="stm-cat-name-row">
                      <span class="stm-cat-name">All courses</span>
                      <?php if ( ! empty( $new_course_counts[0] ) ) : ?>
                        <span class="stm-cat-new-pill"><?php echo esc_html( 'New (' . absint( $new_course_counts[0] ) . ')' ); ?></span>
                      <?php endif; ?>
                    </span>
                    <?php if ( ! empty( $new_course_counts[0] ) ) : ?>
                      <span class="stm-cat-new-count">Just added</span>
                    <?php endif; ?>
                  </span>
                  <span class="stm-cat-count"><?php echo absint( $course_counts[0] ); ?></span>
                </a>
              </li>
            <?php endif; ?>

            <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
              <?php foreach ( $categories as $stm_index => $stm_cat ) : ?>
                <li class="stm-cat-item">
                  <a href="#"
                     class="stm-cat-link <?php echo ! $context['show_all_tab'] && 0 === $stm_index ? 'active' : ''; ?>"
                     data-cat-id="<?php echo esc_attr( $stm_cat->term_id ); ?>">
                    <span class="stm-cat-name-wrap">
                      <span class="stm-cat-name-row">
                        <span class="stm-cat-name"><?php echo esc_html( $stm_cat->name ); ?></span>
                        <?php if ( ! empty( $new_course_counts[ $stm_cat->term_id ] ) ) : ?>
                          <span class="stm-cat-new-pill"><?php echo esc_html( 'New (' . absint( $new_course_counts[ $stm_cat->term_id ] ) . ')' ); ?></span>
                        <?php endif; ?>
                      </span>
                      <?php if ( ! empty( $new_course_counts[ $stm_cat->term_id ] ) ) : ?>
                        <span class="stm-cat-new-count">Just added</span>
                      <?php endif; ?>
                    </span>
                    <span class="stm-cat-count"><?php echo absint( $course_counts[ $stm_cat->term_id ] ); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </aside>

        <main class="stm-course-main">
          <div class="stm-main-header">
            <div class="stm-main-heading">
              <h2 class="stm-main-title"><?php echo esc_html( $display_title ); ?></h2>
              <?php if ( ! empty( $new_course_counts[ $initial_category ] ) ) : ?>
                <span class="stm-main-new-pill"><?php echo esc_html( 'New (' . absint( $new_course_counts[ $initial_category ] ) . ')' ); ?></span>
              <?php endif; ?>
            </div>
            <div class="stm-main-tools">
              <label class="stm-course-search" for="<?php echo esc_attr( $search_id ); ?>">
                <span class="screen-reader-text">Search courses</span>
                <input type="search"
                       id="<?php echo esc_attr( $search_id ); ?>"
                       class="stm-course-search-input"
                       placeholder="Search courses"
                       value="<?php echo esc_attr( $search_value ); ?>"
                       autocomplete="off" />
              </label>
              <span class="stm-result-count">
                <?php echo esc_html( stm_get_result_count_label( $courses['count'] ) ); ?>
                <?php if ( ! empty( $new_course_counts[ $initial_category ] ) ) : ?>
                  <span class="stm-result-new-count"><?php echo esc_html( sprintf( 'Just added: %d', absint( $new_course_counts[ $initial_category ] ) ) ); ?></span>
                <?php endif; ?>
              </span>
            </div>
          </div>

          <div class="stm-course-grid"><?php echo $courses['html']; ?></div>
        </main>

        <?php if ( $show_demo_panel ) : ?>
          <?php echo stm_get_demo_panel_markup( $context ); ?>
        <?php endif; ?>
      </div>
    </div>
    <?php

    return ob_get_clean();
}

function stm_get_result_count_label( $count ) {
    $count = intval( $count );

    return sprintf(
        '%d course%s',
        $count,
        1 === $count ? '' : 's'
    );
}

function stm_render_course_card() {
    include STM_TUTOR_CUSTOMIZATION_DIR . 'stm-course-card.php';
}

add_action( 'wp_ajax_stm_filter_courses', 'stm_filter_courses_callback' );
add_action( 'wp_ajax_nopriv_stm_filter_courses', 'stm_filter_courses_callback' );
function stm_filter_courses_callback() {
    check_ajax_referer( 'stm_course_filter', 'nonce' );

    $args = array(
        'posts_per_page'     => isset( $_POST['posts_per_page'] ) ? wp_unslash( $_POST['posts_per_page'] ) : -1,
        'include_categories' => isset( $_POST['include_categories'] ) ? wp_unslash( $_POST['include_categories'] ) : '',
        'exclude_categories' => isset( $_POST['exclude_categories'] ) ? wp_unslash( $_POST['exclude_categories'] ) : '',
        'search'             => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
    );

    $selected_cat_id = isset( $_POST['category_id'] ) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '0';
    $courses         = stm_get_courses_grid_markup( $selected_cat_id, $args );
    $new_count       = stm_get_selected_new_course_count( $selected_cat_id, $args );

    wp_send_json_success(
        array(
            'html'             => $courses['html'],
            'count'            => $courses['count'],
            'count_label'      => stm_get_result_count_label( $courses['count'] ),
            'new_count'        => $new_count,
            'new_count_label'  => $new_count > 0 ? sprintf( 'Just added: %d', absint( $new_count ) ) : '',
        )
    );
}
