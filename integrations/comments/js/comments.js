/**
 * Spam detection for comment submissions.
 */
try {
  WordPressZeroSpamIntegrations.comments = {
    init: function() {
      // Make sure the WordPress Zero Spam key is available.
      if ( typeof wpzerospam.key == "undefined" ) {
        console.log("WordPress Zero Spam was unable to locate the key for comment submission protection.");
        return;
      }

      // #ast-commentform - Astra theme support (changes the comment if to #ast-commentform)
      // @TODO - Find a better way to support the Astra theme by checking if it's enabled.
      var $form = jQuery( '#commentform, #ast-commentform' );

      // If the form can't be found & should be, send a message to the console.
      if ( ! $form.length ) {
        console.log(
          'WordPress Zero Spam was unable to locate any comment forms (#commentform) on this page.'
        );
        return true;
      }

      console.log(
        `WordPress Zero Spam located ${$form.length} comment form(s) (#commentform) on this page.`
      );

      $form.attr( 'data-wpzerospam', 'protected' );

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
    WordPressZeroSpamIntegrations.comments.init();
  });
}
catch( err ) {
  console.log( 'WordPress Zero Spam was unable to initialize comment submission protection.' );
  console.log( err );
}
