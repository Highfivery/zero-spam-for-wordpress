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
| `core/` | `Access` (block checks on `template_redirect`, `login_init`, XML-RPC; rescue mode), `Settings` (registry built from the `zerospam_settings` filter), `Utilities`, `User::get_ip()`, `core/admin/` (settings page, dashboard, dashboard widget, `WP_List_Table`s). |
| `includes/class-db.php` | Table definitions, `dbDelta` upgrades keyed on `zerospam_db_version`, `DB::log()`, `DB::query()`, `DB::blocked()`. |
| `includes/class-migrations.php` | Run-once migrations tracked in `zerospam_completed_migrations`. Add one here for any data/option shape change. |
| `includes/class-networksettings*.php`, `includes/admin/`, `includes/cli/`, `includes/rest/` | Multisite network settings, templates, stats, audit, notifications, REST controllers (`zero-spam/v1`), WP-CLI (`wp zerospam …`). |
| `includes/templates/` | Plain PHP admin templates. Escape everything on output. |
| `modules/` | One directory or file per detection technique or integrated plugin. Third-party modules are only instantiated when that plugin is active (`includes/class-plugin.php`). |
| `assets/` | Hand-written CSS/JS (jQuery), `assets/src/scss/admin.scss` compiled to `assets/css/admin.css` by hand, vendored Chart.js and jvectormap, flag images, `blacklist.txt`, `disposable-email-domains.txt`. No build tooling exists. |
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
  `DB::$tables`). `wpzerospam_blacklist` is listed there but never created. Schema changes go through `DB::update()` and a
  `DB_VERSION` bump.
- Logged submission data is sanitized on write (keys and values) and must be
  escaped on read: it has carried a stored XSS before (CVE-2026-96752).

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
- **Changelog:** `readme.txt` is the only changelog. Every user-visible or
  behavioral change adds a bullet under the next `= vX.Y.Z =` heading in the
  existing `**type(scope):** description` style, linking issues or support
  topics. Fill the PR template's "Changelog Entry" too.
- **Versions:** `Version:` header, `ZEROSPAM_VERSION` and `Stable tag` must
  agree and never go backwards (`composer version:check`). Bump
  `Tested up to` only after testing on that WordPress release.
- **Docs:** when behavior changes, update `readme.txt` (description, FAQ) and
  the in-admin docs in `includes/templates/docs/`. Network settings
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
composer check       # version check, WPCS on changed files and tests, PHP 8.2+ compat, PHPStan, PHPUnit
composer check:all   # WPCS on every file and the tests, compat, PHPStan, the PHPUnit matrix
                     # (red until the WPCS backlog is cleared)
composer lint:fix    # apply the WPCS fixes phpcbf can make
```

- `.githooks/pre-push` runs the same checks as `composer check` (PHPUnit is
  step 6/6, `bin/test.sh --mode=all`) and refuses the push on failure.
  `SKIP_QUALITY_GATE=1` is for a genuine emergency only and must be mentioned
  in the PR.
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
  `admin_screen()`, `create_site_with_tables()`) instead of ad-hoc set-up.
  Groups: `ms-required` (network only), `ms-excluded` (single site only),
  `known-defect`.
- **Known-defect policy:** a confirmed bug that is not fixed yet gets a test
  that asserts the correct behaviour, tagged `@group known-defect`. Those tests
  are excluded from `composer test` and the hook, run with
  `composer test:defects`, and must fail for the documented reason (not a
  harness error). The PR that fixes the bug removes the tag so the test joins
  the gate. Never tag a test `known-defect` to get a red test past the gate.
- **Database safety:** the suite installs WordPress into `zerospam_tests`
  (single) and `zerospam_tests_ms` (multisite) on the Homebrew MySQL, never a
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
  `setcookie()` and `headers_sent()` are stubbed in the plugin namespaces that
  call them
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
  there; the settings test fails until it is.

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

### e2e environments

- **Node 22** (`.nvmrc`; `nvm use`): `@wordpress/env` 12 needs 22.13+ and
  `@wordpress/e2e-test-utils-playwright` 22.19+.
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

## 8. Known debt and pitfalls (October 2026)

- WPCS backlog measured at 429 errors / 367 warnings in 62 files (340
  auto-fixable); PHPStan level 5 baseline of 168 findings. Clearing them is
  planned work; do it file by file with tests, not in one sweep.
- Confirmed defects awaiting a fix (each gets its own PR with a failing-first
  test). The first six below, through `DB::log()`, already have that test,
  tagged `@group known-defect` (`composer test:defects`); the `region` sort
  and the rest do not yet:
  - `uninstall.php`: the single-site branch loops over array indexes and
    deletes `zero-spam-0`, `zero-spam-1`, … instead of the module options; the
    module list omits `settings`, `ipbase`, `elementor` and uses `fluentforms`
    for the `fluent_form` key; the two `network_*` tables are never dropped;
    the options `zerospam_blocked_email_domains`, `zerospam_api_notices` and
    `zerospam_settings_audit` and the `zero_spam_circuit_open` /
    `zero_spam_failure_count` transients (prefix `zero_spam_`, not matched by
    the cleanup query) are left behind; on a network the site options
    `zerospam_network_settings`, `zero-spam-network-zerospam`,
    `zerospam_network_notifications_enabled`,
    `zerospam_network_settings_notifications_enabled` and
    `zero_spam_last_api_request` are never removed; the
    `zerospam_network_weekly_summary` cron event is never cleared; and the
    multisite branch calls `switch_to_blog()` per site but
    `restore_current_blog()` once, so the network-wide cron clean-up runs on
    the wrong site.
  - `core/admin/tables/class-logtable.php`: `LIMIT` has count and offset
    swapped, the offset is `per_page * page` instead of `per_page * (page - 1)`,
    `$wpdb->prepare()` is called with no placeholders when no filter is set,
    the total count ignores the active filters, and `region` sorts by country.
  - `modules/security/class-security.php`: the "remove resource query
    parameters" and "disable emojis" settings trigger each other's behavior.
  - `core/admin/class-dashboard.php`: `admin_init` rewrites `.htaccess` on a
    query flag with no nonce or capability check.
  - `modules/class-stopforumspam.php`: `wp_cache_get()` reads the default
    group while `wp_cache_set()` writes group `zerospam`, so results are never
    served from cache.
  - `includes/class-db.php`: `DB::log()` prunes only when the table is already
    above `max_logs` (`>`), then inserts, so the log settles at
    `max_logs + 1` rows.
  - `modules/elementor/class-elementor.php` exists but `Plugin::init_modules()`
    never instantiates it.
  - `includes/admin/class-siteadminoverrides.php` hooks
    `zerospam_setting_field_before/after`, `zerospam_setting_field_disabled`
    and `zerospam_settings_page_top`, but the settings page never fires them,
    so site admins on a network get no lock badge and can edit locked fields
    whose values are then silently overridden. `settings_validation` also
    stores `false` for every unticked checkbox, so one save on a subsite turns
    every checkbox into a site override.
  - `modules/login/class-login.php`: `setcookie()` with no `headers_sent()`
    guard and dead PHP < 7.3 branches.
  - Unqualified `WP_Error` in docblocks of namespaced classes (for example
    `includes/rest/class-apiusagecontroller.php`,
    `modules/class-stopforumspam.php`) resolves to the wrong namespace for
    static analysis; the runtime code uses `\WP_Error` or `use WP_Error`
    correctly. Fix the docblocks and drop the matching baseline entries.
- Accessibility debt proven by the Playwright suite (each item has an
  expected-failure test or entries in `tests/e2e/a11y/allowlist.json`;
  GitHub issues still to be filed):
  - the dashboard widget's Refresh result is shown by an icon swap only; the
    "Data refreshed." string is never put in a live region (WCAG 4.1.3);
  - the add/update block modal (`assets/js/admin.js`) does not move focus
    into the modal or return it to the button that opened it;
  - no plugin stylesheet has a `prefers-reduced-motion` rule;
  - `assets/css/admin.css` hides every settings checkbox and radio with
    `display: none` and draws the switch on the label, so the settings
    toggles cannot be reached or switched with the keyboard (WCAG 2.1.1);
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
    network settings value controls (`render_setting_field()`), the network
    import file input and the Network Stats period select;
  - the Facebook `<iframe>` in the dashboard sidebar has no `title`;
  - text below 4.5:1 contrast: the header "Enhanced Protection is DISABLED"
    link, the restyled "Save Settings" and other buttons, list-table sort
    headers and IP links, the "Tweets by ZeroSpamOrg" link, the promo notice's
    call to action and status text, and the Network Stats trend badges.
- `includes/templates/admin-bar-chart.php` and `admin-line-chart.php` still
  load Chart.js from a CDN (the e2e browser serves the bundled
  `assets/js/vendor/chart.umd.min.js` instead and lists the request in the
  summary); the dashboard sidebar loads Twitter's script and a Facebook
  iframe; the dashboard page loads every log row.
- Network settings exist in two storage models (`zerospam_network_settings`
  used by the admin, CLI and most REST; `zero-spam-network-zerospam` used by
  the `zero-spam/v1/settings` resolver). Reconcile before extending either.
- `DavidWalsh::validate_token()` caches accepted tokens in a function-level
  `static $accepted`, which tests cannot reset; use a fresh token per test.
- `core/class-cli.php` is dead code; the `zerospam_blacklist` table is defined
  but never created.
- Release pitfall: in March 2026 a release shipped as 5.5.8 instead of 5.7.8.
  `composer version:check` exists because of it.
