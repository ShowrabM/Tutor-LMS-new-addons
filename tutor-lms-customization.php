<?php
/*
Plugin Name: Tutor LMS Customization
Description: Custom Tutor LMS course archive, demo management, and testimonial features.
Version: 1.1.1
Author: Showrab Mojumdar
Author URI: https://www.banglayseo.com
Plugin URI: https://github.com/showrabm
Contributors: showrabm
GitHub Profile: https://github.com/showrabm
Upwork Profile: https://www.upwork.com/freelancers/showrabm
Fiverr Profile: https://www.fiverr.com/appsdev_showrav
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STM_TUTOR_CUSTOMIZATION_VERSION', '1.1.1' );
define( 'STM_TUTOR_CUSTOMIZATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'STM_TUTOR_CUSTOMIZATION_URL', plugin_dir_url( __FILE__ ) );

require_once STM_TUTOR_CUSTOMIZATION_DIR . 'functions.php';
require_once STM_TUTOR_CUSTOMIZATION_DIR . 'includes/class-stm-tutor-demo-manager.php';
require_once STM_TUTOR_CUSTOMIZATION_DIR . 'includes/class-stm-tutor-testimonial-manager.php';

new STM_Tutor_Demo_Manager();
new STM_Tutor_Testimonial_Manager();
