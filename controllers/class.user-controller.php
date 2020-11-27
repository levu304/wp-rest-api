<?php

class User_Controller {

    public function get_users($request) {
		$controller = new WP_REST_Users_Controller;

        $result = $controller->get_items_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
        }
        
        $response = $controller->get_items($request);
        $data = $response->data;
        $users = array();

        foreach ($data as $key => $value) {
            $posts = (int)count_user_posts($value['id']);
            $value['posts'] = $posts;
            $users [] = $value;
        }

		return wp_send_json( array(
            'success' => true,
            'data' => $users
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
		
		$response = $controller->get_item($request);

        return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 200 );
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
		$response = $controller->create_item($request);

        return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 201 );
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
		$response = $controller->update_item($request);

        return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 200 );
    }
    
    public function delete_users( $request ) {
		
		if ( ! current_user_can( 'delete_user' ) ) {
			return new WP_Error(
				'rest_user_cannot_delete',
				__( 'Sorry, you are not allowed to delete this user.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

        // We don't support delete requests in multisite.
		if ( is_multisite() ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'The user cannot be deleted.' ),
				array( 'status' => 501 )
			);
        }

        $users = get_users( array('include' => $request['users']) );

		if ( is_wp_error( $users ) ) {
			return $users;
        }

        $controller = new WP_REST_Users_Controller; 
        $results = array();
        
        foreach ($users as $user) {
            $id       = $user->ID;
		    $reassign = false === $request['reassign'] ? null : absint( $request['reassign'] );
            $force    = isset( $request['force'] ) ? (bool) $request['force'] : false;
            
            // We don't support trashing for users.
            if ( ! $force ) {
                $result[] = new WP_Error(
                    'rest_trash_not_supported',
                    /* translators: %s: force=true */
                    sprintf( __( "Users do not support trashing. Set '%s' to delete." ), 'force=true' ),
                    array( 'status' => 501 )
                );
                break;
            }

            if ( ! empty( $reassign ) ) {
                if ( $reassign === $id || ! get_userdata( $reassign ) ) {
                    $result[] = new WP_Error(
                        'rest_user_invalid_reassign',
                        __( 'Invalid user ID for reassignment.' ),
                        array( 'status' => 400 )
                    );
                    break;
                }
            }

            $request->set_param( 'context', 'edit' );
            $previous = $controller->prepare_item_for_response( $user, $request );
            
            require_once ABSPATH . 'wp-admin/includes/user.php';
            $result = wp_delete_user( $id, $reassign );
            
            if ( ! $result ) {
                $result[] = new WP_Error(
                    'rest_cannot_delete',
                    __( 'The user cannot be deleted.' ),
                    array( 'status' => 500 )
                );
                break;
            }
            $response = new WP_REST_Response();
            $response->set_data(
                array(
                    'deleted'  => true,
                    'previous' => $previous->get_data(),
                )
            );

            /**
             * Fires immediately after a user is deleted via the REST API.
             *
             * @since 4.7.0
             *
             * @param WP_User          $user     The user data.
             * @param WP_REST_Response $response The response returned from the API.
             * @param WP_REST_Request  $request  The request sent to the API.
             */
            do_action( 'rest_delete_user', $user, $response, $request );

            $results[] = $response->data;
        }

        return wp_send_json( array(
            'success' => true,
            'data' => $results
        ), 200 );
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