<?php
/**
 * WP-CLI Commands class.
 *
 * Registers CLI commands for the Health Radar.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_CLI_Commands
 *
 * Provides WP-CLI commands for running scans, generating reports,
 * and querying health data from the command line.
 *
 * ## EXAMPLES
 *
 *     wp healthmonitor scan
 *     wp healthmonitor report
 *     wp healthmonitor report --format=json
 *     wp healthmonitor conflicts
 *     wp healthmonitor score
 *     wp healthmonitor log --last=20
 */
class WPHM_CLI_Commands {

	/**
	 * Run a full health scan across all modules.
	 *
	 * ## EXAMPLES
	 *
	 *     wp healthmonitor scan
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function scan( array $args, array $assoc_args ): void {
		WP_CLI::log( __( 'Running full health scan…', 'health-radar' ) );

		$scorer   = new WPHM_Health_Scorer();
		$scanner  = new WPHM_Plugin_Scanner();
		$analyzer = new WPHM_Asset_Analyzer();
		$checker  = new WPHM_PHP_Checker();
		$log      = new WPHM_Debug_Log_Reader();

		$health = $scorer->get_score( true );
		WP_CLI::log(
			sprintf(
				/* translators: %d: Health score. */
				__( 'Health Score: %d/100', 'health-radar' ),
				$health['total']
			)
		);

		$conflicts = $scanner->scan( true );
		$conflict_count = count( $conflicts['duplicate_assets'] ) + count( $conflicts['hook_conflicts'] );
		WP_CLI::log(
			sprintf(
				/* translators: %d: Number of conflicts. */
				__( 'Conflicts found: %d', 'health-radar' ),
				$conflict_count
			)
		);

		$duplicates = $analyzer->scan( true );
		$dup_count  = count( $duplicates['hash_duplicates'] )
			+ count( $duplicates['url_duplicates'] )
			+ count( $duplicates['library_duplicates'] );
		WP_CLI::log(
			sprintf(
				/* translators: %d: Number of duplicate assets. */
				__( 'Duplicate assets: %d', 'health-radar' ),
				$dup_count
			)
		);

		$php_compat   = $checker->scan( true );
		$incompat     = array_filter(
			$php_compat['plugins'],
			function ( $p ) {
				return 'incompatible' === $p['status'];
			}
		);
		WP_CLI::log(
			sprintf(
				/* translators: %d: Number of incompatible plugins. */
				__( 'PHP incompatible plugins: %d', 'health-radar' ),
				count( $incompat )
			)
		);

		$debug = $log->scan( true );
		if ( $debug['exists'] ) {
			WP_CLI::log(
				sprintf(
					/* translators: 1: Fatal count, 2: Warning count, 3: Notice count. */
					__( 'Debug log: %1$d fatal, %2$d warnings, %3$d notices', 'health-radar' ),
					$debug['summary']['fatal'],
					$debug['summary']['warning'],
					$debug['summary']['notice']
				)
			);
		} else {
			WP_CLI::log( __( 'Debug log: not found', 'health-radar' ) );
		}

		WP_CLI::success( __( 'Full scan complete.', 'health-radar' ) );
	}

	/**
	 * Generate and display the full health report.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepts 'table' or 'json'. Default 'table'.
	 *
	 * ## EXAMPLES
	 *
	 *     wp healthmonitor report
	 *     wp healthmonitor report --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function report( array $args, array $assoc_args ): void {
		$generator = new WPHM_Report_Generator();
		$report    = $generator->generate( true );

		$format = $assoc_args['format'] ?? 'table';

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format summary.
		WP_CLI::log( '' );
		WP_CLI::log( __( '=== WP Plugin Health Report ===', 'health-radar' ) );
		WP_CLI::log(
			sprintf(
				/* translators: %s: Generation timestamp. */
				__( 'Generated: %s', 'health-radar' ),
				$report['generated_at']
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: %s: WordPress version. */
				__( 'WordPress: %s', 'health-radar' ),
				$report['wordpress']
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: %s: PHP version. */
				__( 'PHP: %s', 'health-radar' ),
				$report['php_version']
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: %d: Health score. */
				__( 'Health Score: %d/100', 'health-radar' ),
				$report['health_score']['total']
			)
		);
		WP_CLI::log( '' );

		// Score breakdown.
		$score_data = array(
			array(
				'dimension' => __( 'Plugins', 'health-radar' ),
				'score'     => $report['health_score']['plugins'],
				'max'       => WPHM_Health_Scorer::PLUGIN_MAX,
			),
			array(
				'dimension' => __( 'Assets', 'health-radar' ),
				'score'     => $report['health_score']['assets'],
				'max'       => WPHM_Health_Scorer::ASSET_MAX,
			),
			array(
				'dimension' => __( 'DB Queries', 'health-radar' ),
				'score'     => $report['health_score']['db_queries'],
				'max'       => WPHM_Health_Scorer::DB_QUERY_MAX,
			),
			array(
				'dimension' => __( 'Autoload', 'health-radar' ),
				'score'     => $report['health_score']['autoload'],
				'max'       => WPHM_Health_Scorer::AUTOLOAD_MAX,
			),
		);

		WP_CLI\Utils\format_items( 'table', $score_data, array( 'dimension', 'score', 'max' ) );

		WP_CLI::success( __( 'Report complete.', 'health-radar' ) );
	}

	/**
	 * Show detected plugin conflicts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp healthmonitor conflicts
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function conflicts( array $args, array $assoc_args ): void {
		$scanner = new WPHM_Plugin_Scanner();
		$results = $scanner->scan( true );

		$items = array_merge( $results['duplicate_assets'], $results['hook_conflicts'] );

		if ( empty( $items ) ) {
			WP_CLI::success( __( 'No plugin conflicts detected.', 'health-radar' ) );
			return;
		}

		$table_data = array();
		foreach ( $items as $item ) {
			$table_data[] = array(
				'handle_hook' => $item['handle'] ?? $item['hook'] ?? '',
				'plugins'     => implode( ', ', $item['plugins'] ?? array() ),
				'type'        => $item['type'] ?? '',
				'severity'    => $item['severity'] ?? '',
			);
		}

		WP_CLI\Utils\format_items( 'table', $table_data, array( 'handle_hook', 'plugins', 'type', 'severity' ) );
	}

	/**
	 * Display the current health score.
	 *
	 * ## EXAMPLES
	 *
	 *     wp healthmonitor score
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function score( array $args, array $assoc_args ): void {
		$scorer = new WPHM_Health_Scorer();
		$score  = $scorer->get_score( true );

		WP_CLI::log(
			sprintf(
				/* translators: 1: Score total, 2: Score label. */
				__( 'Health Score: %1$d/100 (%2$s)', 'health-radar' ),
				$score['total'],
				$scorer->get_score_label( $score['total'] )
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: 1: Plugins score, 2: Max. */
				__( '  Plugins:    %1$d/%2$d', 'health-radar' ),
				$score['plugins'],
				WPHM_Health_Scorer::PLUGIN_MAX
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: 1: Assets score, 2: Max. */
				__( '  Assets:     %1$d/%2$d', 'health-radar' ),
				$score['assets'],
				WPHM_Health_Scorer::ASSET_MAX
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: 1: DB queries score, 2: Max. */
				__( '  DB Queries: %1$d/%2$d', 'health-radar' ),
				$score['db_queries'],
				WPHM_Health_Scorer::DB_QUERY_MAX
			)
		);
		WP_CLI::log(
			sprintf(
				/* translators: 1: Autoload score, 2: Max. */
				__( '  Autoload:   %1$d/%2$d', 'health-radar' ),
				$score['autoload'],
				WPHM_Health_Scorer::AUTOLOAD_MAX
			)
		);
	}

	/**
	 * Show recent debug log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--last=<number>]
	 * : Number of entries to show. Default 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp healthmonitor log
	 *     wp healthmonitor log --last=20
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function log( array $args, array $assoc_args ): void {
		$count = isset( $assoc_args['last'] ) ? absint( $assoc_args['last'] ) : 50;
		if ( $count < 1 ) {
			$count = 50;
		}

		$reader  = new WPHM_Debug_Log_Reader();
		$entries = $reader->get_last_entries( $count );

		if ( empty( $entries ) ) {
			WP_CLI::log( __( 'No debug log entries found, or debug.log does not exist.', 'health-radar' ) );
			return;
		}

		WP_CLI::log(
			sprintf(
				/* translators: %d: Number of entries shown. */
				__( 'Last %d debug log entries:', 'health-radar' ),
				count( $entries )
			)
		);
		WP_CLI::log( '' );

		foreach ( $entries as $entry ) {
			WP_CLI::log( $entry );
		}
	}
}
