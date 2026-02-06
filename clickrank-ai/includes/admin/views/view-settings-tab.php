<?php
/**
 * Provides the view for the Settings tab, now only for modules.
 *
 * @link       https://clickrank.ai/
 * @since      3.1.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Helper function to render a module toggle switch.
 */
function clickrank_ai_render_module_toggle( $key, $title, $description, $icon_class ) {
	?>
	<div class="py-5 flex items-start justify-between">
		<div class="flex items-center">
			<div class="flex-shrink-0 bg-blue-100 text-blue-600 rounded-lg p-2 text-xl">
				<i class="<?php echo esc_attr( $icon_class ); ?>"></i>
			</div>
			<div class="ml-4">
				<h3 class="text-sm font-medium text-gray-900"><?php echo esc_html( $title ); ?></h3>
				<p class="text-sm text-gray-500"><?php echo esc_html( $description ); ?></p>
			</div>
		</div>
		<label for="<?php echo esc_attr( $key ); ?>" class="relative inline-flex items-center cursor-pointer ml-4">
			<input type="checkbox" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( get_option( $key ), 1 ); ?> class="sr-only peer cr-module-toggle">
			<div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
		</label>
	</div>
	<?php
}

$content_modules = [
	'clickrank_ai_enable_title_opt'      => [ __( 'Title Optimization', 'clickrank-ai' ), __( 'Automatically generate and apply SEO-friendly page and post titles.', 'clickrank-ai' ), 'fas fa-heading fa-fw' ],
	'clickrank_ai_enable_meta_opt'       => [ __( 'Meta Description Optimization', 'clickrank-ai' ), __( 'Automatically generate and apply SEO-friendly meta descriptions.', 'clickrank-ai' ), 'fas fa-file-alt fa-fw' ],
	'clickrank_ai_enable_img_alt_opt'    => [ __( 'Image Alt Text Generation', 'clickrank-ai' ), __( 'Generate descriptive alt text for your images.', 'clickrank-ai' ), 'fas fa-image fa-fw' ],
	'clickrank_ai_enable_link_title_opt' => [ __( 'Automatic Link Titles', 'clickrank-ai' ), __( 'Automatically add title attributes to links that are missing them.', 'clickrank-ai' ), 'fas fa-link fa-fw' ],
];

$tech_modules = [
	'clickrank_ai_enable_schema_opt'    => [ __( 'Schema Markup Generation', 'clickrank-ai' ), __( 'Apply structured data to your pages.', 'clickrank-ai' ), 'fas fa-code fa-fw' ],
	'clickrank_ai_enable_canonical_opt' => [ __( 'Canonical Tag Optimization', 'clickrank-ai' ), __( 'Set the canonical URL for pages to prevent duplicate content issues.', 'clickrank-ai' ), 'fas fa-sitemap fa-fw' ],
];

?>

<form id="cr-settings-form" action="options.php" method="post">
	<?php settings_fields( ClickRank_AI_Settings::MODULES_GROUP ); ?>

	<div class="bg-white shadow-sm rounded-lg">
		<div class="p-6 border-b border-gray-200">
			<div class="flex items-center justify-between">
				<div>
					<h2 class="text-xl font-bold text-gray-800"><?php esc_html_e( 'Automation Modules', 'clickrank-ai' ); ?></h2>
					<p class="text-gray-600 mt-2"><?php esc_html_e( 'Enable or disable specific automation features across your entire site.', 'clickrank-ai' ); ?></p>
				</div>
				<div class="flex items-center">
					<span class="text-sm font-medium text-gray-700 mr-3"><?php esc_html_e( 'Toggle All', 'clickrank-ai' ); ?></span>
					 <label for="cr-master-toggle" class="relative inline-flex items-center cursor-pointer">
						<input type="checkbox" id="cr-master-toggle" class="sr-only peer">
						<div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
					</label>
				</div>
			</div>
		</div>
		<div class="bg-gray-50 p-6">
			<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e( 'Content & On-Page', 'clickrank-ai' ); ?></h3>
			<div class="divide-y divide-gray-200">
				<?php
				foreach ( $content_modules as $key => $data ) {
					clickrank_ai_render_module_toggle( $key, $data[0], $data[1], $data[2] );
				}
				?>
			</div>
			<h3 class="text-lg font-semibold text-gray-900 mt-8 mb-2"><?php esc_html_e( 'Technical SEO', 'clickrank-ai' ); ?></h3>
			<div class="divide-y divide-gray-200">
				 <?php
					foreach ( $tech_modules as $key => $data ) {
						clickrank_ai_render_module_toggle( $key, $data[0], $data[1], $data[2] );
					}
					?>
			</div>
		</div>
		<div class="p-6 bg-gray-50 border-t border-gray-200 rounded-b-lg flex justify-end">
			<button type="submit" name="submit" id="submit-modules" class="inline-flex justify-center items-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<i class="fas fa-save fa-fw"></i>
				<?php esc_html_e( 'Save Module Settings', 'clickrank-ai' ); ?>
			</button>
		</div>
	</div>
</form>

<div class="mt-8 bg-white shadow-sm rounded-lg">
	<div class="p-6 border-b border-red-200 bg-red-50 rounded-t-lg">
		<h2 class="text-xl font-bold text-red-800"><?php esc_html_e( 'Danger Zone', 'clickrank-ai' ); ?></h2>
		<p class="text-red-600 mt-2"><?php esc_html_e( 'These are destructive actions. Please be certain before proceeding.', 'clickrank-ai' ); ?></p>
	</div>
	<div class="bg-gray-50 p-6 rounded-b-lg">
		<div class="flex items-center justify-between">
			<div>
				<h3 class="text-sm font-medium text-gray-900"><?php esc_html_e( 'Revert All Changes', 'clickrank-ai' ); ?></h3>
				<p class="text-sm text-gray-500"><?php esc_html_e( 'Remove all ClickRank.ai optimizations and revert your posts and pages to their original state.', 'clickrank-ai' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to revert all changes made by ClickRank.ai? This action cannot be undone.', 'clickrank-ai' ); ?>');">
				<input type="hidden" name="action" value="clickrank_ai_revert_all_changes">
				<?php wp_nonce_field( 'clickrank_ai_revert_all_nonce' ); ?>
				<button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-red-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
					<i class="fas fa-undo fa-fw mr-2"></i>
					<?php esc_html_e( 'Revert All', 'clickrank-ai' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>