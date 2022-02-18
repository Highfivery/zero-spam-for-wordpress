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
						__( 'Super-charge WordPress Zero Spam with a <a href="%s" target="_blank" rel="noopener noreferrer"> Zero Spam API License</a>.', 'zero-spam' ),
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
					__( '<p>Enable enhanced protection with a <strong>Zero Spam API license</strong> &mdash; one of the largest, most comprehensive, constantly-growing global malicious IP, email, and username databases available.</p><p><a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> is comprised of a global detection network of over 30,000+ apps and sites that monitor traffic and usage in real-time to detect malicious activity. <a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>Subscribe today</strong></a> for enhanced protection.</p>', 'zero-spam' ),
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
			<p style="margin-top: 30px">
				<a class="button button-primary" href="<?php echo esc_url( ZEROSPAM_URL ); ?>subscribe/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Enable Enhanced Protection', 'zero-spam' ); ?></a>
				<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>?utm_source=wordpress_zero_spam&utm_medium=dashboard_widget&utm_campaign=license" target="_blank" rel="noreferrer noopener" class="button button-secondary"><?php esc_html_e( 'Learn More', 'zero-spam' ); ?></a>
			</p>
		<?php else : ?>
			<h2>
				<?php
				echo sprintf(
					wp_kses(
						/* translators: %s: Zero Spam link */
						__( 'Congratulations, <a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> enhanced protection is enabled!', 'zero-spam' ),
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
					__( '<p><a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> is comprised of a global detection network of over 30,000+ apps and sites that monitor traffic and usage in real-time to detect malicious activity.</p>', 'zero-spam' ),
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
		<ul style="margin-top: 0">
			<?php if ( 'enabled' !== $settings['zerospam']['value'] || empty( $settings['zerospam_license']['value'] ) ) : ?>
				<li style="margin-bottom: 20px;">
					<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>subscribe/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=license" target="_blank">
						<strong><?php esc_html_e( 'Get a Zero Spam API License', 'zero-spam' ); ?></strong>
					</a>
				</li>
			<?php endif; ?>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam/issues" target="_blank"><?php esc_html_e( 'Submit a Bug or Feature Request', 'zero-spam' ); ?></a></li>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam/wiki" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'WordPress Zero Spam Plugin Documentation', 'zero-spam' ); ?></a></li>
			<li><a href="https://github.com/bmarshall511/wordpress-zero-spam" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Become a Contributor &mdash; Fork on Github', 'zero-spam' ); ?></a></li>
			<li><a href="https://twitter.com/ZeroSpamOrg" target="_blank"><?php esc_html_e( 'Follow us on Twitter', 'zero-spam' ); ?></a> &amp; <a href="https://www.facebook.com/zerospamorg/" target="_blank"><?php esc_html_e( 'Facebook', 'zero-spam' ); ?></a></li>
		</ul>
		<hr />
		<?php
		echo sprintf(
			wp_kses(
				/* translators: %s: Zero Spam API link */
				__( '<p><small>WordPress Zero Spam is proudly developed &amp; maintained by <a href="%1$s" target="_blank" rel="noopener noreferrer">Highfivery LLC &mdash; a creative digital agency</a>.</small></p>', 'zero-spam' ),
				array(
					'a'      => array(
						'target' => array(),
						'href'   => array(),
						'rel'    => array(),
					),
					'p'      => array(),
					'strong' => array(),
					'small'  => array(),
				)
			),
			esc_url( 'https://www.highfivery.com/?utm_source=' . get_bloginfo( 'url' ) . '&utm_medium=zerospam_plugin_callout&utm_campaign=zerospam_plugin' )
		);
		?>
	</div>
</div>
