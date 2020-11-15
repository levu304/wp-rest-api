<?php

class Auth_Controller {

    public function sign_on($request) {
        try {
            $body = json_decode($request->get_body());
            $user = wp_authenticate($body->user_login, $body->user_password);
            if (!property_exists($user, 'ID')) {
                return wp_send_json_error(
                    $user,
                    401
                );
            }

            if ($body->remember) {
                /**
                 * Filters the duration of the authentication cookie expiration period.
                 *
                 * @since 2.8.0
                 *
                 * @param int  $length   Duration of the expiration period in seconds.
                 * @param int  $user_id  User ID.
                 * @param bool $remember Whether to remember the user login. Default false.
                 */
                $expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user->ID, $body->remember );
         
                /*
                 * Ensure the browser will continue to send the cookie after the expiration time is reached.
                 * Needed for the login grace period in wp_validate_auth_cookie().
                 */
                $expire = $expiration + (12 * HOUR_IN_SECONDS);
            } else {
                /** This filter is documented in wp-includes/pluggable.php */
                $expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user->ID, false );
                $expire     = 0;
            }

            $manager = WP_Session_Tokens::get_instance($user->ID);
            $token   = $manager->create( $expiration );

            $auth_cookie = wp_generate_auth_cookie( $user->ID, $expiration, 'auth', $token );
            $logged_in_cookie = wp_generate_auth_cookie( $user->ID, $expiration, 'logged_in', $token );

            do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user->ID, 'auth', $token );
            do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user->ID, 'logged_in', $token );

            $data   = array();
			$data['id'] = $user->ID;
			$data['username'] = $user->user_login;
			$data['name'] = $user->display_name;
			$data['first_name'] = $user->first_name;
			$data['last_name'] = $user->last_name;
			$data['email'] = $user->user_email;
			$data['url'] = $user->user_url;
			$data['description'] = $user->description;
			$data['locale'] = get_user_locale( $user );
			$data['nickname'] = $user->nickname;
			$data['slug'] = $user->user_nicename;
			$data['roles'] = array_values( $user->roles );
			$data['capabilities'] = (object) $user->allcaps;
			$data['extra_capabilities'] = (object) $user->caps;
            $data['avatar_urls'] = rest_get_avatar_urls( $user );
            
            $controller = new WP_REST_Users_Controller;
            $response_data = $controller->prepare_response_for_collection( $data );

            return wp_send_json_success(
                array(
                    'authorization' => $auth_cookie,
                    'user' => $response_data
                )
            );
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        }  
    }

    public function sign_out($request) {     
        try {
            $headers = apache_request_headers();
            $auth_cookie = $headers['Authorization'];
            $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

            $user = get_user_by('login', $cookies['username']);
            $manager = WP_Session_Tokens::get_instance($user->ID);
            $manager->destroy($cookies['token']);

            do_action( 'clear_auth_cookie' );
            wp_set_current_user(0);
            do_action( 'wp_logout', $user->ID );

            return wp_send_json_success(
                array(
                    'message' => "Successful"
                ),
                200
            );
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        }  
    }

    public function reset_password($request) {
        try {
            $errors = new WP_Error();
            $body = json_decode($request->get_body());
            if (!isset($body) || !isset($body->user_login)) {
                $errors->add('empty_username', __('<strong>Error</strong>: Please enter a username or email address.'));
                return wp_send_json_error(
                    $errors,
                    400
                );
            } elseif (strpos($body->user_login,'@')) {
                $user_data = get_user_by('email', $body->user_login);
                if (empty($user_data)) {
                    $errors->add('invalid_email', __('<strong>Error</strong>: There is no account with that username or email address.'));
                    return wp_send_json_error(
                        $errors,
                        400
                    );
                }
                
	        } else {
	            $user_data = get_user_by('login', $body->user_login);
            }
            
            do_action('lostpassword_post', $errors, $user_data);
            $errors = apply_filters('lostpassword_errors', $errors, $user_data);

            if ($errors->has_errors()) { 
                return wp_send_json_error(
                    $errors,
                    400
                );
            }

            if (!$user_data) {
	            $errors->add('invalidcombo', __('<strong>Error</strong>: There is no account with that username or email address.'));
	            return wp_send_json_error(
                    $errors,
                    404
                );
            }
            
            $user_login = $user_data->user_login;
	        $user_email = $user_data->user_email;
	        $key = get_password_reset_key($user_data);
	
	        if (is_wp_error($key)) {
                return wp_send_json_error(
                    $key,
                    400
                );
            }
            
            if (is_multisite()) {
	            $site_name = get_network()->site_name;
	        } else {
                /*
                    * The blogname option is escaped with esc_html on the way into the database
                    * in sanitize_option we want to reverse this for the plain text arena of emails.
                    */
                $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
	        }
	
	        $message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
	        /* translators: %s: Site name. */
	        $message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
	        /* translators: %s: User login. */
	        $message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
	        $message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
            $message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
            $message .= $body->origin . "/reset?action=rp&key=$key&login=" . rawurlencode( $user_login ) . "\r\n";
	
	        /* translators: Password reset notification email subject. %s: Site title. */
            $title = sprintf( __( '[%s] Password Reset' ), $site_name );
            
            $title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );
            $message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

            if ($message && !wp_mail($user_email, wp_specialchars_decode($title), $message)) {
                $errors->add(
                    'retrieve_password_email_failure',
                    /* translators: %s: Documentation URL. */
                    __( '<strong>Error</strong>: The email could not be sent. Your site may not be correctly configured to send emails.')
                );
                return wp_send_json_error(
                    $errors,
                    400
                );
	        }

            return wp_send_json_success(
                array(
                    'message' => "Successful"
                ),
                200
            );
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        }  
    }

    public function authentication($request) {
        $headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
        if(!isset($auth_cookie) || $auth_cookie == ''){
            return false;
        }
        $result = wp_validate_auth_cookie($auth_cookie, 'auth');
        if(!$result){
            return $result;
        }
        $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');
		$user = get_user_by('login', $cookies['username']);
        wp_set_current_user($user->ID);
        return true;
    }
}