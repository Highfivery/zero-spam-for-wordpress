var WordPressZeroSpam = {
  init: function() {
    var forms = "#buddypress #signup_form";
    forms += ", .wpzerospam";
    forms += ", .wpforms-form";
    forms += ", .gform_wrapper form";

    // Astra theme - changes the comment if to #ast-commentform
    forms += ", #ast-commentform";

    if ( typeof wpzerospam.key != "undefined" ) {
      // Gravity forms
      jQuery( document ).on( "gform_post_render", function() {
        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", wpzerospam.key )
          .appendTo( ".gform_wrapper form " );
      });

      // All other forms
      jQuery( forms ).on( "submit", function() {
        if ( ! jQuery( '[name="wpzerospam_key"]', jQuery( this ) ).length ) {
          jQuery( "<input>" )
            .attr( "type", "hidden" )
            .attr( "name", "wpzerospam_key" )
            .attr( "value", wpzerospam.key )
            .appendTo( forms );
        } else {
          jQuery( '[name="wpzerospam_key"]', jQuery( this ) ).value( wpzerospam.key );
        }

        return true;
      });
    }
  }
};

// Will hold the enqueues integrations on request.
var WordPressZeroSpamIntegrations = {};

jQuery(function() {
  WordPressZeroSpam.init();
});
