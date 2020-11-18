<?php

require_once( API__PLUGIN_DIR . 'controllers/class.rest-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.role-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.auth-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.option-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.settings-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.user-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.post-controller.php' );
require_once( API__PLUGIN_DIR . 'controllers/class.category-controller.php' );

class Wordpress_REST_API {

    private static $API_ROUTE = 'api/v1';

    /**
     * Register the REST API routes.
     *
     * @return void
     */
    public function init() {

        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: HEAD, GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method,Access-Control-Request-Headers, Authorization");
        header('Content-Type: application/json');
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            header('Access-Control-Allow-Origin: *');
            header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method,Access-Control-Request-Headers, Authorization");
            header("HTTP/1.1 200 OK");
            die();
        }
        
        if (!function_exists('register_rest_route')) {
            return false;
        }

        $rest_controller = new REST_Controller;

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
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(
                    'Auth_Controller', 'sign_out'
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

        /**
         * USER CONTROLLER
         */

        register_rest_route(
			self::$API_ROUTE, '/users',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'User_Controller', 'get_users' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
					'args'                => array( 'User_Controller', 'get_collection_params' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( 'User_Controller', 'create_user' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
					'args'                => $rest_controller->get_rest_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				),
                'schema' => array( 'REST_controller', 'get_rest_public_item_schema' ),
			)
        );

        register_rest_route(
			self::$API_ROUTE,
			'/users/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the user.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'User_Controller', 'get_user' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
					'args'                => array(
						'context' => $rest_controller->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( 'User_Controller', 'update_user' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
					'args'                => $rest_controller->get_rest_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( 'User_Controller', 'delete_user' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
				),
				'schema' => array( 'REST_controller', 'get_rest_public_item_schema' ),
			)
        );

        register_rest_route(
			self::$API_ROUTE, '/generate-password',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'User_Controller', 'generate_password' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
				),
			)
        );

         /**
         * POSTS CONTROLLER
         */

        register_rest_route(
			self::$API_ROUTE, '/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( 'Post_Controller', 'get_posts' ),
                    // 'args'                => array( 'Post_Controller', 'get_collection_params' ),
				),
			)
        );

        register_rest_route(
			self::$API_ROUTE,
			'/post/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the object.' ),
						'type'        => 'integer',
					),
				),
				// array(
				// 	'methods'             => WP_REST_Server::READABLE,
				// 	'callback'            => array( $this, 'get_item' ),
				// 	'permission_callback' => array( $this, 'get_item_permissions_check' ),
				// 	'args'                => $get_item_args,
				// ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Post_Controller', 'update_post' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
					// 'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				// array(
				// 	'methods'             => WP_REST_Server::DELETABLE,
				// 	'callback'            => array( $this, 'delete_item' ),
				// 	'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				// 	'args'                => array(
				// 		'force' => array(
				// 			'type'        => 'boolean',
				// 			'default'     => false,
				// 			'description' => __( 'Whether to bypass trash and force deletion.' ),
				// 		),
				// 	),
				// ),
				'schema' => array( 'REST_controller', 'get_rest_public_item_schema' ),
			)
		);

        register_rest_route(
			self::$API_ROUTE, '/authors',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( 'Post_Controller', 'get_authors' ),
                    'permission_callback' => array( 'Auth_Controller', 'authentication' ),
				),
			)
        );

        register_rest_route(
			self::$API_ROUTE, '/post-statuses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( 'Post_Controller', 'get_statuses' ),
                    'permission_callback' => array( 'Auth_Controller', 'authentication' ),
				),
			)
        );


        /**
         * POSTS CONTROLLER
         */

        register_rest_route(
			self::$API_ROUTE, '/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( 'Category_Controller', 'get_categories' ),
                    'args'                => array( 'Category_Controller', 'get_collection_params' ),
				),
			)
        );

        /**
         * SETTINGS CONTROLLER
         */

        register_rest_route(
			self::$API_ROUTE, '/settings/languages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Settings_Controller', 'get_languages' ),
					'permission_callback' => array( 'Auth_Controller', 'authentication' ),
				),
			)
        );

    }
}