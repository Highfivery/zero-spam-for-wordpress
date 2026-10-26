<?php
/**
 * The 6.0 admin app shell: the root the app mounts into, a skeleton shown
 * until it does, and a message for browsers without JavaScript.
 *
 * Variables from Admin_App::render_page():
 *
 * @var string                                  $area   Area (overview, activity, …, network).
 * @var string                                  $title  Area title.
 * @var array<string, string>                   $titles Area titles.
 * @var array{theme: string, reduce_motion: bool} $prefs Display preferences.
 * @var bool                                    $onboarding First-run setup replaces the Overview.
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

use ZeroSpam\Core\Admin\Admin_App;

$zerospam_icons = array(
	'overview'   => 'zs-i-wall',
	'activity'   => 'zs-i-activity',
	'rules'      => 'zs-i-rules',
	'protection' => 'zs-i-shield',
	'settings'   => 'zs-i-sliders',
	'help'       => 'zs-i-help',
);
?>
<div
	id="zerospam-app"
	class="zs-app"
	data-area="<?php echo esc_attr( $area ); ?>"
	data-zs-theme="<?php echo esc_attr( $prefs['theme'] ); ?>"
	<?php echo $prefs['reduce_motion'] ? 'data-zs-motion="reduce"' : ''; ?>
>
	<?php if ( ! empty( $onboarding ) ) : ?>
		<div class="zs-skel zs-skel-onb" aria-busy="true">
			<div class="zs-skel-body">
				<p class="zs-sr-only" role="status"><?php esc_html_e( 'Loading Zero Spam setup…', 'zero-spam' ); ?></p>
				<span class="zs-skel-block zs-skel-wide"></span>
				<span class="zs-skel-block zs-skel-narrow"></span>
			</div>
		</div>
	<?php else : ?>
	<div class="zs-skel" aria-busy="true">
		<div class="zs-skel-side">
			<span class="zs-skel-brand"><?php esc_html_e( 'Zero Spam', 'zero-spam' ); ?></span>
			<?php if ( 'network' !== $area ) : ?>
				<ul class="zs-skel-nav">
					<?php foreach ( Admin_App::AREAS as $zerospam_key => $zerospam_slug ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $zerospam_slug ) ); ?>"<?php echo $zerospam_key === $area ? ' aria-current="page"' : ''; ?>>
								<svg class="zs-icon" aria-hidden="true" focusable="false"><use href="#<?php echo esc_attr( $zerospam_icons[ $zerospam_key ] ); ?>"/></svg>
								<?php echo esc_html( $titles[ $zerospam_key ] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<div class="zs-skel-main">
			<div class="zs-skel-top">
				<span><?php esc_html_e( 'Zero Spam', 'zero-spam' ); ?> / <b><?php echo esc_html( $title ); ?></b></span>
			</div>
			<div class="zs-skel-body">
				<p class="zs-sr-only" role="status"><?php esc_html_e( 'Loading Zero Spam…', 'zero-spam' ); ?></p>
				<span class="zs-skel-block zs-skel-hero"></span>
				<span class="zs-skel-block zs-skel-wide"></span>
				<span class="zs-skel-block zs-skel-narrow"></span>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<noscript>
		<div class="zs-noscript">
			<h1 class="zs-h2"><?php esc_html_e( 'Zero Spam needs JavaScript for this screen', 'zero-spam' ); ?></h1>
			<p>
				<?php esc_html_e( 'Your site is still protected. Turn on JavaScript in your browser to see this screen, or use the classic settings screen.', 'zero-spam' ); ?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ); ?>"><?php esc_html_e( 'Open the classic settings', 'zero-spam' ); ?></a>
			</p>
		</div>
	</noscript>
</div>
