<?php
/**
 * Spammer Log Template
 *
 * Content for the plugin spammer log page.
 *
 * @since 1.5.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
?><div class="zero-spam__widget">
    <div class="zero-spam__inner">
        <h3><?php echo __( 'All Time', 'zerospam' ); ?></h3>
        <div id="graph"></div>
        <script>
        jQuery(function() {
            // Use Morris.Area instead of Morris.Line
            Morris.Area({
                element: 'graph',
                behaveLikeLine: true,
                data: [
                    <?php foreach( $spam['by_date'] as $date => $ary ): ?>
                      {
                          'date': '<?php echo $date; ?>',
                          'spam_comments': <?php echo $ary['comment_spam']; ?>,
                          'spam_registrations': <?php echo $ary['registration_spam']; ?>
                      },
                    <?php endforeach; ?>
                ],
                xkey: 'date',
                ykeys: ['spam_comments', 'spam_registrations'],
                labels: ['<?php echo __( 'Spam Comments', 'zerospam' ); ?>', '<?php echo __( 'Spam Registrations', 'zerospam' ); ?>'],
                xLabels: 'day'
            });
        });
        </script>
        <table class="zero-spam__table">
            <thead>
                <tr>
                    <th><?php echo __( 'ID', 'zerospam' ); ?></th>
                    <th><?php echo __( 'Date', 'zerospam' ); ?></th>
                    <th><?php echo __( 'Type', 'zerospam' ); ?></th>
                    <th><?php echo __( 'IP', 'zerospam' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $spam['raw'] as $key => $obj ):
                    switch ( $obj->type ) {
                        case 1:
                            $type = __( 'Registration', 'zerospam' );
                        break;
                        case 2:
                            $type = __( 'Comment', 'zerospam' );
                        break;
                    }
                ?>
                <tr>
                    <td><?php echo $obj->zerospam_id; ?></td>
                    <td><?php echo $obj->date; ?></td>
                    <td><?php echo $type; ?></td>
                    <td><?php echo long2ip( $obj->ip ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
