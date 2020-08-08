=== WordPress Zero Spam ===
Contributors: bmarshall511, jaredatch, EusebiuOprinoiu
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Donate link: https://benmarshall.me/donate/?utm_source=wordpress_zero_spam&utm_medium=wordpress_repo&utm_campaign=donate
Requires at least: 5.2
Tested up to: 5.4.2
Requires PHP: 7.1
Stable tag: 4.9.12
License: GNU GPLv3
License URI: https://choosealicense.com/licenses/gpl-3.0/

WordPress Zero Spam makes blocking spam & malicious visitors a cinch. Just install, activate and enjoy a spam-free site.

== Description ==

Quit forcing users to answer silly questions, read confusing captchas, or take additional steps just to prove they're not spam. Stop malicious bots & hackers in their tracks before they ever have a chance to infiltrate your site &mdash; **introducing WordPress Zero Spam**.

[WordPress Zero Spam](https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam) uses AI in combination with proven spam detection techniques and a database of known malicious IPs from around the world to detect and block unwanted visitors.

In addition, it integrates with other popular plugins to provide all around protection. **Just install, activate, and enjoy a spam-free site!**

= WordPress Zero Spam features =

* **Blocks 99.9% of spam** submissions
* **No captcha**, spam isn't a users' problem
* **No moderation queues**, spam isn't a administrators' problem
* **Multiple spam detection techniques**, including *honeypot*.
* **Site security enhancements**, no config required
* **Blocks malicious IPs** from ever seeing your site
* **IP blacklist spam checks** ([Zero Spam](https://zerospam.org), [Stop Forum Spam](https://www.stopforumspam.com/), [BotScout](https://botscout.com/))
* **Auto-block IPs** when a spam detection is triggered
* **Manually block IPs** either temporarily or permanently
* **Whitelist IPs** to avoid getting blocked
* **Geolocate IP addresses** to see where spammers are coming from
* **Detailed logging** to catch & block recurring spammers
* **Charts &amp; statistics** for easy to understand spam analytics
* **Advanced settings** for complete control over spammers
* **Developer-friendly**, integrate with any theme, plugin or form

= WordPress Zero Spam also protects =

* WordPress core comments & user registrations
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) submissions
* [BuddyPress](https://wordpress.org/plugins/buddypress/) registrations
* [WPForms](https://wordpress.org/plugins/wpforms-lite/) submissions
* [WP Fluent Form](https://wordpress.org/plugins/fluentform/) submissions
* [Formidable Form Builder](https://wordpress.org/plugins/formidable/) submissions
* and can be easily integrated into any existing theme or plugin

WordPress Zero Spam is great at blocking spam &mdash; as a site owner there's more you can do to [stop WordPress spam](https://benmarshall.me/stop-wordpress-spam/) in its tracks.

= Multilingual Supported =

We’ve integrated multi language support within the framework of our plugin, so you get a translated dashboard out of the box, and developer options to add even more languages.

= Developer API =

WordPress Zero Spam is free and open source. It’s the perfect solution to stopping spam and can be extended and integrated further. It was created and developed with the developer in mind, and we have already seen some truly remarkable addons already developed.

To help you get started and learn just how to integrate with WordPress Zero Spam, visit the [plugin's documentation](https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).

= Translations =

* [French](https://translate.wordpress.org/locale/fr/default/wp-plugins/zero-spam/) – (fr_FR)
* [Italian](https://translate.wordpress.org/locale/it/default/wp-plugins/zero-spam/) – (it_IT)

= Be a contributor =

If you want to contribute, go to the [WordPress Zero Spam GitHub Repository](https://github.com/bmarshall511/wordpress-zero-spam) and see where you can help. You can also add a new language via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/zero-spam/).

= Documentation and Support =

* For documentation and tutorials, view the [documentation](https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).
* If you have any more questions, visit our support on the [Plugin’s Forum](https://wordpress.org/support/plugin/zero-spam/).
* For more information, FAQs and API documentation, check out [Zero Spam](https://zerospam.org/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).

= WordPress Zero Spam needs your support =

**WordPress Zero Spam is free — completely free & always will be.** It is hard to continue development and support for this free plugin without contributions from users like you. If you enjoy using WordPress Zero Spam and find it useful, please consider making a [donation](https://benmarshall.me/donate/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam). Your donation will help encourage and support the plugin’s continued development and better user support.

You can also show your support by:

* liking our [Facebook Page](https://www.facebook.com/zerospamorg/);
* following us on [Twitter](https://www.facebook.com/zerospamorg);
* or rating us on [WordPress](https://wordpress.org/support/plugin/zero-spam/reviews/?filter=5/#new-post) 🙂.

== Installation ==

1. Upload the entire wordpress-zero-spam folder to the */wp-content/plugins/* directory.
2. Activate the plugin through the Plugins screen (*Plugins > Installed Plugins*).
3. Visit the plugin setting to configure as needed (*Settings > WP Zero Spam*).

For more information & developer documentation, see the [plugin’s website](https://benmarshall.me/wordpress-zero-spam).

== Frequently Asked Questions ==

= Does WordPress Zero Spam check Ninja Forms submissions? =

**No.** As of v4.10.0, WordPress Zero Spam no longer checks Ninja Form submissions. Support was dropped due to its [requirement of JavaScript](https://developer.ninjaforms.com/codex/loading-the-form-via-ajax/) and how it submits forms. JavaScript is one of the techniques WordPress Zero Spam uses to determine if a submission is spam. Ninja Forms employs a similar method and has its own [spam detection](https://ninjaforms.com/blog/spam-wordpress-form/) feature.

**Does this mean WPZP won't do you any good? Absolutely not.** WPZS employs other techniques and IP blacklist checks that will help prevent malicious IP and spambots from ever seeing your site. You will still get all of the benefits of this plugin, it just won't provide the extra check on Ninja Form submissions.

= Does WordPress Zero Spam check Gravity Form submissions? =

**No.** As of v4.9.9, WordPress Zero Spam no longer checks Gravity Form submissions. Support was dropped due the numerous addon plugins that can be installed & alter GF submissions. These addons will often conflict with how WPZS validates submissions. In addition, Gravity Forms already has a spam detection option that works  similar to how this plugin detects forms. You can enable it by going to the form settings and checking the *Enable anti-spam honeypot* option. For more information, see [Gravity Forms documentation](https://docs.gravityforms.com/form-settings/).

**Does this mean WPZP won't do you any good? Absolutely not.** WPZS employs other techniques and IP blacklist checks that will help prevent malicious IP and spambots from ever seeing your site. You will still get all of the benefits of this plugin, it just won't provide the extra check on Gravity Form submissions.

= Does WordPress Zero Spam check Jetpack comments? =

**No.** WordPress Zero Spam is unable to integrate Jetpack. For more information, see [https://wordpress.org/support/topic/incompatible-with-jetpack-comments](https://wordpress.org/support/topic/incompatible-with-jetpack-comments).

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

= Does WordPress Zero Spam use cookies? =

**Yes. It does not store any kind of personally identifiable information.** Only one cookie is stored (`wpzerospam_api_blacklist`) to log the last time the site queried the blacklist APIs. This is used to boost performance so each page visit doesn't trigger an API call. The expiration can be set in *Admin > WP Zero Spam > Settings*

== Screenshots ==

1. WordPress Zero Spam dashboard
2. WordPress Zero Spam detections log
3. WordPress Zero Spam blocked IPs
4. WordPress Zero Spam blacklisted IPs
5. WordPress Zero Spam settings

== Changelog ==

= 4.9.12 =

* Enhancement - Added support for the French & Italian languages. See [#207](https://github.com/bmarshall511/wordpress-zero-spam/issues/207).
* Enhancement - Strengthened spam detection for registrations using a 'honeypot' field.
* Enhancement - Strengthened spam detection for Contact Form 7 using a 'honeypot' field.
* Fix - Fix for Contact Form 7 protection not firing.

= 4.9.11 =

* Optimization - Converted the WPZS JS to be a jQuery plugin to initialize and manage easier.
* Fix - Fix for WPZS failing when the Autoptimize plugin is set to aggregate JS files. See [#205](https://github.com/bmarshall511/wordpress-zero-spam/issues/205).

= 4.9.10 =

* Fix - Fix for PHP notice relating to an undefined variable, `strip_comment_links`. See [https://wordpress.org/support/topic/im-getting-this-after-latest-update/](https://wordpress.org/support/topic/im-getting-this-after-latest-update/).

= 4.9.9 =

* Enhancement - Strengthened spam detection for comment submission using a 'honeypot' field.
* Enhancement - Added a 'honeypot' helper functions (`wpzerospam_honeypot_field()`, `wpzerospam_get_honeypot()`) to allow other forms, plugins, and themes to easily integrate a 'honeypot' check into submissions.
* Enhancement - IP lookup links integrated in the admin dashboard and tables.
* Deprecation - Gravity Forms is no longer supported &mdash; for the time being. See the plugin FAQs for more information.

= 4.9.8 =

* Fix - Fix for a reporting issue during detections.

= 4.9.7 =

* Enhancement - Added enhanced site security features (no configuration required)
* Enhancement - Added plugin version to the information shared to Zero Spam (optional).
* Optimization - Misc. code clean-up

= 4.9.6 =

* Fix - Gravity Forms not catching spam.

= 4.9.5 =

* Enhancement - Added the *BotScout Count Minimum* field in settings to allow sites to control when a BotScout result should be marked spam/malicious. See [BotScout's documentation](https://botscout.com/api.htm) for more information.
* Enhancement - Improved performance by only querying the blacklist API once every X number of days (set in the admin settings).
* Fix - Removed double slashes in some required PHP & JS paths.

= 4.9.4 =

* Fixed issued with BuddyPress checks not running.

= 4.9.3 =

* Added a confidence threshold for Stop Form Spam checks. See [#202](https://github.com/bmarshall511/wordpress-zero-spam/issues/202);
* Added an API timeout field to adjust how long a response is allowed to take.
* Restructred several functions which fixed some interment bugs users were experiencing.
* Added ability to delete all entries from a table.
* This update **will delete all exisiting blacklisted IPs** to ensure visitors aren't getting blocked when that shouldn't be.

= 4.9.2 =

* Removed Ninja Form submission support. See FAQs for more details.
* Fix for PHP notice on the log page in the view details modal.
* Fix for double slashes in the JS URLs.
* Fix for BotScout not checking IPs
* Fix for table not found notice on activation

= 4.9.1 =

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
