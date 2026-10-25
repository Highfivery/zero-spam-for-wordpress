<?php
/**
 * Dashboard widget body: "Zero Spam overview" (site) and "Zero Spam network
 * overview" (network). Rendered by ZeroSpam\Core\Admin\Dashboard_Widget inside
 * the WordPress postbox; assets/src/widget-refresh updates the elements marked
 * data-zs-field when an administrator presses Refresh, so keep those hooks.
 * Escape everything: detection data comes from visitors.
 *
 * $args:
 * - scope        (string) site or network.
 * - theme        (string) light, dark or auto (the user's ui/prefs theme).
 * - reduce       (bool)   The user's reduce-motion preference.
 * - level        (array)  Protection level: level, tone (quiet, ok, warn), text.
 * - data         (array)  Widget data: table_missing, days, and latest (site) or sites + top_sites (network).
 * - figures      (array)  Dashboard_Widget::figures() of data.days.
 * - spark        (array)  Sparkline::paths() of data.days: line, area.
 * - strings      (array)  Dashboard_Widget::strings().
 * - activity_url (string) Link to the activity log, or '' when the user cannot open it.
 * - can_refresh  (bool)   Whether to show the Refresh button.
 *
 * @package ZeroSpam
 */

use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Core\Admin\Icons;
use ZeroSpam\Core\Admin\Sparkline;

defined( 'ABSPATH' ) || die();

$zerospam_w       = isset( $args ) && is_array( $args ) ? $args : array();
$zerospam_network = 'network' === ( $zerospam_w['scope'] ?? 'site' );
$zerospam_data    = $zerospam_w['data'] ?? array();
$zerospam_days    = $zerospam_data['days'] ?? array();
$zerospam_figures = $zerospam_w['figures'] ?? Dashboard_Widget::figures( $zerospam_days );
$zerospam_delta   = Dashboard_Widget::delta_text( $zerospam_figures );
$zerospam_spark   = $zerospam_w['spark'] ?? Sparkline::paths( $zerospam_days );
$zerospam_level   = $zerospam_w['level'] ?? Dashboard_Widget::level();
$zerospam_strings = $zerospam_w['strings'] ?? Dashboard_Widget::strings();
$zerospam_icons   = array(
	'ok'   => 'zs-i-checkc',
	'warn' => 'zs-i-warn',
);
?>
<div class="zs-widget" data-zs-widget="<?php echo esc_attr( $zerospam_network ? 'network' : 'site' ); ?>" data-zs-theme="<?php echo esc_attr( $zerospam_w['theme'] ?? 'auto' ); ?>"<?php echo empty( $zerospam_w['reduce'] ) ? '' : ' data-zs-motion="reduce"'; ?>>

	<p class="zs-w-level zs-w-level-<?php echo esc_attr( $zerospam_level['tone'] ); ?>">
		<?php
		if ( isset( $zerospam_icons[ $zerospam_level['tone'] ] ) ) {
			echo Icons::svg( $zerospam_icons[ $zerospam_level['tone'] ], 'zs-icon zs-icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() returns markup filtered by wp_kses().
		}
		?>
		<span><?php esc_html_e( 'Protection level:', 'zero-spam' ); ?> <b><?php echo esc_html( $zerospam_level['text'] ); ?></b></span>
	</p>

	<?php if ( ! empty( $zerospam_data['table_missing'] ) ) : ?>
		<div class="zs-w-notice">
			<?php echo Icons::svg( 'zs-i-db', 'zs-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() returns markup filtered by wp_kses(). ?>
			<p>
				<b><?php esc_html_e( 'Activity table not found', 'zero-spam' ); ?></b>
				<span><?php esc_html_e( 'The spam log table could not be found. Deactivate and reactivate Zero Spam to rebuild it.', 'zero-spam' ); ?></span>
			</p>
		</div>
	<?php else : ?>

		<div class="zs-w-headline">
			<p class="zs-w-figure">
				<span class="zs-w-count zs-mono" data-zs-field="total"><?php echo esc_html( number_format_i18n( $zerospam_figures['total'] ) ); ?></span>
				<span class="zs-w-label">
					<?php
					if ( $zerospam_network ) {
						$zerospam_sites = (int) ( $zerospam_data['sites'] ?? 0 );
						echo esc_html(
							sprintf(
								/* translators: %s: number of sites in the network */
								_n( 'spam blocked across %s site in the last 30 days', 'spam blocked across %s sites in the last 30 days', $zerospam_sites, 'zero-spam' ),
								number_format_i18n( $zerospam_sites )
							)
						);
					} else {
						esc_html_e( 'spam blocked in the last 30 days', 'zero-spam' );
					}
					?>
				</span>
			</p>
			<p class="zs-w-delta" data-zs-field="delta" data-zs-direction="<?php echo esc_attr( $zerospam_delta['direction'] ); ?>"><?php echo esc_html( $zerospam_delta['text'] ); ?></p>
		</div>

		<figure class="zs-w-spark">
			<svg class="zs-w-spark-svg" viewBox="0 0 <?php echo (int) Sparkline::WIDTH; ?> <?php echo (int) Sparkline::HEIGHT; ?>" preserveAspectRatio="none" aria-hidden="true" focusable="false">
				<path class="zs-w-spark-area" data-zs-field="spark-area" d="<?php echo esc_attr( $zerospam_spark['area'] ); ?>"/>
				<path class="zs-w-spark-line" data-zs-field="spark-line" d="<?php echo esc_attr( $zerospam_spark['line'] ); ?>" vector-effect="non-scaling-stroke"/>
			</svg>
			<figcaption class="zs-sr-only" data-zs-field="spark-text"><?php echo esc_html( Dashboard_Widget::spark_text( $zerospam_days ) ); ?></figcaption>
		</figure>

		<?php if ( $zerospam_network ) : ?>
			<?php if ( ! empty( $zerospam_data['top_sites'] ) ) : ?>
				<h3 class="zs-w-sub"><?php esc_html_e( 'Sites with the most spam, last 30 days', 'zero-spam' ); ?></h3>
				<ol class="zs-w-list zs-w-sites">
					<?php foreach ( $zerospam_data['top_sites'] as $zerospam_site ) : ?>
						<li class="zs-w-item">
							<?php if ( '' !== $zerospam_site['url'] ) : ?>
								<a class="zs-w-name" href="<?php echo esc_url( $zerospam_site['url'] ); ?>"><?php echo esc_html( $zerospam_site['name'] ); ?></a>
							<?php else : ?>
								<span class="zs-w-name"><?php echo esc_html( $zerospam_site['name'] ); ?></span>
							<?php endif; ?>
							<span class="zs-w-num zs-mono"><?php echo esc_html( number_format_i18n( (int) $zerospam_site['count'] ) ); ?><span class="zs-sr-only"> <?php esc_html_e( 'spam blocked', 'zero-spam' ); ?></span></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<p class="zs-w-empty"><?php esc_html_e( 'No spam detected on the network in the last 30 days.', 'zero-spam' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<h3 class="zs-w-sub" id="zs-w-latest"><?php esc_html_e( 'Latest detections', 'zero-spam' ); ?></h3>
			<ul class="zs-w-list" data-zs-field="latest" aria-labelledby="zs-w-latest"<?php echo empty( $zerospam_data['latest'] ) ? ' hidden' : ''; ?>>
				<?php foreach ( $zerospam_data['latest'] ?? array() as $zerospam_item ) : ?>
					<li class="zs-w-item">
						<span class="zs-w-name"><?php echo esc_html( $zerospam_item['label'] ); ?></span>
						<span class="zs-w-ip zs-mono"><?php echo esc_html( $zerospam_item['ip'] ); ?></span>
						<time class="zs-w-when" datetime="<?php echo esc_attr( $zerospam_item['date_gmt'] ); ?>">
							<?php
							/* translators: %s: time since the detection, for example "5 mins" */
							echo esc_html( sprintf( __( '%s ago', 'zero-spam' ), human_time_diff( $zerospam_item['time'], time() ) ) );
							?>
						</time>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="zs-w-empty" data-zs-field="empty"<?php echo empty( $zerospam_data['latest'] ) ? '' : ' hidden'; ?>><?php echo esc_html( $zerospam_strings['empty'] ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<?php if ( '' !== ( $zerospam_w['activity_url'] ?? '' ) || ! empty( $zerospam_w['can_refresh'] ) ) : ?>
		<div class="zs-w-foot">
			<?php if ( '' !== ( $zerospam_w['activity_url'] ?? '' ) ) : ?>
				<a class="zs-w-link" href="<?php echo esc_url( $zerospam_w['activity_url'] ); ?>">
					<?php
					if ( $zerospam_network ) {
						esc_html_e( 'View network activity', 'zero-spam' );
					} else {
						esc_html_e( 'View activity', 'zero-spam' );
					}
					?>
					<?php echo Icons::svg( 'zs-i-chev', 'zs-icon zs-icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() returns markup filtered by wp_kses(). ?>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $zerospam_w['can_refresh'] ) && empty( $zerospam_data['table_missing'] ) ) : ?>
				<button type="button" class="zs-w-btn" data-zs-refresh>
					<?php echo Icons::svg( 'zs-i-refresh', 'zs-icon zs-icon-sm zs-w-spin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() returns markup filtered by wp_kses(). ?>
					<span><?php esc_html_e( 'Refresh', 'zero-spam' ); ?></span>
				</button>
				<p class="zs-w-status" role="status" aria-live="polite" data-zs-field="status"></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
