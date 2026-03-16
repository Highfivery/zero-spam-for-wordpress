<?php
/**
 * Network Settings Documentation
 *
 * In-plugin documentation for Network Settings feature.
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

?>

<div class="zerospam-docs-section">
	<h2><?php esc_html_e( 'Network Settings & Management', 'zero-spam' ); ?></h2>

	<div class="zerospam-docs-intro">
		<p><strong><?php esc_html_e( 'Overview:', 'zero-spam' ); ?></strong></p>
		<p><?php esc_html_e( 'The Network Settings feature allows Network Administrators to configure Zero Spam settings for all sites in a multisite network from a centralized location. Settings can be set as defaults, enforced (locked), or left customizable by site administrators.', 'zero-spam' ); ?></p>
	</div>

	<!-- Getting Started -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'Getting Started', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Accessing Network Settings', 'zero-spam' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Log in as Network Administrator', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Navigate to Network Admin → Settings → Zero Spam Network', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'You will see 6 tabs: Overview, Settings, Templates, Audit Log, Comparison, and Import/Export', 'zero-spam' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Quick Start: Apply a Template', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'The fastest way to configure your network:', 'zero-spam' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'Go to the Templates tab', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Choose a template (Strict, Balanced, or Relaxed)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Click "Apply to Network" to set network defaults', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Click "Apply to Sites" to update all sites immediately', 'zero-spam' ); ?></li>
			</ol>
		</div>
	</div>

	<!-- Settings Hierarchy -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'Understanding Settings Hierarchy', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Zero Spam uses a 4-level hierarchy to determine which value is used for each setting:', 'zero-spam' ); ?></p>
			
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Level', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Description', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Who Controls', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>1. Network Enforced (Locked)</strong></td>
						<td><?php esc_html_e( 'Highest', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Setting is locked by Network Admin. Cannot be changed by site admins.', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Network Admin only', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><strong>2. Site Override</strong></td>
						<td>2nd</td>
						<td><?php esc_html_e( 'Site admin has customized this setting (only if not locked).', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Site Admin', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><strong>3. Network Default</strong></td>
						<td>3rd</td>
						<td><?php esc_html_e( 'Network-wide default set by Network Admin (not locked).', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Network Admin', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><strong>4. Plugin Default</strong></td>
						<td><?php esc_html_e( 'Lowest', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Hard-coded default value from the plugin.', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Plugin developers', 'zero-spam' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Example:', 'zero-spam' ); ?></h4>
			<p><code>zerospam_confidence_min</code> <?php esc_html_e( 'setting:', 'zero-spam' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Plugin Default: 50', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Network Admin sets Network Default: 30 (unlocked)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Site 5 Admin customizes: 70 (Site Override)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Result: Site 5 uses 70, all other sites use 30', 'zero-spam' ); ?></li>
			</ul>

			<p><strong><?php esc_html_e( 'If Network Admin locks the setting at 30:', 'zero-spam' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'ALL sites must use 30 (enforced)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Site 5 Admin cannot change it', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Managing Settings -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'Managing Settings', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Setting a Network Default', 'zero-spam' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to the Settings tab', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Change the value for any setting', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Click "Save" for that setting', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'This becomes the network default (sites using defaults will inherit this value)', 'zero-spam' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Locking a Setting (Enforcing Network-Wide)', 'zero-spam' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In the Settings tab, find the setting you want to lock', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Click the "🔒 Lock" button', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'The button changes to "🔓 Unlock"', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Now ALL sites must use this value - site admins cannot change it', 'zero-spam' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Applying Settings to All Sites', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Click "Apply to All Sites" to update all sites immediately. You will see:', 'zero-spam' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Locked settings:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Always applied (enforced)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Unlocked settings:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Only applied to sites using defaults', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Sites with overrides:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Keep their custom values (unless setting is locked)', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Templates -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'Using Templates', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Built-in Templates', 'zero-spam' ); ?></h3>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Template', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Use Case', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Key Settings', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Strict Protection', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'High-risk sites, public forums, ecommerce', 'zero-spam' ); ?></td>
						<td>
							<?php esc_html_e( 'Confidence: 30%, All features enabled, API limit: 500/day', 'zero-spam' ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Balanced Protection', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'Most sites (recommended)', 'zero-spam' ); ?></td>
						<td>
							<?php esc_html_e( 'Confidence: 50%, Smart logging, API limit: 1000/day', 'zero-spam' ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Relaxed Protection', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'Development sites, low-risk environments', 'zero-spam' ); ?></td>
						<td>
							<?php esc_html_e( 'Confidence: 70%, Minimal logging, API limit: 5000/day', 'zero-spam' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Creating Custom Templates', 'zero-spam' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Configure your network settings as desired', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Go to Templates tab', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Scroll to "Create Custom Template"', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Enter name, slug, and description', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Click "Save Current Settings as Template"', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Your template is now available for reuse', 'zero-spam' ); ?></li>
			</ol>
		</div>
	</div>

	<!-- Site Admin View -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'Site Administrator Experience', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'What Site Admins See', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'When network settings are active, site administrators will see:', 'zero-spam' ); ?></p>

			<h4>🔒 <?php esc_html_e( 'Locked Settings', 'zero-spam' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Red "Locked by Network Admin" badge', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Field is disabled (grayed out)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Cannot be changed', 'zero-spam' ); ?></li>
			</ul>

			<h4>ℹ️ <?php esc_html_e( 'Network Default Settings', 'zero-spam' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Blue info box: "Using network default: [value]"', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Field is enabled - can customize if desired', 'zero-spam' ); ?></li>
			</ul>

			<h4>⚠️ <?php esc_html_e( 'Customized Settings', 'zero-spam' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Yellow warning box: "You have customized this setting"', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Shows network default for comparison', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Notification Emails', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Site admins receive email notifications when:', 'zero-spam' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'A setting they were using changes', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'A setting becomes locked or unlocked', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- WP-CLI -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'WP-CLI Commands', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'All commands for automation and power users:', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Basic Operations', 'zero-spam' ); ?></h3>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-basic">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="cli-basic"><code># List all settings
wp zerospam network-settings list

# Show specific setting
wp zerospam network-settings show zerospam

# Set a setting
wp zerospam network-settings set api_monitoring_enabled enabled

# Lock a setting
wp zerospam network-settings lock api_monitoring_enabled

# Unlock a setting
wp zerospam network-settings unlock api_monitoring_enabled</code></pre>
			</div>

			<h3><?php esc_html_e( 'Bulk Operations', 'zero-spam' ); ?></h3>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-bulk">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="cli-bulk"><code># Apply to all sites
wp zerospam network-settings apply-all

# Apply only locked settings
wp zerospam network-settings apply-all --mode=locked_only

# Apply to specific sites
wp zerospam network-settings apply-all --sites=2,5,8

# Reset a site to defaults
wp zerospam network-settings reset 5</code></pre>
			</div>

			<h3><?php esc_html_e( 'Templates', 'zero-spam' ); ?></h3>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-templates">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="cli-templates"><code># List templates
wp zerospam network-settings template-list

# Apply template
wp zerospam network-settings template-apply strict_protection

# Create template
wp zerospam network-settings template-create "My Config" my-config

# Delete template
wp zerospam network-settings template-delete my-config</code></pre>
			</div>

			<p><?php esc_html_e( 'See the full command reference with', 'zero-spam' ); ?> <code>wp help zerospam network-settings</code></p>
		</div>
	</div>

	<!-- Troubleshooting -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-btn" type="button">
			<span><?php esc_html_e( 'Troubleshooting', 'zero-spam' ); ?></span>
			<span class="zerospam-docs-accordion-icon">+</span>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Common Issues', 'zero-spam' ); ?></h3>

			<h4><?php esc_html_e( 'Settings not applying to sites', 'zero-spam' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Check if sites have custom overrides (Comparison tab)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Use "Apply to All Sites" with --force option to overwrite overrides', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Verify cache is cleared (transients expire after 1 hour)', 'zero-spam' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Site admin cannot change a setting', 'zero-spam' ); ?></h4>
			<p><?php esc_html_e( 'This is expected if the setting is locked. Go to Network Admin → Zero Spam Network → Settings and unlock the setting.', 'zero-spam' ); ?></p>

			<h4><?php esc_html_e( 'Notifications not sending', 'zero-spam' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Check your WordPress email configuration is working', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Verify WP-Cron is running (for weekly summaries)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Test with wp zerospam network-settings set command to trigger notification', 'zero-spam' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Getting Help', 'zero-spam' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Audit Log:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Check who changed what and when', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Comparison Tool:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Identify which sites have different settings', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Export:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Backup your configuration before making changes', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>
</div>
