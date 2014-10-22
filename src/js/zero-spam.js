/**
 * WordPress Zero Spam JS
 *
 * Required JS for the WordPress Zero Spam plugin to work properly.
 *
 * @link http://www.benmarshall.me/wordpress-zero-spam-plugin
 * @since 1.0.0
 *
 * @package WordPress
 * @subpackage Zero Spam
 */

( function( $ ) {
    'use strict';

    var forms = "#commentform";
    forms += ", #registerform";
    forms += ", .wpcf7-form";
    forms += ", .gform_wrapper form";
    forms += ", #buddypress #signup_form";

    if ( typeof zerospam.key != 'undefined') {
      $( forms ).submit( function() {
      	console.log(3);
          $( "<input>" ).attr( "type", "hidden" )
              .attr( "name", "zerospam_key" )
              .attr( "value", zerospam.key )
              .appendTo( forms );

          return true;
      });
    }

})( jQuery );
