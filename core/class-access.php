<?php
/**
 * Access class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Admin.
 *
 * Handles access checks.
 *
 * @since 5.0.0
 */
class Access {

	/**
	 * Access constructor.
	 *
	 * @since 5.0.0
	 * @access private
	 */
	public function __construct() {
		if ( ZeroSpam\Core\Access::process() ) {
			add_action( 'template_redirect', array( $this, 'access_check' ), 0 );
			add_filter( 'zerospam_access_checks', array( $this, 'check_blocked' ), 0, 3 );
		}
	}

	/**
	 * Returns true if WordPress Zero Spam should process a submission.
	 */
	public static function process() {
		if ( is_admin() || is_user_logged_in() ) {
			return false;
		}

		return true;
	}

	/**
	 * Access check.
	 *
	 * Determines if the current user should be blocked.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function access_check() {
		$access = self::get_access();

		if ( ! empty( $access['blocked'] ) ) {
			$settings = ZeroSpam\Core\Settings::get_settings();

			if ( ! empty( $access['details'] ) && is_array( $access['details'] ) ) {
				if ( ! empty( $settings['share_data']['value'] ) && 'enabled' === $settings['share_data']['value'] ) {
					do_action( 'zerospam_share_blocked', $access['details'] );
				}

				foreach ( $access['details'] as $key => $detail ) {
					if ( ! empty( $detail['blocked'] ) ) {
						if ( empty( $detail['details']['failed'] ) ) {
							$detail['details']['failed'] = $key;
						}

						if ( ! empty( $settings['log_blocked_ips']['value'] ) && 'enabled' === $settings['log_blocked_ips']['value'] ) {
							ZeroSpam\Includes\DB::log( $detail['type'], $detail['details'] );
						}
					}
				}
			}

			if ( ! empty( $settings['block_handler']['value'] ) ) {
				switch ( $settings['block_handler']['value'] ) {
					case 403:
						$message = __( 'Your IP address has been blocked by WordPress Zero Spam due to detected spam/malicious activity.', 'zerospam' );
						if ( ! empty( $settings['blocked_message']['value'] ) ) {
							$message = $settings['blocked_message']['value'];
						}
						wp_die(
							$message,
							__( 'Blocked by WordPress Zero Spam', 'zerospam' ),
							array(
								'response' => 403,
							)
						);
						break;
					case 'redirect':
						$url = 'https://wordpress.org/plugins/zero-spam/';
						if ( ! empty( $settings['blocked_redirect_url']['value'] ) ) {
							$url = esc_url( $settings['blocked_redirect_url']['value'] );
						}
						wp_redirect( $url );
						exit;
						break;
				}
			}
		}
	}

	/**
	 * Helper to get blocked record details.
	 */
	public function get_blocked_details( $blocked_record, $failed = false ) {
		$access_check = array(
			'blocked' => false,
		);

		if ( ! $blocked_record ) {
			return $access_check;
		}

		$today = new \DateTime();

		// Check the start & end dates (all blocks require a start date).
		$start_date = new \DateTime();
		if ( ! empty( $blocked_record['start_block'] ) ) {
			$start_date = new \DateTime( $blocked_record['start_block'] );
		}

		if ( $today >= $start_date ) {
			// Check the end date if temporary block.
			if (
				! empty( $blocked_record['blocked_type'] ) &&
				'temporary' === $blocked_record['blocked_type']
			) {
				// Temporary block.
				if ( ! empty( $blocked_record['end_block'] ) ) {
					$end_date = new \DateTime( $blocked_record['end_block'] );

					if ( $today < $end_date ) {
						$access_check['blocked']            = true;
						$access_check['type']              = 'blocked';
						$access_check['details']           = $blocked_record;
						$access_check['details']['failed'] = $failed;
					}
				}
			} else {
				// Permanent block.
				$access_check['blocked']           = true;
				$access_check['type']              = 'blocked';
				$access_check['details']           = $blocked_record;
				$access_check['details']['failed'] = $failed;
			}
		}

		return $access_check;
	}

	/**
	 * Checks if an IP has been blocked.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function check_blocked( $access_checks, $user_ip, $settings ) {
		$access_checks['blocked'] = array(
			'blocked' => false,
		);

		// Attempt to get the IP address location & checked if block.
		$location = apply_filters( 'zerospam_get_location', $user_ip );
		if ( $location ) {
			$location_keys = array( 'country_code', 'region_code', 'city', 'zip' );
			foreach ( $location_keys as $key => $loc ) {
				if ( ! empty( $location[ $loc ] ) ) {
					$blocked = ZeroSpam\Includes\DB::blocked( $location[ $loc ], $loc );
					if ( $blocked ) {
						$access_checks['blocked'] = self::get_blocked_details( $blocked, 'blocked_' . $loc );
						break;
					}
				}
			}
		}

		// If passed location blocks, check the IP address.
		if ( ! $access_checks['blocked'] ) {
			// Check the user's IP access.
			$blocked = ZeroSpam\Includes\DB::blocked( $user_ip );
			if ( $blocked ) {
				$access_checks['blocked'] = self::get_blocked_details( $blocked, 'blocked_ip' );
			}
		}

		return $access_checks;
	}

	/**
	 * Gets the current user's access.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_access() {
		$settings = ZeroSpam\Core\Settings::get_settings();

		$access = array(
			'blocked' => false,
		);

		$user_ip = ZeroSpam\Core\User::get_ip();

		if ( $user_ip ) {
			if ( ZeroSpam\Core\Utilities::is_whitelisted( $user_ip ) ) {
				return $access;
			}

			$access_checks = apply_filters( 'zerospam_access_checks', array(), $user_ip, $settings );
			foreach ( $access_checks as $key => $check ) {
				if ( ! empty( $check['blocked'] ) ) {
					$access['blocked'] = true;
					break;
				}
			}

			$access['details'] = $access_checks;
		}

		return $access;
	}
}
