<?php
/**
 * API Monitoring Module
 *
 * Provides settings and UI for API usage monitoring and alerts.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * API Monitoring class
 */
class API_Monitoring {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialization
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
	}

	/**
	 * Register admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 * @return array Modified sections array.
	 */
	public function sections( $sections ) {
		$sections['api-monitoring'] = array(
			'title' => __( 'API Monitoring', 'zero-spam' ),
			'icon'  => 'assets/img/icon-reports.svg',
		);

		return $sections;
	}

	/**
	 * Register admin settings
	 *
	 * @param array $settings Array of available settings.
	 * @return array Modified settings array.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-api-monitoring' );

		$settings['api_monitoring'] = array(
			'title'       => __( 'Enable API Usage Monitoring', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => __( 'Track API calls, quota usage, and performance metrics for Zero Spam Enhanced Protection.', 'zero-spam' ),
			'value'       => ! empty( $options['api_monitoring'] ) ? $options['api_monitoring'] : false,
			'recommended' => false,
		);

		$settings['api_retention'] = array(
			'title'       => __( 'Data Retention Period', 'zero-spam' ),
			'desc'        => __( 'How long to keep detailed API usage data before automatic cleanup (days).', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'days', 'zero-spam' ),
			'placeholder' => 90,
			'min'         => 7,
			'max'         => 365,
			'value'       => ! empty( $options['api_retention'] ) ? $options['api_retention'] : 90,
			'recommended' => 90,
		);

		// Alert Settings Section.
		$settings['alert_email'] = array(
			'title'       => __( 'Email Alerts', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => sprintf(
				/* translators: %s: admin email address */
				__( 'Send email alerts to site administrator (%s) when issues are detected.', 'zero-spam' ),
				get_option( 'admin_email' )
			),
			'value'       => ! empty( $options['alert_email'] ) ? $options['alert_email'] : false,
			'recommended' => false,
		);

		$settings['alert_admin_notice'] = array(
			'title'       => __( 'Admin Dashboard Notices', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => __( 'Display alert notices in the WordPress admin dashboard.', 'zero-spam' ),
			'value'       => ! empty( $options['alert_admin_notice'] ) ? $options['alert_admin_notice'] : false,
			'recommended' => false,
		);

		$settings['alert_webhook_url'] = array(
			'title'       => __( 'Webhook URL', 'zero-spam' ),
			'desc'        => __( 'Optional: Enter a webhook URL to receive JSON-formatted alert notifications (e.g., Slack, Discord, PagerDuty).', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'url',
			'field_class' => 'regular-text',
			'placeholder' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
			'value'       => ! empty( $options['alert_webhook_url'] ) ? $options['alert_webhook_url'] : '',
		);

		if ( is_multisite() ) {
			$settings['alert_network'] = array(
				'title'       => __( 'Network-Wide Alerts', 'zero-spam' ),
				'section'     => 'api-monitoring',
				'module'      => 'api-monitoring',
				'type'        => 'checkbox',
				'options'     => array(
					'enabled' => __( 'Enabled', 'zero-spam' ),
				),
				'desc'        => sprintf(
					/* translators: %s: network admin email */
					__( 'Send network-level alerts to super admin (%s) for network-wide quota and usage issues.', 'zero-spam' ),
					get_site_option( 'admin_email' )
				),
				'value'       => ! empty( $options['alert_network'] ) ? $options['alert_network'] : false,
				'recommended' => false,
			);
		}

		// Individual alert type settings.
		$alert_types = \ZeroSpam\Includes\API_Usage_Alerts::get_alert_types();

		foreach ( $alert_types as $type => $config ) {
			$settings[ 'alert_' . $type ] = array(
				'title'       => $config['name'],
				'section'     => 'api-monitoring',
				'module'      => 'api-monitoring',
				'type'        => 'checkbox',
				'options'     => array(
					'enabled' => __( 'Enabled', 'zero-spam' ),
				),
				'desc'        => $this->get_alert_description( $type ),
				'value'       => ! empty( $options[ 'alert_' . $type ] ) ? $options[ 'alert_' . $type ] : false,
				'recommended' => $config['default_enabled'],
			);

			// Add threshold setting for this alert type.
			$settings[ 'threshold_' . $type ] = array(
				'title'       => sprintf(
					/* translators: %s: alert name */
					__( '%s Threshold', 'zero-spam' ),
					$config['name']
				),
				'section'     => 'api-monitoring',
				'module'      => 'api-monitoring',
				'type'        => 'number',
				'field_class' => 'small-text',
				'suffix'      => $this->get_threshold_suffix( $type ),
				'placeholder' => $config['default_threshold'],
				'min'         => 1,
				'value'       => ! empty( $options[ 'threshold_' . $type ] ) ? $options[ 'threshold_' . $type ] : $config['default_threshold'],
				'recommended' => $config['default_threshold'],
			);
		}

		// Test alert buttons.
		$settings['test_alerts'] = array(
			'title'   => __( 'Test Alerts', 'zero-spam' ),
			'desc'    => __( 'Send a test alert to verify your configuration is working correctly.', 'zero-spam' ),
			'section' => 'api-monitoring',
			'module'  => 'api-monitoring',
			'type'    => 'html',
			'html'    => $this->render_test_alert_buttons(),
		);

		// Quota reset date setting.
		$settings['quota_reset_date'] = array(
			'title'       => __( 'Quota Reset Date (Override)', 'zero-spam' ),
			'desc'        => __( 'Optional: Manually set your quota reset date if auto-detection is incorrect. Leave blank to auto-detect from API responses.', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'date',
			'field_class' => 'regular-text',
			'placeholder' => gmdate( 'Y-m-d', strtotime( '+1 month' ) ),
			'value'       => ! empty( $options['quota_reset_date'] ) ? $options['quota_reset_date'] : '',
		);

		// Uninstall data cleanup setting.
		$settings['delete_data_on_uninstall'] = array(
			'title'       => __( 'Delete Data on Uninstall', 'zero-spam' ),
			'section'     => 'api-monitoring',
			'module'      => 'api-monitoring',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => __( 'When enabled, all API usage data will be permanently deleted when the plugin is uninstalled. Disable to preserve historical data.', 'zero-spam' ),
			'value'       => ! empty( $options['delete_data_on_uninstall'] ) ? $options['delete_data_on_uninstall'] : false,
			'recommended' => false,
		);

		return $settings;
	}

	/**
	 * Get alert description based on type
	 *
	 * @param string $type Alert type.
	 * @return string Description text.
	 */
	private function get_alert_description( $type ) {
		$descriptions = array(
			'quota_warning'   => __( 'Triggered when API quota usage exceeds the threshold percentage.', 'zero-spam' ),
			'quota_critical'  => __( 'Triggered when API quota usage reaches critical levels.', 'zero-spam' ),
			'usage_spike'     => __( 'Triggered when daily API usage significantly exceeds normal baseline.', 'zero-spam' ),
			'high_error_rate' => __( 'Triggered when the percentage of failed API requests exceeds the threshold.', 'zero-spam' ),
			'slow_response'   => __( 'Triggered when average API response time exceeds the threshold (milliseconds).', 'zero-spam' ),
		);

		return isset( $descriptions[ $type ] ) ? $descriptions[ $type ] : '';
	}

	/**
	 * Get threshold suffix based on alert type
	 *
	 * @param string $type Alert type.
	 * @return string Suffix text.
	 */
	private function get_threshold_suffix( $type ) {
		$suffixes = array(
			'quota_warning'   => '%',
			'quota_critical'  => '%',
			'usage_spike'     => '% of baseline',
			'high_error_rate' => '%',
			'slow_response'   => 'ms',
		);

		return isset( $suffixes[ $type ] ) ? $suffixes[ $type ] : '';
	}

	/**
	 * Render test alert buttons
	 *
	 * @return string HTML for test alert buttons.
	 */
	private function render_test_alert_buttons() {
		$html = '<div class="zerospam-test-alerts">';

		$html .= sprintf(
			'<a href="%s" class="button button-secondary">%s</a> ',
			wp_nonce_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring&zerospam-action=test-alert-email' ), 'test-alert-email', 'zero-spam' ),
			__( 'Send Test Email', 'zero-spam' )
		);

		$html .= sprintf(
			'<a href="%s" class="button button-secondary">%s</a> ',
			wp_nonce_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring&zerospam-action=test-alert-notice' ), 'test-alert-notice', 'zero-spam' ),
			__( 'Trigger Admin Notice', 'zero-spam' )
		);

		$html .= sprintf(
			'<a href="%s" class="button button-secondary">%s</a>',
			wp_nonce_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring&zerospam-action=test-alert-webhook' ), 'test-alert-webhook', 'zero-spam' ),
			__( 'Send Test Webhook', 'zero-spam' )
		);

		$html .= '</div>';

		return $html;
	}
}
