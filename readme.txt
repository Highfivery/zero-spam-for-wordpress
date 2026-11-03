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

**Zero Spam is a free anti-spam plugin for WordPress that stops spam bots before they post: no captchas, no moderation queue, nothing to clean up.** It protects your comments, logins, registrations and the forms you already use, and it runs on more than 20,000 WordPress sites.

= Bots bounce. People get through. =

Most anti-spam tools either let spam in and leave you to sort it, or make every visitor prove they are human. Zero Spam does neither. It checks each comment, login, sign-up and form submission quietly in the background and turns bots away at the door. Real visitors never see a puzzle, and your comment queue and inbox stay clean.

= Why site owners choose Zero Spam =

* **No captchas.** Visitors don't click on traffic lights, so you keep the comments, sign-ups and sales a captcha drives away.
* **No moderation queue.** Spam is refused before it is saved, so there is nothing to review or delete.
* **Protected in about a minute.** Activate it and a short guided setup turns on the recommended protection.
* **See exactly what was stopped.** A clear overview, a searchable activity log and reports by day, hour, type and country.
* **You can't lock yourself out.** Your own address is never blocked, and if anything goes wrong an emailed rescue link gets you back in.
* **Light on your site.** Built-in checks run on your own server, and answers from outside services are cached, so a visitor is looked up once, not on every page.
* **Free and open source.** GPL licensed, on WordPress.org since 2014, with every line of code on GitHub.

= What Zero Spam protects =

* **Comments**, including bots that post without ever loading your form.
* **Logins** on wp-login.php, and XML-RPC, which bots use to try many passwords at once (turned off by default).
* **Registrations**, including fake accounts and throwaway email addresses.
* **Your forms**, from contact and newsletter forms to checkout and donation forms (the full list is below).
* **Your whole site**, if you choose: block known bad addresses, or whole countries, regions and cities, from every page.

= How it stops spam =

Zero Spam uses layers, so a bot that gets past one meets the next. Every layer can be turned on or off.

1. **Built-in bot checks:** a hidden check that bots fail and people never notice, blocked words, and a list of disposable email domains.
2. **Reputation checks (optional, free):** [Stop Forum Spam](https://www.stopforumspam.com/) and [Project Honeypot](https://www.projecthoneypot.org/) recognise addresses already reported for spam.
3. **Your own rules:** block addresses, countries, regions, cities or postcodes, for good or for a set time. Location rules use a free key from ipbase, IPinfo or ipstack.
4. **Enhanced Protection (optional, paid):** real-time checks against the [Zero Spam](https://www.zerospam.org/) network's reputation database. It needs a license key from zerospam.org.

= Works with the plugins you already use =

* **Forms:** [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), [WPForms](https://wordpress.org/plugins/wpforms-lite/), [Gravity Forms](https://www.gravityforms.com/), [Formidable Forms](https://wordpress.org/plugins/formidable/), [Fluent Forms](https://wordpress.org/plugins/fluentform/) and [Elementor Pro](https://elementor.com/) forms.
* **Stores and donations:** [WooCommerce](https://wordpress.org/plugins/woocommerce/) customer registrations, and [GiveWP](https://wordpress.org/plugins/give/) donation forms, where it helps stop card testing with stolen cards.
* **Memberships and newsletters:** [ProfilePress](https://wordpress.org/plugins/wp-user-avatar/) registrations and [Mailchimp for WordPress](https://wordpress.org/plugins/mailchimp-for-wp/) sign-ups.

Setup switches on protection for each one it finds; turn on any you add later under Zero Spam › Protection › Integrations.

= Built for agencies, hosts and networks =

* **Multisite:** set network defaults, lock the settings sites can't change, apply templates, compare sites, read an audit log and block an address on every site at once.
* **Automation:** manage settings with the REST API and WP-CLI, and extend Zero Spam with documented hooks.
* **Behind a CDN or proxy:** Cloudflare is recognised automatically, and you can list any other proxy, so Zero Spam sees each visitor's real address.

= Your data stays yours =

Zero Spam works without any outside service, and its activity log stays in your own database. Services that receive data about your site or visitors are only contacted once you choose them, either in setup, which shows you what each one receives, or in the settings. The External services section below lists exactly what each one receives, with its terms of use and privacy policy.

= Get started =

1. Install Zero Spam from **Plugins › Add New** and activate it.
2. Open **Zero Spam** in the admin menu, below Comments.
3. Follow the short setup. Your site is protected as soon as you finish.

Questions? The built-in Help screen has every guide, and the [wiki](https://github.com/Highfivery/zero-spam-for-wordpress/wiki) covers the developer reference.

= Source code and build =

The plugin's complete source code is on GitHub: [Highfivery/zero-spam-for-wordpress](https://github.com/Highfivery/zero-spam-for-wordpress). The admin scripts and styles in `assets/dist/` are built from the human-readable sources in `assets/src/` with [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts). To build them yourself, check out the repository and, with Node.js 22, run `npm ci && npm run build`. The sources in `assets/src/` and the build tools are not part of the plugin download (they are listed in `.distignore`); get them from the repository. The fonts in `assets/fonts/` (Plus Jakarta Sans and JetBrains Mono, SIL Open Font License 1.1) are included unmodified; their sources are listed in `assets/fonts/FONTS.md`.

== Installation ==

1. In your WordPress admin, go to **Plugins › Add New**, search for **Zero Spam**, then click **Install Now** and **Activate**. (Or upload the `zero-spam` folder to `/wp-content/plugins/` and activate it on the Plugins screen.)
2. Open **Zero Spam** in the admin menu, below Comments.
3. Follow the short setup. It turns on the recommended protection and shows you each outside service that would receive data, with what it receives, so you choose.
4. Optional: add a free Project Honeypot or geolocation key, or an Enhanced Protection license key, under **Zero Spam › Protection**.

== Frequently Asked Questions ==

= What is Zero Spam for WordPress? =

Zero Spam is a free WordPress plugin that blocks spam and malicious bots on comments, logins, registrations and forms without captchas. It checks each submission in the background, refuses the ones that come from bots or addresses known for spam, and lets real people through. It has been on WordPress.org since 2014 and runs on more than 20,000 sites.

= How do I stop spam comments in WordPress without a captcha? =

Install and activate Zero Spam, then follow its short setup. Comment protection is on as soon as you finish: bots are refused before their comment is saved, so nothing reaches your moderation queue, and your visitors comment as usual with no puzzle to solve.

= How do I stop fake user registrations in WordPress? =

Turn on registration protection under Zero Spam › Protection (the setup does this for you). Zero Spam refuses sign-ups from bots, from addresses reported for spam, and from disposable email domains. It also protects WooCommerce and ProfilePress registrations.

= Is Zero Spam free? =

Yes. Everything on this page except Enhanced Protection is free, including the activity log, reports, rules, the form integrations and multisite support. Enhanced Protection is an optional paid upgrade from [zerospam.org](https://www.zerospam.org/) that adds real-time checks against the Zero Spam network's reputation database.

= Will Zero Spam block my real visitors or customers? =

It is built not to. Logged-in users are never blocked, addresses on your allowlist are never checked, and you can make outside reputation checks stricter or looser. If someone is blocked, Zero Spam › Activity shows exactly why, and you can allow them with one click.

= Which form plugins does Zero Spam work with? =

Contact Form 7, WPForms, Gravity Forms, Formidable Forms, Fluent Forms, Elementor Pro forms, Mailchimp for WordPress, WooCommerce, GiveWP and ProfilePress. Setup switches on protection for each one it finds, and you can switch any of them on or off under Zero Spam › Protection › Integrations.

= Will Zero Spam slow down my site? =

It is designed to stay light. The built-in checks run on your own server without calling anyone, and answers from optional services are cached on your site (for example, a Stop Forum Spam answer is kept for up to 14 days), so a visitor is looked up once rather than on every page.

= Does Zero Spam send my visitors' data anywhere? =

Only to the services you choose, and only what is listed for each under External services. With none chosen, nothing about your visitors leaves your site. Setup offers Stop Forum Spam lookups and shows what they send, and sharing blocked attempts with the Zero Spam network is off unless you turn it on.

= Does Zero Spam work on WordPress Multisite? =

Yes. A network page lets you set defaults for every site, lock settings sites can't change, apply templates, compare sites, read an audit log and block an address across the whole network. Each site still has its own activity log and rules.

= I blocked myself! How do I get back in? =

On the blocked page, open **Help for site owners**, enter an administrator's email address and press **Email me a rescue link**. The link works once, for 15 minutes, and lets that browser in for an hour so you can add your address to Allowed IPs under Zero Spam › Rules. Zero Spam also saves the address you activate it from as one it never locks out.

If email doesn't work on your site, define `ZEROSPAM_RESCUE_KEY` in `wp-config.php` (for example `define( 'ZEROSPAM_RESCUE_KEY', 'a-long-random-secret' );`) and visit any page with `?zerospam_rescue=` and your key added to the address: every check is skipped in that browser for an hour. As a last resort, rename the `wp-content/plugins/zero-spam` folder over FTP.

If every visitor, including you, is suddenly blocked, your site is probably behind a CDN or proxy Zero Spam doesn't know about: get back in, then add it under Trusted proxies (next question).

= My site uses Cloudflare or another CDN. Does Zero Spam see real visitor addresses? =

Yes. Cloudflare is recognised automatically. For any other CDN, load balancer or proxy, list its addresses or ranges under Zero Spam › Settings › Advanced › **Trusted proxies**, one per line (for example `203.0.113.0/24`). Zero Spam only trusts forwarded addresses from those proxies, so visitors can't fake theirs. If many visitors seem to share one address, Zero Spam warns you so you know to check this.

= Does it work with two-factor login plugins? =

Yes. Zero Spam checks the username and password step, then leaves the later steps of the same login alone, so plugins such as Two Factor and WPMU DEV Defender Pro work as usual.

= Why doesn't the WordPress app or Jetpack connect any more? =

Zero Spam turns XML-RPC off by default, because bots use it to try many passwords at once. If an app you use needs it, turn off "Disable XML-RPC" under Zero Spam › Protection › Detection › Security.

= Does Zero Spam check Jetpack comments? =

No. Jetpack comments are posted through Jetpack's own service, which Zero Spam doesn't integrate with.

= Can developers automate Zero Spam? =

Yes. Settings can be read and changed through the REST API (with Application Passwords) and WP-CLI, for example `wp zerospam autoconfigure`, `wp zerospam settings` and `wp zerospam set --share_data=enabled`. Zero Spam › Help lists every command, route and hook.

= Does deleting Zero Spam remove its data? =

Yes, by default: its settings, activity log, blocks and statistics are removed. To keep them for a later reinstall, turn off "Delete Data on Uninstall" under Zero Spam › Settings › Logging & data first. On a network, each site follows its own setting unless the network locks it.

= How do I report a security issue? =

Through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/zero-spam), which validates and handles reports with us.

== External services ==

Zero Spam works without any external service. The services below are only contacted when the feature that uses them is turned on, and only send what is listed. The only one on by default is the Zero Spam news feed, which sends nothing about your site or visitors. Stop Forum Spam is offered, with what it sends, in first-run setup; Usage Data Sharing starts off.

= Zero Spam news (zerospam.org) =

When the "Zero Spam News" setting is on (it is by default), the Zero Spam admin screens show the latest articles and security notices from zerospam.org. Your server downloads the public feed at https://www.zerospam.org/articles/feed/ at most once every six hours (once for a whole network), and at most once an hour after a failed download. The request carries only a generic user agent ("Zero Spam for WordPress/" and the plugin version): no site address, no user or visitor data. Turn it off under Settings, or with the `zerospam_news_feed_enabled` filter. [Terms of use](https://www.zerospam.org/terms/), [privacy policy](https://www.zerospam.org/privacy/).

= Zero Spam API (zerospam.org) =

Used for Enhanced Protection and for sharing detections. [Terms of use](https://www.zerospam.org/terms/), [privacy policy](https://www.zerospam.org/privacy/).

* License check: when you save a license key, and to show its status in the admin (cached for up to 12 hours), the key is sent to https://www.zerospam.org/wp-json/v2/get-license.
* Enhanced Protection: when it is on and a license key is set, the IP address of a visitor who is not logged in is sent with your license key to https://www.zerospam.org/wp-json/v3/query to check its reputation. Answers are cached on your site.
* Test my protection: with Enhanced Protection on, running "Test my protection" sends a fixed test IP address with your license key to https://www.zerospam.org/wp-json/v3/query.
* Usage Data Sharing: off unless you turn it on (in the setup or under Settings). When it is on, each detection is reported to https://www.zerospam.org/wp-json/v6/report/ in the background: the detection type and reason, the visitor's IP address, the email address, names and username entered in the blocked submission (if any), and your site's domain, name, tagline, administrator email address, language, WordPress version and plugin version.

= Stop Forum Spam =

When the Stop Forum Spam check is on, the following are sent to https://api.stopforumspam.org/ to check their reputation, unless the visitor is logged in or on your allowlist: the visitor's IP address (on page requests, the login and registration forms and XML-RPC), the email address of a comment author (when comments are protected), and the email address and IP address of a new registration (when registrations are protected, on single sites: a network's sign-up page is not checked; usernames are never sent). Running "Test my protection" sends a fixed test IP address instead. Like every WordPress request, these also carry your site's address in the user agent. Answers are cached on your site: for a day when nothing is listed, otherwise for the Cache Expiration you set (14 days by default). After repeated failures, lookups pause for 10 minutes. [Terms of use](https://www.stopforumspam.com/legal), [privacy policy](https://www.stopforumspam.com/privacy).

= Project Honeypot =

When a Project Honeypot http:BL access key is set, the visitor's IPv4 address (reversed) and your access key are looked up by DNS under dnsbl.httpbl.org. [Terms of use](https://www.projecthoneypot.org/terms_of_use.php), [privacy policy](https://www.projecthoneypot.org/privacy_policy.php).

= Geolocation: ipbase, IPinfo and ipstack =

When you enter an API key for one of these services, the IP address of visitors who are not logged in (on page requests, and for each logged detection) is sent with your key to look up its location (country, region, city), which is used for location blocks and stored with detections in the log. Answers are cached on your site for the Cache Expiration you set (14 days by default, or 30 days for ipbase with the recommended settings).

* ipbase: https://api.ipbase.com/. [Terms of service](https://ipbase.com/terms-of-service/), [privacy policy](https://ipbase.com/privacy-policy/).
* IPinfo: https://api.ipinfo.io/. [Terms](https://ipinfo.io/terms), [privacy policy](https://ipinfo.io/privacy).
* ipstack: http://api.ipstack.com/ (ipstack's free plan does not offer HTTPS). [Terms](https://ipstack.com/terms), [privacy policy](https://ipstack.com/privacy).

= Addresses you configure =

* API usage alerts: when you enter a webhook address under API Monitoring, alerts are posted to it with the alert type, message, your site's address and name and the usage figures. Addresses on your own server or private network are refused unless a developer allows them.
* Email checks: to tell whether an email address can receive mail, the domain of email addresses entered in protected forms is looked up through your server's DNS resolver.

== Screenshots ==

1. Know you're protected at a glance: what was blocked, how it's trending, and anything that needs you.
2. Turn protection on with a switch, then prove it works with one click.
3. Every block explained: who was stopped, from where and why, with Block and Allow right there.
4. A short guided setup gets your site protected in about a minute.
5. Spot attack patterns by day, hour, type and country.
6. Block addresses or whole locations, for good or for a while, without ever locking yourself out.
7. Bots get a clear "blocked" page, and a locked-out administrator gets back in with an emailed link.
8. Plain-language settings with recommended values, in light or dark.
9. Run a whole multisite network from one place: shared defaults, locked settings and every site's activity.

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
* **fix(privacy):** sharing blocked attempts with Zero Spam is now off unless you turn it on, Stop Forum Spam lookups are a choice in setup, and "Use Recommended Settings" no longer turns on either. The setup and settings say exactly what each one sends.
* **fix(stopforumspam):** registration protection now works (on single sites), people with common first names are no longer refused because usernames are no longer checked, and answers are kept so the service is asked far less often.
* **fix(rules):** permanent blocks saved without a start date now always apply (before, including in 5.7.14, they only applied now and then). Block dates show in your site's time zone and no longer move when you save a block without changing them; loopback addresses such as 127.0.0.1, which would block requests from the server itself, can no longer be blocked.
* **fix(multisite):** sites follow the network's IP Allowlist unless they keep a list of their own, applying the recommended settings no longer turns network defaults into site settings, license and API keys are never shown in clear, and the built-in templates change the settings they promise.
* **removed:** the Google Maps API key (detections link to Google Maps instead of embedding a map) and the Enhanced Protection offer on the WordPress dashboard.
* **dev:** new REST routes, deprecated hooks and AJAX actions, and every other change are listed in the [full changelog on GitHub](https://github.com/Highfivery/zero-spam-for-wordpress/blob/main/CHANGELOG.md).

Older releases are listed in the [full changelog on GitHub](https://github.com/Highfivery/zero-spam-for-wordpress/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 6.0.0 =

A new Zero Spam menu, below Comments, replaces the old screens; old admin links redirect. Still needs PHP 8.2+ and WordPress 6.9+. After updating, review your settings under Zero Spam: some now take effect for the first time.
