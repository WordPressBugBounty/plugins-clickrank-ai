<?php
/**
 * Simplified core plugin class for ClickRank.ai.
 * Essential functionality only, streamlined for performance.
 *
 * @link       https://clickrank.ai/
 * @since      3.3.3
 *
 * @package    ClickRank_AI
 */

class ClickRank_AI {

	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'clickrank-ai';
		$this->version = CLICKRANK_AI_VERSION;

		$this->load_dependencies();
		$this->define_hooks();
		$this->maybe_upgrade_database();
		$this->schedule_maintenance();
	}

	/**
	 * Load required dependencies
	 */
	private function load_dependencies() {
		// Core utilities
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-logger.php';
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-compat.php';
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-seo-data-manager.php';
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/utils/class-clickrank-ai-migration.php';

		// Admin functionality
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/admin/class-clickrank-ai-settings.php';
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/admin/class-clickrank-ai-admin.php';

		// Frontend functionality
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/frontend/class-clickrank-ai-frontend-hooks.php';

		// API functionality
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/api/class-clickrank-ai-api-sender.php';
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/api/class-clickrank-ai-webhook-handler.php';
	}

	/**
	 * Define all WordPress hooks
	 */
	private function define_hooks() {
		// SEO compatibility (must be loaded early)
		new ClickRank_AI_SEO_Compat();

		// Admin hooks
		if ( is_admin() ) {
			$admin = new ClickRank_AI_Admin( $this->plugin_name, $this->version );
			add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_scripts' ] );
			add_action( 'admin_menu', [ $admin, 'add_admin_menu' ] );
			add_action( 'admin_init', [ $admin, 'handle_activation_redirect' ] );
			add_action( 'admin_post_clickrank_ai_clear_logs', [ $admin, 'handle_clear_logs' ] );
			add_action( 'admin_post_clickrank_ai_sync_data', [ $admin, 'handle_sync_data_action' ] );
			add_action( 'admin_post_clickrank_ai_revert_all_changes', [ $admin, 'handle_revert_all_changes_action' ] );

			$settings = new ClickRank_AI_Settings();
			add_action( 'admin_init', [ $settings, 'register_settings' ] );
			add_action( 'admin_init', [ $settings, 'check_settings_update_and_sync' ] );
		}

		// Frontend hooks
		new ClickRank_AI_Frontend_Hooks( $this->plugin_name, $this->version );

		// API hooks
		$webhook_handler = new ClickRank_AI_Webhook_Handler();
		add_action( 'rest_api_init', [ $webhook_handler, 'register_routes' ] );

		// Maintenance hooks
		add_action( 'clickrank_ai_cleanup_logs', [ $this, 'cleanup_logs' ] );
		add_action( 'clickrank_ai_health_check', [ $this, 'health_check' ] );
	}

	/**
	 * Check and upgrade database if needed
	 */
	private function maybe_upgrade_database() {
		if ( is_admin() && get_option( 'clickrank_ai_db_version' ) !== '3.3.2' ) {
			require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/core/class-clickrank-ai-activator.php';
			ClickRank_AI_Activator::maybe_upgrade_db();
		}
	}

	/**
	 * Schedule maintenance tasks
	 */
	private function schedule_maintenance() {
		// Schedule log cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'clickrank_ai_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'clickrank_ai_cleanup_logs' );
		}

		// Schedule health check if not already scheduled
		if ( ! wp_next_scheduled( 'clickrank_ai_health_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'clickrank_ai_health_check' );
		}
	}

	/**
	 * Cleanup old logs
	 */
	public function cleanup_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$max_entries = get_option( 'clickrank_ai_max_log_entries', 1000 );
		
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table_name} WHERE id NOT IN (
				SELECT id FROM (
					SELECT id FROM {$table_name} ORDER BY time DESC LIMIT %d
				) AS recent_logs
			)",
			$max_entries
		) );
		
		if ( $deleted > 0 ) {
			ClickRank_AI_Logger::info( "Log cleanup: {$deleted} entries removed" );
		}
	}

	/**
	 * Perform health check
	 */
	public function health_check() {
		$api_key = get_option( 'clickrank_ai_api_key' );
		if ( empty( $api_key ) ) {
			return;
		}

		// Connection health check - verify API connectivity
		$result = ClickRank_AI_API_Sender::send_subscription( $api_key );
		
		if ( $result ) {
			set_transient( 'clickrank_ai_last_health_check', time(), DAY_IN_SECONDS );
			ClickRank_AI_Logger::debug( 'Health check passed' );
		} else {
			ClickRank_AI_Logger::warning( 'Health check failed: unable to connect to ClickRank.ai' );
		}
	}

	/**
	 * Run the plugin
	 */
	public function run() {
		// Plugin is ready - all hooks are registered
	}

	/**
	 * Get plugin name
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get version
	 */
	public function get_version() {
		return $this->version;
	}
}