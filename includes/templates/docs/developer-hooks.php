<?php
/**
 * Developer Hooks Documentation Template
 *
 * Lists the filters and actions for login protection, visitor IP detection
 * and lockout rescue. The complete list of hooks is on the GitHub wiki.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

$zerospam_hook_kses = array( 'code' => array() );
?>

<div class="zerospam-docs-section" id="developer-hooks-docs">
	<h2><?php esc_html_e( 'Developer Hooks', 'zero-spam' ); ?></h2>

	<p class="zerospam-docs-intro">
		<?php esc_html_e( 'For developers: filters and actions that change how Zero Spam handles logins, visitor IP addresses and lockouts. Add them in a small plugin or your theme\'s functions.php. You do not need any of them to use Zero Spam.', 'zero-spam' ); ?>
	</p>

	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Login protection', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<ul>
				<li><?php echo wp_kses( __( '<code>zerospam_login_skip_check</code> (filter, since 5.7.14; receives <code>$skip</code>, <code>$post</code>, <code>$user</code>): return true to skip the login honeypot and David Walsh checks for your own login flow (SSO, custom endpoints, multi-step logins), or false to force them. IP block checks still apply.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_login_intent_ttl</code> (filter, since 5.7.14; receives <code>$ttl</code>): how long, in seconds, a login intent token stays valid after the login page loads or a password step succeeds. Default 600 (10 minutes), kept between one minute and one hour.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_login_checks_waived</code> (action, since 5.7.14; receives <code>$reason</code>, <code>$user</code>, <code>$post</code>): fires when the login checks were waived, with the reason <code>second_step</code>, <code>skipped_by_filter</code> or <code>bypassed_by_intent</code>.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
			</ul>
		</div>
	</div>

	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Visitor IP addresses, proxies and CDNs', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<ul>
				<li><?php echo wp_kses( __( '<code>zerospam_trusted_proxies</code> (filter; receives <code>$proxies</code>): IP addresses and CIDR ranges whose forwarded headers are trusted, in addition to Cloudflare. It starts with the Trusted Proxy IPs setting, so add to the array rather than replacing it. Ranges broader than /8 (IPv4) or /32 (IPv6) are ignored.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_allow_private_forwarded_ip</code> (filter, since 5.7.14; receives <code>$allow</code>): return true to accept a private or reserved address forwarded by a trusted proxy as the visitor IP, for example on an intranet. Default false.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_shared_ip_warning</code> (filter, since 5.7.14; receives <code>$verdict</code>): the warning shown when many visitors appear to share one IP address, or false. Return false to never show it.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_cloudflare_ip_ranges</code> (filter; receives <code>$ranges</code>): the IP ranges Cloudflare connects from.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_trust_country_headers</code> (filter; receives <code>$trust</code>): return true to trust the <code>CF-IPCountry</code> and <code>X-Forwarded-Country</code> headers from any request. Only for sites that every request reaches through a proxy that overwrites them.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_get_ip</code> (filter; receives <code>$ip</code>): the visitor IP address Zero Spam detected.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
			</ul>
		</div>
	</div>

	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Lockout rescue and .htaccess', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<ul>
				<li><?php echo wp_kses( __( '<code>zerospam_rescue_form_enabled</code> (filter, since 5.7.14; receives <code>$enabled</code>): return false to hide the "Help for site owners" rescue form on the blocked page; form requests then send nothing.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_rescue_email_via_cron</code> (filter, since 5.7.14; receives <code>$use_cron</code>): send rescue emails from a WP-Cron event (true) or at the end of the request (false). Default true unless DISABLE_WP_CRON is set.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_admin_safe_ip_enabled</code> (filter, since 5.7.14; receives <code>$enabled</code>, <code>$ip</code>): return false to stop saving the activating or updating administrator\'s IP address as one Zero Spam never locks out.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
				<li><?php echo wp_kses( __( '<code>zerospam_htaccess_file</code> (filter, since 5.7.14; receives <code>$htaccess_file</code>): the full path of the .htaccess file Zero Spam writes its deny rules to. Default: .htaccess in the WordPress home directory.', 'zero-spam' ), $zerospam_hook_kses ); ?></li>
			</ul>
		</div>
	</div>

	<p class="zerospam-docs-more">
		<?php
		printf(
			wp_kses(
				/* translators: %s: URL of the Hooks page on the GitHub wiki */
				__( 'Every other filter and action is listed on the <a href="%s">Hooks page of the GitHub wiki</a>.', 'zero-spam' ),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			),
			esc_url( 'https://github.com/Highfivery/zero-spam-for-wordpress/wiki/Hooks' )
		);
		?>
	</p>
</div>
