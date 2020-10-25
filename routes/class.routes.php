<?php

define( 'API__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
require_once( API__PLUGIN_DIR . 'controllers/class.user-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.auth-controller.php' );

class Custom_REST_API {

    private static $API_ROUTE = 'api/v1';

    /**
	 * Register the REST API routes.
	 */
    public static function init(){
        if ( ! function_exists( 'register_rest_route' ) ) {
			return false;
        }
        
        register_rest_route( self::$API_ROUTE, '/create-users', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'User_Controller', 'create_users'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            )
        ) );

        register_rest_route( self::$API_ROUTE, '/auth', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'Auth_Controller', 'sign_on'
                )
            ),
        ) );
    }
}