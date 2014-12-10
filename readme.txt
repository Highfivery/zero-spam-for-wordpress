=== WordPress Zero Spam ===
Contributors: bmarshall511, afragen, tangrufus, leewillis77, macbookandrew
Donate link: https://www.gittip.com/bmarshall511/
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Requires at least: 3.0.0
Tested up to: 4.0.1
Stable tag: 1.5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Zero Spam makes blocking spam comments a cinch. Install, activate and enjoy a spam-free site.

== Description ==

**Why should your users prove that they're humans by filling out captchas? Let bots prove they're not bots with the <a href="http://www.benmarshall.me/wordpress-zero-spam-plugin/" target="_blank">WordPress Zero Spam plugin</a>.**

WordPress Zero Spam blocks registration spam and spam in comments automatically without any additional config or setup. Just install, activate, and enjoy a spam-free site.

Zero Spam was initially built based on the work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.

Major features in WordPress Zero Spam include:

* **No captcha**, because spam is not users' problem
* **No moderation queues**, because spam is not administrators' problem
* **Blocks spam registrations & comments** with the use of JavaScript
* **Contact Form 7 support** if installed and activated
* **Gravity Form support** if installed and activated
* **BuddyPress support** if installed and activated
* **Supports caching plugins** to help provide great performance
* **Blocks spammy IPs** from ever seeing your site
* **Extend the plugin** with action hooks
* **Optional logging**, so you can see who's trying to spam
* **Advanced settings** for complete control

**Languages:** English

If you have suggestions for a new add-on, feel free to email me at me@benmarshall.me. Want regular updates? <a href="https://twitter.com/bmarshall0511">Follow me on Twitter</a> or <a href="http://www.benmarshall.me" target="_blank">visit my blog</a>.

== Installation ==

1. Upload the `zero-spam` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Is JavaScript required for this plugin to work? =

Yes, that's what does the magic and keeps spam bots out.

= I keep getting 'There was a problem processing your comment.' =

Be sure JavaScript is enabled and there are no JS errors.

= Can I extend the plugin with action hooks? =

Yes, see below:

* `zero_spam_found_spam_registration` - Runs after a spam registration is detected
* `zero_spam_found_spam_comment` - Runs after a spam comment is detected
* `zero_spam_found_spam_cf7_form_submission` - Runs after a spam Contact Form 7 form submission is detected
* `zero_spam_found_spam_gf_form_submission` - Runs after a spam Gravity Form submission is detected
* `zero_spam_ip_blocked` - Runs after a blocked IP attempts to visit the site
* `zero_spam_found_spam_buddypress_registration` - Runs when a BuddyPress spam registration is detected

= Does this plugin support Contact Form 7 forms? =

Yes! Thanks to @leewillis77.

= Does this plugin support Gravity Forms forms? =

Yes! Thanks to @shazahm1.

= Does this plugin support BuddyPress? =

Yes!

= Does this plugin work with caching plugins like W3 Total Cache? =

Yes! Thanks to @shazahm1.

= Does this plugin work with multisite? =

Yes! Thanks to @afragen. When using with multisite the plugin may be network activated or used individual sub sites.

== Screenshots ==

== Changelog ==

= 1.5.3 =
* Fixed Gravity Form issues (https://github.com/bmarshall511/wordpress-zero-spam/issues/101)

= 1.5.2 =
* Added IP location service (https://github.com/bmarshall511/wordpress-zero-spam/issues/84)
* Improved pagination (https://github.com/bmarshall511/wordpress-zero-spam/issues/91)
* Made date/times match site's WP time, not servers (https://github.com/bmarshall511/wordpress-zero-spam/issues/89)
* Removed the banner image to boost performance (https://github.com/bmarshall511/wordpress-zero-spam/issues/86)
* Enhancements to the admin JS to boost performance
* Works with Multisite as network activated or per sub site (https://github.com/bmarshall511/wordpress-zero-spam/issues/85)
* Added BuddyPress support (https://github.com/bmarshall511/wordpress-zero-spam/issues/61)

= 1.5.1 =
* Added missing code documentation and fixed typos (https://github.com/bmarshall511/wordpress-zero-spam/issues/64)
* Fixed issue with settings not getting initially saved when the plugin is activated. (https://github.com/bmarshall511/wordpress-zero-spam/issues/69)
* Added ability to auto block spam IPs (https://github.com/bmarshall511/wordpress-zero-spam/issues/71)
* Added paging to spammer log and blocked IPs (https://github.com/bmarshall511/wordpress-zero-spam/issues/60)
* Added additional stats and graphs (https://github.com/bmarshall511/wordpress-zero-spam/issues/75)
* Fixed issue with comment moderators not being able to reply to comments (https://github.com/bmarshall511/wordpress-zero-spam/issues/74)
* Fix issue with DB errors when first activating plugin (https://github.com/bmarshall511/wordpress-zero-spam/issues/80)

= 1.5.0 =
* Switched to using a nonce to validate form submissions that support WordPress Zero Spam
* Added Zero Spam plugin settings page for advanced control
* Fix for for non-logged in users (https://github.com/bmarshall511/wordpress-zero-spam/pull/27, thanks @afragen)
* Added blank index.php files to prevent directory browsing (https://github.com/bmarshall511/wordpress-zero-spam/pull/24, thanks @TangRufus)
* Added uninstall.php (https://github.com/bmarshall511/wordpress-zero-spam/pull/23, thanks @TangRufus)
* Addded support for GitHub Updater plugin (https://github.com/bmarshall511/wordpress-zero-spam/pull/21, thanks @afragen)
* Added support for Contact Form 7 form submissions (https://github.com/bmarshall511/wordpress-zero-spam/pull/26, thanks @leewillis77)
* Added ability to log spam detections
* Fix for warnings cause by default settings not being set before actions run (https://github.com/bmarshall511/wordpress-zero-spam/pull/31, thanks @leewillis77)
* Installed Compass (http://compass-style.org/)
* Added support for Gravity Forms
* Fixed potential issue with sites that use caching plugins
* Fixed minor typos (thnaks @macbookandrew)

= 1.4.0 =
* Added `zero_spam_found_spam_comment` and `zero_spam_found_spam_registration` action hooks (thanks @tangrufus)
* Minor updates to the readme file

= 1.3.1 - 1.3.3 =
* Minor fixes to WP SVN repo

= 1.3.0 =
* Removed Grunt creation of the trunk directory
* Added spam detection script to registration form

= 1.2.1 =
* Fixed some typos in the readme.txt file

= 1.2.0 =
* Removed testing for core function testing
* Fix for adding comments from admin (thanks @afragen)
* Removed unneeded WP svn trunk and tags folders from the git repo (thanks @afragen)

= 1.1.0 =
* Updated theme documentation.
* WordPress generator meta tag removed to help hide WordPress sites from spambots.

= 1.0.0 =
* Initial release.

== Credits ==
* Thanks to [David Walsh](http://davidwalsh.name) [@davidwalshblog](https://twitter.com/davidwalshblog) for the inspiration behind this plugin.

== Contributors ==
* [Ben Marshall](https://github.com/bmarshall511)
* [Andy Fragen](https://github.com/afragen)
* [Tang Rufus](https://github.com/TangRufus)
* [Lee Willis](https://github.com/leewillis77)
* [Andrew Minion](https://github.com/macbookandrew)

