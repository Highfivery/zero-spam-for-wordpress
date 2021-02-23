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
		add_action( 'template_redirect', array( $this, 'access_check' ), 0 );
		add_filter( 'zerospam_access_checks', array( $this, 'check_blocked' ), 0, 3 );
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
	 * Checks if an IP has been blocked.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function check_blocked( $access_checks, $user_ip, $settings ) {
		$access_checks['blocked'] = array(
			'blocked' => false,
		);

		$blocked = ZeroSpam\Includes\DB::blocked( $user_ip );

		if ( $blocked ) {
			$today = new \DateTime();

			// Check the start & end dates (all blocks require a start date).
			$start_date = new \DateTime();
			if ( ! empty( $blocked['start_block'] ) ) {
				$start_date = new \DateTime( $blocked['start_block'] );
			}

			if ( $today >= $start_date ) {
				// Check the end date if temporary block.
				if (
					! empty( $blocked['blocked_type'] ) &&
					'temporary' === $blocked['blocked_type']
				) {
					// Temporary block.
					if ( ! empty( $blocked['end_block'] ) ) {
						$end_date = new \DateTime( $blocked['end_block'] );

						if ( $today < $end_date ) {
							$access_checks['blocked']['blocked']           = true;
							$access_checks['blocked']['type']              = 'blocked';
							$access_checks['blocked']['details']           = $blocked;
							$access_checks['blocked']['details']['failed'] = 'blocked_ips';
						}
					}
				} else {
					// Permanent block.
					$access_checks['blocked']['blocked']           = true;
					$access_checks['blocked']['type']              = 'blocked';
					$access_checks['blocked']['details']           = $blocked;
					$access_checks['blocked']['details']['failed'] = 'blocked_ips';
				}
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
