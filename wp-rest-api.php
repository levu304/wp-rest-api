<?php
/**
 * Plugin Name: Wordpress RESTful API
 * Description: RESTful API for Wordpress
 * Plugin URI: 
 * Author: Vu Le
 * Author URI: 
 * Version: 0.1.0
 *
*/
define( 'API__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( API__PLUGIN_DIR . 'routes/class.routes.php' );

add_action( 'rest_api_init', array( 'Wordpress_REST_API', 'init' ) );