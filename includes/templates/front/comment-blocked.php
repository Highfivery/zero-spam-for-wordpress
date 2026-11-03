<?php
/**
 * "Comment not posted": the page a comment flagged as spam gets (403).
 *
 * Rendered by ZeroSpam\Core\Front\Block_Page; a theme can replace this file
 * with the zerospam_block_page_template filter. Escape everything.
 *
 * $args:
 * - message  (string) The comment spam message (HTML, post allowlist).
 * - back_url (string) The post's comment form.
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();
?>
<h1 class="zs-h1"><?php esc_html_e( 'Comment not posted.', 'zero-spam' ); ?> <span><?php esc_html_e( 'It was flagged as spam.', 'zero-spam' ); ?></span></h1>
<div class="zs-lede"><?php echo wp_kses_post( $args['message'] ?? '' ); ?></div>
<div class="zs-card">
	<p class="zs-hint"><?php esc_html_e( 'If you\'re a real person, go back, check your comment and try again. Links and some words can trigger the filter.', 'zero-spam' ); ?></p>
	<p><a class="zs-btn zs-btn-secondary" href="<?php echo esc_url( $args['back_url'] ?? home_url( '/' ) ); ?>"><?php echo \ZeroSpam\Core\Front\Block_Page::icon( 'back', 'zs-icon-sm zs-flip' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from Block_Page::ICONS. ?><?php esc_html_e( 'Go back', 'zero-spam' ); ?></a></p>
</div>
