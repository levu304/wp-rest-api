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

            if ( '' === $secure ) {
                $secure = is_ssl();
            }
         
            // Front-end cookie is secure when the auth cookie is secure and the site's home URL uses HTTPS.
            $secure_logged_in_cookie = $secure && 'https' === parse_url( get_option( 'home' ), PHP_URL_SCHEME );
         
            /**
             * Filters whether the auth cookie should only be sent over HTTPS.
             *
             * @since 3.1.0
             *
             * @param bool $secure  Whether the cookie should only be sent over HTTPS.
             * @param int  $user_id User ID.
             */
            $secure = apply_filters( 'secure_auth_cookie', $secure, $user->ID );
         
            /**
             * Filters whether the logged in cookie should only be sent over HTTPS.
             *
             * @since 3.1.0
             *
             * @param bool $secure_logged_in_cookie Whether the logged in cookie should only be sent over HTTPS.
             * @param int  $user_id                 User ID.
             * @param bool $secure                  Whether the auth cookie should only be sent over HTTPS.
             */
            $secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', $secure_logged_in_cookie, $user->ID, $secure );
         
            if ( $secure ) {
                $auth_cookie_name = SECURE_AUTH_COOKIE;
                $scheme           = 'secure_auth';
            } else {
                $auth_cookie_name = AUTH_COOKIE;
                $scheme           = 'auth';
            }

            $manager = WP_Session_Tokens::get_instance($user->ID);
            $token   = $manager->create( $expiration );

            $auth_cookie      = wp_generate_auth_cookie( $user->ID, $expiration, $scheme, $token );
            $logged_in_cookie = wp_generate_auth_cookie( $user->ID, $expiration, 'logged_in', $token );
        
            /**
             * Fires immediately before the authentication cookie is set.
             *
             * @since 2.5.0
             * @since 4.9.0 The `$token` parameter was added.
             *
             * @param string $auth_cookie Authentication cookie value.
             * @param int    $expire      The time the login grace period expires as a UNIX timestamp.
             *                            Default is 12 hours past the cookie's expiration time.
             * @param int    $expiration  The time when the authentication cookie expires as a UNIX timestamp.
             *                            Default is 14 days from now.
             * @param int    $user_id     User ID.
             * @param string $scheme      Authentication scheme. Values include 'auth' or 'secure_auth'.
             * @param string $token       User's session token to use for this cookie.
             */
            do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user->ID, $scheme, $token );
        
            /**
             * Fires immediately before the logged-in authentication cookie is set.
             *
             * @since 2.6.0
             * @since 4.9.0 The `$token` parameter was added.
             *
             * @param string $logged_in_cookie The logged-in cookie value.
             * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
             *                                 Default is 12 hours past the cookie's expiration time.
             * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
             *                                 Default is 14 days from now.
             * @param int    $user_id          User ID.
             * @param string $scheme           Authentication scheme. Default 'logged_in'.
             * @param string $token            User's session token to use for this cookie.
             */
            do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user->ID, 'logged_in', $token );
        
            setcookie( $auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true );
            setcookie( $auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true );
            setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true );
            if ( COOKIEPATH != SITECOOKIEPATH ) {
                setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true );
            }

            return wp_send_json_success(
                array(
                    'authorization' => $auth_cookie,
                    'user' => $user
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
            $auth_token = $headers['Authorization'];
            $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

            $user = get_user_by('login', $cookies['username']);
            $manager = WP_Session_Tokens::get_instance($user->ID);
            $manager->destroy($cookies['token']);

            do_action( 'clear_auth_cookie' );
            // Auth cookies.
            setcookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN );
            setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN );
            setcookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
            setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
            setcookie( LOGGED_IN_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
            setcookie( LOGGED_IN_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        
            // Settings cookies.
            setcookie( 'wp-settings-' . $user->ID, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH );
            setcookie( 'wp-settings-time-' . $user->ID, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH );
        
            // Old cookies.
            setcookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
            setcookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
            setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
            setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        
            // Even older cookies.
            setcookie( USER_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
            setcookie( PASS_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
            setcookie( USER_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
            setcookie( PASS_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        
            // Post password cookie.
            setcookie( 'wp-postpass_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
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

    final public function authentication($request) {
        $headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
        if(!isset($auth_cookie) || $auth_cookie == ''){
            return false;
        }
        return wp_validate_auth_cookie($auth_cookie, 'auth');
    }
}