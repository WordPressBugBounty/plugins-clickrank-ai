<?php
/**
 * Simplified plugin activator for ClickRank.ai.
 * Essential activation functionality only.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/core
 */

class ClickRank_AI_Activator {

	private const DB_VERSION = '3.3.5';

	/**
	 * Main activation method
	 */
	public static function activate() {
		self::create_logs_table();
		self::create_seo_data_table();
		self::set_default_options();
		self::schedule_events();

		// Set activation redirect (not for bulk activation)
		if ( ! isset( $_GET['activate-multi'] ) ) {
			set_transient( 'clickrank_ai_activation_redirect', true, 30 );
		}

		update_option( 'clickrank_ai_db_version', self::DB_VERSION );
		update_option( 'clickrank_ai_activation_time', time() );
	}

	/**
	 * Create SEO data table for URL-based storage
	 */
	private static function create_seo_data_table() {
		// Load the SEO Data Manager class
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';

		// Create the table
		ClickRank_AI_SEO_Data_Manager::create_table();
	}

	/**
	 * Create logs table
	 */
	private static function create_logs_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$charset_collate = $wpdb->get_charset_collate();

		// Drop existing table if it exists to ensure clean installation
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			level varchar(20) NOT NULL DEFAULT 'INFO',
			message text NOT NULL,
			PRIMARY KEY (id),
			KEY idx_time (time),
			KEY idx_level (level)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created and log error instead of stopping activation
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			// Try direct CREATE TABLE as fallback
			$wpdb->query( $sql );
			
			// Final verification - if still fails, log error but don't stop activation
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
				error_log( 'ClickRank AI: Failed to create database table for logs. Some features may not work properly.' );
				update_option( 'clickrank_ai_table_creation_error', true );
			}
		}
	}

	/**
	 * Set default options
	 */
	private static function set_default_options() {
		$defaults = [
			// Core optimization modules
			'clickrank_ai_enable_title_opt' => 1,
			'clickrank_ai_enable_meta_opt' => 1,
			'clickrank_ai_enable_img_alt_opt' => 1,
			'clickrank_ai_enable_schema_opt' => 1,
			'clickrank_ai_enable_canonical_opt' => 1,
			'clickrank_ai_enable_link_title_opt' => 1,
			
			// System settings
			'clickrank_ai_max_log_entries' => 1000,
			'clickrank_ai_rate_limit_enabled' => 1
		];

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value, '', 'no' );
			}
		}

		// Set installation metadata
		update_option( 'clickrank_ai_install_date', current_time( 'Y-m-d H:i:s' ) );
		update_option( 'clickrank_ai_plugin_version', CLICKRANK_AI_VERSION );
	}

	/**
	 * Schedule recurring events
	 */
	private static function schedule_events() {
		// Schedule log cleanup
		if ( ! wp_next_scheduled( 'clickrank_ai_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'clickrank_ai_cleanup_logs' );
		}

		// Schedule health check
		if ( ! wp_next_scheduled( 'clickrank_ai_health_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'clickrank_ai_health_check' );
		}
	}

	/**
	 * Upgrade database if needed
	 */
	public static function maybe_upgrade_db() {
		$current_version = get_option( 'clickrank_ai_db_version', '1.0.0' );
		
		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			self::upgrade_database( $current_version );
			update_option( 'clickrank_ai_db_version', self::DB_VERSION );
			ClickRank_AI_Logger::info( "Database upgraded from {$current_version} to " . self::DB_VERSION );
		}
	}

	/**
	 * Upgrade database schema
	 */
	private static function upgrade_database( $from_version ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'clickrank_ai_logs';

		// Upgrade from versions before 3.2.0
		if ( version_compare( $from_version, '3.2.0', '<' ) ) {
			// Add indexes if they don't exist
			$indexes = [
				'idx_time' => 'ALTER TABLE %s ADD INDEX idx_time (time)',
				'idx_level' => 'ALTER TABLE %s ADD INDEX idx_level (level)'
			];

			foreach ( $indexes as $index_name => $sql ) {
				$existing = $wpdb->get_results( $wpdb->prepare(
					"SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
					DB_NAME,
					$table_name,
					$index_name
				) );

				if ( empty( $existing ) ) {
					$wpdb->query( sprintf( $sql, $table_name ) );
				}
			}

			// Update time column default
			$wpdb->query( "ALTER TABLE {$table_name} ALTER COLUMN time SET DEFAULT CURRENT_TIMESTAMP" );
		}
	}
}