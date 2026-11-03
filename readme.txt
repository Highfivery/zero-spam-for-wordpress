=== Zero Spam for WordPress ===
Contributors: bmarshall511, highfivery
Tags: anti-spam, firewall, security, spam, spam blocker
Donate link: https://www.zerospam.org/subscribe/
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.0.0
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Free anti-spam for WordPress comments, logins, registrations and forms. No captchas, no moderation queue. Blocks bots before they post.

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
* [Gravity Forms](https://www.gravityforms.com/), [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), [WPForms](https://wordpress.org/plugins/wpforms-lite/), [Formidable Form Builder](https://wordpress.org/plugins/formidable/), [Fluent Forms](https://wordpress.org/plugins/fluentform/), [wpDiscuz](https://wordpress.org/plugins/wpdiscuz/), [Elementor Pro](https://elementor.com/) forms — Versatile form protection.

With Zero Spam for WordPress, you not only get exceptional protection but also a reliable support that ensures your peace of mind.

= Enhance Detection with Optional 3rd-Party Integrations =

Zero Spam for WordPress can integrate optional services for enhanced spam detection. Before using these, we recommend reviewing their terms and privacy policies.

* **[Zero Spam](https://www.zerospam.org/)** - Utilize our real-time IP reputation analysis. Take a look at our [Privacy Policy](https://www.zerospam.org/privacy/) and [Terms of Use](https://www.zerospam.org/terms/) for more details.
* **[ipbase.com](https://ipbase.com/)** - Access detailed geolocation information of attackers. Familiarize yourself with their [Privacy Policy](https://ipbase.com/privacy-policy/) & [Terms of Use](https://www.iubenda.com/terms-and-conditions/41661719).
* **[ipinfo.io](https://ipinfo.io/)** - Gather geolocation details of malicious users. Refer to their [Privacy Policy](https://ipinfo.io/privacy-policy) & [Terms of Use](https://ipinfo.io/terms-of-service) for further information.
* **[ipstack](https://ipstack.com/)** - Obtain extensive geolocation insights. Review their [Privacy Policy](https://www.ideracorp.com/Legal/APILayer/PrivacyStatement) & [Terms of Use](https://ipstack.com/terms) to learn more.
* **[Stop Forum Spam](https://www.stopforumspam.com/)** - Verify if visitors' IPs have been reported. Explore their [Privacy Policy](https://www.stopforumspam.com/privacy) and [Terms of Use](https://www.stopforumspam.com/legal) for additional details.
* **[Project Honeypot](https://www.projecthoneypot.org/)** - Check if visitors' IPs have been flagged. Refer to their [Privacy Policy](https://www.projecthoneypot.org/privacy_policy.php) and [Terms of Use](https://www.projecthoneypot.org/terms_of_use.php) for more information.

Additionally, you have the option to contribute to Zero Spam's improvement by enabling the sharing of detection information. For further information on the shared data, kindly refer to our [FAQ](https://github.com/Highfivery/zero-spam-for-wordpress/wiki/FAQ).

= Source code and build =

The plugin's complete source code is on GitHub: [Highfivery/zero-spam-for-wordpress](https://github.com/Highfivery/zero-spam-for-wordpress). The admin scripts and styles in `assets/dist/` are built from the human-readable sources in `assets/src/` with [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts). To build them yourself, check out the repository and, with Node.js 22, run `npm ci && npm run build`. The sources in `assets/src/` and the build tools are not part of the plugin download (they are listed in `.distignore`); get them from the repository. The fonts in `assets/fonts/` (Plus Jakarta Sans and JetBrains Mono, SIL Open Font License 1.1) are included unmodified; their sources are listed in `assets/fonts/FONTS.md`.

== Installation ==

1. Upload the entire *zero-spam* folder to the */wp-content/plugins/* directory.
2. Activate the plugin through the Plugins screen (*Plugins > Installed Plugins*).
3. Open **Zero Spam** in the admin menu (below Comments): a short setup walks you through the recommended settings.

For more information & developer documentation, see the [wiki](https://github.com/Highfivery/zero-spam-for-wordpress/wiki).

== Frequently Asked Questions ==

= Does Zero Spam for WordPress block user IPs? =

*Not on its own.* Zero Spam for WordPress does not automatically block IP addresses. If a visitor is blocked, it could be due to manual blocking by the site admin or their presence in IP blacklists such as [Stop Forum Spam](https://www.stopforumspam.com/), [Project Honeypot](https://www.projecthoneypot.org/), or the [Zero Spam](https://www.zerospam.org).

In the event that a legitimate user is blocked, look it up in the admin under Zero Spam → Activity for further details on the reason behind the block. You have the flexibility to adjust the strictness of the 3rd-party blacklist checks or disable them if your users are prone to being flagged as spam or malicious.

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

**Getting Started:** Visit Zero Spam → Help for complete details, step-by-step setup instructions, real-world examples, and troubleshooting tips. No technical expertise required!

= Are you getting a `ftp_fget` PHP warning? =

Some hosts have issues with they way they access files. If you're seeing a `ftp_fget` PHP notice, setting the `FS_METHOD` constant to `direct` in `wp-config.php` above the line `/* That's all, stop editing! Happy Pressing. */` should solve the problem:

`define('FS_METHOD', 'direct');`

If hosting with Pantheon, see their [known issues page](https://pantheon.io/docs/plugins-known-issues#define-fs_method) for more information and what to do to resolve it with their `$_ENV['PANTHEON_ENVIRONMENT']` variable check.

= Where do I report security bugs found in this plugin? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/zero-spam)

= I blocked myself! How do I get back in? =

On the blocked page, open **Help for site owners**, enter the email address of an administrator account on the site and press **Email me a rescue link**. Open the link in that email (on any device) and press **Let me back in**: that browser is let in for one hour, so you can log in and add your IP address to **Allowed IPs** under Zero Spam → Rules. The link works once, for 15 minutes; a used or expired link opens a page where you can ask for a new one. If the address isn't an administrator's, nothing is sent; the page says the same either way. Logged-in users are never blocked.

When you activate or update Zero Spam, it also saves your current IP address (public addresses only) as one it never locks out. Visitors from that address are still checked for spam. You can see and remove these addresses under Zero Spam → Rules → No-lockout addresses.

You can also define the `ZEROSPAM_RESCUE_KEY` constant in your `wp-config.php` file (e.g. `define( 'ZEROSPAM_RESCUE_KEY', 'a-long-random-secret' );`) and visit any page of your site with `?zerospam_rescue={YOUR_KEY}` appended (e.g., `https://example.com/wp-login.php?zerospam_rescue=a-long-random-secret`). All checks are bypassed in that browser for one hour.

If none of these work (for example, you use an `.htaccess` block method, or your site can't send email), rename the plugin folder via FTP (`wp-content/plugins/zero-spam` -> `zero-spam-disabled`) to gain access.

If every visitor, including you, suddenly sees "Your IP has been flagged as spam/malicious", your site is probably behind a CDN, load balancer or proxy (for example Hostinger CDN or Sucuri) that Zero Spam doesn't know about, so every visitor appears with the proxy's address. Get back in with the rescue link or Rescue Mode, then add the proxy's IP addresses or ranges under **Trusted proxies** in Zero Spam → Settings → Advanced (your host can tell you what they are). Cloudflare is recognised automatically.

= My site is behind Cloudflare or another CDN. Does Zero Spam see the real visitor IP? =

**Yes.** Cloudflare is recognised automatically. For any other CDN, load balancer or reverse proxy, list its IP addresses or ranges under Zero Spam → Settings → Advanced → **Trusted proxies**, one per line (for example `203.0.113.0/24`), including internal hops such as a load balancer. Zero Spam only reads forwarded IP headers from those addresses, so visitors can't fake their IP. If many visitors appear to share one IP address, Zero Spam shows administrators a warning on its screens so you know to check this setting. Developers can add proxies with the `zerospam_trusted_proxies` filter.

= Does Zero Spam work with two-step login plugins like Defender Pro or Two Factor? =

**Yes, since version 5.7.14.** Zero Spam checks the username and password step of the login as before. Once that step succeeds, the later steps of the same login (such as entering a two-factor code from WPMU DEV Defender Pro or the Two Factor plugin) are not checked again. Verification that has expired or was already used, for example from a cached login page or the back button, no longer blocks the login with "Your IP has been flagged as spam/malicious": the visitor is let through, or asked to try again. Developers with custom login flows can use the `zerospam_login_skip_check` filter.

= Does deleting Zero Spam remove its data? =

Yes, by default: deleting the plugin on the Plugins screen removes its settings, activity log, blocked IPs and locations, API usage data and statistics. To keep them for a later reinstall, turn off "Delete Data on Uninstall" before you delete the plugin: Zero Spam → Settings → Logging & data. On a network, each site follows its own setting, and the network's setting decides for the network-wide data.

= Why can't I access `wp-login.php` or XML-RPC anymore? =

As of version 5.7.1, Zero Spam now actively protects `wp-login.php` and `xmlrpc.php` from blocked IPs. If you are blocked, check your IP reputation, or use the emailed rescue link on the blocked page (or the Rescue Mode key) to log in and add your IP address to Allowed IPs (Zero Spam → Rules).

Zero Spam also turns XML-RPC off for everyone by default, because bots use it to try many passwords at once. If an app you use needs it, such as the WordPress mobile app or Jetpack, turn off "Disable XML-RPC" under Zero Spam → Protection → Detection → Security.

== External services ==

Zero Spam works without any external service. The services below are only contacted when the feature that uses them is turned on, and only send what is listed.

= Zero Spam news (zerospam.org) =

When the "Zero Spam News" setting is on (it is by default), the Zero Spam admin screens show the latest articles and security notices from zerospam.org. Your server downloads the public feed at https://www.zerospam.org/articles/feed/ at most once every six hours (once for a whole network), and at most once an hour after a failed download. The request carries only a generic user agent ("Zero Spam for WordPress/" and the plugin version): no site address, no user or visitor data. Turn it off under Settings, or with the `zerospam_news_feed_enabled` filter. [Terms of use](https://www.zerospam.org/terms/), [privacy policy](https://www.zerospam.org/privacy/).

= Zero Spam API (zerospam.org) =

Used for Enhanced Protection and for sharing detections. [Terms of use](https://www.zerospam.org/terms/), [privacy policy](https://www.zerospam.org/privacy/).

* License check: when you save a license key, and to show its status in the admin (cached for up to 12 hours), the key is sent to https://www.zerospam.org/wp-json/v2/get-license.
* Enhanced Protection: when it is on and a license key is set, the IP address of a visitor who is not logged in is sent with your license key to https://www.zerospam.org/wp-json/v3/query to check its reputation. Answers are cached on your site.
* Test my protection: with Enhanced Protection on, running "Test my protection" sends a fixed test IP address with your license key to https://www.zerospam.org/wp-json/v3/query.
* Usage Data Sharing: when "Usage Data Sharing" is on, each detection is reported to https://www.zerospam.org/wp-json/v6/report/ in the background: the detection type and reason, the visitor's IP address, the email address and names entered in the blocked submission (if any), and your site's domain, name, tagline, administrator email address, language, WordPress version and plugin version.

= Stop Forum Spam =

When the Stop Forum Spam check is on, the following are sent to https://api.stopforumspam.org/ to check their reputation, unless the visitor is logged in or on your allowlist: the visitor's IP address (on page requests, the login and registration forms and XML-RPC), the email address of a comment author (when comments are protected), and the email address and IP address of a new registration (when registrations are protected, on single sites: a network's sign-up page is not checked; usernames are never sent). Running "Test my protection" sends a fixed test IP address instead. Like every WordPress request, these also carry your site's address in the user agent. Answers are cached on your site: for a day when nothing is listed, otherwise for the Cache Expiration you set (14 days by default). After repeated failures, lookups pause for 10 minutes. [Terms of use](https://www.stopforumspam.com/legal), [privacy policy](https://www.stopforumspam.com/privacy).

= Project Honeypot =

When a Project Honeypot http:BL access key is set, the visitor's IPv4 address (reversed) and your access key are looked up by DNS under dnsbl.httpbl.org. [Terms of use](https://www.projecthoneypot.org/terms_of_use.php), [privacy policy](https://www.projecthoneypot.org/privacy_policy.php).

= Geolocation: ipbase, IPinfo and ipstack =

When you enter an API key for one of these services, the IP address of visitors who are not logged in (on page requests, and for each logged detection) is sent with your key to look up its location (country, region, city), which is used for location blocks and stored with detections in the log. Answers are cached on your site for up to a week per address.

* ipbase: https://api.ipbase.com/. [Terms of service](https://ipbase.com/terms-of-service/), [privacy policy](https://ipbase.com/privacy-policy/).
* IPinfo: https://api.ipinfo.io/. [Terms](https://ipinfo.io/terms), [privacy policy](https://ipinfo.io/privacy).
* ipstack: http://api.ipstack.com/ (ipstack's free plan does not offer HTTPS). [Terms](https://ipstack.com/terms), [privacy policy](https://ipstack.com/privacy).

= Addresses you configure =

* API usage alerts: when you enter a webhook address under API Monitoring, alerts are posted to it with the alert type, message, your site's address and name and the usage figures. Addresses on your own server or private network are refused unless a developer allows them.
* Email checks: to tell whether an email address can receive mail, the domain of email addresses entered in protected forms is looked up through your server's DNS resolver.

== Screenshots ==

1. See at a glance that your site is protected: what was blocked, what is trending and anything that needs you.
2. Understand every block: who was stopped, from where and why, with the block and allow actions right there.
3. Spot the patterns in attacks on your site, by day, hour, type and country.
4. Decide who gets in: block addresses and locations for good or for a set time, and keep your own addresses on a no-lockout list.
5. Protect comments, logins and sign-ups with a switch, then prove it works with one test.
6. Make it yours: clear settings with recommended values, in light or dark.
7. Run a whole network from one place: shared defaults, locked settings and every site's activity.
8. Blocked visitors get a clear page, and a locked-out administrator gets back in with an emailed link.

== Changelog ==

= v6.0.0 =

* **feat(admin):** a new Zero Spam admin with its own menu, below Comments: Overview, Activity, Rules, Protection, Settings and Help, in light or dark, and usable with the keyboard and screen readers. It replaces the old screens; their links and bookmarks take you to the page that replaced them.
* **feat(overview):** see at a glance that your site is protected: what was blocked, a live feed of new detections, the trend over the last weeks and anything that needs you.
* **feat(activity):** search, filter and sort every detection, and open one to see who was stopped, from where and why, with Block, Allow and Delete right there. Reports show attacks by day, hour, type and country. Deleting and removing can be undone.
* **feat(rules):** one place for blocked IPs and locations (for good or for a set time), allowed IPs, blocked email domains, disallowed and allowed words, and the administrator addresses Zero Spam never locks out.
* **feat(protection):** protect comments, logins, registrations and each supported form plugin with a switch, see what each one caught, and check it all works with "Test my protection". XML-RPC stays off and the WordPress version stays hidden by default, and you can now turn either back on, for example for the WordPress mobile app or Jetpack.
* **feat(setup):** new installs get a short guided setup; updated sites get a "What's new" tour of where everything moved.
* **feat(settings):** settings in clear tabs with plain-language descriptions and recommended values, import and export, the error log, and a system report to copy for support. Copy buttons also work on sites without HTTPS.
* **feat(help):** every guide, the developer reference, and Zero Spam news and security notices (you can turn the news off under Settings).
* **feat(multisite):** a new network page: network defaults and locks, templates, an audit log, a site comparison, network statistics, and blocking an address on every site at once.
* **feat(alerts):** the API alert thresholds and the Quota Reset Date now take effect, a used-up quota now sends an alert, and quota alerts come once per period instead of every hour.
* **feat(uninstall):** "Delete Data on Uninstall" now decides whether deleting the plugin removes its data. It is on, as before.
* **fix(stopforumspam):** registration protection now works (on single sites), people with common first names are no longer refused because usernames are no longer checked, and answers are kept so the service is asked far less often.
* **fix(rules):** permanent blocks saved without a start date now always apply (before, including in 5.7.14, they only applied now and then). Block dates show in your site's time zone and no longer move when you save a block without changing them; loopback addresses such as 127.0.0.1, which would block requests from the server itself, can no longer be blocked.
* **fix(multisite):** sites follow the network's IP Allowlist unless they keep a list of their own, applying the recommended settings no longer turns network defaults into site settings, license and API keys are never shown in clear, and the built-in templates change the settings they promise.
* **removed:** the Google Maps API key (detections link to Google Maps instead of embedding a map) and the Enhanced Protection offer on the WordPress dashboard.
* **dev:** new REST routes, deprecated hooks and AJAX actions, and every other change are listed in the [full changelog on GitHub](https://github.com/Highfivery/zero-spam-for-wordpress/blob/main/CHANGELOG.md).

Older releases are listed in the [full changelog on GitHub](https://github.com/Highfivery/zero-spam-for-wordpress/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 6.0.0 =

A new Zero Spam menu, below Comments, replaces the old screens; old admin links redirect. Still needs PHP 8.2+ and WordPress 6.9+. After updating, review your settings under Zero Spam: some now take effect for the first time.
