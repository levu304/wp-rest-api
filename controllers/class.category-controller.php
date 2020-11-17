<?php

class Category_Controller {

    public function get_categories($request) {
        if ( 'edit' === $request['context']) {
			$headers = apache_request_headers();
			$auth_cookie = $headers['Authorization'];
			$error = new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit categories' ),
				array( 'status' => rest_authorization_required_code() )
			);
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
        
        $categories = get_categories($request);
        $response = array();

        foreach ($categories as $key => $value) {
            $response[] = $value;
        }

        return wp_send_json( array(
            'success' => true,
            'data' => $response
        ), 200 );

    }
}