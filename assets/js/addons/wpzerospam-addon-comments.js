/**
 * WordPress Zero Spam addon for handling core comment submissions.
 */
WordPressZeroSpamAddons.comments = {
  init: function() {
    // Make sure the WordPress Zero Spam key is available.
    if ( typeof wpzerospam.key == "undefined" ) { return; }

    var $form = jQuery( '#commentform' );

    // If the form can't be found & should be, send a message to the console.
    if ( ! $form.length ) {
      console.log(
        'WordPress Zero Spam was unable to locate the comment form (#commentform)'
      );
      return true;
    }

    // Triggered when the comment form is submitted
    $form.on( "submit", function() {
      // Make sure the WordPress Zero Spam key isn't already on the form, if
      // not, add it.
      if ( ! jQuery( '[name="wpzerospam_key"]', jQuery( this ) ).length ) {
        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", wpzerospam.key )
          .appendTo( jQuery(this) );
      } else {
        jQuery( '[name="wpzerospam_key"]', jQuery( this ) ).value( wpzerospam.key );
      }

      return true;
    });
  }
}

jQuery(function() {
  WordPressZeroSpamAddons.comments.init();
});
