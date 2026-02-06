<?php
/**
 * Provides the view for the Home tab in the admin dashboard with the new UI/UX.
 * This file now includes two states: one for new/inactive users and one for active users.
 *
 * @link       https://clickrank.ai/
 * @since      3.1.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$module_keys = [
	'clickrank_ai_enable_title_opt',
	'clickrank_ai_enable_meta_opt',
	'clickrank_ai_enable_img_alt_opt',
	'clickrank_ai_enable_schema_opt',
	'clickrank_ai_enable_canonical_opt',
	'clickrank_ai_enable_link_title_opt',
];
$active_modules = 0;
foreach ( $module_keys as $key ) {
	if ( get_option( $key ) ) {
		$active_modules++;
	}
}
$total_modules = count( $module_keys );
$is_active     = ( get_option( 'clickrank_ai_api_status' ) === 'valid' );

?>

<?php if ( $is_active ) : ?>
	<!-- ======================================================================== -->
	<!-- ACTIVE STATE: Show the main dashboard for connected users.               -->
	<!-- ======================================================================== -->
	<div class="space-y-8">
		<!-- Dashboard Header -->
		<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
			<div>
				<h2 class="text-3xl font-bold text-gray-900"><?php esc_html_e( 'Dashboard', 'clickrank-ai' ); ?></h2>
				<p class="text-lg text-gray-600 mt-1"><?php esc_html_e( "Welcome back! Here's a quick overview of your integration.", 'clickrank-ai' ); ?></p>
			</div>
			<div class="flex-shrink-0">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="clickrank_ai_sync_data">
					<?php wp_nonce_field( 'clickrank_ai_sync_data_nonce' ); ?>
					<button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md border border-transparent bg-blue-600 py-3 px-5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
						<i class="fas fa-sync-alt"></i>
						<?php esc_html_e( 'Sync Data from ClickRank.ai', 'clickrank-ai' ); ?>
					</button>
				</form>
			</div>
		</div>

		<!-- Status Cards -->
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
			<div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-green-500">
				<div class="flex items-center">
					<div class="flex-shrink-0 bg-green-100 text-green-600 rounded-full h-12 w-12 flex items-center justify-center text-xl">
						<i class="fas fa-check-circle"></i>
					</div>
					<div class="ml-4">
						<p class="text-sm font-medium text-gray-500"><?php esc_html_e( 'Integration Status', 'clickrank-ai' ); ?></p>
						<p class="text-2xl font-bold text-gray-900"><?php esc_html_e( 'Active', 'clickrank-ai' ); ?></p>
					</div>
				</div>
			</div>
			<div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-500">
				<div class="flex items-center">
					<div class="flex-shrink-0 bg-blue-100 text-blue-600 rounded-full h-12 w-12 flex items-center justify-center text-xl">
						<i class="fas fa-puzzle-piece"></i>
					</div>
					<div class="ml-4">
						<p class="text-sm font-medium text-gray-500"><?php esc_html_e( 'Active Modules', 'clickrank-ai' ); ?></p>
						<p class="text-2xl font-bold text-gray-900"><?php echo absint( $active_modules ); ?> / <?php echo absint( $total_modules ); ?></p>
					</div>
				</div>
			</div>
			<div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-yellow-500">
				<div class="flex items-center">
					<div class="flex-shrink-0 bg-yellow-100 text-yellow-600 rounded-full h-12 w-12 flex items-center justify-center text-xl">
						 <i class="fas fa-plug"></i>
					</div>
					<div class="ml-4">
						<p class="text-sm font-medium text-gray-500"><?php esc_html_e( 'Webhook URL', 'clickrank-ai' ); ?></p>
						<p class="text-sm font-semibold text-gray-900"><?php esc_html_e( 'Configured & Ready', 'clickrank-ai' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Main Content Area -->
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
			<div class="lg:col-span-2 bg-white p-8 rounded-lg shadow-sm">
				<h3 class="text-xl font-bold text-gray-900 mb-4"><?php esc_html_e( 'Getting Started', 'clickrank-ai' ); ?></h3>
				<p class="text-gray-600 mb-6"><?php esc_html_e( 'Your WordPress site is now connected to ClickRank.ai. Here’s how to get the most out of our platform:', 'clickrank-ai' ); ?></p>
				<ul class="space-y-5">
					<li class="flex items-start">
						<div class="flex-shrink-0 bg-blue-100 text-blue-600 rounded-full h-8 w-8 flex items-center justify-center font-bold">1</div>
						<div class="ml-4">
							<h4 class="font-semibold text-gray-800"><?php esc_html_e( 'Configure Your Settings', 'clickrank-ai' ); ?></h4>
							<p class="text-gray-600"><?php esc_html_e( 'Visit the Settings tab to toggle on the specific SEO automations you want to use.', 'clickrank-ai' ); ?></p>
						</div>
					</li>
					<li class="flex items-start">
						<div class="flex-shrink-0 bg-blue-100 text-blue-600 rounded-full h-8 w-8 flex items-center justify-center font-bold">2</div>
						<div class="ml-4">
							<h4 class="font-semibold text-gray-800"><?php esc_html_e( 'Manage Optimizations on ClickRank.ai', 'clickrank-ai' ); ?></h4>
							<p class="text-gray-600"><?php esc_html_e( 'Log in to your ClickRank.ai dashboard to manage pages, review AI suggestions, and push updates to your site.', 'clickrank-ai' ); ?></p>
						</div>
					</li>
					<li class="flex items-start">
						<div class="flex-shrink-0 bg-blue-100 text-blue-600 rounded-full h-8 w-8 flex items-center justify-center font-bold">3</div>
						<div class="ml-4">
							<h4 class="font-semibold text-gray-800"><?php esc_html_e( 'Monitor Activity', 'clickrank-ai' ); ?></h4>
							<p class="text-gray-600"><?php esc_html_e( 'Use the Logs tab to see a transparent history of all updates sent from our platform to your website.', 'clickrank-ai' ); ?></p>
						</div>
					</li>
				</ul>
			</div>

			<div class="bg-white p-8 rounded-lg shadow-sm">
				<h3 class="text-xl font-bold text-gray-900 mb-4"><?php esc_html_e( 'Quick Links', 'clickrank-ai' ); ?></h3>
				<div class="space-y-4">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=activation' ) ); ?>" class="group flex items-center p-3 rounded-md bg-gray-50 hover:bg-blue-50 transition-colors">
						<div class="bg-blue-100 text-blue-600 rounded-lg p-2 text-xl"><i class="fas fa-key fa-fw"></i></div>
						<span class="ml-4 font-semibold text-gray-700 group-hover:text-blue-700"><?php esc_html_e( 'Manage API Key', 'clickrank-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=settings' ) ); ?>" class="group flex items-center p-3 rounded-md bg-gray-50 hover:bg-blue-50 transition-colors">
						<div class="bg-blue-100 text-blue-600 rounded-lg p-2 text-xl"><i class="fas fa-cogs fa-fw"></i></div>
						<span class="ml-4 font-semibold text-gray-700 group-hover:text-blue-700"><?php esc_html_e( 'Configure Settings', 'clickrank-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=logs' ) ); ?>" class="group flex items-center p-3 rounded-md bg-gray-50 hover:bg-blue-50 transition-colors">
						<div class="bg-blue-100 text-blue-600 rounded-lg p-2 text-xl"><i class="fas fa-clipboard-list fa-fw"></i></div>
						<span class="ml-4 font-semibold text-gray-700 group-hover:text-blue-700"><?php esc_html_e( 'View Activity Logs', 'clickrank-ai' ); ?></span>
					</a>
				</div>
			</div>
		</div>
	</div>

<?php else : ?>
	<!-- ======================================================================== -->
	<!-- INACTIVE STATE: Show a welcome/onboarding page for new users.          -->
	<!-- ======================================================================== -->
	<div class="bg-white shadow-sm rounded-lg text-center p-8 md:p-12">
		<div class="max-w-2xl mx-auto">
			<h2 class="mt-6 text-3xl font-bold text-gray-900"><?php esc_html_e( 'Welcome to ClickRank.ai', 'clickrank-ai' ); ?></h2>
			<p class="mt-4 text-lg text-gray-600">
				<?php esc_html_e( 'Automate your WordPress SEO with the power of AI. Save time and improve your search rankings by automatically optimizing titles, meta descriptions, schema, image alt text, and more.', 'clickrank-ai' ); ?>
			</p>
			<div class="mt-8">
				<a href="https://app.clickrank.ai/en/onboarding" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md border border-transparent bg-blue-600 py-3 px-6 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
					<?php esc_html_e( 'Create a Free Account', 'clickrank-ai' ); ?>
					<i class="fas fa-external-link-alt"></i>
				</a>
			</div>
			<p class="mt-6 text-sm text-gray-500">
				<?php esc_html_e( 'Already have an account?', 'clickrank-ai' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=activation' ) ); ?>" class="font-medium text-blue-600 hover:text-blue-500">
					<?php esc_html_e( 'Activate your plugin here', 'clickrank-ai' ); ?>
				</a>
			</p>
		</div>
	</div>

<?php endif; ?>