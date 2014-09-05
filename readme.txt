=== WordPress Zero Spam ===
Contributors: bmarshall511, afragen, tangrufus
Donate link: https://www.gittip.com/bmarshall511/
Tags: comments, spam, antispam, anti-spam, comment spam, spambot, spammer, spam free, spam blocker, registration spam
Requires at least: 3.0.0
Tested up to: 4.0.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Zero Spam makes blocking spam comments a cinch. Install, activate and enjoy a spam-free site.

== Description ==

**Why should your users prove that they're humans by filling out captchas? Let bots prove their not bots with the <a href="http://www.benmarshall.me/wordpress-zero-spam-plugin/" target="_blank">WordPress Zero Spam plugin</a>.**

WordPress Zero Spam blocks registration spam and spam in comments automatically without any additional config or setup. Just install, activate and enjoy a spam-free site.

Zero Spam was initially built based on the work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.

Major features in WordPress Zero Spam include:

* **No captcha**, because spam is not users' problem
* **No moderation queues**, because spam is not administrators' problem
* **Blocks spam registrations & comments** with the use of JavaScript
* **Extend the plugin** with action hooks

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

Yes, currently there's two hooks available:

* `zero_spam_found_spam_registration` - Runs after a spam registration is detected
* `zero_spam_found_spam_comment` - Runs after a spam comment is detected

== Screenshots ==

== Changelog ==

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
