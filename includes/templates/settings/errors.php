<?php
/**
 * Settings: Error log template
 *
 * @package ZeroSpam
 */

$log = \ZeroSpam\Core\Utilities::get_error_log();
if ( ! $log ) {
	esc_html_e( 'Yay! No errors have been reported.', 'zero-spam' );
	return;
}
?>

<textarea readonly class="large-text code" rows="30"><?php echo esc_html( $log ); ?></textarea>
<a
	href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=delete-error-log' ), 'delete-error-log', 'zero-spam' ) ); ?>"
	class="button button-primary"
>
	<?php esc_html_e( 'Clear Error Log', 'zero-spam' ); ?>
</a>
