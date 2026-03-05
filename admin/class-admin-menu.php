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
			__( 'Health Radar', 'wp-plugin-health-monitor' ),
			__( 'Health Radar', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-heart',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'wp-plugin-health-monitor' ),
			__( 'Dashboard', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Plugin Conflicts', 'wp-plugin-health-monitor' ),
			__( 'Conflicts', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			'wphm-conflicts',
			array( $this, 'render_conflicts' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Performance', 'wp-plugin-health-monitor' ),
			__( 'Performance', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			'wphm-performance',
			array( $this, 'render_performance' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'PHP Compatibility', 'wp-plugin-health-monitor' ),
			__( 'PHP Compat', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			'wphm-php-compat',
			array( $this, 'render_php_compat' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Debug Log', 'wp-plugin-health-monitor' ),
			__( 'Debug Log', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			'wphm-debug-log',
			array( $this, 'render_debug_log' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Health Report', 'wp-plugin-health-monitor' ),
			__( 'Report', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			'wphm-report',
			array( $this, 'render_report' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Documentation', 'wp-plugin-health-monitor' ),
			__( 'Documentation ↗', 'wp-plugin-health-monitor' ),
			self::CAPABILITY,
			'wphm-documentation',
			array( $this, 'render_docs_redirect' )
		);
	}

	/**
	 * Fallback render callback for the Documentation submenu page.
	 *
	 * Redirects via JavaScript immediately after the page renders.
	 *
	 * @return void
	 */
	public function render_docs_redirect(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
		}
		?>
		<div class="wrap">
			<p><?php esc_html_e( 'Redirecting to documentation…', 'wp-plugin-health-monitor' ); ?> <a href="https://fzihak.github.io/plugin-health-monitor/"><?php esc_html_e( 'Click here if not redirected.', 'wp-plugin-health-monitor' ); ?></a></p>
		</div>
		<script>window.location.replace( 'https://fzihak.github.io/plugin-health-monitor/' );</script>
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
					'scanning'  => __( 'Scanning…', 'wp-plugin-health-monitor' ),
					'completed' => __( 'Scan complete.', 'wp-plugin-health-monitor' ),
					'error'     => __( 'An error occurred. Please try again.', 'wp-plugin-health-monitor' ),
					'noData'    => __( 'No data available. Run a scan first.', 'wp-plugin-health-monitor' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-health-monitor' ) );
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
				array( 'message' => __( 'Permission denied.', 'wp-plugin-health-monitor' ) ),
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
				array( 'message' => __( 'Permission denied.', 'wp-plugin-health-monitor' ) ),
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
				array( 'message' => __( 'Permission denied.', 'wp-plugin-health-monitor' ) ),
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
				array( 'message' => __( 'Permission denied.', 'wp-plugin-health-monitor' ) ),
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
				array( 'message' => __( 'Permission denied.', 'wp-plugin-health-monitor' ) ),
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
				array( 'message' => __( 'Permission denied.', 'wp-plugin-health-monitor' ) ),
				403
			);
		}

		$generator = new WPHM_Report_Generator();
		$report    = $generator->generate( true );

		wp_send_json_success( $report );
	}
}
