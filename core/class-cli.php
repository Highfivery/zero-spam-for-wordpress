<?php
/**
 * WP-CLI Commands
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WP-CLI Commands
 */
class CLI {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'cli_init', array( $this, 'cli_commands' ) );
	}

	/**
	 * CLI commands
	 */
	public function cli_commands() {
		WP_CLI::add_command( 'zerospam', 'WDS_CLI' );
	}
}
