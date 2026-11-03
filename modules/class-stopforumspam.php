<?php
/**
 * Stop Forum Spam class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Stop Forum Spam
 */
class StopForumSpam {
	/**
	 * The service's lookup address.
	 */
	const ENDPOINT = 'https://api.stopforumspam.org/api';

	/**
	 * Start of the transients holding answers (uninstall sweeps `zerospam_`).
	 */
	const TRANSIENT_PREFIX = 'zerospam_sfs_';

	/**
	 * Transient counting recent failed lookups (an outage, not a "not listed").
	 */
	const FAILURES_TRANSIENT = 'zerospam_sfs_failure_count';

	/**
	 * Transient set while lookups are paused after repeated failures.
	 */
	const CIRCUIT_TRANSIENT = 'zerospam_sfs_circuit_open';

	/**
	 * Failed lookups in a row that pause lookups.
	 */
	const CIRCUIT_THRESHOLD = 5;

	/**
	 * How long lookups are paused (seconds).
	 */
	const CIRCUIT_COOL_OFF = 10 * MINUTE_IN_SECONDS;

	/**
	 * How long an answer that lists nothing is kept (seconds). Answers that
	 * list a value are kept for the Cache Expiration setting.
	 */
	const CLEAN_TTL = DAY_IN_SECONDS;

	/**
	 * Longest email address looked up (RFC 5321).
	 */
	const MAX_EMAIL_LENGTH = 254;

	/**
	 * Largest answer read; a real one is well under a kilobyte.
	 */
	const MAX_RESPONSE_BYTES = 65536;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_failed_types', array( $this, 'failed_types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'stop_forum_spam' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_filter( 'zerospam_access_checks', array( $this, 'access_check' ), 10, 2 );
			add_filter( 'zerospam_preprocess_registration_submission', array( $this, 'preprocess_registrations' ), 10, 3 );
			add_filter( 'zerospam_preprocess_comment_submission', array( $this, 'preprocess_comments' ), 10, 3 );
		}
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['stop_forum_spam'] = array(
			'title' => __( 'Stop Forum Spam', 'zero-spam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-stop_forum_spam' );

		$settings['stop_forum_spam'] = array(
			'title'       => __( 'Status', 'zero-spam' ),
			'section'     => 'stop_forum_spam',
			'module'      => 'stop_forum_spam',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => __( 'Check if visitors have been reported as spammers by other websites.', 'zero-spam' ),
			'value'       => ! empty( $options['stop_forum_spam'] ) ? $options['stop_forum_spam'] : false,
			'recommended' => 'enabled',
		);

		$settings['stop_forum_spam_timeout'] = array(
			'title'       => __( 'API Timeout', 'zero-spam' ),
			'section'     => 'stop_forum_spam',
			'module'      => 'stop_forum_spam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zero-spam' ),
			'placeholder' => __( '5', 'zero-spam' ),
			'min'         => 0,
			'desc'        => __( 'How long to wait for a response. Recommended: 5 seconds.', 'zero-spam' ),
			'value'       => ! empty( $options['stop_forum_spam_timeout'] ) ? $options['stop_forum_spam_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['stop_forum_spam_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'stop_forum_spam',
			'module'      => 'stop_forum_spam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => __( '14', 'zero-spam' ),
			'min'         => 0,
			'desc'        => __( 'How long to remember spam check results. Recommended: 14 days.', 'zero-spam' ),
			'value'       => ! empty( $options['stop_forum_spam_cache'] ) ? $options['stop_forum_spam_cache'] : 14,
			'recommended' => 14,
		);

		$settings['stop_forum_spam_confidence_min'] = array(
			'title'       => __( 'Confidence Minimum', 'zero-spam' ),
			'section'     => 'stop_forum_spam',
			'module'      => 'stop_forum_spam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( '%', 'zero-spam' ),
			'placeholder' => __( '50', 'zero-spam' ),
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'desc'        => __( 'How sure we need to be before blocking. Lower blocks more visitors. Recommended: 50%.', 'zero-spam' ),
			'value'       => ! empty( $options['stop_forum_spam_confidence_min'] ) ? $options['stop_forum_spam_confidence_min'] : 50,
			'recommended' => 50,
		);

		return $settings;
	}

	/**
	 * Adds the labels of the reasons this module logs.
	 *
	 * @param array $types Array of failed types.
	 * @return array
	 */
	public function failed_types( $types ) {
		$types['stop_forum_spam']       = __( 'Stop Forum Spam', 'zero-spam' );
		$types['stop_forum_spam_email'] = __( 'Stop Forum Spam (email address)', 'zero-spam' );
		$types['stop_forum_spam_ip']    = __( 'Stop Forum Spam (IP address)', 'zero-spam' );

		// Comment detections logged before 6.0 carry the reason `0`.
		$types['0'] = __( 'Stop Forum Spam (earlier version)', 'zero-spam' );

		return $types;
	}

	/**
	 * Processes comments
	 *
	 * The comments module logs each error's key as the reason, without its
	 * `zerospam_` prefix.
	 *
	 * @param array  $errors            Array of errors.
	 * @param array  $post              Post array.
	 * @param string $detection_msg_key Detection message key.
	 * @return array
	 */
	public function preprocess_comments( $errors, $post, $detection_msg_key = 'comment_spam_message' ) {
		if ( ! is_array( $errors ) || ! is_array( $post ) ) {
			return $errors;
		}

		$email = $this->email_from( $post, array( 'comment_author_email' ) );
		if ( '' === $email ) {
			return $errors;
		}

		return $this->add_errors( $errors, array( 'email' => $email ), $detection_msg_key );
	}

	/**
	 * Processes registrations
	 *
	 * Runs on `zerospam_preprocess_registration_submission`. The module firing
	 * the filter refuses the registration, logs each error's key as the
	 * reason (without its `zerospam_` prefix) and shares the detection, so a
	 * listed email or IP adds one error each. The username is not looked up:
	 * the service lists many common first names (james, karen, admin), so it
	 * would refuse real people.
	 *
	 * @param array  $errors            Array of errors.
	 * @param array  $post              Form post array; the email is in `user_email` or `email`.
	 * @param string $detection_msg_key Detection message key.
	 * @return array
	 */
	public function preprocess_registrations( $errors, $post, $detection_msg_key = 'registration_spam_message' ) {
		if ( ! is_array( $errors ) || ! is_array( $post ) ) {
			return $errors;
		}

		if ( 'enabled' !== \ZeroSpam\Core\Settings_Schema::value( 'stop_forum_spam' ) ) {
			return $errors;
		}

		$params = array_filter(
			array(
				'email' => $this->email_from( $post, array( 'user_email', 'email' ) ),
				'ip'    => $this->visitor_ip(),
			),
			static function ( string $value ): bool {
				return '' !== $value;
			}
		);
		if ( ! $params ) {
			return $errors;
		}

		return $this->add_errors( $errors, $params, $detection_msg_key );
	}

	/**
	 * Looks up values and adds one error per value the service lists.
	 *
	 * Each value is looked up on its own, so the IP's answer from the visit's
	 * access check (and an email's from an earlier comment) is reused.
	 *
	 * @param array  $errors            Array of errors.
	 * @param array  $params            Values by field (`email`, `ip`).
	 * @param string $detection_msg_key Detection message key.
	 * @return array
	 */
	private function add_errors( array $errors, array $params, $detection_msg_key ): array {
		foreach ( $params as $field => $value ) {
			if ( $this->is_listed( $this->lookup( array( $field => $value ) ), $field ) ) {
				$errors[ 'zerospam_stop_forum_spam_' . $field ] = \ZeroSpam\Core\Utilities::detection_message( $detection_msg_key );
			}
		}

		return $errors;
	}

	/**
	 * The first valid email address under the given keys, or ''.
	 *
	 * @param array    $post Form post array.
	 * @param string[] $keys Keys to read.
	 */
	private function email_from( array $post, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! empty( $post[ $key ] ) && is_string( $post[ $key ] ) ) {
				$email = sanitize_email( wp_unslash( $post[ $key ] ) );

				return strlen( $email ) <= self::MAX_EMAIL_LENGTH && is_email( $email ) ? $email : '';
			}
		}

		return '';
	}

	/**
	 * The visitor's IP address, or '' when it is not known.
	 */
	private function visitor_ip(): string {
		$ip = \ZeroSpam\Core\User::get_ip();

		return is_string( $ip ) && rest_is_ip_address( $ip ) ? $ip : '';
	}

	/**
	 * The service's answer to a lookup.
	 *
	 * @param array $params Query parameters.
	 * @return array The decoded answer, or an empty array when the lookup failed.
	 */
	private function lookup( array $params ): array {
		$response = $this->query( $params );
		if ( ! is_string( $response ) ) {
			return array();
		}

		$answer = json_decode( $response, true );

		return is_array( $answer ) ? $answer : array();
	}

	/**
	 * Whether an answer lists a field at or above the configured confidence.
	 *
	 * Anything else, including an answer of the wrong shape, means "not listed":
	 * a failure of the service must never stop a real visitor.
	 *
	 * @param array  $answer Decoded answer.
	 * @param string $field  Field: `email` or `ip`.
	 */
	private function is_listed( array $answer, string $field ): bool {
		$entry   = isset( $answer[ $field ] ) && is_array( $answer[ $field ] ) ? $answer[ $field ] : array();
		$minimum = \ZeroSpam\Core\Settings_Schema::value( 'stop_forum_spam_confidence_min' );

		return ! empty( $entry['appears'] ) &&
			isset( $entry['confidence'] ) &&
			is_numeric( $entry['confidence'] ) &&
			is_numeric( $minimum ) &&
			(float) $entry['confidence'] >= (float) $minimum;
	}

	/**
	 * Query the Stop Forum Spam API
	 *
	 * Successful answers are kept across requests in a transient (which uses
	 * the persistent object cache when the site has one): one that lists a
	 * value for the Cache Expiration days, one that lists nothing for a day.
	 * Failures of any kind are not kept, so the next lookup retries; after
	 * CIRCUIT_THRESHOLD outages in a row (errors, timeouts, 429 or 5xx)
	 * lookups are skipped for CIRCUIT_COOL_OFF, failing open. The stored answer leaves out the `value` the service repeats
	 * from the request, so no email address stays in the options table; its
	 * name only holds a hash.
	 *
	 * @param array $params Array of query parameters.
	 * @return string|false The answer as JSON, or false when the lookup failed.
	 */
	public function query( $params ) {
		// Hash names and values: a key built from the values alone, run
		// through sanitize_title(), maps different IPs and emails to one entry.
		$cache_key = self::TRANSIENT_PREFIX . md5( (string) wp_json_encode( $params ) );

		$cached = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		// Lookups are paused after repeated failures: fail open without waiting.
		if ( get_transient( self::CIRCUIT_TRANSIENT ) ) {
			return false;
		}

		$timeout = absint( \ZeroSpam\Core\Settings_Schema::value( 'stop_forum_spam_timeout' ) );

		$endpoint = self::ENDPOINT . '?' . http_build_query( array_merge( $params, array( 'json' => '' ) ) );
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'             => $timeout > 0 ? $timeout : 5,
				'limit_response_size' => self::MAX_RESPONSE_BYTES,
			)
		);
		$code     = (int) wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 429 === $code || $code >= 500 ) {
			if ( is_wp_error( $response ) ) {
				\ZeroSpam\Core\Utilities::log( $response->get_error_message() );
			}
			self::record_failure();

			return false;
		}

		// The service answered: whatever it said, it is not an outage.
		delete_transient( self::FAILURES_TRANSIENT );

		if ( 200 !== $code ) {
			return false;
		}

		$answer = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $answer ) || empty( $answer['success'] ) ) {
			return false;
		}

		foreach ( $answer as $field => $entry ) {
			if ( is_array( $entry ) ) {
				unset( $answer[ $field ]['value'] );
			}
		}

		$encoded = (string) wp_json_encode( $answer );

		set_transient( $cache_key, $encoded, self::lists_anything( $answer ) ? self::listed_ttl() : self::CLEAN_TTL );

		return $encoded;
	}

	/**
	 * Counts a failed lookup and pauses lookups after CIRCUIT_THRESHOLD in a row.
	 */
	private static function record_failure(): void {
		$failures = (int) get_transient( self::FAILURES_TRANSIENT ) + 1;
		set_transient( self::FAILURES_TRANSIENT, $failures, HOUR_IN_SECONDS );

		if ( $failures >= self::CIRCUIT_THRESHOLD ) {
			set_transient( self::CIRCUIT_TRANSIENT, 1, self::CIRCUIT_COOL_OFF );
			delete_transient( self::FAILURES_TRANSIENT );
			\ZeroSpam\Core\Utilities::log( 'Stop Forum Spam: lookups failed repeatedly. Pausing them for 10 minutes.' );
		}
	}

	/**
	 * Whether an answer says any looked-up value appears in the database.
	 *
	 * @param array $answer Decoded answer.
	 */
	private static function lists_anything( array $answer ): bool {
		foreach ( $answer as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['appears'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * How long an answer that lists a value is kept: the Cache Expiration days.
	 */
	private static function listed_ttl(): int {
		$days = absint( \ZeroSpam\Core\Settings_Schema::value( 'stop_forum_spam_cache' ) );

		return ( $days > 0 ? $days : 14 ) * DAY_IN_SECONDS;
	}

	/**
	 * Stop Forum Spam access_check
	 *
	 * @param array  $access_checks Access checks.
	 * @param string $user_ip       User IP.
	 * @return array
	 */
	public function access_check( $access_checks, $user_ip ) {
		$access_checks['stop_forum_spam'] = array(
			'blocked' => false,
		);

		if ( 'enabled' !== \ZeroSpam\Core\Settings_Schema::value( 'stop_forum_spam' ) ) {
			return $access_checks;
		}

		$answer = $this->lookup( array( 'ip' => $user_ip ) );
		if ( $this->is_listed( $answer, 'ip' ) ) {
			$access_checks['stop_forum_spam']['blocked'] = true;
			$access_checks['stop_forum_spam']['type']    = 'blocked';
			$access_checks['stop_forum_spam']['details'] = $answer['ip'];
		}

		return $access_checks;
	}
}
