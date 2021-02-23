<?php
/**
 * Zero Spam class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Zero Spam.
 *
 * @since 5.0.0
 */
class Zero_Spam {
	/**
	 * Zero Spam constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'zerospam_share_blocked', array( $this, 'share_blocked' ), 10, 1 );
	}

	/**
	 * Share blocked.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function share_blocked( $details ) {

	}
}
