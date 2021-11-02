=== WordPress Zero Spam ===
Contributors: bmarshall511,
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Donate link: https://www.zerospam.org/subscribe/
Requires at least: 5.2
Tested up to: 5.8.1
Requires PHP: 7.3
Stable tag: 5.1.2
License: GNU GPLv3
License URI: https://choosealicense.com/licenses/gpl-3.0/

WordPress Zero Spam makes blocking spam & malicious visitors a cinch. Just install, activate, configure and enjoy a spam-free site.

== Description ==

Quit forcing people to answer questions or confusing captchas to prove they're not spam. Stop malicious users before they ever have a chance to infiltrate your site &mdash; **introducing WordPress Zero Spam**.

[WordPress Zero Spam](https://www.highfivery.com/projects/zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) uses AI in combination with proven spam detection techniques and databases of known malicious IPs from around the world to detect and block unwanted visitors.

**Just install, activate, and enjoy a spam-free site!**

= WordPress Zero Spam features =

* No captcha, spam isn't a users' problem
* No moderation queues, spam isn't a administrators' problem
* Third-party blacklist checks ([Zero Spam](https://www.zerospam.org), [Stop Forum Spam](https://www.stopforumspam.com/))
* Automatically & manually block IPs temporarily or permanently
* Geolocate IP addresses to see where offenders are coming from
* Block entire countries, regions, zip/postal codes & cities
* Optional disallowed list using [splorp's Comment Blacklist](https://github.com/splorp/wordpress-comment-blacklist)
* Multiple detection techniques including [David Walsh's solution](https://davidwalsh.name/wordpress-comment-spam)

= WordPress Zero Spam also protects =

* WordPress core comments & user registrations
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) submissions
* [WooCommerce](https://woocommerce.com/) registration forms
* [WPForms](https://wordpress.org/plugins/wpforms-lite/) submissions
* [Formidable Form Builder](https://wordpress.org/plugins/formidable/) submissions
* and can be easily integrated into any existing theme or plugin

WordPress Zero Spam is great at blocking spam &mdash; as a site owner there's more you can do to [stop WordPress spam](https://www.benmarshall.me/stop-wordpress-spam/) in its tracks.

= WordPress Zero Spam needs your support =

**WordPress Zero Spam is free & always will be.** Please consider making a [donation](https://www.benmarshall.me/donate/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) to help encourage plugin's continued development.

* Like our [Facebook Page](https://www.facebook.com/zerospamorg/)
* Follow us on [Twitter](https://www.facebook.com/zerospamorg)
* Rate us on [WordPress](https://wordpress.org/support/plugin/zero-spam/reviews/?filter=5/#new-post)

== Installation ==

1. Upload the entire wordpress-zero-spam folder to the */wp-content/plugins/* directory.
2. Activate the plugin through the Plugins screen (*Plugins > Installed Plugins*).
3. Visit the plugin setting to configure as needed (*Settings > Zero Spam*).

For more information & developer documentation, see the [plugin’s website](https://www.benmarshall.me/wordpress-zero-spam).

== Frequently Asked Questions ==

= Does WordPress Zero Spam check Jetpack comments? =

**No.** WordPress Zero Spam is unable to integrate Jetpack. For more information, see [https://wordpress.org/support/topic/incompatible-with-jetpack-comments](https://wordpress.org/support/topic/incompatible-with-jetpack-comments).

= How do I boost performance of WordPress Zero Spam? =

**Enabled caching.** Caching is highly recommended and will prevent repeated calls to third-party API and access checks on each page visit.

= What WordPress Zero Spam WP-CLI commands are available? =

* `wp zerospam autoconfigure` &mdash; Auto-configures with recommended settings.
* `wp zerospam settings` &mdash; Displays all plugin settings.
* `wp zerospam set --[SETTING_KEY]=[VALUE]` &mdash; Updates a plugin setting.

== Screenshots ==

1. WordPress Zero Spam dashboard
2. WordPress Zero Spam detections log
3. WordPress Zero Spam blocked IPs
4. WordPress Zero Spam blacklisted IPs
5. WordPress Zero Spam settings

== Changelog ==

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

* Fixed issue with WPForms AJAX forms not getting validated by WordPress Zero Spam [#238](https://github.com/bmarshall511/wordpress-zero-spam/issues/238)
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
