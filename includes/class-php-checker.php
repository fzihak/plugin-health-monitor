<?php
/**
 * PHP Checker class.
 *
 * Module 3 — PHP Compatibility Checker.
 * Checks each plugin's "Requires PHP" header against the current PHP version
 * and scans for usage of deprecated WordPress functions.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_PHP_Checker
 *
 * Checks plugin PHP version compatibility and deprecated function usage.
 */
class WPHM_PHP_Checker {

	/**
	 * Transient key for cached results.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wphm_php_compat_results';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Maximum number of PHP files to scan per plugin.
	 *
	 * @var int
	 */
	const MAX_FILES_PER_PLUGIN = 500;

	/**
	 * Maximum file size to scan in bytes.
	 *
	 * @var int
	 */
	const MAX_FILE_BYTES = 262144;

	/**
	 * Deprecated WordPress functions (WP 5.x and 6.x).
	 *
	 * Each entry: function name => WP version it was deprecated in.
	 *
	 * @var array
	 */
	private static array $deprecated_functions = array(
		// WP 5.x deprecations.
		'wp_get_user_request_data'     => '5.4',
		'get_nodes'                    => '5.3',
		'get_all_registered'           => '5.3',
		'wp_edit_attachments_query'    => '5.3',
		'_wp_register_meta_args_whitelist' => '5.5',
		'wp_blacklist_check'           => '5.5',
		'is_mobile'                    => '5.6',
		'attachment_submitbox_metadata' => '5.5',
		// WP 6.x deprecations.
		'wp_get_loading_attr_default'  => '6.3',
		'_wp_get_current_user'         => '6.4',
		'get_page'                     => '6.2',
		'wp_no_robots'                 => '6.1',
		'wp_sensitive_page_meta'       => '6.1',
		'the_block_template_skip_link' => '6.4',
		'_register_remote_theme_patterns' => '6.5',
		'wp_img_tag_add_loading_attr'  => '6.3',
	);

	/**
	 * Run the PHP compatibility scan.
	 *
	 * @param bool $force_refresh Whether to bypass the cache.
	 * @return array {
	 *     @type array  $plugins          Plugin compatibility results.
	 *     @type array  $deprecated_usage Deprecated function usage found.
	 *     @type string $current_php      Current PHP version.
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
			'plugins'          => $this->check_php_versions(),
			'deprecated_usage' => $this->find_deprecated_usage(),
			'current_php'      => PHP_VERSION,
		);

		set_transient( self::TRANSIENT_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Check each plugin's required PHP version against the current version.
	 *
	 * Reads the "Requires PHP" header from get_plugins() data and falls back
	 * to parsing the plugin's readme.txt for a "Requires PHP:" line.
	 *
	 * @return array Array of plugin compatibility entries.
	 */
	public function check_php_versions(): array {
		$all_plugins = $this->get_installed_plugins();
		$results     = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$required_php = '';

			// First try the plugin header "RequiresPHP".
			if ( ! empty( $plugin_data['RequiresPHP'] ) ) {
				$required_php = $plugin_data['RequiresPHP'];
			}

			// Fallback: parse readme.txt.
			if ( empty( $required_php ) ) {
				$required_php = $this->get_required_php_from_readme( $plugin_file );
			}

			$status = 'unknown';
			if ( ! empty( $required_php ) ) {
				$status = version_compare( PHP_VERSION, $required_php, '>=' ) ? 'compatible' : 'incompatible';
			}

			$results[] = array(
				'name'         => $plugin_data['Name'] ?? $plugin_file,
				'plugin_file'  => $plugin_file,
				'required_php' => $required_php ?: '',
				'current_php'  => PHP_VERSION,
				'status'       => $status,
			);
		}

		return $results;
	}

	/**
	 * Extract "Requires PHP:" value from a plugin's readme.txt using regex.
	 *
	 * @param string $plugin_file The plugin file path relative to the plugins directory.
	 * @return string The required PHP version, or empty string if not found.
	 */
	private function get_required_php_from_readme( string $plugin_file ): string {
		$plugins_dir = trailingslashit( $this->get_plugins_root_dir() );
		$plugin_dir  = dirname( $plugins_dir . ltrim( $plugin_file, '/' ) );
		$readme_path = $plugin_dir . '/readme.txt';

		if ( ! file_exists( $readme_path ) ) {
			return '';
		}

		// Validate path with realpath().
		$real_path = realpath( $readme_path );
		if ( false === $real_path ) {
			return '';
		}

		$plugins_root = realpath( $this->get_plugins_root_dir() );
		if ( false === $plugins_root ) {
			return '';
		}

		$real_path_normalized   = wp_normalize_path( $real_path );
		$plugins_root_normalized = wp_normalize_path( $plugins_root );

		if ( ! str_starts_with( $real_path_normalized, $plugins_root_normalized ) ) {
			return '';
		}

		// Read only the first 8KB — the header should be at the top.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$content = $wp_filesystem->get_contents( $real_path );
		if ( false === $content ) {
			return '';
		}
		$header = substr( $content, 0, 8192 );

		if ( preg_match( '/^Requires PHP:\s*(.+)$/mi', $header, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Scan active plugin files for usage of deprecated WordPress functions.
	 *
	 * Greps PHP files in each active plugin directory for function calls
	 * matching the static deprecated functions list.
	 *
	 * @return array Array of deprecated usage entries.
	 */
	public function find_deprecated_usage(): array {
		$active  = $this->get_active_plugin_files();
		$results = array();

		if ( ! is_array( $active ) ) {
			return $results;
		}

		$function_names = array_keys( self::$deprecated_functions );
		// Build a regex pattern to match any of the deprecated function calls.
		$pattern = '/\b(' . implode( '|', array_map( 'preg_quote', $function_names ) ) . ')\s*\(/';
		$plugins_dir = trailingslashit( $this->get_plugins_root_dir() );
		$plugins_root = realpath( $this->get_plugins_root_dir() );

		if ( false === $plugins_root ) {
			return $results;
		}

		$plugins_root_normalized = wp_normalize_path( $plugins_root );

		foreach ( $active as $plugin_file ) {
			$plugin_dir = dirname( $plugins_dir . ltrim( $plugin_file, '/' ) );

			// Validate path.
			$real_dir = realpath( $plugin_dir );
			if ( false === $real_dir ) {
				continue;
			}

			$real_dir_normalized    = wp_normalize_path( $real_dir );

			if ( ! str_starts_with( $real_dir_normalized, $plugins_root_normalized ) ) {
				continue;
			}

			$php_files = $this->get_php_files( $real_dir, self::MAX_FILES_PER_PLUGIN );

			foreach ( $php_files as $file ) {
				$file_size = filesize( $file );
				if ( false === $file_size || $file_size > self::MAX_FILE_BYTES ) {
					continue;
				}

				$contents = $this->read_file_contents( $file );
				if ( '' === $contents ) {
					continue;
				}

				if ( preg_match_all( $pattern, $contents, $matches ) ) {
					$matched_functions = array_unique( $matches[1] );

					$plugin_slug = dirname( $plugin_file );
					if ( '.' === $plugin_slug ) {
						$plugin_slug = basename( $plugin_file, '.php' );
					}

					foreach ( $matched_functions as $func_name ) {
						$results[] = array(
							'plugin'        => $plugin_slug,
							'function'      => $func_name,
							'deprecated_in' => self::$deprecated_functions[ $func_name ] ?? '',
							'file'          => ltrim( str_replace( trailingslashit( $plugins_root_normalized ), '', wp_normalize_path( $file ) ), '/' ),
						);
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Recursively get all PHP files in a directory.
	 *
	 * @param string $directory The directory path to scan.
	 * @param int    $max_files Maximum files to return.
	 * @return array Array of absolute file paths.
	 */
	private function get_php_files( string $directory, int $max_files = self::MAX_FILES_PER_PLUGIN ): array {
		$files    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			$path_name = wp_normalize_path( $file->getPathname() );
			if (
				str_contains( $path_name, '/vendor/' ) ||
				str_contains( $path_name, '/node_modules/' ) ||
				str_contains( $path_name, '/tests/' )
			) {
				continue;
			}

			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();

				if ( count( $files ) >= $max_files ) {
					break;
				}
			}
		}

		return $files;
	}

	/**
	 * Get installed plugins data without requiring wp-admin plugin loader files.
	 *
	 * @return array
	 */
	private function get_installed_plugins(): array {
		if ( function_exists( 'get_plugins' ) ) {
			return get_plugins();
		}

		$plugins = array();
		$files   = $this->get_active_plugin_files();
		$root    = trailingslashit( $this->get_plugins_root_dir() );

		foreach ( $files as $plugin_file ) {
			$plugin_path = wp_normalize_path( $root . ltrim( $plugin_file, '/' ) );
			if ( ! file_exists( $plugin_path ) || ! is_readable( $plugin_path ) ) {
				continue;
			}

			$data = get_file_data(
				$plugin_path,
				array(
					'Name'        => 'Plugin Name',
					'RequiresPHP' => 'Requires PHP',
				)
			);

			$plugins[ $plugin_file ] = array(
				'Name'        => $data['Name'] ?? $plugin_file,
				'RequiresPHP' => $data['RequiresPHP'] ?? '',
			);
		}

		return $plugins;
	}

	/**
	 * Get active plugin file list for single-site and multisite.
	 *
	 * @return array
	 */
	private function get_active_plugin_files(): array {
		$active_plugins = get_option( 'active_plugins', array() );

		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}

		if ( is_multisite() ) {
			$network_active = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $network_active ) ) {
				$active_plugins = array_merge( $active_plugins, array_keys( $network_active ) );
			}
		}

		$active_plugins = array_map(
			static function ( $plugin_file ) {
				return is_string( $plugin_file ) ? wp_normalize_path( $plugin_file ) : '';
			},
			$active_plugins
		);

		$active_plugins = array_filter( $active_plugins );

		return array_values( array_unique( $active_plugins ) );
	}

	/**
	 * Get plugins root directory from plugin constants.
	 *
	 * @return string
	 */
	private function get_plugins_root_dir(): string {
		return wp_normalize_path( dirname( untrailingslashit( WPHM_PLUGIN_DIR ) ) );
	}

	/**
	 * Read file contents using WP_Filesystem.
	 *
	 * @param string $path Absolute file path.
	 * @return string File content, or empty string on failure.
	 */
	private function read_file_contents( string $path ): string {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return '';
		}

		$content = $wp_filesystem->get_contents( $path );

		return false === $content ? '' : (string) $content;
	}
}
