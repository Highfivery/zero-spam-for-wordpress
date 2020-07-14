var WordPressZeroSpam = {
  init: function() {
    var forms = "#commentform";
    forms += ", #registerform";

    if ( typeof wpzerospam.key != "undefined" ) {
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
