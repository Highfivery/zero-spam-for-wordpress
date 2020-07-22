/**
 * WordPress Zero Spam addon for handling Gravity Form submissions.
 */
WordPressZeroSpamIntegrations.gravityForms = {
  init: function() {
    // Make sure the WordPress Zero Spam key is available.
    if ( typeof wpzerospam.key == "undefined" ) { return; }

    var $form = jQuery( '.gform_wrapper form' );

    // If the form can't be found & should be, send a message to the console.
    if ( ! $form.length ) {
      console.log(
        'WordPress Zero Spam was unable to locate any Gravity Forms (.gform_wrapper form)'
      );
      return true;
    }

    console.log(
      'WordPress Zero Spam located ' + $form.length + ' Gravity Forms (.gform_wrapper form)'
    );

    $form.attr( 'data-wpzerospam', 'protected' );

    jQuery( document ).on( "gform_post_render", function() {
      jQuery( "<input>" )
        .attr( "type", "hidden" )
        .attr( "name", "wpzerospam_key" )
        .attr( "value", wpzerospam.key )
        .appendTo( $form );
    });
  }
}

jQuery(function() {
  WordPressZeroSpamIntegrations.gravityForms.init();
});
