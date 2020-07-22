/**
 * WordPress Zero Spam addon for handling core BuddyPress submissions.
 */
WordPressZeroSpamIntegrations.buddyPress = {
  init: function() {
    // Make sure the WordPress Zero Spam key is available.
    if ( typeof wpzerospam.key == "undefined" ) { return; }

    var $form = jQuery( '#buddypress #signup_form' );

    // If the form can't be found & should be, send a message to the console.
    if ( ! $form.length ) {
      console.log(
        'WordPress Zero Spam was unable to locate any BuddyPress forms (#buddypress #signup_form)'
      );
      return true;
    }

    console.log(
      'WordPress Zero Spam located ' + $form.length + ' BuddyPress form(s) (#buddypress #signup_form)'
    );

    $form.attr( 'data-wpzerospam', 'protected' );

    $form.on( "submit", function() {
      if ( ! jQuery( '[name="wpzerospam_key"]', jQuery( this ) ).length ) {
        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", wpzerospam.key )
          .appendTo( $form );
      } else {
        jQuery( '[name="wpzerospam_key"]', jQuery( this ) ).value( wpzerospam.key );
      }

      return true;
    });
  }
}

jQuery(function() {
  WordPressZeroSpamIntegrations.cf7.init();
});
