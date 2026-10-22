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
	 * Every header a proxy, load balancer or CDN may use to pass on the
	 * visitor's address ($_SERVER keys).
	 *
	 * A request that carries one of them came through a proxy. Admin_Safe_IPs
	 * uses the list to recognise a proxy that is not trusted.
	 *
	 * @var string[]
	 */
	const PROXY_HEADERS = array(
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_FASTLY_CLIENT_IP',
		'HTTP_X_SUCURI_CLIENTIP',
		'HTTP_INCAP_CLIENT_IP',
		'HTTP_X_AZURE_CLIENTIP',
	);

	/**
	 * Gets the current user's IP.
	 *
	 * REMOTE_ADDR first; forwarded headers are read only when REMOTE_ADDR is a
	 * trusted proxy (`zerospam_trusted_proxies` filter). From a comma-separated
	 * list the first valid address is used.
	 *
	 * @return string|false IP address, or false when none is found.
	 */
	public static function get_ip() {
		$trusted_proxies = (array) apply_filters( 'zerospam_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$from_proxy      = in_array( $remote_addr, $trusted_proxies, true );

		$ip_sources = array(
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
		);

		foreach ( $ip_sources as $source ) {
			if ( empty( $_SERVER[ $source ] ) ) {
				continue;
			}

			// Headers are only trusted when the request comes from a trusted proxy.
			if ( 'REMOTE_ADDR' !== $source && ! $from_proxy ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $source ] ) );

			// Handle multiple IP addresses in headers by taking the first valid IP.
			foreach ( explode( ',', $value ) as $candidate ) {
				$candidate = trim( $candidate );
				if ( rest_is_ip_address( $candidate ) ) {
					return apply_filters( 'zerospam_get_ip', $candidate );
				}
			}
		}

		// Return false if no valid IP address is found.
		return false;
	}
}
