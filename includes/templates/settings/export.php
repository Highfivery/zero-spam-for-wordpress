<?php
/**
 * Settings: Export/import template
 *
 * @package ZeroSpam
 */

?>

<?php if ( ! empty( $_GET['zerospam-error'] ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><strong>
			<?php
			switch( intval( $_GET['zerospam-error'] ) ) :
				case 1:
					esc_html_e( 'There was a problem importing the settings JSON. Please try again.', 'zero-spam' );
					break;
			endswitch;
			?>
		</strong></p>
		<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?></span></button>
	</div>
<?php elseif ( ! empty( $_GET['zerospam-success'] ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><strong><?php esc_html_e( 'The settings JSON has been successfully imported.', 'zero-spam' ); ?></strong></p>
		<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?>.</span></button>
	</div>
<?php endif; ?>

<h3><?php esc_html_e( 'Settings Import/Export', 'zero-spam' ); ?></h3>
<p><?php esc_html_e( 'Quickly export and import your saved settings into other sites below.', 'zero-spam' ); ?></p>
<?php
$settings      = ZeroSpam\Core\Settings::get_settings();
$settings_json = array();
foreach ( $settings as $key => $data ) :
	if ( isset( $data['value'] ) ) :
		$settings_json[ $key ] = $data['value'];
	endif;
endforeach;
?>
<div class="zerospam-export-import-block">
	<div class="zerospam-export-import-block-column">
		<h4><?php esc_html_e( 'Settings JSON', 'zero-spam' ); ?></h4>
		<textarea readonly class="large-text code" rows="10"><?php echo wp_json_encode( $settings_json ); ?></textarea>
	</div>
	<div class="zerospam-export-import-block-column">
		<h4><?php esc_html_e( 'Paste the settings JSON to import.', 'zero-spam' ); ?></h4>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="zerospam-import-settings-form">
		<?php wp_nonce_field( 'import_settings', 'zerospam' ); ?>
		<input type="hidden" name="action" value="import_settings" />
		<input type="hidden" name="redirect" value="<?php echo esc_url( ZeroSpam\Core\Utilities::current_url() ); ?>" />
		<textarea class="large-text code" name="settings" rows="10"></textarea>
		<input type="submit" class="button button-primary" value="<?php esc_html_e( 'Import Settings', 'zero-spam' ); ?>" />
		</form>
	</div>
</div>
