<?php

final class Role_Controller {

    final public function get_roles($request) {  
        try {
            $headers = apache_request_headers();
            $user_id = $headers['UserID'];
            if (!user_can($user_id, 'promote_users')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }
            global $wp_roles;

            if (!isset($wp_roles)) {
                $wp_roles = new WP_Roles();
            }

            return wp_send_json_success(
                $wp_roles->roles,
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

    final public function set_role_for_user($request) {
        try {
            $headers = apache_request_headers();
            $user_id = $headers['UserID'];
            if (!user_can($user_id, 'promote_users')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }

            $body = json_decode($request->get_body());
            $change_role_user_id = $request->get_param('user_id');
            $new_role = $body->role;

            if (!isset($new_role)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid role'
                    ),
                    400
                );
            }

            $change_role_user = new WP_User($change_role_user_id);
            $change_role_user->set_role($new_role);
            
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
}