<?php

require_once( 'class.rest-controller.php' );

class User_Controller extends WP_REST_Users_Controller {

    public function get_users($request) {
        $controller = new WP_REST_Users_Controller;

        $result = $controller->get_items_permissions_check($request);
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
			$data    = $controller->prepare_item_for_response( $u, $params );
			$users[] = $controller->prepare_response_for_collection( $data );
        }
        
		$response = rest_ensure_response( $users );

		// Store pagination values for headers then unset for count query.
        $per_page = (int) $prepared_args['offset'];
		$page     = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

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
        $controller = new WP_REST_Users_Controller;

        $result = $controller->create_item_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
        }

        return wp_send_json_success(
            $controller->create_item($request),
            200
        );
    }

    public function update_user( $request ) {
        $controller = new WP_REST_Users_Controller;

        $result = $controller->update_item_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
        }

        return wp_send_json_success(
            $controller->update_item($request),
            200
        );
    }

    public function delete_user( $request ) {
        $controller = new WP_REST_Users_Controller;

        $result = $controller->delete_item_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
        }

        return wp_send_json_success(
            $controller->delete_item($request),
            200
        );
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
}