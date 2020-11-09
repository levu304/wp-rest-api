<?php

class REST_controller extends WP_REST_Controller {

    public function get_rest_endpoint_args_for_item_schema($method) {
        return parent::get_endpoint_args_for_item_schema($method);
    }

    public function get_rest_public_item_schema() {
        return parent::get_public_item_schema();
    }
}