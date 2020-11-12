<?php

require_once( 'class.rest-controller.php' );

class User_Controller {

    public function get_users($request) {
        $controller = new WP_REST_Users_Controller;

        $result = self::get_items_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
        }
        $headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
        $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

        $user = get_user_by('login', $cookies['username']);
        $params = (array)$request->get_query_params();
        // Retrieve the list of registered collection query parameters.
        $registered = self::get_collection_params();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'exclude'  => 'exclude',
			'include'  => 'include',
			'order'    => 'order',
			'per_page' => 'number',
			'search'   => 'search',
			'roles'    => 'role__in',
			'slug'     => 'nicename__in',
		);

		$prepared_args = array();

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $prepared_args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $params[ $api_param ] ) ) {
                $prepared_args[ $wp_param ] = $params[ $api_param ];
            }
        }

		if ( isset( $registered['offset'] ) && ! empty( $params['offset'] ) ) {
			$prepared_args['offset'] = $params['offset'];
		} else {
			$prepared_args['offset'] = ( $params['page'] - 1 ) * $prepared_args['number'];
		}

		if ( isset( $registered['orderby'] ) ) {
			$orderby_possibles        = array(
				'id'              => 'ID',
				'include'         => 'include',
				'name'            => 'display_name',
				'registered_date' => 'registered',
				'slug'            => 'user_nicename',
				'include_slugs'   => 'nicename__in',
				'email'           => 'user_email',
				'url'             => 'user_url',
			);
			$prepared_args['orderby'] = $orderby_possibles[ $params['orderby'] ];
		}

		if ( isset( $registered['who'] ) && ! empty( $params['who'] ) && 'authors' === $params['who'] ) {
			$prepared_args['who'] = 'authors';
		} elseif ( ! user_can($user->ID, 'list_users') ) {
			$prepared_args['has_published_posts'] = get_post_types( array( 'show_in_rest' => true ), 'names' );
		}

		if ( ! empty( $prepared_args['search'] ) ) {
			$prepared_args['search'] = '*' . $prepared_args['search'] . '*';
		}
		/**
		 * Filters WP_User_Query arguments when querying users via the REST API.
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_user_query/
		 *
		 * @since 4.7.0
		 *
		 * @param array           $prepared_args Array of arguments for WP_User_Query.
		 * @param WP_REST_Request $request       The current request.
		 */
        $prepared_args = apply_filters( 'rest_user_query', $prepared_args, $params );
        
		$query = new WP_User_Query( $prepared_args );

		$users = array();

		foreach ( $query->results as $u ) {
			$data    = $controller->prepare_item_for_response( $u, $request );
			$response_data = $controller->prepare_response_for_collection( $data );
			$posts = (int)count_user_posts($u->ID);
			$response_data['posts'] = $posts;
            $users[] = $response_data;
        }
        
		$response = rest_ensure_response( $users );

		// Store pagination values for headers then unset for count query.
        $per_page = (int) $prepared_args['number'];
        if($per_page == 0){
            $per_page = 1;
        }
        $page = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

		$prepared_args['fields'] = 'ID';

		$total_users = $query->get_total();

		if ( $total_users < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $prepared_args['number'], $prepared_args['offset'] );
			$count_query = new WP_User_Query( $prepared_args );
			$total_users = $count_query->get_total();
		}

		$response->header( 'X-WP-Total', (int) $total_users );

		$max_pages = ceil( $total_users / $per_page );

		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( sprintf( '%s/%s', 'api/v1', 'users' ) ) );
		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 200 );
    }

    public function get_user( $request ) {
        $controller = new WP_REST_Users_Controller;

        $result = $controller->get_item_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
        }

        return wp_send_json_success(
            $controller->get_item($request),
            200
        );
    }

    public function create_user( $request ) {
		
		$headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
		$cookies = wp_parse_auth_cookie($auth_cookie, 'auth');
		
		$user = get_user_by('login', $cookies['username']);
				
		if ( ! user_can($user->ID, 'create_users') ) {
			return new WP_Error( 'rest_cannot_create_user', __( 'Sorry, you are not allowed to create new users.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$controller = new WP_REST_Users_Controller;
		$rest_controller = new REST_controller;

		$schema = $controller->get_item_schema();
		$body = json_decode($request->get_body());		

		if ( ! empty( $body->roles ) && ! empty( $schema['properties']['roles'] ) ) {
			$check_permission = self::check_role_update( $user->ID, $body->roles );

			if ( is_wp_error( $check_permission ) ) {
				return $check_permission;
			}
		}

		$newUser = self::prepare_item_for_database( $body );

		if ( is_multisite() ) {
			$ret = wpmu_validate_user_signup( $newUser->user_login, $newUser->user_email );

			if ( is_wp_error( $ret['errors'] ) && $ret['errors']->has_errors() ) {
				$error = new WP_Error( 'rest_invalid_param', __( 'Invalid user parameter(s).' ), array( 'status' => 400 ) );
				foreach ( $ret['errors']->errors as $code => $messages ) {
					foreach ( $messages as $message ) {
						$error->add( $code, $message );
					}
					$error_data = $error->get_error_data( $code );
					if ( $error_data ) {
						$error->add_data( $error_data, $code );
					}
				}
				return $error;
			}
		}

		if ( is_multisite() ) {
			$user_id = wpmu_create_user( $newUser->user_login, $newUser->user_pass, $newUser->user_email );

			if ( ! $user_id ) {
				return new WP_Error( 'rest_user_create', __( 'Error creating new user.' ), array( 'status' => 500 ) );
			}

			$user->ID = $user_id;
			$user_id  = wp_update_user( wp_slash( (array) $newUser ) );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			$result = add_user_to_blog( get_site()->id, $user_id, '' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		} else {
			$user_id = wp_insert_user( wp_slash( (array) $newUser ) );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
		}

		$newUser = get_user_by( 'id', $user_id );

		/**
		 * Fires immediately after a user is created or updated via the REST API.
		 *
		 * @since 4.7.0
		 *
		 * @param WP_User         $user     Inserted or updated user object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a user, false when updating.
		 */
		do_action( 'rest_insert_user', $newUser, $body, true );

		if ( ! empty( $body->roles ) && ! empty( $schema['properties']['roles'] ) ) {
			array_map( array( $newUser, 'add_role' ), $request['roles'] );
		}

		if ( ! empty( $schema['properties']['meta'] ) && isset( $body->meta ) ) {
			$meta_update = $controller->meta->update_value( $body->meta, $user_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$newUser = get_user_by( 'id', $user_id );
		$fields_update = $rest_controller->update_additional_fields_for_object( $newUser, $body );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/**
		 * Fires after a user is completely created or updated via the REST API.
		 *
		 * @since 5.0.0
		 *
		 * @param WP_User         $user     Inserted or updated user object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a user, false when updating.
		 */
		do_action( 'rest_after_insert_user', $newUser, $request, true );

		$response = $controller->prepare_item_for_response( $newUser, $request );
		$response = rest_ensure_response( $response );

		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', 'api/v1', 'users', $user_id ) ) );

        return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 201 );
    }

    public function update_user( $request ) {
		$headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
		$cookies = wp_parse_auth_cookie($auth_cookie, 'auth');
		
		$user = get_user_by('login', $cookies['username']);
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$body = json_decode($request->get_body());

		if ( ! empty( $body->roles ) ) {
			if ( ! user_can( $user->ID, 'promote_user' ) ) {
				return new WP_Error( 'rest_cannot_edit_roles', __( 'Sorry, you are not allowed to edit roles of this user.' ), array( 'status' => rest_authorization_required_code() ) );
			}

			sort( $body );
		}

		if ( ! user_can( $user->ID, 'edit_user' ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$updateUser = get_user_by('ID', $body->id);
		if ( ! $user ) {
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
		}

		$id = $updateUser->ID;

		$owner_id = email_exists( $body->email );
		if ( $owner_id && $owner_id !== $id ) {
			return new WP_Error( 'rest_user_invalid_email', __( 'Invalid email address.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $body->username ) && $body->username !== $updateUser->user_login ) {
			return new WP_Error( 'rest_user_invalid_argument', __( "Username isn't editable." ), array( 'status' => 400 ) );
		}

		if ( ! empty( $body->slug ) && $body->slug !== $updateUser->user_nicename && get_user_by( 'slug', $body->slug ) ) {
			return new WP_Error( 'rest_user_invalid_slug', __( 'Invalid slug.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $body->roles ) ) {
			$check_permission = self::check_role_update( $id, $body->roles );

			if ( is_wp_error( $check_permission ) ) {
				return $check_permission;
			}
		}

		$updateUser = self::prepare_item_for_database( $body );
		$updateUser->ID = $id;
		$user_id = wp_update_user( wp_slash( (array) $updateUser ) );
		
        if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$updateUser = get_user_by( 'id', $user_id );
		do_action( 'rest_insert_user', $user, $request, false );

		if ( ! empty( $body->roles ) ) {
			array_map( array( $updateUser, 'add_role' ), $body->roles );
		}

		$controller = new WP_REST_Users_Controller;
		$rest_controller = new REST_controller;

		$schema = $controller->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && isset( $body->meta ) ) {
			$meta_update = $this->controller->update_value( $body->meta, $id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$updateUser = get_user_by( 'id', $user_id );
		$fields_update = $rest_controller->update_additional_fields_for_object( $updateUser, $body );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php */
		do_action( 'rest_after_insert_user', $updateUser, $request, false );

		$response = $controller->prepare_item_for_response( $updateUser, $request );
		$response = rest_ensure_response( $response );

		return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 200 );
    }

    public function delete_user( $request ) {		
		$headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
        $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

		$user = get_user_by('login', $cookies['username']);

		if ( ! user_can( $user->ID, 'delete_user' ) ) {
			return new WP_Error( 'rest_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$controller = new WP_REST_Users_Controller;
		$params = (array)$request->get_query_params();

		if ( is_multisite() ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 501 ) );
		}

		$delete_user = get_user_by('ID', $params['id']);
		
		if ( is_wp_error( $delete_user ) ) {
			return $delete_user;
		}

		if ( ! empty( $params['reassign'] ) ) {
			if ( $params['reassign'] === $params['id'] || ! get_userdata( $params['reassign'] ) ) {
				return new WP_Error( 'rest_user_invalid_reassign', __( 'Invalid user ID for reassignment.' ), array( 'status' => 400 ) );
			}
		}

		$request->set_param( 'context', 'edit' );

		$previous = $controller->prepare_item_for_response( $delete_user, $request );
		/** Include admin user functions to get access to wp_delete_user() */
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$result = wp_delete_user( $params['id'], $params['reassign'] );

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 500 ) );
		}

		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		do_action( 'rest_delete_user', $delete_user, $response, $request );

		return $response;

	}
	
	public function generate_password($request) {
		try {

			$hashPassword = wp_generate_password();

			return wp_send_json( array(
				'success' => true,
				'data' => $hashPassword
			), 200 );
            
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        } 
	}

    public function get_collection_params() {
        $controller = new WP_REST_Users_Controller;
        return $controller->get_collection_params();
    }

    public function check_reassign( $value, $request, $param ) {
		if ( is_numeric( $value ) ) {
			return $value;
		}

		if ( empty( $value ) || false === $value || 'false' === $value ) {
			return false;
		}

		return new WP_Error( 'rest_invalid_param', __( 'Invalid user parameter(s).' ), array( 'status' => 400 ) );
    }
    
    private function get_items_permissions_check( $request ) {

        $headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
        $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

        $user = get_user_by('login', $cookies['username']);
        $params = (array)$request->get_query_params();

		// Check if roles is specified in GET request and if user can list users.
		if ( ! empty( $request['roles'] ) && ! user_can($user->ID, 'list_users') ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to filter users by role.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( 'edit' === $request['context'] && ! user_can($user->ID, 'list_users') ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to list users.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( in_array( $request['orderby'], array( 'email', 'registered_date' ), true ) && ! user_can($user->ID, 'list_users') ) {
			return new WP_Error( 'rest_forbidden_orderby', __( 'Sorry, you are not allowed to order users by this parameter.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( 'authors' === $params['who'] ) {
			$can_view = false;
			$types    = get_post_types( array( 'show_in_rest' => true ), 'objects' );
			foreach ( $types as $type ) {
				if ( post_type_supports( $type->name, 'author' )
					&& user_can( $user->ID, $type->cap->edit_posts ) ) {
					$can_view = true;
				}
			}
			if ( ! $can_view ) {
				return new WP_Error( 'rest_forbidden_who', __( 'Sorry, you are not allowed to query users by this parameter.' ), array( 'status' => rest_authorization_required_code() ) );
			}
		}

		return true;
	}

	private function prepare_item_for_database( $body ) {
		$prepared_user = new stdClass;

		$controller = new WP_REST_Users_Controller;
		$schema = $controller->get_item_schema();

		// required arguments.
		if ( isset( $body->email ) && ! empty( $schema['properties']['email'] ) ) {
			$prepared_user->user_email = $body->email;
		}

		if ( isset( $body->username ) && ! empty( $schema['properties']['username'] ) ) {
			$prepared_user->user_login = $body->username;
		}

		if ( isset( $body->password ) && ! empty( $schema['properties']['password'] ) ) {
			$prepared_user->user_pass = $body->password;
		}

		if ( isset( $body->name ) && ! empty( $schema['properties']['name'] ) ) {
			$prepared_user->display_name = $body->name;
		}

		if ( isset( $body->first_name ) && ! empty( $schema['properties']['first_name'] ) ) {
			$prepared_user->first_name = $body->first_name;
		}

		if ( isset( $body->last_name ) && ! empty( $schema['properties']['last_name'] ) ) {
			$prepared_user->last_name = $body->last_name;
		}

		if ( isset( $body->nickname ) && ! empty( $schema['properties']['nickname'] ) ) {
			$prepared_user->nickname = $body->nickname;
		}

		if ( isset( $body->slug ) && ! empty( $schema['properties']['slug'] ) ) {
			$prepared_user->user_nicename = $body->slug;
		}

		if ( isset( $body->description ) && ! empty( $schema['properties']['description'] ) ) {
			$prepared_user->description = $body->description;
		}

		if ( isset( $body->url ) && ! empty( $schema['properties']['url'] ) ) {
			$prepared_user->user_url = $body->url;
		}

		if ( isset( $body->locale ) && ! empty( $schema['properties']['locale'] ) ) {
			$prepared_user->locale = $body->locale;
		}

		// setting roles will be handled outside of this function.
		if ( isset( $body->roles ) ) {
			$prepared_user->role = false;
		}

		/**
		 * Filters user data before insertion via the REST API.
		 *
		 * @since 4.7.0
		 *
		 * @param object          $prepared_user User object.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'rest_pre_insert_user', $prepared_user, $body );
	}

	private function check_role_update( $user_id, $roles ) {
		global $wp_roles;

		if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

		foreach ( $roles as $role ) {

			if ( ! isset( $wp_roles->role_objects[ $role ] ) ) {
				/* translators: %s: Role key. */
				return new WP_Error( 'rest_user_invalid_role', sprintf( __( 'The role %s does not exist.' ), $role ), array( 'status' => 400 ) );
			}

			$potential_role = $wp_roles->role_objects[ $role ];

			/*
			 * Don't let anyone with 'edit_users' (admins) edit their own role to something without it.
			 * Multisite super admins can freely edit their blog roles -- they possess all caps.
			 */
			if ( ! ( is_multisite()
				&& user_can($user_id, 'manage_sites' ) )
				&& get_current_user_id() === $user_id
				&& ! $potential_role->has_cap( 'edit_users' )
			) {
				return new WP_Error( 'rest_user_invalid_role', __( 'Sorry, you are not allowed to give users that role.' ), array( 'status' => rest_authorization_required_code() ) );
			}

			/** Include admin functions to get access to get_editable_roles() */
			require_once ABSPATH . 'wp-admin/includes/admin.php';

			// The new role must be editable by the logged-in user.
			$editable_roles = get_editable_roles();

			if ( empty( $editable_roles[ $role ] ) ) {
				return new WP_Error( 'rest_user_invalid_role', __( 'Sorry, you are not allowed to give users that role.' ), array( 'status' => 403 ) );
			}
		}

		return true;
	}
}