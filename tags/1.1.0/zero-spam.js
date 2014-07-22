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

    var form = $( "#commentform" );

    $( form ).submit( function() {
        $( "<input>" ).attr( "type", "hidden" )
            .attr( "name", "zero-spam" )
            .attr( "value", "1" )
            .appendTo( form );

        return true;
    });

})( jQuery );
