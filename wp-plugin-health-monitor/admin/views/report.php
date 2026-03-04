<?php
/**
 * Report view template.
 *
 * Displays the full health report with professional layout and download support.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wphm-wrap wphm-page wphm-page--report">

	<div class="wphm-page-header">
		<div class="wphm-page-header__left">
			<div class="wphm-page-header__icon-wrap wphm-page-header__icon-wrap--report">
				<span class="dashicons dashicons-chart-area"></span>
			</div>
			<div class="wphm-page-header__text">
				<h1 class="wphm-page-header__title"><?php esc_html_e( 'Health Report', 'wp-plugin-health-monitor' ); ?></h1>
				<p class="wphm-page-header__desc"><?php esc_html_e( 'Generate a comprehensive health report across all six monitoring modules.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
		<div class="wphm-page-header__actions">
			<button type="button"
				class="wphm-btn-primary"
				data-wphm-action="wphm_get_report"
				data-wphm-target="wphm-report-results"
				data-wphm-label="<?php esc_attr_e( 'Generate Report', 'wp-plugin-health-monitor' ); ?>">
				<span class="dashicons dashicons-chart-area"></span>
				<?php esc_html_e( 'Generate Report', 'wp-plugin-health-monitor' ); ?>
			</button>
		</div>
	</div>

	<!-- Download buttons — hidden until report is generated -->
	<div id="wphm-download-bar" class="wphm-download-bar" style="display:none;">
		<span class="wphm-download-bar__label">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Download Report:', 'wp-plugin-health-monitor' ); ?>
		</span>
		<button type="button" id="wphm-dl-pdf" class="button wphm-download-btn wphm-download-btn--pdf">
			<span class="dashicons dashicons-pdf"></span>
			<?php esc_html_e( 'PDF', 'wp-plugin-health-monitor' ); ?>
		</button>
		<button type="button" id="wphm-dl-text" class="button wphm-download-btn wphm-download-btn--text">
			<span class="dashicons dashicons-media-text"></span>
			<?php esc_html_e( 'Plain Text', 'wp-plugin-health-monitor' ); ?>
		</button>
		<button type="button" id="wphm-dl-json" class="button wphm-download-btn wphm-download-btn--json">
			<span class="dashicons dashicons-editor-code"></span>
			<?php esc_html_e( 'JSON', 'wp-plugin-health-monitor' ); ?>
		</button>
	</div>

	<div id="wphm-report-results">
		<div class="wphm-report-empty">
			<div class="wphm-report-empty__icon">
				<span class="dashicons dashicons-chart-area"></span>
			</div>
			<h2><?php esc_html_e( 'No Report Generated Yet', 'wp-plugin-health-monitor' ); ?></h2>
			<p><?php esc_html_e( 'Click "Generate Report" to run a full scan across all modules and compile a comprehensive health report for your WordPress site.', 'wp-plugin-health-monitor' ); ?></p>
			<p class="wphm-report-empty__sub"><?php esc_html_e( 'The report includes: Health Score, Plugin Conflicts, Performance Metrics, PHP Compatibility, Debug Log Analysis, and Duplicate Asset Detection.', 'wp-plugin-health-monitor' ); ?></p>
		</div>
	</div>
</div>
