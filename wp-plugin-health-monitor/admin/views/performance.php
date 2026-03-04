<?php
/**
 * Performance view template.
 *
 * Displays performance metrics and the health score breakdown.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wphm-wrap wphm-page wphm-page--performance">

	<div class="wphm-page-header">
		<div class="wphm-page-header__left">
			<div class="wphm-page-header__icon-wrap wphm-page-header__icon-wrap--perf">
				<span class="dashicons dashicons-performance"></span>
			</div>
			<div class="wphm-page-header__text">
				<h1 class="wphm-page-header__title"><?php esc_html_e( 'Performance Insights', 'wp-plugin-health-monitor' ); ?></h1>
				<p class="wphm-page-header__desc"><?php esc_html_e( 'Review enqueued assets, database queries, and autoloaded options.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
		<div class="wphm-page-header__actions">
			<button type="button"
				class="wphm-btn-primary"
				data-wphm-action="wphm_get_performance"
				data-wphm-target="wphm-performance-results"
				data-wphm-label="<?php esc_attr_e( 'Refresh Performance Data', 'wp-plugin-health-monitor' ); ?>">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh Performance Data', 'wp-plugin-health-monitor' ); ?>
			</button>
		</div>
	</div>

	<?php if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) : ?>
		<div class="wphm-alert wphm-alert--warning">
			<span class="dashicons dashicons-info-outline"></span>
			<div class="wphm-alert__body">
				<p class="wphm-alert__title"><?php esc_html_e( 'SAVEQUERIES Not Enabled', 'wp-plugin-health-monitor' ); ?></p>
				<p>
					<?php
					esc_html_e(
						'Database query count will not be available. To enable it, add define( \'SAVEQUERIES\', true ); to wp-config.php (not recommended for production).',
						'wp-plugin-health-monitor'
					);
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<div id="wphm-performance-results">
		<div class="wphm-alert wphm-alert--info">
			<span class="dashicons dashicons-info-outline"></span>
			<div class="wphm-alert__body">
				<p><?php esc_html_e( 'Click "Refresh Performance Data" to collect current metrics.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
	</div>

	<div class="wphm-section-title">
		<span class="dashicons dashicons-database"></span>
		<?php esc_html_e( 'Top Autoloaded Options', 'wp-plugin-health-monitor' ); ?>
	</div>
	<div class="wphm-table-wrap">
		<?php
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoloaded = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS size FROM {$wpdb->options} WHERE autoload = 'yes' ORDER BY size DESC LIMIT 20"
		);

		if ( $autoloaded ) :
			?>
			<table class="wphm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Option Name', 'wp-plugin-health-monitor' ); ?></th>
						<th><?php esc_html_e( 'Size', 'wp-plugin-health-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $autoloaded as $option ) : ?>
						<tr>
							<td><?php echo esc_html( $option->option_name ); ?></td>
							<td><?php echo esc_html( size_format( absint( $option->size ), 1 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="wphm-loading"><p><?php esc_html_e( 'No autoloaded options found.', 'wp-plugin-health-monitor' ); ?></p></div>
		<?php endif; ?>
	</div>
</div>
