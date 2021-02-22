<?php
/**
 * Utilities class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Utilities.
 *
 * @since 5.0.0
 */
class Utilities {

	/**
	 * Outputs a honeypot field
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @return string Returns a HTML honeypot field.
	 */
	public static function honeypot_field() {
		return '<input type="text" name="' . self::get_honeypot() . '" value="" style="display: none !important;" />';
	}

	/**
	 * Returns the generated key for checking submissions.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @return string A unique key used for the 'honeypot' field.
	 */
	public static function get_honeypot() {
		$key = get_option( 'wpzerospam_honeypot' );
		if ( ! $key ) {
			$key = wp_generate_password( 5, false, false );
			update_option( 'wpzerospam_honeypot', $key );
		}

		return $key;
	}

	/**
	 * Returns a cache key.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function cache_key( $args, $table = false ) {
		return sanitize_title( $table . '_' . implode( '_', $args ) );
	}

	/**
	 * Remote get.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function remote_get( $endpoint, $args = array() ) {
		$response = wp_remote_get( $endpoint, $args );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			return wp_remote_retrieve_body( $response );
		}

		return false;
	}

	/**
	 * Returns the current URL.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function current_url() {
		return ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	}
}
