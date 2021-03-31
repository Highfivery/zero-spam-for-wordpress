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
					__( 'Support WordPress Zero Spam & <a href="%s" target="_blank" rel="noopener noreferrer">get enhanced protection</a>.', 'zerospam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.zerospam.org/product/premium/' )
			);
			?>
		</h2>
		<p>
			<?php
			echo sprintf(
				wp_kses(
					__( 'Support continued development by <a href="%1$s" target="_blank" rel="noopener noreferrer">donating</a> and subscribing to a <strong><a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam premium license</a> for enhanced protection</strong>. Donations go toward time it takes to develop new features &amp; updates, but also helps provide pro bono work for nonprofits.', 'zerospam' ),
					array(
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
						'strong' => array(),
					)
				),
				esc_url( 'https://benmarshall.me/donate/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=donation' )
			);
			?>
		</p>
		<p>
			<?php
			echo sprintf(
				wp_kses(
					__( '<strong>Use Zero Spam in anything!</strong> Integrate the Zero Spam blacklist in any application with the <a href="%s" target="_blank" rel="noopener noreferrer">Zero Spam Blacklist API</a>.', 'zerospam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://zerospam.org/spam-blacklist-api/' )
			);
			?>
		</p>
	</div>
	<div class="zerospam-callout-col zerospam-callout-actions">
		<ul>
			<li><a href="https://www.zerospam.org/product/premium/" target="_blank"><?php _e( 'Get a Zero Spam Premium License', 'zerospam' ); ?></a></li>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam/issues" target="_blank"><?php _e( 'Submit Bug/Feature Request', 'zerospam' ); ?></a></li>
			<li><a href="https://twitter.com/ZeroSpamOrg" target="_blank"><?php _e( 'Follow us on Twitter', 'zerospam' ); ?></a> &amp; <a href="https://www.facebook.com/zerospamorg/" target="_blank"><?php _e( 'Facebook', 'zerospam' ); ?></a></li>
			<li><a href="https://benmarshall.me/donate?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=admin" target="_blank"><?php _e( 'Show your Support &mdash; Donate', 'zerospam' ); ?></a></li>
		</ul>
	</div>
</div>
