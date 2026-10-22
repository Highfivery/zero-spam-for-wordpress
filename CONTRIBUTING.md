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

- `develop` is the default branch and the integration branch. It always contains the next release and is kept in sync with `main` (fast-forwarded after every release).
- `main` always matches the latest release published on WordPress.org. Release tags (`vX.Y.Z`) are cut from `main`.
- Branch from `develop` using a descriptive prefix (`fix/`, `feature/`, `security/`, `chore/`, `docs/`) and open pull requests against `develop`.
- Both `develop` and `main` are protected: changes land only through pull requests, and force pushes and branch deletion are disabled. There is no hosted CI; the quality gate below runs locally before every push and its results are posted on the PR.
- Commit messages follow the conventional style already used in the history: `fix(scope): …`, `feat(scope): …`, `security(scope): …`, `perf(scope): …`, `docs: …`, `chore: …`.
- Every PR must include a `readme.txt` changelog entry (see the PR template). `readme.txt` is the single changelog for this project; there is no separate `CHANGELOG.md`.

## Quality gate

This project deliberately uses no hosted CI (GitHub Actions is not used). Instead the checks are committed and run on the developer's machine:

```bash
composer install        # also points git at .githooks/ so the pre-push hook is active
composer check          # the gate: version check, WPCS on changed files, PHP 8.2+ compatibility, PHPStan
composer check:all      # the same with WPCS on every file (red until the pre-existing backlog is cleared)
```

| Script | What it runs |
|---|---|
| `composer lint` / `composer lint:fix` | The full WordPress Coding Standards ruleset (`phpcs.xml.dist`) on every PHP file; `lint:fix` applies the fixes `phpcbf` can make |
| `composer lint:changed` | WPCS on the PHP files changed against `origin/develop` (whole files, so a touched file is left clean) |
| `composer check` / `composer check:all` | Version check, WPCS (changed files / every file), PHP compatibility and PHPStan in one run |
| `composer compat` | PHPCompatibilityWP for PHP 8.2 and newer |
| `composer analyse` | PHPStan level 5 with WordPress and WP-CLI stubs, against `phpstan-baseline.neon` |
| `composer version:check` | The plugin header, `ZEROSPAM_VERSION` and the `readme.txt` Stable tag and changelog agree and are not lower than the latest release tag |

The committed `.githooks/pre-push` hook runs the version check, WPCS and PHP compatibility on changed files, and PHPStan, and refuses the push when any of them fail. `SKIP_QUALITY_GATE=1 git push` bypasses it for a genuine emergency only; say so in the PR.

`phpstan-baseline.neon` records the pre-existing findings so the gate passes today. Do not regenerate it to silence new findings; fix them, and shrink the baseline whenever you touch a file it lists.

Every PR must include the output of `composer check` (or the equivalent subagent report) in its description so the PR carries a visible test record.

## Release instructions

1. **Branch:** starting from an up-to-date `develop`, cut `release/X.Y.Z`.
2. **Version bump:** update the version in all three places and confirm they match: the `Version:` plugin header and the `ZEROSPAM_VERSION` constant in `wordpress-zero-spam.php`, and `Stable tag:` in `readme.txt`. Compare against the previous release tag so the version never goes backwards (in March 2026 a release was mislabeled 5.5.8 instead of 5.7.8, so 5.7.7 sites were never offered the update).
3. **Changelog:** add a `= vX.Y.Z =` section at the top of `== Changelog ==` in `readme.txt`, one bullet per change using the `**type(scope):**` convention, linking issues and support topics where relevant.
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
