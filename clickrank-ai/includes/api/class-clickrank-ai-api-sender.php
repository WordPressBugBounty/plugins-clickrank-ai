<?php
/**
 * Simplified API sender for ClickRank.ai requests.
 * Streamlined for efficiency while maintaining all functionality.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/api
 */
class ClickRank_AI_API_Sender {

	private const API_BASE_URL = 'https://app.clickrank.ai/api/v2/';

	/**
	 * Send subscription to ClickRank.ai
	 */
	public static function send_subscription( $api_key ) {
		if ( empty( $api_key ) ) {
			ClickRank_AI_Logger::error( 'Cannot send subscription - API key is empty' );
			return false;
		}

		$data = [
			'webhook_url' => get_rest_url( null, 'clickrank-ai/v1/update-post' ),
			'site_url' => home_url(),
			'api_key' => $api_key
		];

		$response = self::make_request( 'subscription', $data, $api_key );
		return $response && $response['success'];
	}

	/**
	 * Sync data with ClickRank.ai
	 */
	public static function sync_data( $api_key ) {
		if ( empty( $api_key ) ) {
			ClickRank_AI_Logger::error( 'Cannot sync data - API key is empty' );
			return false;
		}

		$data = [
			'site_url' => home_url(),
			'api_key' => $api_key,
			'post_count' => wp_count_posts()->publish ?? 0
		];

		$response = self::make_request( 'sync', $data, $api_key, 45 );
		if ( ! $response || ! $response['success'] ) {
			return false;
		}

		// Process sync response data
		$body = json_decode( $response['body'], true );
		if ( ! empty( $body ) && is_array( $body ) ) {
			ClickRank_AI_Logger::info( 'Sync successful: processing ' . count( $body ) . ' optimizations' );
			return self::process_sync_data( $body );
		}

		return true;
	}

	/**
	 * Test API connection by attempting subscription
	 */
	public static function test_connection( $api_key ) {
		if ( empty( $api_key ) ) {
			return [ 'success' => false, 'message' => 'API key required' ];
		}

		// Test connection by trying to send subscription
		$result = self::send_subscription( $api_key );
		
		if ( $result ) {
			set_transient( 'clickrank_ai_last_successful_connection', time(), DAY_IN_SECONDS );
			return [ 'success' => true, 'message' => 'Connection successful' ];
		} else {
			return [ 'success' => false, 'message' => 'Connection failed - please verify your API key' ];
		}
	}

	/**
	 * Make API request with simple retry logic
	 */
	private static function make_request( $endpoint, $data, $api_key, $timeout = 30 ) {
		$url = self::API_BASE_URL . $endpoint;
		
		$args = [
			'method' => 'POST',
			'timeout' => $timeout,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type' => 'application/json'
			],
			'body' => wp_json_encode( $data )
		];

		ClickRank_AI_Logger::debug( "Making API request to: {$endpoint}" );
		
		// Try request with one retry on failure
		for ( $i = 0; $i < 2; $i++ ) {
			$response = wp_remote_post( $url, $args );
			
			if ( is_wp_error( $response ) ) {
				if ( $i === 0 ) {
					ClickRank_AI_Logger::warning( "API request failed, retrying: " . $response->get_error_message() );
					sleep( 2 );
					continue;
				}
				ClickRank_AI_Logger::error( "API request failed: " . $response->get_error_message() );
				return false;
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( $status >= 200 && $status < 300 ) {
				ClickRank_AI_Logger::info( "API request successful: {$endpoint}" );
				return [ 'success' => true, 'body' => $body, 'response' => $response ];
			}

			if ( $i === 0 && $status >= 500 ) {
				ClickRank_AI_Logger::warning( "Server error (status: {$status}), retrying" );
				sleep( 2 );
				continue;
			}

			$error = json_decode( $body, true );
			$message = $error['message'] ?? 'Unknown error';
			ClickRank_AI_Logger::error( "API request failed (status: {$status}): {$message}" );
			return false;
		}

		return false;
	}

	/**
	 * Process sync response data
	 */
	private static function process_sync_data( $data ) {
		$processed = 0;
		$successful = 0;

		foreach ( $data as $page_url => $optimizations ) {
			$processed++;
			
			if ( empty( $optimizations['page_url'] ) ) {
				$optimizations['page_url'] = $page_url;
			}

			if ( self::apply_optimizations( $optimizations ) ) {
				$successful++;
			}
		}

		ClickRank_AI_Logger::info( "Sync complete: {$successful}/{$processed} pages updated" );
		return $successful > 0;
	}

	/**
	 * Apply optimizations to content
	 */
	private static function apply_optimizations( $data ) {
		$page_url = $data['page_url'];
		$content = self::resolve_url( $page_url );

		if ( ! $content ) {
			// Try image-only update
			if ( ! empty( $data['image_optimizations'] ) ) {
				return self::update_images( $data['image_optimizations'] );
			}
			ClickRank_AI_Logger::warning( "Cannot resolve URL: {$page_url}" );
			return false;
		}

		switch ( $content['type'] ) {
			case 'homepage':
				return self::update_homepage( $data );
			case 'post':
				return self::update_post( $content['id'], $data );
			default:
				ClickRank_AI_Logger::warning( "Unsupported content type: {$content['type']}" );
				return false;
		}
	}

	/**
	 * Simple URL resolution
	 */
	private static function resolve_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
		
		// Homepage
		if ( rtrim( $path, '/' ) === rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?: '', '/' ) ) {
			return [ 'type' => 'homepage' ];
		}

		// Post/page
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			return [ 'type' => 'post', 'id' => $post_id ];
		}

		return null;
	}

	/**
	 * Update homepage
	 */
	private static function update_homepage( $data ) {
		if ( ! class_exists( 'ClickRank_AI_SEO_Compat' ) ) {
			require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-compat.php';
		}

		$compat = new ClickRank_AI_SEO_Compat();
		$updated = 0;

		// PHASE 1: Write to URL-based table (sync with webhook behavior)
		$url_data = [];
		if ( ! empty( $data['page_title'] ) ) {
			$url_data['page_title'] = sanitize_text_field( $data['page_title'] );
		}
		if ( ! empty( $data['meta_description'] ) ) {
			$url_data['meta_description'] = sanitize_text_field( $data['meta_description'] );
		}
		if ( ! empty( $data['page_schema'] ) ) {
			$url_data['page_schema'] = wp_kses_post( $data['page_schema'] );
		}
		if ( ! empty( $data['canonical_url'] ) ) {
			$url_data['canonical_url'] = esc_url_raw( $data['canonical_url'] );
		}
		if ( ! empty( $url_data ) ) {
			ClickRank_AI_SEO_Data_Manager::save_seo_data( home_url(), $url_data, true );
			ClickRank_AI_Logger::debug( 'Sync: Homepage data saved to URL table' );
		}

		// Continue with post meta writes (backward compatibility)
		if ( get_option( 'clickrank_ai_enable_title_opt' ) && ! empty( $data['page_title'] ) ) {
			$compat->update_homepage_title( sanitize_text_field( $data['page_title'] ) );
			$updated++;
		}

		if ( get_option( 'clickrank_ai_enable_meta_opt' ) && ! empty( $data['meta_description'] ) ) {
			$compat->update_homepage_description( sanitize_text_field( $data['meta_description'] ) );
			$updated++;
		}

		if ( $updated > 0 ) {
			ClickRank_AI_Logger::info( "Homepage updated: {$updated} fields" );
			return true;
		}

		return false;
	}

	/**
	 * Update post/page
	 */
	private static function update_post( $post_id, $data ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return false;
		}

		if ( ! class_exists( 'ClickRank_AI_SEO_Compat' ) ) {
			require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-compat.php';
		}

		$compat = new ClickRank_AI_SEO_Compat();
		$updated = 0;
		$revert_data = [];

		// PHASE 1: Write to URL-based table (sync with webhook behavior)
		$url_data = [];
		if ( ! empty( $data['page_title'] ) ) {
			$url_data['page_title'] = sanitize_text_field( $data['page_title'] );
		}
		if ( ! empty( $data['meta_description'] ) ) {
			$url_data['meta_description'] = sanitize_text_field( $data['meta_description'] );
		}
		if ( ! empty( $data['page_schema'] ) ) {
			$url_data['page_schema'] = wp_kses_post( $data['page_schema'] );
		}
		if ( ! empty( $data['canonical_url'] ) ) {
			$url_data['canonical_url'] = esc_url_raw( $data['canonical_url'] );
		}
		if ( ! empty( $url_data ) ) {
			$post_url = get_permalink( $post_id );
			ClickRank_AI_SEO_Data_Manager::save_seo_data( $post_url, $url_data, true );
			ClickRank_AI_Logger::debug( "Sync: Post {$post_id} data saved to URL table" );
		}

		// Continue with post meta writes (backward compatibility)
		// Title
		if ( get_option( 'clickrank_ai_enable_title_opt' ) && ! empty( $data['page_title'] ) ) {
			$meta_key = $compat->get_seo_meta_key( 'title' );
			$revert_data['page_title'] = get_post_meta( $post_id, $meta_key, true );
			update_post_meta( $post_id, $meta_key, sanitize_text_field( $data['page_title'] ) );
			$updated++;
		}

		// Description
		if ( get_option( 'clickrank_ai_enable_meta_opt' ) && ! empty( $data['meta_description'] ) ) {
			$meta_key = $compat->get_seo_meta_key( 'description' );
			$revert_data['meta_description'] = get_post_meta( $post_id, $meta_key, true );
			update_post_meta( $post_id, $meta_key, sanitize_text_field( $data['meta_description'] ) );
			$updated++;
		}

		// Canonical
		if ( get_option( 'clickrank_ai_enable_canonical_opt' ) && ! empty( $data['canonical_url'] ) ) {
			$revert_data['canonical_url'] = get_post_meta( $post_id, '_clickrank_ai_canonical_url', true );
			update_post_meta( $post_id, '_clickrank_ai_canonical_url', esc_url_raw( $data['canonical_url'] ) );
			$updated++;
		}

		// Schema
		if ( get_option( 'clickrank_ai_enable_schema_opt' ) && ! empty( $data['page_schema'] ) ) {
			$revert_data['page_schema'] = get_post_meta( $post_id, '_clickrank_ai_page_schema', true );
			update_post_meta( $post_id, '_clickrank_ai_page_schema', wp_kses_post( $data['page_schema'] ) );
			$updated++;
		}

		// Images
		if ( get_option( 'clickrank_ai_enable_img_alt_opt' ) && ! empty( $data['image_optimizations'] ) ) {
			if ( self::update_images( $data['image_optimizations'] ) ) {
				$updated++;
			}
		}

		// Save revert data
		if ( ! empty( $revert_data ) ) {
			update_post_meta( $post_id, '_clickrank_ai_revert_data', $revert_data );
		}

		if ( $updated > 0 ) {
			ClickRank_AI_Logger::info( "Post {$post_id} updated: {$updated} fields" );
			return true;
		}

		return false;
	}

	/**
	 * Update images
	 */
	private static function update_images( $images ) {
		$updated = 0;
		
		foreach ( $images as $img ) {
			if ( empty( $img['image_url'] ) ) {
				continue;
			}

			$attachment_id = attachment_url_to_postid( $img['image_url'] );
			if ( ! $attachment_id ) {
				continue;
			}

			if ( isset( $img['new_alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $img['new_alt_text'] ) );
				$updated++;
			}

			if ( isset( $img['new_title'] ) ) {
				wp_update_post( [ 'ID' => $attachment_id, 'post_title' => sanitize_text_field( $img['new_title'] ) ] );
				$updated++;
			}
		}

		return $updated > 0;
	}
}