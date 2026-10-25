<?php
/**
 * Blocked page: what a blocked visitor reads (Access::run_block_check()).
 *
 * Also used, without details and the rescue form, by
 * Access::terminate_execution() for other detections. Rendered by
 * ZeroSpam\Core\Front\Block_Page inside its own document; a theme can replace
 * this file with the zerospam_block_page_template filter. Escape everything.
 *
 * $args:
 * - heading    (string)     First line of the heading.
 * - subheading (string)     Second line, in red; may be empty.
 * - message    (string)     The administrator's blocked message (HTML, post allowlist).
 * - details    (array)      Rows of the Details box: label, value, mono (bool).
 * - rescue     (array|null) Rescue form arguments (Rescue_Link::form_args()), or null.
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || die();

$zerospam_subheading = isset( $args['subheading'] ) ? (string) $args['subheading'] : '';
?>
<h1 class="zs-h1"><?php echo esc_html( $args['heading'] ?? '' ); ?><?php echo '' !== $zerospam_subheading ? ' <span>' . esc_html( $zerospam_subheading ) . '</span>' : ''; ?></h1>
<div class="zs-lede"><?php echo wp_kses_post( $args['message'] ?? '' ); ?></div>
<?php if ( ! empty( $args['details'] ) ) : ?>
<section class="zs-card" aria-labelledby="zs-details-title">
	<h2 class="zs-eyebrow" id="zs-details-title"><?php esc_html_e( 'Details', 'zero-spam' ); ?></h2>
	<dl class="zs-dl">
		<?php foreach ( $args['details'] as $zerospam_row ) : ?>
		<dt><?php echo esc_html( $zerospam_row['label'] ); ?></dt>
		<dd<?php echo empty( $zerospam_row['mono'] ) ? '' : ' class="zs-mono"'; ?>><?php echo esc_html( $zerospam_row['value'] ); ?></dd>
		<?php endforeach; ?>
	</dl>
	<p class="zs-hint"><?php esc_html_e( 'Shared networks and VPNs are sometimes flagged by mistake. Trying again from mobile data often works.', 'zero-spam' ); ?></p>
</section>
<?php endif; ?>
<?php
if ( ! empty( $args['rescue'] ) ) {
	\ZeroSpam\Core\Front\Block_Page::part( 'rescue-form', $args['rescue'] );
}
