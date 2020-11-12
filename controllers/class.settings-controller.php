<?php

class Settings_Controller {
    public function get_languages($request) {
          try {
            return wp_send_json_success(
                wp_dropdown_languages(),
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