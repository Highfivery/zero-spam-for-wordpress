<?php
/**
 * Modal details.
 *
 * @package ZeroSpam
 * @since 5.0.0
 */

?>
<div class="zerospam-modal-details">
	<div class="zerospam-modal-title">
		<h3>ID #<?php echo esc_html( $item['log_id'] ); ?></h3>
	</div>
	<div class="zerospam-modal-subtitle">
		<?php
		echo esc_html(
			gmdate(
				'M j, Y g:ia',
				strtotime( $item['date_recorded'] )
			)
		);
		?>
	</div>

	<ul class="zerospam-modal-list">
		<li>
			<strong><?php esc_html_e( 'IP Address', 'zerospam' ); ?></strong>
			<span>
				<?php
				echo sprintf(
					wp_kses(
						/* translators: %1s: IP URL lookup, %2$2 IP address, %2s: IP address */
						__( '<a href="%1$s" target="_blank" rel="noreferrer noopener">%2$s</a>', 'zerospam' ),
						array(
							'a' => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
							),
						)
					),
					esc_url( ZEROSPAM_URL . 'ip-lookup/' . rawurlencode( esc_html( $item['user_ip'] ) ) ),
					esc_html( $item['user_ip'] )
				);
				?>
			</span>
		</li>
		<li>
			<strong><?php esc_html_e( 'Type', 'zerospam' ); ?></strong>
			<span><?php echo esc_html( $item['log_type'] ); ?></span>
		</li>
	</ul>

	<button class="button action zerospam-block-trigger" data-id="<?php echo esc_attr( $item['log_id'] ); ?>">
		<?php esc_html_e( 'Block IP', 'zerospam' ); ?>
	</button>

	<?php if ( ! empty( $item['latitude'] ) && ! empty( $item['longitude'] ) ) : ?>
		<h4 class="zerospam-modal-headline"><?php esc_html_e( 'Location', 'zerospam' ); ?></h4>
		<?php
		$coordinates = $item['latitude'] . ',' . $item['longitude'];
		do_action( 'zerospam_google_map', $coordinates );
		?>
		<ul class="zerospam-modal-list">
			<?php if ( ! empty( $item['country'] ) ) : ?>
				<li>
					<strong><?php esc_html_e( 'Country', 'zerospam' ); ?></strong>
					<span>
						<?php
						$country_name = ! empty( $item['country_name'] ) ? $item['country_name'] : false;
						$flag         = ZeroSpam\Core\Utilities::country_flag_url( $item['country'] );

						$country = '<img src="' . esc_url( $flag ) . '" width="16" height="16" alt="' . esc_attr( $country_name . ' (' . $item['country'] . ')' ) . '" class="zerospam-flag" />';
						if ( $country_name ) {
							$country .= esc_html( $country_name . ' (' . $item['country'] . ')' );
						} else {
							$country .= esc_html( $item['country'] );
						}

						echo wp_kses(
							$country,
							array(
								'img' => array(
									'width'  => array(),
									'height' => array(),
									'alt'    => array(),
									'class'  => array(),
								),
							)
						);
						?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['region'] ) || ! empty( $item['region_name'] ) ) : ?>
				<li>
					<strong><?php esc_html_e( 'Region', 'zerospam' ); ?></strong>
					<span>
						<?php if ( ! empty( $item['region_name'] ) ) : ?>
							<?php echo esc_html( $item['region_name'] ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $item['region'] ) ) : ?>
							(<?php echo esc_html( $item['region'] ); ?>)
						<?php endif; ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['city'] ) ) : ?>
				<li>
					<strong><?php echo esc_html_e( 'City', 'zerospam' ); ?></strong>
					<span><?php echo esc_html( $item['city'] ); ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['zip'] ) ) : ?>
				<li>
					<strong><?php echo esc_html_e( 'Zip/Postal Code', 'zerospam' ); ?></strong>
					<span><?php echo esc_html( $item['zip'] ); ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['latitude'] ) || ! empty( $item['region_name'] ) ) : ?>
				<li>
					<strong><?php echo esc_html_e( 'Coordinates', 'zerospam' ); ?></strong>
					<span>
						<?php if ( ! empty( $item['latitude'] ) ) : ?>
							<?php echo esc_html( $item['latitude'] ); ?>&deg;,
						<?php endif; ?>
						<?php if ( ! empty( $item['longitude'] ) ) : ?>
							<?php echo esc_html( $item['longitude'] ); ?>&deg;
						<?php endif; ?>
					</span>
				</li>
			<?php endif; ?>
		</ul>
		<?php
	endif;
	?>

	<h4 class="zerospam-modal-headline"><?php echo esc_html_e( 'Additional Details', 'zerospam' ); ?></h4>
	<?php

	if ( ! empty( $item['submission_data'] ) ) :
		// Sanatize the array.
		$submission_data = json_decode( $item['submission_data'], true );
		$submission_data = \ZeroSpam\Core\Utilities::sanitize_array( $submission_data, 'esc_html' );
		echo '<ul class="zerospam-modal-list">';
		foreach ( $submission_data as $key => $value ) :
			?>
			<li>
				<strong><?php echo esc_html( $key ); ?></strong>
				<span>
					<?php
					if ( is_array( $value ) ) :
						// Sanatize the array.
						$value = \ZeroSpam\Core\Utilities::sanitize_array( $value, 'esc_html' );
						?>
						<?php echo wp_json_encode( $value ); ?>
					<?php else : ?>
						<?php echo esc_html( $value ); ?>
					<?php endif; ?>
				</span>
			</li>
			<?php
		endforeach;
		echo '</ul>';
	endif;
	?>
</div>
