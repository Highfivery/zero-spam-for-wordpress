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
		$trusted_proxies = apply_filters( 'zerospam_trusted_proxies', array() );

		$ip_sources = [
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
		];

		foreach ( $ip_sources as $source ) {
			if ( ! empty( $_SERVER[ $source ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $source ] ) );

				// Handle multiple IP addresses in headers by taking the first valid IP.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_list = explode( ',', $ip );
					foreach ( $ip_list as $potential_ip ) {
						$potential_ip = trim( $potential_ip );
						if ( rest_is_ip_address( $potential_ip ) ) {
							// Validate IP only if it's from a trusted proxy or it's directly from REMOTE_ADDR.
							if ( in_array( $_SERVER['REMOTE_ADDR'], $trusted_proxies ) || $source === 'REMOTE_ADDR' ) {
								return apply_filters( 'zerospam_get_ip', $potential_ip );
							}
						}
					}
				} else {
					// Validate single IP address.
					if ( rest_is_ip_address( $ip ) ) {
						// Directly return the IP if it's from REMOTE_ADDR or a trusted proxy.
						if ( in_array( $_SERVER['REMOTE_ADDR'], $trusted_proxies ) || $source === 'REMOTE_ADDR' ) {
							return apply_filters( 'zerospam_get_ip', $ip );
						}
					}
				}
			}
		}

		// Return false if no valid IP address is found.
		return false;
	}
}
