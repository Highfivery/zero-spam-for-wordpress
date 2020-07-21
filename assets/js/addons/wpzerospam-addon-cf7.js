/**
 * WordPress Zero Spam addon for handling core CF7 submissions.
 */
WordPressZeroSpamAddons.cf7 = {
  init: function() {
    // Make sure the WordPress Zero Spam key is available.
    if ( typeof wpzerospam.key == "undefined" ) { return; }

    var $form = jQuery( '.wpcf7-form' );

    // If the form can't be found & should be, send a message to the console.
    if ( ! $form.length ) {
      console.log(
        'WordPress Zero Spam was unable to locate the CF7 form (.wpcf7-form)'
      );
      return true;
    }

    // Triggered when the comment form is submitted
    jQuery( ".wpcf7-submit", $form ).click( function() {
      // Make sure the WordPress Zero Spam key isn't already on the form, if
      // not, add it.
      if ( ! jQuery( '[name="wpzerospam_key"]', $form ).length ) {
        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", wpzerospam.key )
          .appendTo( $form );
      } else {
        jQuery( '[name="wpzerospam_key"]', $form ).value( wpzerospam.key );
      }

      return true;
    });
  }
}

jQuery(function() {
  WordPressZeroSpamAddons.cf7.init();
});
