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
$locations    = array();

foreach ( $entries as $key => $entry ) {
	if ( ! empty( $entry['latitude'] ) && ! empty( $entry['longitude'] ) ) {
		$coords[] = array( $entry['latitude'], $entry['longitude'] );

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

		$locations[] = $name;
	}

	if ( ! empty( $entry['country'] ) ) {

		if ( array_key_exists( $entry['country'], $regions_data ) ) {
			$regions_data[ $entry['country'] ]++;
		} else {
			$regions_data[ $entry['country'] ] = 1;
		}
	}
}

if ( empty( $locations ) ) :
	echo sprintf(
		wp_kses(
			/* translators: %s: url */
			__( 'Current detections have no geolocation information available. Enable ipstack and/or IPinfo on the <a href="%1$s">settings page</a>.', 'zero-spam' ),
			array(
				'a' => array( 'href' => array() ),
			)
		),
		esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ),
	);
	return;
endif;

wp_enqueue_script(
	'zerospam-jvectormap',
	plugins_url( 'assets/js/jquery-jvectormap-2.0.5.min.js', ZEROSPAM ),
	array( 'jquery' ),
	'2.0.5',
	false
);

wp_enqueue_script(
	'zerospam-jvectormap-world',
	plugins_url( 'assets/js/jquery-jvectormap-world-mill.js', ZEROSPAM ),
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
	var names       = <?php echo wp_json_encode( $locations ); ?>;

	$(function() {
		$('#world-map').vectorMap({
			map: 'world_mill',
			backgroundColor: 'transparent',
			markers: coords,
			markerStyle: {
				initial: {
					fill: '#BE0000',
					stroke: '#000000',
					"fill-opacity": 1,
					"stroke-width": 1,
					"stroke-opacity": 0.5,
					r: 3
				},
			},
			regionStyle: {
				initial: {
					fill: '#f1f1f1',
					"fill-opacity": 1,
					stroke: '#ccd0d4',
					"stroke-width": 1,
					"stroke-opacity": 0.5
				},
			},
			series: {
				regions: [{
					values: regionsData,
					scale: ['#FFA17C','#63000D'],
					normalizeFunction: 'polynomial'
				}]
			},
			onMarkerTipShow: function(event, label, index){
				label.html( names[index] );
			},
			onRegionTipShow: function(e, el, code){
				el.html( el.html() + ' (<?php _e( 'Detections', 'zero-spam' ); ?>: ' + regionsData[code] + ')' );
			}
		});
	});
})(jQuery);
</script>
