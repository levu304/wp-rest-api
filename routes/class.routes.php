<?php

require_once( API__PLUGIN_DIR . 'controllers/class.role-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.auth-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.option-controller.php' );

class Wordpress_REST_API {

    private static $API_ROUTE = 'api/v1';

    /**
     * Register the REST API routes.
     *
     * @return void
     */
    public static function init() {
        header("Access-Control-Allow-Origin: *");
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
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
        ) );

        register_rest_route( self::$API_ROUTE, '/reset', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'Auth_Controller', 'reset_password'
                )
            ),
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
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'Role_Controller', 'add_role'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            )
        ) );

        register_rest_route( self::$API_ROUTE, '/roles/(?P<role_name>[a-z]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(
                    'Role_Controller', 'get_role'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'role_name'
                ]
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(
                    'Role_Controller', 'remove_role'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'role_name'
                ]
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(
                    'Role_Controller', 'edit_role_capabilities'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
                'args' => [
                    'role_name'
                ]
            ),
        ) );

        /**
         * OPTION CONTROLLER
         */

        register_rest_route( self::$API_ROUTE, '/option', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(
                    'Option_Controller', 'add_option'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(
                    'Option_Controller', 'get_option'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(
                    'Option_Controller', 'update_option'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(
                    'Option_Controller', 'delete_option'
                ),
                'permission_callback' => array(
                    'Auth_Controller', 'authentication'
                ),
            ),
        ) );

    }
}