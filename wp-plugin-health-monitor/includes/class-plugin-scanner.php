<?php
/**
 * Plugin Scanner class.
 *
 * Module 1 — Plugin Conflict Detector.
 * Inspects $wp_scripts / $wp_styles for duplicate src values and
 * $wp_filter for same-hook callbacks registered by different plugins.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_Plugin_Scanner
 *
 * Detects conflicts between active plugins by analyzing registered
 * scripts, styles, and filter hooks.
 */
class WPHM_Plugin_Scanner {

	/**
	 * Transient key for cached results.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wphm_conflict_results';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Run the conflict scan.
	 *
	 * Returns cached results if available, otherwise performs a fresh scan.
	 *
	 * @param bool $force_refresh Whether to bypass the cache.
	 * @return array {
	 *     @type array $duplicate_assets Array of duplicate asset conflicts.
	 *     @type array $hook_conflicts   Array of hook conflicts.
	 * }
	 */
	public function scan( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$results = array(
			'duplicate_assets' => $this->find_duplicate_assets(),
			'hook_conflicts'   => $this->find_hook_conflicts(),
		);

		set_transient( self::TRANSIENT_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Find scripts and styles with duplicate src values loaded by different plugins.
	 *
	 * Inspects $wp_scripts and $wp_styles globals for duplicate `src` values
	 * across different handles, then maps handles back to plugin directories.
	 *
	 * @return array Array of conflict arrays, each containing handle, plugins, type, severity.
	 */
	public function find_duplicate_assets(): array {
		$duplicates = array();

		$duplicates = array_merge(
			$duplicates,
			$this->find_duplicate_sources( 'scripts' ),
			$this->find_duplicate_sources( 'styles' )
		);

		return $duplicates;
	}

	/**
	 * Find duplicate sources in a specific dependency type.
	 *
	 * @param string $type Either 'scripts' or 'styles'.
	 * @return array Array of conflict entries.
	 */
	private function find_duplicate_sources( string $type ): array {
		global $wp_scripts, $wp_styles;

		$registry = 'scripts' === $type ? $wp_scripts : $wp_styles;

		if ( ! $registry instanceof WP_Dependencies ) {
			return array();
		}

		$src_map    = array();
		$conflicts  = array();
		$asset_type = 'scripts' === $type ? 'script' : 'style';

		foreach ( $registry->registered as $handle => $dep ) {
			if ( empty( $dep->src ) ) {
				continue;
			}

			$src = $dep->src;

			if ( ! isset( $src_map[ $src ] ) ) {
				$src_map[ $src ] = array();
			}

			$src_map[ $src ][] = $handle;
		}

		foreach ( $src_map as $src => $handles ) {
			if ( count( $handles ) < 2 ) {
				continue;
			}

			$plugins = array();
			foreach ( $handles as $handle ) {
				$plugin = $this->guess_plugin_from_src( $src );
				if ( $plugin && ! in_array( $plugin, $plugins, true ) ) {
					$plugins[] = $plugin;
				}
			}

			// Only flag as conflict if multiple handles point to same src.
			$conflicts[] = array(
				'handle'   => implode( ', ', $handles ),
				'src'      => $src,
				'plugins'  => $plugins,
				'type'     => 'duplicate_' . $asset_type,
				'severity' => 'warning',
			);
		}

		return $conflicts;
	}

	/**
	 * Find hook conflicts where the same hook has callbacks from 2+ different plugins.
	 *
	 * Iterates $wp_filter to identify hooks where callbacks originate from
	 * at least two different plugin directories.
	 *
	 * @return array Array of conflict entries.
	 */
	public function find_hook_conflicts(): array {
		global $wp_filter;

		if ( ! is_array( $wp_filter ) ) {
			return array();
		}

		$conflicts = array();

		foreach ( $wp_filter as $hook_name => $hook_obj ) {
			if ( ! $hook_obj instanceof WP_Hook ) {
				continue;
			}

			$plugin_callbacks = array();

			foreach ( $hook_obj->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_id => $callback_data ) {
					$plugin = $this->get_plugin_from_callback( $callback_data['function'] );
					if ( $plugin && ! in_array( $plugin, $plugin_callbacks, true ) ) {
						$plugin_callbacks[] = $plugin;
					}
				}
			}

			if ( count( $plugin_callbacks ) >= 2 ) {
				$conflicts[] = array(
					'hook'     => $hook_name,
					'plugins'  => $plugin_callbacks,
					'type'     => 'hook_conflict',
					'severity' => 'warning',
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Guess the plugin directory name from a script/style src URL.
	 *
	 * @param string $src The asset source URL.
	 * @return string Plugin directory name, or empty string if not determinable.
	 */
	private function guess_plugin_from_src( string $src ): string {
		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $src, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Determine which plugin a callback function belongs to.
	 *
	 * Handles plain function names, static class methods, and object methods.
	 *
	 * @param mixed $callback The callback (string, array, or closure).
	 * @return string Plugin directory name, or empty string if not determinable.
	 */
	private function get_plugin_from_callback( $callback ): string {
		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$ref  = new ReflectionFunction( $callback );
				$file = $ref->getFileName();
			} elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
				$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
				if ( is_string( $class ) && class_exists( $class ) ) {
					$ref  = new ReflectionClass( $class );
					$file = $ref->getFileName();
				} else {
					return '';
				}
			} elseif ( $callback instanceof Closure ) {
				$ref  = new ReflectionFunction( $callback );
				$file = $ref->getFileName();
			} else {
				return '';
			}
		} catch ( ReflectionException $e ) {
			return '';
		}

		if ( empty( $file ) ) {
			return '';
		}

		if ( preg_match( '#[/\\\\]wp-content[/\\\\]plugins[/\\\\]([^/\\\\]+)[/\\\\]#', $file, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}
