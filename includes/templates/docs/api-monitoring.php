<?php
/**
 * API Monitoring Documentation
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();
?>

<div class="zerospam-docs-section" id="api-monitoring-docs">
	<h2><?php esc_html_e( 'API Usage Monitoring & Alerts', 'zero-spam' ); ?></h2>
	
	<p class="zerospam-docs-intro">
		<?php esc_html_e( 'Track your Zero Spam Enhanced Protection usage in real-time with beautiful dashboards, proactive alerts, and powerful analytics. Never get caught off guard by quota limits again!', 'zero-spam' ); ?>
	</p>

	<!-- What is API Monitoring -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'What is API Monitoring?', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p>
				<?php esc_html_e( 'API Monitoring tracks your Zero Spam Enhanced Protection usage in real-time, helping you understand how many API calls your site makes, how well caching is working, and when you might hit quota limits.', 'zero-spam' ); ?>
			</p>
			
			<div class="zerospam-docs-note">
				<strong><?php esc_html_e( 'Think of it like a fuel gauge:', 'zero-spam' ); ?></strong>
				<p>
					<?php esc_html_e( 'Just like your car shows how much gas is left before you need to refill, API Monitoring shows how many API calls you have left before your monthly quota resets. It also warns you before you run out, so you\'re never caught off guard!', 'zero-spam' ); ?>
				</p>
			</div>

			<h3><?php esc_html_e( 'Key Features', 'zero-spam' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Real-Time Tracking:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'See exactly how many API calls your site makes', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Quota Monitoring:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Visual meter showing how much of your monthly quota is used', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Cache Performance:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Track how often cached data is used vs. new API calls', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Proactive Alerts:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Get notified before hitting limits via email, dashboard notices, or webhooks', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Historical Trends:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'View hourly and daily usage patterns to plan for traffic spikes', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Anomaly Detection:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Automatically detect unusual usage spikes, error rates, or slow responses', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Getting Started -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Getting Started (5 Minutes)', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Follow these simple steps to start monitoring your API usage:', 'zero-spam' ); ?></p>
			
			<ol class="zerospam-docs-steps">
				<li>
					<strong><?php esc_html_e( 'Enable Monitoring', 'zero-spam' ); ?></strong>
					<p>
						<?php
						printf(
							/* translators: %s: settings page URL */
							wp_kses_post( __( 'Go to <a href="%s">Settings → API Monitoring</a> and enable "API Usage Monitoring"', 'zero-spam' ) ),
							esc_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring' ) )
						);
						?>
					</p>
				</li>

				<li>
					<strong><?php esc_html_e( 'View Dashboard Widget', 'zero-spam' ); ?></strong>
					<p>
						<?php
						printf(
							/* translators: %s: dashboard URL */
							wp_kses_post( __( 'Check your <a href="%s">WordPress Dashboard</a> for the new "Zero Spam API Usage" widget showing real-time statistics', 'zero-spam' ) ),
							esc_url( admin_url( 'index.php' ) )
						);
						?>
					</p>
				</li>

				<li>
					<strong><?php esc_html_e( 'Configure Alerts (Optional)', 'zero-spam' ); ?></strong>
					<p>
						<?php esc_html_e( 'Enable email alerts, admin notices, or webhooks to get notified when issues are detected. All alerts are OFF by default.', 'zero-spam' ); ?>
					</p>
				</li>
			</ol>
		</div>
	</div>

	<!-- Dashboard Widget -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Understanding the Dashboard Widget', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'The dashboard widget gives you an at-a-glance view of your API usage. Here\'s what each section means:', 'zero-spam' ); ?></p>
			
			<h3><?php esc_html_e( 'Quota Meter', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'The quota meter shows how much of your monthly API quota you\'ve used:', 'zero-spam' ); ?></p>
			<ul>
				<li><strong style="color: #2271b1;"><?php esc_html_e( 'Blue:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Healthy (less than 80% used)', 'zero-spam' ); ?></li>
				<li><strong style="color: #dba617;"><?php esc_html_e( 'Yellow:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Warning (80-90% used)', 'zero-spam' ); ?></li>
				<li><strong style="color: #d63638;"><?php esc_html_e( 'Red:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Critical (over 90% used)', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Statistics Grid', 'zero-spam' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'API Calls:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Number of actual calls made to Zero Spam API (counts against quota)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Cache Hits:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Number of times cached data was used (saves API calls)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Errors:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Number of failed API requests (may indicate connectivity issues)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Avg Response:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Average API response time in milliseconds (lower is better)', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Performance Indicators', 'zero-spam' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Cache Efficiency:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Percentage of requests served from cache (higher is better, 70%+ is excellent)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Error Rate:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Percentage of failed requests (lower is better, <5% is healthy)', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Hourly Activity Chart', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'The bar chart shows today\'s API activity by hour. Blue bars represent API calls, green bars represent cache hits. This helps you identify peak traffic times.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<!-- Alert Configuration -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Configuring Alerts', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Alerts notify you when issues are detected. All alerts are OFF by default - you choose which ones to enable.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Available Alert Types', 'zero-spam' ); ?></h3>
			
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Alert Type', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'What It Detects', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Default Threshold', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Quota Warning', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'API quota usage exceeds threshold', 'zero-spam' ); ?></td>
						<td>80%</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Quota Critical', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'API quota critically low', 'zero-spam' ); ?></td>
						<td>90%</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Usage Spike', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'Daily usage significantly exceeds baseline', 'zero-spam' ); ?></td>
						<td>300%</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'High Error Rate', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'Failed requests exceed threshold', 'zero-spam' ); ?></td>
						<td>10%</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Slow Response', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( 'Average API response time too high', 'zero-spam' ); ?></td>
						<td>5000ms</td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Alert Channels', 'zero-spam' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Email Alerts:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Sent to your site administrator email', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Admin Notices:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Displayed in WordPress admin dashboard', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Webhooks:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'JSON payload sent to your webhook URL (Slack, Discord, PagerDuty, etc.)', 'zero-spam' ); ?></li>
			</ul>

			<div class="zerospam-docs-note">
				<p>
					<strong><?php esc_html_e( 'Alert Throttling:', 'zero-spam' ); ?></strong>
					<?php esc_html_e( 'To prevent alert fatigue, warning-level alerts are limited to 1 email per 24 hours. Critical alerts are sent immediately every time.', 'zero-spam' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- REST API -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Using the REST API', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Access your API usage data programmatically for external monitoring tools, custom dashboards, or automated reporting.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Get Usage Statistics', 'zero-spam' ); ?></h3>
			
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="api-usage-curl">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="api-usage-curl"><code>curl -X GET \
	-u "WP_USER:WP_APP_PASSWORD" \
	"<?php echo esc_url( site_url( '/wp-json/zero-spam/v1/api-usage?period=today' ) ); ?>"</code></pre>
			</div>

			<h3><?php esc_html_e( 'Available Parameters', 'zero-spam' ); ?></h3>
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parameter', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Options', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Description', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>period</code></td>
						<td>today, yesterday, week, month, all</td>
						<td><?php esc_html_e( 'Time period for statistics', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><code>scope</code></td>
						<td>site, network</td>
						<td><?php esc_html_e( 'Site or network-wide (multisite only)', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><code>site_id</code></td>
						<td><?php esc_html_e( 'integer', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Specific site ID (multisite only)', 'zero-spam' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Export Data as CSV', 'zero-spam' ); ?></h3>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="api-export-curl">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="api-export-curl"><code>curl -X GET \
	-u "WP_USER:WP_APP_PASSWORD" \
	"<?php echo esc_url( site_url( '/wp-json/zero-spam/v1/api-usage/export?format=csv&period=month' ) ); ?>" \
	> usage-report.csv</code></pre>
			</div>
		</div>
	</div>

	<!-- WP-CLI Commands -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'WP-CLI Commands', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Manage API monitoring from the command line - perfect for automation, cron jobs, or server monitoring.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'View Usage Statistics', 'zero-spam' ); ?></h3>
			<div class="zerospam-docs-code-block">
				<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-usage">
					<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
				</button>
				<pre id="cli-usage"><code># Show today's usage
wp zerospam api_usage

# Show weekly usage in JSON format
wp zerospam api_usage --period=week --format=json

# Show network-wide usage (multisite)
wp zerospam api_usage --scope=network

# Export as CSV
wp zerospam api_usage --format=csv > usage.csv</code></pre>
			</div>

			<h3><?php esc_html_e( 'Available Options', 'zero-spam' ); ?></h3>
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Option', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Values', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Description', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>--period</code></td>
						<td>today, yesterday, week, month, all</td>
						<td><?php esc_html_e( 'Time period for statistics', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><code>--format</code></td>
						<td>table, json, csv, yaml</td>
						<td><?php esc_html_e( 'Output format', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><code>--scope</code></td>
						<td>site, network</td>
						<td><?php esc_html_e( 'Site or network-wide (multisite)', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><code>--site</code></td>
						<td><?php esc_html_e( 'integer', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Specific site ID (multisite)', 'zero-spam' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Multisite Support -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Multisite Support', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'API Monitoring works seamlessly across WordPress multisite networks with proper permission handling.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Permission Levels', 'zero-spam' ); ?></h3>
			
			<h4><?php esc_html_e( 'Super Admin', 'zero-spam' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'View network-wide aggregated usage', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'View per-site breakdown', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Configure network-level alerts', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Receive network quota alerts', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Access all sites via REST API/CLI', 'zero-spam' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'Site Admin', 'zero-spam' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'View their site\'s usage only', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Configure site-level alerts', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Receive alerts for their site', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Access their site data via REST API/CLI', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Cannot see other sites\' data', 'zero-spam' ); ?></li>
			</ul>

			<div class="zerospam-docs-note">
				<p>
					<strong><?php esc_html_e( 'Important:', 'zero-spam' ); ?></strong>
					<?php esc_html_e( 'Quota is shared across the entire network in multisite installations. All sites contribute to the same API quota limit.', 'zero-spam' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Troubleshooting -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Troubleshooting', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Dashboard widget not showing', 'zero-spam' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Check that "Enable API Usage Monitoring" is turned ON in settings', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Verify you have the "manage_options" capability', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Check Dashboard → Screen Options to ensure the widget is not hidden', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Not receiving email alerts', 'zero-spam' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Verify "Email Alerts" is enabled in API Monitoring settings', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Check that the specific alert type is enabled (e.g., "Quota Warning")', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Confirm your threshold hasn\'t been exceeded recently (alerts throttled to 1 per 24 hours)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Check your spam folder or email server logs', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Test email functionality with the "Send Test Email" button', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Data not being tracked', 'zero-spam' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Ensure "Enable API Usage Monitoring" is turned ON', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Verify Enhanced Protection is enabled with a valid license key', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Check that your site is making API calls (browse your site to trigger checks)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Review error logs for any database issues', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'High quota usage unexpectedly', 'zero-spam' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Check hourly chart to identify peak usage times', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Review cache efficiency - low cache hit rates mean more API calls', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Consider increasing cache expiration time in Enhanced Protection settings', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Check for bots or crawlers hitting your site repeatedly', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- FAQ -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Frequently Asked Questions', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Does monitoring affect site performance?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'No. Tracking is extremely lightweight (single database insert per API call) and statistics queries are cached for 1 hour. The performance impact is negligible.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'What happens when I hit my quota limit?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Zero Spam API will stop accepting new requests until your quota resets (monthly or yearly depending on your plan). Your site will continue to work, but Enhanced Protection checks will be temporarily disabled. Cached results will still be used.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Can I pause monitoring temporarily?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Yes, simply disable "Enable API Usage Monitoring" in settings. Existing data will be preserved, but new API calls won\'t be tracked.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Can I export usage data for reports?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Yes! Use the REST API export endpoint or WP-CLI commands to export data in CSV or JSON format. Perfect for creating custom reports or analysis.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Do cache hits count against my quota?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'No! Cache hits use locally stored data and don\'t make API calls. This is why high cache efficiency is important - it reduces API usage and quota consumption.', 'zero-spam' ); ?></p>
		</div>
	</div>
</div>
