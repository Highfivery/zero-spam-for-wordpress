<?php
/**
 * Detection details modal
 *
 * @package ZeroSpam
 */

$submission_data = ! empty( $item['submission_data'] ) ? json_decode( $item['submission_data'], true ) : false;
if ( $submission_data ) :
	$submission_data = \ZeroSpam\Core\Utilities::sanitize_array( $submission_data );
	// Remove type, pulled from the log_type column.
	if ( ! empty( $submission_data['type'] ) ) :
		unset( $submission_data['type'] );
	endif;
endif;
?>
<div class="zerospam-modal-details">
	<ul class="zerospam-list zerospam-list--data">
		<li>
			<span class="zerospam-list__label"><?php esc_html_e( 'Date', 'zero-spam' ); ?></span>
			<span class="zerospam-list__value">
				<?php
				echo esc_html(
					gmdate(
						'M j, Y g:ia',
						strtotime( $item['date_recorded'] )
					)
				);
				?>
			</span>
		</li>
		<li>
			<span class="zerospam-list__label"><?php esc_html_e( 'IP Address', 'zero-spam' ); ?></span>
			<span class="zerospam-list__value">
				<?php
				$lookup_url  = ZEROSPAM_URL . 'ip-lookup/';
				$lookup_url .= rawurlencode( $item['user_ip'] ) . '/';
				$lookup_url .= '?utm_source=' . site_url() . '&';
				$lookup_url .= '?utm_medium=wpzerospam_ip_lookup&';
				$lookup_url .= '?utm_campaign=wpzerospam';

				echo sprintf(
					wp_kses(
						/* translators: %1s: Replaced with the IP address, %2$s Replaced with the IP lookup URL */
						__( '%1$s &mdash; <a href="%2$s" target="_blank" rel="noreferrer noopener" class="zerospam-new-window-link">IP Lookup</a>', 'zero-spam' ),
						array(
							'a' => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
								'class'  => array(),
							),
						)
					),
					esc_html( $item['user_ip'] ),
					esc_url( $lookup_url )
				);
				?>
			</span>
		</li>
		<li>
			<span class="zerospam-list__label"><?php esc_html_e( 'Type', 'zero-spam' ); ?></span>
			<span class="zerospam-list__value">
				<?php
				$detection_types = apply_filters( 'zerospam_types', array() );
				if ( ! empty( $detection_types[ $item['log_type'] ] ) ) :
					echo wp_kses(
						$detection_types[ $item['log_type'] ] . ' &mdash; <code>' . $item['log_type'] . '</code>',
						array( 'code' => array() )
					);
				else :
					echo wp_kses( $item['log_type'], array( 'code' => array() ) );
				endif;
				?>
			</span>
		</li>
		<?php if ( $submission_data && ! empty( $submission_data['failed'] ) ) : ?>
			<li>
				<span class="zerospam-list__label"><?php esc_html_e( 'Failed', 'zero-spam' ); ?></span>
				<span class="zerospam-list__value">
					<?php
					$failed_types = apply_filters( 'zerospam_failed_types', array() );
					if ( ! empty( $failed_types[ $submission_data['failed'] ] ) ) :
						echo wp_kses(
							$failed_types[ $submission_data['failed'] ] . ' &mdash; <code>' . $submission_data['failed'] . '</code>',
							array( 'code' => array() )
						);
					else :
						echo wp_kses( $submission_data['failed'], array( 'code' => array() ) );
					endif;
					?>
				</span>
			</li>
			<?php
			unset( $submission_data['failed'] );
		endif;
		?>
	</ul>

	<button class="button zerospam-block-trigger" data-ip="<?php echo esc_attr( $item['user_ip'] ); ?>"><?php esc_html_e( 'Block IP', 'zero-spam' ); ?></button>

	<?php if ( ! empty( $item['latitude'] ) && ! empty( $item['longitude'] ) ) : ?>
		<h4 class="zerospam-modal-headline"><?php esc_html_e( 'Location', 'zero-spam' ); ?></h4>
		<?php
		$coordinates = $item['latitude'] . ',' . $item['longitude'];
		do_action( 'zerospam_google_map', $coordinates );
		?>
		<ul class="zerospam-list zerospam-list--data">
			<?php if ( ! empty( $item['country'] ) ) : ?>
				<li>
					<span class="zerospam-list__label"><?php esc_html_e( 'Country', 'zero-spam' ); ?></span>
					<span class="zerospam-list__value">
						<?php
						$country_name = ! empty( $item['country_name'] ) ? $item['country_name'] : false;
						$flag         = \ZeroSpam\Core\Utilities::country_flag_url( $item['country'] );

						$country = '<img src="' . $flag . '" width="16" height="16" alt="' . esc_attr( $country_name . ' (' . $item['country'] . ')' ) . '" class="zerospam-flag" />';
						if ( $country_name ) {
							$country .= esc_html( $country_name . ' (' . $item['country'] . ')' );
						} else {
							$country .= esc_html( $item['country'] );
						}

						echo wp_kses(
							$country,
							array(
								'img' => array(
									'src'    => array(),
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
					<span class="zerospam-list__label"><?php esc_html_e( 'Region', 'zero-spam' ); ?></span>
					<span class="zerospam-list__value">
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
					<span class="zerospam-list__label"><?php echo esc_html_e( 'City', 'zero-spam' ); ?></span>
					<span class="zerospam-list__value"><?php echo esc_html( $item['city'] ); ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['zip'] ) ) : ?>
				<li>
					<span class="zerospam-list__label"><?php echo esc_html_e( 'Zip/Postal Code', 'zero-spam' ); ?></span>
					<span class="zerospam-list__value"><?php echo esc_html( $item['zip'] ); ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['latitude'] ) || ! empty( $item['region_name'] ) ) : ?>
				<li>
					<span class="zerospam-list__label"><?php echo esc_html_e( 'Coordinates', 'zero-spam' ); ?></span>
					<span class="zerospam-list__value">
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

	<h4 class="zerospam-modal-headline"><?php echo esc_html_e( 'Additional Details', 'zero-spam' ); ?></h4>
	<?php

	if ( $submission_data ) :
		echo '<ul class="zerospam-list zerospam-list--data">';
		foreach ( $submission_data as $key => $value ) :
			?>
			<li>
				<span class="zerospam-list__label"><?php echo esc_html( $key ); ?></span>
				<span class="zerospam-list__value">
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
