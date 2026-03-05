<?php
/**
 * Uninstall handler for Health Radar.
 *
 * Cleans up all options and transients with the wphm_ prefix.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'wphm_version' );

// Delete all transients.
$wphm_transients = array(
	'wphm_last_report',
	'wphm_conflict_results',
	'wphm_performance_results',
	'wphm_php_compat_results',
	'wphm_debug_log_results',
	'wphm_duplicate_asset_results',
	'wphm_health_score',
);

foreach ( $wphm_transients as $wphm_transient ) {
	delete_transient( $wphm_transient );
}
