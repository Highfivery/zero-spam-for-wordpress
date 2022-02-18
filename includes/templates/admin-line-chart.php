<?php
/**
 * Admin line chart
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

$data = array();
foreach ( $entries as $key => $entry ) :
	$date_key = gmdate( 'Y-m-d', strtotime( $entry['date_recorded'] ) );
	if ( empty( $data[ $date_key ] ) ) {
		$data[ $date_key ] = 1;
	} else {
		$data[ $date_key ]++;
	}
endforeach;

ksort( $data );

$labels     = array();
$chart_data = array();
foreach ( $data as $date => $count ) {
	$labels[]     = gmdate( 'M j, Y', strtotime( $date ) );
	$chart_data[] = $count;
}
?>

<canvas id="zerospam-line-chart"></canvas>
<script>
(function($) {
	$(function() {
		var lineChart = document.getElementById('zerospam-line-chart');
		var lineChartAnalytics= new Chart(lineChart, {
			type: 'line',
			data: {
				labels: <?php echo wp_json_encode( $labels ); ?>,
				datasets: [{
					label: '<?php esc_html_e( 'Number of Detections', 'zero-spam' ); ?>',
					data: <?php echo wp_json_encode( $chart_data ); ?>,
					backgroundColor: 'rgba(88, 0, 15, 0.5)',
					borderColor: '#63000D',
					borderWidth: 2,
					pointBorderWidth: 2,
					pointBackgroundColor: '#58000f',
					pointRadius: 2,
					fill: false,
				}],
			},
			options: {
				legend: false
			}
		});
	});
})(jQuery);
</script>
