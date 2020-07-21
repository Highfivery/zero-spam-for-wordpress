<?php
/**
 * IP list
 *
 * @package ReferrerAnalytics
 * @since 1.0.0
 */

$chart_limit = 20;
?>
<div class="wpzerospam-box wpzerospam-box-ip-list">
  <h3><?php _e( 'Most Spam by IP Address', 'wpzerospam' ); ?></h3>
  <div class="inside">
    <?php
    if ( $log ):
      $ips = [];
      foreach( $log as $key => $entry ):
        if ( empty( $ips[ $entry->user_ip ] ) ):
          $ips[ $entry->user_ip ] = [
            'count'   => 1,
            'country' => $entry->country
          ];
        else:
          $ips[ $entry->user_ip ]['count']++;
        endif;
      endforeach;

      if ( $ips ):
        arsort( $ips );
      endif;
      ?>
      <ol>
        <?php
        $cnt = 0;
        foreach( $ips as $ip => $ary ):
          $cnt++;
          if ( $cnt > $chart_limit ) { break; }
          ?>
          <li>
            <a href="https://whatismyipaddress.com/ip/<?php echo $ip; ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo $ip; ?></strong></a>
            <?php if ( ! empty( $ary['country'] ) ): ?>
              (<?php echo wpzerospam_get_location( $ary['country'] ); ?>)
            <?php endif; ?>
             &mdash; <?php echo $ary['count']; ?></li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <?php _e( 'No data to report yet.', 'wpzerospam' ); ?>
    <?php endif; ?>
  </div>
</div>
