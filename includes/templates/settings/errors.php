<?php
/**
 * Settings: Error log template
 *
 * @package ZeroSpam
 */

$log = \ZeroSpam\Core\Utilities::get_error_log();
if ( ! $log ) {
	esc_html_e( 'Yay! No errors have been reported.', 'zerospam' );
	return;
}
?>

<textarea readonly class="large-text code" rows="30"><?php echo esc_html( $log ); ?></textarea>
<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&amp;tab=error&amp;zerospam-action=delete-error-log' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Clear Error Log' ); ?></a>
