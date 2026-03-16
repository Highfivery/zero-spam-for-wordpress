<?php
/**
 * Admin line chart
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

// Create the datasets to display on the line chart
$labels   = array();
$datasets = array();

// Detections
$datasets['detections'] = array(
	'label'           => __( 'Total', 'zero-spam' ),
	'data'            => array(),
	'borderColor'     => '#3F0008',
	'backgroundColor' => 'rgba(63, 0, 8, 0.1)',
	'fill'            => true,
	'tension'         => 0.4,
);

// Create the log types
$all_types = apply_filters( 'zerospam_types', array() );
$types     = array();
foreach ( $entries as $key => $entry ) {
	if ( ! in_array( $entry['log_type'], $types ) ) {
		$types[] = $entry['log_type'];
	}
}

foreach ( $types as $type ) {
	$datasets[ $type ] = array(
		'label'           => ! empty( $all_types[ $type ]['label'] ) ? $all_types[ $type ]['label'] : $type,
		'data'            => array(),
		'borderColor'     => ! empty( $all_types[ $type ]['color'] ) ? $all_types[ $type ]['color'] : '#3F0008',
		'backgroundColor' => ! empty( $all_types[ $type ]['color'] ) ? $all_types[ $type ]['color'] : 'rgba(63, 0, 8, 0.2)',
		'fill'            => false,
		'tension'         => 0.4,
	);
}

for ( $x = 0; $x < 14; $x++ ) {
	$time     = strtotime( '-' . $x . ' days' );
	$date_key = gmdate( 'M. d', $time );

	$labels[] = $date_key;

	$datasets['detections']['data'][ $date_key ] = 0;

	foreach ( $types as $type ) {
		$datasets[ $type ]['data'][ $date_key ] = 0;
	}

	foreach ( $entries as $key => $entry ) {
		$entry_date_key = gmdate( 'M. d', strtotime( $entry['date_recorded'] ) );

		if ( $date_key === $entry_date_key ) {
			// Detections
			if ( empty( $datasets['detections']['data'][ $date_key ] ) ) {
				$datasets['detections']['data'][ $date_key ] = 1;
			} else {
				++$datasets['detections']['data'][ $date_key ];
			}

			// Types
			foreach ( $types as $type ) {
				if ( $type === $entry['log_type'] ) {
					if ( ! $datasets[ $type ]['data'][ $date_key ] ) {
						$datasets[ $type ]['data'][ $date_key ] = 1;
					} else {
						++$datasets[ $type ]['data'][ $date_key ];
					}
				}
			}
		}
	}
}

$labels = array_reverse( $labels );
ksort( $datasets['detections']['data'] );

foreach ( $types as $type ) {
	ksort( $datasets[ $type ]['data'] );
}
?>

<canvas id="zerospam-line-chart" style="max-height: 300px;"></canvas>
<script>
(function() {
	const lineChart = document.getElementById('zerospam-line-chart');
	if (lineChart) {
		new Chart(lineChart, {
			type: 'line',
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
				interaction: {
					intersect: false,
					mode: 'index'
				},
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							boxWidth: 10,
							boxHeight: 10,
							padding: 15,
							usePointStyle: true
						}
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
