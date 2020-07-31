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

    if ( 'enabled' == $options['strip_comment_links'] ) {
      remove_filter( 'comment_text', 'make_clickable', 9 );

      add_filter( 'comment_text', 'wpzerospam_strip_comment_links_display', 10, 1);
      add_filter( 'comment_text_rss', 'wpzerospam_strip_comment_links_display', 10, 1);
      add_filter( 'comment_excerpt', 'wpzerospam_strip_comment_links_display', 10, 1);

      add_filter( 'preprocess_comment', 'wpzerospam_strip_comment_links', 10, 1 );
    }

    if ( 'enabled' == $options['strip_comment_author_links'] ) {
      add_filter( 'get_comment_author_link', 'wpzerospam_remove_comment_author_link', 10, 3 );
      add_filter( 'get_comment_author_url', 'wpzerospam_remove_author_url' );
      add_filter( 'comment_form_default_fields', 'wpzerospam_remove_author_url_field' );
    }
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

if ( ! function_exists( 'wpzerospam_remove_author_url_field' ) ) {
  function wpzerospam_remove_author_url_field( $fields ) {
    if ( isset( $fields['url'] ) ) {
      unset( $fields['url'] );
    }

    return $fields;
  }
}

if ( ! function_exists( 'wpzerospam_remove_comment_author_link' ) ) {
  function wpzerospam_remove_comment_author_link( $return, $author, $comment_ID ) {
    return $author;
  }
}

if ( ! function_exists( 'wpzerospam_remove_author_url' ) ) {
  function wpzerospam_remove_author_url() {
    return false;
  }
}

if ( ! function_exists( 'wpzerospam_strip_comment_links' ) ) {
  function wpzerospam_strip_comment_links( $comment ) {
    global $allowedtags;

    $tags = $allowedtags;
    unset( $tags['a'] );
    $content = addslashes( wp_kses( stripslashes( $comment ), $tags) );

    return $comment;
  }
}

if ( ! function_exists( 'wpzerospam_strip_comment_links_display' ) ) {
  function wpzerospam_strip_comment_links_display( $comment ) {
    global $allowedtags;

    $tags = $allowedtags;
    unset( $tags['a'] );
    $content = addslashes( wp_kses( stripslashes( $comment ), $tags) );

    return $comment;
  }
}
