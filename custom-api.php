<?php
/**
 * Plugin Name: Custom API
 * Description: Custom RESTful API
 * Plugin URI: 
 * Author: Vu Le
 * Author URI: 
 * Version: 0.0.1
 *
*/
define( 'API__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( API__PLUGIN_DIR . 'routes/class.routes.php' );

add_action( 'rest_api_init', array( 'Custom_REST_API', 'init' ) );