<?php

class Post_Controller {

    public function get_posts($request) {
		$controller = new WP_REST_Posts_Controller($request['post_type']);
		if ( 'edit' === $request['context']) {
			$headers = apache_request_headers();
			$auth_cookie = $headers['Authorization'];
			$error = new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit posts in this post type.' ),
				array( 'status' => rest_authorization_required_code() )
			);
			if(!isset($auth_cookie) || $auth_cookie == ''){
				return $error;
			}

			$cookies = wp_parse_auth_cookie($auth_cookie, 'auth');
			$user = get_user_by('login', $cookies['username']);
			wp_set_current_user($user->ID);
			
			if(! current_user_can( 'edit_posts' ) ) {
				return $error;
			}
		}

		$response = $controller->get_items($request);
		$posts = array();

		foreach ($response->data as $key => $value) {
			$post = array();
			$post['id'] = $value['id'];
			$post['title'] = $value['title']['raw'];
			$post['slug'] = $value['slug'];
			$post['author'] = array();
			$post['author']['id'] = $value['author'];
			$post['author']['display_name'] = get_the_author_meta('display_name', $value['author']);
			$post['categories'] = get_the_category($value['id']);
			$post['tags'] = get_the_tags($value['id']);
			$post['comments'] = get_comments(array('post_id' => $value['id']));
			$post['date'] = $value['date'];
			$post['status'] = $value['status'];
			$post['password'] = $value['password'];
			$post['ping_status'] = $value['ping_status'];
			$post['comment_status'] = $value['comment_status'];
			$post['sticky'] = $value['sticky'];
			$posts[] = $post;
		}

		return wp_send_json( array(
            'success' => true,
            'data' => $posts
        ), 200 );
	}

	public function update_post($request) {
		$controller = new WP_REST_Posts_Controller($request['post_type']);
		$result = $controller->update_item_permissions_check($request);
        if(is_wp_error($result)) {
            return wp_send_json_error(
                $result,
                400
            );
		}
		
		$response = $controller->update_item($request);

        return wp_send_json( array(
            'success' => true,
            'data' => $response->data
        ), 200 );
	}

	public function get_authors($request) {
		$args = array(
			'orderby'       => 'name',
			'order'         => 'ASC',
			'number'        => '',
			'optioncount'   => false,
			'exclude_admin' => false,
			'show_fullname' => true,
			'hide_empty'    => false,
			'feed'          => '',
			'feed_image'    => '',
			'feed_type'     => '',
			'echo'          => false,
			'style'         => 'list',
			'html'          => true,
			'exclude'       => '',
			'include'       => '',
		);

		$query_args = wp_array_slice_assoc( $args, array( 'orderby', 'order', 'number', 'exclude', 'include' ) );
		$query_args['fields'] = 'ids';
		$users_id = get_users( $query_args );
		$response = array();

		foreach ( $users_id as $user_id ) {
			$user = get_userdata( $user_id );
			if($user->roles[0] == 'subscriber') {
				continue;
			}
			if ( $args['exclude_admin'] && 'admin' === $user->display_name ) {
				continue;
			}
			if ( $args['show_fullname'] && $user->first_name && $user->last_name ) {
				$name = "$user->first_name $user->last_name";
			} else {
				$name = $user->display_name;
			}

			$response[] = array(
				'id' => $user_id,
				'name' => $name
			);
		}
		
		return wp_send_json( array(
            'success' => true,
            'data' => $response
        ), 200 );
	}

	public function get_statuses($request) {	
		
		$statuses = get_post_statuses();
		$response = array();

		foreach ( $statuses as $key => $value ) {
			$response[] = array(
				'key' => $key,
				'name' => $value
			);
		}
		return wp_send_json( array(
            'success' => true,
            'data' => $response
        ), 200 );
	}
}