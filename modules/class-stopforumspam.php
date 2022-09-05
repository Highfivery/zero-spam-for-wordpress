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
			'icon'  => 'assets/img/icon-stop-forum-spam.png'
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
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: Replaced with the Stop Forum Spam URL */
					__( 'Blocks visitor IPs that have been reported to <a href="%s" target="_blank" rel="noopener noreferrer">Stop Forum Spam</a>.', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.stopforumspam.com/#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
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
			'desc'        => __( 'Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond; recommended 5 seconds.', 'zero-spam' ),
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
			'placeholder' => WEEK_IN_SECONDS,
			'min'         => 0,
			'desc'        => __( 'Setting to high could result in outdated information, too low could cause a decrease in performance; recommended 14 days.', 'zero-spam' ),
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
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: Replaced with the Stop Forum Spam URL */
					__( 'Minimum <a href="%s" target="_blank" rel="noopener noreferrer">confidence score</a> an IP must meet before being blocked. Setting this too low could cause users to be blocked that shouldn\'t be; recommended 50%%', 'zero-spam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.stopforumspam.com/usage?utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'value'       => ! empty( $options['stop_forum_spam_confidence_min'] ) ? $options['stop_forum_spam_confidence_min'] : 50,
			'recommended' => 50,
		);

		return $settings;
	}

	/**
	 * Processes comments
	 *
	 * @param array  $errors                Array of errors.
	 * @param array  $post                  Post array.
	 * @param string $detection_message_key Detection message key.
	 */
	public function preprocess_comments( $errors, $post, $detection_message_key ) {
		if ( empty( $post['comment_author_email'] ) ) {
			return $errors;
		}

		$response = self::query(
			array(
				'email' => $post['comment_author_email'],
			)
		);
		if ( $response ) {
			$response = json_decode( $response, true );
			if ( ! empty( $response['success'] ) && $response['success'] ) {
				$settings = \ZeroSpam\Core\Settings::get_settings();

				// Check email.
				if (
					! empty( $response['email'] ) &&
					! empty( $response['email']['confidence'] ) &&
					! empty( $settings['stop_forum_spam_confidence_min']['value'] ) &&
					floatval( $response['email']['confidence'] ) >= floatval( $settings['stop_forum_spam_confidence_min']['value'] )
				) {
					$errors[] = 'stop_forum_spam_email';
				}
			}
		}

		return $errors;
	}

	/**
	 * Processes registrations
	 *
	 * @param WP_Error $errors               A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email           User's email.
	 */
	public function preprocess_registrations( $errors, $sanitized_user_login, $user_email ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['stop_forum_spam']['value'] ) || 'enabled' !== $settings['stop_forum_spam']['value'] ) {
			return $errors;
		}

		$response = self::query(
			array(
				'username' => $sanitized_user_login,
				'email'    => $user_email,
			)
		);
		if ( $response ) {
			$response = json_decode( $response, true );
			if ( ! empty( $response['success'] ) && $response['success'] ) {
				$message = \ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' );

				// Check username.
				if (
					! empty( $response['username'] ) &&
					! empty( $response['username']['confidence'] ) &&
					! empty( $settings['stop_forum_spam_confidence_min']['value'] ) &&
					floatval( $response['username']['confidence'] ) >= floatval( $settings['stop_forum_spam_confidence_min']['value'] )
				) {
					$errors->add( 'zerospam_error_stopformspam_username', $message );

					$details = array(
						'user_login' => $sanitized_user_login,
						'user_email' => $user_email,
						'failed'     => 'stop_forum_spam_username',
					);
					if ( ! empty( $settings['log_blocked_registrations']['value'] ) && 'enabled' === $settings['log_blocked_registrations']['value'] ) {
						\ZeroSpam\Includes\DB::log( 'registration', $details );
					}

					// Share the detection if enabled.
					if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
						$details['type'] = 'registration';
						do_action( 'zerospam_share_detection', $details );
					}
				}

				// Check email.
				if (
					! empty( $response['email'] ) &&
					! empty( $response['email']['confidence'] ) &&
					! empty( $settings['stop_forum_spam_confidence_min']['value'] ) &&
					floatval( $response['email']['confidence'] ) >= floatval( $settings['stop_forum_spam_confidence_min']['value'] )
				) {
					if ( count( $errors->errors ) == 0 ) {
						$errors->add( 'zerospam_error_stopformspam_email', $message );
					}

					if ( ! empty( $settings['log_blocked_registrations']['value'] ) && 'enabled' === $settings['log_blocked_registrations']['value'] ) {
						$details = array(
							'user_login' => $sanitized_user_login,
							'user_email' => $user_email,
							'failed'     => 'stop_forum_spam_email',
						);
						\ZeroSpam\Includes\DB::log( 'registration', $details );
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Query the Stop Forum Spam API
	 *
	 * @param array $params Array of query parameters.
	 */
	public function query( $params ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		$cache_array = array( 'stop_forum_spam' );
		$cache_array = array_merge( $cache_array, $params );
		$cache_key   = \ZeroSpam\Core\Utilities::cache_key( $cache_array );

		$response = wp_cache_get( $cache_key );
		if ( false === $response ) {
			$endpoint = 'https://api.stopforumspam.org/api?';
			$params   = array( 'json' => '' );
			$params   = array_merge( $cache_array, $params );
			$endpoint = $endpoint . http_build_query( $params );

			$timeout = 5;
			if ( ! empty( $settings['stop_forum_spam_timeout'] ) ) {
				$timeout = intval( $settings['stop_forum_spam_timeout']['value'] );
			}

			$response = \ZeroSpam\Core\Utilities::remote_get( $endpoint, array( 'timeout' => $timeout ) );
			if ( $response ) {
				$expiration = 14 * DAY_IN_SECONDS;
				if ( ! empty( $settings['stop_forum_spam_cache']['value'] ) ) {
					$expiration = $settings['stop_forum_spam_cache']['value'] * DAY_IN_SECONDS;
				}
				wp_cache_set( $cache_key, $response, 'zerospam', $expiration );
			}
		}

		return $response;
	}

	/**
	 * Stop Forum Spam access_check
	 *
	 * @param array  $access_checks Access checks.
	 * @param string $user_ip       User IP.
	 */
	public function access_check( $access_checks, $user_ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		$access_checks['stop_forum_spam'] = array(
			'blocked' => false,
		);

		if ( empty( $settings['stop_forum_spam']['value'] ) || 'enabled' !== $settings['stop_forum_spam']['value'] ) {
			return $access_checks;
		}

		$response = self::query( array( 'ip' => $user_ip ) );
		if ( $response ) {
			$response = json_decode( $response, true );
			if (
				! empty( $response['success'] ) &&
				$response['success'] &&
				! empty( $response['ip'] ) &&
				! empty( $response['ip']['appears'] )
			) {

				if (
					! empty( $response['ip']['confidence'] ) &&
					! empty( $settings['stop_forum_spam_confidence_min']['value'] ) &&
					floatval( $response['ip']['confidence'] ) >= floatval( $settings['stop_forum_spam_confidence_min']['value'] )
				) {
					$access_checks['stop_forum_spam']['blocked'] = true;
					$access_checks['stop_forum_spam']['type']    = 'blocked';
					$access_checks['stop_forum_spam']['details'] = $response['ip'];
				}
			}
		}

		return $access_checks;
	}
}
