<?php
/**
 * Admin pie chart
 *
 * @package ZeroSpam
 */

if ( empty( $entries ) ) {
	echo sprintf(
		wp_kses(
			__( 'Nothing to report yet.', 'zero-spam' ),
			array(
				'strong' => array(),
			)
		)
	);

	return;
}

$limit = 10;

$predefined_colors = array(
	'#1a0003',
	'#4d000a',
	'#800011',
	'#b30017',
	'#e6001e',
	'#ff1a38',
	'#ff4d64',
	'#ff8090',
	'#ffb3bd',
	'#ffe5e9',
);

$data   = array();
$labels = array();
for ( $x = 0; $x < 14; $x++ ) {
	$time     = strtotime('-' . $x . ' days');
	$date_key = gmdate( 'M. j', $time );

	foreach ( $entries as $key => $entry ) {
		$entry_date_key = gmdate( 'M. j', strtotime( $entry['date_recorded'] ) );

		if ( $date_key === $entry_date_key ) {
			if ( ! empty( $entry['country_name'] ) ) {
				if ( ! in_array( $entry['country_name'], $labels ) ) {
					$labels[] = $entry['country_name'];
				}

				if ( empty( $data[ $entry['country_name'] ] ) ) {
					$data[ $entry['country_name'] ] = 1;
				} else {
					$data[ $entry['country_name'] ]++;
				}
			} else {
				if ( ! in_array( __( 'Unknown', 'zero-spam' ), $labels ) ) {
					$labels[] = __( 'Unknown', 'zero-spam' );
				}

				if ( empty( $data['unknown'] ) ) {
					$data['unknown'] = 1;
				} else {
					$data['unknown']++;
				}
			}
		}
	}
}

wp_enqueue_script(
	'zerospam-chart',
	plugins_url( 'assets/js/Chart.bundle.min.js', ZEROSPAM ),
	array( 'jquery' ),
	'2.9.4',
	false
);

wp_enqueue_style(
	'zerospam-chart',
	plugins_url( 'assets/css/Chart.min.css', ZEROSPAM ),
	array(),
	'2.9.4'
);
?>

<canvas id="zerospam-pie-countries"></canvas>
<script>
(function($) {
	$(function() {
		var countries = document.getElementById('zerospam-pie-countries');
		var countriesAnalyticsPie = new Chart(countries, {
			type: 'pie',
			data: {
				labels: <?php echo wp_json_encode( $labels ); ?>,
				datasets: [{
					data: <?php echo wp_json_encode( array_values( $data ) ); ?>,
					backgroundColor: <?php echo wp_json_encode( $predefined_colors ); ?>,
				}],
			},
			options: {
				plugins: {
					legend: {
						position: 'right',
						labels: {
							boxWidth: 15,
							boxHeight: 15,
						}
					}
				}
			}
		});
	});
})(jQuery);
</script>
