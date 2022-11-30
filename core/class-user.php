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
		$ip       = false;

		// Check if a debugging IP is enabled.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			// Check against Cloudflare's reported IP address.
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} else {
			// Handle all other IPs.
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED'] ) );
			}  elseif ( isset( $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_XHTTP_X_CLUSTER_CLIENT_IP_FORWARDED'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED_FOR'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_FORWARDED'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED'] ) );
			} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			}
		}

		if ( $ip ) {
			$ip = explode( ',', $ip );
			$ip = trim( $ip[0] );

			if ( ! rest_is_ip_address( $ip ) ) {
				$ip = false;
			}
		}

		return apply_filters( 'zerospam_get_ip', $ip );
	}
}
