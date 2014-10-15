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
?>
<div class="zero-spam__row">
	<div class="zero-spam__cell">
		<div class="zero-spam__widget zero-spam__bg--secondary">
			<div class="zero-spam__inner">
				<h3><?php echo __( 'Summary', 'zerospam' ); ?></h3>
				<div class="zero-spam__row">
				<?php if ( isset( $num_days ) ): ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Protected', 'zerospam' ); ?>
						<b><?php echo number_format( $num_days, 0 ); ?> <?php echo __( 'days', 'zerospam' ); ?></b>
					</div>
				<?php endif; ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Total Spam', 'zerospam' ); ?>
						<b><?php echo number_format( $total_spam, 0 ); ?></b>
					</div>
				<?php if ( isset( $per_day ) ): ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Per day', 'zerospam' ); ?>
						<b><?php echo number_format( $per_day, 0 ); ?></b>
					</div>
				<?php endif; ?>
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
					<?php if ( $this->plugins['gf'] ): ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Gravity Forms', 'zerospam' ); ?>
						<b><?php echo number_format( $spam['gf_spam'], 0 ); ?></b>
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
		<a href="javascript: clearLog();" class="zero-spam__fright button"><?php echo __( 'Reset Log', 'zerospam' ); ?></a>
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
						<?php if ( $this->plugins['cf7'] ): ?>'spam_cf7': <?php echo $ary['cf7_spam']; ?>,<?php endif; ?>
				<?php if ( $this->plugins['gf'] ): ?>'spam_gf': <?php echo $ary['gf_spam']; ?><?php endif; ?>
					},
					<?php endforeach; ?>
				],
				xkey: 'date',
				ykeys: [
					'spam_comments',
					'spam_registrations',
					<?php if ( $this->plugins['cf7'] ): ?>'spam_cf7',<?php endif; ?>
			<?php if ( $this->plugins['gf'] ): ?>'spam_gf',<?php endif; ?>
				],
				labels: [
					'<?php echo __( 'Spam Comments', 'zerospam' ); ?>',
					'<?php echo __( 'Spam Registrations', 'zerospam' ); ?>',
					<?php if ( $this->plugins['cf7'] ): ?>'<?php echo __( 'Contact Form 7', 'zerospam' ); ?>',<?php endif; ?>
			<?php if ( $this->plugins['gf'] ): ?>'<?php echo __( 'Gravity Forms', 'zerospam' ); ?>',<?php endif; ?>
				],
				xLabels: 'day',
				lineColors: [
					'#00639e',
					'#ff183a',
					'#fddb5a',
					'#222d3a'
				]
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
					<th><?php echo __( 'Status', 'zerospam' ); ?></th>
					<th>&nbsp;</th>
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
						case 4:
							$type = '<span class="zero-spam__label zero-spam__bg--gf">' . __( 'Gravity Forms', 'zerospam' ) . '</span>';
							break;
					}
				?>
				<tr data-ip="<?php echo $obj->ip; ?>" id="row-<?php echo $obj->zerospam_id; ?>">
					<td><?php echo $obj->zerospam_id; ?></td>
					<td>
						<?php echo date(
						'l, F j, Y  g:i:sa',
						strtotime( $obj->date )
						); ?>
					</td>
					<td><?php echo $type; ?></td>
					<td><?php echo $obj->ip; ?></td>
					<td>
						<?php if ( isset( $obj->page ) ): ?>
						<a href="<?php echo esc_url( $obj->page ); ?>" target="_blank"><?php echo $obj->page; ?> <i class="fa fa-external-link-square"></i></a>
						<?php else: ?>
							<?php echo __( 'Unknown', 'zerospam' ); ?>
						<?php endif; ?>
					</td>
					<td class="zero-spam__status">
						<?php if( $this->_is_blocked( $obj->ip ) ): ?>
						<span class="zero-spam__label zero-spam__bg--primary"><?php echo __( 'Blocked', 'zerospam' ); ?></span>
						<?php else: ?>
						<span class="zero-spam__label zero-spam__bg--trinary"><?php echo __( 'Unblocked', 'zerospam' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="zero-spam__text-center">
						<i class="fa fa-circle-o-notch fa-spin"></i>&nbsp;
						<i class="fa fa-edit"></i>&nbsp;
						<a href="#" class="button button-small zero-spam__block-ip"
							data-ip="<?php echo $obj->ip; ?>"><i class="fa fa-gear"></i></a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else: ?>
			<?php echo __( 'No spammers detected yet!', 'zerospam'); ?>
		<?php endif; ?>
	</div>
</div>
