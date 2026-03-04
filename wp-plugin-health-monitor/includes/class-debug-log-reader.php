<?php
/**
 * Debug Log Reader class.
 *
 * Module 4 — Debug Log Analyzer.
 * Reads and parses WP_CONTENT_DIR/debug.log, extracting error types,
 * plugin attribution, and recent entries.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_Debug_Log_Reader
 *
 * Parses the WordPress debug.log file for errors, warnings, and notices,
 * attributes them to plugins, and returns structured results.
 */
class WPHM_Debug_Log_Reader {

	/**
	 * Transient key for cached results.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wphm_debug_log_results';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Maximum number of bytes to read from tail of log.
	 * Limits memory usage for very large log files.
	 *
	 * @var int
	 */
	const MAX_READ_BYTES = 1048576; // 1 MB.

	/**
	 * Number of recent entries to return.
	 *
	 * @var int
	 */
	const LAST_ENTRIES = 50;

	/**
	 * Maximum allowed entries for get_last_entries().
	 *
	 * @var int
	 */
	const MAX_LAST_ENTRIES = 200;

	/**
	 * Run the debug log scan.
	 *
	 * @param bool $force_refresh Whether to bypass the cache.
	 * @return array {
	 *     @type bool   $exists      Whether debug.log exists.
	 *     @type array  $summary     Error counts by type (fatal, warning, notice).
	 *     @type array  $top_plugins Top 5 offending plugins.
	 *     @type array  $entries     Last 50 log entries.
	 *     @type int    $file_size   File size in bytes.
	 * }
	 */
	public function scan( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$log_path = $this->get_log_path();

		if ( empty( $log_path ) ) {
			return array(
				'exists'      => false,
				'summary'     => array(
					'fatal'   => 0,
					'warning' => 0,
					'notice'  => 0,
				),
				'top_plugins' => array(),
				'entries'     => array(),
				'file_size'   => 0,
			);
		}

		$lines     = $this->read_log_tail( $log_path );
		$results   = $this->parse_log_lines( $lines );
		$file_size = filesize( $log_path );

		$results['exists']    = true;
		$results['file_size'] = false !== $file_size ? absint( $file_size ) : 0;

		set_transient( self::TRANSIENT_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Get the validated path to the debug.log file.
	 *
	 * Checks file existence and validates the resolved path starts with WP_CONTENT_DIR.
	 *
	 * @return string Absolute path to debug.log, or empty string if invalid.
	 */
	public function get_log_path(): string {
		$log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_path ) || ! is_readable( $log_path ) ) {
			return '';
		}

		$real_path = realpath( $log_path );
		if ( false === $real_path ) {
			return '';
		}

		$content_dir = realpath( WP_CONTENT_DIR );
		if ( false === $content_dir ) {
			return '';
		}

		$real_path_normalized   = wp_normalize_path( $real_path );
		$content_dir_normalized = wp_normalize_path( $content_dir );

		if ( ! str_starts_with( $real_path_normalized, $content_dir_normalized ) ) {
			return '';
		}

		return $real_path;
	}

	/**
	 * Read the last portion of the log file.
	 *
	 * For files larger than MAX_READ_BYTES, reads only the tail.
	 *
	 * @param string $path Absolute path to the log file.
	 * @return array Array of log lines (most recent last).
	 */
	private function read_log_tail( string $path ): array {
		$file_size = filesize( $path );
		if ( false === $file_size || 0 === $file_size ) {
			return array();
		}

		$handle = fopen( $path, 'r' );
		if ( false === $handle ) {
			return array();
		}

		$offset = 0;
		if ( $file_size > self::MAX_READ_BYTES ) {
			$offset = $file_size - self::MAX_READ_BYTES;
		}

		if ( $offset > 0 ) {
			fseek( $handle, $offset );
			// Discard partial first line.
			fgets( $handle );
		}

		$lines = array();
		while ( ! feof( $handle ) ) {
			$line = fgets( $handle );
			if ( false !== $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}
		}

		fclose( $handle );

		return $lines;
	}

	/**
	 * Parse log lines into structured results.
	 *
	 * Extracts error type counts, plugin attributions, and the last N entries.
	 *
	 * @param array $lines Array of log lines.
	 * @return array Parsed results with summary, top_plugins, and entries.
	 */
	private function parse_log_lines( array $lines ): array {
		$summary = array(
			'fatal'   => 0,
			'warning' => 0,
			'notice'  => 0,
		);

		$plugin_errors = array();

		foreach ( $lines as $line ) {
			// Count error types.
			if ( stripos( $line, 'PHP Fatal error' ) !== false ) {
				++$summary['fatal'];
			} elseif ( stripos( $line, 'PHP Warning' ) !== false ) {
				++$summary['warning'];
			} elseif ( stripos( $line, 'PHP Notice' ) !== false ) {
				++$summary['notice'];
			}

			// Extract plugin name from stack traces or file paths.
			$plugin = $this->extract_plugin_from_line( $line );
			if ( ! empty( $plugin ) ) {
				if ( ! isset( $plugin_errors[ $plugin ] ) ) {
					$plugin_errors[ $plugin ] = 0;
				}
				++$plugin_errors[ $plugin ];
			}
		}

		// Sort by error count descending.
		arsort( $plugin_errors );

		// Top 5 offending plugins.
		$top_plugins = array();
		$count       = 0;
		foreach ( $plugin_errors as $plugin => $error_count ) {
			if ( $count >= 5 ) {
				break;
			}
			$top_plugins[] = array(
				'plugin' => $plugin,
				'count'  => $error_count,
			);
			++$count;
		}

		// Last N entries.
		$entry_count = count( $lines );
		$start       = max( 0, $entry_count - self::LAST_ENTRIES );
		$entries     = array_slice( $lines, $start );

		return array(
			'summary'     => $summary,
			'top_plugins' => $top_plugins,
			'entries'     => $entries,
		);
	}

	/**
	 * Extract plugin name from a log line.
	 *
	 * Looks for the wp-content/plugins/PLUGIN_NAME/ pattern.
	 *
	 * @param string $line A single log line.
	 * @return string Plugin directory name, or empty string if not found.
	 */
	private function extract_plugin_from_line( string $line ): string {
		if ( preg_match( '#wp-content[/\\\\]plugins[/\\\\]([^/\\\\]+)[/\\\\]#i', $line, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Get the last N entries from the debug log.
	 *
	 * Convenience method for CLI and direct access.
	 *
	 * @param int $count Number of entries to return.
	 * @return array Array of log line strings.
	 */
	public function get_last_entries( int $count = 50 ): array {
		$log_path = $this->get_log_path();
		if ( empty( $log_path ) ) {
			return array();
		}

		if ( $count < 1 ) {
			$count = self::LAST_ENTRIES;
		}

		if ( $count > self::MAX_LAST_ENTRIES ) {
			$count = self::MAX_LAST_ENTRIES;
		}

		$lines = $this->read_log_tail( $log_path );
		$total = count( $lines );
		$start = max( 0, $total - $count );

		return array_slice( $lines, $start );
	}
}
