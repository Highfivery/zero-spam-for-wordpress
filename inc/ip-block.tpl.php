<div class="zero-spam__row">
	<div class="zero-spam__widget">
		<div class="zero-spam__inner">
			<?php if ( is_array( $ips ) && count( $ips ) > 0 ): ?>
			<div id="zerospam-id-container">
				<h3><?php echo __( 'Blocked IPs', 'zerospam' ); ?></h3>
				<table class="zero-spam__table" id="zerospam--ip-block-table">
					<thead>
						<tr>
							<th><?php echo __( 'IP', 'zerospam' ); ?></th>
							<th><?php echo __( 'Status', 'zerospam' ); ?></th>
							<th><?php echo __( 'Start Date', 'zerospam' ); ?></th>
							<th><?php echo __( 'End Date', 'zerospam' ); ?></th>
							<th><?php echo __( 'Reason', 'zerospam' ); ?></th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ips as $key => $data ): ?>
						<tr data-ip="<?php echo esc_attr( $data->ip ); ?>" id="row-<?php echo esc_attr( $data->zerospam_ip_id ); ?>">
							<td class="zero-spam__ip"><a href="http://ip-lookup.net/index.php?ip=<?php echo esc_attr( $data->ip ); ?>" target="_blank">
								<?php echo $data->ip; ?> <i class="fa fa-external-link-square"></i></a></td>
							<td class="zero-spam__status">
								<?php if ( $this->_is_blocked( $data->ip ) ): ?>
								<span class="zero-spam__label zero-spam__bg--primary"><?php echo __( 'Blocked', 'zerospam' ); ?></span>
								<?php else: ?>
								<span class="zero-spam__label zero-spam__bg--trinary"><?php echo __( 'Unblocked', 'zerospam' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="zero-spam__start-date">
								<?php
								if ( $data->start_date ):
									echo date_i18n(
										'l, F jS, Y g:ia',
										strtotime( $data->start_date )
									);
								else:
								 echo '&mdash;';
								endif;
								?>
							</td>
							<td class="zero-spam__end-date">
								<?php
								if ( $data->start_date ):
									echo date_i18n(
										'l, F jS, Y g:ia',
										strtotime( $data->end_date )
									);
								else:
									echo '&mdash;';
								endif;
								?>
							</td>
							<td class="zero-spam__reason"><?php echo esc_html( $data->reason ); ?></td>
							<td class="zero-spam__text-center">
								<i class="fa fa-circle-o-notch fa-spin"></i>&nbsp;
								<i class="fa fa-edit"></i>&nbsp;
								<a href="#" class="button button-small zero-spam__block-ip" data-ip="<?php echo esc_attr( $data->ip ); ?>">
									<i class="fa fa-gear"></i>
								</a>&nbsp;

								<a href="#" class="button button-small zero-spam__trash" data-ip="<?php echo esc_attr( $data->ip ); ?>"><i class="fa fa-trash"></i></a>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php $this->_pager( $limit, $this->_get_blocked_ip_count(), $page, $tab ); ?>
			</div>
			<?php else: ?>
			<?php echo __( 'No blocked IPs found.', 'zerospam' ); ?>
			<?php endif; ?>
		</div>
	</div>
</div>
