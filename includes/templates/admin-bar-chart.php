<?php
/**
 * Admin bar chart
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


// Create the datasets to display on the line chart
$labels   = array();
$datasets = array();

// Detections
$datasets['detections'] = array(
	'label'           => __( 'Total', 'zero-spam' ),
	'data'            => array(),
	'borderColor'     => '#3F0008',
	'backgroundColor' => '#3F0008',
	'fill'            => false,
);

for ( $x = 0; $x < 24; $x++ ) {
	$time     = strtotime('-' . $x . ' hour');
	$date_key = gmdate( 'ga', $time );

	$labels[] = $date_key;

	$datasets['detections']['data'][ $date_key ] = 0;

	foreach ( $entries as $key => $entry ) {
		$entry_date_key = gmdate( 'ga', strtotime( $entry['date_recorded'] ) );

		if ( $date_key === $entry_date_key ) {
			// Detections
			if ( empty( $datasets['detections']['data'][ $date_key ] ) ) {
				$datasets['detections']['data'][ $date_key ] = 1;
			} else {
				$datasets['detections']['data'][ $date_key ]++;
			}
		}
	}
}

$labels = array_reverse( $labels );
ksort( $datasets['detections']['data'] );
?>

<canvas id="zerospam-bar-chart"></canvas>
<script>
(function($) {
	$(function() {
		var lineChart = document.getElementById('zerospam-bar-chart');
		var lineChartAnalytics= new Chart(lineChart, {
			type: 'bar',
			data: {
				labels: <?php echo wp_json_encode( $labels ); ?>,
				datasets:[
					<?php foreach ( $datasets as $key => $data ) : ?>
						<?php echo wp_json_encode( $data ); ?>,
					<?php endforeach; ?>
				],
			},
			options: {
				plugins: {
					legend: {
						display: false
					}
				}
			}
		});
	});
})(jQuery);
</script>
