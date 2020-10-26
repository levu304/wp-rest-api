<?php

class User_Controller {

    final public function get_users($request) {
        $headers = apache_request_headers();
        $user_id = $headers['UserID'];
        if (!user_can($user_id, 'create_users')) {
            return wp_send_json_error(
                array(
                    'message' => 'No permission'
                ),
                401
            );
        }

        try {
            $body = json_decode($request->get_body());

            if (!isset($body)) {
                return get_users();
            }

            return get_users((array)$body);
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        }
    }

    final public function create_user($request) {
        $headers = apache_request_headers();
        $user_id = $headers['UserID'];
        if (!user_can($user_id, 'create_users')) {
            return wp_send_json_error(
                array(
                    'message' => 'No permission'
                ),
                401
            );
        }

        try {
            $body = json_decode($request->get_body());
            $username = $body->username;
            $password = $body->password;
            $email = $body->email;
            $created_user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($created_user_id)) {
                $error_code = $created_user_id->get_error_code();
                $error_message = $created_user_id->get_error_message($error_code);
                return wp_send_json_error(
                    array(
                        'message' => $error_message
                    ),
                    $error_code
                );
            }

            return wp_send_json_success(
                array(
                    'user_id' => $created_user_id
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

    final public function edit_user($request) {
        $headers = apache_request_headers();
        $user_id = $headers['UserID'];
        if (!user_can($user_id, 'edit_users')) {
            return wp_send_json_error(
                array(
                    'message' => 'No permission'
                ),
                401
            );
        }

        try {
            $body = json_decode($request->get_body());
            $update_user_id = $request->get_param('user_id');

            $default = array(
                'ID' => $update_user_id
            );

            $update_data = (array)array_merge((array)$default, (array)$body);
            
            $update_result = wp_update_user($update_data);

            if (is_wp_error($update_result)) {
                return wp_send_json_error(
                    array(
                        'message' => $update_result->get_error_message($update_result->get_error_code())
                    ),
                    $update_result->get_error_code()
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

    final public function disable_user($request) {
        $headers = apache_request_headers();
        $user_id = $headers['UserID'];
        if (!user_can($user_id, 'edit_users')) {
            return wp_send_json_error(
                array(
                    'message' => 'No permission'
                ),
                401
            );
        }

        
    }

    final public function delete_user($request) {}

}