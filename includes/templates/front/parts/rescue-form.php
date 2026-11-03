<?php
/**
 * "Help for site owners": the rescue link form on the blocked, expired-link
 * and form error pages. Works without JavaScript.
 *
 * $args: Rescue_Link::form_args() (open, error, value, action, field_id,
 * field_name, action_name, nonce_field, nonce, faq_url, placeholder,
 * help_id, error_id).
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();

$zerospam_form      = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	\ZeroSpam\Core\Rescue_Link::form_args()
);
$zerospam_error     = (string) $zerospam_form['error'];
$zerospam_described = $zerospam_form['help_id'] . ( '' !== $zerospam_error ? ' ' . $zerospam_form['error_id'] : '' );
?>
<details class="zs-owner"<?php echo empty( $zerospam_form['open'] ) ? '' : ' open'; ?>>
	<summary><?php echo \ZeroSpam\Core\Front\Block_Page::icon( 'buoy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from Block_Page::ICONS. ?><span><?php esc_html_e( 'Help for site owners', 'zero-spam' ); ?></span><?php echo \ZeroSpam\Core\Front\Block_Page::icon( 'chev', 'zs-icon-sm zs-chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from Block_Page::ICONS. ?></summary>
	<div class="zs-owner-body">
		<p id="<?php echo esc_attr( $zerospam_form['help_id'] ); ?>"><?php esc_html_e( 'Site owner? If you administer this site, enter the email address of an administrator account on this site and we\'ll email you a link that lets you in for one hour.', 'zero-spam' ); ?></p>
		<form method="post" action="<?php echo esc_url( $zerospam_form['action'] ); ?>">
			<div class="zs-field">
				<label for="<?php echo esc_attr( $zerospam_form['field_id'] ); ?>"><?php esc_html_e( 'Administrator email address', 'zero-spam' ); ?></label>
				<div class="zs-row">
					<input class="zs-input" type="email" id="<?php echo esc_attr( $zerospam_form['field_id'] ); ?>" name="<?php echo esc_attr( $zerospam_form['field_name'] ); ?>" value="<?php echo esc_attr( (string) $zerospam_form['value'] ); ?>" placeholder="<?php echo esc_attr( $zerospam_form['placeholder'] ); ?>" required autocomplete="email" spellcheck="false" aria-describedby="<?php echo esc_attr( $zerospam_described ); ?>"<?php echo '' !== $zerospam_error ? ' aria-invalid="true" autofocus' : ''; ?>>
					<button class="zs-btn" type="submit"><?php echo \ZeroSpam\Core\Front\Block_Page::icon( 'mail', 'zs-icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from Block_Page::ICONS. ?><?php esc_html_e( 'Email me a rescue link', 'zero-spam' ); ?></button>
				</div>
				<?php if ( '' !== $zerospam_error ) : ?>
				<p class="zs-ferr" id="<?php echo esc_attr( $zerospam_form['error_id'] ); ?>" role="alert"><?php echo esc_html( $zerospam_error ); ?></p>
				<?php endif; ?>
			</div>
			<input type="hidden" name="action" value="<?php echo esc_attr( $zerospam_form['action_name'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $zerospam_form['nonce_field'] ); ?>" value="<?php echo esc_attr( $zerospam_form['nonce'] ); ?>">
		</form>
		<p class="zs-hint">
			<?php
			printf(
				/* translators: %s: link to the "I blocked myself!" question of the plugin FAQ */
				esc_html__( 'No access to that inbox? See %s in the Zero Spam FAQ.', 'zero-spam' ),
				'<a href="' . esc_url( $zerospam_form['faq_url'] ) . '">' . esc_html__( 'I blocked myself!', 'zero-spam' ) . '</a>'
			);
			?>
		</p>
	</div>
</details>
