=== Zero Spam for WordPress ===
Contributors: bmarshall511
Tags: protection, firewall, security, spam, spam blocker
Donate link: https://www.zerospam.org/subscribe/
Requires at least: 5.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 5.5.5
License: GNU GPLv3
License URI: https://choosealicense.com/licenses/gpl-3.0/

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

= Are you getting a `ftp_fget` PHP warning? =

Some hosts have issues with they way they access files. If you're seeing a `ftp_fget` PHP notice, setting the `FS_METHOD` constant to `direct` in `wp-config.php` above the line `/* That's all, stop editing! Happy Pressing. */` should solve the problem:

`define('FS_METHOD', 'direct');`

If hosting with Pantheon, see their [known issues page](https://pantheon.io/docs/plugins-known-issues#define-fs_method) for more information and what to do to resolve it with their `$_ENV['PANTHEON_ENVIRONMENT']` variable check.

= Where do I report security bugs found in this plugin? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/zero-spam)

== Screenshots ==

1. Dashboard
2. Log
3. Settings
4. Add blocked IP address
5. Add blocked location

== Changelog ==

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
