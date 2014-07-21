<?php
/**
 * Plugin Name: WordPress Zero Spam
 * Plugin URI: http://www.benmarshall.me/wordpress-zero-spam-plugin
 * Description: Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch without all the bloated options. <strong>Just install, activate and say goodbye to spam.</strong> Based on work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.
 * Version: 1.0.0
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
defined('ABSPATH') or die("No script kiddies please!");

class Zero_Spam {
    /**
     * Plugin initilization.
     *
     * Initializes the plugins functionality.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->_actions();
    }

    /**
     * WordPress actions.
     *
     * Adds WordPress actions using the plugin API.
     *
     * @since 1.0.0
     * @access private
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference
     */
    private function _actions() {
        if ( function_exists( 'add_action' ) ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
            add_action( 'preprocess_comment', array( $this, 'preprocess_comment' ) );
        }
    }

    /**
     * WordPress actions.
     *
     * Adds WordPress actions using the plugin API.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
     */
    public function preprocess_comment( $commentdata ) {
        if( ! isset ( $_POST['zero-spam'] ) ) {
          die( __('There was a problem processing your comment.', 'zerospam') );
        }
        return $commentdata;
    }

    /**
     * Add plugin scripts.
     *
     * Adds the plugins JS files.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Function_Reference/wp_enqueue_script
     */
    public function wp_enqueue_scripts() {
        if ( function_exists( 'wp_enqueue_script' ) ) {
          wp_enqueue_script( 'zero-spam', plugins_url( '/zero-spam.min.js' , __FILE__ ), array( 'jquery' ), '1.0.0', true );
        }
    }
}

$zero_spam = new Zero_Spam;
