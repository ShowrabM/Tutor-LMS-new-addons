<?php

$stm_post_id   = get_the_ID();
$stm_thumb     = get_the_post_thumbnail_url( $stm_post_id, 'medium' );
$stm_permalink = get_permalink( $stm_post_id );
$stm_title     = get_the_title( $stm_post_id );
$stm_is_new    = function_exists( 'stm_is_course_new' ) ? stm_is_course_new( $stm_post_id ) : false;

$stm_cats     = get_the_terms( $stm_post_id, 'course-category' );
$stm_cat_name = ( ! is_wp_error( $stm_cats ) && ! empty( $stm_cats ) )
    ? $stm_cats[0]->name
    : '';

$stm_lesson_count = 0;
if ( function_exists( 'tutor_utils' ) ) {
    $stm_lesson_count = tutor_utils()->get_lesson_count_by_course( $stm_post_id );
}

$stm_price = '';
if ( function_exists( 'tutor_utils' ) ) {
    $stm_raw_price = tutor_utils()->get_course_price( $stm_post_id );
    $stm_price     = $stm_raw_price ? trim( wp_strip_all_tags( $stm_raw_price ) ) : 'Free';
}
?>

<div class="stm-course-card">
    <a href="<?php echo esc_url( $stm_permalink ); ?>" class="stm-card-link">
        <div class="stm-card-thumb">
            <?php if ( $stm_thumb ) : ?>
                <?php if ( $stm_is_new ) : ?>
                    <span class="stm-card-badge-new">Just Added</span>
                <?php endif; ?>
                <img src="<?php echo esc_url( $stm_thumb ); ?>"
                     alt="<?php echo esc_attr( $stm_title ); ?>"
                     loading="lazy">
            <?php else : ?>
                <div class="stm-thumb-placeholder">
                    <?php if ( $stm_is_new ) : ?>
                        <span class="stm-card-badge-new">Just Added</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="stm-card-body">
            <?php if ( $stm_cat_name ) : ?>
                <span class="stm-cat-tag"><?php echo esc_html( $stm_cat_name ); ?></span>
            <?php endif; ?>

            <h3 class="stm-card-title"><?php echo esc_html( $stm_title ); ?></h3>

            <?php if ( $stm_lesson_count ) : ?>
                <div class="stm-card-meta">
                    <span><?php echo absint( $stm_lesson_count ); ?> lessons</span>
                </div>
            <?php endif; ?>

            <div class="stm-price-btn-wrap">
                <span class="stm-price-btn"><?php echo esc_html( $stm_price ); ?></span>
            </div>
        </div>
    </a>
</div>
