=== WordPress Zero Spam ===
Contributors: bmarshall511, jaredatch, EusebiuOprinoiu
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Donate link: https://benmarshall.me/donate/?utm_source=wordpress_zero_spam&utm_medium=wordpress_repo&utm_campaign=donate
Requires at least: 5.2
Tested up to: 5.4.2
Requires PHP: 7.1
Stable tag: 4.9.2
License: GNU GPLv3
License URI: https://choosealicense.com/licenses/gpl-3.0/

WordPress Zero Spam makes blocking spam & malicious visitors a cinch. Just install, activate and enjoy a spam-free site.

== Description ==

Quit forcing users to answer silly questions, read confusing captchas, or take additional steps just to prove they're not spam. Stop malicious bots & hackers in their tracks before they ever have a chance to infiltrate your site &mdash; introducing WordPress Zero Spam.

[WordPress Zero Spam](https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) uses AI in combination with proven spam detection techniques and a database of known malicious IPs from around the world to detect and block unwanted visitors. In addition, it integrates with other popular plugins to provide all around protection. Just install, activate, and enjoy a spam-free site.

= WordPress Zero Spam features =

* **No captcha**, spam isn't a users' problem
* **No moderation queues**, spam isn't a administrators' problem
* **Blocks 99.9% of spam** submissions
* **Blocks malicious IPs** from ever seeing your site
* **Check IPs against spam blacklists** ([Zero Spam](https://zerospam.org), [Stop Forum Spam](https://www.stopforumspam.com/), [BotScout](https://botscout.com/))
* **Auto-block IPs** when a spam detection is triggered
* **Manually block IPs** either temporarily or permanently
* **Developer-friendly**, integrate with any theme, plugin or form
* **Detailed logging** to catch & block recurring spammers
* **Geolocate IP addresses** to see where spammers are coming from
* **Whitelist IPs** to avoid getting blocked
* **Advanced settings** for complete control over spammers
* **Charts &amp; statistics** for easy to understand spam analytics

= WordPress Zero Spam also protects =

* WordPress core comments & user registrations
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) submissions
* [Gravity Forms](https://www.gravityforms.com/) submissions
* [BuddyPress](https://wordpress.org/plugins/buddypress/) registrations
* [WPForms](https://wordpress.org/plugins/wpforms-lite/) submissions
* [WP Fluent Form](https://wordpress.org/plugins/fluentform/) submissions
* [Formidable Form Builder](https://wordpress.org/plugins/formidable/) submissions
* and can be easily integrated into any existing theme or plugin

WordPress Zero Spam is great at blocking spam &mdash; as a site owner there's more you can do to [stop WordPress spam](https://benmarshall.me/stop-wordpress-spam/) in its tracks.

= Issues/Feature Requests =

**Something not working as expected?** I wanna hear about it. Have an idea on how to improve the plugin? I'm all ears.

* [Submit an issue or feature request](https://github.com/bmarshall511/wordpress-zero-spam/issues) on GitHub
* [Contact me directly](https://benmarshall.me/contact/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) on my site
* [Follow me](https://twitter.com/bmarshall0511) on Twitter

= Show Your Support =

WordPress Zero Spam is free &mdash; completely free & always will be. No premium versions or addons you've gotta buy to access additional features. Help support it's development by [donating](https://benmarshall.me/donate/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) today.

== Installation ==

1. Upload the entire wordpress-zero-spam folder to the */wp-content/plugins/* directory.
2. Activate the plugin through the Plugins screen (*Plugins > Installed Plugins*).
3. Visit the plugin setting to configure as needed (*Settings > WP Zero Spam*).

For more information & developer documentation, see the [plugin’s website](https://benmarshall.me/wordpress-zero-spam).

== Frequently Asked Questions ==

= Does WordPress Zero Spam check Ninja Forms submissions? =

No. As of v4.10.0, WordPress Zero Spam no longer checks Ninja Form submissions. Support was dropped due its [required of JavaScript](https://developer.ninjaforms.com/codex/loading-the-form-via-ajax/) and how it submits forms. JavaScript is one of the techniques WordPress Zero Spam uses to determine if a submission is spam. Ninja Forms employs a similar method and has its own [spam detection](https://ninjaforms.com/blog/spam-wordpress-form/) feature.

= Does WordPress Zero Spam check Jetpack comments? =

No. WordPress Zero Spam is unable to integrate Jetpack. For more information, see [https://wordpress.org/support/topic/incompatible-with-jetpack-comments](https://wordpress.org/support/topic/incompatible-with-jetpack-comments).

= Spam coments are still getting through, help! =

WordPress Zero Spam relies on the default core form id (`#commentform`) in order to check comment submissions. Verify your comment forms have this ID or add the class `wpzerospam` to enable it on your site.

= All registrations are marked as spam, help! =

This is most likely due to a plugin or theme overriding the default markup of the registration form. Verify the form has an id of `registerform` or add the `wpzerospam` class to it.

Example with `registerform` id:

`<form name="registerform" id="registerform" action="https://yourdomain.local/login/?action=register" method="post" novalidate="novalidate">`

Example with `wpzerospam` class:

`<form name="registerform" class="wpzerospam" action="https://yourdomain.local/login/?action=register" method="post" novalidate="novalidate">`

If you need help, please don't hesitate to [reach out](https://benmarshall.me/contact/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).

= How do I integrate this into another plugin or theme? =

It's easy as adding the class `wpzerospam` to the `form` element, then adding a check in the form processor that the `wpzerospam_key` post value matches the option value in the database using the `wpzerospam_key_check()` helper function. See the [plugin's documentation](https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) for more information on available hooks & functions.

= Is JavaScript required to check form submissions? =

Yes. One of the many techniques WordPress Zero Spam employs requires JavaScript be enabled to work properly.

== Screenshots ==

1. WordPress Zero Spam dashboard
2. WordPress Zero Spam detections log
3. WordPress Zero Spam blocked IPs
4. WordPress Zero Spam blacklisted IPs
5. WordPress Zero Spam settings

== Changelog ==

= 4.9.2 =

* Removed Ninja Form submission support. See FAQs for more details.
* Fix for PHP notice on the log page in the view details modal.
* Fix for double slashes in the JS URLs.
* Fix for BotScout not checking IPs
* Fix for table not found notice on activation

4.9.1
Fix for PHP notice on the modals for spam detections

= 4.9.0 =

* Added support for [Formidable Forms](https://wordpress.org/plugins/formidable/). See [#112](https://github.com/bmarshall511/wordpress-zero-spam/issues/112).
* Added additional country regions
* UI enhancments to the admin tables
* Added a spam detection world map to the dashboard

= 4.8.2 =

* Fix for admin table paging keeping set filters

= 4.8.1 =

* Fix for charts not showing up in the dashboard

= 4.8.0 =

* Added filter & seach options to admin tables
* Various performance enhancements
* Added ability to whitelist IP addresses

= 4.7.1 =

* Update to the WP Zero Spam API

= 4.7.0 =

* Various performance enhancements
* Updates to the admin tables
* Improved UI
* Added functionality & option to permanently auto-block after X spam detections
* Added ability to share spam detections with WordPress Zero Spam to strengthen it's ability to detect spammers

= 4.6.0 =

* Added option to strip links from comments
* Added option to strip & disable the comment author website field
* Added integration with the BotScout blacklist API

= 4.5.0 =

* Added integration with the Stop Forum Spam known spammy IPs
* Fixed issue with Gravity Forms not being enabled by default

= 4.4.1 =

* Fix for Gravity Forms not submitting

= 4.4.0 =

* Misc. code clean-up
* Added support for the Fluent Forms plugin

= 4.3.10 =

* Updated get_plugin_data calls to use a constant. See [#196](https://github.com/bmarshall511/wordpress-zero-spam/issues/196)
* Added additional country regions for geolocation lookup
* Renamed 'addons' to 'integrations'
* Fixed issue with WPForm spam detections
* Fix for plugin deactivation from v3 to v4 upgrade

= 4.3.9 =

* Fix for `Notice: Undefined index: verify_ninja_form`. See [#195](https://github.com/bmarshall511/wordpress-zero-spam/issues/195)

= 4.3.8 =

* Fix for `Call to undefined function wpzerospam_tables()` error

= 4.3.7 =

* Optimized scripts & when they get loaded (only when needed)
* Fixed bug with incrementing spam detections in the blocked IPs log

= 4.3.6 =

* Added a check for the `is_plugin_active` functions to ensure they're available before calling it

= 4.3.5 =

* Fix for `Uncaught Error: Call to undefined function get_plugin_data()`. See [#193](https://github.com/bmarshall511/wordpress-zero-spam/issues/193).

= 4.3.4 =

* Fixed issue with adding/updating IP addresses manually
* Fixed PHP notice for missing submission data on the log chart
* Fixed PHP notice for "Undefined index: log_blocked_ips". See [#191](https://github.com/bmarshall511/wordpress-zero-spam/issues/191)
* Updated the admin scripts to only login on the plugin admin pages
* Fixed an issue with default add-on plugin options being disabled on first save. See [#192](https://github.com/bmarshall511/wordpress-zero-spam/issues/192)

= 4.3.3 =

* Fix for `REFERRER_ANALYTICS` unknown constant

= 4.3.2 =

* Fix for Gravity Forms PHP notice. See [#188](https://github.com/bmarshall511/wordpress-zero-spam/issues/188)
* Add more stats & charts. See [#184](https://github.com/bmarshall511/wordpress-zero-spam/issues/184)

= 4.3.1 =

* Fixing plugin version

= 4.3.0 =

* Added the ability to manually add blocked IPs. See [#185](https://github.com/bmarshall511/wordpress-zero-spam/issues/185)
* Fixed the ignored start & end date of blocked IPs
* Added the ability to auto-block an IP when spam is detected. See [#185](https://github.com/bmarshall511/wordpress-zero-spam/issues/185)
* Added raw data to spammer log table
* Added the ability to uninstall options on Multisite. See [#187](https://github.com/bmarshall511/wordpress-zero-spam/pull/187)

= 4.2.0 =

* Re-implemented logging & added admin pages to prepare for charts & statistics. See [#181](https://github.com/bmarshall511/wordpress-zero-spam/issues/181)

= 4.1.3 =

* Fixed JS errors for some 3rd-party plugins. See [#178](https://github.com/bmarshall511/wordpress-zero-spam/issues/178)
* Fixed caching conflicts issue relating to using cookies to set & get keys. See [#177](https://github.com/bmarshall511/wordpress-zero-spam/issues/177)
* When plugin is uninstalled, plugin-related data is now deleted. See [#179](https://github.com/bmarshall511/wordpress-zero-spam/issues/179)
* Added an option in the plugin settings to determine how spam detections are handled. See [#180](https://github.com/bmarshall511/wordpress-zero-spam/issues/180)

= 4.1.2 =

* Fixed issue with plugin settings not saving. See [#176](https://github.com/bmarshall511/wordpress-zero-spam/issues/176).
* Fixed some PHP notices. See [#175](https://github.com/bmarshall511/wordpress-zero-spam/issues/175).

= v4.1.1 =

* Fixed missing JS for new 3rd-party plugin support

= v4.1.0 =

* Added support for [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
* Added support for [Gravity Forms](https://www.gravityforms.com/)
* Added support for [Ninja Forms](https://wordpress.org/plugins/ninja-forms/)
* Added support for [BuddyPress](https://wordpress.org/plugins/buddypress/)
* Added support for [Contact Form by WPForms](https://wordpress.org/plugins/wpforms-lite/)

= v4.0.0 =

* A complete rewrite of the original plugin
