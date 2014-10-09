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

$total_spam = count( $spam['raw'] );
$unique_spammers = count( $spam['unique_spammers'] );
$per_day = $this->num_days( end( $spam['raw'] )->date ) ? number_format( ( count( $spam['raw'] ) / $this->num_days( end( $spam['raw'] )->date ) ), 2 ) : 0;
$num_days = $this->num_days( end( $spam['raw'] )->date );
$starting_date = end( $spam['raw'] )->date;
?><div class="zero-spam__row">
	<div class="zero-spam__cell">
		<div class="zero-spam__widget zero-spam__bg--secondary">
			<div class="zero-spam__inner">
				<h3><?php echo __( 'Summary', 'zerospam' ); ?></h3>
				<div class="zero-spam__row">
					<div class="zero-spam__stat">
						<?php echo __( 'Protected', 'zerospam' ); ?>
						<b><?php echo number_format( $num_days, 0 ); ?> <?php echo __( 'days', 'zerospam' ); ?></b>
					</div>
					<div class="zero-spam__stat">
						<?php echo __( 'Total Spam', 'zerospam' ); ?>
						<b><?php echo number_format( $total_spam, 0 ); ?></b>
					</div>
					<div class="zero-spam__stat">
						<?php echo __( 'Per day', 'zerospam' ); ?>
						<b><?php echo number_format( $per_day, 0 ); ?></b>
					</div>
					<div class="zero-spam__stat">
						<?php echo __( 'Unique Spammers', 'zerospam' ); ?>
						<b><?php echo number_format( $unique_spammers, 0 ); ?></b>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="zero-spam__cell">
		<div class="zero-spam__widget zero-spam__bg--primary">
			<div class="zero-spam__inner">
				<h3><?php echo __( 'Stats', 'zerospam' ); ?></h3>
				<div class="zero-spam__row">
					<div class="zero-spam__stat">
						<?php echo __( 'Comments', 'zerospam' ); ?>
						<b><?php echo number_format( $spam['comment_spam'], 0 ); ?></b>
					</div>
					<div class="zero-spam__stat">
						<?php echo __( 'Registrations', 'zerospam' ); ?>
						<b><?php echo number_format( $spam['registration_spam'], 0 ); ?></b>
					</div>
					<?php if ( $this->plugins['cf7'] ): ?>
						<div class="zero-spam__stat">
							<?php echo __( 'Contact Form 7', 'zerospam' ); ?>
							<b><?php echo number_format( $spam['cf7_spam'], 0 ); ?></b>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="zero-spam__widget">
    <div class="zero-spam__inner">
        <?php if ( count( $spam['by_date'] ) ): ?>
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
					    'spam_registrations': <?php echo $ary['registration_spam']; ?>,
					    <?php if ( $this->plugins['cf7'] ): ?>'spam_cf7': <?php echo $ary['cf7_spam']; ?><?php endif; ?>
					},
					<?php endforeach; ?>
				],
				xkey: 'date',
				ykeys: [
					'spam_comments',
					'spam_registrations',
					<?php if ( $this->plugins['cf7'] ): ?>'spam_cf7',<?php endif; ?>
				],
				labels: [
					'<?php echo __( 'Spam Comments', 'zerospam' ); ?>',
					'<?php echo __( 'Spam Registrations', 'zerospam' ); ?>',
					<?php if ( $this->plugins['cf7'] ): ?>'<?php echo __( 'Contact Form 7', 'zerospam' ); ?>',<?php endif; ?>
				],
				xLabels: 'day',
				lineColors: [
					'#00639e',
					'#ff183a',
					'#fddb5a'
				],
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
                    <th><?php echo __( 'Page', 'zerospam' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $spam['raw'] as $key => $obj ):
                    switch ( $obj->type ) {
                        case 1:
                            $type = '<span class="zero-spam__label zero-spam__bg--primary">' . __( 'Registration', 'zerospam' ) . '</span>';
                        break;
                        case 2:
                            $type = '<span class="zero-spam__label zero-spam__bg--secondary">' . __( 'Comment', 'zerospam' ) . '</span>';
                        break;
                        case 3:
                            $type = '<span class="zero-spam__label zero-spam__bg--trinary">' . __( 'Contact Form 7', 'zerospam' ) . '</span>';
                        break;
                    }
                ?>
                <tr>
                    <td><?php echo $obj->zerospam_id; ?></td>
                    <td><?php echo date( 'l, F j, Y  g:i:sa', strtotime( $obj->date ) ); ?></td>
                    <td><?php echo $type; ?></td>
                    <td><?php echo long2ip( $obj->ip ); ?></td>
                    <td><a href="<?php echo esc_url( $obj->page ); ?>" target="_blank"><?php echo $obj->page; ?> <i class="fa fa-external-link-square"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
		<?php else: ?>
			<?php echo __( 'No spammers detected yet!', 'zerospam'); ?>
        <?php endif; ?>
    </div>
</div>
