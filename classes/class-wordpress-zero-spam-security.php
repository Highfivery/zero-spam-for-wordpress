<?php
/**
 * WordPress Zero Spam security class.
 *
 * @package WordPressZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WordPress Zero Spam security class.
 */
class WordPress_Zero_Spam_Security {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Removes the meta generator tag.
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
	}

	/**
	 * Removes the meta generator tag.
	 */
	public function after_setup_theme() {
		// Remove the meta generator tag.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter(
			'the_generator',
			function() {
				return '';
			}
		);
	}
}
