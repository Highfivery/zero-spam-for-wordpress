<?php
/**
 * Handles checking submitted Formidable forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.9.0
 */

/**
 * Add the 'formidable' spam type
 *
 * @since 4.9.0
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'formidable' => __( 'Formidable Forms', 'zero-spam' ) ] );
  return $types;
});

/**
 * Validates the wpzerospam_key
 *
 * @since 4.9.0
 * @link https://formidableforms.com/knowledgebase/frm_validate_entry/
 */
if ( ! function_exists( 'wpzerospam_formidable_frm_validate_entry' ) ) {
  function wpzerospam_formidable_frm_validate_entry( $errors, $values ) {
    // Don't validate for logged in users
    if ( is_user_logged_in() ) {
      return $errors;
    }

    // Validate the wpzerospam_key
    if ( ! wpzerospam_key_check( $values ) ) {
      $options = wpzerospam_options();

      do_action( 'wpzerospam_formidable_spam' );

      wpzerospam_spam_detected( 'formidable', $values, false );

      $errors['wpzerospam'] = $options['spam_message'];
    }

    return $errors;
  }
}
add_filter( 'frm_validate_entry', 'wpzerospam_formidable_frm_validate_entry', 20, 2 );

/**
 * Adds the hidden wpzerospam_key field
 *
 * @since 4.9.0
 * @link https://formidableforms.com/knowledgebase/frm_entry_form/
 */
if ( ! function_exists( 'wpzerospam_formidable_frm_entry_form' ) ) {
  function wpzerospam_formidable_frm_entry_form( $form ) {
    echo '<input type="hidden" name="wpzerospam_key" value="" />';
  }
}
add_action( 'frm_entry_form', 'wpzerospam_formidable_frm_entry_form' );

/**
 * Adds the wpzerospam_key value to the hidden field via JS
 *
 * @since 4.9.0
 * @link https://formidableforms.com/knowledgebase/frm_entries_footer_scripts/
 */
if ( ! function_exists( 'wpzerospam_formidable_frm_entries_footer_scripts' ) ) {
  function wpzerospam_formidable_frm_entries_footer_scripts( $fields, $form ) {
    ?>
    jQuery( '[name="wpzerospam_key"]' ).val( "<?php echo wpzerospam_get_key(); ?>" );
    <?php
  }
}
add_action( 'frm_entries_footer_scripts', 'wpzerospam_formidable_frm_entries_footer_scripts', 20, 2 );
