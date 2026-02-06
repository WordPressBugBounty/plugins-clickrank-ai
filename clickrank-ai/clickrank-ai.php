<?php
/**
 * Plugin Name:      ClickRank - Ai SEO automation
 * Plugin URI:       https://clickrank.ai/wordpress
 * Description:      Integrates WordPress with the ClickRank.ai platform for AI-powered SEO automation, including titles, metas, schema, and more.
 * Version:          3.3.5
 * Author:           ClickRank.ai
 * Author URI:       https://clickrank.ai/
 * License:          GPLv2 or later
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      clickrank-ai
 * Domain Path:      /languages
 *
 * @package      ClickRank_AI
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants for the plugin.
 */
define( 'CLICKRANK_AI_VERSION', '3.3.5' );
define( 'CLICKRANK_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLICKRANK_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLICKRANK_AI_MENU_SLUG', 'clickrank-ai' );

/**
 * The code that runs during plugin activation.
 */
function activate_clickrank_ai() {
	require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/core/class-clickrank-ai-activator.php';
	ClickRank_AI_Activator::activate();
}

register_activation_hook( __FILE__, 'activate_clickrank_ai' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_clickrank_ai() {
	require_once CLICKRANK_AI_PLUGIN_DIR . 'includes/core/class-clickrank-ai-deactivator.php';
	ClickRank_AI_Deactivator::deactivate();
}

register_deactivation_hook( __FILE__, 'deactivate_clickrank_ai' );

/**
 * The core plugin class that initializes everything.
 */
require CLICKRANK_AI_PLUGIN_DIR . 'class-clickrank-ai.php';

/**
 * Begins execution of the plugin.
 */
function run_clickrank_ai() {
	$plugin = new ClickRank_AI();
	$plugin->run();
}

run_clickrank_ai();