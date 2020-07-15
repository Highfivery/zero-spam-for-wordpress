=== WordPress Zero Spam ===
Contributors: bmarshall511
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Donate link: https://benmarshall.me
Requires at least: 5.2
Tested up to: 5.4.2
Requires PHP: 7.1
Stable tag: 4.1.0
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
* **Developer-friendly** allowing you to integrate with any theme or plugin
* **Detailed logging** to catch & block recurring spammers
* **Advanced settings** for complete control over spammers

= Plugin Support =

* WordPress comments system
* WordPress user registrations
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) submissions
* [Gravity Forms](https://www.gravityforms.com/) submissions
* [Ninja Forms](https://wordpress.org/plugins/ninja-forms/) submissions
* [BuddyPress](https://wordpress.org/plugins/buddypress/) registrations
* [Contact Form by WPForms](https://wordpress.org/plugins/wpforms-lite/) submissions

This plugin does not support with Jetpack Comments. For more information, see [https://wordpress.org/support/topic/incompatible-with-jetpack-comments](https://wordpress.org/support/topic/incompatible-with-jetpack-comments).

Have a question, comment or suggestion? Feel free to [contact me](https://benmarshall.me/contact/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam), follow me [on Twitter](https://twitter.com/bmarshall0511) or [visit my site](https://benmarshall.me/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=wordpress_zero_spam).

== Installation ==

1. Upload the entire wordpress-zero-spam folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins screen (Plugins > Installed Plugins).
3. Visit the plugin setting to configure as needed (Settings > WP Zero Spam).

For more information, see the [plugin’s website](https://benmarshall.me/wordpress-zero-spam).

== Frequently Asked Questions ==

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

= v4.0.0 =

* A complete rewrite of the original plugin

= v4.1.0- =

* Added support for [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
* Added support for [Gravity Forms](https://www.gravityforms.com/)
* Added support for [Ninja Forms](https://wordpress.org/plugins/ninja-forms/)
* Added support for [BuddyPress](https://wordpress.org/plugins/buddypress/)
* Added support for [Contact Form by WPForms](https://wordpress.org/plugins/wpforms-lite/)
