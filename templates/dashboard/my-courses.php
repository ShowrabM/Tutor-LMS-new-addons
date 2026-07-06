<?php
/**
 * My Courses Page override for Tutor LMS Customization
 *
 * Based on Tutor LMS template: templates/dashboard/my-courses.php
 */

use TUTOR\Input;
use Tutor\Models\CourseModel;

$current_user_id                     = get_current_user_id();
! isset( $active_tab ) ? $active_tab = 'my-courses' : 0;

$status_map = array(
	'my-courses'                  => CourseModel::STATUS_PUBLISH,
	'my-courses/draft-courses'    => CourseModel::STATUS_DRAFT,
	'my-courses/pending-courses'  => CourseModel::STATUS_PENDING,
	'my-courses/schedule-courses' => CourseModel::STATUS_FUTURE,
);

$status    = isset( $status_map[ $active_tab ] ) ? $status_map[ $active_tab ] : CourseModel::STATUS_PUBLISH;
$post_type = apply_filters( 'tutor_dashboard_course_list_post_type', array( tutor()->course_post_type ) );

$count_map = array(
	'publish' => CourseModel::get_courses_by_instructor( $current_user_id, CourseModel::STATUS_PUBLISH, 0, 0, true, $post_type ),
	'pending' => CourseModel::get_courses_by_instructor( $current_user_id, CourseModel::STATUS_PENDING, 0, 0, true, $post_type ),
	'draft'   => CourseModel::get_courses_by_instructor( $current_user_id, CourseModel::STATUS_DRAFT, 0, 0, true, $post_type ),
	'future'  => CourseModel::get_courses_by_instructor( $current_user_id, CourseModel::STATUS_FUTURE, 0, 0, true, $post_type ),
);

$course_archive_arg = isset( $GLOBALS['tutor_course_archive_arg'] ) ? $GLOBALS['tutor_course_archive_arg']['column_per_row'] : null;
$courseCols         = null === $course_archive_arg ? tutor_utils()->get_option( 'courses_col_per_row', 4 ) : $course_archive_arg;
$per_page           = function_exists( 'stm_is_current_user_instructor' ) && stm_is_current_user_instructor()
	? PHP_INT_MAX
	: tutor_utils()->get_option( 'courses_per_page', 10 );
$paged              = Input::get( 'current_page', 1, Input::TYPE_INT );
$offset             = $per_page * ( $paged - 1 );
$results            = CourseModel::get_courses_by_instructor( $current_user_id, $status, $offset, $per_page, false, $post_type );
$show_course_delete = true;
$post_type_query    = Input::get( 'type', '' );
$post_type_args     = $post_type_query ? array( 'type' => $post_type_query ) : array();

$tabs = array(
	'publish' => array(
		'title' => __( 'Publish', 'tutor' ),
		'link'  => 'my-courses',
	),
	'pending' => array(
		'title' => __( 'Pending', 'tutor' ),
		'link'  => 'my-courses/pending-courses',
	),
	'draft'   => array(
		'title' => __( 'Draft', 'tutor' ),
		'link'  => 'my-courses/draft-courses',
	),
	'future'  => array(
		'title' => __( 'Schedule', 'tutor' ),
		'link'  => 'my-courses/schedule-courses',
	),
);

if ( ! current_user_can( 'administrator' ) && ! tutor_utils()->get_option( 'instructor_can_delete_course' ) ) {
	$show_course_delete = false;
}
?>

<div class="tutor-dashboard-my-courses">
	<div class="tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mb-16">
		<?php esc_html_e( 'My Courses', 'tutor' ); ?>
	</div>

	<?php do_action( 'tutor_dashboard_my_courses_filter' ); ?>

	<div class="tutor-dashboard-content-inner">
		<div class="tutor-mb-32 tutor-w-100">
			<ul class="tutor-nav">
				<?php foreach ( $tabs as $key => $tab ) : ?>
				<li class="tutor-nav-item">
					<a class="tutor-nav-link<?php echo esc_attr( $tab['link'] === $active_tab ? ' is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( $post_type_args, tutor_utils()->get_tutor_dashboard_page_permalink( $tab['link'] ) ) ); ?>">
						<?php echo esc_html( $tab['title'] ); ?> <?php echo esc_html( '(' . $count_map[ $key ] . ')' ); ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<?php
		$placeholder_img = tutor()->url . 'assets/images/placeholder.svg';

		if ( ! is_array( $results ) || ( ! count( $results ) && 1 == $paged ) ) {
			tutor_utils()->tutor_empty_state( tutor_utils()->not_found_text() );
		} else {
			?>
			<div class="tutor-grid tutor-grid-3">
				<?php
				global $post;
				$tutor_nonce_value = wp_create_nonce( tutor()->nonce_action );
				foreach ( $results as $post ) :
					setup_postdata( $post );

					$avg_rating         = tutor_utils()->get_course_rating()->rating_avg;
					$tutor_course_img   = get_tutor_course_thumbnail_src();
					$id_string_delete   = 'tutor_my_courses_delete_' . $post->ID;
					$row_id             = 'tutor-dashboard-my-course-' . $post->ID;
					$course_duration    = get_tutor_course_duration_context( $post->ID, true );
					$course_students    = tutor_utils()->count_enrolled_users_by_course();
					$is_main_instructor = CourseModel::is_main_instructor( $post->ID );
					$course_edit_link   = apply_filters( 'tutor_dashboard_course_list_edit_link', tutor_utils()->course_edit_link( $post->ID, tutor()->has_pro ? 'frontend' : 'backend' ), $post );
					?>

					<div id="<?php echo esc_attr( $row_id ); ?>" class="tutor-card tutor-course-card tutor-mycourse-<?php the_ID(); ?>">
						<a href="<?php echo esc_url( get_the_permalink() ); ?>" class="tutor-d-block">
							<div class="tutor-ratio tutor-ratio-16x9">
								<img class="tutor-card-image-top" src="<?php echo empty( $tutor_course_img ) ? esc_url( $placeholder_img ) : esc_url( $tutor_course_img ); ?>" alt="<?php the_title(); ?>" loading="lazy">
							</div>
						</a>

						<?php if ( false === $is_main_instructor ) : ?>
						<div class="tutor-course-co-author-badge"><?php esc_html_e( 'Co-author', 'tutor' ); ?></div>
						<?php endif; ?>
						<div class="tutor-card-body">
							<?php do_action( 'tutor_my_courses_before_meta', get_the_ID() ); ?>
							<div class="tutor-meta tutor-mb-8">
								<span>
									<?php echo esc_html( get_the_date() ); ?> <?php echo esc_html( get_the_time() ); ?>
								</span>
							</div>

							<div class="tutor-course-name tutor-fs-6 tutor-fw-bold tutor-mb-16">
								<a href="<?php echo esc_url( get_the_permalink() ); ?>"><?php the_title(); ?></a>
							</div>

							<?php if ( ! empty( $course_duration ) || ! empty( $course_students ) ) : ?>
							<div class="tutor-meta tutor-mt-16">
								<?php if ( ! empty( $course_duration ) ) : ?>
									<div>
										<span class="tutor-icon-clock-line tutor-meta-icon" aria-hidden="true"></span>
										<span class="tutor-meta-value">
										<?php
										echo wp_kses(
											stripslashes( $course_duration ),
											array(
												'span' => array( 'class' => true ),
											)
										);
										?>
										</span>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $course_students ) ) : ?>
									<div>
										<span class="tutor-icon-user-line tutor-meta-icon" aria-hidden="true"></span>
										<span class="tutor-meta-value">
										<?php
										echo wp_kses(
											stripslashes( $course_students ),
											array(
												'span' => array( 'class' => true ),
											)
										);
										?>
										</span>
									</div>
								<?php endif; ?>
							</div>
							<?php endif; ?>
						</div>

						<div class="tutor-card-footer">
							<div class="tutor-d-flex tutor-align-center tutor-justify-between">
								<div class="tutor-d-flex tutor-align-center">
									<span class="tutor-fs-7 tutor-fw-medium tutor-color-muted tutor-mr-4">
										<?php
										$membership_only_mode = apply_filters( 'tutor_membership_only_mode', false );
										echo esc_html( $membership_only_mode ? __( 'Plan:', 'tutor' ) : '' );
										?>
									</span>
									<span class="tutor-fs-7 tutor-fw-medium tutor-color-black">
										<?php
										$price = tutor_utils()->get_course_price();
										if ( null === $price ) {
											esc_html_e( 'Free', 'tutor' );
										} else {
											echo wp_kses_post( tutor_utils()->get_course_price() );
										}
										?>
									</span>
								</div>
								<div class="tutor-iconic-btn-group tutor-mr-n8">
									<a href="<?php echo esc_url( $course_edit_link ); ?>" class="tutor-iconic-btn tutor-my-course-edit">
										<i class="tutor-icon-edit" aria-hidden="true"></i>
									</a>
									<div class="tutor-dropdown-parent">
										<button type="button" class="tutor-iconic-btn" action-tutor-dropdown="toggle">
											<span class="tutor-icon-kebab-menu" aria-hidden="true"></span>
										</button>
										<div id="table-dashboard-course-list-<?php echo esc_attr( $post->ID ); ?>" class="tutor-dropdown tutor-dropdown-dark tutor-text-left">
											<?php if ( tutor()->has_pro && in_array( $post->post_status, array( CourseModel::STATUS_DRAFT ), true ) ) : ?>
												<?php
												$params = http_build_query(
													array(
														'tutor_action' => 'update_course_status',
														'status'       => CourseModel::STATUS_PENDING,
														'course_id'    => $post->ID,
														tutor()->nonce => $tutor_nonce_value,
													)
												);
												?>
											<a class="tutor-dropdown-item" href="?<?php echo esc_attr( $params ); ?>">
												<i class="tutor-icon-share tutor-mr-8" aria-hidden="true"></i>
												<span>
													<?php
													$can_publish_course = current_user_can( 'administrator' ) || (bool) tutor_utils()->get_option( 'instructor_can_publish_course' );
													if ( $can_publish_course ) {
														esc_html_e( 'Publish', 'tutor' );
													} else {
														esc_html_e( 'Submit', 'tutor' );
													}
													?>
												</span>
											</a>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				<?php wp_reset_postdata(); ?>
			</div>
		<?php } ?>
	</div>
</div>
