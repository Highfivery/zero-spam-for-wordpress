<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps API_Usage_Alerts to this file name.
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
	 * Alert types, their default thresholds and the range a threshold may
	 * take (`threshold_{type}` settings in API Monitoring).
	 *
	 * @var array
	 */
	private static $alert_types = array(
		'quota_warning'   => array(
			'name'              => 'Quota Warning',
			'default_threshold' => 80, // 80% used.
			'min_threshold'     => 1,
			'max_threshold'     => 100,
			'default_enabled'   => false,
			'severity'          => 'warning',
		),
		'quota_critical'  => array(
			'name'              => 'Quota Critical',
			'default_threshold' => 90, // 90% used.
			'min_threshold'     => 1,
			'max_threshold'     => 100,
			'default_enabled'   => false,
			'severity'          => 'critical',
		),
		'usage_spike'     => array(
			'name'              => 'Usage Spike',
			'default_threshold' => 300, // 300% of normal.
			'min_threshold'     => 100,
			'max_threshold'     => 10000,
			'default_enabled'   => false,
			'severity'          => 'warning',
		),
		'high_error_rate' => array(
			'name'              => 'High Error Rate',
			'default_threshold' => 10, // 10% errors.
			'min_threshold'     => 1,
			'max_threshold'     => 100,
			'default_enabled'   => false,
			'severity'          => 'critical',
		),
		'slow_response'   => array(
			'name'              => 'Slow API Response',
			'default_threshold' => 5000, // 5000ms.
			'min_threshold'     => 100,
			'max_threshold'     => 60000,
			'default_enabled'   => false,
			'severity'          => 'warning',
		),
	);

	/**
	 * The threshold an alert type uses: its `threshold_{type}` setting,
	 * kept within the type's range, or the default when it is not a number.
	 *
	 * @since 6.0.0 Before, detection used fixed values and the settings were never read.
	 *
	 * @param string $type Alert type.
	 * @return int|float Threshold (0 for an unknown type).
	 */
	public static function threshold( string $type ) {
		return self::clamp_threshold( $type, \ZeroSpam\Core\Settings_Schema::value( 'threshold_' . $type ) );
	}

	/**
	 * A threshold value kept within an alert type's range.
	 *
	 * @since 6.0.0
	 *
	 * @param string $type  Alert type.
	 * @param mixed  $value Stored value.
	 * @return int|float The value within the range, or the default when it is not a number (0 for an unknown type).
	 */
	public static function clamp_threshold( string $type, $value ) {
		if ( ! isset( self::$alert_types[ $type ] ) ) {
			return 0;
		}

		$config = self::$alert_types[ $type ];

		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return $config['default_threshold'];
		}

		return max( $config['min_threshold'], min( $config['max_threshold'], 0 + $value ) );
	}

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
				switch_to_blog( (int) $site->blog_id );
				$this->check_site_anomalies( (int) $site->blog_id );
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

		// Check for network-level quota issues, with the API Monitoring thresholds (share of the quota used).
		if ( $stats['current_limit'] && is_numeric( $stats['current_remaining'] ) ) {
			$remaining_pct = ( $stats['current_remaining'] / $stats['current_limit'] ) * 100;
			$used_pct      = 100 - $remaining_pct;

			if ( $used_pct > self::threshold( 'quota_warning' ) || $used_pct >= self::threshold( 'quota_critical' ) ) {
				$anomaly = array(
					'type'     => 'quota_warning',
					'severity' => $used_pct >= self::threshold( 'quota_critical' ) ? 'critical' : 'warning',
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

		// Quota alerts: once per quota period (the hourly check would repeat a used-up quota until it resets).
		$quota = self::quota_throttle( "zerospam_alert_sent_{$site_id}_{$anomaly['type']}" );
		if ( null !== $quota ) {
			if ( get_transient( $quota['key'] ) ) {
				return;
			}
			set_transient( $quota['key'], true, $quota['ttl'] );
		} elseif ( 'critical' !== $anomaly['severity'] ) {
			// Other alerts: at most one per type per 24 hours, except critical ones.
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

		// Check throttling: the network's quota alert once per quota period and severity.
		$quota = self::quota_throttle( "zerospam_network_alert_sent_{$anomaly['type']}_{$anomaly['severity']}" );
		if ( null !== $quota ) {
			if ( get_transient( $quota['key'] ) ) {
				return;
			}
			set_transient( $quota['key'], true, $quota['ttl'] );
		}

		// Send to network admin email.
		$to      = get_site_option( 'admin_email' );
		$network = wp_specialchars_decode( (string) get_network()->site_name, ENT_QUOTES );
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[Zero Spam] Network Alert: %s', 'zero-spam' ),
			$network
		);

		Email::send( $to, $subject, $this->format_alert_email( $anomaly, null, true ) );
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
			/* translators: 1: severity (Warning or Critical), 2: site name */
			__( '[Zero Spam] %1$s Alert: %2$s', 'zero-spam' ),
			self::severity_label( (string) $anomaly['severity'] ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);

		Email::send( $to, $subject, $this->format_alert_email( $anomaly, $site_id ) );
	}

	/**
	 * The translated name of an alert severity.
	 *
	 * @param string $severity Severity: warning or critical.
	 */
	private static function severity_label( string $severity ): string {
		switch ( $severity ) {
			case 'critical':
				return __( 'Critical', 'zero-spam' );
			case 'warning':
				return __( 'Warning', 'zero-spam' );
		}

		return ucfirst( $severity );
	}

	/**
	 * The alert email (Includes\Email message): what happened, today's usage and where to look.
	 *
	 * @param array    $anomaly    Anomaly data.
	 * @param int|null $site_id    Site ID (null for a network alert).
	 * @param bool     $is_network Is network alert.
	 * @return array Email message.
	 */
	private function format_alert_email( $anomaly, $site_id, $is_network = false ) {
		$stats = $is_network ? API_Usage_Tracker::get_network_usage_stats( 'today' ) : API_Usage_Tracker::get_usage_stats( $site_id, 'today' );
		$name  = $is_network ? (string) get_network()->site_name : (string) get_bloginfo( 'name' );
		$name  = wp_specialchars_decode( $name, ENT_QUOTES );

		$rows = array(
			array( __( 'API calls', 'zero-spam' ), number_format_i18n( (int) $stats['api_calls'] ) ),
			array( __( 'Cache hits', 'zero-spam' ), number_format_i18n( (int) $stats['cache_hits'] ) ),
			array( __( 'Errors', 'zero-spam' ), number_format_i18n( (int) $stats['errors'] ) ),
		);

		if ( ! empty( $stats['current_limit'] ) ) {
			$rows[] = array(
				__( 'Queries used', 'zero-spam' ),
				sprintf(
					/* translators: 1: queries used, 2: queries allowed */
					__( '%1$s of %2$s', 'zero-spam' ),
					number_format_i18n( (int) $stats['current_made'] ),
					number_format_i18n( (int) $stats['current_limit'] )
				),
			);
			$rows[] = array( __( 'Queries left', 'zero-spam' ), number_format_i18n( (int) $stats['current_remaining'] ) );
		}

		return array(
			'site'    => $name,
			'heading' => (string) $anomaly['message'],
			'blocks'  => array(
				array(
					'type' => 'text',
					'text' => $is_network
						/* translators: 1: severity (Warning or Critical), 2: network name */
						? __( '%1$s alert: Zero Spam found this while checking API usage across the %2$s network.', 'zero-spam' )
						/* translators: 1: severity (Warning or Critical), 2: site name */
						: __( '%1$s alert: Zero Spam found this while checking API usage on %2$s.', 'zero-spam' ),
					'args' => array(
						array(
							'text'  => self::severity_label( (string) $anomaly['severity'] ),
							'style' => 'strong',
						),
						array(
							'text'  => $name,
							'style' => 'strong',
						),
					),
				),
				array(
					'type'  => 'rows',
					'title' => __( 'Today', 'zero-spam' ),
					'rows'  => $rows,
				),
				// TODO(6.0 cutover): menu path (API usage and API monitoring settings links).
				array(
					'type'  => 'button',
					'label' => __( 'View API usage', 'zero-spam' ),
					'url'   => $is_network ? network_admin_url( 'index.php' ) : admin_url( 'index.php' ),
				),
				array(
					'type' => 'text',
					'size' => 'small',
					/* translators: %s: link to the API monitoring settings */
					'text' => __( 'To change which alerts you get, go to %s.', 'zero-spam' ),
					'args' => array(
						array(
							'text'  => $is_network ? __( 'Zero Spam network settings', 'zero-spam' ) : __( 'API monitoring settings', 'zero-spam' ),
							'style' => 'link',
							'url'   => $is_network ? network_admin_url( 'settings.php?page=zerospam-network-settings' ) : admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=api-monitoring' ),
						),
					),
				),
			),
		);
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
			echo '<p><a href="' . esc_url( \ZeroSpam\Core\Utilities::admin_link( 'api-monitoring' ) ) . '">' . esc_html__( 'Open API Monitoring settings', 'zero-spam' ) . '</a></p>';
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
	 * @return array|\WP_Error The HTTP API response (since 6.0.0).
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

		// The URL is an administrator setting: refuse local and private addresses
		// (allow a trusted internal host with the http_request_host_is_external filter).
		return wp_safe_remote_post(
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
					$response = $instance->send_webhook_alert( $test_anomaly, get_current_blog_id(), $settings['alert_webhook_url']['value'] );
					$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

					// Since 6.0.0 a webhook that fails or answers with an error is reported as not sent.
					return $code >= 200 && $code < 300;
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
	 * The throttle of a quota alert: one per quota period.
	 *
	 * The period is the one of the Quota Reset Date (License_Status::
	 * quota_period()); without a date, quota alerts judge today's reports
	 * only, so the period is the day. The key ends with the period's first
	 * day, so the alert can be sent again once the quota has reset.
	 *
	 * @since 6.0.0
	 *
	 * @param string $prefix Transient name without the period.
	 * @return array{key: string, ttl: int}|null Null for an alert that is not about the quota.
	 */
	private static function quota_throttle( string $prefix ): ?array {
		if ( false === strpos( $prefix, '_quota_' ) ) {
			return null;
		}

		$period = \ZeroSpam\Core\License_Status::quota_period();

		if ( null === $period ) {
			return array(
				'key' => $prefix . '_' . current_time( 'Ymd' ),
				'ttl' => DAY_IN_SECONDS,
			);
		}

		$until = (int) strtotime( $period['next'] . ' 23:59:59' ) - (int) strtotime( current_time( 'mysql' ) );

		return array(
			'key' => $prefix . '_' . str_replace( '-', '', $period['start'] ),
			'ttl' => max( HOUR_IN_SECONDS, $until ),
		);
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
