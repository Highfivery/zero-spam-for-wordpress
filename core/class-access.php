<?php
/**
 * Access class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Access
 */
class Access {

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
		if ( ! is_admin() && is_main_query() && self::process() ) {
			add_action( 'template_redirect', array( $this, 'access_check' ), 0 );
			add_filter( 'zerospam_access_checks', array( $this, 'check_blocked' ), 0, 3 );
		}
	}

	/**
	 * Returns true if WordPress Zero Spam should process a submission.
	 *
	 * @param boolean $ignore_ajax True if AJAX shouldn't be checked.
	 */
	public static function process( $ignore_ajax = false ) {
		if ( $ignore_ajax && is_admin() || is_user_logged_in() ) {
			return false;
		} elseif ( ! $ignore_ajax && ( is_admin() && ! wp_doing_ajax() ) || is_user_logged_in() ) {
			return false;
		}

		return true;
	}

	/**
	 * Access check
	 */
	public function access_check() {
		$access = self::get_access();

		if ( ! empty( $access['blocked'] ) ) {
			$settings = ZeroSpam\Core\Settings::get_settings();

			if ( ! empty( $access['details'] ) && is_array( $access['details'] ) ) {
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
						header( 'Cache-Control: no-cache, no-store, must-revalidate' );
						header( 'Pragma: no-cache' );
						header( 'Expires: 0' );

						$message = __( 'Your IP address has been blocked due to detected spam/malicious activity.', 'zero-spam' );
						if ( ! empty( $settings['blocked_message']['value'] ) ) {
							$message = $settings['blocked_message']['value'];
						}
						wp_die(
							$message,
							__( 'Blocked', 'zero-spam' ),
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
	public static function get_blocked_details( $blocked_record, $failed = false ) {
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

		$blocked = false;
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
						$blocked = true;
					}
				}
			} else {
				// Permanent block.
				$blocked = true;
			}
		}

		if ( $blocked ) {
			$access_check['blocked']            = true;
			$access_check['type']              = 'blocked';
			$access_check['details']           = $blocked_record;
			$access_check['details']['failed'] = $failed;
		}

		return $access_check;
	}

	/**
	 * Checks if an IP has been blocked
	 *
	 * @param array  $access_checks Array of exisiting access checks.
	 * @param string $user_ip The user's IP address.
	 * @param array  $settings The plugin settings.
	 */
	public function check_blocked( $access_checks, $user_ip, $settings ) {
		$access_checks['blocked'] = array(
			'blocked' => false,
		);

		// Check if geolocation information is available, if so, check if blocked.
		$geolocation_information = \ZeroSpam\Core\Utilities::geolocation( $user_ip );
		if ( $geolocation_information ) {
			// Geolocation information available, check the blocked locations.
			// Available blocked location keys.
			$location_keys = array(
				'country_code',
				'region_code',
				'city',
				'zip',
			);

			foreach ( $location_keys as $key => $loc ) {
				if ( ! empty( $geolocation_information[ $loc ] ) ) {
					$blocked = \ZeroSpam\Includes\DB::blocked( $geolocation_information[ $loc ], $loc );
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
			$blocked = \ZeroSpam\Includes\DB::blocked( $user_ip );
			if ( $blocked ) {
				$access_checks['blocked'] = self::get_blocked_details( $blocked, 'blocked_ip' );
			}
		}

		return $access_checks;
	}

	/**
	 * Gets the current user's access
	 */
	public function get_access() {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$user_ip  = ZeroSpam\Core\User::get_ip();

		$access = array(
			'blocked' => false,
		);

		if ( $user_ip ) {
			$access['ip'] = $user_ip;

			if ( ZeroSpam\Core\Utilities::is_whitelisted( $user_ip ) ) {
				return $access;
			}

			$access_checks = apply_filters(
				'zerospam_access_checks',
				array(),
				$user_ip,
				$settings
			);

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
