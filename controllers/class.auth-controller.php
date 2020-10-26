<?php

final class Auth_Controller {
    
    final public function sign_on($request) {
        $body = json_decode($request->get_body());
        $user = wp_authenticate($body->user_login, $body->user_password);
        if (!property_exists($user, 'ID')) {
            return $user;
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
    }

    final public function authentication($request) {
        $headers = apache_request_headers();
        $token = $headers['Authorization'];
        $user_id = $headers['UserID'];
        $manager = WP_Session_Tokens::get_instance($user_id);
        return $manager->verify($token);
    }


}