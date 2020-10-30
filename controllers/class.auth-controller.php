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
                    'user_id' => $user->ID,
                    'roles' => $user->roles,
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