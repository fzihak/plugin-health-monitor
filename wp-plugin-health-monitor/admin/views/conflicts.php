<?php
/**
 * Conflicts view template.
 *
 * Displays plugin conflict detection results.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wphm-wrap wphm-page wphm-page--conflicts">

	<div class="wphm-page-header">
		<div class="wphm-page-header__left">
			<div class="wphm-page-header__icon-wrap wphm-page-header__icon-wrap--conflicts">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="wphm-page-header__text">
				<h1 class="wphm-page-header__title"><?php esc_html_e( 'Plugin Conflicts', 'wp-plugin-health-monitor' ); ?></h1>
				<p class="wphm-page-header__desc"><?php esc_html_e( 'Detect duplicate script/style handles and hook conflicts between active plugins.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
		<div class="wphm-page-header__actions">
			<button type="button"
				class="wphm-btn-primary"
				data-wphm-action="wphm_get_conflicts"
				data-wphm-target="wphm-conflicts-results"
				data-wphm-label="<?php esc_attr_e( 'Scan for Conflicts', 'wp-plugin-health-monitor' ); ?>">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Scan for Conflicts', 'wp-plugin-health-monitor' ); ?>
			</button>
		</div>
	</div>

	<div id="wphm-conflicts-results">
		<div class="wphm-alert wphm-alert--info">
			<span class="dashicons dashicons-info-outline"></span>
			<div class="wphm-alert__body">
				<p><?php esc_html_e( 'Click "Scan for Conflicts" to analyze your active plugins for duplicated handles and hook conflicts.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
	</div>

</div>
