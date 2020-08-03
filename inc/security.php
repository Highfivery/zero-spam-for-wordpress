<?php
/**
 * Action & filter hooks to boost site security
 *
 * @package WordPressZeroSpam
 * @since 4.9.7
 */

/**
 * WordPress filter hooks
 */
if ( ! function_exists( 'wpzerospam_filter_hooks' ) ) {
  function wpzerospam_filter_hooks() {
    $options = wpzerospam_options();

    add_filter( 'the_generator', 'wpzerospam_remove_generator' );
  }
}

/**
 * WordPress action hooks
 */
if ( ! function_exists( 'wpzerospam_action_hooks' ) ) {
  function wpzerospam_action_hooks() {
    // Remove the generator meta tag
    remove_action( 'wp_head', 'wp_generator' );
  }
}

add_action( 'after_setup_theme', 'wpzerospam_filter_hooks' );
add_action( 'after_setup_theme', 'wpzerospam_action_hooks' );

if ( ! function_exists( 'wpzerospam_remove_generator' ) ) {
  function wpzerospam_remove_generator() {
    return '';
  }
}
