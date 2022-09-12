<?php
/**
 * Site security
 *
 * Implement Zero Spam's recommended WordPress security practices.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Security class
 */
class Security {
	/**
	 * Constructor
	 */
	public function __construct() {
		// It can be considered a security risk to make your WP version visible &
		// public you should hide it.
		remove_action( 'wp_head', 'wp_generator' );

		// XML-RPC can significantly amplify the brute-force attacks.
		add_filter( 'xmlrpc_enabled', '__return_false' );

		// Fired on detections.
		add_action( 'zero_spam_detection', array( $this, 'handle_detection' ), 10, 2 );
	}

	/**
	 * Handles detections.
	 *
	 * @param array $validation_errors Array of validation errors.
	 */
	public function handle_detection( $details, $validation_errors ) {
	}
}
