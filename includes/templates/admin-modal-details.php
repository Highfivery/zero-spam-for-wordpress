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
		<h3>ID #<?php echo $item['log_id']; ?></h3>
	</div>
	<div class="zerospam-modal-subtitle">
		<?php echo gmdate( 'M j, Y g:ia' , strtotime( $item[ 'date_recorded' ] ) ); ?>
	</div>

	<ul class="zerospam-modal-list">
		<li>
			<strong><?php echo __( 'IP Address', 'zerospam' ); ?></strong>
			<span><?php echo '<a href="' . ZEROSPAM_URL . 'ip-lookup/' . urlencode( $item['user_ip'] ) .'" target="_blank" rel="noopener noreferrer">' . $item['user_ip'] . '</a>'; ?></span>
		</li>
		<li>
			<strong><?php echo __( 'Type', 'zerospam' ); ?></strong>
			<span><?php echo $item['log_type']; ?></span>
		</li>
	</ul>

	<button class="button action zerospam-block-trigger" data-id="<?php echo esc_attr( $item['log_id'] ); ?>"><?php _e( 'Block IP', 'zerospam' ); ?></button>

	<?php
	if ( ! empty( $item['latitude'] ) && ! empty( $item['longitude'] ) ) {
		?>
		<h4 class="zerospam-modal-headline"><?php echo __( 'Location', 'zerospam' ); ?></h4>
		<?php
		$coordinates = $item['latitude'] . ',' . $item['longitude'];
		do_action( 'zerospam_google_map', $coordinates );
		?>
		<ul class="zerospam-modal-list">
			<?php if ( ! empty( $item['country'] ) ) : ?>
				<li>
					<strong><?php echo __( 'Country', 'zerospam' ); ?></strong>
					<span>
						<?php
						$country_name = ! empty( $item['country_name'] ) ? $item['country_name'] : false;
						$flag         = ZeroSpam\Core\Utilities::country_flag_url( $item['country'] );

						$country = '<img src="' . $flag . '" width="16" height="16" alt="' . esc_attr( $country_name . ' (' . $item['country'] . ')' ) . '" class="zerospam-flag" />';
						if ( $country_name ) {
							$country .= $country_name . ' (' . $item['country'] . ')';
						} else {
							$country .= $item['country'];
						}

						echo $country;
						?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['region'] ) || ! empty( $item['region_name'] ) ) : ?>
				<li>
					<strong><?php echo __( 'Region', 'zerospam' ); ?></strong>
					<span>
						<?php if ( ! empty( $item['region_name'] ) ) : ?>
							<?php echo $item['region_name']; ?>
						<?php endif; ?>
						<?php if ( ! empty( $item['region'] ) ) : ?>
							(<?php echo $item['region']; ?>)
						<?php endif; ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['city'] ) ) : ?>
				<li>
					<strong><?php echo __( 'City', 'zerospam' ); ?></strong>
					<span><?php echo $item['city']; ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['zip'] ) ) : ?>
				<li>
					<strong><?php echo __( 'Zip/Postal Code', 'zerospam' ); ?></strong>
					<span><?php echo $item['zip']; ?>
					</span>
				</li>
			<?php endif; ?>
			<?php if ( ! empty( $item['latitude'] ) || ! empty( $item['region_name'] ) ) : ?>
				<li>
					<strong><?php echo __( 'Coordinates', 'zerospam' ); ?></strong>
					<span>
						<?php if ( ! empty( $item['latitude'] ) ) : ?>
							<?php echo $item['latitude']; ?>&deg;,
						<?php endif; ?>
						<?php if ( ! empty( $item['longitude'] ) ) : ?>
							<?php echo $item['longitude']; ?>&deg;
						<?php endif; ?>
					</span>
				</li>
			<?php endif; ?>
		</ul>
		<?php
	}
	?>

	<h4 class="zerospam-modal-headline"><?php echo __( 'Additional Details', 'zerospam' ); ?></h4>
	<?php

	if ( ! empty( $item['submission_data'] ) ) :
		$submission_data = json_decode( $item['submission_data'], true );
		echo '<ul class="zerospam-modal-list">';
		foreach ( $submission_data as $key => $value ) :
			?>
			<li>
				<strong><?php echo $key; ?></strong>
				<span>
					<?php if ( is_array( $value ) ) : ?>
						<?php echo wp_json_encode( $value ); ?>
					<?php else : ?>
						<?php echo $value; ?>
					<?php endif; ?>
				</span>
			</li>
			<?php
		endforeach;
		echo '</ul>';
	endif;
	?>
</div>
