<?php
/**
 * Health Scorer class.
 *
 * Calculates a 0–100 health score based on plugin count, asset count,
 * DB query count, and autoloaded options size.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_Health_Scorer
 *
 * Produces a composite health score from four weighted dimensions:
 *   - Plugin count  (30 pts)
 *   - Asset count   (30 pts)
 *   - DB queries    (20 pts)
 *   - Autoload size (20 pts)
 */
class WPHM_Health_Scorer {

	/**
	 * Maximum points for plugin count dimension.
	 *
	 * @var int
	 */
	const PLUGIN_MAX = 30;

	/**
	 * Maximum points for asset count dimension.
	 *
	 * @var int
	 */
	const ASSET_MAX = 30;

	/**
	 * Maximum points for DB query dimension.
	 *
	 * @var int
	 */
	const DB_QUERY_MAX = 20;

	/**
	 * Maximum points for autoload size dimension.
	 *
	 * @var int
	 */
	const AUTOLOAD_MAX = 20;

	/**
	 * Transient key for cached score.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wphm_health_score';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Calculate the overall health score.
	 *
	 * Returns cached score if available. Otherwise computes fresh score
	 * and stores in a transient.
	 *
	 * @param bool $force_refresh Whether to ignore the cached value.
	 * @return array {
	 *     @type int   $total       Overall score 0–100.
	 *     @type int   $plugins     Plugin count score component.
	 *     @type int   $assets      Asset count score component.
	 *     @type int   $db_queries  DB query score component.
	 *     @type int   $autoload    Autoload size score component.
	 *     @type array $raw         Raw metric values.
	 * }
	 */
	public function get_score( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$raw = $this->collect_raw_metrics();

		$plugins    = $this->score_plugin_count( $raw['plugin_count'] );
		$assets     = $this->score_asset_count( $raw['asset_count'] );
		$db_queries = $this->score_db_queries( $raw['db_query_count'] );
		$autoload   = $this->score_autoload_size( $raw['autoload_size'] );

		$result = array(
			'total'      => $plugins + $assets + $db_queries + $autoload,
			'plugins'    => $plugins,
			'assets'     => $assets,
			'db_queries' => $db_queries,
			'autoload'   => $autoload,
			'raw'        => $raw,
		);

		set_transient( self::TRANSIENT_KEY, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Collect raw metric values from the current environment.
	 *
	 * @return array {
	 *     @type int $plugin_count  Number of active plugins.
	 *     @type int $asset_count   Number of enqueued JS + CSS handles.
	 *     @type int $db_query_count Number of DB queries (0 if SAVEQUERIES not enabled).
	 *     @type int $autoload_size  Total byte size of autoloaded options.
	 * }
	 */
	public function collect_raw_metrics(): array {
		return array(
			'plugin_count'   => $this->get_active_plugin_count(),
			'asset_count'    => $this->get_enqueued_asset_count(),
			'db_query_count' => $this->get_db_query_count(),
			'autoload_size'  => $this->get_autoload_size(),
		);
	}

	/**
	 * Get the number of active plugins.
	 *
	 * @return int
	 */
	public function get_active_plugin_count(): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}
		$active = get_option( 'active_plugins', array() );
		return is_array( $active ) ? count( $active ) : 0;
	}

	/**
	 * Get the count of enqueued JS and CSS assets.
	 *
	 * @return int
	 */
	public function get_enqueued_asset_count(): int {
		global $wp_scripts, $wp_styles;

		$count = 0;

		if ( $wp_scripts instanceof WP_Scripts ) {
			$count += count( $wp_scripts->queue );
		}

		if ( $wp_styles instanceof WP_Styles ) {
			$count += count( $wp_styles->queue );
		}

		return $count;
	}

	/**
	 * Get the DB query count.
	 *
	 * Only returns a value when SAVEQUERIES is already defined and true.
	 * Never force-enables SAVEQUERIES.
	 *
	 * @return int
	 */
	public function get_db_query_count(): int {
		global $wpdb;

		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			return 0;
		}

		if ( ! is_array( $wpdb->queries ) ) {
			return 0;
		}

		return count( $wpdb->queries );
	}

	/**
	 * Get total byte size of autoloaded options.
	 *
	 * @return int Total bytes.
	 */
	public function get_autoload_size(): int {
		global $wpdb;

		$cache_key = 'wphm_autoload_size';
		$result    = wp_cache_get( $cache_key, 'wphm' );
		if ( false === $result ) {
			$autoload_values = array( 'yes', 'on' );

			if ( function_exists( 'wp_autoload_values_to_autoload' ) ) {
				$autoload_values = wp_autoload_values_to_autoload();
			}

			if ( ! is_array( $autoload_values ) || empty( $autoload_values ) ) {
				$autoload_values = array( 'yes', 'on' );
			}

			$autoload_values = array_values(
				array_filter(
					array_map( 'strval', $autoload_values ),
					static function ( $value ) {
						return '' !== $value;
					}
				)
			);

			$placeholders = implode( ', ', array_fill( 0, count( $autoload_values ), '%s' ) );

			$query = $wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ($placeholders)",
				$autoload_values
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->get_var( $query );
			wp_cache_set( $cache_key, $result, 'wphm', HOUR_IN_SECONDS );
		}

		return $result ? absint( $result ) : 0;
	}

	/**
	 * Score the plugin count dimension (0–30 points).
	 *
	 * Thresholds:
	 *   0–10 active plugins  = 30 pts (excellent)
	 *  11–20 active plugins  = 25 pts
	 *  21–30 active plugins  = 20 pts
	 *  31–40 active plugins  = 10 pts
	 *  41+   active plugins  =  0 pts
	 *
	 * @param int $count Number of active plugins.
	 * @return int Score 0–30.
	 */
	public function score_plugin_count( int $count ): int {
		if ( $count <= 10 ) {
			return self::PLUGIN_MAX;
		}
		if ( $count <= 20 ) {
			return 25;
		}
		if ( $count <= 30 ) {
			return 20;
		}
		if ( $count <= 40 ) {
			return 10;
		}
		return 0;
	}

	/**
	 * Score the asset count dimension (0–30 points).
	 *
	 * Thresholds:
	 *   0–20 assets = 30 pts (excellent)
	 *  21–40 assets = 25 pts
	 *  41–60 assets = 15 pts
	 *  61–80 assets = 5 pts
	 *  81+   assets =  0 pts
	 *
	 * @param int $count Number of enqueued JS + CSS assets.
	 * @return int Score 0–30.
	 */
	public function score_asset_count( int $count ): int {
		if ( $count <= 20 ) {
			return self::ASSET_MAX;
		}
		if ( $count <= 40 ) {
			return 25;
		}
		if ( $count <= 60 ) {
			return 15;
		}
		if ( $count <= 80 ) {
			return 5;
		}
		return 0;
	}

	/**
	 * Score the DB query count dimension (0–20 points).
	 *
	 * Returns full marks if SAVEQUERIES is not enabled (we cannot measure).
	 *
	 * Thresholds:
	 *     0–50 queries  = 20 pts (excellent)
	 *   51–100 queries  = 15 pts
	 *  101–200 queries  = 10 pts
	 *  201–500 queries  = 5 pts
	 *  501+   queries   =  0 pts
	 *
	 * @param int $count Number of DB queries.
	 * @return int Score 0–20.
	 */
	public function score_db_queries( int $count ): int {
		// When SAVEQUERIES is off, count is 0 and we give full marks
		// because we cannot measure — not because queries are low.
		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			return self::DB_QUERY_MAX;
		}
		if ( $count <= 50 ) {
			return self::DB_QUERY_MAX;
		}
		if ( $count <= 100 ) {
			return 15;
		}
		if ( $count <= 200 ) {
			return 10;
		}
		if ( $count <= 500 ) {
			return 5;
		}
		return 0;
	}

	/**
	 * Score the autoload options size dimension (0–20 points).
	 *
	 * Thresholds:
	 *       0–500 KB  = 20 pts (excellent)
	 *   500 KB–1 MB   = 15 pts
	 *     1 MB–2 MB   = 10 pts
	 *     2 MB–5 MB   = 5 pts
	 *     5 MB+       =  0 pts
	 *
	 * @param int $bytes Total autoload size in bytes.
	 * @return int Score 0–20.
	 */
	public function score_autoload_size( int $bytes ): int {
		$kb = $bytes / 1024;
		$mb = $kb / 1024;

		if ( $kb <= 500 ) {
			return self::AUTOLOAD_MAX;
		}
		if ( $mb <= 1 ) {
			return 15;
		}
		if ( $mb <= 2 ) {
			return 10;
		}
		if ( $mb <= 5 ) {
			return 5;
		}
		return 0;
	}

	/**
	 * Get a human-readable label for the score.
	 *
	 * @param int $score Score 0–100.
	 * @return string Label: Excellent, Good, Fair, Poor, or Critical.
	 */
	public function get_score_label( int $score ): string {
		if ( $score >= 80 ) {
			return __( 'Excellent', 'health-radar' );
		}
		if ( $score >= 60 ) {
			return __( 'Good', 'health-radar' );
		}
		if ( $score >= 40 ) {
			return __( 'Fair', 'health-radar' );
		}
		if ( $score >= 20 ) {
			return __( 'Poor', 'health-radar' );
		}
		return __( 'Critical', 'health-radar' );
	}

	/**
	 * Get a CSS class name for the score color.
	 *
	 * @param int $score Score 0–100.
	 * @return string CSS class suffix: excellent, good, fair, poor, or critical.
	 */
	public function get_score_css_class( int $score ): string {
		if ( $score >= 80 ) {
			return 'excellent';
		}
		if ( $score >= 60 ) {
			return 'good';
		}
		if ( $score >= 40 ) {
			return 'fair';
		}
		if ( $score >= 20 ) {
			return 'poor';
		}
		return 'critical';
	}
}
