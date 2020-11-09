<?php

class Option_Controller {

    public function add_option($request) {
        try {
            $headers = apache_request_headers();
            $auth_cookie = $headers['Authorization'];
            $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

            $user = get_user_by('login', $cookies['username']);
            if (!user_can($user->ID, 'manage_options')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }

            $body = json_decode($request->get_body());

            if (!isset($body) || !isset($body->option) || !isset($body->value)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid request'
                    ),
                    400
                );
            }

            $result = add_option($body->option, $body->value, $body->description, false);

            if (!$result) {
                return wp_send_json_error(
                    array(
                        'message' => 'Option already exists'
                    ),
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

    public function update_option($request) {
        try {
            $headers = apache_request_headers();
            $auth_cookie = $headers['Authorization'];
            $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

            $user = get_user_by('login', $cookies['username']);
            if (!user_can($user->ID, 'manage_options')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }

            $body = json_decode($request->get_body());

            if (!isset($body) || !isset($body->option) || !isset($body->value)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid request'
                    ),
                    400
                );
            }

            $result = update_option($body->option, $body->value);

            if (!$result) {
                return wp_send_json_error(
                    array(
                        'message' => 'Update failed'
                    ),
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

    public function get_option($request) {
        try {
            $headers = apache_request_headers();
            $auth_cookie = $headers['Authorization'];
            $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

            $user = get_user_by('login', $cookies['username']);
            if (!user_can($user->ID, 'manage_options')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }
            $params = (object)$request->get_query_params();

            if (!isset($params) || !isset($params->option)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid request'
                    ),
                    400
                );
            }

            $result = get_option($params->option);

            if (is_bool($result)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Option does not exists'
                    ),
                    400
                );
            }

            $response = array();
            $response[$params->option] = $result;

            return wp_send_json_success(
                $response,
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

    public function delete_option($request) {
        try {
            $headers = apache_request_headers();
            $auth_cookie = $headers['Authorization'];
            $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

            $user = get_user_by('login', $cookies['username']);
            if (!user_can($user->ID, 'manage_options')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }

            $body = json_decode($request->get_body());

            if (!isset($body) || !isset($body->option)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid request'
                    ),
                    400
                );
            }

            $result = delete_option($body->option);

            if (!$result) {
                return wp_send_json_error(
                    array(
                        'message' => 'Update failed'
                    ),
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

}