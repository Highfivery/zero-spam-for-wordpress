<?php
/**
 * Block IP form template.
 *
 * @since 1.5.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
?>
<h2><?php echo __( 'Configure Block for', 'zerospam' ); ?> <?php echo $ip; ?></h2>
<form method="post" action="options.php" id="zero-spam__block-ip-form">
<table class="form-table">
	<tr>
		<th><label for="zerospam-ip">IP:</th>
		<td><input type="text" name="zerospam-ip" id="zerospam-ip" value="<?php echo esc_attr( $ip ); ?>" readonly="readonly" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="zerospam-type"><?php echo __( 'Type', 'zerospam' ); ?>:</th>
		<td>
			<select name="zerospam-type" id="zerospam-type">
				<option value="temporary"<?php if( isset( $data->type ) && 'temporary' == $data->type ): ?> selected="selected"<?php endif; ?>><?php echo __( 'Temporary', 'zerospam' ); ?></option>
				<option value="permanent"<?php if( isset( $data->type ) && 'permanent' == $data->type ): ?> selected="selected"<?php endif; ?>><?php echo __( 'Permanent', 'zerospam' ); ?></option>
			</select>
		</td>
	</tr>
	<tr class="zero-spam__period"<?php if( isset( $data->type ) && 'permanent' == $data->type ): ?> style="display: none;"<?php endif; ?>>
		<th><label for="zerospam-startdate"><?php echo __( 'Start Date', 'zerospam' ); ?>:</th>
		<td>
			<select name="zerospam-startdate-month" id="zerospam-startdate">
				<option value="1"<?php if( isset( $start_date_month ) && '1' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'January', 'zerospam' ); ?></option>
				<option value="2"<?php if( isset( $start_date_month ) && '2' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'February', 'zerospam' ); ?></option>
				<option value="3"<?php if( isset( $start_date_month ) && '3' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'March', 'zerospam' ); ?></option>
				<option value="4"<?php if( isset( $start_date_month ) && '4' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'April', 'zerospam' ); ?></option>
				<option value="5"<?php if( isset( $start_date_month ) && '5' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'May', 'zerospam' ); ?></option>
				<option value="6"<?php if( isset( $start_date_month ) && '6' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'June', 'zerospam' ); ?></option>
				<option value="7"<?php if( isset( $start_date_month ) && '7' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'July', 'zerospam' ); ?></option>
				<option value="8"<?php if( isset( $start_date_month ) && '8' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'August', 'zerospam' ); ?></option>
				<option value="9"<?php if( isset( $start_date_month ) && '9' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'September', 'zerospam' ); ?></option>
				<option value="10"<?php if( isset( $start_date_month ) && '10' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'October', 'zerospam' ); ?></option>
				<option value="11"<?php if( isset( $start_date_month ) && '11' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'November', 'zerospam' ); ?></option>
				<option value="12"<?php if( isset( $start_date_month ) && '12' == $start_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'December', 'zerospam' ); ?></option>
			</select>

			<select name="zerospam-startdate-day">
				<?php for ($i = 1; $i <= 31; $i++): ?>
					<option value="<?php echo $i; ?>"<?php if( isset( $start_date_day ) && $i == $start_date_day ): ?> selected="selected"<?php endif; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
			</select>

			<select name="zerospam-startdate-year">
				<?php for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 50 ); $i++ ): ?>
					<option value="<?php echo $i; ?>"<?php if( isset( $start_date_year ) && $i == $start_date_year ): ?> selected="selected"<?php endif; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
			</select>
		</td>
	</tr>
	<tr class="zero-spam__period"<?php if( isset( $data->type ) && 'permanent' == $data->type ): ?> style="display: none;"<?php endif; ?>>
		<th><label for="zerospam-enddate"><?php echo __( 'End Date', 'zerospam' ); ?>:</th>
		<td>
			<select name="zerospam-enddate-month" id="zerospam-enddate">
				<option value="1"<?php if( isset( $end_date_month ) && '1' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'January', 'zerospam' ); ?></option>
				<option value="2"<?php if( isset( $end_date_month ) && '2' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'February', 'zerospam' ); ?></option>
				<option value="3"<?php if( isset( $end_date_month ) && '3' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'March', 'zerospam' ); ?></option>
				<option value="4"<?php if( isset( $end_date_month ) && '4' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'April', 'zerospam' ); ?></option>
				<option value="5"<?php if( isset( $end_date_month ) && '5' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'May', 'zerospam' ); ?></option>
				<option value="6"<?php if( isset( $end_date_month ) && '6' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'June', 'zerospam' ); ?></option>
				<option value="7"<?php if( isset( $end_date_month ) && '7' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'July', 'zerospam' ); ?></option>
				<option value="8"<?php if( isset( $end_date_month ) && '8' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'August', 'zerospam' ); ?></option>
				<option value="9"<?php if( isset( $end_date_month ) && '9' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'September', 'zerospam' ); ?></option>
				<option value="10"<?php if( isset( $end_date_month ) && '10' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'October', 'zerospam' ); ?></option>
				<option value="11"<?php if( isset( $end_date_month ) && '11' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'November', 'zerospam' ); ?></option>
				<option value="12"<?php if( isset( $end_date_month ) && '12' == $end_date_month ): ?> selected="selected"<?php endif; ?>><?php echo __( 'December', 'zerospam' ); ?></option>
			</select>
			<select name="zerospam-enddate-day">
				<?php for ($i = 1; $i <= 31; $i++): ?>
					<option value="<?php echo $i; ?>"<?php if( isset( $end_date_day ) && $i == $end_date_day ): ?> selected="selected"<?php endif; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
			</select>

			<select name="zerospam-enddate-year">
				<?php for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 50 ); $i++ ): ?>
					<option value="<?php echo $i; ?>"<?php if( isset( $end_date_year ) && $i == $end_date_year ): ?> selected="selected"<?php endif; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="zerospam-reason"><?php echo __( 'Reason', 'zerospam' ); ?>:</th>
		<td><input type="text" name="zerospam-reason" id="zerospam-reason" class="large-text" value="<?php if( isset( $data->reason ) ): echo esc_attr( $data->reason ); endif; ?>"></td>
	</tr>
</table>
<p class="submit">
		<input type="submit" value="<?php echo __( 'Save Changes', 'zerospam' ); ?>" class="button button-primary button-large">
		<? if ( $ip ): ?><a href="javascript: closeForms();" class="button button-large"><?php echo __( 'Close', 'zerospam' ); ?></a><?php endif; ?>
</p>
</form>
<script>
jQuery( document ).ready( function( $ ) {
	$( "#zero-spam__block-ip-form" ).submit( function( e ) {
		e.preventDefault();

		var form = $( this );

		$( "input[type='submit']", form ).attr( "disabled", true );
		$( ".zero-spam__msg" ).remove();

		var data = $( "#zero-spam__block-ip-form" ).serialize();
		data += '&security=<?php echo $ajax_nonce; ?>';
		data += '&action=block_ip';

		$.post( ajaxurl, data, function( d ) {
			$( "input[type='submit']", form ).attr( "disabled", false );

			form.prepend( "<div class='zero-spam__msg'>This IP address has been updated.</div>" );

			<? if ( $ip ): ?>updateRow( '<?php echo $ip; ?>' );<?php endif; ?>
		});
	});

	$( "#zerospam-type" ).change( function() {
		var val = $( this ).val();
		if ( "permanent" == val ) {
			$( ".zero-spam__period" ).hide();
		} else {
			$( ".zero-spam__period" ).show();
		}
	});
});
</script>
