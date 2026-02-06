<?php
/**
 * Migration tool for moving post meta data to URL-based table.
 * Handles bulk migration of existing SEO data.
 *
 * @link       https://clickrank.ai/
 * @since      3.2.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/utils
 */
class ClickRank_AI_Migration {

	/**
	 * Migrate all posts/pages from post meta to URL table
	 *
	 * @param int $batch_size Number of posts to process per batch
	 * @param int $offset Starting offset
	 * @return array Migration results
	 */
	public static function migrate_posts_to_url_table( $batch_size = 100, $offset = 0 ) {
		$compat = new ClickRank_AI_SEO_Compat();
		$results = [
			'processed' => 0,
			'migrated' => 0,
			'skipped' => 0,
			'errors' => []
		];

		// Get all published posts and pages
		$args = [
			'post_type' => 'any',
			'post_status' => 'publish',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
			'orderby' => 'ID',
			'order' => 'ASC'
		];

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {
			$results['processed']++;

			try {
				// Get post URL
				$post_url = get_permalink( $post->ID );
				if ( ! $post_url ) {
					$results['skipped']++;
					continue;
				}

				// Check if already migrated
				$existing = ClickRank_AI_SEO_Data_Manager::get_seo_data( $post_url );
				if ( $existing && ! empty( $existing->page_title ) ) {
					$results['skipped']++;
					continue; // Already migrated
				}

				// Gather SEO data from post meta
				$seo_data = [];

				// Title
				$title_key = $compat->get_seo_meta_key( 'title' );
				$title = get_post_meta( $post->ID, $title_key, true );
				if ( ! empty( $title ) ) {
					$seo_data['page_title'] = $title;
				}

				// Description
				$desc_key = $compat->get_seo_meta_key( 'description' );
				$description = get_post_meta( $post->ID, $desc_key, true );
				if ( ! empty( $description ) ) {
					$seo_data['meta_description'] = $description;
				}

				// Schema
				$schema = get_post_meta( $post->ID, '_clickrank_ai_page_schema', true );
				if ( ! empty( $schema ) ) {
					$seo_data['page_schema'] = $schema;
				}

				// Canonical
				$canonical = get_post_meta( $post->ID, '_clickrank_ai_canonical_url', true );
				if ( ! empty( $canonical ) ) {
					$seo_data['canonical_url'] = $canonical;
				}

				// Only migrate if we have data
				if ( empty( $seo_data ) ) {
					$results['skipped']++;
					continue;
				}

				// Migrate to URL table
				$result = ClickRank_AI_SEO_Data_Manager::save_seo_data( $post_url, $seo_data, false );

				if ( $result ) {
					$results['migrated']++;
					ClickRank_AI_Logger::debug( "Migrated post {$post->ID}: {$post->post_title}" );
				} else {
					$results['errors'][] = "Failed to migrate post {$post->ID}";
				}

			} catch ( Exception $e ) {
				$results['errors'][] = "Error migrating post {$post->ID}: " . $e->getMessage();
			}
		}

		return $results;
	}

	/**
	 * Migrate homepage data to URL table
	 *
	 * @return bool Success status
	 */
	public static function migrate_homepage_to_url_table() {
		$seo_data = [];

		// Title
		$title = get_option( '_clickrank_ai_homepage_title', '' );
		if ( ! empty( $title ) ) {
			$seo_data['page_title'] = $title;
		}

		// Description
		$description = get_option( '_clickrank_ai_homepage_description', '' );
		if ( ! empty( $description ) ) {
			$seo_data['meta_description'] = $description;
		}

		// Schema
		$schema = get_option( '_clickrank_ai_homepage_schema', '' );
		if ( ! empty( $schema ) ) {
			$seo_data['page_schema'] = $schema;
		}

		// Canonical
		$canonical = get_option( '_clickrank_ai_homepage_canonical', '' );
		if ( ! empty( $canonical ) ) {
			$seo_data['canonical_url'] = $canonical;
		}

		if ( empty( $seo_data ) ) {
			return false; // No data to migrate
		}

		// Migrate to URL table
		$result = ClickRank_AI_SEO_Data_Manager::save_seo_data( home_url( '/' ), $seo_data, false );

		if ( $result ) {
			ClickRank_AI_Logger::info( 'Homepage migrated to URL table' );
		}

		return $result !== false;
	}

	/**
	 * Migrate taxonomy terms to URL table
	 *
	 * @param int $batch_size Number of terms to process per batch
	 * @param int $offset Starting offset
	 * @return array Migration results
	 */
	public static function migrate_taxonomies_to_url_table( $batch_size = 100, $offset = 0 ) {
		$compat = new ClickRank_AI_SEO_Compat();
		$results = [
			'processed' => 0,
			'migrated' => 0,
			'skipped' => 0,
			'errors' => []
		];

		// Get all public taxonomies
		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$args = [
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'number' => $batch_size,
				'offset' => $offset
			];

			$terms = get_terms( $args );

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$results['processed']++;

				try {
					// Get term URL
					$term_url = get_term_link( $term );
					if ( is_wp_error( $term_url ) ) {
						$results['skipped']++;
						continue;
					}

					// Check if already migrated
					$existing = ClickRank_AI_SEO_Data_Manager::get_seo_data( $term_url );
					if ( $existing && ! empty( $existing->page_title ) ) {
						$results['skipped']++;
						continue;
					}

					// Gather SEO data from term meta
					$seo_data = [];

					// Description
					$desc_key = $compat->get_taxonomy_meta_key( 'description', $taxonomy );
					$description = get_term_meta( $term->term_id, $desc_key, true );
					if ( ! empty( $description ) ) {
						$seo_data['meta_description'] = $description;
					}

					// Title (use term name if no custom title)
					$seo_data['page_title'] = $term->name;

					// Schema
					$schema = get_term_meta( $term->term_id, '_clickrank_ai_page_schema', true );
					if ( ! empty( $schema ) ) {
						$seo_data['page_schema'] = $schema;
					}

					// Canonical
					$canonical = get_term_meta( $term->term_id, '_clickrank_ai_canonical_url', true );
					if ( ! empty( $canonical ) ) {
						$seo_data['canonical_url'] = $canonical;
					}

					// Migrate to URL table
					$result = ClickRank_AI_SEO_Data_Manager::save_seo_data( $term_url, $seo_data, false );

					if ( $result ) {
						$results['migrated']++;
						ClickRank_AI_Logger::debug( "Migrated term {$term->term_id}: {$term->name}" );
					} else {
						$results['errors'][] = "Failed to migrate term {$term->term_id}";
					}

				} catch ( Exception $e ) {
					$results['errors'][] = "Error migrating term {$term->term_id}: " . $e->getMessage();
				}
			}
		}

		return $results;
	}

	/**
	 * Get total number of posts that need migration
	 *
	 * @return int Total count
	 */
	public static function get_posts_migration_count() {
		$args = [
			'post_type' => 'any',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids'
		];

		$posts = get_posts( $args );
		return count( $posts );
	}

	/**
	 * Get total number of terms that need migration
	 *
	 * @return int Total count
	 */
	public static function get_terms_migration_count() {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
		$total = 0;

		foreach ( $taxonomies as $taxonomy ) {
			$count = wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
			if ( ! is_wp_error( $count ) ) {
				$total += $count;
			}
		}

		return $total;
	}

	/**
	 * Clean up post meta after successful migration
	 * CAUTION: Only run after verifying migration success
	 *
	 * @param bool $dry_run If true, only count what would be deleted
	 * @return array Cleanup results
	 */
	public static function cleanup_post_meta( $dry_run = true ) {
		global $wpdb;
		$results = [
			'deleted' => 0,
			'dry_run' => $dry_run
		];

		$meta_keys = [
			'_yoast_wpseo_title',
			'_yoast_wpseo_metadesc',
			'rank_math_title',
			'rank_math_description',
			'_aioseo_title',
			'_aioseo_description',
			'_clickrank_ai_page_schema',
			'_clickrank_ai_canonical_url',
			'_clickrank_ai_link_titles',
			'_clickrank_ai_revert_data'
		];

		foreach ( $meta_keys as $meta_key ) {
			if ( $dry_run ) {
				// Just count
				$count = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
					$meta_key
				) );
				$results['deleted'] += (int) $count;
			} else {
				// Actually delete
				$deleted = $wpdb->delete(
					$wpdb->postmeta,
					[ 'meta_key' => $meta_key ],
					[ '%s' ]
				);
				$results['deleted'] += $deleted;
			}
		}

		if ( ! $dry_run ) {
			ClickRank_AI_Logger::warning( "Cleaned up {$results['deleted']} post meta entries" );
		}

		return $results;
	}

	/**
	 * Clean up homepage options after migration
	 *
	 * @param bool $dry_run If true, only report what would be deleted
	 * @return array Cleanup results
	 */
	public static function cleanup_homepage_options( $dry_run = true ) {
		$results = [
			'deleted' => 0,
			'dry_run' => $dry_run
		];

		$options = [
			'_clickrank_ai_homepage_title',
			'_clickrank_ai_homepage_description',
			'_clickrank_ai_homepage_schema',
			'_clickrank_ai_homepage_canonical',
			'_clickrank_ai_homepage_revert_data'
		];

		foreach ( $options as $option ) {
			if ( get_option( $option ) !== false ) {
				$results['deleted']++;
				if ( ! $dry_run ) {
					delete_option( $option );
				}
			}
		}

		if ( ! $dry_run ) {
			ClickRank_AI_Logger::warning( "Cleaned up {$results['deleted']} homepage options" );
		}

		return $results;
	}

	/**
	 * Run full migration (all content types)
	 *
	 * @param int $batch_size Batch size for processing
	 * @return array Complete migration results
	 */
	public static function run_full_migration( $batch_size = 100 ) {
		$results = [
			'started_at' => current_time( 'mysql' ),
			'homepage' => false,
			'posts' => [ 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => [] ],
			'taxonomies' => [ 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => [] ],
			'completed_at' => null
		];

		ClickRank_AI_Logger::info( 'Starting full migration to URL table' );

		// Migrate homepage
		$results['homepage'] = self::migrate_homepage_to_url_table();

		// Migrate posts in batches
		$total_posts = self::get_posts_migration_count();
		$offset = 0;

		while ( $offset < $total_posts ) {
			$batch_results = self::migrate_posts_to_url_table( $batch_size, $offset );
			$results['posts']['processed'] += $batch_results['processed'];
			$results['posts']['migrated'] += $batch_results['migrated'];
			$results['posts']['skipped'] += $batch_results['skipped'];
			$results['posts']['errors'] = array_merge( $results['posts']['errors'], $batch_results['errors'] );

			$offset += $batch_size;

			// Prevent timeouts on large sites
			if ( $offset % 500 === 0 ) {
				sleep( 1 );
			}
		}

		// Migrate taxonomies
		$results['taxonomies'] = self::migrate_taxonomies_to_url_table( $batch_size );

		$results['completed_at'] = current_time( 'mysql' );

		ClickRank_AI_Logger::info( sprintf(
			'Migration completed: %d posts migrated, %d taxonomies migrated',
			$results['posts']['migrated'],
			$results['taxonomies']['migrated']
		) );

		// Save migration results
		update_option( 'clickrank_ai_migration_results', $results );

		return $results;
	}

	/**
	 * Get migration status
	 *
	 * @return array Status information
	 */
	public static function get_migration_status() {
		$status = [
			'url_table_entries' => 0,
			'posts_count' => self::get_posts_migration_count(),
			'terms_count' => self::get_terms_migration_count(),
			'last_migration' => get_option( 'clickrank_ai_migration_results', null ),
			'post_meta_mode' => get_option( 'clickrank_ai_use_post_meta', true )
		];

		// Get URL table count
		$stats = ClickRank_AI_SEO_Data_Manager::get_statistics();
		$status['url_table_entries'] = $stats['total_entries'];

		// Calculate migration percentage
		$total_content = $status['posts_count'] + $status['terms_count'] + 1; // +1 for homepage
		if ( $total_content > 0 ) {
			$status['migration_percentage'] = round( ( $status['url_table_entries'] / $total_content ) * 100, 2 );
		} else {
			$status['migration_percentage'] = 0;
		}

		return $status;
	}
}
