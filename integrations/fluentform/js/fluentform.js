/**
 * WordPress Zero Spam addon for handling core Fluent Form submissions.
 */
WordPressZeroSpamIntegrations.fluentform = {
  init: function() {
    // Make sure the WordPress Zero Spam key is available.
    if ( typeof wpzerospam.key == "undefined" ) { return; }

    var $form = jQuery( '.frm-fluent-form' );

    // If the form can't be found & should be, send a message to the console.
    if ( ! $form.length ) {
      console.log(
        'WordPress Zero Spam was unable to locate any Fluent Forms (.frm-fluent-form)'
      );
      return true;
    }

    console.log(
      'WordPress Zero Spam located ' + $form.length + ' Fluent Form(s) (.frm-fluent-form)'
    );

    $form.attr( 'data-wpzerospam', 'protected' );

    jQuery( $form ).submit( function() {console.log('sdsd');
      if ( ! jQuery( '[name="wpzerospam_key"]', $form ).length ) {
        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", wpzerospam.key )
          .appendTo( $form );
      } else {
        jQuery( '[name="wpzerospam_key"]', $form ).value( wpzerospam.key );
      }
    });
  }
}

jQuery(function() {
  WordPressZeroSpamIntegrations.fluentform.init();
});
