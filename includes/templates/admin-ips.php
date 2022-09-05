<?php
/**
 * IPs list
 *
 * @package ZeroSpam
 */

if ( empty( $entries ) ) {
	echo sprintf(
		wp_kses(
			__( 'Nothing to report.', 'zero-spam' ),
			array(
				'strong' => array(),
			)
		)
	);

	return;
}

$ips = array();
foreach ( $entries as $key => $entry ) {
	if ( empty( $ips[ $entry['user_ip'] ] ) ) {
		$ips[ $entry['user_ip'] ] = array(
			'count'   => 1,
			'country' => ! empty( $entry['country'] ) ? $entry['country'] : false,
		);
	} else {
		$ips[ $entry['user_ip'] ]['count']++;
	}
}

if ( $ips ) {
	arsort( $ips );
}
?>
<ul class="zerospam-list zerospam-list--data-actions">
	<?php
	$limit = 12;
	$cnt   = 0;
	foreach ( $ips as $ip => $data ) :
		$cnt++;
		if ( $cnt > $limit ) {
			break;
		}
		?>
		<li>
			<span class="zerospam-list__value">
				<?php if ( ! empty( $data['country'] ) ) : ?>
					<img
						src="<?php echo esc_url( ZeroSpam\Core\Utilities::country_flag_url( $data['country'] ) ); ?>"
						alt="<?php echo esc_attr( $data['country'] ); ?>"
						class="zerospam-flag"
						width="14"
						height="14"
					/>
				<?php endif; ?>
				<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>ip-lookup/<?php echo urlencode( $ip ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $ip ); ?>
				</a>
			</span>
			<span class="zerospam-list__value">
				<?php if ( ! empty( $data['country'] ) ) : ?>
					<?php echo ZeroSpam\Core\Utilities::countries( $data['country'] ); ?>
				<?php else: ?>
					<?php esc_html_e( 'Unknown', 'zero-spam' ); ?>
				<?php endif; ?>
				</span>
			<span class="zerospam-list__value"><?php echo number_format( $data['count'], 0 ); ?></span>
			<span class="zerospam-list__value">
				<?php
				$blocked = ZeroSpam\Includes\DB::blocked( $ip );
				if ( $blocked ) :
					?>
					<button
						class="button zerospam-block-trigger"
						data-ip="<?php echo esc_attr( $ip ); ?>"
						data-reason="<?php echo esc_attr( $blocked['reason'] ); ?>"
						data-start="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $blocked['start_block'] ) ) ); ?>T<?php echo esc_attr( gmdate( 'H:i', strtotime( $blocked['start_block'] ) ) ); ?>"
						data-end="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $blocked['end_block'] ) ) ); ?>T<?php echo esc_attr( gmdate( 'H:i', strtotime( $blocked['end_block'] ) ) ); ?>"
						data-type="<?php echo esc_attr( $blocked['blocked_type'] ); ?>"
						aria-label="<?php esc_html_e( 'Update Block', 'zero-spam' ); ?>"
					>
						<img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-edit.svg" width="13" />
					</button>
					<?php
				else :
					?>
					<button class="button zerospam-block-trigger" data-ip="<?php echo esc_attr( $ip ); ?>" aria-label="<?php esc_html_e( 'Block IP', 'zero-spam' ); ?>"><img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-blocked.svg" width="13" /></button>
					<?php
				endif;
				?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
