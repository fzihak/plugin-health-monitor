<?php
/**
 * Report Generator class.
 *
 * Module 6 — Health Report Generator.
 * Collects output from all five modules into a single report array,
 * caches in a transient, and provides data for the report view and CLI.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_Report_Generator
 *
 * Aggregates data from all scanner modules into a unified report.
 */
class WPHM_Report_Generator {

	/**
	 * Transient key for cached report.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wphm_last_report';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Generate the full health report.
	 *
	 * Collects data from all modules and caches the result in a transient.
	 *
	 * @param bool $force_refresh Whether to bypass all caches and run fresh scans.
	 * @return array The complete report data.
	 */
	public function generate( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$scorer   = new WPHM_Health_Scorer();
		$scanner  = new WPHM_Plugin_Scanner();
		$analyzer = new WPHM_Asset_Analyzer();
		$checker  = new WPHM_PHP_Checker();
		$log      = new WPHM_Debug_Log_Reader();

		$report = array(
			'generated_at'     => gmdate( 'Y-m-d H:i:s' ),
			'wordpress'        => get_bloginfo( 'version' ),
			'php_version'      => PHP_VERSION,
			'site_url'         => get_site_url(),
			'health_score'     => $scorer->get_score( $force_refresh ),
			'conflicts'        => $scanner->scan( $force_refresh ),
			'duplicate_assets' => $analyzer->scan( $force_refresh ),
			'php_compat'       => $checker->scan( $force_refresh ),
			'debug_log'        => $log->scan( $force_refresh ),
		);

		set_transient( self::TRANSIENT_KEY, $report, self::CACHE_TTL );

		return $report;
	}

	/**
	 * Get the last generated report from cache.
	 *
	 * @return array|false The cached report, or false if not available.
	 */
	public function get_cached_report() {
		return get_transient( self::TRANSIENT_KEY );
	}
}
