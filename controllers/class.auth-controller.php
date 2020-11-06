<?php

final class Auth_Controller {
    
    final public function sign_on($request) {
        try {
            $body = json_decode($request->get_body());
            $user = wp_authenticate($body->user_login, $body->user_password);
            if (!property_exists($user, 'ID')) {
                return wp_send_json_error(
                    $user,
                    401
                );
            }

            $manager = WP_Session_Tokens::get_instance($user->ID);
            $date = date('Y-m-d H:i:s', strtotime('now +60 minutes'));

            return wp_send_json_success(
                array(
                    'user' => $user,
                    'token' => $manager->create(strtotime($date))
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

    final public function sign_out($request) {     
        try {
            $headers = apache_request_headers();
            $token = $headers['Authorization'];
            $uid = $headers['uid'];
            $manager = WP_Session_Tokens::get_instance($uid);
            $manager->destroy($token);

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

    final public function reset_password($request) {
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

    final public function get_current_user_session($request) {
        try {
            $headers = apache_request_headers();
            $token = $headers['Authorization'];
            $uid = $headers['uid'];
            $manager = WP_Session_Tokens::get_instance($uid);

            $session = (object)$manager->get($token);
            return wp_send_json_success(
                (array)$session
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

    final public function authentication($request) {
        $headers = apache_request_headers();
        $token = $headers['Authorization'];
        $uid = $headers['uid'];
        $manager = WP_Session_Tokens::get_instance($uid);

        $result = $manager->verify($token);

        if ($result) {
            $session = (object)$manager->get($token);
            $session->expiration = strtotime('now +60 minutes');
            $manager->update($token, (array)$session);
        }

        return $result;
    }

    
}