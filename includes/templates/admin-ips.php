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

$data = array();
for ( $x = 0; $x < 30; $x++ ) {
	$time     = strtotime('-' . $x . ' days');
	$date_key = gmdate( 'M. j', $time );

	foreach ( $entries as $key => $entry ) {
		$entry_date_key = gmdate( 'M. j', strtotime( $entry['date_recorded'] ) );

		if ( $date_key === $entry_date_key ) {
			if ( empty( $data[ $entry['user_ip'] ] ) ) {
				$data[ $entry['user_ip'] ] = array(
					'count' => 1,
				);
			} else {
				$data[ $entry['user_ip'] ]['count']++;
			}

			if ( ! empty( $entry['country'] ) ) {
				$data[ $entry['user_ip'] ]['country'] = $entry['country'];
			}
		}
	}
}

uasort($data, function($a, $b){
	return $b['count'] <=> $a['count'];
});

array_splice( $data, 12 );
?>
<ul class="zerospam-list zerospam-list--top">
	<?php
	$limit = 12;
	$cnt   = 0;
	foreach ( $data as $ip => $info ) :
		$cnt++;
		if ( $cnt > $limit ) {
			break;
		}

		$blocked = ZeroSpam\Includes\DB::blocked( $ip );
		?>
		<li class="zerospam-list__item<?php if ( $blocked ) : ?> zerospam-list__item--blocked<?php endif; ?>">
			<span class="zerospam-list__value zerospam-list__value--label">
			<?php if ( $blocked ) : ?><span class="zerospam-tag"><?php _e( 'Blocked', 'zero-spam' ); ?></span><?php endif; ?>
				<?php if ( ! empty( $info['country'] ) ) : ?>
					<img
						src="<?php echo esc_url( ZeroSpam\Core\Utilities::country_flag_url( $info['country'] ) ); ?>"
						alt="<?php echo esc_attr( $info['country'] ); ?>"
						class="zerospam-flag"
						width="14"
						height="14"
						title="<?php echo esc_attr( \ZeroSpam\Core\Utilities::countries( $info['country'] ) ); ?>"
					/>
				<?php endif; ?>
				<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>ip-lookup/<?php echo urlencode( $ip ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $ip ); ?>
				</a>
			</span>
			<span class="zerospam-list__value zerospam-list__value--count"><?php echo number_format( $info['count'], 0 ); ?></span>
			<span class="zerospam-list__value zerospam-list__value--actions">
				<?php
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
						<?php _e( 'Edit Block', 'zero-spam' ); ?>
					</button>
					<?php
				else :
					?>
					<button class="button zerospam-block-trigger" data-ip="<?php echo esc_attr( $ip ); ?>"><?php _e( 'Block', 'zero-spam' ); ?></button>
					<?php
				endif;
				?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
