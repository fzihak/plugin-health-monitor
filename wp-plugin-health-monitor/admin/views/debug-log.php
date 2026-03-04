<?php
/**
 * Debug Log view template.
 *
 * Displays parsed debug.log contents and error summaries.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wphm_debug_active  = defined( 'WP_DEBUG' ) && WP_DEBUG;
$wphm_log_active    = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
$wphm_log_exists    = file_exists( WP_CONTENT_DIR . '/debug.log' );
$wphm_display_off   = defined( 'WP_DEBUG_DISPLAY' ) && ! WP_DEBUG_DISPLAY;
?>
<div class="wrap wphm-wrap wphm-page wphm-page--debug-log">

	<div class="wphm-page-header">
		<div class="wphm-page-header__left">
			<div class="wphm-page-header__icon-wrap wphm-page-header__icon-wrap--debug">
				<span class="dashicons dashicons-search"></span>
			</div>
			<div class="wphm-page-header__text">
				<h1 class="wphm-page-header__title"><?php esc_html_e( 'Debug Log Analyzer', 'wp-plugin-health-monitor' ); ?></h1>
				<p class="wphm-page-header__desc"><?php esc_html_e( 'Analyze your WordPress debug.log for errors, warnings, and notices.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
		<?php if ( $wphm_log_exists ) : ?>
		<div class="wphm-page-header__actions">
			<button type="button"
				class="wphm-btn-primary"
				data-wphm-action="wphm_get_debug_log"
				data-wphm-target="wphm-debug-log-results"
				data-wphm-label="<?php esc_attr_e( 'Analyze Debug Log', 'wp-plugin-health-monitor' ); ?>">
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Analyze Debug Log', 'wp-plugin-health-monitor' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>

	<!-- Debug Environment Status -->
	<div class="wphm-debug-status">
		<h3><?php esc_html_e( 'Debug Environment Status', 'wp-plugin-health-monitor' ); ?></h3>
		<table class="wphm-debug-status-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'WP_DEBUG', 'wp-plugin-health-monitor' ); ?></th>
					<td>
						<?php if ( $wphm_debug_active ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Enabled', 'wp-plugin-health-monitor' ); ?></span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--warning"><?php esc_html_e( 'Disabled', 'wp-plugin-health-monitor' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Core debug mode — enables error reporting.', 'wp-plugin-health-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP_DEBUG_LOG', 'wp-plugin-health-monitor' ); ?></th>
					<td>
						<?php if ( $wphm_log_active ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Enabled', 'wp-plugin-health-monitor' ); ?></span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--warning"><?php esc_html_e( 'Disabled', 'wp-plugin-health-monitor' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Writes errors to debug.log file.', 'wp-plugin-health-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP_DEBUG_DISPLAY', 'wp-plugin-health-monitor' ); ?></th>
					<td>
						<?php if ( $wphm_display_off ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Hidden', 'wp-plugin-health-monitor' ); ?></span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--info"><?php esc_html_e( 'Visible', 'wp-plugin-health-monitor' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Controls whether errors show on screen.', 'wp-plugin-health-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'debug.log file', 'wp-plugin-health-monitor' ); ?></th>
					<td>
						<?php if ( $wphm_log_exists ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Found', 'wp-plugin-health-monitor' ); ?></span>
							<span class="wphm-debug-filesize">
								<?php echo esc_html( size_format( filesize( WP_CONTENT_DIR . '/debug.log' ), 1 ) ); ?>
							</span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--error"><?php esc_html_e( 'Not Found', 'wp-plugin-health-monitor' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<code><?php echo esc_html( WP_CONTENT_DIR . '/debug.log' ); ?></code>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php if ( ! $wphm_log_exists ) : ?>
		<!-- Setup Guide when no log exists -->
		<div class="wphm-debug-setup">
			<h3><?php esc_html_e( 'How to Enable Debug Logging', 'wp-plugin-health-monitor' ); ?></h3>
			<div class="wphm-debug-setup__body">
				<p><?php esc_html_e( 'No debug.log file was found. To enable WordPress debug logging, add the following lines to your wp-config.php file, just before the line that says "That\'s all, stop editing!":', 'wp-plugin-health-monitor' ); ?></p>
				<div class="wphm-code-block">
					<div class="wphm-code-block__header">
						<span><?php esc_html_e( 'wp-config.php', 'wp-plugin-health-monitor' ); ?></span>
					</div>
					<pre class="wphm-code-block__code"><code>// Enable debug mode
define( 'WP_DEBUG', true );

// Log errors to wp-content/debug.log
define( 'WP_DEBUG_LOG', true );

// Do not display errors on the site
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );</code></pre>
				</div>
				<div class="wphm-debug-setup__tips">
					<h3><?php esc_html_e( 'Important Notes', 'wp-plugin-health-monitor' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Never enable WP_DEBUG on a production site that is visible to visitors — set WP_DEBUG_DISPLAY to false.', 'wp-plugin-health-monitor' ); ?></li>
						<li><?php esc_html_e( 'The debug.log file may contain sensitive information. Restrict access via .htaccess or server configuration.', 'wp-plugin-health-monitor' ); ?></li>
						<li><?php esc_html_e( 'After enabling, reproduce the issue you are investigating, then return here to analyze the log.', 'wp-plugin-health-monitor' ); ?></li>
						<li><?php esc_html_e( 'Disable WP_DEBUG when you are finished debugging to avoid performance overhead.', 'wp-plugin-health-monitor' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div id="wphm-debug-log-results">
		<?php if ( $wphm_log_exists ) : ?>
			<div class="wphm-alert wphm-alert--info">
				<span class="dashicons dashicons-info-outline"></span>
				<div class="wphm-alert__body">
					<p><?php esc_html_e( 'Click "Analyze Debug Log" to scan and parse the debug.log file.', 'wp-plugin-health-monitor' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
