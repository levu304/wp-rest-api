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
        ), 201 );
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
}