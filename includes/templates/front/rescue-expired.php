<?php
/**
 * "This link has expired": the page a used or expired rescue link opens, with
 * the form to ask for a new one (Access::init()).
 *
 * Rendered by ZeroSpam\Core\Front\Block_Page; a theme can replace this file
 * with the zerospam_block_page_template filter. Escape everything.
 *
 * $args:
 * - rescue (array|null) Rescue form arguments (Rescue_Link::form_args()), or
 *   null when the zerospam_rescue_form_enabled filter turns the form off.
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();
?>
<h1 class="zs-h1"><?php esc_html_e( 'This link has expired.', 'zero-spam' ); ?> <span><?php esc_html_e( 'Ask for a new one.', 'zero-spam' ); ?></span></h1>
<?php if ( ! empty( $args['rescue'] ) ) : ?>
<p class="zs-lede"><?php esc_html_e( 'Rescue links work once and expire 15 minutes after they\'re sent. Ask for a new link below.', 'zero-spam' ); ?></p>
	<?php \ZeroSpam\Core\Front\Block_Page::part( 'rescue-form', $args['rescue'] ); ?>
<?php else : ?>
<p class="zs-lede"><?php esc_html_e( 'Rescue links work once and expire 15 minutes after they\'re sent.', 'zero-spam' ); ?></p>
<?php endif; ?>
