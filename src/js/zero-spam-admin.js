jQuery( document ).ready( function( $ ) {
	$.each( $( "[data-ip-location]" ), function() {
		var element = $( this ),
			ip = $( this ).data( "ip-location" );
		jQuery.post( ajaxurl, {
			action: 'get_location',
			security: zero_spam_admin.nonce,
			ip: ip
		}, function( data ) {
			var obj = $.parseJSON( data ),
				html = '';

			if ( obj ) {

				if ( obj.country_name ) {
					html += obj.country_code;
				}

				if ( obj.region_name ) {
					if ( html.length ) { html += ', '; }
					html += obj.region_name;
				}

				if ( obj.city ) {
					if ( html.length ) { html += ', '; }
					html += obj.city;
				}

				if ( obj.country_code ) {
					html = '<span class="country-flag country-flags-' + obj.country_code.toLowerCase() + '"></span> ' + html;
				}
			}

			if ( ! html.length ) html = '<div class="zero-spam__text-center"><i class="fa fa-exclamation-triangle"></i></div>';

			element.html( html );
		});
	});

	$( ".zero-spam__block-ip, .zero-spam__trash" ).click( function( e ) {
		e.preventDefault();

		closeForms();

		var row = $( this ).closest( "tr" ),
			form_row = $( "<tr class='zero-spam__row-highlight'>" ),
			btn = $( this ),
			btn_cell = btn.parent(),
			ip = btn.data( "ip" ),
			action = '';

			row.addClass( "zero-spam__loading" );

		if ( btn.hasClass( "zero-spam__trash" ) ) {
			action = 'trash_ip_block';
		} else {
			action = 'block_ip_form';
		}

		$.post( ajaxurl, {
			action: action,
			security: zero_spam_admin.nonce,
			ip: ip
		}, function( data ) {
			row.removeClass( "zero-spam__loading" );

			if ( btn.hasClass( "zero-spam__trash" ) ) {
				action = 'trash_ip_block';
				row.fadeOut( function() {
					row.remove();

					if ( $( ".zero-spam__table tbody tr" ).length === 0 ) {
						$( "#zerospam-id-container" ).after( "No blocked IPs found." );
						$( "#zerospam-id-container" ).remove();
					}
				});
			} else {
				action = 'block_ip_form';

				row.addClass( "zero-spam__loaded" );

				form_row.append( "<td colspan='10'>" + data + "</td>" );

				row.before( form_row );
			}
		});
	});
});

function closeForms() {
	jQuery( ".zero-spam__row-highlight" ).remove();
	jQuery( "tr" ).removeClass( "zero-spam__loading" );
	jQuery( "tr" ).removeClass( "zero-spam__loaded" );
}

function clearLog() {
	if ( true === confirm("This will PERMANENTLY delete all data in the spammer log. This action cannot be undone. Are you sure you want to continue?") ) {
		jQuery.post( ajaxurl, {
			action: 'reset_log',
			security: zero_spam_admin.nonce,
		}, function() {
			location.reload();
		});
	}
}

function updateRow( ip ) {
	if ( ip ) {
		jQuery.post( ajaxurl, {
			action: 'get_blocked_ip',
			security: zero_spam_admin.nonce,
			ip: ip
		}, function( data ) {
			var d = jQuery.parseJSON( data ),
				row = jQuery( "tr[data-ip='" + d.ip + "']" ),
				label;
			if ( true === d.is_blocked ) {
				label = '<span class="zero-spam__label zero-spam__bg--primary">Blocked</span>';
			} else {
				label = '<span class="zero-spam__label zero-spam__bg--trinary">Unblocked</span>';
			}

			jQuery( ".zero-spam__reason", row ).text( d.reason );
			jQuery( ".zero-spam__start-date", row ).text( d.start_date_txt );
			jQuery( ".zero-spam__end-date", row ).text( d.end_date_txt );
			jQuery( ".zero-spam__status", row ).html( label );
		});
	}
}