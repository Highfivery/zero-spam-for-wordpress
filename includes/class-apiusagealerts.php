<?php
/**
 * API Usage Alerts class
 *
 * Handles alert configuration, detection, and notification for API usage monitoring.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * API Usage Alerts class
 */
class API_Usage_Alerts {

	/**
	 * Alert types and their default thresholds
	 *
	 * @var array
	 */
	private static $alert_types = array(
		'quota_warning'   => array(
			'name'              => 'Quota Warning',
			'default_threshold' => 80, // 80% used.
			'default_enabled'   => false,
			'severity'          => 'warning',
		),
		'quota_critical'  => array(
			'name'              => 'Quota Critical',
			'default_threshold' => 90, // 90% used.
			'default_enabled'   => false,
			'severity'          => 'critical',
		),
		'usage_spike'     => array(
			'name'              => 'Usage Spike',
			'default_threshold' => 300, // 300% of normal.
			'default_enabled'   => false,
			'severity'          => 'warning',
		),
		'high_error_rate' => array(
			'name'              => 'High Error Rate',
			'default_threshold' => 10, // 10% errors.
			'default_enabled'   => false,
			'severity'          => 'critical',
		),
		'slow_response'   => array(
			'name'              => 'Slow API Response',
			'default_threshold' => 5000, // 5000ms.
			'default_enabled'   => false,
			'severity'          => 'warning',
		),
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Schedule daily anomaly detection cron.
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( 'zerospam_check_api_anomalies', array( $this, 'check_anomalies' ) );
		add_action( 'zerospam_aggregate_api_data', array( $this, 'aggregate_data' ) );

		// Add admin notices for alerts.
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Schedule WP-Cron jobs
	 */
	public function schedule_cron() {
		// Hourly anomaly detection.
		if ( ! wp_next_scheduled( 'zerospam_check_api_anomalies' ) ) {
			wp_schedule_event( time(), 'hourly', 'zerospam_check_api_anomalies' );
		}

		// Daily data aggregation.
		if ( ! wp_next_scheduled( 'zerospam_aggregate_api_data' ) ) {
			wp_schedule_event( time(), 'daily', 'zerospam_aggregate_api_data' );
		}
	}

	/**
	 * Check for anomalies and send alerts
	 */
	public function check_anomalies() {
		// Only check if monitoring is enabled.
		if ( ! API_Usage_Tracker::is_monitoring_enabled() ) {
			return;
		}

		// For multisite, check each site.
		if ( is_multisite() ) {
			$sites = get_sites( array( 'number' => 100 ) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				$this->check_site_anomalies( $site->blog_id );
				restore_current_blog();
			}

			// Also check network-level anomalies.
			$this->check_network_anomalies();
		} else {
			$this->check_site_anomalies( get_current_blog_id() );
		}
	}

	/**
	 * Check anomalies for a specific site
	 *
	 * @param int $site_id Site ID.
	 */
	private function check_site_anomalies( $site_id ) {
		$anomalies = API_Usage_Tracker::detect_anomalies( $site_id );

		foreach ( $anomalies as $anomaly ) {
			$this->send_alert( $anomaly, $site_id );
		}
	}

	/**
	 * Check network-level anomalies (multisite only)
	 */
	private function check_network_anomalies() {
		// Get network-wide stats.
		$stats = API_Usage_Tracker::get_network_usage_stats( 'today' );

		// Check for network-level quota issues.
		if ( $stats['current_limit'] && $stats['current_remaining'] ) {
			$remaining_pct = ( $stats['current_remaining'] / $stats['current_limit'] ) * 100;

			if ( $remaining_pct < 20 ) {
				$anomaly = array(
					'type'     => 'quota_warning',
					'severity' => $remaining_pct <= 10 ? 'critical' : 'warning',
					'message'  => sprintf(
						/* translators: %s: percentage remaining */
						__( 'Network-wide API quota at %s%% remaining', 'zero-spam' ),
						number_format( $remaining_pct, 1 )
					),
					'value'    => $remaining_pct,
				);

				$this->send_network_alert( $anomaly );
			}
		}
	}

	/**
	 * Sends an alert for a site-level anomaly
	 *
	 * @param array $anomaly Anomaly data.
	 * @param int   $site_id Site ID.
	 */
	private function send_alert( $anomaly, $site_id ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		// Check if this alert type is enabled.
		$alert_key = 'alert_' . $anomaly['type'];
		if ( empty( $settings[ $alert_key ]['value'] ) || 'enabled' !== $settings[ $alert_key ]['value'] ) {
			return;
		}

		// Check throttling (max 1 email per alert type per 24 hours, except critical).
		if ( 'critical' !== $anomaly['severity'] ) {
			$throttle_key = "zerospam_alert_sent_{$site_id}_{$anomaly['type']}";
			if ( get_transient( $throttle_key ) ) {
				// Alert already sent recently.
				return;
			}

			// Set throttle for 24 hours.
			set_transient( $throttle_key, true, DAY_IN_SECONDS );
		}

		// Send email alert if enabled.
		if ( ! empty( $settings['alert_email']['value'] ) && 'enabled' === $settings['alert_email']['value'] ) {
			$this->send_email_alert( $anomaly, $site_id );
		}

		// Store admin notice.
		$this->store_admin_notice( $anomaly, $site_id );

		// Send webhook if configured.
		if ( ! empty( $settings['alert_webhook_url']['value'] ) ) {
			$this->send_webhook_alert( $anomaly, $site_id, $settings['alert_webhook_url']['value'] );
		}
	}

	/**
	 * Sends a network-level alert
	 *
	 * @param array $anomaly Anomaly data.
	 */
	private function send_network_alert( $anomaly ) {
		// Only for super admins.
		if ( ! is_multisite() ) {
			return;
		}

		$settings = \ZeroSpam\Core\Settings::get_settings();

		// Check if network alerts are enabled.
		if ( empty( $settings['alert_network']['value'] ) || 'enabled' !== $settings['alert_network']['value'] ) {
			return;
		}

		// Check throttling.
		if ( 'critical' !== $anomaly['severity'] ) {
			$throttle_key = "zerospam_network_alert_sent_{$anomaly['type']}";
			if ( get_transient( $throttle_key ) ) {
				return;
			}
			set_transient( $throttle_key, true, DAY_IN_SECONDS );
		}

		// Send to network admin email.
		$to      = get_site_option( 'admin_email' );
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[Zero Spam] Network Alert: %s', 'zero-spam' ),
			get_network()->site_name
		);

		$message = $this->format_alert_email( $anomaly, null, true );

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Sends an email alert
	 *
	 * @param array $anomaly Anomaly data.
	 * @param int   $site_id Site ID.
	 */
	private function send_email_alert( $anomaly, $site_id ) {
		$to = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: 1: severity, 2: site name */
			__( '[Zero Spam] %1$s Alert: %2$s', 'zero-spam' ),
			ucfirst( $anomaly['severity'] ),
			get_bloginfo( 'name' )
		);

		$message = $this->format_alert_email( $anomaly, $site_id );

		wp_mail( $to, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Formats alert email content
	 *
	 * @param array $anomaly        Anomaly data.
	 * @param int   $site_id        Site ID.
	 * @param bool  $is_network     Is network alert.
	 * @return string Email HTML content.
	 */
	private function format_alert_email( $anomaly, $site_id, $is_network = false ) {
		$stats = $is_network ? API_Usage_Tracker::get_network_usage_stats( 'today' ) : API_Usage_Tracker::get_usage_stats( $site_id, 'today' );

		$html  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px;">';

		// Header.
		$html .= '<div style="background: ' . ( 'critical' === $anomaly['severity'] ? '#d32f2f' : '#ff9800' ) . '; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">';
		$html .= '<h1 style="margin: 0; font-size: 24px;">' . esc_html( ucfirst( $anomaly['severity'] ) . ' Alert' ) . '</h1>';
		$html .= '</div>';

		// Content.
		$html .= '<div style="background: white; padding: 20px; border-radius: 0 0 8px 8px;">';
		$html .= '<h2 style="color: #2c3e50; margin-top: 0;">' . esc_html( $anomaly['message'] ) . '</h2>';

		// Stats summary.
		$html .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0;">';
		$html .= '<h3 style="margin-top: 0; color: #34495e;">Today\'s Statistics:</h3>';
		$html .= '<ul style="list-style: none; padding: 0;">';
		$html .= '<li><strong>API Calls:</strong> ' . number_format( $stats['api_calls'] ) . '</li>';
		$html .= '<li><strong>Cache Hits:</strong> ' . number_format( $stats['cache_hits'] ) . '</li>';
		$html .= '<li><strong>Errors:</strong> ' . number_format( $stats['errors'] ) . '</li>';

		if ( $stats['current_limit'] ) {
			$html .= '<li><strong>Quota Used:</strong> ' . number_format( $stats['current_made'] ) . ' / ' . number_format( $stats['current_limit'] ) . '</li>';
			$html .= '<li><strong>Quota Remaining:</strong> ' . number_format( $stats['current_remaining'] ) . '</li>';
		}

		$html .= '</ul>';
		$html .= '</div>';

		// Action link.
		$dashboard_url = $is_network ? network_admin_url( 'index.php' ) : admin_url( 'index.php' );
		$html         .= '<div style="text-align: center; margin-top: 20px;">';
		$html         .= '<a href="' . esc_url( $dashboard_url ) . '" style="display: inline-block; padding: 12px 30px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">View Dashboard</a>';
		$html         .= '</div>';

		// Add promotional content for quota-related alerts.
		if ( in_array( $anomaly['type'], array( 'quota_warning', 'quota_critical' ), true ) ) {
			$html .= $this->get_promotional_content( $anomaly, $stats );
		}

		// Footer.
		$html .= '<p style="margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center;">';
		$html .= 'This alert was sent by Zero Spam for WordPress. ';
		$html .= 'To configure alert settings, visit the <a href="' . esc_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring' ) ) . '">API Monitoring settings page</a>.';
		$html .= '</p>';

		$html .= '</div></div></body></html>';

		return $html;
	}

	/**
	 * Get promotional content for quota alerts
	 *
	 * @param array $anomaly Anomaly data.
	 * @param array $stats   Usage statistics.
	 * @return string Promotional HTML content.
	 */
	private function get_promotional_content( $anomaly, $stats ) {
		$is_critical = 'quota_critical' === $anomaly['type'] || 'critical' === $anomaly['severity'];

		// Detect current tier based on quota limit.
		$current_tier = 'Explorer';
		$next_tier    = 'Essentials';
		$next_quota   = '50,000';

		if ( $stats['current_limit'] ) {
			if ( $stats['current_limit'] >= 100000 ) {
				$current_tier = 'Business';
				$next_tier    = 'Platform';
				$next_quota   = '500,000';
			} elseif ( $stats['current_limit'] >= 50000 ) {
				$current_tier = 'Essentials';
				$next_tier    = 'Business';
				$next_quota   = '100,000';
			} elseif ( $stats['current_limit'] >= 10000 ) {
				$current_tier = 'Explorer';
				$next_tier    = 'Essentials';
				$next_quota   = '50,000';
			}
		}

		// Build UTM parameters.
		$utm_params = array(
			'utm_source'   => 'wordpress',
			'utm_medium'   => 'email',
			'utm_campaign' => 'quota_alert',
			'utm_content'  => $is_critical ? 'critical' : 'warning',
		);

		$pricing_url = add_query_arg( $utm_params, 'https://www.zerospam.org/pricing/' );

		// Build promotional section.
		$html = '';

		if ( $is_critical ) {
			// Critical: Urgent messaging.
			$html .= '<div style="margin-top: 30px; padding: 20px; background: #fcf0f1; border-left: 4px solid #d63638; border-radius: 4px;">';
			$html .= '<h3 style="margin-top: 0; color: #8c1c1c;">⚠️ Upgrade to Avoid Service Interruption</h3>';
			$html .= '<p style="margin: 10px 0; color: #2c3e50;">';
			$html .= 'You\'re close to hitting your API quota limit. To ensure uninterrupted spam protection for your site, upgrade to a higher plan now.';
			$html .= '</p>';

			$html .= '<div style="background: white; padding: 15px; border-radius: 4px; margin: 15px 0;">';
			$html .= '<p style="margin: 0 0 10px 0; font-size: 14px; color: #646970;"><strong>Currently on:</strong> ' . esc_html( $current_tier ) . ' (' . number_format( $stats['current_limit'] ) . ' queries/month)</p>';
			$html .= '<p style="margin: 0; font-size: 14px; color: #0c5d8f;"><strong>Upgrade to ' . esc_html( $next_tier ) . ':</strong> ' . esc_html( $next_quota ) . ' queries/month + priority support</p>';
			$html .= '</div>';

			$html .= '<div style="text-align: center; margin-top: 15px;">';
			$html .= '<a href="' . esc_url( $pricing_url ) . '" style="display: inline-block; padding: 14px 32px; background: #d32f2f; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">Upgrade Now →</a>';
			$html .= '</div>';

			$html .= '<p style="margin: 15px 0 0 0; font-size: 12px; color: #646970; text-align: center;">';
			$html .= '✓ Instant activation &nbsp;•&nbsp; ✓ 30-day money-back guarantee &nbsp;•&nbsp; ✓ Priority support';
			$html .= '</p>';

			$html .= '</div>';
		} else {
			// Warning: Softer, value-focused messaging.
			$html .= '<div style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">';
			$html .= '<h3 style="margin-top: 0; color: #0c5d8f;">💡 Need More API Quota?</h3>';
			$html .= '<p style="margin: 10px 0; color: #2c3e50;">';
			$html .= 'As your site grows, upgrading ensures continuous protection with increased API quota, advanced threat intelligence, and priority support.';
			$html .= '</p>';

			$html .= '<div style="background: white; padding: 15px; border-radius: 4px; margin: 15px 0;">';
			$html .= '<ul style="margin: 0; padding-left: 20px; color: #2c3e50;">';
			$html .= '<li><strong>Higher API quota</strong> – Never worry about hitting limits</li>';
			$html .= '<li><strong>Advanced geolocation data</strong> – Better threat insights</li>';
			$html .= '<li><strong>Detailed threat intelligence</strong> – Comprehensive reporting</li>';
			$html .= '<li><strong>Priority support</strong> – Get help when you need it</li>';
			$html .= '</ul>';
			$html .= '</div>';

			$html .= '<div style="text-align: center; margin-top: 15px;">';
			$html .= '<a href="' . esc_url( $pricing_url ) . '" style="display: inline-block; padding: 12px 28px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">View Plans →</a>';
			$html .= '</div>';

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Stores an admin notice for display
	 *
	 * @param array $anomaly Anomaly data.
	 * @param int   $site_id Site ID.
	 */
	private function store_admin_notice( $anomaly, $site_id ) {
		$notices = get_option( 'zerospam_api_notices', array() );

		$notices[] = array(
			'type'      => $anomaly['type'],
			'severity'  => $anomaly['severity'],
			'message'   => $anomaly['message'],
			'site_id'   => $site_id,
			'timestamp' => time(), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- We need a Unix timestamp for comparisons.
		);

		// Keep only last 10 notices.
		$notices = array_slice( $notices, -10 );

		update_option( 'zerospam_api_notices', $notices );
	}

	/**
	 * Displays admin notices
	 */
	public function display_admin_notices() {
		// Only show to admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = \ZeroSpam\Core\Settings::get_settings();

		// Check if admin notices are enabled.
		if ( empty( $settings['alert_admin_notice']['value'] ) || 'enabled' !== $settings['alert_admin_notice']['value'] ) {
			return;
		}

		$notices = get_option( 'zerospam_api_notices', array() );
		$site_id = get_current_blog_id();
		$now     = time(); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- We need a Unix timestamp for comparisons.

		foreach ( $notices as $key => $notice ) {
			// Only show notices for current site (or network admin for all).
			if ( ! is_network_admin() && $notice['site_id'] !== $site_id ) {
				continue;
			}

			// Only show notices from last 24 hours.
			if ( ( $now - $notice['timestamp'] ) > DAY_IN_SECONDS ) {
				unset( $notices[ $key ] );
				continue;
			}

			$class = 'critical' === $notice['severity'] ? 'notice-error' : 'notice-warning';

			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Zero Spam API Alert:', 'zero-spam' ) . '</strong> ' . esc_html( $notice['message'] ) . '</p>';
			echo '<p><a href="' . esc_url( admin_url( 'index.php' ) ) . '">' . esc_html__( 'View API Usage Dashboard', 'zero-spam' ) . '</a></p>';
			echo '</div>';
		}

		// Clean up old notices.
		update_option( 'zerospam_api_notices', $notices );
	}

	/**
	 * Sends webhook alert
	 *
	 * @param array  $anomaly     Anomaly data.
	 * @param int    $site_id     Site ID.
	 * @param string $webhook_url Webhook URL.
	 */
	private function send_webhook_alert( $anomaly, $site_id, $webhook_url ) {
		$stats = API_Usage_Tracker::get_usage_stats( $site_id, 'today' );

		$payload = array(
			'alert_type' => $anomaly['type'],
			'severity'   => $anomaly['severity'],
			'message'    => $anomaly['message'],
			'site_id'    => $site_id,
			'site_url'   => get_site_url( $site_id ),
			'site_name'  => get_bloginfo( 'name' ),
			'timestamp'  => current_time( 'mysql' ),
			'stats'      => $stats,
		);

		wp_remote_post(
			$webhook_url,
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Sends a test alert
	 *
	 * @param string $alert_type Alert type to test.
	 * @param string $method     Method: 'email', 'admin_notice', 'webhook'.
	 * @return bool Success status.
	 */
	public static function send_test_alert( $alert_type, $method = 'email' ) {
		$test_anomaly = array(
			'type'     => $alert_type,
			'severity' => 'warning',
			'message'  => __( 'This is a test alert from Zero Spam API Monitoring', 'zero-spam' ),
			'value'    => 0,
		);

		$instance = new self();

		switch ( $method ) {
			case 'email':
				$instance->send_email_alert( $test_anomaly, get_current_blog_id() );
				return true;

			case 'admin_notice':
				$instance->store_admin_notice( $test_anomaly, get_current_blog_id() );
				return true;

			case 'webhook':
				$settings = \ZeroSpam\Core\Settings::get_settings();
				if ( ! empty( $settings['alert_webhook_url']['value'] ) ) {
					$instance->send_webhook_alert( $test_anomaly, get_current_blog_id(), $settings['alert_webhook_url']['value'] );
					return true;
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Aggregate old data (run daily via cron)
	 */
	public function aggregate_data() {
		API_Usage_Tracker::aggregate_old_data();
	}

	/**
	 * Gets available alert types
	 *
	 * @return array Alert types.
	 */
	public static function get_alert_types() {
		return self::$alert_types;
	}
}
