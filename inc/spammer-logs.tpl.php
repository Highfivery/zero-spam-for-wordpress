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
		<div class="zero-spam__widget zero-spam__bg--primary">
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
						<b><?php echo number_format( count( $all_spam['raw'] ), 0 ); ?></b>
					</div>
				<?php if ( isset( $per_day ) ): ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Per day', 'zerospam' ); ?>
						<b><?php echo number_format( $per_day, 0 ); ?></b>
					</div>
				<?php endif; ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Unique Spammers', 'zerospam' ); ?>
						<b><?php echo number_format( count( $all_spam['unique_spammers'] ), 0 ); ?></b>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="zero-spam__cell">
		<div class="zero-spam__widget zero-spam__bg--secondary">
			<div class="zero-spam__inner">
				<h3><?php echo __( 'Stats', 'zerospam' ); ?></h3>
				<div class="zero-spam__row">
					<div class="zero-spam__stat">
						<?php echo __( 'Comments', 'zerospam' ); ?>
						<b><?php echo number_format( $all_spam['comment_spam'], 0 ); ?></b>
					</div>
					<div class="zero-spam__stat">
						<?php echo __( 'Registrations', 'zerospam' ); ?>
						<b><?php echo number_format( $all_spam['registration_spam'], 0 ); ?></b>
					</div>
					<?php if ( $this->settings['plugins']['cf7'] ): ?>
						<div class="zero-spam__stat">
							<?php echo __( 'Contact Form 7', 'zerospam' ); ?>
							<b><?php echo number_format( $all_spam['cf7_spam'], 0 ); ?></b>
						</div>
					<?php endif; ?>
					<?php if ( $this->settings['plugins']['gf'] ): ?>
					<div class="zero-spam__stat">
						<?php echo __( 'Gravity Forms', 'zerospam' ); ?>
						<b><?php echo number_format( $all_spam['gf_spam'], 0 ); ?></b>
					</div>
					<?php endif; ?>
					<?php if ( $this->settings['plugins']['bp'] ): ?>
					<div class="zero-spam__stat">
						<?php echo __( 'BP Registrations', 'zerospam' ); ?>
						<b><?php echo number_format( $all_spam['bp_registration_spam'], 0 ); ?></b>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if ( $ip_location_support ): ?>
<div class="zero-spam__widget">
	<div class="zero-spam__overlay">
		<div class="zero-spam__inner">
			<i class="fa fa-circle-o-notch fa-spin"></i>
			<h4><?php echo __( 'Crunching numbers...', 'zerospam' ); ?></h4>
			<p><?php echo __( 'This could take a minute or two, please be patient.' ); ?></p>
		</div>
	</div>
	<div class="zero-spam__row">
		<div class="zero-spam__cell">
			<div class="zero-spam__inner">
				<h3><?php echo __( 'Most Spam By Country', 'zerospam' ); ?></h3>
				<table class="zero-spam__table">
					<thead>
						<tr>
							<th><?php echo __( 'Country', 'zerospam' ); ?></th>
							<th class="zero-spam__text-right"><?php echo __( 'Count', 'zerospam' ); ?></th>
						</tr>
					</thead>
					<tbody id="zerospam-country-spam">
					</tbody>
				</table>
			</div>
		</div>
		<div class="zero-spam__cell">
			<div id="map" class="zero-spam__map"></div>
			<script>

			jQuery(function() {
  				jQuery.post( ajaxurl, {
					action: 'get_ip_spam',
					security: '<?php echo $ajax_nonce; ?>',
				}, function( data ) {
					if ( data ) {
						var obj = jQuery.parseJSON( data ),
							country_count = {},
							cnt = 0;

						if ( obj.by_country ) {
							jQuery( ".zero-spam__overlay" ).fadeOut();


							jQuery.each( obj.by_country, function( abbr, c ) {
								cnt++;
								if ( cnt > 6 ) return false;
								jQuery( "#zerospam-country-spam" ).append( "<tr><td><b>" + c.name + "</b></td><td class='zero-spam__text-right'>" + c.count + "</td></tr>" );
								country_count[abbr] = String(c.count);
							});

							jQuery('#map').vectorMap({
			      				map: 'world_mill_en',
			      				backgroundColor: '#1b1e24',
								series: {
									regions: [{
										scale: ['#ffe6ea', '#ff183a'],
										normalizeFunction: 'linear',
										attribute: 'fill',
			       				values: country_count
									}]
								}

		  				});

							var map = jQuery('#map').vectorMap('get', 'mapObject');
							map.series.regions[0].setValues( country_count );
						} else {
							jQuery( ".zero-spam__inner", jQuery( ".zero-spam__overlay" ) ).html( "<i class='fa fa-thumbs-up'></i><h4>No spammers yet!</h4>" );
						}
					} else {
						jQuery( ".zero-spam__inner", jQuery( ".zero-spam__overlay" ) ).html( "<i class='fa fa-exclamation-triangle'></i><h4>IP API Usage Limit Reached</h4><p>You've reached you're daily  limit to the IP API to gather location information. Please check back in one hour.</p>" );
					}
				});
			});
			</script>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( count( $all_spam['raw'] ) ): ?>
<div class="zero-spam__row">
	<div class="zero-spam__cell">
		<div class="zero-spam__widget">
			<div class="zero-spam__inner">
				<div class="zero-spam__row">
					<div class="zero-spam__cell">
						<h3><?php echo __( 'Percentage of Spam by Day', 'zerospam' ); ?></h3>
						<table class="zero-spam__table">
							<thead>
								<tr>
									<th><?php echo __( 'Day', 'zerospam' ); ?></th>
									<th class="zero-spam__text-right"><?php echo __( 'Count', 'zerospam' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach( $all_spam['by_day'] as $day => $count ): ?>
								<tr>
									<td><b><?php echo $day; ?></b></td>
									<td class="zero-spam__text-right"><?php echo number_format( $count, 0 ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="zero-spam__cell">
						<div id="donut"></div>
						<script>
						jQuery(function() {
						  Morris.Donut({
							  element: 'donut',
							  data: [
							  	<?php foreach( $all_spam['by_day'] as $day => $count ): ?>
							  	{value: <?php echo $this->_get_percent( $count, count( $all_spam['raw'] ) ); ?>, label: '<?php echo $day; ?>', formatted: '<?php echo $this->_get_percent( $count, count( $all_spam['raw'] ) ); ?>%'},
							  	<?php endforeach; ?>
							  ],
							  formatter: function (x, data) { return data.formatted; }
							});
						});
						</script>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="zero-spam__cell">
		<div class="zero-spam__widget">
			<div class="zero-spam__inner">
				<h3><?php echo __( 'Most Frequent Spammers', 'zerospam' ); ?></h3>
				<table class="zero-spam__table">
					<thead>
						<tr>
							<th width="100"><?php echo __( 'IP', 'zerospam' ); ?></th>
							<?php if ( $ip_location_support ): ?><th><?php echo __( 'Location', 'zerospam' ); ?></th><?php endif; ?>
							<th class="zero-spam__text-right">#</th>
							<th><?php echo __( 'Status', 'zerospam' ); ?></th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						<?php
						arsort( $all_spam['by_spam_count'] );
						$cnt = 0;

						foreach( $all_spam['by_spam_count'] as $ip => $count ):
							$cnt++; if ( $cnt > 6) break;
							?>
							<tr data-ip="<?php echo $ip; ?>">
								<td><a href="http://ip-lookup.net/index.php?ip=<?php echo $ip; ?>" target="_blank">
							<?php echo $ip; ?> <i class="fa fa-external-link-square"></i></a></td>
								<?php if ( $ip_location_support ): ?>
								<td>
									<div data-ip-location="<?php echo $ip; ?>"><i class="fa fa fa-circle-o-notch fa-spin"></i> <em><?php echo __( 'Locating...', 'zerospam' ); ?></em></div>
								</td>
								<?php endif; ?>
								<td class="zero-spam__text-right"><?php echo number_format( $count, 0 ); ?></td>
								<td class="zero-spam__status">
									<?php if( $this->_is_blocked( $ip ) ): ?>
									<span class="zero-spam__label zero-spam__bg--primary"><?php echo __( 'Blocked', 'zerospam' ); ?></span>
									<?php else: ?>
									<span class="zero-spam__label zero-spam__bg--trinary"><?php echo __( 'Unblocked', 'zerospam' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="zero-spam__text-center">
									<i class="fa fa-circle-o-notch fa-spin"></i>
									<i class="fa fa-edit"></i>
									<a href="#" class="button button-small zero-spam__block-ip"
										data-ip="<?php echo $ip; ?>"><i class="fa fa-gear"></i></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="zero-spam__widget">
	<div class="zero-spam__inner">
		<?php if ( count( $all_spam['by_date'] ) ): ?>
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
					<?php foreach( $all_spam['by_date'] as $date => $ary ): ?>
					{
						'date': '<?php echo $date; ?>',
						'spam_comments': <?php echo $ary['comment_spam']; ?>,
						'spam_registrations': <?php echo $ary['registration_spam']; ?>,
						<?php if ( $this->settings['plugins']['cf7'] ): ?>'spam_cf7': <?php echo $ary['cf7_spam']; ?>,<?php endif; ?>
						<?php if ( $this->settings['plugins']['gf'] ): ?>'spam_gf': <?php echo $ary['gf_spam']; ?><?php endif; ?>
						<?php if ( $this->settings['plugins']['bp'] ): ?>'bp_registrations': <?php echo $ary['bp_registration_spam']; ?><?php endif; ?>
					},
					<?php endforeach; ?>
				],
				xkey: 'date',
				ykeys: [
					'spam_comments',
					'spam_registrations',
					<?php if ( $this->settings['plugins']['cf7'] ): ?>'spam_cf7',<?php endif; ?>
					<?php if ( $this->settings['plugins']['gf'] ): ?>'spam_gf',<?php endif; ?>
					<?php if ( $this->settings['plugins']['bp'] ): ?>'bp_registrations',<?php endif; ?>
				],
				labels: [
					'<?php echo __( 'Spam Comments', 'zerospam' ); ?>',
					'<?php echo __( 'Spam Registrations', 'zerospam' ); ?>',
					<?php if ( $this->settings['plugins']['cf7'] ): ?>'<?php echo __( 'Contact Form 7', 'zerospam' ); ?>',<?php endif; ?>
					<?php if ( $this->settings['plugins']['gf'] ): ?>'<?php echo __( 'Gravity Forms', 'zerospam' ); ?>',<?php endif; ?>
					<?php if ( $this->settings['plugins']['bp'] ): ?>'<?php echo __( 'BuddyPress', 'zerospam' ); ?>',<?php endif; ?>
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
					<th><?php echo __( 'Date', 'zerospam' ); ?></th>
					<th width="90"><?php echo __( 'Type', 'zerospam' ); ?></th>
					<?php if ( $ip_location_support ): ?><th><?php echo __( 'Location', 'zerospam' ); ?></th><?php endif; ?>
					<th width="106"><?php echo __( 'IP', 'zerospam' ); ?></th>
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
						case 5:
							$type = '<span class="zero-spam__label zero-spam__bg--bpr">' . __( 'BP Registration', 'zerospam' ) . '</span>';
							break;
					}
				?>
				<tr data-ip="<?php echo $obj->ip; ?>" id="row-<?php echo $obj->zerospam_id; ?>">
					<td>
						<?php
						echo date_i18n(
							'l, F jS, Y g:ia',
							strtotime( $obj->date )
						);
						?>
					</td>
					<td><?php echo isset( $type ) ? $type : '&mdash;'; ?></td>
					<?php if ( $ip_location_support ): ?>
					<td>
						<div data-ip-location="<?php echo $obj->ip; ?>"><i class="fa fa fa-circle-o-notch fa-spin"></i> <em><?php echo __( 'Locating...', 'zerospam' ); ?></em></div>
					</td>
					<?php endif; ?>
					<td>
						<a href="http://ip-lookup.net/index.php?ip=<?php echo $obj->ip; ?>" target="_blank">
							<?php echo $obj->ip; ?> <i class="fa fa-external-link-square"></i></a>
					</td>
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
		<?php $this->_pager( $limit, $this->_get_spam_count(), $page, $tab ); ?>
		<?php else: ?>
			<?php echo __( 'No spammers detected yet!', 'zerospam'); ?>
		<?php endif; ?>
	</div>
</div>
