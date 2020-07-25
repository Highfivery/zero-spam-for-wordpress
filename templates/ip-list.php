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
      <ol class="wpzerospam-list">
        <?php
        $cnt = 0;
        foreach( $ips as $ip => $ary ):
          $cnt++;
          if ( $cnt > $chart_limit ) { break; }
          ?>
          <li>
            <?php if ( ! empty( $ary['country'] ) ): ?>
              <img class="wpzerospam-country-flag" width="16" src="https://hatscripts.github.io/circle-flags/flags/<?php echo strtolower( $ary['country'] ); ?>.svg" alt="<?php echo wpzerospam_get_location( $ary['country'] ); ?>" />
            <?php endif; ?>
            <a href="https://whatismyipaddress.com/ip/<?php echo $ip; ?>" target="_blank" rel="noopener noreferrer" class="wpzerospam-list-cell"><strong><?php echo $ip; ?></strong></a>
            <span class="wpzerospam-list-cell">
            <?php if ( ! empty( $ary['country'] ) ): ?>
              <?php echo wpzerospam_get_location( $ary['country'] ); ?>
            <?php endif; ?>
            </span>
            <span class="wpzerospam-list-cell-small">
              <?php echo $ary['count']; ?>
            </span>
            <span class="wpzerospam-action">
              <?php
              $blocked_status = wpzerospam_get_blocked_ips( $ip );
              if ( $blocked_status && wpzerospam_is_blocked( $blocked_status ) ):
              ?>
                <span class="wpzerospam-blocked"><?php _e( 'Blocked', 'wpzerospam' ); ?></span>
              <?php else: ?>
                <a href="<?php echo admin_url( 'admin.php?page=wordpress-zero-spam-blocked-ips&ip=' . $ip ); ?>">
                  <?php _e( 'Configure IP Block', 'wpzerospam' ); ?>
                </a>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <?php _e( 'No data to report yet.', 'wpzerospam' ); ?>
    <?php endif; ?>
  </div>
</div>
