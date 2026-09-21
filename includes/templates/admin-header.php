<?php
/**
 * Admin header
 *
 * @package ZeroSpam
 */
?>

<header class="zerospam-header">
	<img src="<?php echo plugin_dir_url( ZEROSPAM ) . 'assets/img/text-zero-spam.svg'; ?>" width="250" />
	<div class="zerospam-header__status">
		<?php
		$status = \ZeroSpam\Core\Settings::get_settings( 'zerospam' );
		if ( 'enabled' !== $status ) :
			echo '<img src="' . plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-error.svg" class="zerospam-small-icon" />';
			printf(
				wp_kses(
					/* translators: %1s: Replaced with the Zero Spam URL */
					__( '<strong>Enhanced Protection is <a href="%1$s">DISABLED</a>.</strong>', 'zero-spam' ),
					array(
						'h3'     => array(),
						'p'      => array(),
						'a'      => array(
							'href'  => array(),
							'class' => array(),
							'rel'   => array(),
						),
						'strong' => array(),
					)
				),
				admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=zerospam' )
			);
		else :
			$license_key = \ZeroSpam\Core\Settings::get_settings( 'zerospam_license' );
			if ( $license_key ) :
				$license = \ZeroSpam\Modules\Zero_Spam::get_license( $license_key );
				if ( false === $license ) :
					// The API couldn't be reached, which says nothing about the key itself.
					echo '<img src="' . plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-info.svg" class="zerospam-small-icon" />';
					echo '<strong>' . esc_html__( 'Couldn\'t verify your license right now (Zero Spam API unreachable). Protection stays enabled.', 'zero-spam' ) . '</strong>';
				elseif ( empty( $license['license_key'] ) ) :
					echo '<img src="' . plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-error.svg" class="zerospam-small-icon" />';
					printf(
						wp_kses(
							/* translators: %1s: Replaced with the Zero Spam URL */
							__( '<strong>Enhanced Protection is <a href="%1$s">DISABLED</a> (invalid license).</strong>', 'zero-spam' ),
							array(
								'h3'     => array(),
								'p'      => array(),
								'a'      => array(
									'href'  => array(),
									'class' => array(),
									'rel'   => array(),
								),
								'strong' => array(),
							)
						),
						admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=zerospam' )
					);
				elseif ( isset( $license['queries_remaining'] ) && (int) $license['queries_remaining'] <= 0 ) :
					echo '<img src="' . plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-error.svg" class="zerospam-small-icon" />';
					printf(
						wp_kses(
							/* translators: %1$s: Replaced with the Zero Spam pricing URL */
							__( '<strong>License query limit reached.</strong> <a href="%1$s" target="_blank" rel="noopener noreferrer">Upgrade your plan</a> to keep Enhanced Protection running.', 'zero-spam' ),
							array(
								'a'      => array(
									'href'   => array(),
									'target' => array(),
									'rel'    => array(),
								),
								'strong' => array(),
							)
						),
						esc_url( ZEROSPAM_URL . 'pricing/' )
					);
				else :
					echo '<img src="' . plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-success.svg" class="zerospam-small-icon" />';
					printf(
						wp_kses(
							/* translators: %1s: Replaced with the Zero Spam URL */
							__( '<strong>%1s queries available</strong>', 'zero-spam' ),
							array(
								'h3'     => array(),
								'p'      => array(),
								'a'      => array(
									'href'  => array(),
									'class' => array(),
									'rel'   => array(),
								),
								'strong' => array(),
							)
						),
						number_format( $license['queries_remaining'], 0 )
					);
				endif;
			else :
				echo '<img src="' . plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-error.svg" class="zerospam-small-icon" />';
				printf(
					wp_kses(
						/* translators: %1s: Replaced with the Zero Spam URL, %2$s: Replaced with the DDoD attack wiki URL */
						__( '<strong>Enhanced Protection is <a href="%1$s">DISABLED</a>.</strong>', 'zero-spam' ),
						array(
							'h3'     => array(),
							'p'      => array(),
							'a'      => array(
								'href'  => array(),
								'class' => array(),
								'rel'   => array(),
							),
							'strong' => array(),
						)
					),
					admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=zerospam' )
				);
			endif;
		endif;
		?>
	</div>
</header>
