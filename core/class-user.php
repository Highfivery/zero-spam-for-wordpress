<?php
/**
 * User class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * User class.
 *
 * Handles getting user specific information.
 */
class User {

	/**
	 * Gets the current user's IP.
	 */
	public static function get_ip() {
		$settings = Settings::get_settings();

		// Check if a debugging IP is enabled.
		if (
			! empty( $settings['debug']['value'] ) &&
			'enabled' === $settings['debug']['value'] &&
			! empty( $settings['debug_ip']['value'] )
		) {
			return $settings['debug_ip']['value'];
		}

		// Check against Cloudflare IPs.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} else {

			// Handle all other IPs.
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED'] ) ) {
				$ip = $_SERVER['HTTP_X_FORWARDED'];
			} elseif ( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
				$ip = $_SERVER['HTTP_FORWARDED_FOR'];
			} elseif ( ! empty( $_SERVER['HTTP_FORWARDED'] ) ) {
				$ip = $_SERVER['HTTP_FORWARDED'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
		}

		$ip = explode( ',', $ip );
		$ip = trim( $ip[0] );

		if ( ! rest_is_ip_address( $ip ) ) {
			return false;
		}

		return $ip;
	}
}
