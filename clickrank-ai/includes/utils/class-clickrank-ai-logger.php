<?php
/**
 * Simplified logger for ClickRank.ai plugin.
 * Streamlined database logging with essential functionality.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/utils
 */
class ClickRank_AI_Logger {

	private static $max_entries = 1000;

	/**
	 * Add log entry
	 */
	public static function add( $level, $message, $context = [] ) {
		global $wpdb;
		
		if ( ! $wpdb ) {
			return false;
		}

		// Validate level
		$level = strtoupper( $level );
		if ( ! in_array( $level, [ 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL' ] ) ) {
			$level = 'INFO';
		}

		// Add context if provided
		if ( ! empty( $context ) ) {
			$message .= ' | ' . wp_json_encode( $context );
		}

		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		
		$result = $wpdb->insert( $table_name, [
			'time' => current_time( 'mysql' ),
			'level' => $level,
			'message' => substr( sanitize_text_field( $message ), 0, 2000 )
		], [ '%s', '%s', '%s' ] );

		// Cleanup old logs occasionally
		if ( $result && rand( 1, 100 ) === 1 ) {
			self::cleanup();
		}

		return $result !== false;
	}

	/**
	 * Log levels shortcuts
	 */
	public static function info( $message, $context = [] ) {
		return self::add( 'INFO', $message, $context );
	}

	public static function warning( $message, $context = [] ) {
		return self::add( 'WARNING', $message, $context );
	}

	public static function error( $message, $context = [] ) {
		return self::add( 'ERROR', $message, $context );
	}

	public static function debug( $message, $context = [] ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return self::add( 'DEBUG', $message, $context );
		}
		return false;
	}

	/**
	 * Get recent logs
	 */
	public static function get_recent_logs( $limit = 50, $level = null ) {
		global $wpdb;
		
		if ( ! $wpdb ) {
			return [];
		}

		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$where = $level ? $wpdb->prepare( 'WHERE level = %s', strtoupper( $level ) ) : '';
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name} {$where} ORDER BY time DESC LIMIT %d",
			$limit
		) );
	}

	/**
	 * Get log statistics
	 */
	public static function get_stats() {
		global $wpdb;
		
		if ( ! $wpdb ) {
			return [];
		}

		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$stats = [];
		
		foreach ( [ 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL' ] as $level ) {
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE level = %s AND time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
				$level
			) );
			$stats[ strtolower( $level ) ] = (int) $count;
		}

		return $stats;
	}

	/**
	 * Clean up old logs
	 */
	private static function cleanup() {
		global $wpdb;
		
		if ( ! $wpdb ) {
			return;
		}

		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table_name} WHERE id NOT IN (
				SELECT id FROM (
					SELECT id FROM {$table_name} ORDER BY time DESC LIMIT %d
				) AS recent_logs
			)",
			self::$max_entries
		) );
	}
}