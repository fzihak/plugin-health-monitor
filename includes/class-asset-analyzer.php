<?php
/**
 * Asset Analyzer class.
 *
 * Module 5 — Duplicate Asset Detector.
 * Fingerprints local JS/CSS files with md5_file(), compares external URLs
 * by filename + version, and flags known library duplicates.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_Asset_Analyzer
 *
 * Detects duplicate JavaScript and CSS assets loaded by plugins.
 * Detection only — does not attempt deduplication.
 */
class WPHM_Asset_Analyzer {

	/**
	 * Transient key for cached results.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wphm_duplicate_asset_results';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Maximum local asset file size to hash.
	 *
	 * @var int
	 */
	const MAX_HASH_FILE_BYTES = 2097152;

	/**
	 * Known library filename signatures.
	 *
	 * Maps common library filenames to their canonical names.
	 *
	 * @var array
	 */
	private static array $known_libraries = array(
		'jquery'    => array( 'jquery.js', 'jquery.min.js' ),
		'lodash'    => array( 'lodash.js', 'lodash.min.js', 'lodash.core.js', 'lodash.core.min.js' ),
		'moment'    => array( 'moment.js', 'moment.min.js', 'moment-with-locales.js', 'moment-with-locales.min.js' ),
		'chart.js'  => array( 'chart.js', 'chart.min.js', 'chart.umd.js', 'chart.umd.min.js' ),
	);

	/**
	 * Normalized local hostnames used for local URL detection.
	 *
	 * @var array|null
	 */
	private ?array $local_hosts = null;

	/**
	 * Run the duplicate asset scan.
	 *
	 * @param bool $force_refresh Whether to bypass the cache.
	 * @return array {
	 *     @type array $hash_duplicates    Assets with identical file hashes.
	 *     @type array $url_duplicates     External assets with matching filename + version.
	 *     @type array $library_duplicates Known libraries loaded by multiple plugins.
	 *     @type array $asset_inventory    Full list of enqueued assets with metadata.
	 * }
	 */
	public function scan( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$inventory = $this->build_asset_inventory();

		$results = array(
			'hash_duplicates'    => $this->find_hash_duplicates( $inventory ),
			'url_duplicates'     => $this->find_url_duplicates( $inventory ),
			'library_duplicates' => $this->find_known_library_duplicates( $inventory ),
			'asset_inventory'    => $inventory,
		);

		set_transient( self::TRANSIENT_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Build an inventory of all enqueued scripts and styles.
	 *
	 * Collects handle, src, version, plugin, local file path, file size,
	 * and MD5 hash (for local files only).
	 *
	 * @return array Array of asset data arrays.
	 */
	public function build_asset_inventory(): array {
		global $wp_scripts, $wp_styles;

		$inventory = array();

		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( $wp_scripts->queue as $handle ) {
				if ( isset( $wp_scripts->registered[ $handle ] ) ) {
					$inventory[] = $this->analyze_dependency( $wp_scripts->registered[ $handle ], 'script' );
				}
			}
		}

		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( $wp_styles->queue as $handle ) {
				if ( isset( $wp_styles->registered[ $handle ] ) ) {
					$inventory[] = $this->analyze_dependency( $wp_styles->registered[ $handle ], 'style' );
				}
			}
		}

		return $inventory;
	}

	/**
	 * Analyze a single registered dependency.
	 *
	 * @param _WP_Dependency $dep  The dependency object.
	 * @param string         $type Either 'script' or 'style'.
	 * @return array Asset data array.
	 */
	private function analyze_dependency( _WP_Dependency $dep, string $type ): array {
		$src       = $dep->src ?? '';
		$local     = $this->is_local_url( $src );
		$file_path = $local ? $this->url_to_local_path( $src ) : '';
		$file_size = 0;
		$md5       = '';

		if ( $file_path && file_exists( $file_path ) && is_readable( $file_path ) ) {
			$file_size = filesize( $file_path );

			if ( false !== $file_size ) {
				$file_size = absint( $file_size );
				if ( $file_size > 0 && $file_size <= self::MAX_HASH_FILE_BYTES ) {
					$md5 = md5_file( $file_path );
				}
			} else {
				$file_size = 0;
			}
		}

		$plugin   = '';
		$filename = '';
		if ( ! empty( $src ) ) {
			$filename = basename( wp_parse_url( $src, PHP_URL_PATH ) ?: '' );
			$plugin   = $this->extract_plugin_slug_from_src( $src );
		}

		return array(
			'handle'    => $dep->handle,
			'src'       => $src,
			'version'   => $dep->ver ?? '',
			'type'      => $type,
			'plugin'    => $plugin,
			'is_local'  => $local,
			'file_path' => $file_path,
			'file_size' => $file_size,
			'md5'       => $md5,
			'filename'  => $filename,
		);
	}

	/**
	 * Determine if a URL points to a local file on this WordPress installation.
	 *
	 * @param string $url The asset URL.
	 * @return bool
	 */
	private function is_local_url( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		// Relative URLs are local.
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return true;
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return false;
		}

		if ( null === $this->local_hosts ) {
			$this->local_hosts = array_values(
				array_filter(
					array_map(
						'strtolower',
						array(
							(string) wp_parse_url( site_url(), PHP_URL_HOST ),
							(string) wp_parse_url( home_url(), PHP_URL_HOST ),
						)
					)
				)
			);
		}

		return in_array( strtolower( (string) $parsed['host'] ), $this->local_hosts, true );
	}

	/**
	 * Convert a local URL to an absolute filesystem path.
	 *
	 * Resolves paths by mapping WordPress content/plugins URLs to the
	 * corresponding filesystem directories.
	 *
	 * @param string $url The local URL.
	 * @return string Absolute file path, or empty string on failure.
	 */
	private function url_to_local_path( string $url ): string {
		$asset_path = '';

		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			$asset_path = $url;
		} else {
			$parsed_path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! is_string( $parsed_path ) || '' === $parsed_path ) {
				return '';
			}
			$asset_path = $parsed_path;
		}

		$asset_path       = wp_normalize_path( $asset_path );
		$plugins_url_path = untrailingslashit( wp_normalize_path( (string) wp_parse_url( plugins_url( '/' ), PHP_URL_PATH ) ) );
		$content_url_path = untrailingslashit( wp_normalize_path( (string) wp_parse_url( content_url( '/' ), PHP_URL_PATH ) ) );
		$plugins_dir      = $this->get_plugins_dir_path();
		$content_dir      = $this->get_content_dir_path();
		$path             = '';

		if ( '' !== $plugins_url_path && str_starts_with( $asset_path, $plugins_url_path . '/' ) ) {
			$relative = ltrim( substr( $asset_path, strlen( $plugins_url_path ) ), '/' );
			$path     = wp_normalize_path( $plugins_dir . '/' . $relative );
		} elseif ( '' !== $content_url_path && str_starts_with( $asset_path, $content_url_path . '/' ) ) {
			$relative = ltrim( substr( $asset_path, strlen( $content_url_path ) ), '/' );
			$path     = wp_normalize_path( $content_dir . '/' . $relative );
		} else {
			return '';
		}

		$resolved = realpath( $path );
		if ( false === $resolved ) {
			return '';
		}

		$resolved_normalized = wp_normalize_path( $resolved );
		$plugins_normalized  = wp_normalize_path( $plugins_dir );
		$content_normalized  = wp_normalize_path( $content_dir );

		if (
			! str_starts_with( $resolved_normalized, $plugins_normalized ) &&
			! str_starts_with( $resolved_normalized, $content_normalized )
		) {
			return '';
		}

		return $resolved;
	}

	/**
	 * Extract plugin slug from an asset URL when it points to plugins directory.
	 *
	 * @param string $src Asset source URL.
	 * @return string Plugin slug or empty string.
	 */
	private function extract_plugin_slug_from_src( string $src ): string {
		$path             = wp_parse_url( $src, PHP_URL_PATH );
		$plugins_url_path = untrailingslashit( wp_normalize_path( (string) wp_parse_url( plugins_url( '/' ), PHP_URL_PATH ) ) );

		if ( ! is_string( $path ) || '' === $path || '' === $plugins_url_path ) {
			return '';
		}

		$path = wp_normalize_path( $path );
		if ( ! str_starts_with( $path, $plugins_url_path . '/' ) ) {
			return '';
		}

		$relative = ltrim( substr( $path, strlen( $plugins_url_path ) ), '/' );
		$parts    = explode( '/', $relative );

		if ( empty( $parts[0] ) ) {
			return '';
		}

		return sanitize_key( $parts[0] );
	}

	/**
	 * Get plugins directory path from plugin constants.
	 *
	 * @return string
	 */
	private function get_plugins_dir_path(): string {
		return wp_normalize_path( dirname( untrailingslashit( WPHM_PLUGIN_DIR ) ) );
	}

	/**
	 * Resolve content directory from uploads directory, with plugin-relative fallback.
	 *
	 * @return string
	 */
	private function get_content_dir_path(): string {
		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ) {
			$uploads_dir = realpath( $uploads['basedir'] );
			if ( false !== $uploads_dir ) {
				$content_dir = dirname( $uploads_dir );
				if ( is_dir( $content_dir ) ) {
					return wp_normalize_path( $content_dir );
				}
			}
		}

		return wp_normalize_path( dirname( $this->get_plugins_dir_path() ) );
	}

	/**
	 * Find assets with duplicate MD5 file hashes (local files only).
	 *
	 * @param array $inventory The asset inventory.
	 * @return array Array of duplicate groups.
	 */
	public function find_hash_duplicates( array $inventory ): array {
		$hash_map   = array();
		$duplicates = array();

		foreach ( $inventory as $asset ) {
			if ( empty( $asset['md5'] ) ) {
				continue;
			}

			if ( ! isset( $hash_map[ $asset['md5'] ] ) ) {
				$hash_map[ $asset['md5'] ] = array();
			}

			$hash_map[ $asset['md5'] ][] = $asset;
		}

		foreach ( $hash_map as $hash => $assets ) {
			if ( count( $assets ) < 2 ) {
				continue;
			}

			$handles = array_column( $assets, 'handle' );
			$plugins = array_unique( array_filter( array_column( $assets, 'plugin' ) ) );

			$duplicates[] = array(
				'handle'   => implode( ', ', $handles ),
				'plugins'  => array_values( $plugins ),
				'type'     => 'hash_duplicate',
				'severity' => 'warning',
				'md5'      => $hash,
			);
		}

		return $duplicates;
	}

	/**
	 * Find external URL duplicates by matching filename + version.
	 *
	 * @param array $inventory The asset inventory.
	 * @return array Array of duplicate groups.
	 */
	public function find_url_duplicates( array $inventory ): array {
		$fp_map     = array();
		$duplicates = array();

		foreach ( $inventory as $asset ) {
			if ( $asset['is_local'] || empty( $asset['filename'] ) ) {
				continue;
			}

			$fingerprint = $asset['filename'] . '|' . ( $asset['version'] ?? '' );

			if ( ! isset( $fp_map[ $fingerprint ] ) ) {
				$fp_map[ $fingerprint ] = array();
			}

			$fp_map[ $fingerprint ][] = $asset;
		}

		foreach ( $fp_map as $fp => $assets ) {
			if ( count( $assets ) < 2 ) {
				continue;
			}

			$handles = array_column( $assets, 'handle' );
			$plugins = array_unique( array_filter( array_column( $assets, 'plugin' ) ) );

			$duplicates[] = array(
				'handle'   => implode( ', ', $handles ),
				'plugins'  => array_values( $plugins ),
				'type'     => 'url_duplicate',
				'severity' => 'warning',
			);
		}

		return $duplicates;
	}

	/**
	 * Find known libraries loaded by multiple plugins.
	 *
	 * Checks the asset filenames against the static known_libraries map.
	 *
	 * @param array $inventory The asset inventory.
	 * @return array Array of library duplicate entries.
	 */
	public function find_known_library_duplicates( array $inventory ): array {
		$lib_map    = array();
		$duplicates = array();

		foreach ( $inventory as $asset ) {
			if ( empty( $asset['filename'] ) || empty( $asset['plugin'] ) ) {
				continue;
			}

			foreach ( self::$known_libraries as $lib_name => $filenames ) {
				if ( in_array( strtolower( $asset['filename'] ), $filenames, true ) ) {
					if ( ! isset( $lib_map[ $lib_name ] ) ) {
						$lib_map[ $lib_name ] = array();
					}
					$lib_map[ $lib_name ][] = $asset;
					break;
				}
			}
		}

		foreach ( $lib_map as $lib_name => $assets ) {
			$plugins = array_unique( array_filter( array_column( $assets, 'plugin' ) ) );

			if ( count( $plugins ) < 2 ) {
				continue;
			}

			$handles = array_column( $assets, 'handle' );

			$duplicates[] = array(
				'handle'   => implode( ', ', $handles ),
				'library'  => $lib_name,
				'plugins'  => array_values( $plugins ),
				'type'     => 'library_duplicate',
				'severity' => 'warning',
			);
		}

		return $duplicates;
	}
}
