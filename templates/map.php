<?php
/**
 * World map
 *
 * @package WordPressZeroSpam
 * @since 1.0.0
 */
$options = wpzerospam_options();
?>
<div class="wpzerospam-box wpzerospam-box-map">
  <h3><?php _e( 'Spam Detections World Map', 'zero-spam' ); ?></h3>
  <div class="inside">
    <?php if ( $options['ipstack_api'] ): ?>
      <?php
      $regions_data = [];
      $coords       = [];
      $names        = [];
      foreach( $log as $key => $entry ):
        if ( ! empty( $entry->latitude ) && ! empty( $entry->longitude ) ) {
          $coords[] = [ $entry->latitude, $entry->longitude ];

          $name = '';

          if ( ! empty(  $entry->city ) ) {
            $name .= $entry->city;
          }

          if ( ! empty(  $entry->region ) ) {
            if ( $name ) { $name .= ', '; }
            $name .= $entry->region;
          }

          if ( ! empty(  $entry->country ) ) {
            if ( $name ) { $name .= ', '; }
            $name .= $entry->country;
          }

          $names[] = $name;
        }

        if ( ! empty( $entry->country ) ) {

          if ( array_key_exists( $entry->country, $regions_data ) ) {
            $regions_data[ $entry->country ]++;
          } else {
            $regions_data[ $entry->country ] = 1;
          }

        }
      endforeach;

      $regions_data = json_encode( $regions_data );
      $coords       = json_encode( $coords );
      $names        = json_encode( $names );
      ?>
      <div id="world-map" style="width: 100%; height: 490px"></div>
      <script>
      jQuery( function() {
        var regionsData = <?php echo $regions_data; ?>;
        var coords      = <?php echo $coords; ?>;
        var names       = <?php echo $names; ?>;

        jQuery( '#world-map' ).vectorMap({
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
              fill: 'transparent',
              "fill-opacity": 1,
              stroke: '#63000D',
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
            el.html( el.html() + ' (<?php _e( 'Spam Detections', 'zero-spam' ); ?>: ' + regionsData[code] + ')' );
          }
        });
      });
      </script>
    <?php else: ?>
      <p><?php
      echo sprintf(
        wp_kses(
          __( '<strong>Enter your <a href="%s" target="_blank" rel="noopener noreferrer">ipstack API Key</a></strong> to enable the world mp view of spam detections.', 'zero-spam' ),
          [ 'strong' => [], 'a' => [ 'target' => [], 'href' => [], 'rel' => [] ] ]
        ),
        admin_url( 'admin.php?page=wordpress-zero-spam-settings' )
      );
      ?></p>
    <?php endif; ?>
  </div>
</div>
