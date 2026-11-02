<?php
/**
 * Tutor Demos Dashboard Page override for Tutor LMS Customization
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="tutor-dashboard-content-inner">
	<div class="tutor-flex tutor-items-center tutor-justify-between tutor-mb-24">
		<h3 class="tutor-h4 tutor-text-primary">
			<?php esc_html_e( 'Tutor Demos', 'tutor' ); ?>
		</h3>
		<?php if ( function_exists( 'stm_user_can_manage_demos' ) && stm_user_can_manage_demos() ) : ?>
			<a href="#" class="tutor-btn tutor-btn-primary tdl-dashboard-upload-btn">
				+ <?php esc_html_e( 'Upload Demo', 'tutor' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="tutor-surface-l1 tutor-border tutor-rounded-2xl tutor-p-24">
		<?php echo do_shortcode( '[tutor_demo_upload_form]' ); ?>
	</div>
</div>
