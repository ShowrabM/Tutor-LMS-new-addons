<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        'show_all_tab'       => true,
    );

    $args = wp_parse_args( $args, $defaults );

    return array(
        'posts_per_page'     => stm_normalize_posts_per_page( $args['posts_per_page'] ),
        'title'              => sanitize_text_field( $args['title'] ),
        'include_categories' => stm_parse_term_ids( $args['include_categories'] ),
        'exclude_categories' => stm_parse_term_ids( $args['exclude_categories'] ),
        'show_all_tab'       => stm_to_bool( $args['show_all_tab'] ),
    );
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

function stm_get_course_archive_markup( $args = array() ) {
    $context            = stm_get_archive_context( $args );
    $categories         = stm_get_filtered_categories( $context );
    $initial_category   = 0;
    $display_title      = $context['title'];

    if ( ! $context['show_all_tab'] && ! is_wp_error( $categories ) && ! empty( $categories ) ) {
        $initial_category = (int) $categories[0]->term_id;
        $display_title    = $categories[0]->name;
    }

    $courses = stm_get_courses_grid_markup( $initial_category, $context );

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
          <ul class="stm-cat-list">
            <?php if ( $context['show_all_tab'] ) : ?>
              <li class="stm-cat-item">
                <a href="#" class="stm-cat-link active" data-cat-id="0">
                  <span class="stm-cat-name">All courses</span>
                  <span class="stm-cat-count"><?php echo absint( $courses['count'] ); ?></span>
                </a>
              </li>
            <?php endif; ?>

            <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
              <?php foreach ( $categories as $stm_index => $stm_cat ) : ?>
                <li class="stm-cat-item">
                  <a href="#"
                     class="stm-cat-link <?php echo ! $context['show_all_tab'] && 0 === $stm_index ? 'active' : ''; ?>"
                     data-cat-id="<?php echo esc_attr( $stm_cat->term_id ); ?>">
                    <span class="stm-cat-name"><?php echo esc_html( $stm_cat->name ); ?></span>
                    <span class="stm-cat-count"><?php echo absint( $stm_cat->count ); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </aside>

        <main class="stm-course-main">
          <div class="stm-main-header">
            <h2 class="stm-main-title"><?php echo esc_html( $display_title ); ?></h2>
            <span class="stm-result-count"><?php echo esc_html( stm_get_result_count_label( $courses['count'] ) ); ?></span>
          </div>

          <div class="stm-course-grid"><?php echo $courses['html']; ?></div>
        </main>
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
    );

    $selected_cat_id = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;
    $courses         = stm_get_courses_grid_markup( $selected_cat_id, $args );

    wp_send_json_success(
        array(
            'html'        => $courses['html'],
            'count'       => $courses['count'],
            'count_label' => stm_get_result_count_label( $courses['count'] ),
        )
    );
}
