var WordPressZeroSpam = {
  init: function() {
    var forms = "#commentform";
    forms += ", #registerform";
    forms += ", #buddypress #signup_form";
    forms += ", .wpzerospam";
    forms += ", .ninja-forms-form";
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

      // WPForms
      jQuery( ".wpcf7-submit" ).click( function() {
        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", zerospam.key )
          .appendTo( ".wpcf7-form" );
      });

      // NinjaForms
      jQuery( document ).on( "nfFormReady", function( e, layoutView ) {
        var form = layoutView['$el'].find( 'form' );

        jQuery( "<input>" )
          .attr( "type", "hidden" )
          .attr( "name", "wpzerospam_key" )
          .attr( "value", zerospam.key )
          .appendTo( form );

        return true;
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

jQuery(function() {
  WordPressZeroSpam.init();
});
