<?php
/**
 * Block IP.
 *
 * @package ZeroSpam
 */
?>

<form method="post" class="zerospam-table-form<?php if ( ! empty( $location_form ) ) : ?> zerospam-block-location-form<?php endif; ?>" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
<?php wp_nonce_field( 'zerospam', 'zerospam' ); ?>
<input type="hidden" name="action" value="add_blocked_ip" />

<div class="zerospam-form-fields">
	<?php if ( empty( $location_form ) ) : ?>
		<div class="zerospam-form-field zerospam-form-field--half">
			<label for="blocked-ip"><?php _e( 'IP Address', 'zero-spam' ); ?></label>
			<input
				d="blocked-ip"
				type="text"
				name="blocked_ip"
				value="<?php if( ! empty( $_REQUEST['ip'] ) ) : echo esc_attr( $_REQUEST['ip'] ); endif; ?>"
				placeholder="e.g. xxx.xxx.x.x"
			/>
		</div>
	<?php else: ?>
		<div class="zerospam-form-field zerospam-form-field--half">
			<label for="location-type"><?php esc_html_e( 'Location Type', 'zero-spam' ); ?></label>
			<select id="location-type" name="key_type">
				<option value="country_code"><?php esc_html_e( 'Country Code', 'zero-spam' ); ?></option>
				<option value="region_code"><?php esc_html_e( 'Region Code', 'zero-spam' ); ?></option>
				<option value="city"><?php esc_html_e( 'City Name', 'zero-spam' ); ?></option>
				<option value="zip"><?php esc_html_e( 'Zip/Postal Code', 'zero-spam' ); ?></option>
			</select>
		</div>

		<div class="zerospam-form-field zerospam-form-field--half">
			<label for="location-key"><?php esc_html_e( 'Location Key', 'zero-spam' ); ?></label>
			<input
				id="location-key"
				type="text"
				name="blocked_key"
				value=""
				placeholder="ex. US"
			/>
		</div>
	<?php endif; ?>

	<div class="zerospam-form-field zerospam-form-field--half">
		<label for="blocked-type"><?php esc_html_e( 'Type', 'zero-spam' ); ?></label>
		<select id="blocked-type" name="blocked_type">
			<option value="temporary"><?php esc_html_e( 'Temporary', 'zero-spam' ); ?></option>
			<option value="permanent"><?php esc_html_e( 'Permanent', 'zero-spam' ); ?></option>
		</select>
	</div>

	<div class="zerospam-form-field">
		<label for="blocked-reason"><?php esc_html_e( 'Reason', 'zero-spam' ); ?></label>
		<input type="text" id="blocked-reason" name="blocked_reason" value="" placeholder="<?php esc_attr_e( 'e.g. Spammed form', 'zero-spam' ); ?>" />
	</div>

	<div class="zerospam-form-field zerospam-form-field--half">
		<label for="blocked-start-date"><?php esc_html_e( 'Start Date', 'zero-spam' ); ?></label>
		<input
			type="datetime-local"
			id="blocked-start-date"
			name="blocked_start_date"
			value=""
			placeholder="<?php echo esc_attr( __( 'Optional', 'zero-spam' ) ); ?>"
		/>
	</div>

	<div class="zerospam-form-field zerospam-form-field--half">
		<label for="blocked-end-date"><?php esc_html_e( 'End Date', 'zero-spam' ); ?></label>
		<input type="datetime-local" id="blocked-end-date" name="blocked_end_date" value="" placeholder="<?php esc_attr_e( 'Optional', 'zero-spam' ); ?>" />
	</div>
</div>

<?php if ( empty( $location_form ) ) : ?>
	<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add/Update Blocked IP →', 'zero-spam' ); ?>" />
<?php else: ?>
	<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add/Update Blocked Location →', 'zero-spam' ); ?>" />
<?php endif; ?>
</form>
