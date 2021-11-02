<?php
/**
 * Callout.
 *
 * @package ZeroSpam
 * @since 5.0.0
 */

$settings = \ZeroSpam\Core\Settings::get_settings();
?>

<div class="zerospam-callout">
	<div class="zerospam-callout-col">
		<?php if ( 'enabled' !== $settings['zerospam']['value'] || empty( $settings['zerospam_license']['value'] ) ) : ?>
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
					esc_url( ZEROSPAM_URL . 'subscribe/' )
				);
				?>
			</h2>
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
				esc_url( ZEROSPAM_URL . '?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license' ),
				esc_url( ZEROSPAM_URL . 'subscribe/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license' )
			);
			?>
			<a class="button button-primary" href="<?php echo esc_url( ZEROSPAM_URL ); ?>subscribe/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Enable Enhanced Protection', 'zerospam' ); ?></a>
			<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>?utm_source=wordpress_zero_spam&utm_medium=dashboard_widget&utm_campaign=license" target="_blank" rel="noreferrer noopener" class="button button-secondary"><?php esc_html_e( 'Learn More', 'zerospam' ); ?></a>
		<?php else : ?>
			<h2>
				<?php
				echo sprintf(
					wp_kses(
						/* translators: %s: Zero Spam link */
						__( 'Congratulations, <a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> enhanced protection is enabled!', 'zerospam' ),
						array(
							'a' => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
							),
						)
					),
					esc_url( ZEROSPAM_URL . '?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license' )
				);
				?>
			</h2>
			<?php
			echo sprintf(
				wp_kses(
					/* translators: %s: Zero Spam API link */
					__( '<p><a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> is comprised of a global detection network of over 30,000+ apps and sites that monitor traffic and usage in real-time to detect malicious activity.</p>', 'zerospam' ),
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
				esc_url( ZEROSPAM_URL . '?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license' )
			);
			?>
		<?php endif; ?>
	</div>
	<div class="zerospam-callout-col zerospam-callout-actions">
		<ul>
			<?php if ( 'enabled' !== $settings['zerospam']['value'] || empty( $settings['zerospam_license']['value'] ) ) : ?>
				<li><a href="<?php echo esc_url( ZEROSPAM_URL ); ?>subscribe/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license" target="_blank"><?php esc_html_e( 'Get a Zero Spam API License', 'zerospam' ); ?></a></li>
			<?php endif; ?>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam/issues" target="_blank"><?php esc_html_e( 'Submit a Bug or Feature Request', 'zerospam' ); ?></a></li>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam/wiki" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'WordPress Zero Spam Plugin Documentation', 'zerospam' ); ?></a></li>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Become a Contributor &mdash; Fork on Github', 'zerospam' ); ?></a></li>
			<li><a href="https://twitter.com/ZeroSpamOrg" target="_blank"><?php _e( 'Follow us on Twitter', 'zerospam' ); ?></a> &amp; <a href="https://www.facebook.com/zerospamorg/" target="_blank"><?php esc_html_e( 'Facebook', 'zerospam' ); ?></a></li>
		</ul>
	</div>
</div>
