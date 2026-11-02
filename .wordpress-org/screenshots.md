# Readme screenshots

Capture these **after the 6.0 cutover** (PR 13), when the 6.0 admin is what people get. They replace `screenshot-1.jpg` to `screenshot-5.jpg` in the SVN `assets/` directory, and the captions replace `== Screenshots ==` in `readme.txt`, in the same order.

**How:** `npm run env:start`, 1280 px wide, seeded TEST-NET addresses only (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32) and `@example.com` addresses, so no real person's data appears. Save as `screenshot-N.png`. No price or plan quota may be visible: crop it or use a Basic site if a screen shows one.

| # | Screen | Set-up | Caption |
|---|---|---|---|
| 1 | Overview | Seeded detections, Enhanced Protection on | See at a glance that your site is protected: what was blocked, what is trending and anything that needs you. |
| 2 | Activity › Log, details drawer open | Seeded detections | Understand every block: who was stopped, from where and why, and block or allow them in one click. |
| 3 | Activity › Reports | 30 days of seeded detections | Spot the patterns in attacks on your site, by day, hour, type and country. |
| 4 | Rules › Blocked IPs | Seeded blocks | Decide who gets in: block addresses and locations for good or for a set time, without locking yourself out. |
| 5 | Protection › Detection, self-test finished | "Test my protection" run | Protect comments, logins and sign-ups with a switch, then prove it works with one test. |
| 6 | Settings › General, dark theme | Theme set to dark | Make it yours: clear settings with recommended values, in light or dark. |
| 7 | Network page (Overview) | Multisite, `npm run env:start:ms` | Run a whole network from one place: shared defaults, locked settings and every site's activity. |
| 8 | Blocked page | Visit as a blocked TEST-NET address | Blocked visitors get a clear page, and a locked-out administrator gets back in with an emailed link. |
