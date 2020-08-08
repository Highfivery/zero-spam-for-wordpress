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
  <h3><?php _e( 'Most Spam by IP Address', 'zero-spam' ); ?></h3>
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
          <li class="wpzerospam-list-item">
            <span class="wpzerospam-list-cell wpzerospam-list-cell-icon">
            <?php if ( ! empty( $ary['country'] ) ): ?>
              <img class="wpzerospam-country-flag" width="16" src="https://hatscripts.github.io/circle-flags/flags/<?php echo strtolower( $ary['country'] ); ?>.svg" alt="<?php echo wpzerospam_get_location( $ary['country'] ); ?>" />
            <?php endif; ?>
            </span>
            <span class="wpzerospam-list-cell wpzerospam-list-cell-ip">
              <a href="https://zerospam.org/ip-lookup/<?php echo urlencode( $ip ); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo $ip; ?></strong></a>
            </span>
            <span class="wpzerospam-list-cell wpzerospam-list-cell-country<?php if ( empty( $ary['country'] ) ): ?> wpzerospam-list-cell-na<?php endif; ?>">
              <?php if ( ! empty( $ary['country'] ) ): ?>
                <?php echo wpzerospam_get_location( $ary['country'] ); ?>
              <?php else: ?>
                N/A
              <?php endif; ?>
            </span>
            <span class="wpzerospam-list-cell wpzerospam-list-cell-count">
              <?php echo number_format( $ary['count'], 0 ); ?>
            </span>
            <span class="wpzerospam-list-cell wpzerospam-list-cell-action">
              <?php if ( wpzerospam_is_blocked( $ip ) ): ?>
                <span class="wpzerospam-blocked"><?php _e( 'Blocked', 'zero-spam' ); ?></span>
              <?php else: ?>
                <a href="<?php echo admin_url( 'admin.php?page=wordpress-zero-spam-blocked-ips&ip=' . $ip ); ?>">
                  <?php _e( 'Block IP', 'zero-spam' ); ?>
                </a>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <?php _e( 'No data to report yet.', 'zero-spam' ); ?>
    <?php endif; ?>
  </div>
</div>
