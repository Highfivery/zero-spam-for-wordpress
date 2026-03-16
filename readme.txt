=== Zero Spam for WordPress ===
Contributors: bmarshall511
Tags: protection, firewall, security, spam, spam blocker
Donate link: https://www.zerospam.org/subscribe/
Requires at least: 6.9
Tested up to: 6.9.1
Requires PHP: 8.2
Stable tag: 5.7.8
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

No spam, no scams, just seamless experiences with Zero Spam for WordPress - the shield your site deserves.

== Description ==

Protect your WordPress website seamlessly with Zero Spam for WordPress! Eliminate spam and malicious attacks that can harm your online presence. Our plugin integrates effortlessly with [Zero Spam](https://www.zerospam.org), [Stop Forum Spam](https://www.stopforumspam.com/), and [Project Honeypot](https://www.projecthoneypot.org/) to offer a strong defense system.

Rest easy knowing that we utilize multiple detection methods to swiftly identify and halt potential threats. Whether it's pesky spam, devious trolls, or cunning hackers, Zero Spam is here to protect your website.

= Worry-free, Powerful Protection at Your Fingertips =

* No captchas or moderation queues — no longer a admin’s problem.
* Our system dynamically blocks threats, keeping your site safe.
* Integration with global IP reputation providers for enhanced security.
* Block IPs temporarily or permanently, keep unwanted visitors out.
* Geolocation tracks origins of threats, providing valuable insights.
* Ability to block countries, regions, zip/postal codes & cities.
* REST API for programmatic settings management — perfect for CI/CD, staging syncs, and automation.
* Utilize [splorp's Comment Blacklist](https://github.com/splorp/wordpress-comment-blacklist) to strengthen your disallowed list.
* Block disposable & malicious email effortlessly with [disposable](https://github.com/disposable).
* Multiple techniques, including the renowned solution by [David Walsh](https://davidwalsh.name/wordpress-comment-spam).

= Seamlessly integrates with popular plugins including: =

* [WooCommerce](https://wordpress.org/plugins/woocommerce/) — Secure customer registrations.
* [GiveWP](https://givewp.com/ref/1118/) — Prevents attempts to test stolen credit cards.
* [ProfilePress](https://wordpress.org/plugins/wp-user-avatar/) — Keeps registrations safe & secure.
* [Mailchimp for WordPress](https://wordpress.org/plugins/mailchimp-for-wp/) — Protects sign-ups from abuse.
* [Gravity Forms](https://www.gravityforms.com/), [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), [WPForms](https://wordpress.org/plugins/wpforms-lite/), [Formidable Form Builder](https://wordpress.org/plugins/formidable/), [Fluent Forms](https://wordpress.org/plugins/fluentform/), [wpDiscuz](https://wordpress.org/plugins/wpdiscuz/) — Versatile form protection.

With Zero Spam for WordPress, you not only get exceptional protection but also a reliable support that ensures your peace of mind.

= Enhance Detection with Optional 3rd-Party Integrations =

Zero Spam for WordPress can integrate optional services for enhanced spam detection. Before using these, we recommend reviewing their terms and privacy policies.

* **[Zero Spam](https://www.zerospam.org/)** - Utilize our real-time IP reputation analysis. Take a look at our [Privacy Policy](https://www.zerospam.org/privacy/) and [Terms of Use](https://www.zerospam.org/terms/) for more details.
* **[ipbase.com](https://ipbase.com/)** - Access detailed geolocation information of attackers. Familiarize yourself with their [Privacy Policy](https://ipbase.com/privacy-policy/) & [Terms of Use](https://www.iubenda.com/terms-and-conditions/41661719).
* **[ipinfo.io](https://ipinfo.io/)** - Gather geolocation details of malicious users. Refer to their [Privacy Policy](https://ipinfo.io/privacy-policy) & [Terms of Use](https://ipinfo.io/terms-of-service) for further information.
* **[ipstack](https://ipstack.com/)** - Obtain extensive geolocation insights. Review their [Privacy Policy](https://www.ideracorp.com/Legal/APILayer/PrivacyStatement) & [Terms of Use](https://ipstack.com/terms) to learn more.
* **[Stop Forum Spam](https://www.stopforumspam.com/)** - Verify if visitors' IPs have been reported. Explore their [Privacy Policy](https://www.stopforumspam.com/privacy) and [Terms of Use](https://www.stopforumspam.com/legal) for additional details.
* **[Project Honeypot](https://www.projecthoneypot.org/)** - Check if visitors' IPs have been flagged. Refer to their [Privacy Policy](https://www.projecthoneypot.org/privacy_policy.php) and [Terms of Use](https://www.projecthoneypot.org/terms_of_use.php) for more information.
* **[Google Maps](https://developers.google.com/maps)** - Plot attack locations on Google Maps. Please review their [Privacy Policy](https://www.ideracorp.com/Legal/APILayer/PrivacyStatement) & [Terms of Use](https://developers.google.com/terms/site-terms) for complete details.

Additionally, you have the option to contribute to Zero Spam's improvement by enabling the sharing of detection information. For further information on the shared data, kindly refer to our [FAQ](https://github.com/Highfivery/zero-spam-for-wordpress/wiki/FAQ).

== Installation ==

1. Upload the entire *zero-spam* folder to the */wp-content/plugins/* directory.
2. Activate the plugin through the Plugins screen (*Plugins > Installed Plugins*).
3. Visit the plugin setting to configure as needed (*Settings > Zero Spam*).

For more information & developer documentation, see the [wiki](https://github.com/Highfivery/zero-spam-for-wordpress/wiki).

== Frequently Asked Questions ==

= Does Zero Spam for WordPress block user IPs? =

*Not on its own.* Zero Spam for WordPress does not automatically block IP addresses. If a visitor is blocked, it could be due to manual blocking by the site admin or their presence in IP blacklists such as [Stop Forum Spam](https://www.stopforumspam.com/), [Project Honeypot](https://www.projecthoneypot.org/), or the [Zero Spam](https://www.zerospam.org).

In the event that a legitimate user is blocked, refer to the Log (Admin > Dashboard > Zero Spam > Log) for further details on the reason behind the block. You have the flexibility to adjust the strictness of the 3rd-party blacklist checks or disable them if your users are prone to being flagged as spam or malicious.

= Does Zero Spam for WordPress check Jetpack comments? =

**No, it doesn't.** Zero Spam for WordPress does not have integration with Jetpack. If you have any inquiries regarding this, please refer to [https://wordpress.org/support/topic/incompatible-with-jetpack-comments](https://wordpress.org/support/topic/incompatible-with-jetpack-comments) for more details.

= How do I boost performance of Zero Spam for WordPress? =

**Enable caching for optimal performance.** Enabling caching is highly recommended as it helps prevent repetitive calls to third-party APIs and access checks during each page visit.

To further optimize performance, you can adjust the cache and API timeout settings in the admin panel based on your server specifications and specific requirements.

= Does Zero Spam support WP-CLI commands? =

* `wp zerospam autoconfigure` &mdash; Auto-configures with recommended settings.
* `wp zerospam settings` &mdash; Displays all plugin settings.
* `wp zerospam set --[SETTING_KEY]=[VALUE]` &mdash; Updates a plugin setting.

= Can I manage Zero Spam settings programmatically? =

**Yes!** Zero Spam provides a secure REST API for reading and updating settings remotely. This is perfect for:

* Syncing settings between staging and production environments
* Automating configuration in CI/CD pipelines
* Managing settings across multiple WordPress sites
* Remote administration and monitoring
* Testing configuration changes safely with dry-run mode

The API supports multisite installations with granular control over network defaults and per-site overrides. Authentication uses WordPress Application Passwords for secure, revocable access without exposing your main password.

**Getting Started:** Visit the Documentation tab in Settings > Zero Spam for complete details, step-by-step setup instructions, real-world examples, and troubleshooting tips. No technical expertise required!

= Are you getting a `ftp_fget` PHP warning? =

Some hosts have issues with they way they access files. If you're seeing a `ftp_fget` PHP notice, setting the `FS_METHOD` constant to `direct` in `wp-config.php` above the line `/* That's all, stop editing! Happy Pressing. */` should solve the problem:

`define('FS_METHOD', 'direct');`

If hosting with Pantheon, see their [known issues page](https://pantheon.io/docs/plugins-known-issues#define-fs_method) for more information and what to do to resolve it with their `$_ENV['PANTHEON_ENVIRONMENT']` variable check.

= Where do I report security bugs found in this plugin? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/zero-spam)

= I blocked myself! How do I get back in? =

If you have defined the `ZEROSPAM_RESCUE_KEY` constant in your `wp-config.php` file, you can bypass all checks by appending `?zerospam_rescue={YOUR_KEY}` to any URL. (e.g., `https://example.com/wp-admin/?zerospam_rescue=mysecretkey`).

If you haven't defined this key, you must manually rename the plugin folder via FTP (`wp-content/plugins/zero-spam` -> `zero-spam-disabled`) to gain access.

= Why can't I access `wp-login.php` or XML-RPC anymore? =

As of version 5.7.1, Zero Spam now actively protects `wp-login.php` and `xmlrpc.php` from blocked IPs. If you are blocked, check your IP reputation or use the Rescue Mode key to log in and whitelist your IP.

== Screenshots ==

1. Dashboard
2. Log
3. Settings
4. Add blocked IP address
5. Add blocked location

== Changelog ==

= v5.7.8 =

* **fix(woocommerce):** custom spam message for WooCommerce registrations could never be saved due to an option key mismatch — messages now persist correctly ([#393](https://github.com/Highfivery/zero-spam-for-wordpress/issues/393))
* **feat(woocommerce):** added independent "Protect Checkout" toggle — checkout and registration protection can now be enabled/disabled separately ([#393](https://github.com/Highfivery/zero-spam-for-wordpress/issues/393))
* **feat(woocommerce):** added dedicated "Checkout Flagged Message" setting with context-appropriate default for checkout blocks ([#393](https://github.com/Highfivery/zero-spam-for-wordpress/issues/393))
* **feat(woocommerce):** added dedicated "Log Blocked Checkouts" toggle for independent checkout logging ([#393](https://github.com/Highfivery/zero-spam-for-wordpress/issues/393))
* **feat(woocommerce):** checkout now validates billing email against blocked email domains, matching registration behavior ([#393](https://github.com/Highfivery/zero-spam-for-wordpress/issues/393))
* **refactor(woocommerce):** normalized David Walsh filter hooks to `zerospam_preprocess_*` naming convention for consistency with all other modules
* **fix(woocommerce):** one-time migration ensures existing sites retain checkout protection and logging preferences after update
* **fix(dashboard-widget):** widget now properly hides when disabled — previously displayed for administrators even when all roles were deselected in visibility settings ([#391](https://github.com/Highfivery/zero-spam-for-wordpress/issues/391))
* **fix(dashboard-widget):** resolved database errors (`Table 'prefix_zerospam_log' doesn't exist`) by adding table existence checks before querying — shows a clean notice with remediation steps when the log table is missing ([#391](https://github.com/Highfivery/zero-spam-for-wordpress/issues/391))
* **fix(settings):** multi-select fields (e.g. widget visibility) now correctly store an empty array when no options are selected, preventing fallback to default values
* **fix(dashboard-widget):** AJAX refresh now correctly returns network-level data on multisite network admin dashboards
* **fix(dashboard-widget):** widget transient cache is now cleared immediately when settings are saved, so visibility changes take effect without waiting for the 5-minute cache expiry
* **feat(settings):** added "Dashboard Widget" enable/disable toggle — provides an unambiguous on/off control separate from role-based visibility
* **feat(dashboard-widget):** added `zerospam_dashboard_widget_visible` filter — developers can programmatically override widget visibility
* **feat(dashboard-widget):** refresh button now updates stats and charts in-place via AJAX without requiring a page reload
* **refactor(dashboard-widget):** extracted duplicated role-check logic into reusable `has_widget_access()` method, eliminating ~60 lines of duplication
* **perf(dashboard-widget):** Chart.js is now bundled locally instead of loaded from CDN — works in locked-down environments and eliminates external dependency
* **fix(contactform7):** disallowed word checks no longer scan security token fields like Cloudflare Turnstile (`cf-turnstile-response`), reCAPTCHA, and hCaptcha responses — these long random strings almost always triggered false positives against short blocklist entries
* **fix(gravityforms):** disallowed word and blocked email domain checks now use the centralized field validation with excluded fields support
* **fix(formidable):** disallowed word and blocked email domain checks now use the centralized field validation with excluded fields support
* **fix(fluentforms):** disallowed word checks now use the centralized field validation with excluded fields support
* **fix(wpforms):** disallowed word and blocked email domain checks now use the centralized field validation with excluded fields support
* **feat(settings):** added "Allowed Words" setting — lets you whitelist specific words so they are never flagged as spam, even if they appear in the disallowed words list (e.g. if your email or domain contains a blocked string like "ugg")
* **feat(settings):** added "Minimum Disallowed Word Length" setting — skip very short blocklist entries (3-4 characters) that cause the most false positives
* **feat(utilities):** added `zerospam_excluded_fields` filter — developers can add custom POST field keys (e.g. third-party CAPTCHA tokens) to exclude from disallowed word scanning
* **feat(utilities):** added `Utilities::check_fields_for_spam()` centralized method — all form modules now share the same field validation logic with automatic system field exclusion
* **feat(comments):** allowed words and minimum word length settings now also apply to WordPress comment validation via `wp_check_comment_disallowed_list()`
* **refactor(modules):** deduplicated field validation loops across CF7, Gravity Forms, Formidable, Fluent Forms, and WPForms modules into shared `check_fields_for_spam()` utility
* **perf(utilities):** allowed words list and minimum length setting are cached per-request to avoid repeated database lookups when checking multiple form fields

= v5.7.7 =

* **fix(davidwalsh):** fixed 403 responses on `/wp-json/zero-spam/v5/davidwalsh-key` for non-logged-in visitors on cached pages — stale `wp_rest` nonce in the AJAX key-refresh request triggered WordPress cookie authentication rejection before the public `permission_callback` was evaluated; removed unnecessary nonce header since the endpoint is intentionally public
* **ui(project honeypot):** added direct link to Project Honeypot website in the settings section description
* **ui(project honeypot):** renamed "Access Key" field to "HTTP:BL Access Key" with links to sign up and find the key on the HTTP:BL configuration page

= v5.7.6 =

* **fix(cli):** removed `## OPTIONS` docblock from `set` command that caused WP-CLI to reject valid flags like `--stop_forum_spam`

= v5.7.5 =

* **security(database):** fixed SQL injection vulnerabilities in `blocked()` method — all queries now use `$wpdb->prepare()`
* **security(database):** refactored `query()` method to use prepared statements, column whitelisting, and sanitized LIMIT/OFFSET
* **security(database):** secured DELETE query in `log()` method with `$wpdb->prepare()` and `absint()`
* **security(utilities):** added `esc_attr()` to honeypot field output to prevent potential XSS
* **security(utilities):** hardened `log()` method with `sanitize_file_name()`, mode allowlist, and `fopen` failure handling
* **fix(gravityforms):** blocked email domains are now checked during Gravity Forms submissions — previously these features were disconnected
* **fix(contactform7):** blocked email domains and disallowed words are now checked during CF7 submissions
* **fix(formidable):** blocked email domains and disallowed words are now checked during Formidable Forms submissions
* **fix(fluentforms):** fixed inverted logic in `validate_email()` that prevented blocked email domain checks from working correctly
* **fix(cli):** `wp zerospam set --blocked_email_domains` now writes to the correct option (`zerospam_blocked_email_domains`)
* **fix(cli):** `wp zerospam set --regenerate_honeypot` now properly regenerates the honeypot ID instead of storing a meaningless value
* **fix(settings):** `auto_configure()` now correctly handles `blocked_email_domains` via standalone option and skips HTML action settings
* **feat(cli):** added `wp zerospam regenerate-honeypot` standalone command for honeypot ID regeneration
* **feat(cli):** added `wp zerospam update-blocked-domains` command with `--recommended`, `--file`, `--domains`, and `--append` options
* **feat(cli):** `wp zerospam set` now supports `--update_blocked_email_domains` and `--update_disallowed_words` action flags
* **feat(gravityforms):** added per-module toggle settings for blocked email domain and disallowed word checking
* **feat(contactform7):** added per-module toggle settings for blocked email domain and disallowed word checking
* **feat(formidable):** added per-module toggle settings for blocked email domain and disallowed word checking
* **feat(fluentforms):** added per-module toggle setting for disallowed word checking

= v5.7.4 =

* fix(database): corrected table name reference in dashboard widget from `zerospam_log` to `wpzerospam_log`
* fix(database): added `blog_id` column to log table schema for single-site compatibility
* fix(dashboard): resolved SQL errors on single-site installations caused by missing `blog_id` column
* perf(database): added indexes for `blog_id` and composite `blog_date` for improved query performance
* feat(database): automatic migration adds `blog_id` column to existing installations
* fix(dashboard): corrected widget styling to use light theme (removed dark mode support)

= v5.7.3 =

* feat(multisite): added independent control for network settings change email notifications
* fix(multisite): resolved issue where changing network settings sent exponential emails in large networks
* feat(multisite): network administrators can now separately control weekly summaries and settings change notifications
* perf(multisite): prevents thousands of emails from being sent when network settings are modified

= v5.7.2 =

* fix(ipinfo): migrated to Lite API (unlimited free tier) to resolve 429 quota exceeded errors
* perf(ipinfo): added persistent transient caching to reduce API calls
* refactor(ipinfo): removed ipinfo/ipinfo vendor dependency in favor of native WordPress HTTP API
* feat(multisite): added Notifications tab to Network Settings with toggle for weekly summary emails
* feat(multisite): network administrators can now enable/disable weekly email notifications from the UI

= v5.7.1 =

* fix(settings): resolved undefined array key warnings for rescue mode setting

= v5.7.0 =

* feat(safety): implemented rescue mode (ZEROSPAM_RESCUE_KEY) to bypass blocks (emergency access)
* feat(security): extended protection to wp-login.php and xmlrpc.php endpoints
* fix(login): implemented intent token mechanism to prevent false positives with multi-step login flows (e.g. 2FA, Math Captcha)
* fix(login): refined error messaging for missing verification fields to avoid incorrect "malicious" labeling
* feat(performance): implemented transient caching for geolocation lookups (1 week) to reduce API calls
* feat(logging): added granular failure reasons to detection logs (e.g. "High Confidence Score: 95%")
* fast(core): removed incorrect main query check that could bypass blocks
* feat(multisite): comprehensive network-wide settings management for agencies managing multiple sites
* feat(multisite): network admin dashboard with overview statistics, site comparison, and application status
* feat(multisite): settings hierarchy system - network defaults with site-level override capability and lock enforcement
* feat(multisite): settings templates system for quick configuration deployment across sites
* feat(multisite): audit trail tracking all network setting changes with user attribution
* feat(multisite): import/export functionality for network settings backup and migration
* feat(multisite): WP-CLI commands for programmatic network settings management
* feat(multisite): REST API endpoints for remote network configuration
* feat(dashboard): unified dashboard widget that intelligently adapts to multisite/single-site context
* feat(dashboard): modern, responsive design using WordPress core components with dark mode support
* feat(dashboard): real-time API usage monitoring with visual progress bars and warning levels
* feat(dashboard): 30-day spam trend visualization using Chart.js 4.x
* feat(dashboard): top 10 sites by spam volume (network admin) and spam types breakdown (single site)
* fix(dashboard): improved permission handling for multisite super admins
* fix(dashboard): added proper hooks for both network and regular admin dashboards
* fix(comparison): corrected override count calculation to show actual differences, not just stored values
* fix(comparison): resolved undefined value errors and improved data validation
* fix(comparison): auto-load comparison data when viewing tab for better UX
* fix(import-export): added 3-second delay before page reload so success messages are visible
* fix(import-export): enhanced validation with file type checking, size limits, and JSON parsing
* fix(import-export): improved error messages and inline status feedback
* fix(ui): polished settings interface with better grouping, descriptions, and visual hierarchy
* fix(ui): inline save feedback that doesn't scroll users away from their work
* fix(ui): simplified setting descriptions to be non-technical and user-friendly
* fix(php8.1): resolved deprecation warnings for number_format() with null values

= v5.6.2 =

* fix(admin): resolved issue where "Advanced Protection is enabled but not licensed" notice displayed incorrectly when Enhanced Protection was disabled
* fix(admin): corrected Settings API usage in admin notices for consistency with dashboard widget
* fix(admin): added support for ZEROSPAM_LICENSE_KEY constant check in admin notice logic
* fix(debug): removed testing debug statements

= v5.6.1 =

* fix(api): corrected email report submission to use GET method with query parameters (was incorrectly using POST with body)
* fix(api): email reports now properly include report_ip parameter
* fix(api): fixed variable reuse bug by using separate $email_endpoint variable for email reports

= v5.6.0 =

* feat(api): API version increment - v2/query to v3/query, v5.4/report to v6/report, v1/get-license to v2/get-license
* feat(api): migrated all protected endpoints from POST to GET method with query parameters
* feat(api): master API key can bypass localhost blocking for testing purposes
* feat(david walsh): rewritten in vanilla JavaScript, removing jQuery dependency
* feat(david walsh): added MutationObserver for dynamically loaded forms (AJAX, React)
* feat(david walsh): implemented daily key rotation with dual-key caching for cached pages
* feat(david walsh): increased key length from 5 to 16 characters for enhanced security
* feat(david walsh): added REST API endpoint for AJAX-based key refresh
* feat(gravity forms): added David Walsh technique support
* feat(formidable): added David Walsh technique support
* feat(elementor): added David Walsh technique support
* feat(woocommerce): added David Walsh validation for checkout (in addition to registration)
* feat(admin): added conversion-optimized promotional notice for Enhanced Protection with lifetime discount offer
* refactor(givewp): removed David Walsh support (incompatible with v3 block-based forms)
* refactor(david walsh): centralized form selector management via filter
* fix(david walsh): removed dead MemberPress selectors from JavaScript
* perf(api): Apache-level validation requires license_key parameter (zero PHP overhead for invalid requests)
* perf(api): v3/query requires ip or email parameter at Apache level
* fix(api): corrected hardcoded api url to use ZEROSPAM_URL constant in query function
* fix(api): wrapped report data in 'data' array for proper API format
* fix(code): corrected remote_request return type docblock
* fix(code): corrected Object→WP_REST_Request param types and increment_license_queries parameter
* ui(david walsh): redesigned settings page with comprehensive how-it-works documentation
* ui(david walsh): added security key status display with rotation countdown
* ui(david walsh): added step-by-step testing instructions for non-technical users
* ui(david walsh): enhanced custom form selectors field with detailed usage examples
* ui(david walsh): added list of currently protected form selectors
* ui(admin): promotional notice displays on dashboard and Zero Spam pages for unlicensed users
* ui(admin): notice is dismissible and reappears after 30 days if no license is added
* ui(admin): notice waits 3 days after plugin activation before displaying



= v5.5.9 =

* fix(api): corrected app_type case mismatch and app_details/email_details encoding

= v5.5.8 =

* fix(caching): prevented caching of 403 forbidden pages to resolve compatibility with litespeed cache (closes #383)
* fix(david walsh): improved js reliability for comment forms to prevent false positives (closes #378)
* fix(david walsh): resolved conflict where wpforms submissions were blocked when david walsh protection was enabled (closes #364)
* fix(ipinfo): corrected issue where location data was reported as "unknown" in the dashboard widget (closes #360)
* fix(install): resolved database errors on fresh installations due to strict dbdelta requirements (closes #332)
* chore(requirements): updated php and wp version requirements
* chore(standards): fixed issues with strict types
* docs(project): updated project documentation files
* ci(github): updated github workflows and templates
* perf(core): optimized disallowed words option to prevent autoloading large data
* perf(api): implemented async detection reporting to reduce server load
* perf(api): implemented persistent response caching (transients)
* perf(api): implemented circuit breaker pattern for api fault tolerance
* perf(core): optimized disposable email domains storage to prevent autoloading large data

= v5.5.7 =

* fix(bypass vulnerability): see https://patchstack.com/database/database/vulnerability/zero-spam/wordpress-zero-spam-for-wordpress-plugin-5-5-5-bypass-spam-protection-vulnerability

= v5.5.6 =

* fix(missing tables): fix for missing tables error on multisites, #377

= v5.5.5 =

* fix(jquery): fix for jquery not found error when jquery is loaded with defer
* chore(splorp): updated the slorp blacklist

= v5.5.4 =

* fix(zero spam): fix for error in the zero spam api

= v5.5.3 =

* feat(patchstack): integrating patchstack faqs
* fix(coding standards): updates to better comply with wp coding standards

= v5.5.2 =

* fix(vulnerability): fix for bypass using .ico in url or adjusting the x-forwarded-for header

= v5.5.1 =

* fix(david walsh): fix for jquery not defined error, related to the zerospamdavidwalsh method, resolves #359

= v5.5.0 =

* feat(profilepress): added support for profilepress registrations

= v5.4.7 =

* fix(david walsh): fix for missing david walsh dependency, resolves #345

= v5.4.6 =

* fix(admin): fix for php notice about missing database_query_arguments
* fix(whitelist): fix for whitelisted ips not getting triggered on comments, resolves #350

= v5.4.5 =

* fix(security): fixed sql injection vulnerability in the zero spam admin log table query

= v5.4.4 =

* refactor(project honeypot): resolves #344, added additional check & debug info for ip type support
* fix(wpforms): resolves #343, fix for jquery dependency
* fix(registration): resolves #342, fix for failed registration output
* fix(php8): resolves #341, fix for php8+ compatibility issue

= v5.4.3 =

* fix(emojis): fix for fatal error when emojis are disabled

= v5.4.2 =

* feat(ipbase): added support for ipbase.com
* feat(security): added additional advanced security protections

= v5.4.1 =

* feat(dashboard): dashboard ui enhancements
* perf(sharing): performance improvements when sharing data
* fix(memberpress): removed memberpress support, they made fundamental changes to their plugin that's ganna require a rework
* fix(uninstall): fix issue where the plugin couldn't be deleted
* fix(cli): resolves #33
* chore(charts): updated chart.js to 3.9.1

= v5.4.0 =

* chore(admin): updated the after-activation message
* chore(spam): updated splorp's wordpress comment blacklist
* feat(givewp): enhanced security using the david walsh method on legacy forms
* feat(admin): major ui enhancements
* feat(gravityforms): adds support for gravity forms
* feat(reports): improved error logs
* feat(wpdiscuz): resolves #327, added support for wpdiscuz
* feat(wpforms): now supports checking blocked email addresses
* feat(email): enhanced email security checks
* fix(double requests): issue with double checks being performed per page visit
* fix(blocks): fix for blocked ips not getting properly blocked
* fix(locations): fix for blocked locations not getting added/updated
* fix(comments): fix for valid comment submissions being flagged
* fix(admin): missing country flag in ip details modal
* fix(woocommerce): fix for login woocommerce registrations fixed
* fix(david walsh): fix for flagged submissions when using the david walsh technique
* perf(misc): misc performance improvements related to 3rd-party api queries

= v5.3.9 =

* fix(admin): fix issue with admin notice not dismissing properly, resolves #319

= v5.3.8 =

* chore(zero spam api): updated the zero spam api to v2

= v5.3.7 =

* chore(readme): documentation updates

= v5.3.6 =

* fix(admin): fix for admin notice not getting dismissed when clicked, resolves #318

= v5.3.5 =

* chore(readme): added 3rd-party service integration documentation to the readme
* chore(admin): revised the admin message that's displayed with zero spam enhanced protection is enabled, but a valid api key is not provided

= v5.3.4 =

* fix(notice): removed dismiss button on initial install to ensure plugin settings are configured before use

= v5.3.3 =

* feat(zero spam settings): displays dismissible notices for enhanced protection and invalid license keys
* feat(dates): updated the admin tables to display dates based on the site settings, resolves #305
* fix(ukraine): removed the ukraine banner

= v5.3.2 =

* feat(zero spam api): now reports spam and malicious email addresses

= v5.3.1 =

* fix(zero spam api): update to limit number of requests when sharing data

= v5.3.0 =

* fix(woocommerce): fix for spam getting triggered during woo checkout with create account checked, resolves #313
* refactor(zero spam api): performance improvements when sharing detections

= v5.2.15 =

* feat(ukraine): we'll no longer provide protection for .ru, .su, and .by domains & will display a banner of support for the ukrainian people on those sites - united with ukraine

= v5.2.14 =

* fix(woocommerce): fixes issues with woocommerce login not working, resolves #310

= v5.2.13 =

* feat(woocommerce): added support for woocommerce registrations, resolves #306
* fix(admin): fix for displaying & adding blocked ip addresses, resolves #308

= v5.2.12 =

* refactor(wordpress coding standards): misc updates to conform to wordpress coding standards

= v5.2.11

* fix(security): fixes the missing orderby parameter sanitization in the admin dashboard
* fix(admin settings): fixed whitespace issue in textarea setting fields, resolves #303
* fix(admin log): updated date column to use the local setting date & time format, resolves #305

= v5.2.10 =

* fix(security): fixes the missing parameter sanitization in the admin dashboard, resolves #301

= v5.2.9 =

* feat(zero spam): you can now define your zero spam license key in wp-config.php using the constant ZEROSPAM_LICENSE_KEY, resolves #298
* fix(admin): fix for setting action buttons not doing anything, resolves #295
* fix(admin): fixes php notice for in_array in class-utilities, resolves #299

= v5.2.8 =

* feat(memberpress): resolves #286, added support for the memberpress login page
* fix(memberpress): updated memberpress sign-up hook priority to ensure it runs
* refactor(admin): now using nonces to process zero spam admin actions

= v5.2.7 =

* perf(settings): performance improvement to settings being loaded
* style(admin): added check for zero spam license key when enabled
* style(admin): misc. admin interface improvements

= v5.2.6 =

* fix(undefined method): fix for undefined types method

= v5.2.5 =

* feat(givewp): now checks submitted emails against the blocked email domains list
* perf(everything): refactoring of code for a boost in performance
* docs(readme): misc. readme file updates
* fix(admin): fix for error log not clearing

= v5.2.4 =

* feat(memberpress): resolves #283, now supports memberpress registration forms
* feat(mailchimp4wp): resolves #121, now supports mailchimp4wp forms
* refactor(misc): misc. updates to comply with wordpress coding standards.
* style(admin): misc. admin interface improvements

= v5.2.3 =

* feat(givewp): now support givewp donation forms
* style(notices): minor update to default detection notice

= v5.2.2 =

* fix(db): resolves #281, fixes db update error for multisite installations
* fix(db): fix for unsanitized db log entries
* style(admin): new cf7 icon added for blocked log

= v5.2.1 =

* fix(woocommerce): resolves #280, fixes login integration breaking woocommerce login form

= v5.2.0 =

* feat(login): now protects user login attempts
* feat(project honeypot): resolves #201, project honeypot ip checks now integrated
* perf(sharing): blocked ips are no longer shared with zerospam.org
* perf(database): doesn't log .ico requests anymore that normally resulted in 2 entries per detection
* style(admin): misc admin interface improvements
* refactor(misc): cleaning up code & wordpress coding standards updates
* refactor(zero spam api): updated version on the zero spam api endpoint

= v5.1.7 =

* fix(php notice): fix for some hosts firing a php notice when unable to retrieve the list of recommended blocked email domains

= v5.1.6 =

* feat(fluent forms): resolves #276, fluent forms is now supported
* fix(php notice): resolves #277, fix for array_intersect(): Argument #2 must be of type array, bool

= v5.1.5 =

* feat(dashboard widget): resolves #275, added the ability to control the dashboard widget visibility
* feat(settings): button to quickly override and update settings to zero spam's recommended
* feat(email domains): resolves #246, ability to block disposable and malicious email domains
* perf(sharing): sharing detections optimized
* perf(disallowed list): removed the unused cron to sync disallowed words
* chore(disallowed list): updated to the latest splorp's disallowed list
* docs(htaccess): added a notice & recommended max number of blocked ips when using .htaccess
* fix(ipinfo): fix for uncaught ipinfo exception

= v5.1.4 =

* fix(htaccess): resolves #274, fix for newer apache versions and option to select the method ips are blocked

= v5.1.3 =

* perf(blocked ips): moved blocked ips to .htacess for improved performance
* refactor(woocommerce): woocommerce registration forms support dropped in place of 3rd-party IP checks
* docs(admin): misc updates to admin interface

= v5.1.2 =

* perf(geolocation): improved performance for geolocation and data sharing
* docs(readme): updated readme file
* refactor(misc): added some functionality to make debugging easier
* fix(ipinfo): resolves #273, loads the ipinfo library only if enabled

= v5.1.1 =

* feat(geolocation): resolves #270, added support for ipinfo geolocation
* feat(cli): resolves #271, added WP CLI support
* feat(admin): resolves #237, new admin dashboard widget
* refactor(admin): wordpress coding standards fixes
* refactor(settings): minor update to settings section title
* docs(readme): updated readme file

= v5.1.0 =

* feat(ipstack): ipstack errors are logged to the zerospam.log file in the uploads directory
* feat(cloudflare): resolves #267, checks http_cf_ipcountry against blocked countries
* feat(admin): resolves #264, adds ability to export & import settings
* perf(davidwalsh): resolves #266, only loads the david walsh script on pages that are needed
* fix(caching): resolves #258, added no-cache header to the blocked page output
* refactor(stopforumspam): increased the default confidence score for stop forum spam to help prevent false positives
* docs(faq): added common question about how to boost performance of the plugin

= v5.0.13 =

* fix(updates): resolves #262, sanitized & escaped variables
* fix(standards): resolved #261, sanitized & escaped variables
* fix(cron jobs): resolves #260, removed the remote call to splorp's blacklist on Github

= v5.0.12 =

* Fixed issue with WPForms AJAX forms not getting validated by Zero Spam for WordPress [#238](https://github.com/bmarshall511/wordpress-zero-spam/issues/238)
* David Walsh detection technique applied to WPForms & CF7
* Miscellaneous admin UI improvements
* Added ability to disable syncing WP's Disallowed Comment Keys

= v5.0.11 =

* Improved protection for comments, CF7, Formidbale, registrations, WooCommerce and WPForms submissions.
* David Walsh detection technique applied to core WP registration forms.

= v5.0.10 =

* PHP notice fix

= v5.0.9 =

* Performance enhancements
* Various admin UI improvements
* Strengthened comment & registration spam detections

= v5.0.8 =

* Fix for admin first-time config notice

= v5.0.7 =

* Added first-time configuration notice & auto-configure recommended settings functionality
* Added the ability to regenerate the honeypot ID
* Various admin UI improvements
* WP Disallowed Comment Keys are automatically updated weekly using https://github.com/splorp/wordpress-comment-blacklist
* Strengthened comment spam detections using WP core disallowed list
* [David Walsh's spam technique](https://davidwalsh.name/wordpress-comment-spam#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam) is back! https://github.com/bmarshall511/wordpress-zero-spam/issues/247

= v5.0.6 =

* Various admin UI improvements
* Strengthened comment spam detections

= v5.0.5 =

* Fix autoloader compatibility with Windows paths (https://github.com/bmarshall511/wordpress-zero-spam/pull/236)
* Various admin UI improvements

= v5.0.4 =

* Fix for when checks should be preformed

= v5.0.3 =

* Added support for Formidable Form Builder
* Fixed PHP error related to a blacklist call

= v5.0.2 =

* Admin UI enhancements
* Added support for WooCommerce
* Added Cloudflare IP address support (https://github.com/bmarshall511/wordpress-zero-spam/issues/220)
* Update to data sharing option
* Added ability to block individual locations (country, region, zip & city)
* Added support for WPForms

= v5.0.1 =

* Updated readme file & documentation
* Can now be installed via composer
* Updated the required PHP version

= v5.0.0 =

* Initial v5.0.0 release
* Huge performance enhancements
* More control over settings to fine-tune functionality
* Lots of bug fixes & improvements
