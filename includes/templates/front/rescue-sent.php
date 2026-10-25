<?php
/**
 * "Check your email": the answer every valid rescue form submission gets,
 * whether or not an email is sent (Rescue_Link::handle_request()).
 *
 * Rendered by ZeroSpam\Core\Front\Block_Page; a theme can replace this file
 * with the zerospam_block_page_template filter. No arguments.
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();
?>
<h1 class="zs-h1"><?php esc_html_e( 'Check your email.', 'zero-spam' ); ?> <span><?php esc_html_e( 'A rescue link is on its way.', 'zero-spam' ); ?></span></h1>
<p class="zs-lede"><?php esc_html_e( 'If that address belongs to a site administrator, a rescue link has been sent.', 'zero-spam' ); ?></p>
<div class="zs-card">
	<p><?php esc_html_e( 'The link works once and expires in 15 minutes. It can take a few minutes to arrive. If it does not arrive, check the spam folder.', 'zero-spam' ); ?></p>
</div>
