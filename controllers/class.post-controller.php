<?php

class Post_Controller extends WP_REST_Posts_Controller {
    public function get_posts($request) {
        $headers = apache_request_headers();
        $auth_cookie = $headers['Authorization'];
        $cookies = wp_parse_auth_cookie($auth_cookie, 'auth');

        $user = get_user_by('login', $cookies['username']);
        $params = (array)$request->get_query_params();

        $post_type = get_post_type_object( "post" );

		if ( 'edit' === $params['context'] && ! user_can( $user->ID, $post_type->cap->edit_posts ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit posts in this post type.' ), array( 'status' => rest_authorization_required_code() ) );
        }
        
        // Ensure a search string is set in case the orderby is set to 'relevance'.
		if ( ! empty( $params['orderby'] ) && 'relevance' === $params['orderby'] && empty( $params['search'] ) ) {
			return new WP_Error( 'rest_no_search_term_defined', __( 'You need to define a search term to order by relevance.' ), array( 'status' => 400 ) );
        }
        
        // Ensure an include parameter is set in case the orderby is set to 'include'.
		if ( ! empty( $params['orderby'] ) && 'include' === $params['orderby'] && empty( $params['include'] ) ) {
			return new WP_Error( 'rest_orderby_include_missing_include', __( 'You need to define an include parameter to order by include.' ), array( 'status' => 400 ) );
        }
        
        $controller = new WP_REST_Posts_Controller('post');
        // Retrieve the list of registered collection query parameters.
		$registered = $controller->get_collection_params();
        $args       = array();
        
        
		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'menu_order'     => 'menu_order',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
        );
        
        /*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $params[ $api_param ] ) ) {
				$args[ $wp_param ] = $params[ $api_param ];
			}
        }
        
        // Check for & assign any parameters which require special handling or setting.
		$args['date_query'] = array();

		// Set before into date query. Date query must be specified as an array of an array.
		if ( isset( $registered['before'], $params['before'] ) ) {
			$args['date_query'][0]['before'] = $params['before'];
        }
        
        // Set after into date query. Date query must be specified as an array of an array.
		if ( isset( $registered['after'], $params['after'] ) ) {
			$args['date_query'][0]['after'] = $params['after'];
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $params['per_page'];
        }
        
        if ( isset( $registered['sticky'], $params['sticky'] ) ) {
            $sticky_posts = get_option( 'sticky_posts', array() );
			if ( ! is_array( $sticky_posts ) ) {
				$sticky_posts = array();
            }
            
            if ( $params['sticky'] ) {
				/*
				 * As post__in will be used to only get sticky posts,
				 * we have to support the case where post__in was already
				 * specified.
				 */
				$args['post__in'] = $args['post__in'] ? array_intersect( $sticky_posts, $args['post__in'] ) : $sticky_posts;

				/*
				 * If we intersected, but there are no post ids in common,
				 * WP_Query won't return "no posts" for post__in = array()
				 * so we have to fake it a bit.
				 */
				if ( ! $args['post__in'] ) {
					$args['post__in'] = array( 0 );
				}
			} elseif ( $sticky_posts ) {
				/*
				 * As post___not_in will be used to only get posts that
				 * are not sticky, we have to support the case where post__not_in
				 * was already specified.
				 */
				$args['post__not_in'] = array_merge( $args['post__not_in'], $sticky_posts );
			}
        }

        // Force the post_type argument, since it's not a user input variable.
        $args['post_type'] = 'post';
        
        /**
		 * Filters the query arguments for a request.
		 *
		 * Enables adding extra arguments or setting defaults for a post collection request.
		 *
		 * @since 4.7.0
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_query/
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 */
		$args       = apply_filters( "rest_post_query", $args, $request );
		$query_args = $controller->prepare_items_query( $args, $request );
        $taxonomies = wp_list_filter( get_object_taxonomies( 'post', 'objects' ), array( 'show_in_rest' => true ) );

        foreach ( $taxonomies as $taxonomy ) {
			$base        = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;
			$tax_exclude = $base . '_exclude';

			if ( ! empty( $params[ $base ] ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy'         => $taxonomy->name,
					'field'            => 'term_id',
					'terms'            => $params[ $base ],
					'include_children' => false,
				);
			}

			if ( ! empty( $params[ $tax_exclude ] ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy'         => $taxonomy->name,
					'field'            => 'term_id',
					'terms'            => $params[ $tax_exclude ],
					'include_children' => false,
					'operator'         => 'NOT IN',
				);
			}
        }
        
        $posts_query  = new WP_Query();
        $query_result = $posts_query->query( $query_args );
        
        // Allow access to all password protected posts if the context is edit.
		if ( 'edit' === $params['context'] ) {
			add_filter( 'post_password_required', '__return_false' );
		}

        $posts = array();
        
        foreach ( $query_result as $post ) {
			// if ( ! $controller->check_read_permission( $post ) ) {
			// 	continue;
			// }

			// $data    = $controller->prepare_item_for_response( $post, $request );
            // $posts[] = $controller->prepare_response_for_collection( $data );
            
            $posts[] = $post;
        }
        
        // Reset filter.
		if ( 'edit' === $params['context'] ) {
			remove_filter( 'post_password_required', '__return_false' );
		}

		$page        = (int) $query_args['paged'];
		$total_posts = $posts_query->found_posts;

		if ( $total_posts < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $query_args['paged'] );

			$count_query = new WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
        }
        
        $max_pages = ceil( $total_posts / (int) $posts_query->query_vars['posts_per_page'] );

		if ( $page > $max_pages && $total_posts > 0 ) {
			return new WP_Error( 'rest_post_invalid_page_number', __( 'The page number requested is larger than the number of pages available.' ), array( 'status' => 400 ) );
        }
        
        $response = rest_ensure_response( $posts );
        $base           = add_query_arg( urlencode_deep( $params ), rest_url( sprintf( '%s/%s', 'api/v1', 'posts' ) ) );
        
        if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;

    }

    // public function get_collection_params() {
    //     $controller = new WP_REST_Posts_Controller;
    //     return $controller->get_collection_params();
    // }
    
}