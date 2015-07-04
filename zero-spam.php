<?php
/**
 * Plugin Name: WordPress Zero Spam
 * Plugin URI: https://benmarshall.me/wordpress-zero-spam
 * Description: Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch. <strong>Just install, activate and say goodbye to spam.</strong> Based on work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.
 * Version: 2.0.1
 * Author: Ben Marshall
 * Author URI: https://benmarshall.me
 * License: GPL2
 */

/*  Copyright 2015  Ben Marshall  (email : me@benmarshall.me)

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
 * Include the plugin helpers.
 */
require_once ZEROSPAM_ROOT . 'src' . DIRECTORY_SEPARATOR . 'helpers.php';

/**
 * Used to detect installed plugins.
 */
require_once ABSPATH . 'wp-admin/includes/plugin.php';

spl_autoload_register( 'zerospam_autoloader' );
function zerospam_autoloader( $class_name ) {
  if ( false !== strpos( $class_name, 'ZeroSpam' ) ) {
    $classes_dir = ZEROSPAM_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    $class_file = str_replace( '_', DIRECTORY_SEPARATOR, $class_name ) . '.php';
    require_once $classes_dir . $class_file;
  }
}

// Load the plugin features.
$plugin            = new ZeroSpam_Plugin();
$plugin['install'] = new ZeroSpam_Install();
$plugin['access']  = new ZeroSpam_Access();
$plugin['scripts'] = new ZeroSpam_Scripts();
$plugin['admin']   = new ZeroSpam_Admin();
$plugin['ajax']    = new ZeroSpam_Ajax();

// Registration support.
if ( ! empty( $plugin->settings['registration_support'] ) && $plugin->settings['registration_support'] ) {
  $plugin['registration'] = new ZeroSpam_Registration();
}

// Comments support.
if ( ! empty( $plugin->settings['comment_support'] ) && $plugin->settings['comment_support'] ) {
  $plugin['comments'] = new ZeroSpam_Comments();
}

// Contact Form 7 support.
if (
  zerospam_plugin_check( 'cf7' ) &&
  ! empty( $plugin->settings['cf7_support'] ) && $plugin->settings['cf7_support']
) {
  $plugin['cf7'] = new ZeroSpam_ContactForm7();
}

// BuddyPress support.
if (
  zerospam_plugin_check( 'bp' ) &&
  ! empty( $plugin->settings['bp_support'] ) && $plugin->settings['bp_support']
) {
  $plugin['bp'] = new ZeroSpam_BuddyPress();
}

// Ninja Forms support.
if (
  zerospam_plugin_check( 'nf' ) &&
  ! empty( $plugin->settings['nf_support'] ) && $plugin->settings['nf_support']
) {
  $plugin['nf'] = new ZeroSpam_NinjaForms();
}

// Gravity Forms support.
if (
  zerospam_plugin_check( 'gf' ) &&
  ! empty( $plugin->settings['gf_support'] ) && $plugin->settings['gf_support']
) {
  $plugin['gf'] = new ZeroSpam_GravityForms();
}

// Initialize the plugin.
$plugin->run();
