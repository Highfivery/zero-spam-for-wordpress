<?php
/**
 * Admin pie chart
 *
 * @package ZeroSpam
 */

if ( empty( $entries ) ) {
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

$countries = array();
foreach ( $entries as $key => $entry ) :

	if ( empty( $entry['country'] ) ) :
		continue;
	endif;

	$k = ZeroSpam\Core\Utilities::countries( $entry['country'] );

	if ( empty( $countries[ $k ] ) ) :
		$countries[ $k ] = 1;
	else :
		$countries[ $k ]++;
	endif;
endforeach;

if ( $countries ) :
	arsort( $countries );
endif;

$labels = array();
$data   = array();
$colors = array();
$count  = 0;
foreach ( $countries as $key => $value ) :
	if ( $count >= $limit ) :
		break;
	endif;

	$labels[] = $key;
	$data[]   = $value;
	$colors[] = $predefined_colors[ $count ];
	$count++;
endforeach;
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
					data: <?php echo wp_json_encode( $data ); ?>,
					backgroundColor: <?php echo wp_json_encode( $colors ); ?>,
					borderWidth: 0,
					borderColor: '#f1f1f1'
				}],
			},
			options: {
				legend: {
					position: 'right',
					fullWidth: false
				}
			}
		});
	});
})(jQuery);
</script>
