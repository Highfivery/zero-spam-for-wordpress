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
| `bin/`, `.githooks/`, `tests/`, `phpstan*.neon*`, `phpcs.xml.dist` | Quality gate (section 5). Never shipped: see `.distignore`. |

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
- Per-site tables `{prefix}zerospam_log`, `{prefix}zerospam_blocked`;
  network-wide (`base_prefix`) `zerospam_api_usage`, `zerospam_stats_daily`,
  `zerospam_stats_monthly`, `zerospam_network_templates`,
  `zerospam_network_audit`. Schema changes go through `DB::update()` and a
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
composer check       # version check, WPCS on changed files, PHP 8.2+ compat, PHPStan
composer check:all   # same with WPCS on every file (red until the backlog is cleared)
composer lint:fix    # apply the WPCS fixes phpcbf can make
```

- `.githooks/pre-push` runs `composer check` and refuses the push on failure.
  `SKIP_QUALITY_GATE=1` is for a genuine emergency only and must be mentioned
  in the PR.
- `phpstan-baseline.neon` holds pre-existing findings. Fix new findings; never
  regenerate the baseline to hide them. Shrink it when you touch a listed file.
- **Done means proven:** the PR description contains the `composer check`
  summary, the manual smoke-test steps and results on both Local sites
  (single site and multisite), screenshots or recordings for any UI change,
  the `readme.txt` changelog entry, and a note of anything not verified and
  why. Do not claim something works without having run it.
- A PHPUnit and Playwright suite is planned; when it exists, every change
  comes with tests and the gate includes them.
- Do not create markdown process files (`NOTES.md`, `PLAN.md`, "what I did"
  files). Plans and findings go in the PR or in the conversation.

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
- The plugin's email checks reject `.test` addresses; use `@example.com` in
  test data.

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
- Confirmed defects awaiting a fix: `uninstall.php` single-site branch loops
  over array indexes and never deletes module options; the log table builds
  `LIMIT` with count and offset swapped; the Security module wires the
  "remove resource query parameters" and "disable emojis" settings to each
  other's behavior; `admin_init` rewrites `.htaccess` on a query flag with no
  nonce or capability check; `new WP_Error` inside namespaced modules resolves
  to `ZeroSpam\Modules\WP_Error`.
- `includes/templates/admin-bar-chart.php` and `admin-line-chart.php` still
  load Chart.js from a CDN; the dashboard page loads every log row.
- Network settings exist in two storage models (`zerospam_network_settings`
  used by the admin, CLI and most REST; `zero-spam-network-zerospam` used by
  the `zero-spam/v1/settings` resolver). Reconcile before extending either.
- `core/class-cli.php` is dead code; the `zerospam_blacklist` table is defined
  but never created.
- Release pitfall: in March 2026 a release shipped as 5.5.8 instead of 5.7.8.
  `composer version:check` exists because of it.
