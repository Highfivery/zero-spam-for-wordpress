<?php
/**
 * Lockout Rescue Documentation Template
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();
?>

<div class="zerospam-docs-section" id="lockout-rescue-docs">
	<h2><?php esc_html_e( 'Getting Back In After a Lockout', 'zero-spam' ); ?></h2>

	<p class="zerospam-docs-intro">
		<?php esc_html_e( 'If Zero Spam ever blocks your own IP address, you can get back in without FTP or editing files.', 'zero-spam' ); ?>
	</p>

	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'The emailed rescue link', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<ol class="zerospam-docs-steps">
				<li><?php esc_html_e( 'On the blocked page, open "Help for site owners".', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Enter the email address of an administrator account on this site and press "Email me a rescue link".', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Open the link in that email, on any device, and press "Let me back in". That browser is let in for one hour, so you can log in.', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Add your IP address to the IP Whitelist under Settings → Zero Spam → Settings so it does not happen again.', 'zero-spam' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'The link works once and expires 15 minutes after it is sent. The page answers the same way whatever address is entered, and only an administrator\'s address ever receives an email. Requests are limited to 5 per hour per IP address, 3 emails per hour per address and 10 emails per hour per site.', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'Each use is recorded in the Detection Log as "Rescue link used", with the IP address that asked for the link and the one that used it. These entries are not detections: dashboard totals, charts, top lists, the dashboard widget and network statistics leave them out.', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'The email is sent by WP-Cron a moment after the request, so it can take a few minutes to arrive. If it never arrives, check Tools → Site Health: WP-Cron needs loopback requests to work (or a system cron that runs wp-cron.php).', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'The link cannot help when an .htaccess block method is used: the web server rejects blocked addresses before WordPress runs.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Administrator addresses that can never be locked out', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'When an administrator activates or updates Zero Spam, their current IP address is saved (public addresses only, and never when the request comes through a proxy Zero Spam does not trust). Zero Spam never blocks that address from the site, but comments, registrations and forms sent from it are still checked and logged, and the IP Whitelist is not changed.', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'The 10 most recent saved addresses are kept and listed under the IP Whitelist on Settings → Zero Spam → Settings, each with a Remove button. With an .htaccess block method, the deny rules leave them out too.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'For developers', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<ul>
				<li><?php echo wp_kses( __( '<code>zerospam_rescue_form_enabled</code> (default true): hide the rescue form from the blocked page; form requests then send nothing.', 'zero-spam' ), array( 'code' => array() ) ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_rescue_email_via_cron</code> (default true unless DISABLE_WP_CRON is set): send rescue emails from a WP-Cron event instead of at the end of the request.', 'zero-spam' ), array( 'code' => array() ) ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_admin_safe_ip_enabled</code> (default true): save the activating or updating administrator\'s address.', 'zero-spam' ), array( 'code' => array() ) ); ?></li>
				<li><?php echo wp_kses( __( '<code>ZEROSPAM_RESCUE_KEY</code> in wp-config.php still works: visit any page with <code>?zerospam_rescue={KEY}</code> appended.', 'zero-spam' ), array( 'code' => array() ) ); ?></li>
				<li><?php echo wp_kses( __( '<code>DB::query( \'log\' )</code> returns detections only; pass <code>include_non_detections</code> to include rescue entries.', 'zero-spam' ), array( 'code' => array() ) ); ?></li>
			</ul>
		</div>
	</div>
</div>
