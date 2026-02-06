<?php
/**
 * Simplified webhook handler for ClickRank.ai optimization requests.
 * Streamlined for maximum efficiency and simplicity while maintaining security.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/api
 */
class ClickRank_AI_Webhook_Handler {

	private $rate_limit_requests = 1000;
	private $rate_limit_window = 3600;

	public function register_routes() {
		register_rest_route( 'clickrank-ai/v1', '/update-post', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_request' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	public function permissions_check( WP_REST_Request $request ) {
		// Rate limiting
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 'rate_limit_exceeded', 'Rate limit exceeded', [ 'status' => 429 ] );
		}

		// API key validation
		$api_key = get_option( 'clickrank_ai_api_key' );
		if ( empty( $api_key ) ) {
			ClickRank_AI_Logger::warning( 'Webhook blocked: No API key configured' );
			return new WP_Error( 'no_api_key', 'API key not configured', [ 'status' => 401 ] );
		}

		$auth_header = $request->get_header( 'authorization' );
		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			ClickRank_AI_Logger::warning( 'Webhook blocked: Invalid authorization' );
			return new WP_Error( 'invalid_auth', 'Invalid authorization', [ 'status' => 401 ] );
		}

		if ( ! hash_equals( $api_key, sanitize_text_field( $matches[1] ) ) ) {
			ClickRank_AI_Logger::warning( 'Webhook blocked: Invalid API key from IP: ' . $this->get_client_ip() );
			return new WP_Error( 'invalid_key', 'Invalid API key', [ 'status' => 403 ] );
		}

		return true;
	}

	public function handle_request( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) || empty( $params['page_url'] ) ) {
			return $this->error_response( 'Invalid request data', 400 );
		}

		$params = $this->sanitize_params( $params );
		$page_url = $params['page_url'];

		ClickRank_AI_Logger::info( 'Processing webhook for: ' . $page_url );

		// Handle revert action
		if ( isset( $params['action'] ) && $params['action'] === 'revert' ) {
			return $this->handle_revert( $params );
		}

		// PHASE 1+2: ALWAYS save to URL table first (regardless of post resolution)
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';
		$url_data = [];
		if ( ! empty( $params['page_title'] ) ) {
			$url_data['page_title'] = $params['page_title'];
		}
		if ( ! empty( $params['meta_description'] ) ) {
			$url_data['meta_description'] = $params['meta_description'];
		}
		if ( ! empty( $params['page_schema'] ) ) {
			$url_data['page_schema'] = $params['page_schema'];
		}
		if ( ! empty( $params['canonical_url'] ) ) {
			$url_data['canonical_url'] = $params['canonical_url'];
		}

		$url_saved = false;
		if ( ! empty( $url_data ) ) {
			$url_saved = ClickRank_AI_SEO_Data_Manager::save_seo_data( $page_url, $url_data, true );
			if ( $url_saved ) {
				ClickRank_AI_Logger::info( "SEO data saved to URL table for: {$page_url}" );
			}
		}

		// Try to resolve content for post meta backup (optional)
		$content = $this->resolve_content( $page_url );

		switch ( $content['type'] ) {
			case 'homepage':
				return $this->update_homepage( $params );
			case 'post':
				return $this->update_post( $content['id'], $params );
			case 'taxonomy':
				return $this->update_taxonomy( $content, $params );
			default:
				// Content not found BUT URL table was updated
				if ( $url_saved ) {
					ClickRank_AI_Logger::info( "URL stored in table but post not found: {$page_url}" );
					return $this->success_response( 'SEO data saved to URL table (post not resolved)', [ 'url_table_only' => true ] );
				}

				// Try image-only update if no content found
				if ( ! empty( $params['image_optimizations'] ) ) {
					return $this->update_images_only( $params );
				}

				return $this->error_response( 'Content not found and no data to save', 404 );
		}
	}

	/**
	 * Unified content resolution - handles all URL types in one simple function
	 */
	private function resolve_content( $url ) {
		$url_path = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
		
		// Homepage check
		if ( rtrim( $url_path, '/' ) === rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?: '', '/' ) ) {
			return [ 'type' => 'homepage' ];
		}

		// Try to find post by URL - WordPress handles permalink structures
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			return [ 'type' => 'post', 'id' => $post_id ];
		}

		// Try direct slug lookup for posts
		$slug = basename( rtrim( $url_path, '/' ) );
		if ( $slug ) {
			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1",
				$slug
			) );
			if ( $post_id ) {
				return [ 'type' => 'post', 'id' => (int) $post_id ];
			}
		}

		// Try taxonomy - handle different permalink structures
		$path_parts = array_filter( explode( '/', trim( $url_path, '/' ) ) );
		
		// Try different taxonomy patterns
		if ( ! empty( $path_parts ) ) {
			$slug = end( $path_parts ); // Get the last part as potential term slug
			
			// Check all public taxonomies for this slug
			$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
			foreach ( $taxonomies as $taxonomy ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					ClickRank_AI_Logger::add( 'INFO', "Found taxonomy term: {$term->name} (ID: {$term->term_id}) in taxonomy: {$taxonomy}" );
					return [ 'type' => 'taxonomy', 'id' => $term->term_id, 'taxonomy' => $taxonomy, 'term' => $term ];
				}
			}
			
			// Also try with traditional /category/slug format
			if ( count( $path_parts ) >= 2 ) {
				$taxonomy = $path_parts[0] === 'category' ? 'category' : $path_parts[0];
				$term_slug = $path_parts[1];
				
				$term = get_term_by( 'slug', $term_slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					ClickRank_AI_Logger::add( 'INFO', "Found taxonomy term via traditional format: {$term->name} (ID: {$term->term_id}) in taxonomy: {$taxonomy}" );
					return [ 'type' => 'taxonomy', 'id' => $term->term_id, 'taxonomy' => $taxonomy, 'term' => $term ];
				}
			}
		}

		return [ 'type' => 'unknown' ];
	}

	/**
	 * Update homepage settings
	 */
	private function update_homepage( $params ) {
		$compat = new ClickRank_AI_SEO_Compat();
		$updated = [];

		// PHASE 1: Write to URL-based table (new system)
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';
		$url_data = [];
		if ( ! empty( $params['page_title'] ) || ! empty( $params['title'] ) || ! empty( $params['site_title'] ) ) {
			$url_data['page_title'] = $params['page_title'] ?? $params['title'] ?? $params['site_title'] ?? '';
		}
		if ( ! empty( $params['meta_description'] ) || ! empty( $params['description'] ) ) {
			$url_data['meta_description'] = $params['meta_description'] ?? $params['description'] ?? '';
		}
		if ( ! empty( $params['page_schema'] ) ) {
			$url_data['page_schema'] = $params['page_schema'];
		}
		if ( ! empty( $params['canonical_url'] ) ) {
			$url_data['canonical_url'] = $params['canonical_url'];
		}
		if ( ! empty( $url_data ) ) {
			ClickRank_AI_SEO_Data_Manager::save_seo_data( home_url(), $url_data, true );
			ClickRank_AI_Logger::info( 'Homepage SEO data saved to URL-based table' );
		}

		// Save revert data before making changes
		$revert_data = [
			'page_title' => get_option( '_clickrank_ai_homepage_title', '' ),
			'meta_description' => get_option( '_clickrank_ai_homepage_description', '' ),
			'page_schema' => get_option( '_clickrank_ai_homepage_schema', '' ),
			'canonical_url' => get_option( '_clickrank_ai_homepage_canonical', '' )
		];
		update_option( '_clickrank_ai_homepage_revert_data', $revert_data );

		// Log what parameters we received for debugging
		$received_params = array_keys( $params );
		ClickRank_AI_Logger::add( 'INFO', "Homepage update received parameters: " . implode( ', ', $received_params ) );
		
		// Log settings status for debugging
		$title_enabled = get_option( 'clickrank_ai_enable_title_opt' );
		$meta_enabled = get_option( 'clickrank_ai_enable_meta_opt' );
		ClickRank_AI_Logger::add( 'INFO', "Homepage settings - Title optimization: " . ($title_enabled ? 'enabled' : 'disabled') . ", Meta optimization: " . ($meta_enabled ? 'enabled' : 'disabled') );

		// Title (check multiple possible parameter names)
		$title_param = $params['page_title'] ?? $params['title'] ?? $params['site_title'] ?? '';
		if ( get_option( 'clickrank_ai_enable_title_opt' ) && ! empty( $title_param ) ) {
			$compat->update_homepage_title( $title_param );
			$updated[] = 'title';
			ClickRank_AI_Logger::add( 'INFO', "Updated homepage title: {$title_param}" );
		}

		// Description (check multiple possible parameter names)
		$description_param = $params['meta_description'] ?? $params['description'] ?? '';
		if ( get_option( 'clickrank_ai_enable_meta_opt' ) && ! empty( $description_param ) ) {
			$compat->update_homepage_description( $description_param );
			$updated[] = 'description';
			ClickRank_AI_Logger::add( 'INFO', "Updated homepage description: {$description_param}" );
		}

		// Schema
		if ( get_option( 'clickrank_ai_enable_schema_opt' ) && ! empty( $params['page_schema'] ) ) {
			update_option( '_clickrank_ai_homepage_schema', wp_kses_post( $params['page_schema'] ) );
			$updated[] = 'schema';
		}

		// Canonical URL
		if ( get_option( 'clickrank_ai_enable_canonical_opt' ) && ! empty( $params['canonical_url'] ) ) {
			update_option( '_clickrank_ai_homepage_canonical', esc_url_raw( $params['canonical_url'] ) );
			$updated[] = 'canonical';
		}

		return $this->success_response( 'Homepage updated', [ 'fields' => $updated ] );
	}

	/**
	 * Update post/page content
	 */
	private function update_post( $post_id, $params ) {
		if ( ! $this->is_post_accessible( $post_id ) ) {
			return $this->error_response( 'Post not found or not accessible', 404 );
		}

		$compat = new ClickRank_AI_SEO_Compat();
		$updated = [];
		$revert_data = [];

		// PHASE 1: Write to URL-based table (new system)
		// IMPORTANT: Use the original page_url from webhook, NOT get_permalink()
		// This prevents duplicate entries for translated URLs (/it/, /fr/, etc.)
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';
		$url_data = [];
		if ( ! empty( $params['page_title'] ) ) {
			$url_data['page_title'] = $params['page_title'];
		}
		if ( ! empty( $params['meta_description'] ) ) {
			$url_data['meta_description'] = $params['meta_description'];
		}
		if ( ! empty( $params['page_schema'] ) ) {
			$url_data['page_schema'] = $params['page_schema'];
		}
		if ( ! empty( $params['canonical_url'] ) ) {
			$url_data['canonical_url'] = $params['canonical_url'];
		}
		if ( ! empty( $url_data ) && ! empty( $params['page_url'] ) ) {
			// Use the ORIGINAL page_url from webhook to avoid duplicates
			$url_data['post_id'] = $post_id; // Store post ID for reference
			ClickRank_AI_SEO_Data_Manager::save_seo_data( $params['page_url'], $url_data, true );
			ClickRank_AI_Logger::info( "Post {$post_id} SEO data saved to URL-based table for: {$params['page_url']}" );
		}

		// Title
		if ( get_option( 'clickrank_ai_enable_title_opt' ) && ! empty( $params['page_title'] ) ) {
			$meta_key = $compat->get_seo_meta_key( 'title' );
			$revert_data['page_title'] = get_post_meta( $post_id, $meta_key, true );
			update_post_meta( $post_id, $meta_key, $params['page_title'] );
			$updated[] = 'title';
		}

		// Meta description
		if ( get_option( 'clickrank_ai_enable_meta_opt' ) && ! empty( $params['meta_description'] ) ) {
			$meta_key = $compat->get_seo_meta_key( 'description' );
			$revert_data['meta_description'] = get_post_meta( $post_id, $meta_key, true );
			update_post_meta( $post_id, $meta_key, $params['meta_description'] );
			$updated[] = 'description';
		}

		// Canonical URL
		if ( get_option( 'clickrank_ai_enable_canonical_opt' ) && ! empty( $params['canonical_url'] ) ) {
			$revert_data['canonical_url'] = get_post_meta( $post_id, '_clickrank_ai_canonical_url', true );
			update_post_meta( $post_id, '_clickrank_ai_canonical_url', $params['canonical_url'] );
			$updated[] = 'canonical';
		}

		// Schema
		if ( get_option( 'clickrank_ai_enable_schema_opt' ) && ! empty( $params['page_schema'] ) ) {
			$revert_data['page_schema'] = get_post_meta( $post_id, '_clickrank_ai_page_schema', true );
			update_post_meta( $post_id, '_clickrank_ai_page_schema', wp_kses_post( $params['page_schema'] ) );
			$updated[] = 'schema';
		}

		// Images
		if ( get_option( 'clickrank_ai_enable_img_alt_opt' ) && ! empty( $params['image_optimizations'] ) ) {
			$image_results = $this->update_images( $params['image_optimizations'] );
			if ( ! empty( $image_results['updated'] ) ) {
				$revert_data['image_optimizations'] = $image_results['revert_data'];
				$updated[] = 'images';
			}
		}

		// Link titles
		if ( get_option( 'clickrank_ai_enable_link_title_opt' ) && ! empty( $params['link_titles'] ) ) {
			$revert_data['link_titles'] = get_post_meta( $post_id, '_clickrank_ai_link_titles', true );
			update_post_meta( $post_id, '_clickrank_ai_link_titles', $params['link_titles'] );
			$updated[] = 'links';
		}

		// Save revert data
		if ( ! empty( $revert_data ) ) {
			update_post_meta( $post_id, '_clickrank_ai_revert_data', $revert_data );
		}

		return empty( $updated ) 
			? $this->error_response( 'No updates applied', 400 )
			: $this->success_response( "Post {$post_id} updated", [ 'fields' => $updated ] );
	}

	/**
	 * Update taxonomy term
	 */
	private function update_taxonomy( $content, $params ) {
		$term_id = $content['id'];
		$taxonomy = $content['taxonomy'];
		$compat = new ClickRank_AI_SEO_Compat();
		$updated = [];
		$revert_data = [];

		// PHASE 1: Write to URL-based table (new system)
		// IMPORTANT: Use the original page_url from webhook, NOT get_term_link()
		// This prevents duplicate entries for translated URLs (/it/, /fr/, etc.)
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';
		$url_data = [];
		$title_param = $params['term_name'] ?? $params['page_title'] ?? $params['title'] ?? '';
		if ( ! empty( $title_param ) ) {
			$url_data['page_title'] = $title_param;
		}
		$description_param = $params['meta_description'] ?? $params['description'] ?? '';
		if ( ! empty( $description_param ) ) {
			$url_data['meta_description'] = $description_param;
		}
		if ( ! empty( $url_data ) && ! empty( $params['page_url'] ) ) {
			// Use the ORIGINAL page_url from webhook to avoid duplicates
			ClickRank_AI_SEO_Data_Manager::save_seo_data( $params['page_url'], $url_data, true );
			ClickRank_AI_Logger::info( "Taxonomy term {$term_id} SEO data saved to URL-based table for: {$params['page_url']}" );
		}

		// Term title (check multiple possible parameter names)
		$title_param = $params['term_name'] ?? $params['page_title'] ?? $params['title'] ?? '';
		if ( get_option( 'clickrank_ai_enable_title_opt' ) && ! empty( $title_param ) ) {
			$revert_data['term_name'] = $content['term']->name;
			$result = wp_update_term( $term_id, $taxonomy, [ 'name' => $title_param ] );
			if ( ! is_wp_error( $result ) ) {
				$updated[] = 'name';
				ClickRank_AI_Logger::add( 'INFO', "Updated taxonomy term name: {$title_param}" );
			}
		}

		// Meta description (check multiple possible parameter names)
		$description_param = $params['meta_description'] ?? $params['description'] ?? '';
		if ( get_option( 'clickrank_ai_enable_meta_opt' ) && ! empty( $description_param ) ) {
			$meta_key = $compat->get_taxonomy_meta_key( 'description', $taxonomy );
			$revert_data['meta_description'] = get_term_meta( $term_id, $meta_key, true );
			update_term_meta( $term_id, $meta_key, $description_param );
			$updated[] = 'description';
			ClickRank_AI_Logger::add( 'INFO', "Updated taxonomy meta description for term ID: {$term_id}" );
		}

		// Log what parameters we received for debugging
		$received_params = array_keys( $params );
		ClickRank_AI_Logger::add( 'INFO', "Taxonomy update received parameters: " . implode( ', ', $received_params ) );
		
		// Log settings status for debugging
		$title_enabled = get_option( 'clickrank_ai_enable_title_opt' );
		$meta_enabled = get_option( 'clickrank_ai_enable_meta_opt' );
		ClickRank_AI_Logger::add( 'INFO', "Settings - Title optimization: " . ($title_enabled ? 'enabled' : 'disabled') . ", Meta optimization: " . ($meta_enabled ? 'enabled' : 'disabled') );

		// Save revert data
		if ( ! empty( $revert_data ) ) {
			update_term_meta( $term_id, '_clickrank_ai_revert_data', $revert_data );
		}

		return empty( $updated )
			? $this->error_response( 'No updates applied', 400 )
			: $this->success_response( "Term {$term_id} updated", [ 'fields' => $updated ] );
	}

	/**
	 * Handle revert requests
	 */
	private function handle_revert( $params ) {
		$page_url = $params['page_url'];

		// PHASE 2 FIX: Revert from URL table first
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';

		$fields = $params['fields'] ?? [];
		$url_reverted = ClickRank_AI_SEO_Data_Manager::revert_seo_data( $page_url, $fields );

		if ( $url_reverted ) {
			ClickRank_AI_Logger::info( "Reverted SEO data from URL table for: {$page_url}" );
		}

		// Also try to revert post meta (optional, for backward compatibility)
		$content = $this->resolve_content( $page_url );

		if ( $content['type'] === 'homepage' ) {
			return $this->revert_homepage( $params );
		}

		if ( $content['type'] === 'post' ) {
			return $this->revert_post( $content['id'], $params );
		}

		if ( $content['type'] === 'taxonomy' ) {
			return $this->revert_taxonomy( $content, $params );
		}

		// If post meta revert failed but URL table revert worked
		if ( $url_reverted ) {
			return $this->success_response( 'Reverted from URL table (post not resolved)', [ 'url_table_only' => true ] );
		}

		return $this->error_response( 'Cannot revert: content not found', 404 );
	}

	/**
	 * Revert homepage changes
	 */
	private function revert_homepage( $params ) {
		$revert_data = get_option( '_clickrank_ai_homepage_revert_data', [] );
		
		if ( empty( $revert_data ) ) {
			// If no revert data, clear all homepage settings
			delete_option( '_clickrank_ai_homepage_title' );
			delete_option( '_clickrank_ai_homepage_description' );
			delete_option( '_clickrank_ai_homepage_schema' );
			delete_option( '_clickrank_ai_homepage_canonical' );
			
			// No need to call update methods with empty strings - deleting options is enough
			
			return $this->success_response( 'Homepage reverted to default (no backup found)' );
		}

		$compat = new ClickRank_AI_SEO_Compat();
		$reverted = [];
		$fields = $params['fields'] ?? array_keys( $revert_data );

		foreach ( $fields as $field ) {
			if ( ! isset( $revert_data[ $field ] ) ) {
				continue;
			}

			switch ( $field ) {
				case 'page_title':
					$compat->update_homepage_title( $revert_data[ $field ] );
					$reverted[] = 'title';
					break;
				case 'meta_description':
					$compat->update_homepage_description( $revert_data[ $field ] );
					$reverted[] = 'description';
					break;
				case 'page_schema':
					update_option( '_clickrank_ai_homepage_schema', $revert_data[ $field ] );
					$reverted[] = 'schema';
					break;
				case 'canonical_url':
					update_option( '_clickrank_ai_homepage_canonical', $revert_data[ $field ] );
					$reverted[] = 'canonical';
					break;
			}
		}

		// Clean up revert data
		delete_option( '_clickrank_ai_homepage_revert_data' );

		return $this->success_response( 'Homepage reverted', [ 'fields' => $reverted ] );
	}

	/**
	 * Revert post changes
	 */
	private function revert_post( $post_id, $params ) {
		$revert_data = get_post_meta( $post_id, '_clickrank_ai_revert_data', true );
		if ( empty( $revert_data ) ) {
			return $this->error_response( 'No revert data found', 404 );
		}

		$compat = new ClickRank_AI_SEO_Compat();
		$reverted = [];
		$fields = $params['fields'] ?? array_keys( $revert_data );

		foreach ( $fields as $field ) {
			$field_key = ( $field === 'post_title' ) ? 'page_title' : $field;
			
			if ( ! isset( $revert_data[ $field_key ] ) ) {
				continue;
			}

			switch ( $field_key ) {
				case 'page_title':
					$meta_key = $compat->get_seo_meta_key( 'title' );
					$this->update_or_delete_meta( $post_id, $meta_key, $revert_data[ $field_key ] );
					$reverted[] = 'title';
					break;
				case 'meta_description':
					$meta_key = $compat->get_seo_meta_key( 'description' );
					$this->update_or_delete_meta( $post_id, $meta_key, $revert_data[ $field_key ] );
					$reverted[] = 'description';
					break;
				case 'canonical_url':
					$this->update_or_delete_meta( $post_id, '_clickrank_ai_canonical_url', $revert_data[ $field_key ] );
					$reverted[] = 'canonical';
					break;
				case 'page_schema':
					$this->update_or_delete_meta( $post_id, '_clickrank_ai_page_schema', $revert_data[ $field_key ] );
					$reverted[] = 'schema';
					break;
				case 'image_optimizations':
					if ( is_array( $revert_data[ $field_key ] ) ) {
						$this->revert_images( $revert_data[ $field_key ] );
						$reverted[] = 'images';
					}
					break;
			}
		}

		return empty( $reverted )
			? $this->error_response( 'No fields reverted', 400 )
			: $this->success_response( "Post {$post_id} reverted", [ 'fields' => $reverted ] );
	}

	/**
	 * Revert taxonomy changes
	 */
	private function revert_taxonomy( $content, $params ) {
		$term_id = $content['id'];
		$revert_data = get_term_meta( $term_id, '_clickrank_ai_revert_data', true );
		if ( empty( $revert_data ) ) {
			return $this->error_response( 'No revert data found', 404 );
		}

		$reverted = [];
		$fields = $params['fields'] ?? array_keys( $revert_data );

		foreach ( $fields as $field ) {
			if ( ! isset( $revert_data[ $field ] ) ) {
				continue;
			}

			if ( $field === 'term_name' && ! empty( $revert_data[ $field ] ) ) {
				wp_update_term( $term_id, $content['taxonomy'], [ 'name' => $revert_data[ $field ] ] );
				$reverted[] = 'name';
			}
		}

		return empty( $reverted )
			? $this->error_response( 'No fields reverted', 400 )
			: $this->success_response( "Term {$term_id} reverted", [ 'fields' => $reverted ] );
	}

	/**
	 * Update images only (when no content is found)
	 */
	private function update_images_only( $params ) {
		if ( ! get_option( 'clickrank_ai_enable_img_alt_opt' ) || empty( $params['image_optimizations'] ) ) {
			return $this->error_response( 'Image optimization not enabled or no images', 400 );
		}

		$results = $this->update_images( $params['image_optimizations'] );
		return $results['updated'] > 0 
			? $this->success_response( 'Images updated', [ 'count' => $results['updated'] ] )
			: $this->error_response( 'No images updated', 400 );
	}

	/**
	 * Update images and return results
	 */
	private function update_images( $optimizations ) {
		$updated = 0;
		$revert_data = [];

		foreach ( $optimizations as $opt ) {
			if ( empty( $opt['image_url'] ) ) {
				continue;
			}

			$attachment_id = attachment_url_to_postid( $opt['image_url'] );
			if ( ! $attachment_id ) {
				continue;
			}

			// Store original data
			$revert_data[] = [
				'image_url' => $opt['image_url'],
				'original_alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'original_title' => get_the_title( $attachment_id )
			];

			// Update alt text
			if ( isset( $opt['new_alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $opt['new_alt_text'] );
				$updated++;
			}

			// Update title
			if ( isset( $opt['new_title'] ) ) {
				wp_update_post( [ 'ID' => $attachment_id, 'post_title' => $opt['new_title'] ] );
				$updated++;
			}
		}

		return [ 'updated' => $updated, 'revert_data' => $revert_data ];
	}

	/**
	 * Revert image changes
	 */
	private function revert_images( $image_data ) {
		foreach ( $image_data as $data ) {
			$attachment_id = attachment_url_to_postid( $data['image_url'] );
			if ( $attachment_id ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $data['original_alt'] );
				wp_update_post( [ 'ID' => $attachment_id, 'post_title' => $data['original_title'] ] );
			}
		}
	}

	/**
	 * Sanitize webhook parameters
	 */
	private function sanitize_params( $params ) {
		$sanitized = [];
		
		// Required field
		$sanitized['page_url'] = esc_url_raw( $params['page_url'] );
		
		// Optional fields
		if ( isset( $params['action'] ) ) {
			$sanitized['action'] = sanitize_key( $params['action'] );
		}
		if ( isset( $params['page_title'] ) ) {
			$sanitized['page_title'] = sanitize_text_field( $params['page_title'] );
		}
		if ( isset( $params['meta_description'] ) ) {
			$sanitized['meta_description'] = sanitize_textarea_field( $params['meta_description'] );
		}
		if ( isset( $params['canonical_url'] ) ) {
			$sanitized['canonical_url'] = esc_url_raw( $params['canonical_url'] );
		}
		if ( isset( $params['page_schema'] ) ) {
			$sanitized['page_schema'] = wp_kses_post( $params['page_schema'] );
		}
		if ( isset( $params['term_name'] ) ) {
			$sanitized['term_name'] = sanitize_text_field( $params['term_name'] );
		}
		if ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
			$sanitized['fields'] = array_map( 'sanitize_key', $params['fields'] );
		}
		if ( isset( $params['image_optimizations'] ) && is_array( $params['image_optimizations'] ) ) {
			$sanitized['image_optimizations'] = $this->sanitize_images( $params['image_optimizations'] );
		}
		if ( isset( $params['link_titles'] ) && is_array( $params['link_titles'] ) ) {
			$sanitized['link_titles'] = $this->sanitize_link_titles( $params['link_titles'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize image optimization data
	 */
	private function sanitize_images( $images ) {
		$sanitized = [];
		foreach ( $images as $img ) {
			if ( empty( $img['image_url'] ) ) {
				continue;
			}
			$clean = [ 'image_url' => esc_url_raw( $img['image_url'] ) ];
			if ( isset( $img['new_alt_text'] ) ) {
				$clean['new_alt_text'] = sanitize_text_field( $img['new_alt_text'] );
			}
			if ( isset( $img['new_title'] ) ) {
				$clean['new_title'] = sanitize_text_field( $img['new_title'] );
			}
			$sanitized[] = $clean;
		}
		return $sanitized;
	}

	/**
	 * Sanitize link titles
	 */
	private function sanitize_link_titles( $links ) {
		$sanitized = [];
		foreach ( $links as $url => $title ) {
			$clean_url = esc_url_raw( $url );
			$clean_title = sanitize_text_field( $title );
			if ( $clean_url && $clean_title ) {
				$sanitized[ $clean_url ] = $clean_title;
			}
		}
		return $sanitized;
	}

	/**
	 * Check if post is accessible
	 */
	private function is_post_accessible( $post_id ) {
		$post = get_post( $post_id );
		return $post && $post->post_status === 'publish';
	}

	/**
	 * Update or delete meta based on value
	 */
	private function update_or_delete_meta( $post_id, $meta_key, $value ) {
		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Rate limiting check
	 */
	private function check_rate_limit() {
		$ip = $this->get_client_ip();
		$key = 'clickrank_ai_rate_limit_' . md5( $ip );
		$count = (int) get_transient( $key );
		
		if ( $count >= $this->rate_limit_requests ) {
			return false;
		}
		
		set_transient( $key, $count + 1, $this->rate_limit_window );
		return true;
	}

	/**
	 * Get client IP
	 */
	private function get_client_ip() {
		$headers = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ips = explode( ',', $_SERVER[ $header ] );
				$ip = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}
		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}

	/**
	 * Success response helper
	 */
	private function success_response( $message, $data = [] ) {
		ClickRank_AI_Logger::info( $message );
		return new WP_REST_Response( array_merge( [ 'success' => true, 'message' => $message ], $data ), 200 );
	}

	/**
	 * Error response helper
	 */
	private function error_response( $message, $status = 400 ) {
		ClickRank_AI_Logger::warning( $message );
		return new WP_REST_Response( [ 'success' => false, 'message' => $message ], $status );
	}
}