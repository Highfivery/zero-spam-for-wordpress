# Readme screenshots

The 6.0.0 captures are committed in `.wordpress-org/screenshots/` (`screenshot-1.png` to `screenshot-9.png`, not shipped). Capture them again when a screen changes visibly. They replace `screenshot-1.jpg` to `screenshot-5.jpg` in the SVN `assets/` directory (the old files are JPGs, so copying the PNGs in leaves both: remove them, below), and the captions replace `== Screenshots ==` in `readme.txt`, in the same order.

**How:** `npm run env:start`, 1280 px wide, seeded TEST-NET addresses only (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32) and `@example.com` addresses, so no real person's data appears. Name the site "Example Site" and the network "Example Network" while capturing (`wp option update blogname`, `wp network meta update 1 site_name`), and put the names back afterwards. Save as `screenshot-N.png`, 1280 × 800. No price or plan quota may be visible: crop it or use a Basic site if a screen shows one.

| # | Screen | Set-up | Caption |
|---|---|---|---|
| 1 | Overview | Seeded detections, Basic (on Enhanced the sidebar shows the plan and the queries left) | Know you're protected at a glance: what was blocked, how it's trending, and anything that needs you. |
| 2 | Protection › Detection, self-test finished | "Test my protection" run | Turn protection on with a switch, then prove it works with one click. |
| 3 | Activity › Log, details drawer open | Seeded detections | Every block explained: who was stopped, from where and why, with Block and Allow right there. |
| 4 | First-run setup, step 1 (protection level) | The mu-plugin's `first-run` route, mode `fresh`, after logging in (it needs the REST nonce) | A short guided setup gets your site protected in about a minute. |
| 5 | Activity › Reports | 30 days of seeded detections | Spot attack patterns by day, hour, type and country. |
| 6 | Rules › Blocked IPs | Seeded blocks | Block addresses or whole locations, for good or for a while, without ever locking yourself out. |
| 7 | Blocked page | Visit as a blocked TEST-NET address | Bots get a clear "blocked" page, and a locked-out administrator gets back in with an emailed link. |
| 8 | Settings › General, dark theme | Theme set to dark | Plain-language settings with recommended values, in light or dark. |
| 9 | Network page (Overview) | Multisite, `npm run env:start:ms` | Run a whole multisite network from one place: shared defaults, locked settings and every site's activity. |

The order leads with the promise (protected at a glance), then how little it takes (a switch, a short setup), the proof (every block explained), safety (no lockout, the rescue link) and depth (settings, networks).

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
