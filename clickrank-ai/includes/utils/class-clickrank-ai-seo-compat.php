<?php
/**
 * Handles compatibility with popular SEO plugins.
 *
 * @link       https://clickrank.ai/
 * @since      3.1.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/utils
 */
class ClickRank_AI_SEO_Compat {

	private static $wp_hook_added = false;
	private static $master_instance = null;
	private $homepage_description = '';
	private $homepage_title = '';

	public function __construct() {
		// Only add the wp hook once to prevent duplicate overrides
		if ( ! self::$wp_hook_added ) {
			// Store the first instance as the master instance
			self::$master_instance = $this;
			// Add hook using the master instance
			add_action( 'wp', [ self::$master_instance, 'init_overrides' ], 1 );
			self::$wp_hook_added = true;
		}
	}

	/**
	 * Initialize SEO overrides
	 */
	public function init_overrides() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// Improved homepage detection
		$current_url = home_url( $_SERVER['REQUEST_URI'] ?? '' );
		$home_url = home_url();
		$is_homepage = ( rtrim( $current_url, '/' ) === rtrim( $home_url, '/' ) );

		// Homepage overrides - improved detection
		if ( is_front_page() || is_home() || $is_homepage ) {
			$this->setup_homepage_overrides();
		}

		// Post overrides
		if ( is_singular() ) {
			$this->setup_post_overrides();
		}
	}

	/**
	 * Setup homepage overrides
	 */
	private function setup_homepage_overrides() {
		// Only check our ClickRank AI options - don't fallback to SEO plugin raw templates
		$title = get_option( '_clickrank_ai_homepage_title', '' );
		$description = get_option( '_clickrank_ai_homepage_description', '' );
		$schema = get_option( '_clickrank_ai_homepage_schema', '' );
		$canonical = get_option( '_clickrank_ai_homepage_canonical', '' );

		// Only override if we have actual ClickRank AI data
		// This prevents showing raw RankMath templates like %sitename% %sep%
		if ( ! empty( $title ) ) {
			// Store title for buffer processing
			$this->homepage_title = $title;
			$this->override_title( $title );
		}

		if ( ! empty( $description ) ) {
			// Store description for buffer processing
			$this->homepage_description = $description;
		}
		
		// Start output buffering if we have either title or description to process
		if ( ! empty( $title ) || ! empty( $description ) ) {
			add_action( 'template_redirect', [ $this, 'start_homepage_buffer' ], 1 );
		}

		// REMOVED: Schema output moved to end_buffer_and_clean() to avoid duplicates
		// Schema will be added through the buffer cleaning process

		if ( ! empty( $canonical ) ) {
			$this->override_canonical( $canonical );
		}

		// NOTE: Schema is NOT added here to avoid duplicates
		// Schema will be added via buffer processing in end_buffer_and_clean()
	}

	/**
	 * Setup post overrides
	 */
	private function setup_post_overrides() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Check for ClickRank AI data first, then SEO plugin data (for webhook compatibility)
		$title = get_post_meta( $post_id, '_clickrank_ai_seo_title', true );
		if ( empty( $title ) ) {
			$title_key = $this->get_seo_meta_key( 'title' );
			$title = get_post_meta( $post_id, $title_key, true );
		}

		$description = get_post_meta( $post_id, '_clickrank_ai_meta_description', true );
		if ( empty( $description ) ) {
			$desc_key = $this->get_seo_meta_key( 'description' );
			$description = get_post_meta( $post_id, $desc_key, true );
		}

		// Only override if we have actual content AND it doesn't contain template variables
		if ( ! empty( $title ) && ! $this->contains_template_variables( $title ) ) {
			$this->override_title( $title );
		}

		if ( ! empty( $description ) && ! $this->contains_template_variables( $description ) ) {
			$this->override_meta_description( $description );
		}
	}

	/**
	 * Check if content contains SEO plugin template variables
	 */
	private function contains_template_variables( $content ) {
		if ( empty( $content ) ) {
			return false;
		}

		// Common SEO plugin template variables
		$template_patterns = [
			'%sep%', '%sitename%', '%page%', '%title%', '%excerpt%', '%category%', '%tag%',
			'%author%', '%date%', '%modified%', '%%sitedesc%%', '%%focuskw%%', '%%primary_category%%',
			'%%term_title%%', '%%term_description%%', '%%post_title%%', '%%post_excerpt%%',
			'%%sitename%%', '%%sep%%', '%%page%%', '%siteTitle%', '%pageTitle%', '%separator%'
		];

		foreach ( $template_patterns as $pattern ) {
			if ( stripos( $content, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the appropriate meta key for a given SEO field based on active plugins.
	 *
	 * @param string $type 'title' or 'description'.
	 * @return string The meta key.
	 */
	public function get_seo_meta_key( $type = 'description' ) {
		$is_title = ( 'title' === $type );

		if ( defined( 'WPSEO_VERSION' ) ) {
			return $is_title ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return $is_title ? 'rank_math_title' : 'rank_math_description';
		}
		if ( function_exists( 'aioseo' ) ) {
			return $is_title ? 'aioseo_title' : 'aioseo_description';
		}

		// Fallback meta key.
		return $is_title ? '_clickrank_ai_seo_title' : '_clickrank_ai_meta_description';
	}

	public function override_title( $title ) {
		// Only override when we have actual content to prevent showing raw templates
		if ( empty( $title ) ) {
			return;
		}
		
		// For titles, preserve ampersands and decode any existing HTML entities
		$clean_title = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );

		// Use maximum possible priority to override ALL SEO plugins
		$max_priority = PHP_INT_MAX;
		
		// Yoast SEO overrides
		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'wpseo_opengraph_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'wpseo_twitter_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
		}
		
		// RankMath SEO overrides - comprehensive list
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			add_filter( 'rank_math/frontend/title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'rank_math/opengraph/facebook/title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'rank_math/opengraph/twitter/title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'rank_math_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'rank_math/head/title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
		}
		
		// All in One SEO overrides
		if ( function_exists( 'aioseo' ) ) {
			add_filter( 'aioseo_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'aioseo_facebook_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
			add_filter( 'aioseo_twitter_title', function() use ( $clean_title ) {
				return $clean_title;
			}, $max_priority );
		}

		// WordPress core title overrides with absolute maximum priority
		add_filter( 'pre_get_document_title', function() use ( $clean_title ) {
			return $clean_title;
		}, $max_priority );
		
		add_filter( 'wp_title', function() use ( $clean_title ) {
			return $clean_title;
		}, $max_priority );
		
		add_filter( 'document_title_parts', function( $parts ) use ( $clean_title ) {
			$parts['title'] = $clean_title;
			return $parts;
		}, $max_priority );
		
		add_filter( 'wp_get_document_title', function() use ( $clean_title ) {
			return $clean_title;
		}, $max_priority );

		// Additional JavaScript override for persistent SEO plugins
		if ( ! empty( $clean_title ) ) {
			add_action( 'wp_head', function() use ( $clean_title ) {
				?>
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					document.title = <?php echo wp_json_encode( $clean_title ); ?>;
				});
				</script>
				<?php
			}, 9999 );
		}
	}

	public function override_meta_description( $description ) {
		// Only override when we have actual content to prevent showing raw templates  
		if ( empty( $description ) ) {
			return;
		}
		
		$clean_description = html_entity_decode( wp_strip_all_tags( $description ), ENT_QUOTES, 'UTF-8' );

		// Override all SEO plugin descriptions with maximum priority
		$max_priority = PHP_INT_MAX;
		
		// Yoast SEO description overrides
		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_metadesc', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			add_filter( 'wpseo_opengraph_desc', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			add_filter( 'wpseo_twitter_description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
		}
		
		// RankMath description overrides - comprehensive list
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			add_filter( 'rank_math/frontend/description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			add_filter( 'rank_math/opengraph/facebook/description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			add_filter( 'rank_math/opengraph/twitter/description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			// Additional RankMath filters
			add_filter( 'rank_math_description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
		}
		
		// All in One SEO description overrides
		if ( function_exists( 'aioseo' ) ) {
			add_filter( 'aioseo_description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			add_filter( 'aioseo_facebook_description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
			add_filter( 'aioseo_twitter_description', function() use ( $clean_description ) {
				return $clean_description;
			}, $max_priority );
		}

		// No additional buffering needed - direct injection above handles it
		
	}


	/**
	 * Starts an output buffer to capture the HTML head.
	 */
	public function start_buffer() {
		ob_start();
	}

	/**
	 * Ends the buffer, cleans out other schema, adds our own, and prints.
	 *
	 * @param string $our_schema The schema provided by ClickRank.ai.
	 */
	public function end_buffer_and_clean( $our_schema, $our_description = '', $our_canonical = '', $our_title = '' ) {
		$html = ob_get_clean();

		if ( empty( $html ) ) {
			return;
		}

		// COMPREHENSIVE META TAG REMOVAL - Remove ALL duplicate meta tags
		// This ensures no conflicts with Yoast, RankMath, AIOSEO, themes, or other plugins

		// 1. Remove ALL schema markup (from any source)
		$html = preg_replace( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is', '', $html );

		// 2. Remove ALL meta descriptions
		$html = preg_replace( '/<meta[^>]+name=["\']description["\'][^>]*>/i', '', $html );

		// 3. Remove ALL canonical tags
		$html = preg_replace( '/<link[^>]+rel=["\']canonical["\'][^>]*>/i', '', $html );

		// 4. Remove ALL Open Graph meta tags
		$html = preg_replace( '/<meta[^>]+property=["\']og:title["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+property=["\']og:description["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+property=["\']og:url["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+property=["\']og:type["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+property=["\']og:image["\'][^>]*>/i', '', $html );

		// 5. Remove ALL Twitter Card meta tags
		$html = preg_replace( '/<meta[^>]+name=["\']twitter:title["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+name=["\']twitter:description["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+name=["\']twitter:card["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<meta[^>]+name=["\']twitter:image["\'][^>]*>/i', '', $html );

		// Log removal for debugging
		ClickRank_AI_Logger::debug( 'Removed all duplicate meta tags and schema to prevent conflicts' );

		// NOW ADD OUR CLEAN META TAGS (only one of each)
		$meta_tags = '';

		// Title for social media (if provided)
		if ( ! empty( $our_title ) ) {
			$clean_title = esc_attr( html_entity_decode( wp_strip_all_tags( $our_title ), ENT_QUOTES, 'UTF-8' ) );
			$meta_tags .= '<meta property="og:title" content="' . $clean_title . '">' . "\n";
			$meta_tags .= '<meta name="twitter:title" content="' . $clean_title . '">' . "\n";
		}

		// Meta description
		if ( ! empty( $our_description ) ) {
			$clean_desc = esc_attr( html_entity_decode( wp_strip_all_tags( $our_description ), ENT_QUOTES, 'UTF-8' ) );
			$meta_tags .= '<meta name="description" content="' . $clean_desc . '">' . "\n";
			$meta_tags .= '<meta property="og:description" content="' . $clean_desc . '">' . "\n";
			$meta_tags .= '<meta name="twitter:description" content="' . $clean_desc . '">' . "\n";
		}

		// Canonical URL + Open Graph URL
		if ( ! empty( $our_canonical ) ) {
			$clean_canonical = esc_url( $our_canonical );
			$meta_tags .= '<link rel="canonical" href="' . $clean_canonical . '" />' . "\n";
			$meta_tags .= '<meta property="og:url" content="' . $clean_canonical . '" />' . "\n";
		}

		// Open Graph type
		if ( is_front_page() ) {
			$meta_tags .= '<meta property="og:type" content="website" />' . "\n";
		} else {
			$meta_tags .= '<meta property="og:type" content="article" />' . "\n";
		}

		// Twitter card type
		$meta_tags .= '<meta name="twitter:card" content="summary_large_image" />' . "\n";

		// Schema markup
		if ( ! empty( $our_schema ) ) {
			$decoded = json_decode( $our_schema, true );
			if ( null !== $decoded && json_last_error() === JSON_ERROR_NONE ) {
				// Properly escape the JSON for HTML context
				$escaped_schema = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				$meta_tags .= '<script type="application/ld+json" class="clickrank-ai-schema" data-source="clickrank-ai">' . $escaped_schema . '</script>' . "\n";
				ClickRank_AI_Logger::debug( 'Added ClickRank schema to page' );
			}
		}

		// Insert all meta tags before closing </head>
		if ( ! empty( $meta_tags ) ) {
			$pos = strripos( $html, '</head>' );
			if ( false !== $pos ) {
				$html = substr_replace( $html, $meta_tags, $pos, 0 );
			} else {
				$html .= $meta_tags;
			}
		}

		echo $html;
	}

	/**
	 * Ends the buffer, removes duplicate meta descriptions, and outputs our description.
	 *
	 * @param string $our_description The description provided by ClickRank.ai.
	 */
	public function end_buffer_and_output_clean_description( $our_description ) {
		$html = ob_get_clean();

		// Always add our meta description, even if no buffer content
		$clean_description = html_entity_decode( wp_strip_all_tags( $our_description ), ENT_QUOTES, 'UTF-8' );
		
		if ( ! empty( $html ) ) {
			// Remove any existing meta description tags (including from SEO plugins)
			$html = preg_replace( '/<meta[^>]+name=["\']description["\'][^>]*>/i', '', $html );
			
			// Add our meta description
			$meta_tag = '<meta name="description" content="' . esc_attr( $clean_description ) . '>' . "\n";
			
			// Insert before closing head tag or at the end
			$pos = strripos( $html, '</head>' );
			if ( false !== $pos ) {
				$html = substr_replace( $html, $meta_tag, $pos, 0 );
			} else {
				$html .= $meta_tag;
			}
			echo $html;
		} else {
			// If no buffer content, just output our meta tag directly
			if ( ! empty( $clean_description ) ) {
				echo '<meta name="description" content="' . esc_attr( $clean_description ) . '>' . "\n";
			}
		}
	}

	/**
	 * Ends the buffer and flushes the content without modification.
	 */
	public function end_buffer_and_flush() {
		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}


	public function override_canonical( $canonical_url ) {
		if ( empty( $canonical_url ) ) {
			return;
		}

		$clean_url = esc_url( $canonical_url );

		// Override all SEO plugin canonical URLs using filters (max priority)
		$max_priority = PHP_INT_MAX;

		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_canonical', function() use ( $clean_url ) {
				return $clean_url;
			}, $max_priority );
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			add_filter( 'rank_math/frontend/canonical', function() use ( $clean_url ) {
				return $clean_url;
			}, $max_priority );
		}

		if ( function_exists( 'aioseo' ) ) {
			add_filter( 'aioseo_canonical_url', function() use ( $clean_url ) {
				return $clean_url;
			}, $max_priority );
		}

		// Override WordPress default canonical (for when no SEO plugin is active)
		add_filter( 'get_canonical_url', function() use ( $clean_url ) {
			return $clean_url;
		}, $max_priority );

		// REMOVED: Direct canonical tag output
		// Canonical will be added via end_buffer_and_clean() to prevent duplicates
		// This ensures only ONE canonical tag appears on the page
	}

	/**
	 * Updates the homepage title in the options of popular SEO plugins.
	 */
	public function update_homepage_title( $title ) {
		// Always store in our basic option for override system to use
		update_option( '_clickrank_ai_homepage_title', $title );
		ClickRank_AI_Logger::add( 'INFO', "Homepage title stored in database: {$title}" );
		
		// Also store in SEO plugin-specific locations for backup
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			$options['title-home-wpseo'] = $title;
			update_option( 'wpseo_titles', $options );
		}
		
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			// RankMath stores homepage title differently - try multiple possible keys
			$titles_options = get_option( 'rank-math-options-titles', [] );
			$titles_options['homepage_title'] = $title;
			update_option( 'rank-math-options-titles', $titles_options );
			
			// Also try the main options
			$main_options = get_option( 'rank-math-options-general', [] );
			$main_options['homepage_title'] = $title;
			update_option( 'rank-math-options-general', $main_options );
			
			// And try RankMath's specific homepage title option
			update_option( 'rank_math_homepage_title', $title );
		}
		
		// Log the update for debugging
		ClickRank_AI_Logger::add( 'INFO', "Homepage title updated in database: $title" );
	}

	/**
	 * Updates the homepage meta description in the options of popular SEO plugins.
	 */
	public function update_homepage_description( $description ) {
		// Always store in our basic option for override system to use
		update_option( '_clickrank_ai_homepage_description', $description );
		
		// Also store in SEO plugin-specific locations for backup
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			$options['metadesc-home-wpseo'] = $description;
			update_option( 'wpseo_titles', $options );
		}
		
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			// RankMath stores homepage description differently - try multiple possible keys
			$titles_options = get_option( 'rank-math-options-titles', [] );
			$titles_options['homepage_description'] = $description;
			update_option( 'rank-math-options-titles', $titles_options );
			
			// Also try the main options
			$main_options = get_option( 'rank-math-options-general', [] );
			$main_options['homepage_description'] = $description;
			update_option( 'rank-math-options-general', $main_options );
			
			// And try RankMath's specific homepage description option
			update_option( 'rank_math_homepage_description', $description );
		}
		
		// Log the update for debugging
		ClickRank_AI_Logger::add( 'INFO', "Homepage meta description updated in database: $description" );
	}

	/**
	 * Gets the homepage title from the options of popular SEO plugins.
	 */
	public function get_homepage_title() {
		$title = '';
		
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			$title = $options['title-home-wpseo'] ?? '';
		} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
			// Try multiple RankMath option locations
			$titles_options = get_option( 'rank-math-options-titles', [] );
			$title = $titles_options['homepage_title'] ?? '';
			
			if ( empty( $title ) ) {
				$main_options = get_option( 'rank-math-options-general', [] );
				$title = $main_options['homepage_title'] ?? '';
			}
			
			if ( empty( $title ) ) {
				$title = get_option( 'rank_math_homepage_title', '' );
			}
			
		} else {
			$title = get_option( '_clickrank_ai_homepage_title', '' );
		}
		
		return $title;
	}

	/**
	 * Gets the homepage meta description from the options of popular SEO plugins.
	 */
	public function get_homepage_description() {
		$description = '';
		
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			$description = $options['metadesc-home-wpseo'] ?? '';
		} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
			// Try multiple RankMath option locations
			$titles_options = get_option( 'rank-math-options-titles', [] );
			$description = $titles_options['homepage_description'] ?? '';
			
			if ( empty( $description ) ) {
				$main_options = get_option( 'rank-math-options-general', [] );
				$description = $main_options['homepage_description'] ?? '';
			}
			
			if ( empty( $description ) ) {
				$description = get_option( 'rank_math_homepage_description', '' );
			}
			
		} else {
			$description = get_option( '_clickrank_ai_homepage_description', '' );
		}
		
		return $description;
	}

	/**
	 * Gets the appropriate meta key for taxonomy fields based on active plugins.
	 *
	 * @param string $type 'title' or 'description'.
	 * @param string $taxonomy The taxonomy name.
	 * @return string The meta key.
	 */
	public function get_taxonomy_meta_key( $type = 'description', $taxonomy = '' ) {
		$is_title = ( 'title' === $type );
		
		// For taxonomies, we generally use standard meta keys since most SEO plugins
		// handle taxonomy meta differently than post meta
		if ( defined( 'WPSEO_VERSION' ) ) {
			return $is_title ? 'wpseo_title' : 'wpseo_desc';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return $is_title ? 'rank_math_title' : 'rank_math_description';
		}
		if ( function_exists( 'aioseo' ) ) {
			return $is_title ? 'aioseo_title' : 'aioseo_description';
		}
		
		// Fallback meta key
		return $is_title ? '_clickrank_ai_seo_title' : '_clickrank_ai_meta_description';
	}

	/**
	 * Updates taxonomy archive title in SEO plugin options.
	 *
	 * @param string $title The title.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function update_taxonomy_archive_title( $title, $taxonomy ) {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			$options["title-tax-{$taxonomy}"] = $title;
			update_option( 'wpseo_titles', $options );
		} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
			$options = get_option( 'rank-math-options-titles', [] );
			$options["{$taxonomy}_title"] = $title;
			update_option( 'rank-math-options-titles', $options );
		} else {
			update_option( "_clickrank_ai_{$taxonomy}_archive_title", $title );
		}
	}

	/**
	 * Updates taxonomy archive meta description in SEO plugin options.
	 *
	 * @param string $description The description.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function update_taxonomy_archive_description( $description, $taxonomy ) {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			$options["metadesc-tax-{$taxonomy}"] = $description;
			update_option( 'wpseo_titles', $options );
		} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
			$options = get_option( 'rank-math-options-titles', [] );
			$options["{$taxonomy}_description"] = $description;
			update_option( 'rank-math-options-titles', $options );
		} else {
			update_option( "_clickrank_ai_{$taxonomy}_archive_description", $description );
		}
	}

	/**
	 * Gets taxonomy archive title from SEO plugin options.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @return string The title.
	 */
	public function get_taxonomy_archive_title( $taxonomy ) {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			return $options["title-tax-{$taxonomy}"] ?? '';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$options = get_option( 'rank-math-options-titles', [] );
			return $options["{$taxonomy}_title"] ?? '';
		}
		return get_option( "_clickrank_ai_{$taxonomy}_archive_title", '' );
	}

	/**
	 * Gets taxonomy archive meta description from SEO plugin options.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @return string The description.
	 */
	public function get_taxonomy_archive_description( $taxonomy ) {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$options = get_option( 'wpseo_titles', [] );
			return $options["metadesc-tax-{$taxonomy}"] ?? '';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$options = get_option( 'rank-math-options-titles', [] );
			return $options["{$taxonomy}_description"] ?? '';
		}
		return get_option( "_clickrank_ai_{$taxonomy}_archive_description", '' );
	}

	/**
	 * Start output buffering for homepage to clean title and meta descriptions
	 */
	public function start_homepage_buffer() {
		ob_start( [ $this, 'clean_homepage_head_tags' ] );
	}

	/**
	 * Clean homepage HTML and replace title and meta description with ours
	 */
	public function clean_homepage_head_tags( $html ) {
		$has_title = ! empty( $this->homepage_title );
		$has_description = ! empty( $this->homepage_description );
		
		if ( ! $has_title && ! $has_description ) {
			return $html;
		}

		// Handle title replacement
		if ( $has_title ) {
			$clean_title = html_entity_decode( wp_strip_all_tags( $this->homepage_title ), ENT_QUOTES, 'UTF-8' );
			// Remove all existing title tags
			$html = preg_replace( '/<title[^>]*>.*?<\/title>/is', '', $html );
			// Add our title tag
			$title_tag = '<title>' . esc_attr( $clean_title ) . '</title>';
			$html = preg_replace( '/(<head[^>]*>)/i', '$1' . "\n" . $title_tag, $html );
		}

		// Handle meta description replacement  
		if ( $has_description ) {
			// Remove all existing meta description tags
			$html = preg_replace( '/<meta[^>]+name=["\']description["\'][^>]*>/i', '', $html );
			// Add our single meta description
			$clean_description = html_entity_decode( wp_strip_all_tags( $this->homepage_description ), ENT_QUOTES, 'UTF-8' );
			$meta_tag = '<meta name="description" content="' . esc_attr( $clean_description ) . '">';
			$html = preg_replace( '/(<head[^>]*>)/i', '$1' . "\n" . $meta_tag, $html );
		}
		
		return $html;
	}
}