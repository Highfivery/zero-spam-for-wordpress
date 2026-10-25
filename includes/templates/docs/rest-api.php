<?php
/**
 * REST API Documentation Template
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

$site_url = esc_url( get_site_url() );
?>

<div class="zerospam-docs-section" id="rest-api-docs">
	<h2><?php esc_html_e( 'REST API for Settings Management', 'zero-spam' ); ?></h2>
	
	<p class="zerospam-docs-intro">
		<?php esc_html_e( 'The Zero Spam REST API lets you read and update plugin settings remotely — perfect for automating configuration, syncing between environments, and managing multiple sites. No coding expertise required!', 'zero-spam' ); ?>
	</p>

	<!-- Quick Start -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Quick Start Guide (5 Minutes)', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Follow these simple steps to make your first API call:', 'zero-spam' ); ?></p>
			
			<ol class="zerospam-docs-steps">
				<li>
					<strong><?php esc_html_e( 'Create an Application Password', 'zero-spam' ); ?></strong>
					<p><?php esc_html_e( 'Think of this as a "spare key" for your WordPress site — it lets the API access your settings without using your main password.', 'zero-spam' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Go to Users → Profile in your WordPress admin', 'zero-spam' ); ?></li>
						<li><?php esc_html_e( 'Scroll down to "Application Passwords" section', 'zero-spam' ); ?></li>
						<li><?php esc_html_e( 'Enter a name like "Zero Spam API" and click "Add New Application Password"', 'zero-spam' ); ?></li>
						<li><?php esc_html_e( 'Copy the generated password (it looks like: xxxx xxxx xxxx xxxx xxxx xxxx)', 'zero-spam' ); ?></li>
						<li><?php esc_html_e( 'Save it somewhere safe — you won\'t be able to see it again!', 'zero-spam' ); ?></li>
					</ul>
				</li>
				
				<li>
					<strong><?php esc_html_e( 'Test the API', 'zero-spam' ); ?></strong>
					<p><?php esc_html_e( 'Open your computer\'s Terminal (Mac/Linux) or Command Prompt (Windows) and paste this command:', 'zero-spam' ); ?></p>
					<div class="zerospam-docs-code-block">
						<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="quick-start-get">
							<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
						</button>
						<pre id="quick-start-get"><code>curl -u "YOUR_USERNAME:YOUR_APP_PASSWORD" \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings"</code></pre>
					</div>
					<p class="zerospam-docs-note">
						<strong><?php esc_html_e( 'Replace:', 'zero-spam' ); ?></strong>
					</p>
					<ul>
						<li><code>YOUR_USERNAME</code> <?php esc_html_e( '— Your WordPress username', 'zero-spam' ); ?></li>
						<li><code>YOUR_APP_PASSWORD</code> <?php esc_html_e( '— The application password you just created (remove spaces)', 'zero-spam' ); ?></li>
					</ul>
				</li>
				
				<li>
					<strong><?php esc_html_e( 'Success!', 'zero-spam' ); ?></strong>
					<p><?php esc_html_e( 'If everything worked, you\'ll see your current settings displayed as JSON data. Congratulations — you just made your first API call!', 'zero-spam' ); ?></p>
				</li>
			</ol>
		</div>
	</div>

	<!-- Authentication Setup -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Understanding Authentication', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'What are Application Passwords?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Think of Application Passwords like spare keys to your house:', 'zero-spam' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Your main password is the master key you keep safe', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Application Passwords are like copies you give to trusted services', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'If a spare key gets lost, you can delete it without changing your master key', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Each spare key (app password) can be revoked independently', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Required Permissions', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'To use the API, your WordPress user account needs:', 'zero-spam' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Single Site:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Administrator role (to manage site settings)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Multisite:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Super Admin role (to manage network settings)', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Security Best Practices', 'zero-spam' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Create separate Application Passwords for each use case (production, staging, CI/CD)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Use descriptive names so you remember what each password is for', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Revoke passwords you\'re no longer using', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Store passwords securely (use environment variables, not hardcoded in scripts)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Never commit Application Passwords to version control (Git, etc.)', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- API Reference -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Complete API Reference', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Base URL', 'zero-spam' ); ?></h3>
			<div class="zerospam-docs-code-block">
				<pre><code><?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1</code></pre>
			</div>

			<hr class="zerospam-docs-divider">

			<h3><?php esc_html_e( 'GET /settings — Read Current Settings', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Retrieves the current Enhanced Protection settings for your site.', 'zero-spam' ); ?></p>

			<h4><?php esc_html_e( 'Query Parameters', 'zero-spam' ); ?></h4>
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parameter', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Description', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>scope</code></td>
						<td>string</td>
						<td>
							<?php esc_html_e( 'Which settings to show:', 'zero-spam' ); ?>
							<ul>
								<li><code>resolved</code> <?php esc_html_e( '(default) — Final settings after merging all levels', 'zero-spam' ); ?></li>
								<li><code>site</code> <?php esc_html_e( '— Only site-specific overrides', 'zero-spam' ); ?></li>
								<li><code>network</code> <?php esc_html_e( '— Only network defaults (multisite only)', 'zero-spam' ); ?></li>
							</ul>
						</td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Example Request', 'zero-spam' ); ?></h4>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="get-example">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="get-example"><code>curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings?scope=resolved"</code></pre>
			</div>

			<h4><?php esc_html_e( 'Example Response', 'zero-spam' ); ?></h4>
			<div class="zerospam-docs-code-block">
				<pre><code>{
	"zerospam": "enabled",
	"zerospam_confidence_min": 30,
	"meta": {
	"scope": "resolved",
	"is_multisite": false,
	"sources": {
		"zerospam": "site",
		"zerospam_confidence_min": "default"
	}
	}
}</code></pre>
			</div>

			<hr class="zerospam-docs-divider">

			<h3><?php esc_html_e( 'PATCH /settings — Update Settings', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Updates one or more Enhanced Protection settings.', 'zero-spam' ); ?></p>

			<h4><?php esc_html_e( 'Query Parameters', 'zero-spam' ); ?></h4>
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parameter', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Description', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>scope</code></td>
						<td>string</td>
						<td>
							<?php esc_html_e( 'Where to save the settings:', 'zero-spam' ); ?>
							<ul>
								<li><code>site</code> <?php esc_html_e( '(default) — Save to this site', 'zero-spam' ); ?></li>
								<li><code>network</code> <?php esc_html_e( '— Save as network defaults (multisite only)', 'zero-spam' ); ?></li>
							</ul>
						</td>
					</tr>
					<tr>
						<td><code>dry_run</code></td>
						<td>boolean</td>
						<td><?php esc_html_e( 'If true (1), validates changes without saving them. Perfect for testing!', 'zero-spam' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Request Body (JSON)', 'zero-spam' ); ?></h4>
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Description', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>zerospam</code></td>
						<td>string</td>
						<td>
							<?php esc_html_e( 'Enhanced Protection status:', 'zero-spam' ); ?>
							<ul>
								<li><code>"enabled"</code> <?php esc_html_e( '— Turn on protection', 'zero-spam' ); ?></li>
								<li><code>false</code> <?php esc_html_e( '— Turn off protection', 'zero-spam' ); ?></li>
							</ul>
						</td>
					</tr>
					<tr>
						<td><code>zerospam_confidence_min</code></td>
						<td>number</td>
						<td><?php esc_html_e( 'Confidence threshold (0-100). Higher = stricter. Recommended: 30', 'zero-spam' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Example: Dry Run (Test Before Saving)', 'zero-spam' ); ?></h4>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="patch-dry-run">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="patch-dry-run"><code>curl -X PATCH \
	-u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
	-H "Content-Type: application/json" \
	-d '{"zerospam":"enabled","zerospam_confidence_min":50}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings?dry_run=1"</code></pre>
			</div>

			<h4><?php esc_html_e( 'Example: Save Settings', 'zero-spam' ); ?></h4>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="patch-save">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="patch-save"><code>curl -X PATCH \
	-u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
	-H "Content-Type: application/json" \
	-d '{"zerospam":"enabled","zerospam_confidence_min":50}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings"</code></pre>
			</div>

			<h4><?php esc_html_e( 'Example Response (Success)', 'zero-spam' ); ?></h4>
			<div class="zerospam-docs-code-block">
				<pre><code>{
	"success": true,
	"changes": {
	"zerospam_confidence_min": {
		"old": 30,
		"new": 50
	}
	},
	"settings": {
	"zerospam": "enabled",
	"zerospam_confidence_min": 50
	},
	"meta": {
	"scope": "site",
	"is_multisite": false
	}
}</code></pre>
			</div>

			<h4><?php esc_html_e( 'Multisite: the "source" Field', 'zero-spam' ); ?></h4>
			<p><?php esc_html_e( 'On a network site, each entry in "changes" also carries a "source" field. "site" means the value is now stored as this site\'s own override. "network_default" means the value you sent equals the network value, so nothing is stored and the site keeps following the network default (and any later change to it).', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>{
	"success": true,
	"changes": {
	"zerospam_confidence_min": {
		"old": 30,
		"new": 40,
		"source": "network_default"
	}
	}
}</code></pre>
			</div>

			<h4><?php esc_html_e( 'Multisite: Locked Settings (403)', 'zero-spam' ); ?></h4>
			<p><?php esc_html_e( 'Settings locked by the Network Administrator cannot be changed with scope=site. If the request includes any locked key, the whole request is refused and nothing is saved. The response lists the locked keys so your script can drop them and retry.', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>HTTP/1.1 403 Forbidden
{
	"code": "rest_setting_locked",
	"message": "These settings are locked by the network administrator: zerospam_confidence_min.",
	"data": {
	"status": 403,
	"locked_keys": ["zerospam_confidence_min"]
	}
}</code></pre>
			</div>
		</div>
	</div>

	<!-- Use Cases -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Real-World Use Cases', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Use Case 1: Sync Settings from Production to Staging', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Copy your production settings to your staging site:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code># Step 1: Get production settings
curl -u "admin:PROD_PASSWORD" \
	"https://mysite.com/wp-json/zero-spam/v1/settings" > settings.json

# Step 2: Apply to staging
curl -X PATCH \
	-u "admin:STAGING_PASSWORD" \
	-H "Content-Type: application/json" \
	-d @settings.json \
	"https://staging.mysite.com/wp-json/zero-spam/v1/settings"</code></pre>
			</div>

			<h3><?php esc_html_e( 'Use Case 2: Automated Deployment Script', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Configure Zero Spam as part of your deployment:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>#!/bin/bash
# deploy.sh

# Enable protection after deployment
curl -X PATCH \
	-u "$WP_USER:$WP_APP_PASSWORD" \
	-H "Content-Type: application/json" \
	-d '{"zerospam":"enabled","zerospam_confidence_min":30}' \
	"$SITE_URL/wp-json/zero-spam/v1/settings"

echo "Zero Spam configured successfully!"</code></pre>
			</div>

			<h3><?php esc_html_e( 'Use Case 3: Test Changes Before Applying', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Use dry-run mode to validate settings:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code># Test with strict threshold
curl -X PATCH \
	-u "admin:APP_PASSWORD" \
	-H "Content-Type: application/json" \
	-d '{"zerospam_confidence_min":80}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings?dry_run=1"

# If validation passes, apply for real
curl -X PATCH \
	-u "admin:APP_PASSWORD" \
	-H "Content-Type: application/json" \
	-d '{"zerospam_confidence_min":80}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings"</code></pre>
			</div>

			<h3><?php esc_html_e( 'Use Case 4: JavaScript/Frontend Integration', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Call the API from JavaScript:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>// Read settings
const response = await fetch('<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings', {
	headers: {
	'Authorization': 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx xxxx xxxx')
	}
});
const settings = await response.json();
console.log(settings);

// Update settings
const updateResponse = await fetch('<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings', {
	method: 'PATCH',
	headers: {
	'Authorization': 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx xxxx xxxx'),
	'Content-Type': 'application/json'
	},
	body: JSON.stringify({
	zerospam: 'enabled',
	zerospam_confidence_min: 40
	})
});
const result = await updateResponse.json();
console.log(result);</code></pre>
			</div>
		</div>
	</div>

	<!-- Since 6.0 -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Every setting, the license and your preferences (since 6.0)', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Every setting through /settings', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'GET and PATCH /settings accept every Zero Spam setting that holds a value, not only Enhanced Protection. Values are checked against the setting\'s type, choices and limits. The zero_spam_rest_settings_whitelist filter can still add or remove settings.', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'License keys, API keys and the alert webhook URL are write-only: PATCH stores them, but reads return only whether one is set, where it comes from (site, network, locked or constant) and, for a key this site stores, its last four characters. On a network site, keys set by the network or in wp-config.php are never hinted.', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'Network values come from the Network Settings store, the same one the Network Settings screen and WP-CLI use. A locked network value wins over a site override and is reported with the source "locked".', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'GET /settings/schema — Describe Every Setting', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Lists every section and setting with its type, choices, limits, default, recommended value, where it belongs in the admin, whether it can be or is locked, where its value comes from, and its value (secrets masked). Administrators only.', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>curl -u "admin:APP_PASSWORD" \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings/schema"</code></pre>
			</div>

			<h3><?php esc_html_e( 'GET, PUT and DELETE /license — The Zero Spam License', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'GET returns the license status (none, valid, invalid, unreachable or quota_exceeded), the plan, query counts and the masked key; add refresh=1 to ask the Zero Spam API again. PUT with {"license_key": "…"} checks the key and saves it (a rejected key is not saved). DELETE removes the key this site stores; a key set by the network or in wp-config.php is refused with 409. Administrators only.', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>curl -X PUT -u "admin:APP_PASSWORD" \
	-H "Content-Type: application/json" \
	-d '{"license_key":"YOUR-LICENSE-KEY"}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/license"</code></pre>
			</div>

			<h3><?php esc_html_e( 'GET and PATCH /ui/prefs — Your Admin Preferences', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Used by the Zero Spam admin screens and may change between releases. Any logged-in user reads and changes their own preferences: theme (light, dark or auto), reduce_motion and live_paused (true or false), dismissed (a list of IDs), whats_new_seen (a version number) and onboarding_step (0 to 4).', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Errors', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Invalid input is answered with 400 and data.params, one message per parameter (PATCH /settings keeps its rest_invalid_settings code and errors list as well). A locked setting is answered with 403 rest_setting_locked and data.locked_keys. List routes accept at most 100 items per page and return the X-WP-Total and X-WP-TotalPages headers.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<!-- Data routes, since 6.0 -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Activity, statistics, rules, protection, actions, self-test and news (since 6.0)', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'These routes give the Zero Spam admin screens their data and work for your own tools too. They answer administrators of the current site only, and on a network every site sees its own data only. Lists accept page and per_page (1 to 100) and return the X-WP-Total, X-WP-TotalPages and X-ZeroSpam-Page headers; a page past the last one returns the last page. Every route accepts _fields.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'GET /stats/summary and /stats/timeseries — Statistics', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'stats/summary?range=1, 7 or 30 (days) returns the detections of the period and of the period before it, the difference, the detections by type, the top countries, the busiest day, the daily average and how full the activity log is (entries and the Maximum Log Entries setting). stats/timeseries?range=1 to 30 returns every day of the period, today last, split by type, and the detections per hour of the day. Rescue-link entries are not detections and are left out. Times are in the site\'s time zone. Answers are cached for a minute (the summary) or five minutes (the time series) and refreshed as soon as a new detection is logged.', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>curl -u "admin:APP_PASSWORD" \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/stats/summary?range=7"</code></pre>
			</div>

			<h3><?php esc_html_e( 'GET /activity — The Activity Log', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Filters: type[] (detection types), country[] (two-letter codes), ip (any form of the address), after and before (a date such as 2026-10-01, which as before includes that day, or an ISO 8601 time), and search (IP address, page address, country, region or city). Sort with orderby (date_recorded, log_type, user_ip, country or region) and order (asc or desc). Every entry says whether its IP address is blocked right now. since_id returns only the entries newer than that ID, newest first and without a total, for live updates. facets=1 returns, instead of entries, the number of entries per type, country and IP address (the ten with most entries) for the same filters; each facet ignores its own filter.', 'zero-spam' ); ?></p>
			<p><?php esc_html_e( 'GET /activity/{id} adds the submitted data as it was logged (it was cleaned when it was saved; escape it before showing it), the geolocation, the failed check, the IP address\'s block and a link to the location on Google Maps. DELETE /activity/{id} deletes one entry. POST /activity/bulk-delete with {"ids": [...]} deletes up to 500 entries; {"all": true, "confirm": true} empties the log and cannot be undone.', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>curl -u "admin:APP_PASSWORD" \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/activity?type[]=comment&amp;after=2026-10-01&amp;per_page=50"</code></pre>
			</div>

			<h3><?php esc_html_e( 'POST /undo/{token} — Undo a Deletion', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Deleting activity entries or a block returns an undo_token. Within two minutes, the same user on the same site can send it to /undo/{token} once to put everything back with its original IDs.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( '/rules/blocks — Blocked IP Addresses and Locations', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'GET lists the IP blocks (kind=ip, the default) or the location blocks (kind=location, optionally one key_type), with search on the address, the location, the country name or the reason. POST adds a block: {"ip": "203.0.113.5", "type": "permanent"} or {"kind": "location", "key_type": "country_code", "key": "DE", "type": "temporary", "end": "2026-12-31"}, with an optional start and reason. Addresses are stored in one canonical form; ranges are not supported for blocks. A temporary block needs an end in the future. Blocking an address or location that is already blocked replaces its block. GET, PUT and DELETE /rules/blocks/{id} read, change and remove one block, and POST /rules/blocks/bulk-delete with {"ids": [...]} removes up to 500; deleting returns an undo_token. Block dates are UTC. When the IP block method is an .htaccess option, the rules are rewritten after every change.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( '/rules/lists/{list} — Allowlist, Email Domains and Allowed Words', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'The lists are allowed-ips (the IP Allowlist), email-domains (blocked email domains) and allowed-words. GET returns a page of the entries (search to filter), the total, and whether the list is locked by the network administrator; allowed-words also returns min_length, the shortest disallowed word checked. PUT replaces the list with {"items": [...]} or changes it with {"add": [...], "remove": [...]}; allowed-words also accepts {"min_length": 4}. Invalid entries are listed in the error and nothing is saved. Changes are stored and audited like PATCH /settings; a locked list answers 403 rest_setting_locked.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( '/rules/no-lockout — Addresses Never Locked Out', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'GET lists the administrator addresses Zero Spam never locks out on this site, marking the one you are using. DELETE /rules/no-lockout/{ip} removes one.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'GET /protection/modules — What Protects the Site', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Every settings section with whether it is on, the settings that switch it on, whether it needs an API key, its plugin and whether that plugin is active, which of its settings are locked, its last detection and its detections in the last seven days; integrations whose plugin is not active are listed as not available. attention lists what needs looking at: an active form plugin that is not protected, an activity log at least 90% full, visitors that seem to share one IP address, and a forwarding header from a proxy that is not trusted.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Settings actions, export and import', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'POST /settings/actions/{action} runs one of the settings screen\'s actions: autoconfigure, regenerate-honeypot, update-blocked-email-domains, update-disallowed-words, clear-error-log, test-alert-email, test-alert-notice or test-alert-webhook. Each answers with whether it worked and a message. Settings locked by the network administrator are never changed: autoconfigure skips them and lists them in skipped_locked. GET /settings/export returns every setting that holds a value, as the site uses it, grouped by module; license keys, API keys and webhook URLs are never exported. POST /settings/import accepts that export or the JSON from the 5.x Import/Export screen, up to 5 MB, checks every value like PATCH /settings, stores the valid ones and reports the rest (errors, ignored, skipped_locked).', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'POST /self-test — Test the Protection', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Checks the honeypot, the browser check, the lists, the reputation services that are on (Stop Forum Spam, and the Zero Spam network with Enhanced Protection, asked about a documentation address) and where visitor addresses come from, then reports pass, warn or fail for each with a message and how long it took. It writes nothing to the activity log and runs at most once a minute per site (429 with data.retry_after).', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'GET /news — Zero Spam News', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Returns the latest articles and security notices from zerospam.org as a list (title, excerpt, date, link, categories, security_notice), newest first, all plain text; limit is 1 to 8 (default 8). The feed is downloaded at most every six hours (once per network) with a generic user agent and no site data; after a failed download the last copy is served and the X-ZeroSpam-News-Stale header is 1 (X-ZeroSpam-News-Fetched says when it was downloaded). Answers 404 when the Zero Spam News setting is off or the zerospam_news_feed_enabled filter returns false, and 503 when no copy is available.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<!-- Multisite -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Multisite Settings Management', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'How Multisite Settings Work', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'In a WordPress Multisite network, settings follow a three-level hierarchy:', 'zero-spam' ); ?></p>
			
			<div class="zerospam-docs-diagram">
				<pre>
┌─────────────────────────────────────────────┐
│  1. Plugin Defaults (Built-in)             │
│     └─ zerospam_confidence_min: 30         │
└─────────────────────────────────────────────┘
					↓ (can be overridden by)
┌─────────────────────────────────────────────┐
│  2. Network Defaults (Set by Super Admin)   │
│     └─ Apply to all sites in network       │
└─────────────────────────────────────────────┘
					↓ (can be overridden by)
┌─────────────────────────────────────────────┐
│  3. Site Overrides (Set by Site Admin)      │
│     └─ Override network for this site only │
└─────────────────────────────────────────────┘
				</pre>
			</div>

			<p><?php esc_html_e( 'Think of it like household rules:', 'zero-spam' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Plugin defaults = universal rules everyone follows', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Network defaults = family rules for your household', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Site overrides = exceptions for individual family members', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Setting Network Defaults', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Super Admins can set network-wide defaults:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>curl -X PATCH \
	-u "superadmin:APP_PASSWORD" \
	-H "Content-Type: application/json" \
	-d '{"zerospam":"enabled","zerospam_confidence_min":40}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings?scope=network"</code></pre>
			</div>

			<h3><?php esc_html_e( 'Overriding for a Specific Site', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Site Admins can override network defaults:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>curl -X PATCH \
	-u "siteadmin:APP_PASSWORD" \
	-H "Content-Type: application/json" \
	-d '{"zerospam_confidence_min":60}' \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings?scope=site"</code></pre>
			</div>

			<h3><?php esc_html_e( 'Locked Settings', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'A Super Admin can lock a setting on the Network Settings page. Locked settings are shown as locked on every site\'s settings page, the settings page never saves them, and a scope=site REST request that includes one is refused with a 403 "rest_setting_locked" error (see the PATCH section above). WP-CLI "wp zerospam set" warns and exits with an error for a locked setting.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Checking Where Settings Come From', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Use the "sources" field in the response:', 'zero-spam' ); ?></p>
			<div class="zerospam-docs-code-block">
				<pre><code>{
	"zerospam": "enabled",
	"zerospam_confidence_min": 60,
	"meta": {
	"sources": {
		"zerospam": "network",
		"zerospam_confidence_min": "site"
	}
	}
}</code></pre>
			</div>
			<p><?php esc_html_e( 'This tells you "zerospam" comes from network defaults, but "zerospam_confidence_min" is overridden at the site level.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<!-- Troubleshooting -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Troubleshooting Common Issues', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Error: "rest_forbidden"', 'zero-spam' ); ?></h3>
			<p><strong><?php esc_html_e( 'What it means:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'You don\'t have permission to access this endpoint.', 'zero-spam' ); ?></p>
			<p><strong><?php esc_html_e( 'How to fix:', 'zero-spam' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Make sure you\'re using an Administrator account (or Super Admin for multisite)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Check that your Application Password is correct (copy-paste to avoid typos)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Remove spaces from the Application Password', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Error: "rest_not_multisite"', 'zero-spam' ); ?></h3>
			<p><strong><?php esc_html_e( 'What it means:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'You tried to use scope=network on a single site.', 'zero-spam' ); ?></p>
			<p><strong><?php esc_html_e( 'How to fix:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Remove the scope parameter or use scope=site', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Error: "rest_invalid_param"', 'zero-spam' ); ?></h3>
			<p><strong><?php esc_html_e( 'What it means:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'You sent an invalid value.', 'zero-spam' ); ?></p>
			<p><strong><?php esc_html_e( 'Common mistakes:', 'zero-spam' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'zerospam must be "enabled" (with quotes) or false (no quotes)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'zerospam_confidence_min must be a number between 0-100', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Error: "rest_update_failed"', 'zero-spam' ); ?></h3>
			<p><strong><?php esc_html_e( 'What it means:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'WordPress couldn\'t save the settings to the database.', 'zero-spam' ); ?></p>
			<p><strong><?php esc_html_e( 'How to fix:', 'zero-spam' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Check WordPress debug.log for database errors', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Verify your WordPress database is working correctly', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Contact your hosting provider if the problem persists', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Application Password Button Does Nothing', 'zero-spam' ); ?></h3>
			<p><strong><?php esc_html_e( 'Possible causes:', 'zero-spam' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Your site must use HTTPS (not HTTP) for Application Passwords to work', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Some hosting providers disable Application Passwords — check with your host', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Try a different browser or clear your browser cache', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Testing with curl Shows SSL Errors', 'zero-spam' ); ?></h3>
			<p><strong><?php esc_html_e( 'For local/development sites only:', 'zero-spam' ); ?></strong></p>
			<div class="zerospam-docs-code-block">
				<pre><code># Add --insecure flag (NOT recommended for production!)
curl --insecure -u "admin:APP_PASSWORD" \
	"<?php echo esc_url( $site_url ); ?>/wp-json/zero-spam/v1/settings"</code></pre>
			</div>
			<p class="zerospam-docs-warning">
				<strong><?php esc_html_e( 'Warning:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Never use --insecure on production sites. Fix your SSL certificate instead.', 'zero-spam' ); ?>
			</p>
		</div>
	</div>

	<!-- Glossary -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Glossary of Terms', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<dl class="zerospam-docs-glossary">
				<dt><?php esc_html_e( 'API (Application Programming Interface)', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A way for different programs to talk to each other. Like a waiter taking your order to the kitchen.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'REST API', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A specific type of API that uses standard web requests (GET, POST, PATCH, etc.) to read and modify data.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Application Password', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A special password that lets apps and scripts access your WordPress site without using your main password. Can be revoked anytime.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Endpoint', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A specific URL in an API that performs an action. Like /settings is an endpoint for managing settings.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'GET Request', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'An API request that reads data without changing anything. Like viewing a menu without ordering.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'PATCH Request', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'An API request that updates existing data. Like editing your address in a form.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'JSON (JavaScript Object Notation)', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A format for organizing data that both humans and computers can read. Looks like: {"setting": "value"}', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Dry Run', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'Test mode that checks if your changes are valid without actually saving them. Like a practice run.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Scope', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'In multisite, where settings are saved: "network" (applies to all sites) or "site" (only this site).', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Confidence Threshold', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A number (0-100) that controls how strict spam protection is. Higher = stricter = more likely to block.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Authentication', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'Proving who you are to access something. Like showing ID to enter a building.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'Authorization', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'Permission to do something. You might be authenticated (proven who you are) but not authorized (allowed) to perform an action.', 'zero-spam' ); ?></dd>

				<dt><?php esc_html_e( 'curl', 'zero-spam' ); ?></dt>
				<dd><?php esc_html_e( 'A command-line tool for making web requests. Pre-installed on Mac/Linux, available for Windows.', 'zero-spam' ); ?></dd>
			</dl>
		</div>
	</div>
</div>
