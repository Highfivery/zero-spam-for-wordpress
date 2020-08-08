<?php
/**
 * Spam line chart
 *
 * @package ReferrerAnalytics
 * @since 1.0.0
 */
?>
<div class="wpzerospam-box wpzerospam-box-line-chart">
  <h3><?php _e( 'Spam by Date', 'zero-spam' ); ?></h3>
  <div class="inside">
    <?php
    if ( $log ):
      ?>
      <canvas id="wpzerospam-line-chart"></canvas>
      <script>
      <?php
      $parsed = [];
      foreach( $log as $key => $entry ):
        $date_key = date( 'Y-m-d', strtotime( $entry->date_recorded ) );
        if ( empty( $parsed[ $date_key ] ) ) {
          $parsed[ $date_key ] = 1;
        } else {
          $parsed[ $date_key ]++;
        }
      endforeach;

      ksort( $parsed );
      $labels = [];
      $data   = [];
      foreach( $parsed as $date => $count ) {
        $labels[] = date( 'M j, Y', strtotime( $date ) );
        $data[]   = $count;
      }
      ?>
      var lineChart = document.getElementById('wpzerospam-line-chart');
      var lineChartAnalytics= new Chart(lineChart, {
        type: 'line',
        data: {
          labels: <?php echo json_encode( $labels ); ?>,
          datasets: [{
            data: <?php echo json_encode( $data ); ?>,
            backgroundColor: 'rgba(88, 0, 15, 0.5)',
            borderColor: '#63000D',
            borderWidth: 2,
            pointBorderWidth: 2,
            pointBackgroundColor: '#58000f',
            pointRadius: 2,
            fill: false,
          }],
        },
        options: {
          legend: false
        }
      });
      </script>
    <?php else: ?>
      <?php _e( 'No data to report yet.', 'zero-spam' ); ?>
    <?php endif; ?>
  </div>
</div>
