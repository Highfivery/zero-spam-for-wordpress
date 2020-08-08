<?php
/**
 * Comment form spam detection
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Adds admin settings for comment submission protection.
 *
 * @since 4.9.9
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_comments_admin_fields' ) ) {
  function wpzerospam_comments_admin_fields() {
    // Option to strips links in comments
    add_settings_field( 'strip_comment_links', __( 'Strip Comment Links', 'zero-spam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_onsite', [
      'label_for' => 'strip_comment_links',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => __( 'Spambots commonly post spam links in comments. Enable this option to strip links from comments.', 'zero-spam' ),
      'options'   => [
        'enabled' => __( 'Enabled', 'zero-spam' )
      ]
    ]);

    // Option to remove author links
    add_settings_field( 'strip_comment_author_links', __( 'Strip Comment Author Links', 'zero-spam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_onsite', [
      'label_for' => 'strip_comment_author_links',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => __( 'Spammers are well-known at injecting malicious links in the comment author website field, this option disables it.', 'zero-spam' ),
      'options'   => [
        'enabled' => __( 'Enabled', 'zero-spam' )
      ]
    ]);

    // Add option to enable/disable comment form submission protection.
    add_settings_field( 'verify_comments', __( 'Detect Spam/Malicious Comments', 'zero-spam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_comments',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => __( 'Monitors comments for malicious links and automated spambot submissions.', 'zero-spam' ),
      'options'   => [
        'enabled' => __( 'Enabled', 'zero-spam' )
      ]
    ]);
  }
}
add_action( 'wpzerospam_admin_options', 'wpzerospam_comments_admin_fields' );

/**
 * Add validation to the comment form submission protection admin fields.
 *
 * @since 4.9.9
 *
 * @param array $fields Array on available admin fields.
 */
if ( ! function_exists( 'wpzerospam_comments_admin_validation' ) ) {
  function wpzerospam_comments_admin_validation( $fields ) {
    if ( empty( $fields['verify_comments'] ) ) { $fields['verify_comments'] = 'disabled'; }
    if ( empty( $fields['strip_comment_links'] ) ) { $fields['strip_comment_links'] = 'disabled'; }
    if ( empty( $fields['strip_comment_author_links'] ) ) { $fields['strip_comment_author_links'] = 'disabled'; }

    return $fields;
  }
}
add_filter( 'wpzerospam_admin_validation', 'wpzerospam_comments_admin_validation' );

if ( ! function_exists( 'wpzerospam_comments_admin_fields_default' ) ) {
  function wpzerospam_comments_admin_fields_default( $defaults ) {
    if ( empty( $defaults['verify_comments'] ) ) { $defaults['verify_comments'] = 'enabled'; }
    if ( empty( $defaults['strip_comment_links'] ) ) { $defaults['strip_comment_links'] = 'disabled'; }
    if ( empty( $defaults['strip_comment_author_links'] ) ) { $defaults['strip_comment_author_links'] = 'disabled'; }

    return $defaults;
  }
}
add_filter( 'wpzerospam_admin_option_defaults', 'wpzerospam_comments_admin_fields_default' );

if ( ! function_exists( 'wpzerospam_comments_admin_submission_data_item' ) ) {
  function wpzerospam_comments_admin_submission_data_item( $key, $value ) {
    switch( $key ) {
      case 'comment_post_ID':
        $post = get_post( $value );
        if ( $post ) {
          $item_value = '<a href="' . get_the_permalink( $value ) . '">' . get_the_title( $value ) . '</a>';
        } else {
          $item_value = 'N/A';
        }
        echo wpzerospam_admin_details_item( __( 'Comment Post', 'zero-spam' ), $item_value );
      break;
      case 'comment_author':
        echo wpzerospam_admin_details_item( __( 'Author', 'zero-spam' ), $value );
      break;
      case 'comment_author_email':
        echo wpzerospam_admin_details_item( __( 'Email', 'zero-spam' ), $value );
      break;
      case 'comment_author_url':
        echo wpzerospam_admin_details_item( __( 'Website', 'zero-spam' ), $value );
      break;
      case 'comment_content':
        echo wpzerospam_admin_details_item( __( 'Comment', 'zero-spam' ), sanitize_text_field( $value ) );
      break;
      case 'comment_type':
        echo wpzerospam_admin_details_item( __( 'Comment Type', 'zero-spam' ), $value );
      break;
      case 'comment_parent':
        echo wpzerospam_admin_details_item( __( 'Comment Parent ID', 'zero-spam' ), '<a href="' . get_comment_link( $value  ) . '">' . $value . '</a>' );
      break;
      case 'comment_as_submitted':
        foreach( $value as $k => $v ):
          if ( ! $v ) { continue; }
          switch( $k ):
            case 'comment_author':
              if ( empty( $author_shown ) ) {
                echo wpzerospam_admin_details_item( __( 'Author', 'zero-spam' ), $v );
              }
            break;
            case 'comment_author_email':
              if ( empty( $author_email ) ) {
                echo wpzerospam_admin_details_item( __( 'Email', 'zero-spam' ), $v );
              }
            break;
            case 'comment_author_url':
              if ( empty( $author_url ) ) {
                echo wpzerospam_admin_details_item( __( 'Website', 'zero-spam' ), $v );
              }
            break;
            case 'comment_content':
              echo wpzerospam_admin_details_item( __( 'Comment', 'zero-spam' ), sanitize_text_field( $v ) );
            break;
            case 'user_ip':
              echo wpzerospam_admin_details_item( __( 'User IP', 'zero-spam' ), '<a href="https://zerospam.org/ip-lookup/' . urlencode( $v ) .'" target="_blank" rel="noopener noreferrer">' . $v . '</a>' );
            break;
            case 'user_agent':
              echo wpzerospam_admin_details_item( __( 'User Agent', 'zero-spam' ), $v );
            break;
            case 'blog':
              echo wpzerospam_admin_details_item( __( 'Site', 'zero-spam' ), $v );
            break;
            case 'blog_lang':
              echo wpzerospam_admin_details_item( __( 'Site Language', 'zero-spam' ), $v );
            break;
            case 'blog_charset':
              echo wpzerospam_admin_details_item( __( 'Site Charset', 'zero-spam' ), $v );
            break;
            case 'permalink':
              echo wpzerospam_admin_details_item( __( 'Permalink', 'zero-spam' ), '<a href="' . $v . '" target="_blank">' . $v . '</a>' );
            break;
            default:
              echo wpzerospam_admin_details_item( $k, $v );
          endswitch;
        endforeach;
      break;
      case 'akismet_result':
        echo wpzerospam_admin_details_item( __( 'Akismet Result', 'zero-spam' ), $value );
      break;
      case 'akismet_pro_tip':
        echo wpzerospam_admin_details_item( __( 'Akismet Pro Tip', 'zero-spam' ), $value );
      break;
    }
  }
}
add_action( 'wpzerospam_admin_submission_data_items', 'wpzerospam_comments_admin_submission_data_item', 10, 2 );

if ( ! function_exists( 'wpzerospam_comments_defined_submission_data' ) ) {
  function wpzerospam_comments_defined_submission_data( $submission_data_keys ) {
    $submission_data_keys[] = 'comment_post_ID';
    $submission_data_keys[] = 'comment_author';
    $submission_data_keys[] = 'comment_author_email';
    $submission_data_keys[] = 'comment_author_url';
    $submission_data_keys[] = 'comment_content';
    $submission_data_keys[] = 'comment_type';
    $submission_data_keys[] = 'comment_parent';
    $submission_data_keys[] = 'comment_as_submitted';
    $submission_data_keys[] = 'akismet_result';
    $submission_data_keys[] = 'akismet_pro_tip';

    return $submission_data_keys;
  }
}
add_filter( 'wpzerospam_defined_submission_data', 'wpzerospam_comments_defined_submission_data', 10, 1 );

/**
 * Runs the comment form spam detections.
 *
 * Runs all action & filter hooks needed for monitoring comment submissions for
 * spam (when enabled via the 'Detect Comment Spam' option).
 *
 * @since 4.9.9
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_comments_after_setup_theme' ) ) {
  function wpzerospam_comments_after_setup_theme() {
    $options = wpzerospam_options();

    // Add the 'comment' spam type.
    add_filter( 'wpzerospam_types', 'wpzerospam_comments_types' );

    // Determines is author links should be stripped.
    if ( 'enabled' == $options['strip_comment_author_links'] ) {
      add_filter( 'get_comment_author_link', 'wpzerospam_remove_comment_author_link', 10, 3 );
      add_filter( 'get_comment_author_url', 'wpzerospam_remove_author_url' );
      add_filter( 'comment_form_default_fields', 'wpzerospam_remove_author_url_field' );
    }

    // Determines if comment links should be stripped.
    if ( 'enabled' == $options['strip_comment_links'] ) {
      remove_filter( 'comment_text', 'make_clickable', 9 );
      add_filter( 'comment_text', 'wpzerospam_strip_comment_links_display', 10, 1);
      add_filter( 'comment_text_rss', 'wpzerospam_strip_comment_links_display', 10, 1);
      add_filter( 'comment_excerpt', 'wpzerospam_strip_comment_links_display', 10, 1);
      add_filter( 'preprocess_comment', 'wpzerospam_strip_comment_links', 10, 1 );
    }

    // Check if detecting comments is enabled & user is unauthenticated.
    if ( 'enabled' != $options['verify_comments'] || is_user_logged_in() ) { return false; }

    // Add the 'honeypot' field to the comment form.
    add_filter( 'comment_form_defaults', 'wpzerospam_comments_form_defaults' );

    // Preprocess comment submissions.
    add_action( 'preprocess_comment', 'wpzerospam_comments_preprocess' );

    // Register & enqueue needed CSS & JS files, only when the comment form is on the page.
    add_action( 'comment_form', 'wpzerospam_comments_enqueue_scripts' );
  }
}
add_action( 'after_setup_theme', 'wpzerospam_comments_after_setup_theme' );

/**
 * Strips links from comment submissions.
 */
if ( ! function_exists( 'wpzerospam_strip_comment_links' ) ) {
  function wpzerospam_strip_comment_links( $comment ) {
    global $allowedtags;

    $tags = $allowedtags;
    unset( $tags['a'] );
    $content = addslashes( wp_kses( stripslashes( $comment ), $tags) );

    return $comment;
  }
}

/**
 * Strips links from comments when displayed.
 */
if ( ! function_exists( 'wpzerospam_strip_comment_links_display' ) ) {
  function wpzerospam_strip_comment_links_display( $comment ) {
    global $allowedtags;

    $tags = $allowedtags;
    unset( $tags['a'] );
    $content = addslashes( wp_kses( stripslashes( $comment ), $tags) );

    return $comment;
  }
}

/**
 * Removes the comment author link.
 */
if ( ! function_exists( 'wpzerospam_remove_comment_author_link' ) ) {
  function wpzerospam_remove_comment_author_link( $return, $author, $comment_ID ) {
    return $author;
  }
}

/**
 * Removes the comment author url from display.
 */
if ( ! function_exists( 'wpzerospam_remove_author_url' ) ) {
  function wpzerospam_remove_author_url() {
    return false;
  }
}

/**
 * Removed the comment author url field.
 */
if ( ! function_exists( 'wpzerospam_remove_author_url_field' ) ) {
  function wpzerospam_remove_author_url_field( $fields ) {
    if ( isset( $fields['url'] ) ) {
      unset( $fields['url'] );
    }

    return $fields;
  }
}

/**
 * Adds the 'comment' spam type.
 *
 * @param array An array of the current spam types.
 * @return array The resulting current spam types.
 */
if ( ! function_exists( 'wpzerospam_comments_types' ) ) {
  function wpzerospam_comments_types( $types ) {
    $types = array_merge( $types, [ 'comment' => __( 'Comment', 'zero-spam' ) ] );

    return $types;
  }
}

/**
 * Add a 'honeypot' field to the comment form.
 *
 * @since 4.9.9
 *
 * @link https://developer.wordpress.org/reference/hooks/comment_form_defaults/
 *
 * @return array The default comment form arguments.
 */
if ( ! function_exists( 'wpzerospam_comments_form_defaults' ) ) {
  function wpzerospam_comments_form_defaults( $defaults ) {
    $defaults['fields']['wpzerospam_hp'] = wpzerospam_honeypot_field();

    return $defaults;
  }
}

/**
 * Preprocess comment fields.
 *
 * @since 4.3.7
 *
 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
 *
 * @return array The $commentdata array which may have been manipulated during the execution of the handler.
 */
if ( ! function_exists( 'wpzerospam_comments_preprocess' ) ) {
  function wpzerospam_comments_preprocess( $commentdata ) {
    $options  = wpzerospam_options();
    $honeypot = wpzerospam_get_honeypot();

    if (
      // First, check the 'honeypot' field.
      ( ! isset( $_REQUEST[ $honeypot ] ) || $_REQUEST[ $honeypot ] ) ||
      // Next, check the 'wpzerospam_key' field.
      ( empty( $_REQUEST['wpzerospam_key'] ) || wpzerospam_get_key() != $_REQUEST['wpzerospam_key'] )
    ) {
      // Spam comment selected.
      do_action( 'wpzerospam_comment_spam', $commentdata );
      wpzerospam_spam_detected( 'comment', $commentdata );

      return false;
    }

    return $commentdata;
  }
}

/**
 * Register & enqueue CSS & JS files for comment spam detection.
 *
 * @since 4.9.9
 *
 * @link https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_comments_enqueue_scripts' ) ) {
  function wpzerospam_comments_enqueue_scripts() {
    // Load the 'wpzerospam_key' form field JS
    wp_enqueue_script(
      'wpzerospam-integration-comments',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/comments/js/comments.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
