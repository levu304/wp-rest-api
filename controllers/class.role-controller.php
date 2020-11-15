<?php

class Role_Controller {

    public function get_roles($request) {  
        try {
            if (!current_user_can('promote_users')) {
                return new WP_Error(
                    'rest_forbidden_context',
                    __( 'Sorry, you are not allowed to promote users' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }
            global $wp_roles;

            if (!isset($wp_roles)) {
                $wp_roles = new WP_Roles();
            }

            foreach ($wp_roles->role_objects as $key => $value) {
                $roles[] = $value;
            }

            return wp_send_json_success(
                $roles,
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

    public function get_role($request) {  
        try {
            if (!current_user_can('promote_users')) {
                return new WP_Error(
                    'rest_forbidden_context',
                    __( 'Sorry, you are not allowed to promote users' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }

            $role_name = $request->get_param('role_name');
            $role = get_role($role_name);

            if (!isset($role)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid role'
                    ),
                    400
                );
            }

            return wp_send_json_success(
                (array)$role,
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

    public function add_role($request) {
        try {
            if (!current_user_can('promote_users')) {
                return new WP_Error(
                    'rest_forbidden_context',
                    __( 'Sorry, you are not allowed to promote users' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }

            $body = json_decode($request->get_body());

            if (!isset($body) || !isset($body->role) || !isset($body->display_name)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid request'
                    ),
                    400
                );
            }

            $result = add_role($body->role, $body->display_name, $body->capabilities);

            if (!isset($result)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Role already exists'
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

    public function edit_role_capabilities($request) {
        try {
            if (!current_user_can('promote_users')) {
                return new WP_Error(
                    'rest_forbidden_context',
                    __( 'Sorry, you are not allowed to promote users' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }
            $body = json_decode($request->get_body());
            if (!isset($body)) {
                return wp_send_json_error(
                    array(
                        'message' => 'Invalid request'
                    ),
                    400
                );
            }
            $capabilities = array();
            if (isset($body->capabilities)) {
                $capabilities = $body->capabilities;
            }
            $role_name = $request->get_param('role_name');
            global $wp_roles;

            foreach ($capabilities as $key => $value) {
                add_cap($role_name, $key, $value);
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

    public function remove_role($request) {
        try {
            if (!current_user_can('promote_users')) {
                return new WP_Error(
                    'rest_forbidden_context',
                    __( 'Sorry, you are not allowed to promote users' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }

            $role_name = $request->get_param('role_name');
            remove_role($role_name);

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