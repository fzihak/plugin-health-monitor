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
$wphm_log_reader    = new WPHM_Debug_Log_Reader();
$wphm_log_path      = $wphm_log_reader->get_log_path();
$wphm_log_exists    = '' !== $wphm_log_path;
$wphm_log_expected  = $wphm_log_reader->get_expected_log_path();
$wphm_display_off   = defined( 'WP_DEBUG_DISPLAY' ) && ! WP_DEBUG_DISPLAY;
?>
<div class="wrap wphm-wrap wphm-page wphm-page--debug-log">

	<div class="wphm-page-header">
		<div class="wphm-page-header__left">
			<div class="wphm-page-header__icon-wrap wphm-page-header__icon-wrap--debug">
				<span class="dashicons dashicons-search"></span>
			</div>
			<div class="wphm-page-header__text">
				<h1 class="wphm-page-header__title"><?php esc_html_e( 'Debug Log Analyzer', 'health-radar' ); ?></h1>
				<p class="wphm-page-header__desc"><?php esc_html_e( 'Analyze your WordPress debug.log for errors, warnings, and notices.', 'health-radar' ); ?></p>
			</div>
		</div>
		<?php if ( $wphm_log_exists ) : ?>
		<div class="wphm-page-header__actions">
			<button type="button"
				class="wphm-btn-primary"
				data-wphm-action="wphm_get_debug_log"
				data-wphm-target="wphm-debug-log-results"
				data-wphm-label="<?php esc_attr_e( 'Analyze Debug Log', 'health-radar' ); ?>">
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Analyze Debug Log', 'health-radar' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>

	<!-- Debug Environment Status -->
	<div class="wphm-debug-status">
		<h3><?php esc_html_e( 'Debug Environment Status', 'health-radar' ); ?></h3>
		<table class="wphm-debug-status-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'WP_DEBUG', 'health-radar' ); ?></th>
					<td>
						<?php if ( $wphm_debug_active ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Enabled', 'health-radar' ); ?></span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--warning"><?php esc_html_e( 'Disabled', 'health-radar' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Core debug mode — enables error reporting.', 'health-radar' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP_DEBUG_LOG', 'health-radar' ); ?></th>
					<td>
						<?php if ( $wphm_log_active ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Enabled', 'health-radar' ); ?></span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--warning"><?php esc_html_e( 'Disabled', 'health-radar' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Writes errors to debug.log file.', 'health-radar' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP_DEBUG_DISPLAY', 'health-radar' ); ?></th>
					<td>
						<?php if ( $wphm_display_off ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Hidden', 'health-radar' ); ?></span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--info"><?php esc_html_e( 'Visible', 'health-radar' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Controls whether errors show on screen.', 'health-radar' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'debug.log file', 'health-radar' ); ?></th>
					<td>
						<?php if ( $wphm_log_exists ) : ?>
							<span class="wphm-badge wphm-badge--success"><?php esc_html_e( 'Found', 'health-radar' ); ?></span>
							<span class="wphm-debug-filesize">
									<?php echo esc_html( size_format( filesize( $wphm_log_path ), 1 ) ); ?>
							</span>
						<?php else : ?>
							<span class="wphm-badge wphm-badge--error"><?php esc_html_e( 'Not Found', 'health-radar' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
							<code><?php echo esc_html( $wphm_log_exists ? $wphm_log_path : $wphm_log_expected ); ?></code>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php if ( ! $wphm_log_exists ) : ?>
		<!-- Setup Guide when no log exists -->
		<div class="wphm-debug-setup">
			<h3><?php esc_html_e( 'How to Enable Debug Logging', 'health-radar' ); ?></h3>
			<div class="wphm-debug-setup__body">
				<p><?php esc_html_e( 'No debug.log file was found. To enable WordPress debug logging, add the following lines to your wp-config.php file, just before the line that says "That\'s all, stop editing!":', 'health-radar' ); ?></p>
				<div class="wphm-code-block">
					<div class="wphm-code-block__header">
						<span><?php esc_html_e( 'wp-config.php', 'health-radar' ); ?></span>
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
					<h3><?php esc_html_e( 'Important Notes', 'health-radar' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Never enable WP_DEBUG on a production site that is visible to visitors — set WP_DEBUG_DISPLAY to false.', 'health-radar' ); ?></li>
						<li><?php esc_html_e( 'The debug.log file may contain sensitive information. Restrict access via .htaccess or server configuration.', 'health-radar' ); ?></li>
						<li><?php esc_html_e( 'After enabling, reproduce the issue you are investigating, then return here to analyze the log.', 'health-radar' ); ?></li>
						<li><?php esc_html_e( 'Disable WP_DEBUG when you are finished debugging to avoid performance overhead.', 'health-radar' ); ?></li>
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
					<p><?php esc_html_e( 'Click "Analyze Debug Log" to scan and parse the debug.log file.', 'health-radar' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
