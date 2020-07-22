=== WordPress Zero Spam ===
Contributors: bmarshall511, jaredatch, EusebiuOprinoiu
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Donate link: https://benmarshall.me/donate/?utm_source=wordpress_zero_spam&utm_medium=wordpress_repo&utm_campaign=donate
Requires at least: 5.2
Tested up to: 5.4.2
Requires PHP: 7.1
Stable tag: 4.3.10
License: GNU GPLv3
License URI: https://choosealicense.com/licenses/gpl-3.0/

WordPress Zero Spam makes blocking spammers a cinch. Install, activate and enjoy a spam-free site &mdash; with third-party plugin support.

== Description ==

Why force users to prove that they're humans by filling out captchas? Let bots prove they're not bots with the [WordPress Zero Spam plugin](https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).

WordPress Zero Spam blocks spam submissions including comments, registrations and more automatically without any config or setup. Just install, activate, and enjoy a spam-free site.

WordPress Zero Spam was initially built based on the work by [David Walsh](http://davidwalsh.name/wordpress-comment-spam).

= Plugin Features =

* **No captcha**, spam isn't a users' problem
* **No moderation queues**, spam isn't a administrators' problem
* **Blocks 99.9% of spam** submissions
* **Blocks spammy IPs** from ever seeing your site
* **Auto-block IPs** when a spam detection is triggered
* **Manually block IPs** either temporarily or permanently
* **Developer-friendly**, integrate with any theme, plugin or form
* **Detailed logging** to catch & block recurring spammers
* **Advanced settings** for complete control over spammers
* **Charts &amp; statistics** for easy to understand spam analytics

= Plugin Support =

* WordPress comments system
* WordPress user registrations
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) submissions
* [Gravity Forms](https://www.gravityforms.com/) submissions
* [Ninja Forms](https://wordpress.org/plugins/ninja-forms/) submissions
* [BuddyPress](https://wordpress.org/plugins/buddypress/) registrations
* [Contact Form by WPForms](https://wordpress.org/plugins/wpforms-lite/) submissions

<small>This plugin does not support with Jetpack Comments. For more information, see [https://wordpress.org/support/topic/incompatible-with-jetpack-comments](https://wordpress.org/support/topic/incompatible-with-jetpack-comments).</small>

Have a question, comment or suggestion? Feel free to [contact me](https://benmarshall.me/contact/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam), follow me [on Twitter](https://twitter.com/bmarshall0511) or [visit my site](https://benmarshall.me/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).

== Installation ==

1. Upload the entire wordpress-zero-spam folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins screen (Plugins > Installed Plugins).
3. Visit the plugin setting to configure as needed (Settings > WP Zero Spam).

For more information, see the [plugin’s website](https://benmarshall.me/wordpress-zero-spam).

== Frequently Asked Questions ==

= Why aren't spammy comments being blocked? =

WordPress Zero Spam relies on the default core form id (`#commentform`) in order to detect comments. Verify your comment forms have this ID or add the class `wpzerospam` to them so the plugin knows to it should attempt to detect spam comments.

= How can I integrate this into a plugin, theme or custom form? =

It's easy as adding the class `wpzerospam` to the `form` element, then adding a check in the form processor that the `wpzerospam_key` post value matches the option value in the database using the `wpzerospam_key_check()` helper function.

= Why does my registration form think every submission is spam? =

This is most likely due to a plugin or theme overriding the default markup of the registration form. Verify the form has an id of `registerform` or add the `wpzerospam` class to it.

Example with `registerform` id:

`<form name="registerform" id="registerform" action="https://yourdomain.local/login/?action=register" method="post" novalidate="novalidate">`

Example with `wpzerospam` class:

`<form name="registerform" class="wpzerospam" action="https://yourdomain.local/login/?action=register" method="post" novalidate="novalidate">`

= Is JavaScript required for this plugin to work? =

Yes, that's what does the magic and keeps spam bots out.

= What action hooks are available? =

* `wpzerospam_comment_spam` - Fires when a spam comment is detected.
* `wpzerospam_registration_spam` - Fires when a spam registration is detected.
* `wpzerospam_cf7_spam` - Fires when a spam submission is made with a CF7 form.
* `wpzerospam_gform_spam` - Fires when a spam submission is made with a Gravity Form.
* `wpzerospam_ninja_forms_spam` - Fires when a spam submission is made with a Ninja Form.
* `wpzerospam_bp_registration_spam` - Fires when a BuddyPress spam registration is detected.
* `wpzerospam_wpform_spam` - Fires when a spam submission is made with a WPForm.

== Changelog ==

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
