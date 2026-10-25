<?php
/**
 * "Let me back in": the page an emailed rescue link opens. Opening the link
 * leaves the token unused; the button posts it (Access::init()).
 *
 * Rendered by ZeroSpam\Core\Front\Block_Page; a theme can replace this file
 * with the zerospam_block_page_template filter. Escape everything, and keep
 * the form: it is the only way to use the link.
 *
 * $args:
 * - site_name (string) Site name (plain text).
 * - action    (string) Where the form posts (the home page).
 * - field     (string) Name of the token field.
 * - token     (string) The token.
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();
?>
<h1 class="zs-h1"><?php esc_html_e( 'Let me back in.', 'zero-spam' ); ?> <span><?php esc_html_e( 'One click, then log in.', 'zero-spam' ); ?></span></h1>
<p class="zs-lede">
	<?php
	printf(
		/* translators: %s: site name */
		esc_html__( 'This Zero Spam rescue link lets this browser into %s for one hour, so you can log in.', 'zero-spam' ),
		'<strong>' . esc_html( $args['site_name'] ?? '' ) . '</strong>'
	);
	?>
</p>
<form class="zs-actions" method="post" action="<?php echo esc_url( $args['action'] ?? home_url( '/' ) ); ?>">
	<input type="hidden" name="<?php echo esc_attr( $args['field'] ?? '' ); ?>" value="<?php echo esc_attr( $args['token'] ?? '' ); ?>">
	<button class="zs-btn" type="submit"><?php esc_html_e( 'Let me back in', 'zero-spam' ); ?></button>
</form>
<p class="zs-hint"><?php esc_html_e( 'The link works once and expires 15 minutes after it was sent.', 'zero-spam' ); ?></p>
