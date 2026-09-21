<?php // phpcs:ignore
/**
 * Zero Spam class for enhanced site protection.
 *
 * Handles the core functionality of interacting with the Zero Spam API,
 * managing site settings for spam prevention, and reporting detections.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

defined( 'ABSPATH' ) || exit; // Prevent direct access.

/**
 * Core class for Zero Spam functionality.
 */
class Zero_Spam {
	/**
	 * Option holding detection reports waiting to be sent (not autoloaded).
	 *
	 * @var string
	 */
	const SHARE_QUEUE_OPTION = 'zerospam_share_queue';

	/**
	 * Maximum number of queued detection reports.
	 *
	 * @var int
	 */
	const SHARE_QUEUE_MAX = 100;

	/**
	 * Number of detection reports sent per cron run.
	 *
	 * @var int
	 */
	const SHARE_BATCH_SIZE = 20;

	/**
	 * Class constructor.
	 *
	 * Adds necessary hooks for initialization.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initialization.
	 *
	 * Fires after WordPress has finished loading but before any headers are sent.
	 * Registers plugin filters and actions.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', [ $this, 'sections' ] );
		add_filter( 'zerospam_settings', [ $this, 'settings' ], 10, 1 );
		add_action( 'zerospam_share_detection', [ $this, 'share_detection' ], 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'zerospam' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_filter( 'zerospam_access_checks', [ $this, 'access_check' ], 10, 2 );
		}

		// Register async share detection action.
		add_action( 'zerospam_async_share_detection', [ $this, 'process_share_detection' ] );
	}

	/**
	 * Site access check.
	 *
	 * Determines if a visitor should be blocked based on the zerospam.org API query results.
	 *
	 * @param array  $access_checks Existing access check results.
	 * @param string $user_ip       Visitor's IP address.
	 * @return array Updated access checks with Zero Spam results.
	 */
	public function access_check( $access_checks, $user_ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		$access_checks['zero_spam'] = [
			'blocked' => false,
		];

		$response = self::query( [ 'ip' => $user_ip ] );
		if ( $response && ! empty( $response['ip_addresses'][ $user_ip ] ) ) {
			$ip_data              = $response['ip_addresses'][ $user_ip ];
			$min_confidence_score = (float) $settings['zerospam_confidence_min']['value'];

			if ( ! empty( $ip_data['confidence'] ) ) {
				$confidence_score = (float) $ip_data['confidence'] * 100;

				if ( $confidence_score >= $min_confidence_score ) {
					$access_checks['zero_spam']['blocked'] = true;
					$access_checks['zero_spam']['type']    = 'blocked';
					$access_checks['zero_spam']['details'] = $ip_data;
					$access_checks['zero_spam']['details']['failed'] = sprintf(
						/* translators: %s: The calculated confidence score. */
						__( 'High Confidence Score: %s%%', 'zero-spam' ),
						$confidence_score
					);
				}
			}
		}

		return $access_checks;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['zerospam'] = array(
			'title' => __( 'Enhanced Protection', 'zero-spam' ),
			'icon'  => 'assets/img/icon.svg',
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-zerospam' );

		$settings['zerospam'] = array(
			'title'       => __( 'Status', 'zero-spam' ),
			'section'     => 'zerospam',
			'module'      => 'zerospam',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => __( 'Turn on spam checking using Zero Spam\'s spam database to block bad visitors.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam'] ) ? $options['zerospam'] : false,
			'recommended' => 'enabled',
		);

		$settings['zerospam_license'] = array(
			'title'       => __( 'License Key', 'zero-spam' ),
			'desc'        => __( 'Enter your Zero Spam license key to unlock spam protection features.', 'zero-spam' ),
			'section'     => 'zerospam',
			'module'      => 'zerospam',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your Zero Spam license key.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_license'] ) ? $options['zerospam_license'] : false,
		);

		if ( defined( 'ZEROSPAM_LICENSE_KEY' ) && ! $settings['zerospam_license']['value'] ) {
			$settings['zerospam_license']['value'] = ZEROSPAM_LICENSE_KEY;
		}

		$settings['zerospam_timeout'] = array(
			'title'       => __( 'API Timeout', 'zero-spam' ),
			'section'     => 'zerospam',
			'module'      => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zero-spam' ),
			'placeholder' => __( '5', 'zero-spam' ),
			'min'         => 0,
			'desc'        => __( 'How long to wait for a response from Zero Spam. Recommended: 5 seconds.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_timeout'] ) ? $options['zerospam_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['zerospam_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'zerospam',
			'module'      => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => WEEK_IN_SECONDS,
			'min'         => 0,
			'desc'        => __( 'How long to remember spam check results. Recommended: 14 days.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_cache'] ) ? $options['zerospam_cache'] : 14,
			'recommended' => 14,
		);

		$settings['zerospam_confidence_min'] = array(
			'title'       => __( 'Confidence Minimum', 'zero-spam' ),
			'section'     => 'zerospam',
			'module'      => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( '%', 'zero-spam' ),
			'placeholder' => __( '30', 'zero-spam' ),
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'desc'        => __( 'How sure we need to be that someone is a spammer before blocking them. Lower number blocks more. Recommended: 30%.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_confidence_min'] ) ? $options['zerospam_confidence_min'] : 30,
			'recommended' => 30,
		);

		return $settings;
	}

	/**
	 * Global API data.
	 */
	public function global_api_data() {
		$api_data                   = array();
		$api_data['reporter_email'] = sanitize_email( get_bloginfo( 'admin_email' ) );
		$api_data['app_key']        = \ZeroSpam\Core\Utilities::clean_domain( esc_url( site_url() ) );
		$api_data['app_type']       = 'wordpress';
		$api_data['app_details']    = wp_json_encode(
			array(
				'app_version'      => sanitize_text_field( get_bloginfo( 'version' ) ),
				'app_type_version' => sanitize_text_field( ZEROSPAM_VERSION ),
				'app_language'     => sanitize_text_field( strtolower( get_bloginfo( 'language' ) ) ),
				'app_email'        => sanitize_email( get_bloginfo( 'admin_email' ) ),
				'app_name'         => sanitize_text_field( get_bloginfo( 'name' ) ),
				'app_desc'         => sanitize_text_field( get_bloginfo( 'description' ) ),
			)
		);

		return $api_data;
	}

	/**
	 * Shares detection details with zerospam.org.
	 *
	 * Only the details needed for the report are kept, and the visitor's IP is
	 * captured now because the report is sent later from a cron request. Reports
	 * are queued in a single option and sent by one cron event, so detections
	 * never create a cron event each.
	 *
	 * @param array $data Contains all detection details. Must include 'type' and may include 'failed'.
	 * @return true
	 */
	public function share_detection( $data ) {
		if ( ! is_array( $data ) || empty( $data['type'] ) ) {
			return true;
		}

		$ip = \ZeroSpam\Core\User::get_ip();
		if ( ! $ip ) {
			return true;
		}

		$report = array(
			'type'   => sanitize_text_field( $data['type'] ),
			'failed' => isset( $data['failed'] ) && is_scalar( $data['failed'] ) ? sanitize_text_field( $data['failed'] ) : '',
			'ip'     => sanitize_text_field( $ip ),
			'email'  => self::get_report_email( $data ),
			'names'  => self::get_report_names( $data ),
		);

		$queue = get_option( self::SHARE_QUEUE_OPTION, array() );
		$queue = is_array( $queue ) ? $queue : array();
		$key   = md5( $report['type'] . '|' . $report['failed'] . '|' . $report['ip'] . '|' . $report['email'] );

		// Skip duplicates, and cap the queue so a flood of detections can't grow it without limit.
		if ( ! isset( $queue[ $key ] ) && count( $queue ) < self::SHARE_QUEUE_MAX ) {
			$queue[ $key ] = $report;
			update_option( self::SHARE_QUEUE_OPTION, $queue, false );
		}

		if ( ! wp_next_scheduled( 'zerospam_async_share_detection' ) ) {
			wp_schedule_single_event( time(), 'zerospam_async_share_detection' );
		}

		return true;
	}

	/**
	 * Sends queued detection reports to zerospam.org.
	 *
	 * Events scheduled by versions before 5.7.10 passed the raw detection data as
	 * an argument. That data is ignored: its IP would be the cron request's, not
	 * the visitor's.
	 */
	public function process_share_detection() {
		$queue = get_option( self::SHARE_QUEUE_OPTION, array() );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			return;
		}

		$batch     = array_slice( $queue, 0, self::SHARE_BATCH_SIZE, true );
		$remaining = array_slice( $queue, self::SHARE_BATCH_SIZE, null, true );

		// Save the rest of the queue before sending so a timeout can't resend this batch.
		if ( $remaining ) {
			update_option( self::SHARE_QUEUE_OPTION, $remaining, false );
		} else {
			delete_option( self::SHARE_QUEUE_OPTION );
		}

		foreach ( $batch as $report ) {
			if ( is_array( $report ) && ! empty( $report['type'] ) && ! empty( $report['ip'] ) ) {
				$this->send_report( $report );
			}
		}

		if ( $remaining && ! wp_next_scheduled( 'zerospam_async_share_detection' ) ) {
			wp_schedule_single_event( time(), 'zerospam_async_share_detection' );
		}

		// Successfully updated the last API request time.
		update_site_option( 'zero_spam_last_api_request', current_time( 'mysql' ) );
	}

	/**
	 * Sends a single queued detection report.
	 *
	 * @param array $report Report built by share_detection().
	 */
	private function send_report( $report ) {
		$global_data = self::global_api_data();

		$query_params = array(
			'report_type'   => 'ip_address',
			'report_module' => $report['type'],
			'report_key'    => $report['ip'],
			'report_failed' => $report['failed'],
		);

		// Build URL with query params - wrap in 'data' array for API format.
		$endpoint = add_query_arg( array( 'data' => array_merge( $query_params, $global_data ) ), ZEROSPAM_URL . 'wp-json/v6/report/' );
		self::remote_request( $endpoint );

		// The domain's DNS check is done here, in cron, rather than during the visitor's request.
		if ( empty( $report['email'] ) || ! \ZeroSpam\Core\Utilities::is_email( $report['email'] ) ) {
			return;
		}

		$report_details = array(
			'report_type'   => 'email_address',
			'report_module' => $report['type'],
			'report_key'    => $report['email'],
			'report_failed' => $report['failed'],
			// Encode email_details as JSON string (API expects JSON, not array).
			'email_details' => wp_json_encode(
				array(
					'names'     => ! empty( $report['names'] ) ? array_values( (array) $report['names'] ) : array(),
					'companies' => array(),
					'titles'    => array(),
					'phones'    => array(),
					'locations' => array(),
				)
			),
			// The IP being reported for email reports.
			'report_ip'     => $report['ip'],
		);

		$email_endpoint = add_query_arg( array( 'data' => array_merge( $report_details, $global_data ) ), ZEROSPAM_URL . 'wp-json/v6/report/' );
		self::remote_request( $email_endpoint );
	}

	/**
	 * Returns the email address to report from detection details, if any.
	 *
	 * Only the format is checked here; send_report() checks the domain.
	 *
	 * @param array $data Detection details.
	 * @return string Email address, or an empty string.
	 */
	private static function get_report_email( $data ) {
		$valid_email_fields = array(
			'comment_author_email', // Comments.
			'user_email',           // Registration.
			'email',                // WooCommerce Registration.
			'post' => array(        // Mailchimp.
				'EMAIL',
			),
			'data' => array(        // Give.
				'give_email',
			),
		);

		foreach ( $valid_email_fields as $key => $field ) {
			if ( is_array( $field ) ) {
				foreach ( $field as $f ) {
					if ( ! empty( $data[ $key ][ $f ] ) && is_string( $data[ $key ][ $f ] ) && is_email( $data[ $key ][ $f ] ) ) {
						return sanitize_email( $data[ $key ][ $f ] );
					}
				}
			} elseif ( ! empty( $data[ $field ] ) && is_string( $data[ $field ] ) && is_email( $data[ $field ] ) ) {
				return sanitize_email( $data[ $field ] );
			}
		}

		return '';
	}

	/**
	 * Returns the names to report alongside an email address.
	 *
	 * @param array $data Detection details.
	 * @return array Names.
	 */
	private static function get_report_names( $data ) {
		$valid_name_fields = array(
			'comment_author', // Comment.
			'user_login',     // Register.
			'username',       // WooCommerce Registration.
			'data' => array(  // Give.
				'give_first',
				'give_last',
			),
		);

		$names = array();
		foreach ( $valid_name_fields as $key => $field ) {
			if ( is_array( $field ) ) {
				$parts = array();
				foreach ( $field as $f ) {
					if ( ! empty( $data[ $key ][ $f ] ) && is_string( $data[ $key ][ $f ] ) ) {
						$parts[] = sanitize_text_field( $data[ $key ][ $f ] );
					}
				}
				if ( $parts ) {
					$names[] = implode( ' ', $parts );
				}
			} elseif ( ! empty( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$names[] = sanitize_text_field( $data[ $field ] );
			}
		}

		return $names;
	}

	/**
	 * Returns license key data from the API
	 *
	 * @param string $license The license key.
	 * @return array|false License data (with `license_key`) when valid,
	 *                     `array( 'status' => 'invalid' )` when the API rejected the key,
	 *                     or false when the key couldn't be verified (API unreachable).
	 */
	public static function get_license( $license ) {
		// Older versions replaced a rejected key with an error message, which is never a real key.
		if ( false !== stripos( $license, 'invalid' ) ) {
			return array( 'status' => 'invalid' );
		}

		$cache_key    = self::license_cache_key( $license );
		$license_data = get_transient( $cache_key );

		if ( false !== $license_data ) {
			return $license_data;
		}

		$endpoint = ZEROSPAM_URL . 'wp-json/v2/get-license';
		$endpoint = add_query_arg( 'license_key', $license, $endpoint );

		$response = self::remote_request( $endpoint );

		// Without an answer from the API the key is neither valid nor invalid, so don't cache anything.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $license_data['license_key'] ) ) {
			set_transient( $cache_key, $license_data, HOUR_IN_SECONDS );

			return $license_data;
		}

		if ( isset( $license_data['response'] ) && 'invalid_license' === $license_data['response'] ) {
			set_transient( $cache_key, array( 'status' => 'invalid' ), HOUR_IN_SECONDS );
			\ZeroSpam\Core\Utilities::log( 'Zero Spam License Check: invalid_license' );

			return array( 'status' => 'invalid' );
		}

		\ZeroSpam\Core\Utilities::log( 'Zero Spam License Check: unexpected response from the API.' );

		return false;
	}

	/**
	 * Clears the cached license check so the next check asks the API again
	 *
	 * @param string $license The license key.
	 */
	public static function delete_license_cache( $license ) {
		delete_transient( self::license_cache_key( $license ) );
	}

	/**
	 * Returns the transient key used to cache a license check
	 *
	 * @param string $license The license key.
	 * @return string
	 */
	private static function license_cache_key( $license ) {
		return sanitize_title( 'license_' . $license );
	}

	/**
	 * Query the Zero Spam Blacklist API
	 *
	 * @param array $params Array of query parameters.
	 */
	public static function query( $params ) {
		if (
			empty( $params['ip'] ) &&
			empty( $params['email'] )
		) {
			return false;
		}

		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['zerospam_license']['value'] ) ) {
			return false;
		}

		$cache_array = array( 'zero_spam' );
		$cache_array = array_merge( $cache_array, $params );
		$cache_key   = \ZeroSpam\Core\Utilities::cache_key( $cache_array );

		// Use persistent transient method instead of wp_cache_get.
		$response = get_transient( $cache_key );

		if ( false === $response ) {
			$endpoint = ZEROSPAM_URL . 'wp-json/v3/query';

			$query_params = array(
				'license_key' => $settings['zerospam_license']['value'],
			);

			if ( ! empty( $params['ip'] ) ) {
				$query_params['ip'] = $params['ip'];
			}

			if ( ! empty( $params['email'] ) ) {
				$query_params['email'] = $params['email'];
			}

			$endpoint = add_query_arg( $query_params, $endpoint );

			$args = array();
			$args['timeout'] = 5;
			if ( ! empty( $settings['zerospam_timeout'] ) ) {
				$args['timeout'] = intval( $settings['zerospam_timeout']['value'] );
			}

			// Use the circuit-breaker aware request method.
			$raw_response = self::remote_request( $endpoint, $args );

			if ( $raw_response && ! is_wp_error( $raw_response ) ) {
				$body     = wp_remote_retrieve_body( $raw_response );
				$response = json_decode( $body, true );

				if (
					! is_array( $response ) ||
					empty( $response['status'] ) ||
					200 !== $response['status'] ||
					empty( $response['body_response'] )
				) {
					// Cache the negative response for a shorter time (1 hour).
					set_transient( $cache_key, [ 'status' => 'error' ], HOUR_IN_SECONDS );

					if ( ! empty( $response['response'] ) ) {
						\ZeroSpam\Core\Utilities::log( $response['response'] );
					} else {
						\ZeroSpam\Core\Utilities::log( __( 'There was a problem querying the Zero Spam Blacklist API.', 'zero-spam' ) );
					}

					return false;
				}

				$response = $response['body_response'];

				$expiration = 14 * DAY_IN_SECONDS;
				if ( ! empty( $settings['zerospam_confidence_min']['value'] ) ) {
					$expiration = $settings['zerospam_confidence_min']['value'] * DAY_IN_SECONDS;
				}

				// Store persistently.
				set_transient( $cache_key, $response, $expiration );
			}
		} else {
			// Cache hit - track it if monitoring is enabled.
			if ( class_exists( '\ZeroSpam\Includes\API_Usage_Tracker' ) ) {
				\ZeroSpam\Includes\API_Usage_Tracker::track_cache_hit(
					ZEROSPAM_URL . 'wp-json/v3/query',
					$params
				);
			}
		}

		return $response;
	}

	/**
	 * Remote request wrapper with Circuit Breaker pattern and API usage tracking.
	 *
	 * @param string $endpoint The URL to request.
	 * @param array  $args     Request arguments.
	 * @return array|\WP_Error Response array or WP_Error.
	 */
	public static function remote_request( $endpoint, $args = [] ) {
		// Circuit Breaker: Check if circuit is open.
		if ( get_transient( 'zero_spam_circuit_open' ) ) {
			return new \WP_Error( 'circuit_open', 'API Circuit Breaker is open due to recent failures.' );
		}

		// Track start time for response time measurement.
		$start_time = microtime( true );

		$response = wp_remote_get( $endpoint, $args );

		// Calculate response time.
		$response_time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		// Track API call if monitoring is enabled.
		if ( class_exists( '\ZeroSpam\Includes\API_Usage_Tracker' ) ) {
			\ZeroSpam\Includes\API_Usage_Tracker::track_api_call(
				$endpoint,
				$response,
				array(
					'timeout' => isset( $args['timeout'] ) ? $args['timeout'] : 5,
				),
				$response_time_ms
			);
		}

		// Analyze response for Circuit Breaker. Only outages count as failures: other 4xx
		// responses (invalid license, query limit exceeded) mean the API is up and answering.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 429 === $response_code || $response_code >= 500 ) {
			// Increment failure count.
			$failures = (int) get_transient( 'zero_spam_failure_count' );
			$failures++;
			set_transient( 'zero_spam_failure_count', $failures, HOUR_IN_SECONDS );

			// Trip circuit if failures exceed threshold (5).
			if ( $failures > 5 ) {
				set_transient( 'zero_spam_circuit_open', true, 10 * MINUTE_IN_SECONDS );
				\ZeroSpam\Core\Utilities::log( 'Zero Spam API Circuit Breaker tripped. Pausing requests for 10 minutes.' );
			}

			return $response;
		}

		// Success: Reset failure count.
		delete_transient( 'zero_spam_failure_count' );

		return $response;
	}
}
