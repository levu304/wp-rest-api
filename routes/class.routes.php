<?php

require_once( API__PLUGIN_DIR . 'controllers/class.user-controller.php');
require_once( API__PLUGIN_DIR . 'controllers/class.role-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.auth-controller.php' );

class Wordpress_REST_API {

    private static $API_ROUTE = 'api/v1';

    /**
     * Register the REST API routes.
     *
     * @return void
     */
    public static function init() {
        if (!function_exists('register_rest_route')) {
            return false;
        }


        /**
         * AUTHENTICATION CONTROLLER
         */
        
        register_rest_route( self::$API_ROUTE, '/login', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'Auth_Controller', 'sign_on'
                )
            ),
        ) );

        register_rest_route( self::$API_ROUTE, '/logout', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'Auth_Controller', 'sign_out'
                )
            ),
        ) );

        /**
         * USER CONTROLLER
         */

        register_rest_route( self::$API_ROUTE, '/users', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(
                    'User_Controller', 'get_users'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'User_Controller', 'create_user'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            )
        ) );

        register_rest_route( self::$API_ROUTE, '/users/(?P<user_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(
                    'User_Controller', 'edit_user'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'user_id'
                ]
            ),
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(
                    'User_Controller', 'get_user'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'user_id'
                ]
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(
                    'User_Controller', 'delete_user'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'user_id'
                ]
            )
        ) );

        /**
         * ROLE CONTROLLER
         */

        register_rest_route( self::$API_ROUTE, '/roles', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(
                    'Role_Controller', 'get_roles'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
        ) );


        register_rest_route( self::$API_ROUTE, '/role/(?P<user_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(
                    'Role_Controller', 'set_role_for_user'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'user_id'
                ]
            ),
        ) );
    }
}