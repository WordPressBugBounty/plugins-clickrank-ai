<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/core
 */

class ClickRank_AI_Deactivator {

	/**
	 * The main deactivation method.
	 *
	 * This method is called when the plugin is deactivated. It cleans up
	 * scheduled events, temporary data, and optionally removes all data
	 * based on user preferences.
	 *
	 * @since 3.2.0
	 */
	public static function deactivate() {
		try {
			// Check if this is a network deactivation
			if ( is_multisite() && is_network_admin() ) {
				self::network_deactivate();
			} else {
				self::single_site_deactivate();
			}
		} catch ( Exception $e ) {
			if ( class_exists( 'ClickRank_AI_Logger' ) ) {
				ClickRank_AI_Logger::error( 'Plugin deactivation failed: ' . $e->getMessage() );
			}
			// Don't prevent deactivation, just log the error
		}
	}

	/**
	 * Deactivate on single site.
	 *
	 * @since 3.2.0
	 */
	private static function single_site_deactivate() {
		self::clear_scheduled_events();
		self::clear_transients();
		self::log_deactivation();
		
		// Optional: Clear all data if user setting is enabled
		if ( get_option( 'clickrank_ai_remove_data_on_deactivate', false ) ) {
			self::remove_all_data();
		}
	}

	/**
	 * Network deactivation for multisite.
	 *
	 * @since 3.2.0
	 */
	private static function network_deactivate() {
		if ( ! current_user_can( 'manage_network_plugins' ) ) {
			return;
		}

		global $wpdb;
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			self::single_site_deactivate();
			restore_current_blog();
		}
	}

	/**
	 * Clear all scheduled events.
	 *
	 * @since 3.2.0
	 * @access private
	 */
	private static function clear_scheduled_events() {
		$events = [
			'clickrank_ai_cleanup_logs',
			'clickrank_ai_health_check',
			'clickrank_ai_cache_cleanup'
		];

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}

		if ( class_exists( 'ClickRank_AI_Logger' ) ) {
			ClickRank_AI_Logger::info( 'Scheduled events cleared during deactivation' );
		}
	}

	/**
	 * Clear plugin transients and cached data.
	 *
	 * @since 3.2.0
	 * @access private
	 */
	private static function clear_transients() {
		global $wpdb;

		// Clear specific transients
		$transients = [
			'clickrank_ai_activation_redirect',
			'clickrank_ai_last_connection_test',
			'clickrank_ai_last_successful_connection',
			'clickrank_ai_api_health',
			'clickrank_ai_rate_limit_',
			'clickrank_ai_cache_'
		];

		foreach ( $transients as $transient ) {
			if ( strpos( $transient, '_' ) === strlen( $transient ) - 1 ) {
				// Pattern-based deletion for prefixed transients
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_' . $transient . '%',
					'_transient_timeout_' . $transient . '%'
				) );
			} else {
				delete_transient( $transient );
			}
		}

		// Clear object cache
		wp_cache_flush();
	}

	/**
	 * Log deactivation event.
	 *
	 * @since 3.2.0
	 * @access private
	 */
	private static function log_deactivation() {
		if ( class_exists( 'ClickRank_AI_Logger' ) ) {
			ClickRank_AI_Logger::info( 'Plugin deactivated', [
				'user_id' => get_current_user_id(),
				'timestamp' => current_time( 'timestamp' ),
				'wp_version' => get_bloginfo( 'version' ),
				'plugin_version' => CLICKRANK_AI_VERSION ?? 'unknown'
			] );
		}

		update_option( 'clickrank_ai_last_deactivation', current_time( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Remove all plugin data (DANGEROUS - only if user explicitly opts in).
	 *
	 * @since 3.2.0
	 * @access private
	 */
	private static function remove_all_data() {
		global $wpdb;

		if ( class_exists( 'ClickRank_AI_Logger' ) ) {
			ClickRank_AI_Logger::warning( 'Starting complete data removal - this cannot be undone' );
		}

		// Remove database table
		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		// Remove all plugin options
		$option_patterns = [
			'clickrank_ai_%'
		];

		foreach ( $option_patterns as $pattern ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			) );
		}

		// Remove all post meta created by the plugin
		$meta_keys = [
			'_clickrank_ai_revert_data',
			'_clickrank_ai_canonical_url',
			'_clickrank_ai_page_schema',
			'_clickrank_ai_link_titles'
		];

		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		// Remove user meta
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			'clickrank_ai_%'
		) );
	}

	/**
	 * Get deactivation status and cleanup results.
	 *
	 * @since 3.2.0
	 * @return array Deactivation status details.
	 */
	public static function get_deactivation_status() {
		$events_cleared = 0;
		$events = [
			'clickrank_ai_cleanup_logs',
			'clickrank_ai_health_check',
			'clickrank_ai_cache_cleanup'
		];

		foreach ( $events as $event ) {
			if ( ! wp_next_scheduled( $event ) ) {
				$events_cleared++;
			}
		}

		return [
			'events_cleared' => $events_cleared,
			'total_events' => count( $events ),
			'transients_cleared' => true, // Always true after deactivation
			'last_deactivation' => get_option( 'clickrank_ai_last_deactivation', 'never' ),
			'data_removed' => ! get_option( 'clickrank_ai_plugin_version', false )
		];
	}
}