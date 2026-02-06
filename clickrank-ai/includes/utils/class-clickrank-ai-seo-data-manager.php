<?php
/**
 * SEO Data Manager - URL-based SEO data storage and retrieval.
 * Provides reliable URL-to-SEO-data mapping independent of WordPress post IDs.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/utils
 */
class ClickRank_AI_SEO_Data_Manager {

	/**
	 * Get the database table name
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'clickrank_ai_seo_data';
	}

	/**
	 * Create the SEO data table
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_url varchar(500) NOT NULL,
			page_url_normalized varchar(500) NOT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			page_title text DEFAULT NULL,
			meta_description text DEFAULT NULL,
			canonical_url varchar(500) DEFAULT NULL,
			page_schema longtext DEFAULT NULL,
			original_title text DEFAULT NULL,
			original_description text DEFAULT NULL,
			original_canonical varchar(500) DEFAULT NULL,
			original_schema longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_page_url_normalized (page_url_normalized),
			KEY idx_page_url (page_url),
			KEY idx_post_id (post_id),
			KEY idx_updated_at (updated_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			// Try direct CREATE TABLE as fallback
			$wpdb->query( $sql );

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
				error_log( 'ClickRank AI: Failed to create SEO data table. URL-based storage will not work.' );
				return false;
			}
		}

		ClickRank_AI_Logger::info( 'SEO data table created successfully' );
		return true;
	}

	/**
	 * Normalize URL for consistent storage and lookup
	 *
	 * @param string $url The URL to normalize
	 * @return string Normalized URL
	 */
	public static function normalize_url( $url ) {
		// Parse URL
		$parsed = wp_parse_url( $url );

		// Build normalized URL without query string and fragment
		$normalized = '';

		if ( isset( $parsed['scheme'] ) ) {
			$normalized .= strtolower( $parsed['scheme'] ) . '://';
		}

		if ( isset( $parsed['host'] ) ) {
			$normalized .= strtolower( $parsed['host'] );
		}

		if ( isset( $parsed['path'] ) ) {
			// Remove trailing slash for consistency
			$path = rtrim( $parsed['path'], '/' );
			// Keep root as /
			$normalized .= empty( $path ) ? '/' : $path;
		} else {
			$normalized .= '/';
		}

		return $normalized;
	}

	/**
	 * Save or update SEO data for a URL
	 *
	 * @param string $page_url The page URL
	 * @param array $data SEO data to save
	 * @param bool $save_revert Whether to save revert data
	 * @return bool|int False on failure, insert/update ID on success
	 */
	public static function save_seo_data( $page_url, $data, $save_revert = true ) {
		global $wpdb;
		$table_name = self::get_table_name();

		// Normalize URL
		$normalized_url = self::normalize_url( $page_url );

		// Check if record exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE page_url_normalized = %s",
			$normalized_url
		) );

		// Prepare data for insertion/update
		$save_data = [
			'page_url' => esc_url_raw( $page_url ),
			'page_url_normalized' => $normalized_url,
			'updated_at' => current_time( 'mysql' )
		];

		// Add post_id if we can resolve it
		$post_id = url_to_postid( $page_url );
		if ( $post_id ) {
			$save_data['post_id'] = $post_id;
		}

		// Add SEO fields if provided
		if ( isset( $data['page_title'] ) ) {
			$save_data['page_title'] = sanitize_text_field( $data['page_title'] );
		}
		if ( isset( $data['meta_description'] ) ) {
			$save_data['meta_description'] = sanitize_textarea_field( $data['meta_description'] );
		}
		if ( isset( $data['canonical_url'] ) ) {
			$save_data['canonical_url'] = esc_url_raw( $data['canonical_url'] );
		}
		if ( isset( $data['page_schema'] ) ) {
			$save_data['page_schema'] = wp_kses_post( $data['page_schema'] );
		}

		// Save revert data if requested and this is first time or explicit update
		if ( $save_revert && $existing ) {
			// Save original values only if not already saved
			if ( empty( $existing->original_title ) && ! empty( $existing->page_title ) ) {
				$save_data['original_title'] = $existing->page_title;
			}
			if ( empty( $existing->original_description ) && ! empty( $existing->meta_description ) ) {
				$save_data['original_description'] = $existing->meta_description;
			}
			if ( empty( $existing->original_canonical ) && ! empty( $existing->canonical_url ) ) {
				$save_data['original_canonical'] = $existing->canonical_url;
			}
			if ( empty( $existing->original_schema ) && ! empty( $existing->page_schema ) ) {
				$save_data['original_schema'] = $existing->page_schema;
			}
		}

		if ( $existing ) {
			// Update existing record
			$result = $wpdb->update(
				$table_name,
				$save_data,
				[ 'id' => $existing->id ],
				null,
				[ '%d' ]
			);

			ClickRank_AI_Logger::info( "Updated SEO data for URL: {$page_url} (ID: {$existing->id})" );
			return $result !== false ? $existing->id : false;
		} else {
			// Insert new record
			$save_data['created_at'] = current_time( 'mysql' );
			$result = $wpdb->insert( $table_name, $save_data );

			if ( $result ) {
				$insert_id = $wpdb->insert_id;
				ClickRank_AI_Logger::info( "Inserted SEO data for URL: {$page_url} (ID: {$insert_id})" );
				return $insert_id;
			}

			return false;
		}
	}

	/**
	 * Get SEO data by URL
	 *
	 * @param string $page_url The page URL
	 * @return object|null SEO data object or null if not found
	 */
	public static function get_seo_data( $page_url ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$normalized_url = self::normalize_url( $page_url );

		$data = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE page_url_normalized = %s",
			$normalized_url
		) );

		return $data;
	}

	/**
	 * Get SEO data by post ID (fallback method)
	 *
	 * @param int $post_id The post ID
	 * @return object|null SEO data object or null if not found
	 */
	public static function get_seo_data_by_post_id( $post_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$data = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE post_id = %d ORDER BY updated_at DESC LIMIT 1",
			$post_id
		) );

		return $data;
	}

	/**
	 * Delete SEO data by URL
	 *
	 * @param string $page_url The page URL
	 * @return bool True on success, false on failure
	 */
	public static function delete_seo_data( $page_url ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$normalized_url = self::normalize_url( $page_url );

		$result = $wpdb->delete(
			$table_name,
			[ 'page_url_normalized' => $normalized_url ],
			[ '%s' ]
		);

		if ( $result ) {
			ClickRank_AI_Logger::info( "Deleted SEO data for URL: {$page_url}" );
		}

		return $result !== false;
	}

	/**
	 * Revert SEO data to original values
	 *
	 * @param string $page_url The page URL
	 * @param array $fields Fields to revert (empty = all fields)
	 * @return bool True on success, false on failure
	 */
	public static function revert_seo_data( $page_url, $fields = [] ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$normalized_url = self::normalize_url( $page_url );

		// Get existing record
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE page_url_normalized = %s",
			$normalized_url
		) );

		if ( ! $existing ) {
			return false;
		}

		// Build revert data
		$revert_data = [];

		$field_map = [
			'page_title' => 'original_title',
			'meta_description' => 'original_description',
			'canonical_url' => 'original_canonical',
			'page_schema' => 'original_schema'
		];

		// If no specific fields, revert all
		if ( empty( $fields ) ) {
			$fields = array_keys( $field_map );
		}

		foreach ( $fields as $field ) {
			if ( isset( $field_map[ $field ] ) ) {
				$original_field = $field_map[ $field ];
				if ( isset( $existing->$original_field ) ) {
					$revert_data[ $field ] = $existing->$original_field;
					// Clear the original backup after reverting
					$revert_data[ $original_field ] = null;
				}
			}
		}

		if ( empty( $revert_data ) ) {
			return false;
		}

		// Update record with reverted values
		$result = $wpdb->update(
			$table_name,
			$revert_data,
			[ 'id' => $existing->id ],
			null,
			[ '%d' ]
		);

		if ( $result !== false ) {
			ClickRank_AI_Logger::info( "Reverted SEO data for URL: {$page_url}" );
		}

		return $result !== false;
	}

	/**
	 * Clean up old entries (optional maintenance)
	 *
	 * @param int $days Delete entries older than X days
	 * @return int Number of deleted rows
	 */
	public static function cleanup_old_entries( $days = 90 ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$result = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table_name} WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );

		if ( $result ) {
			ClickRank_AI_Logger::info( "Cleaned up {$result} old SEO data entries" );
		}

		return $result;
	}

	/**
	 * Get statistics about stored SEO data
	 *
	 * @return array Statistics
	 */
	public static function get_statistics() {
		global $wpdb;
		$table_name = self::get_table_name();

		$stats = [
			'total_entries' => 0,
			'with_title' => 0,
			'with_description' => 0,
			'with_schema' => 0,
			'with_canonical' => 0,
			'oldest_entry' => null,
			'newest_entry' => null
		];

		$stats['total_entries'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$stats['with_title'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE page_title IS NOT NULL AND page_title != ''" );
		$stats['with_description'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE meta_description IS NOT NULL AND meta_description != ''" );
		$stats['with_schema'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE page_schema IS NOT NULL AND page_schema != ''" );
		$stats['with_canonical'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE canonical_url IS NOT NULL AND canonical_url != ''" );
		$stats['oldest_entry'] = $wpdb->get_var( "SELECT created_at FROM {$table_name} ORDER BY created_at ASC LIMIT 1" );
		$stats['newest_entry'] = $wpdb->get_var( "SELECT updated_at FROM {$table_name} ORDER BY updated_at DESC LIMIT 1" );

		return $stats;
	}
}
