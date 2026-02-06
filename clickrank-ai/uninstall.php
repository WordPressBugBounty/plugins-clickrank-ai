<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://clickrank.ai/
 * @since      3.0.0
 *
 * @package    ClickRank_AI
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/core/class-clickrank-ai-uninstaller.php';
ClickRank_AI_Uninstaller::uninstall();
