<?php
class ZeroSpam_Access extends ZeroSpam_Plugin {
  public function run() {
    add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

    if ( ! empty ( $this->settings['wp_generator'] ) && $this->settings['wp_generator'] ) {
      remove_action( 'wp_head', 'wp_generator' );
    }
  }

  /**
   * Uses plugins_loaded.
   *
   * This hook is called once any activated plugins have been loaded. Is
   * generally used for immediate filter setup, or plugin overrides.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded
   */
  public function plugins_loaded() {
    // Check if user IP has been blocked.
    $this->ip_check();
  }

  /**
   * Checks if the current IP is blocked.
   *
   * @since 2.0.0
   */
  public function ip_check() {
    if ( ! is_user_logged_in() && zerospam_is_blocked( zerospam_get_ip() ) ) {
      do_action( 'zero_spam_ip_blocked' );
      die( __( $this->settings['blocked_ip_msg'], 'zerospam' ) );
    }
  }
}