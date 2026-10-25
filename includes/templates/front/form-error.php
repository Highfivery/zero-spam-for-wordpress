<?php
/**
 * Rescue form error: the blocked page's copy with the rescue form open and the
 * error tied to the email field (Rescue_Link::handle_request()).
 *
 * Rendered by ZeroSpam\Core\Front\Block_Page; a theme can replace this file
 * with the zerospam_block_page_template filter. Escape everything.
 *
 * $args:
 * - message (string) The administrator's blocked message (HTML, post allowlist).
 * - rescue  (array)  Rescue form arguments with `error` and `value` (Rescue_Link::form_args()).
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();
?>
<h1 class="zs-h1"><?php esc_html_e( 'Blocked.', 'zero-spam' ); ?> <span><?php esc_html_e( 'Your connection was flagged.', 'zero-spam' ); ?></span></h1>
<div class="zs-lede"><?php echo wp_kses_post( $args['message'] ?? '' ); ?></div>
<?php
if ( ! empty( $args['rescue'] ) ) {
	\ZeroSpam\Core\Front\Block_Page::part( 'rescue-form', $args['rescue'] );
}
