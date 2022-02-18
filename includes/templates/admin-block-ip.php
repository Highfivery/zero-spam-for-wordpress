<?php
/**
 * Block IP.
 *
 * @package ZeroSpam
 * @since 5.0.0
 */
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"<?php if ( ! empty( $location_form ) ) : ?> class="zerospam-block-location-form"<?php endif; ?>>
<?php wp_nonce_field( 'zerospam', 'zerospam' ); ?>
<input type="hidden" name="action" value="add_blocked_ip" />
<input type="hidden" name="redirect" value="<?php echo esc_url( ZeroSpam\Core\Utilities::current_url() ); ?>" />

<?php if ( empty( $location_form ) ) : ?>
	<label for="blocked-ip">
		<?php _e( 'IP Address', 'zero-spam' ); ?>
		<input
			type="text"
			name="blocked_ip"
			value="<?php if( ! empty( $_REQUEST['ip'] ) ) : echo esc_attr( $_REQUEST['ip'] ); endif; ?>"
			placeholder="e.g. xxx.xxx.x.x"
		/>
	</label>
<?php else: ?>
	<label for="location-type">
		<?php esc_html_e( 'Location Type', 'zero-spam' ); ?>
		<select id="location-type" name="key_type">
			<option value="country_code"><?php esc_html_e( 'Country Code', 'zero-spam' ); ?></option>
			<option value="region_code"><?php esc_html_e( 'Region Code', 'zero-spam' ); ?></option>
			<option value="city"><?php esc_html_e( 'City Name', 'zero-spam' ); ?></option>
			<option value="zip"><?php esc_html_e( 'Zip/Postal Code', 'zero-spam' ); ?></option>
		</select>
	</label>

	<label for="location-key">
		<?php esc_html_e( 'Location Key', 'zero-spam' ); ?>
		<input
			id="location-key"
			type="text"
			name="blocked_key"
			value=""
			placeholder="ex. US"
		/>
	</label>
<?php endif; ?>

<label for="blocked-type"><?php esc_html_e( 'Type', 'zero-spam' ); ?>
	<select id="blocked-type" name="blocked_type">
		<option value="temporary"><?php esc_html_e( 'Temporary', 'zero-spam' ); ?></option>
		<option value="permanent"><?php esc_html_e( 'Permanent', 'zero-spam' ); ?></option>
	</select>
</label>

<label for="blocked-reason">
	<?php esc_html_e( 'Reason', 'zero-spam' ); ?>
	<input type="text" id="blocked-reason" name="blocked_reason" value="" placeholder="<?php esc_attr_e( 'e.g. Spammed form', 'zero-spam' ); ?>" />
</label>

<label for="blocked-start-date">
	<?php esc_html_e( 'Start Date', 'zero-spam' ); ?>
	<input
		type="datetime-local"
		id="blocked-start-date"
		name="blocked_start_date"
		value=""
		placeholder="<?php echo esc_attr( __( 'Optional', 'zero-spam' ) ); ?>"
	/>
</label>

<label for="blocked-end-date">
	<?php esc_html_e( 'End Date', 'zero-spam' ); ?>
	<input type="datetime-local" id="blocked-end-date" name="blocked_end_date" value="" placeholder="<?php esc_attr_e( 'Optional', 'zero-spam' ); ?>" />
</label>

<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add/Update Blocked IP', 'zero-spam' ); ?>" />

</form>
