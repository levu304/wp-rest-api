<?php

class User_Controller{

    public static function create_users($request){
        $headers = apache_request_headers();
        $user_id = $headers['UserID'];
        if(!user_can($user_id, 'create_users')){
            return wp_send_json_error(
                array(
                    'message' => 'No permission'
                ),
                401
            );
        }

        
    }
}