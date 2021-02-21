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

			if ( ! empty( $settings['log_blocked_ips']['value'] ) && 'enabled' === $settings['log_blocked_ips']['value'] ) {
				if ( ! empty( $access['details'] ) && is_array( $access['details'] ) ) {

					if ( ! empty( $settings['share_data']['value'] ) && 'enabled' === $settings['share_data']['value'] ) {
						do_action( 'zerospam_share_blocked', $access['details'] );
					}

					foreach ( $access['details'] as $key => $detail ) {
						if ( ! empty( $detail['blocked'] ) ) {
							ZeroSpam\Includes\DB::log( $key, $detail['details'] );
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
	 * Gets the current user's access.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_access() {
		$access = array(
			'blocked' => false,
		);

		$user_ip = ZeroSpam\Core\User::get_ip();

		if ( $user_ip ) {
			$settings = ZeroSpam\Core\Settings::get_settings();

			$access_checks = apply_filters( 'zerospam_access_checks', array(), $user_ip, $settings );
			foreach ( $access_checks as $key => $check ) {
				if ( ! empty( $check['blocked'] ) ) {
					$access['blocked'] = true;
					break;
				}
			}

			$access['details'] = $access_checks;

			/*$this->get_blocked(
				array(
					'key_type' => 'ip',
					'user_ip'  => $user_ip,
				)
			);
			print_r($settings);*/
		}

		return $access;
	}

	/**
	 * Get blocked records.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_blocked( $args = array() ) {

		$cache_key = ZeroSpam\Core\Utilities::cache_key( $args );
		$result    = wp_cache_get( $cache_key );

		if ( false === $result ) {
			$params = array();
			if ( ! empty( $args['key_type'] ) ) {
				$params['where']['key_type'] = array(
					'value' => $args['key_type'],
				);
			}

			if ( ! empty( $args['user_ip'] ) ) {
				$params['where']['user_ip'] = array(
					'value' => $args['user_ip'],
				);
			}

			$result = ZeroSpam\Includes\DB::query( 'blocked', $params );
			wp_cache_set( $cache_key, $result );
		}

		return $result;
	}
}
