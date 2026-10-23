# Contributing and Maintaining

First, thank you for taking the time to contribute!

The following is a set of guidelines for contributors as well as information and instructions around our maintenance process.  The two are closely tied together in terms of how we all work together and set expectations, so while you may not need to know everything in here to submit an issue or pull request, it's best to keep them in the same document.

## Ways to contribute

Contributing isn't just writing code - it's anything that improves the project.  All contributions are managed right here on GitHub.  Here are some ways you can help:

### Reporting bugs

If you're running into an issue, please take a look through [existing issues](/issues) and [open a new one](/issues/new) if needed.  If you're able, include steps to reproduce, environment information, and screenshots/screencasts as relevant.

### Suggesting enhancements

New features and enhancements are also managed via [issues](/issues).

### Pull requests

Pull requests represent a proposed solution to a specified problem.  They should always reference an issue that describes the problem and contains discussion about the problem itself.  Discussion on pull requests should be limited to the pull request itself, i.e. code review.

For more on how Highfivery Studio writes and manages code, check out our [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).

## Workflow

Agent and contributor conventions (repository map, standards, quality gate, definition of done) live in [`AGENTS.md`](AGENTS.md). Keep it current whenever a convention changes.

- `develop` is the default branch and the integration branch. It always contains the next release and is kept in sync with `main` (fast-forwarded after every release).
- `main` always matches the latest release published on WordPress.org. Release tags (`vX.Y.Z`) are cut from `main`.
- Branch from `develop` using a descriptive prefix (`fix/`, `feature/`, `security/`, `chore/`, `docs/`) and open pull requests against `develop`.
- Both `develop` and `main` are protected: changes land only through pull requests, and force pushes and branch deletion are disabled. There is no hosted CI; the quality gate below runs locally before every push and its results are posted on the PR.
- Commit messages follow the conventional style already used in the history: `fix(scope): …`, `feat(scope): …`, `security(scope): …`, `perf(scope): …`, `docs: …`, `chore: …`.
- Every PR must include a `readme.txt` changelog entry (see the PR template). `readme.txt` carries the user-facing changelog summary for the current minor series and `CHANGELOG.md` the full entries and history; release PRs write both (see the release instructions below).

## Quality gate

This project deliberately uses no hosted CI (GitHub Actions is not used). Instead the checks are committed and run on the developer's machine:

```bash
composer install        # also points git at .githooks/ so the pre-push hook is active
composer check          # the gate: version check, WPCS on changed files and the tests, PHP 8.2+ compatibility, PHPStan, PHPUnit
composer check:all      # WPCS on every file and the tests, PHPStan and the full PHPUnit matrix (red until the pre-existing WPCS backlog is cleared)
```

| Script | What it runs |
|---|---|
| `composer lint` / `composer lint:fix` | The full WordPress Coding Standards ruleset (`phpcs.xml.dist`) on every PHP file; `lint:fix` applies the fixes `phpcbf` can make |
| `composer lint:changed` | WPCS on the PHP files changed against `origin/develop` (whole files, so a touched file is left clean) |
| `composer check` / `composer check:all` | Version check, WPCS (changed files and the tests / every file and the tests), PHP compatibility, PHPStan and PHPUnit (one run / the full matrix) |
| `composer test` | PHPUnit on single site and multisite against the latest WordPress (`bin/test.sh --mode=all`). Needs MySQL on 127.0.0.1 (`brew services start mysql`); downloads WordPress once into `~/Library/Caches/zero-spam-tests/` |
| `composer test:unit` / `test:integration` / `test:multisite` | One test suite |
| `composer test:wp` | The matrix: PHP 8.2 and 8.4 × minimum and latest WordPress × single site and multisite, then checks `Tested up to` and `Requires at least` in `readme.txt` |
| `composer test:defects` | Only the `@group known-defect` tests, which document confirmed bugs and fail until each is fixed |
| `composer test:coverage` | PHPUnit with an HTML coverage report in `build/coverage/` (needs pcov or Xdebug) |
| `composer lint:tests` | WPCS on the test code (`phpcs.tests.xml.dist`) |
| `composer compat` | PHPCompatibilityWP for PHP 8.2 and newer |
| `composer analyse` | PHPStan level 5 with WordPress and WP-CLI stubs, against `phpstan-baseline.neon` |
| `composer version:check` | The plugin header, `ZEROSPAM_VERSION` and the `readme.txt` Stable tag and changelog agree and are not lower than the latest release tag |
| `npm run test:e2e:wp-env` (`composer test:e2e:wp-env`) | The Playwright end-to-end, accessibility (axe-core) and visual-regression suite on the disposable wp-env single site (port 8900) and network (port 8910), latest WordPress. Needs Node 22 (`nvm use`), Docker Desktop, `npm ci`, `npx playwright install chromium` and `npm run env:start` / `env:start:ms`. Not part of `composer check` or the hook |
| `npm run test:e2e:wp-env:min` | The same on the minimum supported WordPress (`tests/e2e/wp-versions.json`), then switches wp-env back to the latest |
| `npm run test:e2e` / `test:e2e:local` / `test:e2e:network` | The suite on the Local single site and/or network (after the one-off `npm run e2e:setup:local`; see `AGENTS.md` section 6) |
| `npm run test:a11y` / `test:visual` / `test:visual:update` | Accessibility scans only / compare the committed visual baselines / re-record them (in a commit of its own) |

The committed `.githooks/pre-push` hook runs the version check, WPCS and PHP compatibility on changed files, WPCS on the test code, PHPStan and the PHPUnit suite (single site and multisite), and refuses the push when any of them fail. Pushes that only delete branches or only push tags skip the gate. `SKIP_QUALITY_GATE=1 git push` bypasses it for a genuine emergency only; say so in the PR.

`phpstan-baseline.neon` records the pre-existing findings so the gate passes today. Do not regenerate it to silence new findings; fix them, and shrink the baseline whenever you touch a file it lists.

Every PR must include the output of `composer check` (or the equivalent subagent report) in its description so the PR carries a visible test record.

The Playwright suite (`tests/e2e/`) runs outside the hook because it needs Docker or the Local sites and takes several minutes. Run it before opening a PR and paste the combined `summary.md` it prints (also in `artifacts/e2e/latest/summary.md`) into the PR description, naming the targets it ran on. Its last line is `E2E RESULT: PASS|FAIL …`. Accessibility and visual-baseline policies are in `AGENTS.md` section 5.

## Release instructions

1. **Branch:** starting from an up-to-date `develop`, cut `release/X.Y.Z`.
2. **Version bump:** update the version in all three places and confirm they match: the `Version:` plugin header and the `ZEROSPAM_VERSION` constant in `wordpress-zero-spam.php`, and `Stable tag:` in `readme.txt`. Compare against the previous release tag so the version never goes backwards (in March 2026 a release was mislabeled 5.5.8 instead of 5.7.8, so 5.7.7 sites were never offered the update).
3. **Changelog:** add a `= vX.Y.Z =` section at the top of `== Changelog ==` in `readme.txt`, one bullet per change using the `**type(scope):**` convention, linking issues and support topics where relevant. Add the full entry to `CHANGELOG.md` (`## vX.Y.Z`, `- **type(scope):** …`): `readme.txt` carries the user-facing summary (plain language, benefit first, no hook, class or migration detail), `CHANGELOG.md` the full entry. `readme.txt` keeps only the current minor series: when a new minor series starts, move the previous one to `CHANGELOG.md` (WordPress.org truncates the readme changelog at 5,000 words; `composer version:check` fails above 4,500).
4. **Compatibility:** check the current WordPress release (`https://api.wordpress.org/core/version-check/1.7/`). Bump `Tested up to` in `readme.txt` only after actually testing the plugin on that version.
5. **Review and test:** open the release PR against `develop`, confirm `composer check` passes, and run the full regression pass on both the single-site and the multisite Local sites (settings save, each enabled module, log and dashboard screens, network settings, uninstall).
6. **Merge to develop**, then open a PR from `develop` to `main` and merge it with a merge commit (not a squash) so history stays shared between the two branches.
7. **Tag and release:** on `main`, create an annotated tag `git tag -a vX.Y.Z -m "vX.Y.Z"`, push it, and create a [GitHub release](/releases/new) for that tag with the changelog section as its body.
8. **WordPress.org SVN (manual, there is no deploy action):** in the SVN working copy of `https://plugins.svn.wordpress.org/zero-spam`, replace `trunk/` with a clean export of the `vX.Y.Z` tag, excluding everything listed in `.distignore` (`rsync -a --delete --exclude-from=.distignore <export>/ <svn>/trunk/`). Review `svn status`, commit `trunk`, then `svn cp trunk tags/X.Y.Z` and commit the tag.
9. **Verify:** confirm https://wordpress.org/plugins/zero-spam/ shows the new version and `Tested up to`, and that `https://downloads.wordpress.org/plugin/zero-spam.X.Y.Z.zip` installs and activates cleanly on a fresh site. The update-check API can lag behind the plugin page by several hours.
10. **Sync develop:** `git checkout develop && git merge --ff-only main && git push origin develop`.
11. **Security releases:** if the release fixes a reported vulnerability, submit the patch details to the reporting program (Wordfence, Patchstack) only after the SVN tag is live, since that can start public disclosure.
12. **Close the milestone:** set the release date and link the GitHub release, then close it. Move any unfinished milestoned issues or PRs to the next milestone.

### What to do if things go wrong

If you run into issues during the release process and the new version has NOT been committed to WordPress.org, delete the GitHub tag and release, fix the problem on a new release branch, and re-tag and re-release once resolved.

If the new version HAS been committed to WordPress.org, do not delete or overwrite the SVN tag. Fix the problem on a new release branch, ship it as a patch release (X.Y.Z+1), and note at the top of its changelog entry that it is a hotfix for the previous release.
