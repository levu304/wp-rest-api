<?php

class Category_Controller {

    public function get_categories($request) {
        if ( 'edit' === $request['context']) {
			$headers = apache_request_headers();
			$auth_cookie = $headers['Authorization'];
			$error = new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit terms in this taxonomy.' ), array( 'status' => rest_authorization_required_code() ) );
			if(!isset($auth_cookie) || $auth_cookie == ''){
				return $error;
			}

			$cookies = wp_parse_auth_cookie($auth_cookie, 'auth');
			$user = get_user_by('login', $cookies['username']);
			wp_set_current_user($user->ID);
			
			if(! current_user_can( 'manage_categories' ) ) {
				return $error;
			}
		}
		
		$controller = new WP_REST_Terms_Controller('category');
		if ( !isset( $request['include'] ) ) {
			$request['include'] = array();
		}

		if ( !isset( $request['exclude'] ) ) {
			$request['exclude'] = array();
		}

		if ( !isset( $request['order'] ) ) {
			$request['order'] = 'asc';
		}

		if ( !isset( $request['orderby'] ) ) {
			$request['orderby'] = 'name';
		}

		if ( !isset( $request['per_page'] ) ) {
			$request['per_page'] = 10;
		}
		
		if ( !isset( $request['page'] ) ) {
			$request['page'] = 1;
		}

		$request['hide_empty'] = false;

		if ( isset( $request['page'] ) ) {
			$request['hide_empty'] = $request['hide_empty'] === "true" ? true : false;
		}
		
		$response = $controller->get_items($request);
		
        return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 200 );

	}
	
	public function get_collection_params() {
		$controller = new WP_REST_Terms_Controller('category');
        return $controller->get_collection_params();
	}
}