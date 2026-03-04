<?php
/**
 * Plugin Name: WP Plugin Health Monitor
 * Plugin URI:  https://wordpress.org/plugins/wp-plugin-health-monitor/
 * Description: Helps site admins understand plugin conflicts, performance issues, PHP compatibility problems, and debug log errors — all from within the WordPress admin.
 * Version:     1.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      WP Plugin Health Monitor Contributors
 * Author URI:  https://wordpress.org/plugins/wp-plugin-health-monitor/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-plugin-health-monitor
 * Domain Path: /languages
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'WPHM_VERSION', '1.2.0' );

/**
 * Plugin directory path with trailing slash.
 *
 * @var string
 */
define( 'WPHM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL with trailing slash.
 *
 * @var string
 */
define( 'WPHM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @var string
 */
define( 'WPHM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 *
 * @var string
 */
define( 'WPHM_MIN_PHP', '8.1' );

/**
 * Load plugin classes.
 *
 * Requires all class files using WordPress-style require_once.
 *
 * @return void
 */
function wphm_load_classes() {
	require_once WPHM_PLUGIN_DIR . 'includes/class-health-scorer.php';
	require_once WPHM_PLUGIN_DIR . 'includes/class-plugin-scanner.php';
	require_once WPHM_PLUGIN_DIR . 'includes/class-asset-analyzer.php';
	require_once WPHM_PLUGIN_DIR . 'includes/class-php-checker.php';
	require_once WPHM_PLUGIN_DIR . 'includes/class-debug-log-reader.php';
	require_once WPHM_PLUGIN_DIR . 'includes/class-report-generator.php';
	require_once WPHM_PLUGIN_DIR . 'admin/class-admin-menu.php';
}

/**
 * Initialize the plugin.
 *
 * Loads classes and sets up admin hooks.
 *
 * @return void
 */
function wphm_init() {
	wphm_load_classes();

	$admin_menu = new WPHM_Admin_Menu();
	$admin_menu->register_hooks();
}
add_action( 'plugins_loaded', 'wphm_init' );

/**
 * Load plugin textdomain for translations.
 *
 * @return void
 */
function wphm_load_textdomain() {
	load_plugin_textdomain(
		'wp-plugin-health-monitor',
		false,
		dirname( WPHM_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'wphm_load_textdomain' );

/**
 * Run on plugin activation.
 *
 * Sets initial options and flushes rewrite rules if needed.
 *
 * @return void
 */
function wphm_activate() {
	if ( version_compare( PHP_VERSION, WPHM_MIN_PHP, '<' ) ) {
		deactivate_plugins( WPHM_PLUGIN_BASENAME );
		wp_die(
			sprintf(
				/* translators: %s: Minimum PHP version required. */
				esc_html__( 'WP Plugin Health Monitor requires PHP %s or higher.', 'wp-plugin-health-monitor' ),
				esc_html( WPHM_MIN_PHP )
			),
			esc_html__( 'Plugin Activation Error', 'wp-plugin-health-monitor' ),
			array( 'back_link' => true )
		);
	}
	update_option( 'wphm_version', WPHM_VERSION );
}
register_activation_hook( __FILE__, 'wphm_activate' );

/**
 * Run on plugin deactivation.
 *
 * Cleans up transients.
 *
 * @return void
 */
function wphm_deactivate() {
	delete_transient( 'wphm_last_report' );
	delete_transient( 'wphm_conflict_results' );
	delete_transient( 'wphm_performance_results' );
	delete_transient( 'wphm_php_compat_results' );
	delete_transient( 'wphm_debug_log_results' );
	delete_transient( 'wphm_duplicate_asset_results' );
	delete_transient( 'wphm_health_score' );
}
register_deactivation_hook( __FILE__, 'wphm_deactivate' );

/**
 * Register WP-CLI commands when WP-CLI is available.
 *
 * @return void
 */
function wphm_register_cli_commands() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once WPHM_PLUGIN_DIR . 'cli/class-cli-commands.php';
		WP_CLI::add_command( 'healthmonitor', 'WPHM_CLI_Commands' );
	}
}
add_action( 'plugins_loaded', 'wphm_register_cli_commands' );
