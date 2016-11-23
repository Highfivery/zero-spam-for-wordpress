<?php
/**
 * ZeroSpam_Comments library
 *
 * Processes comments for spam.
 *
 * @package WordPress Zero Spam
 * @subpackage ZeroSpam_Comments
 * @since 1.0.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * CSS & JS scripts.
 *
 * Registers plugin CSS & JS scripts.
 *
 * @since 1.0.0
 */
class ZeroSpam_Scripts
{
  /**
   * Runs the library.
   *
   * Initializes & runs the ZeroSpam_Scripts library.
   *
   * @since 1.0.0
   *
   * @see add_action
   */
  public function run()
  {
    add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
    add_action( 'login_footer', array( $this, 'wp_enqueue_scripts' ) );
  }

  public function wp_enqueue_scripts() {
    $this->register_scripts();

    $this->enqueue_scripts();
  }

  public function register_scripts() {
    $plugin = get_plugin_data( ZEROSPAM_PLUGIN );

    wp_register_script( 'zerospam', plugins_url( '/js/zerospam.js' , ZEROSPAM_PLUGIN ), array( 'jquery' ), $plugin['Version'], true );
  }

  public function enqueue_scripts() {
    wp_localize_script( 'zerospam', 'zerospam', array( 'key' => zerospam_get_key() ) );
    wp_enqueue_script( 'zerospam' );
  }
}
