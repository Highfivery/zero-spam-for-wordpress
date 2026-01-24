<?php
/**
 * Network Statistics Documentation
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

?>

<div class="zerospam-docs-section" id="network-statistics-docs">
	<h2><?php esc_html_e( 'Network Statistics & Insights', 'zero-spam' ); ?></h2>
	
	<p class="zerospam-docs-intro">
		<?php esc_html_e( 'Monitor and analyze spam activity across your WordPress Multisite network. Compare site performance, identify high-spam sites, and make data-driven decisions about Enhanced Protection.', 'zero-spam' ); ?>
	</p>

	<!-- Overview -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Overview', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Network Statistics provides powerful insights into spam activity across your entire WordPress Multisite network. Available exclusively for multisite installations with 2 or more sites.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Key Features', 'zero-spam' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Network-Wide Stats:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Total spam blocked, unique IPs, and spam types across all sites', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Site Comparison:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Rankings and detailed metrics for each site in your network', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Multi-Site Attackers:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Identify IPs targeting multiple sites with one-click network blocking', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Smart Recommendations:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Upgrade suggestions based on spam volume (high, medium, low)', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Dashboard Widget:', 'zero-spam' ); ?></strong> <?php esc_html_e( '3-tab interface showing Spam Activity, API Usage, and Combined Analysis', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'Statistics Page:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Dedicated network admin page with comparison tables and charts', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'CSV Export:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Download site comparison data for reporting and analysis', 'zero-spam' ); ?></li>
				<li><strong><?php esc_html_e( 'WP-CLI Commands:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'Automate monitoring and reporting from the command line', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Dashboard Widget -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Dashboard Widget', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'The Network Overview widget appears on your WordPress dashboard and provides quick insights across three tabs:', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Tab 1: Spam Activity', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'View network-wide spam statistics including total blocked, unique IPs, and top sites by spam count. For Network Admins, see a list of your top 5 sites receiving the most spam.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Tab 2: API Usage', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Monitor your API quota, cache efficiency, and response times. See today\'s usage with visual quota meter and performance indicators.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Tab 3: Combined Analysis', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Weekly summary showing both spam activity and API usage side-by-side with insights and recommendations.', 'zero-spam' ); ?></p>

			<div class="zerospam-docs-note">
				<strong><?php esc_html_e( 'Pro Tip:', 'zero-spam' ); ?></strong>
				<p><?php esc_html_e( 'Click the "Refresh" button to clear cached data and see real-time statistics.', 'zero-spam' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Statistics Page -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Statistics Page', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Access the full Network Statistics page from Network Admin → Settings → Zero Spam Stats.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Site Comparison Table', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Compare all sites in your network with the following metrics:', 'zero-spam' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Total spam blocked', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Unique IP addresses', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Top spam type (comments, registrations, etc.)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Top country of origin', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Trend (percentage change from previous period)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Protection level (Enhanced or Free)', 'zero-spam' ); ?></li>
				<li><?php esc_html_e( 'Recommendation level', 'zero-spam' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Multi-Site Attackers', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Identify IP addresses that have attempted spam on multiple sites in your network (last 7 days). Take action with one-click network-wide blocking.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Exporting Data', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Click "Export CSV" to download site comparison data for reporting or further analysis. The export includes all metrics shown in the comparison table.', 'zero-spam' ); ?></p>
		</div>
	</div>

	<!-- WP-CLI Commands -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'WP-CLI Commands', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Automate network statistics monitoring and reporting with WP-CLI commands.', 'zero-spam' ); ?></p>

		<h3><?php esc_html_e( 'Network Statistics', 'zero-spam' ); ?></h3>
		<p><?php esc_html_e( 'Display network-wide spam statistics:', 'zero-spam' ); ?></p>

		<div class="zerospam-docs-code-block">
			<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-network-stats">
				<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
			</button>
			<pre id="cli-network-stats"><code># Default (last 30 days)
wp zerospam network_stats

# Last 7 days
wp zerospam network_stats --period=week

# JSON output
wp zerospam network_stats --period=month --format=json</code></pre>
		</div>

		<h3><?php esc_html_e( 'Site Rankings', 'zero-spam' ); ?></h3>
		<p><?php esc_html_e( 'Display sites ranked by spam count:', 'zero-spam' ); ?></p>

		<div class="zerospam-docs-code-block">
			<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-site-rankings">
				<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
			</button>
			<pre id="cli-site-rankings"><code># Top 10 sites (default)
wp zerospam site_rankings

# Top 20 sites
wp zerospam site_rankings --limit=20

# CSV export
wp zerospam site_rankings --period=week --format=csv > sites.csv</code></pre>
		</div>

		<h3><?php esc_html_e( 'Multi-Site Attackers', 'zero-spam' ); ?></h3>
		<p><?php esc_html_e( 'Find IPs attacking multiple sites:', 'zero-spam' ); ?></p>

		<div class="zerospam-docs-code-block">
			<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-multi-site-attackers">
				<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
			</button>
			<pre id="cli-multi-site-attackers"><code># Default (2+ sites, last 7 days)
wp zerospam multi_site_attackers

# 3+ sites, last 14 days
wp zerospam multi_site_attackers --min-sites=3 --days=14

# JSON output
wp zerospam multi_site_attackers --format=json</code></pre>
		</div>

		<h3><?php esc_html_e( 'Backfill Aggregation Data', 'zero-spam' ); ?></h3>
		<p><?php esc_html_e( 'Pre-aggregate historical spam data for improved performance:', 'zero-spam' ); ?></p>

		<div class="zerospam-docs-code-block">
			<button class="zerospam-docs-copy-btn" type="button" data-clipboard-target="cli-backfill-stats">
				<?php esc_html_e( 'Copy', 'zero-spam' ); ?>
			</button>
			<pre id="cli-backfill-stats"><code># Backfill last 30 days (default)
wp zerospam backfill_stats

# Backfill last 90 days
wp zerospam backfill_stats --days=90</code></pre>
		</div>
			<p class="zerospam-docs-note"><strong><?php esc_html_e( 'Note:', 'zero-spam' ); ?></strong> <?php esc_html_e( 'This command aggregates historical data into optimized tables for faster dashboard and stats page loading. Run after initial installation or data cleanup.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Available Formats', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'All commands support multiple output formats:', 'zero-spam' ); ?></p>
			<ul>
				<li><code>table</code> - <?php esc_html_e( 'Formatted table (default, color-coded)', 'zero-spam' ); ?></li>
				<li><code>json</code> - <?php esc_html_e( 'JSON format for API integration', 'zero-spam' ); ?></li>
				<li><code>csv</code> - <?php esc_html_e( 'CSV format for spreadsheets', 'zero-spam' ); ?></li>
				<li><code>yaml</code> - <?php esc_html_e( 'YAML format', 'zero-spam' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Recommendations -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Understanding Recommendations', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<p><?php esc_html_e( 'Network Statistics automatically analyzes each site and provides upgrade recommendations based on spam volume.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Recommendation Levels', 'zero-spam' ); ?></h3>
			<table class="zerospam-docs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Level', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Criteria', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Action', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Strongly Recommended', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( '> 300 spam/month', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Upgrade to Enhanced Protection immediately', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Recommended', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( '100-300 spam/month', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Consider Enhanced Protection', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Consider', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( '50-100 spam/month', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'Monitor and evaluate', 'zero-spam' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'None', 'zero-spam' ); ?></strong></td>
						<td><?php esc_html_e( '< 50 spam/month or already using Enhanced', 'zero-spam' ); ?></td>
						<td><?php esc_html_e( 'No action needed', 'zero-spam' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- FAQs -->
	<div class="zerospam-docs-accordion">
		<button class="zerospam-docs-accordion-toggle" type="button">
			<span class="zerospam-docs-accordion-icon">▶</span>
			<strong><?php esc_html_e( 'Frequently Asked Questions', 'zero-spam' ); ?></strong>
		</button>
		<div class="zerospam-docs-accordion-content">
			<h3><?php esc_html_e( 'Is Network Statistics available on single-site WordPress?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'No. Network Statistics requires WordPress Multisite with 2 or more sites. Single-site installations can use the API Monitoring features instead.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'How often are statistics updated?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Statistics are cached for 1 hour for performance. Use the "Refresh" button on the dashboard widget or statistics page to clear the cache and see real-time data.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Can I block an IP across all sites at once?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Yes! On the Multi-Site Attackers table, click "Block Network-Wide" to add an IP to the block list for all sites in your network.', 'zero-spam' ); ?></p>

			<h3><?php esc_html_e( 'Where is the data stored?', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Spam logs are stored in the wpzerospam_log table. Aggregated statistics are cached in transients (1-hour expiration). No external data storage is used.', 'zero-spam' ); ?></p>
		</div>
	</div>
</div>
