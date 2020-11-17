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
			$posts[] = $post;
		}

		return wp_send_json( array(
            'success' => true,
            'data' => $posts
        ), 200 );
	}
}