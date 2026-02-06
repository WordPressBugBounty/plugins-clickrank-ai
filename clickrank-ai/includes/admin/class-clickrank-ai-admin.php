<?php
/**
 * Simplified admin functionality for ClickRank.ai plugin.
 * Essential admin features only, streamlined for efficiency.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/admin
 */
class ClickRank_AI_Admin {

	private $plugin_name;
	private $version;
	private $plugin_screen_id;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action( 'admin_notices', [ $this, 'show_activation_notice' ] );
		add_action( 'admin_init', [ $this, 'dismiss_activation_notice' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
		add_action( 'wp_ajax_clickrank_test_api', [ $this, 'test_api_connection' ] );
	}

	public function enqueue_scripts( $hook ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Basic admin styles for all admin pages
		wp_enqueue_style(
			$this->plugin_name . '-admin-styles',
			CLICKRANK_AI_PLUGIN_URL . 'assets/css/admin-styles.css',
			[],
			$this->version,
			'all'
		);

		// Only load plugin-specific assets on our plugin pages
		if ( $hook !== $this->plugin_screen_id ) {
			return;
		}

		// Font Awesome for icons
		wp_enqueue_style(
			'font-awesome',
			CLICKRANK_AI_PLUGIN_URL . 'assets/vendor/fontawesome/css/all.min.css',
			[],
			'5.15.4'
		);

		// Tailwind CSS for styling
		wp_enqueue_script(
			$this->plugin_name . '-tailwind',
			CLICKRANK_AI_PLUGIN_URL . 'assets/vendor/tailwindcss/tailwind.js',
			[],
			null,
			false // Load in head to prevent FOUC
		);

		// Plugin admin scripts
		wp_enqueue_script(
			$this->plugin_name,
			CLICKRANK_AI_PLUGIN_URL . 'assets/js/admin-scripts.js',
			[ 'jquery' ],
			$this->version,
			true
		);
	}

	public function add_admin_menu() {
		$this->plugin_screen_id = add_menu_page(
			'ClickRank.ai Dashboard',
			'ClickRank.ai',
			'manage_options',
			CLICKRANK_AI_MENU_SLUG,
			[ $this, 'render_admin_page' ],
			CLICKRANK_AI_PLUGIN_URL . 'assets/images/icon.png',
			25
		);
	}

	public function render_admin_page() {
		require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/admin/views/view-admin-shell.php';
	}

	public function handle_activation_redirect() {
		if ( get_transient( 'clickrank_ai_activation_redirect' ) ) {
			delete_transient( 'clickrank_ai_activation_redirect' );
			update_user_meta( get_current_user_id(), 'clickrank_ai_activation_notice', 'show' );
			
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=activation' ) );
				exit;
			}
		}
	}

	public function show_activation_notice() {
		if ( get_user_meta( get_current_user_id(), 'clickrank_ai_activation_notice', true ) !== 'show' ) {
			return;
		}

		$dismiss_url = wp_nonce_url( 
			add_query_arg( 'clickrank_ai_dismiss_notice', 'activation' ), 
			'clickrank_ai_dismiss_activation', 
			'clickrank_ai_nonce' 
		);
		?>
		<div class="notice notice-success is-dismissible" style="border-left-color: #0052cc; display: flex; align-items: center; padding: 15px;">
			<div style="margin-right: 15px;">
				<img src="<?php echo esc_url( CLICKRANK_AI_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="ClickRank.ai Logo" style="max-width: 120px;">
			</div>
			<div>
				<p><strong>Welcome to ClickRank.ai!</strong></p>
				<p>Your AI-powered SEO assistant is now active. Enter your API key to get started.</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=activation' ) ); ?>" class="button button-primary" style="margin-right: 10px;">Go to Activation</a>
					<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button">Dismiss</a>
				</p>
			</div>
		</div>
		<?php
	}

	public function dismiss_activation_notice() {
		if ( ! isset( $_GET['clickrank_ai_dismiss_notice'], $_GET['clickrank_ai_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['clickrank_ai_nonce'], 'clickrank_ai_dismiss_activation' ) ) {
			return;
		}

		if ( $_GET['clickrank_ai_dismiss_notice'] === 'activation' ) {
			delete_user_meta( get_current_user_id(), 'clickrank_ai_activation_notice' );
			wp_safe_redirect( remove_query_arg( [ 'clickrank_ai_dismiss_notice', 'clickrank_ai_nonce' ] ) );
			exit;
		}
	}

	public function handle_admin_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $_GET['action'] ?? $_POST['action'] ?? '';

		switch ( $action ) {
			case 'sync_data':
				$this->handle_sync_data_action();
				break;
			case 'clear_logs':
				$this->handle_clear_logs();
				break;
			case 'revert_all':
				$this->handle_revert_all_changes_action();
				break;
			case 'run_migration':
				$this->handle_run_migration();
				break;
		}
	}

	public function test_api_connection() {
		if ( ! wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( 'API key required' );
		}

		$result = ClickRank_AI_API_Sender::test_connection( $api_key );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	private function handle_sync_data() {
		if ( ! $this->verify_nonce( 'clickrank_ai_sync_data_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$api_key = get_option( 'clickrank_ai_api_key' );
		$redirect_url = admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=home' );

		if ( empty( $api_key ) ) {
			wp_redirect( add_query_arg( 'sync_status', 'no_key', $redirect_url ) );
			exit;
		}

		$success = ClickRank_AI_API_Sender::sync_data( $api_key );
		wp_redirect( add_query_arg( 'sync_status', $success ? 'success' : 'error', $redirect_url ) );
		exit;
	}

	public function handle_clear_logs() {
		if ( ! $this->verify_nonce( 'clickrank_ai_clear_logs_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );

		$message = $result !== false ? 'cleared' : 'error';
		wp_redirect( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=logs&message=' . $message ) );
		exit;
	}

	public function handle_sync_data_action() {
		if ( ! $this->verify_nonce( 'clickrank_ai_sync_data_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$api_key = get_option( 'clickrank_ai_api_key' );
		$redirect_url = admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=home' );

		if ( empty( $api_key ) ) {
			wp_redirect( add_query_arg( 'sync_status', 'no_key', $redirect_url ) );
			exit;
		}

		$success = ClickRank_AI_API_Sender::sync_data( $api_key );
		wp_redirect( add_query_arg( 'sync_status', $success ? 'success' : 'error', $redirect_url ) );
		exit;
	}

	public function handle_revert_all_changes_action() {
		if ( ! $this->verify_nonce( 'clickrank_ai_revert_all_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		global $wpdb;
		$compat = new ClickRank_AI_SEO_Compat();
		$reverted = 0;

		// Find posts with revert data
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
			'_clickrank_ai_revert_data'
		) );

		foreach ( $posts as $post_meta ) {
			$post_id = $post_meta->post_id;
			$revert_data = get_post_meta( $post_id, '_clickrank_ai_revert_data', true );

			if ( ! is_array( $revert_data ) ) {
				continue;
			}

			// Revert SEO fields
			if ( isset( $revert_data['page_title'] ) ) {
				$meta_key = $compat->get_seo_meta_key( 'title' );
				update_post_meta( $post_id, $meta_key, $revert_data['page_title'] );
			}

			if ( isset( $revert_data['meta_description'] ) ) {
				$meta_key = $compat->get_seo_meta_key( 'description' );
				update_post_meta( $post_id, $meta_key, $revert_data['meta_description'] );
			}

			// Clean up plugin meta
			delete_post_meta( $post_id, '_clickrank_ai_revert_data' );
			delete_post_meta( $post_id, '_clickrank_ai_canonical_url' );
			delete_post_meta( $post_id, '_clickrank_ai_page_schema' );

			$reverted++;
		}

		// Revert homepage from stored data or clear
		$homepage_revert_data = get_option( '_clickrank_ai_homepage_revert_data', [] );
		
		if ( ! empty( $homepage_revert_data ) && is_array( $homepage_revert_data ) ) {
			// Restore from backup
			if ( isset( $homepage_revert_data['page_title'] ) ) {
				$compat->update_homepage_title( $homepage_revert_data['page_title'] );
			}
			if ( isset( $homepage_revert_data['meta_description'] ) ) {
				$compat->update_homepage_description( $homepage_revert_data['meta_description'] );
			}
			if ( isset( $homepage_revert_data['page_schema'] ) ) {
				update_option( '_clickrank_ai_homepage_schema', $homepage_revert_data['page_schema'] );
			}
			if ( isset( $homepage_revert_data['canonical_url'] ) ) {
				update_option( '_clickrank_ai_homepage_canonical', $homepage_revert_data['canonical_url'] );
			}
			delete_option( '_clickrank_ai_homepage_revert_data' );
		} else {
			// Clear all if no backup
			delete_option( '_clickrank_ai_homepage_title' );
			delete_option( '_clickrank_ai_homepage_description' );
			delete_option( '_clickrank_ai_homepage_schema' );
			delete_option( '_clickrank_ai_homepage_canonical' );
			$compat->update_homepage_title( '' );
			$compat->update_homepage_description( '' );
		}

		ClickRank_AI_Logger::info( "Global revert completed: {$reverted} posts" );

		wp_safe_redirect( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=settings&message=revert_success' ) );
		exit;
	}

	public function handle_run_migration() {
		if ( ! $this->verify_nonce( 'clickrank_ai_run_migration_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Run full migration
		$results = ClickRank_AI_Migration::run_full_migration( 100 );

		// Redirect with results
		$message = $results['posts']['migrated'] > 0 ? 'migration_success' : 'migration_no_data';
		wp_safe_redirect( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=settings&message=' . $message ) );
		exit;
	}

	private function verify_nonce( $action ) {
		return isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $action );
	}
}