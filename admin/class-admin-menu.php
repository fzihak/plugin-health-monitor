<?php
/**
 * Admin Menu class.
 *
 * Registers all admin menu pages, enqueues assets, and handles AJAX endpoints
 * for the Health Radar.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHM_Admin_Menu
 *
 * Manages the wp-admin sidebar menu, page rendering, asset enqueuing,
 * and AJAX handlers for all plugin screens.
 */
class WPHM_Admin_Menu {

	/**
	 * Menu slug prefix.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'wphm-dashboard';

	/**
	 * Required capability to access all plugin screens.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Nonce action for AJAX requests.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wphm_ajax_nonce';

	/**
	 * Base64-encoded SVG icon for the admin menu.
	 *
	 * Radar screen with concentric rings and sweep line.
	 * Monochrome so WordPress admin colour schemes tint it automatically.
	 *
	 * @var string
	 */
	const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSI+PGNpcmNsZSBjeD0iMTAiIGN5PSIxMCIgcj0iOSIgc3Ryb2tlPSIjYTdhYWFkIiBzdHJva2Utd2lkdGg9IjEuMiIgZmlsbD0ibm9uZSIvPjxjaXJjbGUgY3g9IjEwIiBjeT0iMTAiIHI9IjYiIHN0cm9rZT0iI2E3YWFhZCIgc3Ryb2tlLXdpZHRoPSIxIiBmaWxsPSJub25lIiBzdHJva2UtZGFzaGFycmF5PSIxLjUgMS41Ii8+PGNpcmNsZSBjeD0iMTAiIGN5PSIxMCIgcj0iMyIgc3Ryb2tlPSIjYTdhYWFkIiBzdHJva2Utd2lkdGg9IjAuOCIgZmlsbD0ibm9uZSIvPjxsaW5lIHgxPSIxMCIgeTE9IjEwIiB4Mj0iMTciIHkyPSI0IiBzdHJva2U9IiNhN2FhYWQiIHN0cm9rZS13aWR0aD0iMS4yIiBzdHJva2UtbGluZWNhcD0icm91bmQiLz48Y2lyY2xlIGN4PSIxMCIgY3k9IjEwIiByPSIxIiBmaWxsPSIjYTdhYWFkIi8+PGNpcmNsZSBjeD0iMTQiIGN5PSI2LjUiIHI9IjEuMiIgZmlsbD0iI2E3YWFhZCIgb3BhY2l0eT0iMC43Ii8+PC9zdmc+';

	/**
	 * Register all WordPress hooks used by this class.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'do_enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wphm_run_scan', array( $this, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_wphm_get_conflicts', array( $this, 'ajax_get_conflicts' ) );
		add_action( 'wp_ajax_wphm_get_performance', array( $this, 'ajax_get_performance' ) );
		add_action( 'wp_ajax_wphm_get_php_compat', array( $this, 'ajax_get_php_compat' ) );
		add_action( 'wp_ajax_wphm_get_debug_log', array( $this, 'ajax_get_debug_log' ) );
		add_action( 'wp_ajax_wphm_get_report', array( $this, 'ajax_get_report' ) );
	}

	/**
	 * Add top-level menu and submenu pages in wp-admin.
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		add_menu_page(
			__( 'Health Radar', 'health-radar' ),
			__( 'Health Radar', 'health-radar' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			self::MENU_ICON,
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'health-radar' ),
			__( 'Dashboard', 'health-radar' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Plugin Conflicts', 'health-radar' ),
			__( 'Conflicts', 'health-radar' ),
			self::CAPABILITY,
			'wphm-conflicts',
			array( $this, 'render_conflicts' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Performance', 'health-radar' ),
			__( 'Performance', 'health-radar' ),
			self::CAPABILITY,
			'wphm-performance',
			array( $this, 'render_performance' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'PHP Compatibility', 'health-radar' ),
			__( 'PHP Compat', 'health-radar' ),
			self::CAPABILITY,
			'wphm-php-compat',
			array( $this, 'render_php_compat' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Debug Log', 'health-radar' ),
			__( 'Debug Log', 'health-radar' ),
			self::CAPABILITY,
			'wphm-debug-log',
			array( $this, 'render_debug_log' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Health Report', 'health-radar' ),
			__( 'Report', 'health-radar' ),
			self::CAPABILITY,
			'wphm-report',
			array( $this, 'render_report' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Documentation', 'health-radar' ),
			__( 'Documentation ↗', 'health-radar' ),
			self::CAPABILITY,
			'wphm-documentation',
			array( $this, 'render_docs_redirect' )
		);
	}

	/**
	 * Fallback render callback for the Documentation submenu page.
	 *
	 * Renders a link to external documentation.
	 *
	 * @return void
	 */
	public function render_docs_redirect(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Health Radar Documentation', 'health-radar' ); ?></h1>
			<p><?php esc_html_e( 'Documentation is hosted externally. Use the button below to open it in a new tab.', 'health-radar' ); ?></p>
			<p>
				<a class="button button-primary" href="https://fzihak.github.io/plugin-health-monitor/" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open Documentation', 'health-radar' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Check whether current request is for one of plugin admin pages.
	 *
	 * @return bool
	 */
	private function is_wphm_page_request(): bool {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check, no state change.
			return false;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed_pages = array(
			self::MENU_SLUG,
			'wphm-conflicts',
			'wphm-performance',
			'wphm-php-compat',
			'wphm-debug-log',
			'wphm-report',
		);

		return in_array( $page, $allowed_pages, true );
	}

	/**
	 * Perform the actual asset enqueuing.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function do_enqueue_assets( string $hook_suffix ): void {
		unset( $hook_suffix );

		if ( ! $this->is_wphm_page_request() ) {
			return;
		}

		wp_enqueue_style(
			'wphm-admin-style',
			WPHM_PLUGIN_URL . 'admin/css/admin-style.css',
			array(),
			WPHM_VERSION
		);

		wp_enqueue_script(
			'wphm-admin-script',
			WPHM_PLUGIN_URL . 'admin/js/admin-script.js',
			array(),
			WPHM_VERSION,
			true
		);

		wp_localize_script(
			'wphm-admin-script',
			'wphmData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'scanning'  => __( 'Scanning…', 'health-radar' ),
					'completed' => __( 'Scan complete.', 'health-radar' ),
					'error'     => __( 'An error occurred. Please try again.', 'health-radar' ),
					'noData'    => __( 'No data available. Run a scan first.', 'health-radar' ),
				),
			)
		);
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}

		$scorer = new WPHM_Health_Scorer();
		$score  = $scorer->get_score();

		include WPHM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the Conflicts page.
	 *
	 * @return void
	 */
	public function render_conflicts(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}

		include WPHM_PLUGIN_DIR . 'admin/views/conflicts.php';
	}

	/**
	 * Render the Performance page.
	 *
	 * @return void
	 */
	public function render_performance(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}

		include WPHM_PLUGIN_DIR . 'admin/views/performance.php';
	}

	/**
	 * Render the PHP Compatibility page.
	 *
	 * @return void
	 */
	public function render_php_compat(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}

		include WPHM_PLUGIN_DIR . 'admin/views/php-compat.php';
	}

	/**
	 * Render the Debug Log page.
	 *
	 * @return void
	 */
	public function render_debug_log(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}

		include WPHM_PLUGIN_DIR . 'admin/views/debug-log.php';
	}

	/**
	 * Render the Report page.
	 *
	 * @return void
	 */
	public function render_report(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'health-radar' ) );
		}

		include WPHM_PLUGIN_DIR . 'admin/views/report.php';
	}

	/**
	 * AJAX: Run a full scan across all modules.
	 *
	 * Refreshes the health score and triggers each module's scan,
	 * caching results in transients.
	 *
	 * @return void
	 */
	public function ajax_run_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'health-radar' ) ),
				403
			);
		}

		// Run each module's scan.
		$scanner  = new WPHM_Plugin_Scanner();
		$analyzer = new WPHM_Asset_Analyzer();
		$checker  = new WPHM_PHP_Checker();
		$log      = new WPHM_Debug_Log_Reader();
		$scorer   = new WPHM_Health_Scorer();

		$conflicts    = $scanner->scan();
		$duplicates   = $analyzer->scan();
		$php_compat   = $checker->scan();
		$debug_log    = $log->scan();
		$health_score = $scorer->get_score( true );

		wp_send_json_success(
			array(
				'score'      => $health_score,
				'conflicts'  => $conflicts,
				'duplicates' => $duplicates,
				'php_compat' => $php_compat,
				'debug_log'  => $debug_log,
			)
		);
	}

	/**
	 * AJAX: Get conflict scan results.
	 *
	 * @return void
	 */
	public function ajax_get_conflicts(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'health-radar' ) ),
				403
			);
		}

		$scanner = new WPHM_Plugin_Scanner();
		$results = $scanner->scan();

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Get performance data.
	 *
	 * @return void
	 */
	public function ajax_get_performance(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'health-radar' ) ),
				403
			);
		}

		$scorer = new WPHM_Health_Scorer();
		$score  = $scorer->get_score( true );

		wp_send_json_success( $score );
	}

	/**
	 * AJAX: Get PHP compatibility results.
	 *
	 * @return void
	 */
	public function ajax_get_php_compat(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'health-radar' ) ),
				403
			);
		}

		$checker = new WPHM_PHP_Checker();
		$results = $checker->scan();

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Get debug log results.
	 *
	 * @return void
	 */
	public function ajax_get_debug_log(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'health-radar' ) ),
				403
			);
		}

		$log     = new WPHM_Debug_Log_Reader();
		$results = $log->scan();

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Get full health report.
	 *
	 * @return void
	 */
	public function ajax_get_report(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'health-radar' ) ),
				403
			);
		}

		$generator = new WPHM_Report_Generator();
		$report    = $generator->generate( true );

		wp_send_json_success( $report );
	}
}
