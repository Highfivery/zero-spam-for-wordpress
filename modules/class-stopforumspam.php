<?php
/**
 * Stop Forum Spam class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Stop Forum Spam.
 *
 * @since 5.0.0
 */
class StopForumSpam {
	/**
	 * Stop Forum Spam constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );

		if ( ZeroSpam\Core\Access::process() ) {
			add_filter( 'zerospam_access_checks', array( $this, 'access_check' ), 10, 3 );
			add_filter( 'zerospam_registration_errors', array( $this, 'preprocess_registrations' ), 10, 3 );
			add_filter( 'zerospam_preprocess_comment', array( $this, 'preprocess_comments' ), 10, 1 );
		}
	}

	/**
	 * Stop Forum Spam sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['stop_forum_spam'] = array(
			'title' => __( 'Stop Forum Spam Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Stop Forum Spam settings.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['stop_forum_spam'] = array(
			'title'       => __( 'Stop Forum Spam', 'zerospam' ),
			'section'     => 'stop_forum_spam',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'desc'        => sprintf(
				wp_kses(
					__( 'Checks user IPs against <a href="%s" target="_blank" rel="noopener noreferrer">Stop Forum Spam</a>\'s blacklist.', 'zerospam' ),
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
			'title'       => __( 'Stop Forum Spam API Timeout', 'zerospam' ),
			'section'     => 'stop_forum_spam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zerospam' ),
			'placeholder' => __( '5', 'zerospam' ),
			'min'         => 0,
			'desc'        => __( 'Recommended setting is 5 seconds. Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond.', 'zerospam' ),
			'value'       => ! empty( $options['stop_forum_spam_timeout'] ) ? $options['stop_forum_spam_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['stop_forum_spam_cache'] = array(
			'title'       => __( 'Stop Forum Spam Cache Expiration', 'zerospam' ),
			'section'     => 'stop_forum_spam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zerospam' ),
			'placeholder' => __( WEEK_IN_SECONDS, 'zerospam' ),
			'min'         => 0,
			'desc'        => __( 'Recommended setting is 14 days. Setting to high could result in outdated information, too low could cause a decrease in performance.', 'zerospam' ),
			'value'       => ! empty( $options['stop_forum_spam_cache'] ) ? $options['stop_forum_spam_cache'] : 14,
			'recommended' => 14,
		);

		$settings['stop_forum_spam_confidence_min'] = array(
			'title'       => __( 'Stop Forum Spam Confidence Minimum', 'zerospam' ),
			'section'     => 'stop_forum_spam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( '%', 'zerospam' ),
			'placeholder' => __( '50', 'zerospam' ),
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'desc'      => sprintf(
				wp_kses(
					__( 'Recommended setting is 50%%. Minimum <a href="%s" target="_blank" rel="noopener noreferrer">confidence score</a> an IP must meet before being blocked. Setting this too low could cause users to be blocked that shouldn\'t be.', 'zerospam' ),
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
	 * Processes comments.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function preprocess_comments( $commentdata ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['stop_forum_spam']['value'] ) || 'enabled' !== $settings['stop_forum_spam']['value'] ) {
			return $commentdata;
		}

		$response = self::query(
			array(
				'email' => $commentdata['comment_author_email'],
			)
		);
		if ( $response ) {
			$response = json_decode( $response, true );
			if ( ! empty( $response['success'] ) && $response['success'] ) {

				// Check email.
				if (
					! empty( $response['email'] ) &&
					! empty( $response['email']['confidence'] ) &&
					! empty( $settings['stop_forum_spam_confidence_min']['value'] ) &&
					floatval( $response['email']['confidence'] ) >= floatval( $settings['stop_forum_spam_confidence_min']['value'] )
				) {

					if ( ! empty( $settings['log_blocked_comments']['value'] ) && 'enabled' === $settings['log_blocked_comments']['value'] ) {
						$details = array(
							'failed' => 'stop_forum_spam_email',
						);
						$details = array_merge( $details, $commentdata );
						ZeroSpam\Includes\DB::log( 'comment', $details );
					}

					$message = ZeroSpam\Core\Utilities::detection_message( 'comment_spam_message' );
					wp_die(
						wp_kses(
							$message,
							array(
								'a'      => array(
									'target' => array(),
									'href'   => array(),
									'rel'    => array(),
								),
								'strong' => array(),
							)
						),
						esc_html( ZeroSpam\Core\Utilities::detection_title( 'comment_spam_message' ) ),
						array(
							'response' => 403,
						)
					);
				}
			}
		}

		return $commentdata;
	}

	/**
	 * Processes registrations.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function preprocess_registrations( $errors, $sanitized_user_login, $user_email ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

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
				$message = ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' );

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
						ZeroSpam\Includes\DB::log( 'registration', $details );
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
						$errors->add( 'zerospam_error_stopformspam_email', __( $message, 'zerospam' ) );
					}

					if ( ! empty( $settings['log_blocked_registrations']['value'] ) && 'enabled' === $settings['log_blocked_registrations']['value'] ) {
						$details = array(
							'user_login' => $sanitized_user_login,
							'user_email' => $user_email,
							'failed'     => 'stop_forum_spam_email',
						);
						ZeroSpam\Includes\DB::log( 'registration', $details );
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Query the Stop Forum Spam API.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function query( $params ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		$cache_array = array( 'stop_forum_spam' );
		$cache_array = array_merge( $cache_array, $params );
		$cache_key = ZeroSpam\Core\Utilities::cache_key( $cache_array );

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

			$response = ZeroSpam\Core\Utilities::remote_get( $endpoint, array( 'timeout' => $timeout ) );
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
	 * Stop Forum Spam access_check.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function access_check( $access_checks, $user_ip, $settings ) {
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
