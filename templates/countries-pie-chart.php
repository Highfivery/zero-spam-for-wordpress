<?php
/**
 * Countries pie chart
 *
 * @package ReferrerAnalytics
 * @since 1.0.0
 */

$chart_limit = 10;
?>
<div class="wpzerospam-box wpzerospam-box-countries-pie">
  <h3><?php _e( 'Most Spam by Country', 'zero-spam' ); ?></h3>
  <div class="inside">
    <?php
    if ( $log ):
      $countries = [];
      foreach( $log as $key => $entry ):

        $k = wpzerospam_get_location( $entry->country );
        if ( empty( $countries[ $k ] ) ):
          $countries[ $k ] = 1;
        else:
          $countries[ $k ]++;
        endif;
      endforeach;

      if ( $countries ):
        arsort( $countries );
      endif;
      ?>
      <canvas id="wpzerospam-pie-countries"></canvas>
      <script>
      <?php
      $labels = [];
      $data   = [];
      $count  = 0;
      foreach( $countries as $key => $value ):
        if ( $count >= $chart_limit ): break; endif;

        $labels[] = $key;
        $data[]   = $value;
        $colors[] = $predefined_colors[ $count ];
        $count++;
      endforeach;
      ?>
      var countries = document.getElementById('wpzerospam-pie-countries');
      var countriesAnalyticsPie = new Chart(countries, {
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
