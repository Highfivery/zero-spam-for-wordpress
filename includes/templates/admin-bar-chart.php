<?php
/**
 * Admin bar chart
 *
 * @package ZeroSpam
 */

if ( empty( $entries ) ) {
	printf(
		wp_kses(
			__( 'Nothing to report.', 'zero-spam' ),
			array(
				'strong' => array(),
			)
		)
	);

	return;
}

// Enqueue Chart.js 4.x (modern version)
wp_enqueue_script(
	'zerospam-chartjs',
	'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
	array(),
	'4.4.1',
	true
);

// Create the datasets to display on the bar chart
$labels   = array();
$datasets = array();

// Detections
$datasets['detections'] = array(
	'label'           => __( 'Total', 'zero-spam' ),
	'data'            => array(),
	'borderColor'     => '#3F0008',
	'backgroundColor' => 'rgba(63, 0, 8, 0.8)',
);

for ( $x = 0; $x < 24; $x++ ) {
	$time     = strtotime( '-' . $x . ' hour' );
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
				++$datasets['detections']['data'][ $date_key ];
			}
		}
	}
}

$labels = array_reverse( $labels );
ksort( $datasets['detections']['data'] );
?>

<canvas id="zerospam-bar-chart" style="max-height: 300px;"></canvas>
<script>
(function() {
	const barChart = document.getElementById('zerospam-bar-chart');
	if (barChart) {
		new Chart(barChart, {
			type: 'bar',
			data: {
				labels: <?php echo wp_json_encode( $labels ); ?>,
				datasets: [
					<?php foreach ( $datasets as $key => $data ) : ?>
						<?php echo wp_json_encode( $data ); ?>,
					<?php endforeach; ?>
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 12,
						titleFont: { size: 14, weight: 'bold' },
						bodyFont: { size: 13 }
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0
						},
						grid: {
							color: 'rgba(0, 0, 0, 0.05)'
						}
					},
					x: {
						grid: {
							display: false
						}
					}
				}
			}
		});
	}
})();
</script>
