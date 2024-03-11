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
		'backgroundColor' => ! empty( $all_types[ $type ]['color'] ) ? $all_types[ $type ]['color'] : '#3F0008',
		'fill'            => false,
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

<canvas id="zerospam-line-chart"></canvas>
<script>
(function($) {
	$(function() {
		var lineChart = document.getElementById('zerospam-line-chart');
		var lineChartAnalytics= new Chart(lineChart, {
			type: 'line',
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
						position: 'bottom',
						labels: {
							boxWidth: 10,
							boxHeight: 10,
							padding: 15,
						}
					}
				}
			}
		});
	});
})(jQuery);
</script>
