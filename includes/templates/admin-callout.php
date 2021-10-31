<?php
/**
 * Callout.
 *
 * @package ZeroSpam
 * @since 5.0.0
 */

?>

<div class="zerospam-callout">
	<div class="zerospam-callout-col">
		<h2>
			<?php
			echo sprintf(
				wp_kses(
					/* translators: %s: Zero Spam API link */
					__( 'Super-charge WordPress Zero Spam with a <a href="%s" target="_blank" rel="noopener noreferrer"> Zero Spam API License</a>.', 'zerospam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.zerospam.org/subscribe/' )
			);
			?>
		</h2>
		<p>
			<?php
			echo sprintf(
				wp_kses(
					/* translators: %s: Zero Spam API link */
					__( '<p><strong>Is some spam still getting through?</strong> Enable enhanced protection with a <strong>Zero Spam API license</strong> &mdash; one of the largest, most comprehensive, up-to-date IP, username, and email blacklist databases available.</p><p><a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> is comprised of a global detection network of over 30,000+ apps and sites that monitor traffic and usage in real-time to detect malicious activity. <a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>Subscribe today</strong></a> for enhanced protection.</p>', 'zerospam' ),
					array(
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
						'p'      => array(),
						'strong' => array(),
					)
				),
				esc_url( 'https://www.zerospam.org/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license' ),
				esc_url( 'https://www.zerospam.org/subscribe/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license' )
			);
			?>
		</p>
	</div>
	<div class="zerospam-callout-col zerospam-callout-actions">
		<ul>
			<li><a href="https://www.zerospam.org/subscribe/" target="_blank"><?php esc_html_e( 'Get a Zero Spam API License', 'zerospam' ); ?></a></li>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam/issues" target="_blank"><?php esc_html_e( 'Submit a Bug or Feature Request', 'zerospam' ); ?></a></li>
			<li><a href="https://twitter.com/ZeroSpamOrg" target="_blank"><?php _e( 'Follow us on Twitter', 'zerospam' ); ?></a> &amp; <a href="https://www.facebook.com/zerospamorg/" target="_blank"><?php esc_html_e( 'Facebook', 'zerospam' ); ?></a></li>
		</ul>
	</div>
</div>
