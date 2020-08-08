<?php
/**
 * Regions pie chart
 *
 * @package ReferrerAnalytics
 * @since 1.0.0
 */

$chart_limit = 10;
?>
<div class="wpzerospam-box wpzerospam-box-countries-pie">
  <h3><?php _e( 'Most Spam by Region', 'zero-spam' ); ?></h3>
  <div class="inside">
    <?php
    if ( $log ):
      $regions = [];
      foreach( $log as $key => $entry ):
        $region = wpzerospam_get_location( $entry->country, $entry->region );
        if ( $region ) {
          if ( empty( $regions[ $region ] ) ) {
            $regions[ $region ] = 1;
          } else {
            $regions[ $region ]++;
          }
        } else {
          if ( empty( $entry->region ) ) {
            if ( empty( $regions['N/A'] ) ) {
              $regions['N/A'] = 1;
            } else {
              $regions['N/A']++;
            }
          } else {
            if ( empty( $regions[ $entry->region ] ) ) {
              $regions[ $entry->region ] = 1;
            } else {
              $regions[ $entry->region ]++;
            }
          }
        }
      endforeach;

      if ( $regions ):
        arsort( $regions );
      endif;
      ?>
      <canvas id="wpzerospam-pie-regions"></canvas>
      <script>
      <?php
      $labels = [];
      $data   = [];
      $count  = 0;
      foreach( $regions as $key => $value ):
        if ( $count >= $chart_limit ): break; endif;

        $labels[] = $key;
        $data[]   = $value;
        $colors[] = $predefined_colors[ $count ];
        $count++;
      endforeach;
      ?>
      var regions = document.getElementById('wpzerospam-pie-regions');
      var regionsAnalyticsPie = new Chart(regions, {
        type: 'pie',
        data: {
          labels: <?php echo json_encode( $labels ); ?>,
          datasets: [{
            data: <?php echo json_encode( $data ); ?>,
            backgroundColor: <?php echo json_encode( $colors ); ?>,
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
      </script>
    <?php else: ?>
      <?php _e( 'No data to report yet.', 'zero-spam' ); ?>
    <?php endif; ?>
  </div>
</div>
