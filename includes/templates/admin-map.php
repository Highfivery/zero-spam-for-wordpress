<?php
/**
 * World map
 *
 * @package ZeroSpam
 */

if ( empty( $entries ) ) {
	echo sprintf(
		wp_kses(
			__( '<strong>Good news!</strong> There haven\'t been any detections of malicious or spammy IPs yet.', 'zero-spam' ),
			array(
				'strong' => array(),
			)
		)
	);

	return;
}

$regions_data = array();
$coords       = array();
$coords_data  = array();
$locations    = array();

foreach ( $entries as $key => $entry ) {
	if ( ! empty( $entry['latitude'] ) && ! empty( $entry['longitude'] ) ) {
		$name = '';
		if ( ! empty( $entry['city'] ) ) {
			$name .= $entry['city'];
		}

		if ( ! empty( $entry['region'] ) ) {
			if ( $name ) {
				$name .= ', ';
			}
			$name .= $entry['region'];
		}

		if ( ! empty( $entry['country'] ) ) {
			if ( $name ) {
				$name .= ', ';
			}
			$name .= $entry['country'];
		}

		if ( ! $name ) {
			$name = __( 'Unknown', 'zero-spam' );
		}

		$coord_key = $entry['latitude'] . $entry['longitude'];
		if ( empty( $coords[ $coord_key ] ) ) {
			$coords_data[ $coord_key ] = 1;
			$coords[ $coord_key ] = array(
				'latLng' => array(
					$entry['latitude'],
					$entry['longitude']
				)
			);
			$locations[ $coord_key ] = array(
				'name'  => $name,
				'count' => 0,
			);
		} else {
			$coords_data[ $coord_key ]++;
			$locations[ $coord_key ]['count']++;
		}
	}

	if ( ! empty( $entry['country'] ) ) {

		if ( array_key_exists( $entry['country'], $regions_data ) ) {
			$regions_data[ $entry['country'] ]++;
		} else {
			$regions_data[ $entry['country'] ] = 1;
		}
	}
}

$locations_data = array();
foreach ( $locations as $key => $loc ) {
	$locations_data[ $key ] = $loc['name'] . ': ' . $loc['count'];
}

wp_enqueue_script(
	'zerospam-jvectormap',
	plugins_url( 'assets/js/jquery-jvectormap-2.0.5.min.js', ZEROSPAM ),
	array( 'jquery' ),
	'2.0.5',
	false
);

wp_enqueue_script(
	'zerospam-jvectormap-world',
	plugins_url( 'assets/js/jquery-jvectormap-world-merc.js', ZEROSPAM ),
	array( 'zerospam-jvectormap' ),
	'2.0.5',
	false
);

wp_enqueue_style(
	'zerospam-jvectormap-world',
	plugins_url( 'assets/css/jquery-jvectormap-2.0.5.css', ZEROSPAM ),
	array(),
	'2.0.5'
);
?>

<div id="world-map" style="width: 100%; height: 490px"></div>
<script>
(function($) {
	var regionsData = <?php echo wp_json_encode( $regions_data ); ?>;
	var coords      = <?php echo wp_json_encode( $coords ); ?>;
	var names       = <?php echo wp_json_encode( $locations_data ); ?>;

	$(function() {
		$('#world-map').vectorMap({
			map: 'world_merc',
			backgroundColor: 'transparent',
			markers: coords,
			markerStyle: {
				initial: {
					fill: '#be0000',
					stroke: '#fff',
					"fill-opacity": 1,
					"stroke-width": 2,
					"stroke-opacity": 1,
					r: 2
				},
			},
			regionStyle: {
				initial: {
					fill: '#e7e7e7',
					"fill-opacity": 1,
					stroke: '#fff',
					"stroke-width": 1,
					"stroke-opacity": 1
				},
			},
			series: {
				markers: [{
					attribute: 'r',
					scale: [5, 15],
					values: <?php echo wp_json_encode( $coords_data ); ?>
				}],
				regions: [{
					values: regionsData,
					scale: ['#ffe2e2','#ff2929'],
					normalizeFunction: 'polynomial'
				}]
			},
			onRegionTipShow: function(e, el, code) {
				if ( regionsData[code] ) {
					el.html( el.html() + ': ' + regionsData[code] );
				} else {
					el.html( el.html() );
				}
			},
			onMarkerTipShow: function(e, tip, code) {
				tip.html( names[code] );
			},
		});
	});
})(jQuery);
</script>
