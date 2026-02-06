<?php
/**
 * Provides the main shell for the admin pages with the new UI/UX.
 *
 * @link       https://clickrank.ai/
 * @since      3.1.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'home';

$tabs = [
	'home'       => [
		'title' => __( 'Dashboard', 'clickrank-ai' ),
		'icon'  => '<i class="fas fa-tachometer-alt fa-fw mr-2"></i>',
	],
	'activation' => [
		'title' => __( 'Activation', 'clickrank-ai' ),
		'icon'  => '<i class="fas fa-key fa-fw mr-2"></i>',
	],
	'settings'   => [
		'title' => __( 'Settings', 'clickrank-ai' ),
		'icon'  => '<i class="fas fa-cogs fa-fw mr-2"></i>',
	],
	'logs'       => [
		'title' => __( 'Logs', 'clickrank-ai' ),
		'icon'  => '<i class="fas fa-clipboard-list fa-fw mr-2"></i>',
	],
];

// Define the allowed HTML for the icons.
$allowed_icon_html = [
	'i' => [
		'class' => [],
	],
];

?>
<div id="cr-app" class="min-h-screen -mx-5 -my-5">
	<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
		<header class="pb-8">
			<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
				<div class="flex items-center gap-4">
					<img src="<?php echo esc_url( CLICKRANK_AI_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="ClickRank.ai Logo" style="max-width: 150px;"/>
					<span class="text-sm font-semibold text-gray-500 bg-gray-100 rounded-full px-3 py-1">v<?php echo esc_html( CLICKRANK_AI_VERSION ); ?></span>
				</div>
				<div class="w-full md:w-auto">
					<div class="border-b border-gray-200">
						<nav class="-mb-px flex space-x-6" aria-label="Tabs">
							<?php foreach ( $tabs as $tab_id => $tab ) : ?>
								<?php
								$url       = admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=' . $tab_id );
								$is_active = ( $current_tab === $tab_id );
								$classes   = $is_active
									? 'border-blue-600 text-blue-700'
									: 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
								?>
								<a href="<?php echo esc_url( $url ); ?>" class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors <?php echo esc_attr( $classes ); ?>">
									<?php echo wp_kses( $tab['icon'], $allowed_icon_html ); ?>
									<?php echo esc_html( $tab['title'] ); ?>
								</a>
							<?php endforeach; ?>
						</nav>
					</div>
				</div>
			</div>
		</header>

		<main>
			<?php
			// Display notifications based on query arguments.
			if ( isset( $_GET['message'] ) ) {
				$message     = sanitize_key( $_GET['message'] );
				$notice_html = '';

				switch ( $message ) {
					case 'settings_saved':
						$notice_html = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'Settings saved successfully.', 'clickrank-ai' ) . "</strong></p></div>";
						break;
					case 'settings_saved_synced':
						$notice_html = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'API Key saved and webhook successfully synced!', 'clickrank-ai' ) . "</strong></p></div>";
						break;
					case 'settings_saved_sync_failed':
						$notice_html = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'API Key saved, but could not sync webhook. Check Logs for details.', 'clickrank-ai' ) . "</strong></p></div>";
						break;
					case 'revert_success':
						if ( 'settings' === $current_tab ) {
							$notice_html = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'All changes have been successfully reverted.', 'clickrank-ai' ) . "</strong></p></div>";
						}
						break;
				}
				if ( ! empty( $notice_html ) ) {
					echo wp_kses_post( $notice_html );
				}
			}

			if ( isset( $_GET['sync_status'] ) && 'home' === $current_tab ) {
				$status      = sanitize_key( $_GET['sync_status'] );
				$notice_html = '';

				if ( 'success' === $status ) {
					$notice_html = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'Sync request sent successfully!', 'clickrank-ai' ) . "</strong> " . esc_html__( 'Check the Logs tab for activity.', 'clickrank-ai' ) . "</p></div>";
				} elseif ( 'error' === $status ) {
					$notice_html = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'Sync request failed.', 'clickrank-ai' ) . "</strong> " . esc_html__( 'Please check the Logs tab for more details.', 'clickrank-ai' ) . "</p></div>";
				} elseif ( 'no_key' === $status ) {
					$notice_html = "<div class='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-md'><p><strong>" . esc_html__( 'Cannot sync.', 'clickrank-ai' ) . "</strong> " . esc_html__( 'Please go to the Activation page to save a valid API Key first.', 'clickrank-ai' ) . "</p></div>";
				}

				if ( ! empty( $notice_html ) ) {
					echo wp_kses_post( $notice_html );
				}
			}

			// Load the correct view file based on the current tab identifier.
			$view_file = CLICKRANK_AI_PLUGIN_DIR . 'includes/admin/views/view-' . $current_tab . '-tab.php';
			if ( file_exists( $view_file ) ) {
				include $view_file;
			} else {
				// Fallback to home tab if the view file doesn't exist.
				include CLICKRANK_AI_PLUGIN_DIR . 'includes/admin/views/view-home-tab.php';
			}
			?>
		</main>
	</div>
</div>