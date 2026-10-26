# AGENTS.md — Zero Spam for WordPress

Instructions for coding agents (Claude Code and any other agent) and a
quick reference for humans. `CLAUDE.md` imports this file. Keep it current:
when a convention, tool or process in this repository changes, change it here
in the same PR.

## 1. What this is

- The free **Zero Spam for WordPress** plugin, published on WordPress.org as
  [`zero-spam`](https://wordpress.org/plugins/zero-spam/). GPL-2.0-or-later.
- Blocks spam and malicious submissions on comments, registration, login and
  the forms of integrated plugins, with optional IP reputation from the
  [Zero Spam](https://www.zerospam.org) service, Stop Forum Spam and Project
  Honeypot, geolocation providers, and IP / location block lists.
- **Targets:** PHP 8.2+, WordPress 6.9+ (see `Requires at least` and
  `Tested up to` in `readme.txt`). Text domain `zero-spam`. Plugin slug and
  directory `zero-spam`; main file `wordpress-zero-spam.php`.
- **Not in this repo:** the zerospam.org website (`Highfivery/zerospam`) and
  the service plugin that answers the API (`Highfivery/zero-spam-store`).
  Do not change them from here. The client code that talks to the service is
  `modules/class-zerospam.php` (`v6/report/`, `v2/get-license`, `v3/query`).
- Many users are not technical. Admin UI must be visual and understandable
  without reading docs, with detail available for technical users.

## 2. Repository map

| Path | Purpose |
|---|---|
| `wordpress-zero-spam.php` | Bootstrap: constants, PHP/WP version gates, loads `includes/class-plugin.php`, activation hook (stores activation time only). |
| `includes/class-plugin.php` | `Plugin::instance()` runs at include time: registers the autoloader, instantiates every module, registers REST routes. |
| `includes/class-autoloader.php` | Custom SPL autoloader for `ZeroSpam\`: lowercases the class name, strips underscores, prefixes `class-`. `ZeroSpam\Modules\Zero_Spam` → `modules/class-zerospam.php`. New classes must follow this file-naming rule or they will not load. |
| `core/` | `Access` (block checks on `template_redirect`, `login_init`, XML-RPC; rescue mode), `Settings` (registry built from the `zerospam_settings` filter), `Utilities`, `User::get_ip()`, `Rescue_Link` (emailed rescue link), `core/front/` (`Front\Block_Page`: the blocked, rescue, expired-link and comment-blocked pages, sent through `wp_die()` with its own document, inlined `assets/dist/blocked.css`, no JavaScript), `core/admin/` (settings page, dashboard, `WP_List_Table`s; `Dashboard_Widget`, the 6.0 site and network dashboard widgets rendered in PHP from `includes/templates/dashboard-widget.php` with a server-drawn `Sparkline`; `Icons::svg()` prints one symbol of `assets/dist/sprite.svg` as inline SVG for PHP-rendered screens; and `Admin_App`: the 6.0 admin's menu, pages, shell and assets behind the flag). |
| `core/admin/class-adminapp.php`, `includes/templates/admin-app-shell.php` | The 6.0 admin, only while `Utilities::admin_v6()` is true (the `ZEROSPAM_ADMIN_V6` constant wins, otherwise the `zerospam_admin_v6` filter; off by default until the cutover): top-level "Zero Spam" menu at position 26 (below Comments) with six `manage_options` pages (`zero-spam`, `zero-spam-activity`, `-rules`, `-protection`, `-settings`, `-help`) and the network page `zero-spam-network` (`manage_network_options`). Each page prints the shell (icon sprite, `#zerospam-app.zs-app[data-area]` with a skeleton and a `<noscript>` message), enqueues `assets/dist/admin.js`/`.css` with the `.asset.php` dependencies and version, `wp_set_script_translations( 'zerospam-admin', 'zero-spam' )` and the fonts, and preloads its first REST responses (`Admin_App::SHELL_PRELOADS`, `OVERVIEW_PRELOADS`, the news) through `rest_preload_api_request` and `createPreloadingMiddleware`, so the Overview makes no request of its own on load; the Activity page preloads `Admin_App::activity_preloads()` (the heading count, then the log's first page and facets, or with `&tab=reports` the 30-day series and breakdown, plus an `&item=` entry), and those paths must stay identical to `activity/query.js` and `reports-data.js` (the middleware matches query arguments in any order, so only the set matters). The theme and motion attributes come from `Utilities::display_prefs()` (the one place that resolves `ui/prefs`: "auto" follows the device on the plugin's screens and renders light elsewhere). The legacy screens stay registered until the cutover. |
| `includes/class-db.php` | Table definitions, `dbDelta` upgrades keyed on `zerospam_db_version`, `DB::log()`, `DB::query()`, `DB::blocked()`. |
| `includes/class-migrations.php` | Run-once migrations tracked in `zerospam_completed_migrations`. Add one here for any data/option shape change. |
| `includes/class-networksettings*.php`, `includes/admin/`, `includes/cli/`, `includes/rest/` | Multisite network settings, templates, stats, audit, notifications, REST controllers (`zero-spam/v1`), WP-CLI (`wp zerospam …`). |
| `includes/templates/` | Plain PHP admin templates; `front/` holds the copy of the Block_Page pages (`blocked`, `rescue-sent`, `rescue-confirm`, `rescue-expired`, `form-error`, `comment-blocked`, overridable with the `zerospam_block_page_template` filter) and `front/parts/rescue-form.php`. Escape everything on output. |
| `includes/class-email.php` | `Includes\Email`: the one email template (HTML tables ≤ 600px, inline styles, the mark as coloured cells, no images) with a plain-text AltBody added by a one-shot `phpmailer_init` callback. The rescue link, API usage alerts and network notifications send through it; messages are data (`heading`, `blocks`), never markup. |
| `modules/` | One directory or file per detection technique or integrated plugin. Third-party modules are only instantiated when that plugin is active (`includes/class-plugin.php`). |
| `assets/` | Legacy (5.x) admin: hand-written CSS/JS (jQuery) in `assets/css/` and `assets/js/`, `assets/src/scss/admin.scss` compiled to `assets/css/admin.css` by hand (no build step for these), vendored Chart.js and jvectormap, flag images, `blacklist.txt`, `disposable-email-domains.txt`. Enqueue the plugin's own CSS/JS with `Utilities::asset_version( 'assets/…' )` as the version (file time with `SCRIPT_DEBUG`, else `ZEROSPAM_VERSION`); pinned vendored libraries keep their own version. |
| `assets/src/`, `assets/dist/`, `webpack.config.js` | 6.0 admin front end, built with `@wordpress/scripts` (section 5, Front-end build). Sources in `assets/src/` (never shipped): entries `admin/index.js`, `network/index.js`, `widget-refresh/index.js` (the dashboard widget's Refresh: plain JS, no WordPress dependencies; `model.js` mirrors the PHP sparkline and figures, and both are tested with the same values) and `blocked/index.css` (CSS only); the admin app in `admin/` (`index.js` mounts `app/app.js` on `#zerospam-app` by `data-area`; `app/` the shell, the in-app sidebar and its lower block, the top bar and level chip, the Compare levels drawer, the mark controller and the primitives in `app/primitives/` (Button, Badge, Chip, Switch, Tile, Drawer, toasts with Undo, live regions, unsaved-changes bar); `data/` the REST client and bootstrap (`api.js`, paths exactly as preloaded), formatting, `?tab=` routing with the History API, theme resolution, and the live feed's scheduler (`poll.js`, `use-live-feed.js`); `overview/` the Overview and its pure chart mapper; `activity/` the Activity area: `query.js` (the log's view ⇄ REST paths and the address, the paths Admin_App preloads), `reports-data.js`, `details-data.js` and `undo.js` (pure, unit tested), `log.js` (filters, bulk bar, table from 1,200px and cards below, pages, the "N new" bar), `details.js` (the details drawer), `reports.js` and `column-chart.js` (keyboard-readable charts with a table); `app/block-form.js` (with `block-data.js`) is the shared Block IP / Edit block drawer; areas not built yet render a placeholder inside the shell), React through `@wordpress/element` with the plugin's own components (no `@wordpress/components`); shared styles in `admin/styles/` (`primitives.css`, `shell.css`, `overview.css`, `areas.css` for the app, every selector under a `zs-` class; `tokens.css` design tokens scoped to `.zs-app` and `.zs-widget` with light, `data-zs-theme="dark"` and `"auto"`, plus `.zs-dark-block` for the hero and Enhanced Protection block; `base.css` reset, typography and reduced motion for `.zs-app`, `.zs-blocked`, `.zs-widget`; `logo.css` mark animation); `blocked/tokens.css` (the blocked pages' own minimal token subset, because `blocked.css` is inlined) and `blocked/page.css` (the pages' own reset and styles; it does not import `base.css`, to stay inside the 8 KB raw budget); `admin/icons/sprite.svg` (marks `zs-mark`, `zs-mark-flat`, `zs-mark-mono` and `zs-i-*` UI icons; no ids inside symbols, bricks are `.zs-b[data-i]`); Vitest tests in `**/test/*.test.js`. Output in `assets/dist/` (committed and shipped): `{entry}.js`, `{entry}.css`, `{entry}-rtl.css`, `{entry}.asset.php` (dependencies and version for enqueueing) and `sprite.svg`. Never edit `assets/dist/` by hand. |
| `assets/fonts/` | Self-hosted Plus Jakarta Sans and JetBrains Mono (variable woff2, Latin and Latin Extended), `fonts.css` (`@font-face`, shipped as is) and each family's `OFL.txt`. Sources, versions and checksums in `assets/fonts/FONTS.md`. |
| `assets/src/admin/rules/`, `assets/src/admin/data/validate.js` | The 6.0 Rules area (`?tab=` `ips`, `locations`, `allowlist`, `email`, `words`, `no-lockout`): `rules.js` (heading, tabs with counts from the bootstrap's `rules.counts` = `Rules_Controller::counts()`, and the one unsaved-changes bar for every list), `blocks.js` (Blocked IPs and locations: table from 1,200px, cards below, bulk bar, the shared `BlockForm` drawer with `kind="ip"` or `"location"`), `list-editor.js` (IP Allowlist, email domains, allowed words: add field, paged list, network lock and network-list states), `no-lockout.js`, and the pure `query.js` (tabs and REST paths; `firstPath()` must equal what `Admin_App::rules_preloads()` preloads, `test/query.test.js` reads the PHP to check), `list-diff.js` (unsaved changes as `{ add, remove }`, sent to `PUT rules/lists/{list}`, so a 3,000-entry list is never loaded whole), `actions.js` (removals with Undo). `data/validate.js` mirrors the server's checks (canonical IPs as `User::normalize_ip()` writes them, no CIDR, locations, list entries); use it in every form that takes an address. Browser tests: `tests/e2e/specs/rules.spec.ts` and the `v6-rules-*` screens. |
| `assets/img/zero-spam-mark.svg`, `assets/img/zero-spam-menu-icon.svg` | The 6.0 mark (treatment B "Live core") on a 256 × 256 artboard (gradient ids prefixed `zs-mark-`), and the 20 × 20 one-colour admin menu icon with explicit `fill="#a7aaad"` attributes, which WordPress's svg-painter rewrites per colour scheme (`currentColor` does not work in a base64 data URI). Not used yet; `assets/img/icon.svg` is still the 5.x logo the legacy screens show. |
| `CHANGELOG.md` | Full release history, newest first (`readme.txt` keeps only the current minor series). Not shipped: listed in `.distignore`. |
| `uninstall.php` | Removes options, tables, transients and cron events. Keep it in sync when adding any of those. |
| `bin/`, `.githooks/`, `tests/`, `phpstan*.neon*`, `phpcs*.xml.dist`, `phpunit*.xml.dist` | Quality gate and test suite (section 5). `bin/test.sh` and `bin/test-matrix.sh` run PHPUnit; `tests/phpunit/` holds the suites (`unit/`, `integration/`, `multisite/`), the harness (`includes/`) and the pinned versions (`versions.env`); `tests/phpstan/` holds the PHPStan bootstrap and stubs; `tests/e2e/` holds the Playwright suite (with `package.json`, `.nvmrc` and `.wp-env*.json` at the root). Never shipped: see `.distignore`. |

### How a module works
Constructor hooks `init`; adds its sections, settings and types through the
`zerospam_setting_sections`, `zerospam_settings`, `zerospam_types` and
`zerospam_failed_types` filters; when its own setting is `'enabled'` and
`Access::process()` is true it hooks the relevant form (`zerospam_preprocess_*`
filters, `zerospam_access_checks`), logs with `DB::log()` and fires
`zerospam_share_detection`. Copy an existing small module (for example
`modules/wpforms/`) rather than inventing a new shape.

### Settings
- Core settings live in option `zero-spam-settings`; each module in
  `zero-spam-{module}`. `blocked_email_domains` has its own non-autoloaded
  option. Read through `Settings::get_settings( $key )`.
- `Settings::get_settings()` rebuilds the whole registry on every call and is
  expensive. Do not call it in loops or hot paths; read once and reuse.
- Since 6.0 (`feat/rest-foundation`) `Core\Settings_Schema` builds the
  registry once per request and site and describes every setting (type,
  choices, default, area/group, locked, source, secrets masked); prefer
  `Settings_Schema::resolve()` / `registry()` in new code. Optional registry
  keys: `default`, `secret`, `lockable`, `area`, `group`, `depends_on`,
  `order`. `Core\License_Status` is the one license status (header, widget,
  notices, REST); never ask the license API from elsewhere.
- REST controllers extend `Includes\Rest\Base_Controller` (permission
  helpers, `rest_invalid_param` with `data.params`, `per_page` ≤ 100, paging
  headers, `_fields`). Secrets are write-only over REST.
- Multisite precedence, as implemented in
  `includes/class-networksettings.php`: **locked network value → site
  override → network default → plugin default.** Network values live in the
  site option `zerospam_network_settings` (`value` + `locked` per key).
  Capabilities: `manage_network_options` for network pages and REST,
  `manage_options` for site pages. Never leak one site's data to another.

### Data
- Per-site tables `{prefix}wpzerospam_log`, `{prefix}wpzerospam_blocked`;
  network-wide (`base_prefix`) `wpzerospam_api_usage`,
  `wpzerospam_stats_daily`, `wpzerospam_stats_monthly`,
  `wpzerospam_network_templates`, `wpzerospam_network_audit` (see
  `DB::$tables`). `wpzerospam_blacklist` is listed there; 4.x created it and
  current versions do not, but uninstall still drops it. Schema changes go through `DB::update()` and a
  `DB_VERSION` bump.
- Logged submission data is sanitized on write (keys and values) and must be
  escaped on read: it has carried a stored XSS before (CVE-2026-96752).
  The REST routes (`activity/{id}`) return it as stored; the client escapes
  (JSX text only, never `dangerouslySetInnerHTML`). Data printed into an
  inline script (the app's bootstrap and preloaded answers) is encoded with
  `Admin_App::JSON_FLAGS` (`JSON_HEX_TAG | JSON_HEX_AMP`), so logged text
  such as `</script>` or `<!--` cannot end or change the script.
- The dashboard widgets (6.0, `feat/dashboard-widget`) read the site's
  figures from `Stats_Controller::timeseries_data()` (the same cache as
  `GET stats/timeseries`) and the network's from every site's log table (one
  `UNION ALL`, cached five minutes in `zerospam_dashboard_data_network`).
  Refresh uses the REST routes; the 5.x AJAX action
  `zerospam_refresh_dashboard` stays registered but is deprecated
  (`_doing_it_wrong()` once per request). Links into the activity log go
  through `Utilities::activity_url()`, which follows the 6.0 admin flag
  (`Utilities::admin_v6()`: the `ZEROSPAM_ADMIN_V6` constant, else the
  `zerospam_admin_v6` filter, off by default); other admin links go through
  `Utilities::admin_link( $view )` (activity, api-monitoring, license,
  network-activity, network-license), which maps legacy and 6.0 pages. The
  `data-zs-theme` and `data-zs-motion` of a Zero Spam root come from
  `Utilities::display_prefs( $plugin_screen )` (defined by the 6.0 shell):
  an explicit light or dark ui/prefs theme everywhere; `auto` follows the OS
  on the plugin's own screens and is light elsewhere in wp-admin (the
  dashboard widget calls `display_prefs( false )`). The activity route's
  `date_gmt` carries `+00:00`; clients read an offset-less time as UTC.
- 6.0 data routes (`feat/rest-data`): `stats/*`, `activity*`, `undo/{token}`,
  `rules/*`, `protection/modules`, `settings/actions|export|import`,
  `self-test`, `news`. Log statistics are cached in the transients
  `zerospam_stats_summary`, `zerospam_stats_timeseries` and
  `zerospam_protection_detections`, tagged with `MAX(log_id)` (a new
  detection refreshes them); code that deletes or restores log rows calls
  `Stats_Controller::flush_cache()`. REST deletions keep the rows for two
  minutes in `zerospam_undo_{token}` (`Includes\Undo_Store`, at most 500 rows,
  bound to the user and the site, single use). The live feed (`activity?since_id=`)
  must stay at three queries or fewer and never build the settings registry.
  `activity` also takes `no_count` (no COUNT and no paging headers, for
  lists that are not paged: the Overview's latest blocks and the widget's
  Refresh), `days` (last N days of the site's calendar, as
  `stats/timeseries` counts them), `detections_only` and `network_only`;
  rows carry `network` (`Stats_Controller::is_network_detection()`, the
  row-level twin of `network_only_sql()`), facets carry `network_only` and
  `max_id` (where a filtered live feed starts), and `activity/{id}` carries
  `allowed` (`Rules_Controller::is_allowed_ip()`, no settings registry).
  The self-test runs real checks inside `Access::intercept_termination()`,
  which makes `terminate_execution()` and every `Front\Block_Page` page throw
  (caught) instead of sending a page or headers; use it for any dry run.
  Alert webhooks go through `wp_safe_remote_post()` (private addresses are
  refused; the `http_request_host_is_external` filter allows a trusted host).
  Read settings in per-request paths with `Settings_Schema::value()` (one
  registry build per request), not `Settings::get_settings()`.
  `Includes\News_Feed` is the only code that reads the zerospam.org feed
  (site transients `zerospam_news` and `zerospam_news_backoff`); every outbound
  service is listed in the readme's External services section: keep it
  current when adding one.

## 3. Workflow

- **Branches:** `develop` is the default and integration branch. `main` always
  equals the latest WordPress.org release. Branch from `develop` as
  `fix/…`, `feature/…`, `security/…`, `perf/…`, `chore/…`, `docs/…` and open
  the PR against `develop`. Both branches are protected: PR only, no force
  pushes. Release = PR `develop` → `main` with a merge commit, tag on `main`,
  then fast-forward `develop`. Full steps: `CONTRIBUTING.md`.
- **No hosted CI.** GitHub Actions is not used for this project and must not
  be added. Checks run locally (section 5) and their output goes in the PR.
- **Commits:** `type(scope): what and why` as in the existing history
  (`fix(login): …`, `security(davidwalsh): …`, `feat(woocommerce): …`,
  `perf(dashboard-widget): …`, `docs: …`, `chore: …`).
- **Changelog:** new entries go in `readme.txt`. Every user-visible or
  behavioral change adds a bullet under the next `= vX.Y.Z =` heading in the
  existing `**type(scope):** description` style, linking issues or support
  topics. Fill the PR template's "Changelog Entry" too. `CHANGELOG.md` holds
  the full history (Markdown: `## vX.Y.Z`, `- **type(scope):** text`).
  `readme.txt` carries the user-facing summary (plain language, benefit
  first, no hook, class or migration detail); `CHANGELOG.md` the full entry.
  Release PRs write both. `readme.txt` keeps only the current minor series (5.7.x),
  because WordPress.org truncates the readme changelog at 5,000 words and
  `composer version:check` fails above 4,500. When a new minor series starts
  (for example 5.8.0), move the previous series out of `readme.txt` into
  `CHANGELOG.md` (each release's full entry is already there; confirm
  before deleting) and keep the "full changelog on GitHub" line below
  the last readme entry.
- **Versions:** `Version:` header, `ZEROSPAM_VERSION` and `Stable tag` must
  agree and never go backwards (`composer version:check`). Bump
  `Tested up to` only after testing on that WordPress release.
- **Docs:** when behavior changes, update `readme.txt` (description, FAQ) and
  the in-admin docs in `includes/templates/docs/`. The 6.0 Help screen shows
  them through `Core\Admin\Documentation` (`zero-spam/v1/ui/help/docs`): keep
  the accordion markup (`.zerospam-docs-accordion` with a toggle button and a
  content block), which becomes `<details>`; headings are moved below the
  screen's own and the HTML always goes through `wp_kses_post()`. A new guide
  is added to `Documentation::defaults()` (the classic Documentation tab keeps
  its own list until the cutover). Network settings
  extensibility is documented in `NETWORK_SETTINGS_DEVELOPER_GUIDE.md`.
- **Public contract:** option keys, table names, hook names and REST routes are
  public API for site owners and developers. Changing or removing one needs a
  migration, a changelog entry and, ideally, a deprecation period.

## 4. Engineering standards (non-negotiable)

- **WordPress Coding Standards** (`phpcs.xml.dist`, WordPress-Core/Extra/Docs)
  and 10up engineering practices. Every file you touch is left fully clean,
  not only the lines you changed. A `phpcs:ignore` needs a justification
  comment and is only for cases WPCS cannot understand.
- **Security:** capability check + nonce on every admin action and AJAX
  handler, `permission_callback` on every REST route, `wp_unslash()` then
  sanitize on input, escape on output (`esc_html__`, `esc_attr`, `wp_kses`),
  `$wpdb->prepare()` for every query, `wp_safe_redirect()`, `wp_safe_remote_*`
  for URLs an admin can configure, `hash_equals()` for secrets, HttpOnly
  cookies. Never trust client-supplied flags for scope (`is_network`, etc.).
  Never trust proxy or geo headers unless the request comes from a configured
  trusted proxy.
- **Performance:** this plugin runs on every front-end request. No new work on
  hot hooks without need, no repeated option reads, cache per request (static)
  and across requests (transients / object cache) with sensible TTLs, bound
  every query (`LIMIT`), never load whole tables into memory, no external CDN
  assets in admin (bundle them under `assets/`).
- **PHP:** `declare( strict_types=1 )` in new files, typed parameters and
  returns, no dynamic properties, guard superglobals with `isset`, PHP 8.2
  through 8.4 compatible (`composer compat`).
- **Accessibility (WCAG 2.2 AA minimum, aim for AAA):** semantic markup, labels
  for every control, visible focus, keyboard operability, 4.5:1 contrast,
  `aria-live` for async updates, no information conveyed by color alone,
  respect `prefers-reduced-motion`.
- **UX:** plain-language setting titles and descriptions with a recommended
  value, progressive disclosure for advanced options, clear empty/error
  states, actionable notices, consistent with the existing admin design.
- **i18n:** every string through the `zero-spam` text domain, no variables
  inside `__()`, translator comments for placeholders.
- **Architecture:** match existing patterns (namespaces, autoloader naming,
  hook strategy, option strategy). Reuse or refactor existing utilities
  instead of duplicating. If there is no established pattern for what you need,
  stop and ask. Large, well-tested refactors are welcome; clever hacks are not.
- **Multisite:** every feature works on single site and on subdirectory and
  subdomain networks, honors locked network values, and is tested on both
  Local sites (section 6).

## 5. Quality gate and definition of done

```bash
composer install     # installs tooling and points git at .githooks/
npm ci               # Node 22 (nvm use): the front-end build, its linters and tests, and the e2e suite
composer check       # built assets and budgets (check:dist), version check, WPCS on changed files
                     # and tests, PHP 8.2+ compat, PHPStan, PHPUnit
composer check:all   # check:dist, WPCS on every file and the tests, compat, PHPStan, the PHPUnit
                     # matrix (red until the WPCS backlog is cleared)
composer lint:fix    # apply the WPCS fixes phpcbf can make
```

- `.githooks/pre-push` runs the same checks as `composer check` (step 0/6 is
  `bin/check-dist.sh`, PHPUnit is step 6/6, `bin/test.sh --mode=all`) and
  refuses the push on failure.
  `SKIP_QUALITY_GATE=1` is for a genuine emergency only and must be mentioned
  in the PR.
  Pushes that only delete branches or only push tags skip the gate.
- `phpstan-baseline.neon` holds pre-existing findings. Fix new findings; never
  regenerate the baseline to hide them. Shrink it when you touch a listed file.
- **Done means proven:** the PR description contains the `composer check`
  summary (including the PHPUnit result), the e2e `summary.md` (Playwright
  section below) with the targets it ran on, the manual smoke-test steps and
  results on both Local sites (single site and multisite), screenshots or
  recordings for any UI change, the `readme.txt` changelog entry, and a note
  of anything not verified and why. Do not claim something works without
  having run it.
- Do not create markdown process files (`NOTES.md`, `PLAN.md`, "what I did"
  files). Plans and findings go in the PR or in the conversation.

### Front-end build (`@wordpress/scripts`)

```bash
nvm use                  # Node 22 (.nvmrc; engines ^22)
npm ci                   # with install scripts (the e2e packages need them)
npx playwright install chromium   # the render unit test and the e2e suite use it
npm run build            # production build of assets/src into assets/dist (commit the result)
npm start                # development build with source maps, rebuilt on change; never commit it
npm run lint:js          # ESLint (@wordpress/eslint-plugin, eslint.config.cjs; text domain zero-spam)
npm run lint:css         # stylelint (@wordpress/stylelint-config, .stylelintrc.json)
npm run test:js          # Vitest unit tests (wp-scripts test-unit-js; vitest.config.mjs)
npm run check:dist       # bin/check-dist.sh (also composer check:dist and pre-push step 0)
npm run check:budget     # budgets of the committed assets/dist only, no build
bin/check-dist.sh --twice   # build twice and require byte-identical output
```

- `webpack.config.js` extends the `@wordpress/scripts` default: four entries,
  output in `assets/dist/` (`ZEROSPAM_DIST_DIR` overrides it), no split chunks
  (WordPress loads translations per script handle, so a lazily loaded chunk
  would never be translated; `LimitChunkCountPlugin` folds any `import()`
  back in), no source maps in production, no JavaScript stub for the CSS-only
  `blocked` entry, and `sprite.svg` copied in. Enqueue a built script with the
  `dependencies` and `version` from its `.asset.php` and call
  `wp_set_script_translations()` for it. `assets/dist/` is excluded from WPCS
  (generated); `.distignore` keeps `assets/src/` and the tool configs out of
  the release.
- **`bin/check-dist.sh`** runs when `assets/src/**`, `assets/dist/**`,
  `assets/fonts/**`, `package*.json`, `webpack.config.js`, the lint and test
  configs, `.nvmrc` or the script itself changed against `origin/develop`
  (`--force` always). It needs Node 22 and loads nvm from `$NVM_DIR`
  (default `~/.nvm`) when the `node` on `PATH` is another version, because
  git hooks do not read shell profiles; refuses to run when `node_modules` is
  missing or older than `package-lock.json` (run `npm ci` yourself; it never
  installs, because an install without scripts would break the e2e
  packages); runs `lint:js`, `lint:css` and `test:js`; builds into a
  temporary directory; refuses the push when that build differs from
  `assets/dist/` in any byte; checks that the admin bundle kept its
  `zero-spam` strings and `translators:` comments; and enforces the budgets
  (KB = 1,024 bytes): gzip -9 admin JS 110 (temporarily, until the
  per-area entry split; it was 75), admin CSS 25, network JS 40,
  network CSS 25, widget JS 6 and CSS 6; raw `blocked.css` and
  `blocked-rtl.css` 8 each (inlined into every blocked response); fonts
  (woff2 as stored) 110. `sprite.svg` is reported without a budget.
- **Reproducible builds:** the same sources must give byte-identical output,
  on any machine and from any checkout path, or the stale-dist check could
  never pass. `--twice` proves it (two builds, `diff -r`, SHA-256 per file).
  Keep it that way: no timestamps, absolute paths, random ids or
  environment-dependent values in the build, and pin build dependencies to
  exact versions (`package-lock.json` is committed; `npm ci` only).
- **Unit tests:** `@wordpress/scripts` 36 runs Vitest 5 for `test-unit-js`
  (Jest moved to `test-unit-jest` without WordPress defaults). Tests live next
  to the sources in `test/` directories as `*.test.js`, import `describe`,
  `it` and `expect` from `vitest`, and run in Node.
  `admin/test/tokens.test.js` resolves tokens as the browser does (a `var()`
  in a custom property is resolved on the element that declares it) and fails
  when a text colour drops below 4.5:1 or a UI colour (controls, focus,
  status, sparkline, chart series) below 3:1 against its surfaces in either
  theme or inside a dark block (including every colour of the hero
  gradient), when the `dark` and `auto` blocks differ, when the blocked
  subset disagrees with the admin tokens or misses one `base.css` uses, or
  when a selector can reach outside Zero Spam markup.
  `admin/test/render.test.js` renders in Playwright's Chromium: `<use
  href="#zs-mark">` must match an inline copy at 32 and 256 px (at most 1% of
  pixels off), the focus ring and eyebrow inside `.zs-dark-block` must be
  peach, and a root's `data-zs-theme` must win over an ancestor's.
  `admin/test/icons.test.js` checks the sprite (no ids in symbols, animation
  layers hidden by attributes) and the exported marks.
  `vitest.config.mjs` transforms the admin sources' JSX (they are `.js`
  files), so a test can render a component to markup with
  `react-dom/server`'s `renderToStaticMarkup()` after putting the answers
  it reads in `window.zeroSpamPreload` (`rules/test/render.test.js`); there
  is no DOM, so effects and portals do not run.
- **Design tokens** are the only source of colour, type, radius, spacing,
  motion and layer values for 6.0 screens: `var(--zs-…)`, never a literal.
  Everything is scoped (`.zs-app`, `.zs-blocked`, `.zs-widget`, or a `zs-`
  class); nothing styles wp-admin outside those roots. Put `data-zs-theme` on
  the root (it wins over an ancestor's). Alias tokens (`--zs-eyebrow`,
  `--zs-sx-label`) resolve where they are declared, so a surface that
  redefines the colour underneath must set them again, as `.zs-dark-block`
  does; compose values from themeable tokens where they are used (the focus
  ring is `outline: var(--zs-focus-width) solid var(--zs-focus)`), never in
  another token. Overlay layers (`--zs-z-scrim` … `--zs-z-skip`, 100062–100100)
  sit above the admin bar (99999) and below the media modal (159900+). Motion
  animates `transform` and `opacity` only and stops under
  `prefers-reduced-motion` or `data-zs-motion="reduce"`. Elements a `<use>`
  instance must hide get attributes (`opacity="0"`), because page CSS cannot
  reach inside it.

### PHPUnit

```bash
composer test            # single site + multisite, latest WordPress, the php on PATH
composer test:unit       # one suite; also test:integration and test:multisite
composer test:wp         # PHP 8.2/8.4 x WordPress min/latest x single/multisite,
                         # then checks readme.txt "Tested up to"/"Requires at least"
composer test:defects    # only the @group known-defect tests (they fail on purpose)
composer test:coverage   # HTML report in build/coverage/ (needs pcov: pecl install pcov)
composer lint:tests      # WPCS on tests/phpunit (phpcs.tests.xml.dist)
bin/test.sh --php=8.2 --wp=min --mode=multisite -- --filter Test_Access
```

- **Every change comes with tests**; a bug fix starts with a failing test.
  Tests extend `ZeroSpam\Tests\TestCase` (`tests/phpunit/includes/TestCase.php`),
  live in `unit/`, `integration/` or `multisite/` as `test-*.php` with
  `Test_*` classes, and use its helpers (`set_setting()`,
  `set_network_setting()`, `set_request()`, `insert_log_rows()`, `block()`,
  `mock_http()`, `capture_queries()`, `expect_redirect()`, `as_role()`,
  `admin_screen()`, `create_site_with_tables()`, `rest()`) instead of ad-hoc
  set-up.
  Groups: `ms-required` (network only), `ms-excluded` (single site only),
  `known-defect`.
- **Known-defect policy:** a confirmed bug that is not fixed yet gets a test
  that asserts the correct behaviour, tagged `@group known-defect`. Those tests
  are excluded from `composer test` and the hook, run with
  `composer test:defects`, and must fail for the documented reason (not a
  harness error). The PR that fixes the bug removes the tag so the test joins
  the gate. Never tag a test `known-defect` to get a red test past the gate.
- **Database safety:** the suite installs WordPress into
  `zerospam_tests_<checkout-id>` (single) and `zerospam_tests_<checkout-id>_ms`
  (multisite) on the Homebrew MySQL, where the id is a hash of the checkout
  path so parallel worktrees never share a database or plugin link; never a
  Local site database. The bootstrap and `tests/phpunit/wp-tests-config.php`
  refuse any name that does not match `^zerospam_tests(_\w+)?\z`, and drop
  and recreate the database on every run, because the WordPress installer
  drops tables. Do not weaken that check. Choose the install type with
  `--mode`; each PHPUnit configuration declares the type it runs
  (`ZEROSPAM_TESTS_EXPECT_MULTISITE`) and the bootstrap refuses a mismatch.
- **Tables:** the plugin tables are created for real while WordPress loads.
  Inside a test, WordPress rewrites `CREATE/DROP TABLE` to `TEMPORARY` tables,
  which `SHOW TABLES` (and so `DB::log()`/`DB::query()`) cannot see. Never call
  `DB::update()` in a test; assert `DROP TABLE` statements with
  `capture_queries()`; create extra network sites with
  `create_site_with_tables()` from `wpSetUpBeforeClass()`.
- **No network:** every WordPress HTTP API request is answered by
  `tests/phpunit/includes/HttpMock.php`; an unmocked request fails the test
  (`mock_http()` to answer one), including requests made while WordPress
  loads or in `wpSetUpBeforeClass()`. DNS (`checkdnsrr`, `dns_get_record`),
  `setcookie()`, `headers_sent()`, `header()` and `header_remove()` are stubbed
  in the plugin namespaces that call them (`header()` lines land in
  `Stubs::$headers`, for the Block_Page headers)
  (`tests/phpunit/includes/stubs/`); add a stub when code in another namespace
  starts calling a network or header function.
- **Versions:** `tests/phpunit/versions.env` pins the WordPress versions
  tested (`WP_LATEST`, `WP_MIN`), their checksums, the test library versions
  and `PHP_MATRIX`. For a new WordPress release: update the pins and
  `wp-phpunit/wp-phpunit` in `composer.json`, run `composer test:wp`, then
  bump `Tested up to`.

### Playwright (e2e, accessibility, visual)

Browser tests live in `tests/e2e/` (specs, page objects, the test mu-plugin,
the runner in `tests/e2e/bin/`). They are **not** part of `composer check` or
the pre-push hook (they need Docker or the Local sites and take minutes);
they are required before a PR is opened and their summary goes in the PR.

```bash
nvm use                          # Node 22 (.nvmrc); the runner refuses older Node
npm ci && npx playwright install chromium   # or: composer e2e:install
npm run env:start                # wp-env single site, http://localhost:8900
npm run env:start:ms             # wp-env subdirectory network, http://localhost:8910
npm run test:e2e:wp-env          # everything on both wp-env sites (latest WordPress)
npm run test:e2e:wp-env:min      # the same on the minimum WordPress, then back to latest
npm run test:e2e:matrix          # both wp-env sites on latest, then on the minimum, then back
npm run test:e2e                 # both Local sites (after npm run e2e:setup:local, section 6)
npm run test:a11y                # accessibility only; test:visual / test:visual:update for baselines
npm run test:e2e:report          # print the summary of the last run again
npm run env:stop                 # stop both wp-env sites
```

- Composer delegates for convenience: `composer e2e:install`, `test:e2e`,
  `test:e2e:wp-env`, `test:e2e:wp-env:min`, `test:e2e:local`,
  `test:e2e:network`, `test:a11y`, `test:visual`.
- **Projects:** `setup` (logs every role in through the real `wp-login.php`
  form), `functional`, `a11y`, `visual`, `destructive` (uninstall, after the
  rest). One worker per target; the two wp-env targets run in parallel.
  The `@single-only` and `@multisite` tags pick the targets. Ctrl-C stops
  the run but still switches wp-env back to the latest WordPress after a
  min run (a second Ctrl-C exits at once).
- **Output:** `artifacts/e2e/<timestamp>-<sha>/<target>/` (HTML report,
  traces, `summary.json`, `summary.md`, `gallery/index.html`) and
  `artifacts/e2e/latest`. The last line printed is always
  `E2E RESULT: PASS|FAIL …`; the exit code matches. **Done** includes pasting
  the run's combined `summary.md` into the PR (targets, counts, known defects
  still reproducing, accessibility per screen, blocked external requests).
- **Test-only mu-plugin** (`tests/e2e/mu-plugins/zero-spam-e2e.php`, linted
  by `composer lint:tests`): inert unless `ZEROSPAM_E2E_TOKEN` is defined.
  While its stubs are active it blocks every outgoing HTTP request (canned
  answers for Zero Spam's services and WordPress.org update checks, "no
  updates" for core, plugins and themes); see section 6 for when that is.
  For requests carrying the token it uses the `X-ZeroSpam-E2E-IP` header as
  the visitor IP, records the plugin's PHP notices, warnings, deprecations,
  fatal errors and `_doing_it_wrong()` calls (they fail the test), and offers
  REST routes under `zero-spam-e2e/v1` for resets, seeding and inspection.
  **Only TEST-NET addresses** (192.0.2.0/24, 198.51.100.0/24,
  203.0.113.0/24, 2001:db8::/32) are accepted as the fake IP, by `/block`
  and by `/seed-log`; anything else is refused with a 400, so a test can
  never block your own IP. Its own state is prefixed `zs_e2e_` so the
  uninstall test never mistakes it for plugin data. `/ping` returns the run
  marker the runner writes to the git-ignored `tests/e2e/.run-marker`, and
  the preflight refuses a site that does not return it (another checkout's
  wp-env on the same port, or a Local site loading another copy).
  Its canned zerospam.org answers: a valid Business license for any key
  (`get-license`), an empty `v3/query` result in the shape the module reads,
  and eight fixed articles for the news feed. `/reset` also clears every
  user's `zerospam_ui` (6.0 preferences) and the cached news, and takes
  `level=enhanced` (saves the key `e2e-license` and turns Enhanced
  Protection on); `/ui-prefs` merges preferences for the current user.
- **Uninstall** always runs `wp plugin uninstall zero-spam --skip-delete`;
  the plugin directory is this repository. The CLI helpers
  (`tests/e2e/support/cli-guard.cjs`, shared by the runner scripts and the
  Playwright helpers) refuse `plugin delete` and `plugin uninstall` (with or
  without `--all`) without `--skip-delete`, also inside shell scripts run in
  a wp-env container. On Local it only runs with `E2E_ALLOW_DESTRUCTIVE=1`.
  Every Local run snapshots first and restores at the end: every site's
  plugin options with their autoload values (`zerospam_blocked_email_domains`
  stays non-autoloaded), `users_can_register` and `default_role`, the error
  log, the network's plugin options, and every `wpzerospam_*` table of the
  install with its schema (uninstall drops them; restore recreates them). A
  table above 50,000 rows stops the run before it starts instead of being
  saved in part. The network reset empties `wpzerospam_network_audit` on
  wp-env only; a Local network keeps its audit history.
- **Known defects:** a confirmed bug gets a test that asserts the correct
  behaviour, marked `test.fail( true, '<reason, AGENTS.md §8>' )`; the run
  stays green while the bug exists and fails once it is fixed ("Fixed (remove
  test.fail)" in the summary), which forces the marker's removal. Check that
  a new expected failure fails for the documented reason, not a harness error.
- **Accessibility policy** (`tests/e2e/support/a11y.ts`): axe-core with the
  WCAG 2.0–2.2 A/AA, AAA and best-practice tags. A critical or serious A/AA
  violation fails the run unless it is in `tests/e2e/a11y/allowlist.json`;
  moderate and minor, AAA-only, best-practice and "needs manual review"
  results are reported only. Every allowlist entry needs a real reason (empty
  or TODO fails the run); `kind: "debt"` (plugin markup) also needs an issue,
  `kind: "external"` (WordPress core or third-party markup) a reason. An entry
  with `target: "*"` covers every node of its rule on its screen, so it needs
  a `count` (the nodes it covered when recorded; only `external` entries may
  omit it): more matching nodes fail the run, fewer print "lower the count"
  (and list it in the summary). An entry that no longer matches fails the
  run, so the list only shrinks: fixing debt means lowering counts or
  deleting entries in the same PR. Entries have no per-target field; a
  screen's `sites` already limits where it runs. `E2E_A11Y_BASELINE=1`
  writes proposed entries to `artifacts/` instead of failing and accepts
  entries without a count; each entry's matched node count is in
  `summary.json` (`a11y[].allowlistUsage`).
- **Visual baselines** (`tests/e2e/__screenshots__/`, committed): element
  screenshots recorded on wp-env with the latest WordPress only (single site
  for shared screens, the network for network screens), 1280px plus 782px
  for key screens, with dates, counts, charts and the map masked or hidden
  (`tests/e2e/support/visual.css`). Other targets only write the gallery.
  Update them only with `npm run test:visual:update`, in a commit of its own,
  with Playwright's expected/actual/diff images in the PR. They are recorded
  and compared on macOS only (`-darwin`; other platforms only write the
  gallery); re-record after a macOS upgrade changes font rendering. Budget
  15 MB in total: above it the run fails. The summary warns about images
  over 300 KB.
- **Screens** are listed once in `tests/e2e/support/screens.ts` (smoke crawl,
  accessibility, visual, gallery). A new settings section must be added
  there; the settings test fails until it is. 6.0 screens use the `AREA(
  area, tab, extra )` path helper, `theme: 'both'` (each gets a `-dark` twin
  scanned under prefers-color-scheme dark; only screens with `visual.dark`
  keep a dark baseline) and `level: 'enhanced'` where the license matters
  (set before the page opens; the next state reset undoes it). The e2e
  context runs with reduced motion; `specs/admin-v6.spec.ts` covers the
  shell's keyboard paths and measures an idle Overview's main-thread time
  (Chrome DevTools Protocol TaskDuration over 10 s: at most 0.3 s animated,
  0.05 s reduced). `specs/activity.spec.ts` covers the Activity area
  (no requests on load, filters, sorting, the drawer's focus paths, Undo,
  the stored-XSS fixture through the mu-plugin's `seed-log` `data`
  argument, cards at 375px, the live bar, Reports).

## 6. Local environment

| Site | URL | Purpose |
|---|---|---|
| `~/Local Sites/wordpress-zero-spam` | http://wordpress-zero-spam.local/ | Single-site development. This repo is `wp-content/plugins/zero-spam` there. |
| `~/Local Sites/zero-spam-network` | http://zero-spam-network.local/ | Subdirectory multisite for network testing. |
| `~/Local Sites/zero-spam` | https://zerospam.local/ | Local copy of zerospam.org (separate repos; out of scope here). |

- Sites run under LocalWP. WP-CLI needs Local's MySQL socket:
  `php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/<site-id>/mysql/mysqld.sock" $(which wp) …`
  from the site's `app/public`. The site id is the directory under
  `~/Library/Application Support/Local/run/` whose `mysql/` matches the site.
- Keep WordPress current on all three sites; `Tested up to` must be earned.
- The PHPUnit suite does not use Local's MySQL. It needs Homebrew MySQL on
  `127.0.0.1` (`brew services start mysql`; user `root`, no password;
  override with `WP_TESTS_DB_HOST`, `WP_TESTS_DB_USER`,
  `WP_TESTS_DB_PASSWORD`). `bin/test.sh` checks the connection first.
- `--php=8.4` resolves to Homebrew PHP; `--php=8.2` to the newest LocalWP
  PHP 8.2 binary, which has no `php.ini` (the script passes
  `memory_limit=512M`).
- WordPress cores and the test library for the minimum version are downloaded
  once, checksum-verified, into `~/Library/Caches/zero-spam-tests/`
  (override with `ZEROSPAM_TESTS_CACHE`). Delete a directory there to force a
  fresh download. The test WordPress loads plugins from `plugins/` there,
  where `bin/test.sh` links `zero-spam` to the current checkout, so the suite
  works from a checkout with any directory name and never sees the Local
  site's other plugins.
- The plugin's email checks reject `.test` addresses; use `@example.com` in
  test data.
- **Previewing a branch on the Local site:** the single-site Local plugin
  folder *is* the main checkout, normally on `develop`, so merged work shows
  there after `git pull`. To preview an unmerged branch, run
  `git fetch origin <branch> && git checkout --detach origin/<branch> && composer install`
  in the main checkout, then hard-refresh the browser: admin assets are
  versioned by `ZEROSPAM_VERSION`, so the browser keeps the old CSS/JS until
  the version changes (unless the site defines `SCRIPT_DEBUG`, which versions
  them by file time). Afterwards run `git checkout develop && composer install`.
  Never switch it while `composer check` or the pre-push hook is running
  there. Agents work in their own worktree and never rely on the main
  checkout's branch.

### e2e environments

- **Node 22** (`.nvmrc`; `nvm use`; `engines` `^22`): `@wordpress/env` 12
  needs 22.13+ and `@wordpress/e2e-test-utils-playwright` 22.19+. The
  front-end build gives byte-identical output on 22.19 and 22.23
  (`@wordpress/scripts` 36 declares 22.22.2+, which only makes npm warn).
- **wp-env** (Docker Desktop): `.wp-env.json` serves the single site on port
  **8900**, `.wp-env.multisite.json` the subdirectory network on **8910**
  (8888 is taken by another project on the maintainer's Mac). Each config is
  its own Docker Compose project. `afterStart`
  (`tests/e2e/bin/wp-env-after-start.mjs`) activates the plugin and creates
  `e2eadmin` (super admin on the network), `e2eeditor`, `e2eauthor`, on the
  network `/site2/` and `e2esiteadmin` (administrator of `/site2/` only), and
  the "E2E comments" post. The checkout is mapped to
  `wp-content/plugins/zero-spam` whatever its directory name. The pinned
  WordPress versions are in `tests/e2e/wp-versions.json`
  (`npm run env:core:min` / `env:core:latest` switch). Never run
  `wp-env destroy` or `stop` for other configs than these two.
  Both configs define `ZEROSPAM_ADMIN_V6` (the 6.0 admin is tested next to
  the legacy screens until the cutover) and `ZEROSPAM_RESCUE_KEY`, so the accessibility scans and
  visual baselines only ever see the Rescue Mode card's "Active" state; the
  "Rescue key not set" state is covered by PHPUnit only (known e2e gap).
- **Local targets** need a one-off set-up that changes the Local sites
  (approve it first): make the network site load this checkout as
  `plugins/zero-spam` (it ran a stale `zero-spam-for-wordpress` 5.7.3 copy;
  the preflight refuses to run until it is fixed), then
  `npm run e2e:setup:local` (add `-- --rescue` to test rescue mode). That
  asks first, then adds a `ZEROSPAM_E2E_TOKEN` constant (and with `--rescue`
  a `ZEROSPAM_RESCUE_KEY`) to both `wp-config.php` files (`wp config set`),
  symlinks the test mu-plugin into each site's `wp-content/mu-plugins/`,
  creates `e2eadmin`, `e2eeditor` and on the network `e2esiteadmin`
  (administrator of `/test-site-1/`) with random passwords passed to WP-CLI
  on stdin (`--prompt=user_pass`), the "E2E comments" post, sets
  `users_can_register=1` on the single site, and writes the git-ignored
  `tests/e2e/.env`. A constant a site already had is never overwritten (its
  value is used); `.env` records which constants setup created
  (`E2E_LOCAL_CREATED_CONSTANTS`, `E2E_NETWORK_CREATED_CONSTANTS`) and
  `npm run e2e:teardown:local` removes only those while undoing the rest.
  Local's WP-CLI runs on Local's PHP 8.2 with
  `-d mysqli.default_socket=<socket> /opt/homebrew/bin/wp --skip-packages`.
- **When the mu-plugin stubs outgoing HTTP:** on every request that carries
  the token; on wp-env always (`ZEROSPAM_E2E_DISPOSABLE` in `.wp-env*.json`);
  on a Local site only while a run is in progress: the `setup` project sets
  the `zs_e2e_run_active` option (site option on the network) to an expiry
  three hours ahead and the `restore` teardown deletes it. Outside a run
  your own browsing of a Local site, its cron and its update checks reach
  WordPress.org and Zero Spam's services as usual. If a run is killed before
  its teardown, the stubs stop when the mark expires (or delete the option).
- The preflight (`tests/e2e/bin/preflight.mjs`) checks Node, Docker or the
  Local site and socket, the mu-plugin, the plugin version and path, the run
  marker (that the site loads this checkout), the WordPress version and DNS
  for `example.com`, and stops with one line saying what to do.

## 7. Working with the maintainer

- Never assume or guess. When anything is unclear, ask **one question at a
  time, with a recommendation**, and wait. Present suggestions the same way.
- For multi-file changes, refactors, settings or multisite work, and anything
  release-impacting: present the plan (files, patterns found, steps, risks,
  acceptance criteria) and get approval before editing.
- Outward-facing actions need explicit approval each time: merging to `main`,
  tagging, SVN commits, WordPress.org changes, vulnerability-program
  submissions, anything that posts or emails.
- When reviewing or touching code, also report nearby cleanup, performance,
  security and bug opportunities, each as its own suggestion.
- Report outcomes faithfully: failing checks with their output, skipped steps
  as skipped.

## 8. Known debt and pitfalls (October 2026, 5.7.14)

- WPCS backlog at 5.7.14: 143 errors / 172 warnings in 32 files (176
  auto-fixable), down from 293 / 292 in 52 files at 5.7.13 and 429 / 367 in
  62 files before; PHPStan level 5 baseline of 93 findings in 61 entries
  on develop for 6.0 (135 in 93 at 5.7.14, 144 in 99 at 5.7.13), with no
  unmatched entries. Clearing them is planned work; do it
  file by file with tests, not in one sweep. `phpcs.xml.dist` excludes
  `.claude/` (agent worktrees hold full plugin copies), `artifacts/` and
  `build/`; `composer compat` runs the `phpcs.compat.xml.dist` ruleset with the
  same exclusions, relative, so it still scans a checkout that itself lives
  under `.claude/worktrees/` (a `--ignore=*/.claude/*` on the command line
  matched the worktree's own path and scanned nothing).
- Fixed in 5.7.13 (tests now in the gate): the multisite locked-settings UI
  and site-save rule (locked keys are never stored; a saved value equal to the
  network value follows the network; REST returns 403 `rest_setting_locked`
  and WP-CLI warns and exits non-zero on locked keys), the `uninstall.php`
  cleanup, the
  detection-log table (pagination, filtered count, `prepare()` notice, region
  sort, lost type filter, bulk-delete capability), the Security module's
  cross-wired settings, the unprotected `.htaccess` refresh (now an
  `admin_post` action), the Stop Forum Spam cache group, `DB::log()` pruning,
  the never-loaded Elementor Pro module, the login cookie guard, and the
  1970 end date on permanent blocks.
- Fixed in 5.7.14 (tests in the gate): the client IP behind CDNs and proxies
  (`CF-Connecting-IP` / `X-Forwarded-For` read only from Cloudflare or the
  Trusted Proxy IPs setting, `zerospam_trusted_proxies` with CIDR, #430);
  logins refused on lapsed David Walsh keys, including WPMU DEV Defender Pro
  and other two-step logins (#429); lockouts without a way back in (emailed
  rescue link and saved administrator addresses, #432); network-screen
  textarea settings losing their newlines (#430); IPv6 matching in the IP
  Whitelist and blocked IPs (canonical form, IPv4-mapped addresses, #430);
  `Settings::get_settings()` registering `rescue_mode` after the
  `zerospam_settings` filter (missing from the first call, invisible to
  filters); and, from #433, keyboard-accessible settings switches, a
  `prefers-reduced-motion` rule and button contrast.
- Confirmed defects awaiting a fix (each gets its own PR with a failing-first
  test tagged `@group known-defect`, run with `composer test:defects`):
  - `modules/class-stopforumspam.php`: `preprocess_registrations()` is hooked
    to `zerospam_preprocess_registration_submission`, whose arguments are
    `( array $errors, $post, 'registration_spam_message' )`, so the registration
    check receives nonsense and would fatal (`$errors->add()` on an array) on a
    hit; Stop Forum Spam registration protection has never worked. Comment
    detections from this module are logged with reason `0` (numeric key).
  - Cache group mismatch (`wp_cache_get()` default group vs `wp_cache_set()`
    group) in `modules/class-projecthoneypot.php` (~92),
    `modules/ipbase/class-ipbase.php` (~203), `modules/class-ipinfomodule.php`
    (~293) and `modules/class-ipstack.php` (~173); the Stop Forum Spam cache
    is object-cache only (no transient fallback), so it lasts one request on
    hosts without a persistent object cache.
  - `core/admin/tables/class-blockedtable.php`: the bulk delete never deletes
    (`sanitize_text_field()` on the `ids` array returns `''`) and has no
    capability check of its own; the legacy delete links never refresh
    `.htaccess`. The Blocked Locations search looks at the empty IP column.
    The 6.0 REST routes that replace these screens (`rules/blocks`,
    `rules/blocks/bulk-delete`, used by the 6.0 Rules area) delete,
    search by location value or country name and refresh `.htaccess`, and
    `Utilities::refresh_htaccess()` now clears the rules when no IP is
    blocked any more; the legacy screens keep their bugs until the cutover
    (PR 13) removes them.
  - `core/admin/tables/class-logtable.php`: the Actions column runs one
    blocked-IP query per row (about 50 per page); a page past the end shows
    "No items found" with no pagination. `GET activity` (6.0) resolves the
    blocked state with one query per page and serves the last page instead
    (PHPUnit `test-rest-activity.php`; the 6.0 Activity screen's page clamp
    in `specs/activity.spec.ts`); the legacy table stays as it is until the
    cutover.
  - `core/class-settings.php` `update_blocked_email_domains()` (the legacy
    "Use Blocked Email Domains Recommendation" link) rewrites the whole
    `zero-spam-settings` option with resolved values, which on a network
    turns network defaults into site overrides; the REST action writes only
    `zerospam_blocked_email_domains`.
  - ipstack is queried over plain `http://`.
  - `modules/security/class-security.php`: the emoji setting is incomplete
    since WordPress 6.4 (`wp_enqueue_emoji_styles` on
    `wp_enqueue_scripts`/`admin_enqueue_scripts` and the `embed_head` script
    are not unhooked); the setting title has a typo ("Emoji's");
    `remove_resource_query_params` is hooked with 2 arguments but takes 1.
  - `core/class-utilities.php` `refresh_htaccess()`: a subsite admin in
    `.htaccess` mode writes the network-root `.htaccess`.
  - `Network_Settings` is
    instantiated in several places, each registering its own resolver;
    `Network_Settings::is_using_default()` rebuilds the settings registry per
    call (the settings page now caches, other callers do not).
- Accessibility debt proven by the Playwright suite (each item has an
  expected-failure test or entries in `tests/e2e/a11y/allowlist.json`;
  GitHub issues still to be filed):
  - the add/update block modal (`assets/js/admin.js`) does not move focus
    into the modal or return it to the button that opened it (the 6.0 Rules
    area replaces it with the shared block drawer, whose focus handling
    `keyboard.spec.ts` and `rules.spec.ts` check; the modal goes at the
    cutover);
  - the admin header logo (`<img width="250">` in
    `includes/templates/admin-header.php`) makes the plugin screens 378px wide
    at a 320px viewport, where WordPress's own screens fit (WCAG 1.4.10); the
    Dashboard (reports) is still 329px wide without the logo, through
    `.zerospam-block--review`, the pie and line chart blocks and
    `canvas#zerospam-bar-chart`, so fixing the logo alone does not flip its
    reflow test;
  - images without `alt`: the header logo and status icon
    (`admin-header.php`), the dashboard menu icons
    (`core/admin/class-dashboard.php`) and the images in the icon-only
    list-table action buttons (`core/admin/tables/`);
  - controls without a label: the import/export and error-log textareas
    (`core/admin/class-settings.php`), the list-table row checkboxes, the
    network settings multi-select value control (`render_setting_field()`;
    its textareas are labelled since #430), the network import file input
    and the Network Stats period select;
  - the Facebook `<iframe>` in the dashboard sidebar has no `title`;
  - text below 4.5:1 contrast: the admin link colour `#ff2929` (3.74:1) on
    the header "Enhanced Protection is DISABLED" link, list-table sort headers
    and IP links, the "Tweets by ZeroSpamOrg" link and links in setting
    descriptions and dashboard blocks; `<code>` in the David Walsh help text;
    the promo notice's status text; and the Network Stats trend badges (the
    `color-contrast` entries of `tests/e2e/a11y/allowlist.json`).
- Admin UI debt not covered by a test yet:
  - the settings row titles are printed by WordPress's settings API as
    `<label for="{setting key}">` (`label_for`), but checkbox and radio inputs
    have the id `{setting key}{option}`, so for those settings the title
    labels nothing;
  - the Network Settings page (`render_setting_field()` in
    `includes/admin/class-networksettingspage.php`) shows native checkboxes
    inside their labels, in WordPress blue, where the site settings show the
    plugin's `.zerospam-switch`, so the same setting looks different on the
    two pages.
- `assets/css/admin.css` is not a pure compile of `assets/src/scss/admin.scss`:
  about 850 lines (documentation, feature/step lists, labels) exist only in
  the CSS. Change both by hand, keeping the compiled regions identical to
  what sass produces, until those rules are moved into the SCSS. There is no
  source map.
- Found by the 5.7.14 Local smoke test, not fixed yet (each needs its own
  PR):
  - on a network subsite, a setting whose network value is empty shows
    "Using network default:" followed by an empty box;
  - Network Stats labels sites "Enhanced" even when they have no working
    license;
  - the API monitoring notice's links (Enable, Dismiss) point to
    `admin.php?page=wordpress-zero-spam-settings…` instead of
    `options-general.php?page=…`;
  - the network settings lock button's 🔓 emoji is replaced by WordPress's
    emoji script with an `s.w.org` image (an external asset in admin).
- 6.0 Overview data (PR 4): "caught by the Zero Spam network" is derived
  from the log (`Stats_Controller::network_only_sql()`: `blocked` rows whose
  `failed` is `zero_spam`, or whose reason starts with the 5.x "High
  Confidence Score:" sentence in English, the site language or the user's
  language); a visitor the network blocks on several requests counts once
  per blocked request, like every other detection, and a row logged in a
  third language before 6.0 is not counted. Still missing: the API reports no
  renewal date (`License_Status` renewal.date is always null), so the renewal
  reminder never shows; all-time totals are not kept, so the milestone counts
  the last 30 days (a lifetime counter is a follow-up).
- `includes/templates/admin-bar-chart.php` and `admin-line-chart.php` still
  load Chart.js from a CDN (the e2e browser serves the bundled
  `assets/js/vendor/chart.umd.min.js` instead and lists the request in the
  summary); the dashboard sidebar loads Twitter's script and a Facebook
  iframe; the dashboard page loads every log row.
- Network settings live in `zerospam_network_settings` only. The 5.x REST
  option `zero-spam-network-zerospam` is merged into it once per network
  (migration `v6_network_store`, guarded by the site option
  `zerospam_network_completed_migrations`) and kept, unread and unwritten,
  for a downgrade; remove it, and `zerospam_backup_5x`, in 6.1.
- `DavidWalsh::validate_token()` caches accepted tokens in a function-level
  `static $accepted`, which tests cannot reset; use a fresh token per test.
- `core/class-cli.php` is dead code; the `zerospam_blacklist` table is defined
  but never created.
- Release pitfall: in March 2026 a release shipped as 5.5.8 instead of 5.7.8.
  `composer version:check` exists because of it.
- Follow-up for PR 13: `rules/blocks` and `rules/blocks/bulk` accept
  loopback (127.0.0.1, ::1) and private ranges as blocked addresses (as the
  5.x screens do); decide whether to refuse them.
- Follow-up chore after PR 8 (6.0 admin): split `assets/dist/admin.js` into
  per-area entry points (one bundle per page, shared code in a common
  entry, translations per handle) and bring the admin JS budget in
  `bin/check-dist.sh` back from the temporary 110 KB to 75 KB gz.
- 6.0 area baselines (policy since PR 5): capture `.zs-areabody` only (the
  shell is covered by the Overview baselines), 782px for one key screen per
  area, one dark 1280px screen per area, 8–10 seeded rows (a screen's own
  `before`), table and card text hidden by `tests/e2e/support/visual.css`.
