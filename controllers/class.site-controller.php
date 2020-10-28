<?php

class Site_Controller {

    public function insert_site($request) {
        try {
            $headers = apache_request_headers();
            $user_id = $headers['UserID'];
            if (!user_can($user_id, 'create_sites')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        }
    }

    public function delete_site($request) {
        try {
            $headers = apache_request_headers();
            $user_id = $headers['UserID'];
            if (!user_can($user_id, 'delete_sites')) {
                return wp_send_json_error(
                    array(
                        'message' => 'No permission'
                    ),
                    401
                );
            }
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