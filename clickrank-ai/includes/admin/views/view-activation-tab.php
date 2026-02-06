<?php
/**
 * Provides the view for the new Activation page.
 *
 * @link       https://clickrank.ai/
 * @since      3.1.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<form id="cr-activation-form" action="options.php" method="post">
	<?php settings_fields( ClickRank_AI_Settings::ACTIVATION_GROUP ); ?>

	<!-- API Key Section -->
	<div class="bg-white shadow-sm rounded-lg">
		<div class="p-6 border-b border-gray-200">
			<h2 class="text-xl font-bold text-gray-800"><?php esc_html_e( 'Plugin Activation', 'clickrank-ai' ); ?></h2>
			<p class="text-gray-600 mt-2"><?php esc_html_e( 'Activate the plugin by entering your API key to connect your site to the ClickRank.ai platform.', 'clickrank-ai' ); ?></p>
		</div>
		<div class="bg-gray-50 p-6">
            <div class="flex items-center justify-between mb-2">
                <label for="clickrank_ai_api_key" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Your API Key', 'clickrank-ai' ); ?></label>
                
                <!-- Tooltip Wrapper -->
                <div class="relative group flex items-center">
                    <i class="fas fa-question-circle text-gray-400"></i>
                    
                    <!-- Tooltip Content -->
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 bg-gray-800 text-white text-xs rounded-lg py-2 px-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <?php esc_html_e( 'Your API key can be found in your account dashboard on the ClickRank.ai website.', 'clickrank-ai' ); ?>
                        <a href="https://app.clickrank.ai/en/integration" class="text-blue-400 hover:text-blue-300 font-semibold" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Find your API key here.', 'clickrank-ai' ); ?></a>
                        <svg class="absolute text-gray-800 h-2 w-full left-0 top-full" x="0px" y="0px" viewBox="0 0 255 255" xml:space="preserve"><polygon class="fill-current" points="0,0 127.5,127.5 255,0"/></svg>
                    </div>
                </div>
            </div>
			<input type="text" id="clickrank_ai_api_key" name="clickrank_ai_api_key" value="<?php echo esc_attr( get_option( 'clickrank_ai_api_key' ) ); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="<?php esc_attr_e( 'Enter your API Key', 'clickrank-ai' ); ?>">
		</div>
        <div class="p-6 bg-gray-50 border-t border-gray-200 rounded-b-lg flex justify-end">
			<button type="submit" name="submit" id="submit-api" class="w-full sm:w-auto inline-flex justify-center items-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<i class="fas fa-key fa-fw"></i>
				<?php esc_html_e( 'Save & Activate Key', 'clickrank-ai' ); ?>
			</button>
		</div>
	</div>
</form>