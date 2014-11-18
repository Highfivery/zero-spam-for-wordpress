<?php
/**
 * Plugin Name: WordPress Zero Spam
 * Plugin URI: http://www.benmarshall.me/wordpress-zero-spam-plugin
 * Description: Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch. <strong>Just install, activate and say goodbye to spam.</strong> Based on work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.
 * Version: 1.5.3
 * Author: Ben Marshall
 * Author URI: http://www.benmarshall.me
 * License: GPL2
 */

/*  Copyright 2014  Ben Marshall  (email : me@benmarshall.me)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Define constants.
if( ! defined( 'ZEROSPAM_ROOT ' ) ) {
	define( 'ZEROSPAM_ROOT', plugin_dir_path( __FILE__ ) );
}

if( ! defined( 'ZEROSPAM_PLUGIN ' ) ) {
	define( 'ZEROSPAM_PLUGIN', __FILE__ );
}

/**
 * Used to detect installed plugins.
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
 * Include the Zero Spam class.
 */
require_once( ZEROSPAM_ROOT . 'lib/zero-spam.class.php' );


// Initialize the Zero Spam class.
$zero_spam = Zero_Spam::get_instance();
