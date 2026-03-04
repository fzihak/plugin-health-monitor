<?php
/**
 * PHP Compatibility view template.
 *
 * Displays PHP version compatibility results for installed plugins.
 *
 * @package WP_Plugin_Health_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wphm-wrap wphm-page wphm-page--php-compat">

	<div class="wphm-page-header">
		<div class="wphm-page-header__left">
			<div class="wphm-page-header__icon-wrap wphm-page-header__icon-wrap--php">
				<span class="dashicons dashicons-editor-code"></span>
			</div>
			<div class="wphm-page-header__text">
				<h1 class="wphm-page-header__title"><?php esc_html_e( 'PHP Compatibility', 'wp-plugin-health-monitor' ); ?></h1>
				<p class="wphm-page-header__desc">
					<?php
					printf(
						/* translators: %s: Current PHP version. */
						esc_html__( 'Check if your plugins are compatible with your current PHP version (%s).', 'wp-plugin-health-monitor' ),
						esc_html( PHP_VERSION )
					);
					?>
				</p>
			</div>
		</div>
		<div class="wphm-page-header__actions">
			<button type="button"
				class="wphm-btn-primary"
				data-wphm-action="wphm_get_php_compat"
				data-wphm-target="wphm-php-compat-results"
				data-wphm-label="<?php esc_attr_e( 'Check Compatibility', 'wp-plugin-health-monitor' ); ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Check Compatibility', 'wp-plugin-health-monitor' ); ?>
			</button>
		</div>
	</div>

	<div id="wphm-php-compat-results">
		<div class="wphm-alert wphm-alert--info">
			<span class="dashicons dashicons-info-outline"></span>
			<div class="wphm-alert__body">
				<p><?php esc_html_e( 'Click "Check Compatibility" to scan all plugins against your current PHP version.', 'wp-plugin-health-monitor' ); ?></p>
			</div>
		</div>
	</div>
</div>
