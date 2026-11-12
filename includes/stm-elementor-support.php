<?php
/** Elementor-aware asset detection and the landing page's free course listing. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function stm_page_has_shortcode( $tag ) {
    if ( ! is_singular() ) {
        return false;
    }
    $post = get_post();
    if ( ! $post instanceof WP_Post ) {
        return false;
    }
    if ( has_shortcode( $post->post_content, $tag ) ) {
        return true;
    }
    $data = json_decode( get_post_meta( $post->ID, '_elementor_data', true ), true );
    return is_array( $data ) && stm_elementor_has_shortcode( $data, $tag );
}

function stm_elementor_has_shortcode( $elements, $tag ) {
    foreach ( $elements as $element ) {
        foreach ( array( 'shortcode', 'editor', 'html' ) as $key ) {
            $value = $element['settings'][ $key ] ?? '';
            if ( is_string( $value ) && has_shortcode( $value, $tag ) ) {
                return true;
            }
        }
        if ( ! empty( $element['elements'] ) && stm_elementor_has_shortcode( $element['elements'], $tag ) ) {
            return true;
        }
    }
    return false;
}

add_shortcode( 'stm_free_courses', 'stm_free_courses_shortcode' );
function stm_free_courses_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'limit' => 6 ), $atts, 'stm_free_courses' );
    stm_enqueue_course_assets( true );
    $query = new WP_Query(
        array(
            'post_type'      => 'courses',
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, min( 24, absint( $atts['limit'] ) ) ),
            'meta_key'       => '_tutor_course_price_type',
            'meta_value'     => 'free',
            'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ),
            'no_found_rows'  => true,
        )
    );
    ob_start();
    echo '<div class="stm-free-course-grid">';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            stm_render_course_card();
        }
    } else {
        echo '<p class="stm-no-courses">Free courses are coming soon.</p>';
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
