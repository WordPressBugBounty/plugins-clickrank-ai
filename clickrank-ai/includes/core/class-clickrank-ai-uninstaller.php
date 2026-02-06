<?php
/**
 * Fired during plugin uninstallation.
 *
 * @link       https://clickrank.ai/
 * @since      3.0.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/core
 */
class ClickRank_AI_Uninstaller {

	/**
	 * The main uninstallation method.
	 *
	 * This method is called from uninstall.php when the plugin is deleted.
	 * It cleans up all options and the custom database table.
	 *
	 * @since    3.0.0
	 */
	public static function uninstall() {
		self::delete_options();
		self::drop_logs_table();
	}

	/**
	 * Deletes all plugin options from the wp_options table.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private static function delete_options() {
		$options_to_delete = [
			'clickrank_ai_api_key',
			'clickrank_ai_enable_title_opt',
			'clickrank_ai_enable_meta_opt',
			'clickrank_ai_enable_img_alt_opt',
			'clickrank_ai_enable_schema_opt',
			'clickrank_ai_enable_canonical_opt',
			'clickrank_ai_enable_link_title_opt',
		];

		foreach ( $options_to_delete as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Drops the custom logs table from the database.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private static function drop_logs_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'clickrank_ai_logs';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
