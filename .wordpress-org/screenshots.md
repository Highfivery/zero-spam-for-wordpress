# Readme screenshots

The 6.0.0 captures are committed in `.wordpress-org/screenshots/` (`screenshot-1.png` to `screenshot-8.png`, not shipped). Capture them again when a screen changes visibly. They replace `screenshot-1.jpg` to `screenshot-5.jpg` in the SVN `assets/` directory (the old files are JPGs, so copying the PNGs in leaves both: remove them, below), and the captions replace `== Screenshots ==` in `readme.txt`, in the same order.

**How:** `npm run env:start`, 1280 px wide, seeded TEST-NET addresses only (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32) and `@example.com` addresses, so no real person's data appears. Save as `screenshot-N.png`. No price or plan quota may be visible: crop it or use a Basic site if a screen shows one.

| # | Screen | Set-up | Caption |
|---|---|---|---|
| 1 | Overview | Seeded detections, Enhanced Protection on | See at a glance that your site is protected: what was blocked, what is trending and anything that needs you. |
| 2 | Activity › Log, details drawer open | Seeded detections | Understand every block: who was stopped, from where and why, with the block and allow actions right there. |
| 3 | Activity › Reports | 30 days of seeded detections | Spot the patterns in attacks on your site, by day, hour, type and country. |
| 4 | Rules › Blocked IPs | Seeded blocks | Decide who gets in: block addresses and locations for good or for a set time, and keep your own addresses on a no-lockout list. |
| 5 | Protection › Detection, self-test finished | "Test my protection" run | Protect comments, logins and sign-ups with a switch, then prove it works with one test. |
| 6 | Settings › General, dark theme | Theme set to dark | Make it yours: clear settings with recommended values, in light or dark. |
| 7 | Network page (Overview) | Multisite, `npm run env:start:ms` | Run a whole network from one place: shared defaults, locked settings and every site's activity. |
| 8 | Blocked page | Visit as a blocked TEST-NET address | Blocked visitors get a clear page, and a locked-out administrator gets back in with an emailed link. |

**Publish (maintainer, by hand, in the SVN working copy):**

```bash
cd /Users/benmarshall/Documents/Projects/zero-spam
svn rm assets/screenshot-1.jpg assets/screenshot-2.jpg assets/screenshot-3.jpg assets/screenshot-4.jpg assets/screenshot-5.jpg
cp "$PLUGIN"/.wordpress-org/screenshots/screenshot-*.png assets/   # $PLUGIN: a checkout of the released tag
svn add assets/screenshot-*.png
svn propset svn:mime-type image/png assets/screenshot-*.png
svn status assets
svn commit assets -m "Update the plugin screenshots for 6.0"
```
