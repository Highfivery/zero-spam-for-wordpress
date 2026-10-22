#!/usr/bin/env bash
#
# Run PHP_CodeSniffer on the PHP files that differ from the integration branch.
#
# Usage: bin/lint-changed.sh [base-ref]
#   base-ref defaults to origin/develop (falls back to develop, then HEAD~1).
#   PHPCS_STANDARD=PHPCompatibilityWP runs the compatibility ruleset instead
#   of the project's WPCS ruleset.
#
# Whole files are checked, not just changed lines: touching a file means
# leaving it clean.

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

base_ref="${1:-}"
if [[ -z "$base_ref" ]]; then
	for candidate in origin/develop develop; do
		if git rev-parse --verify --quiet "$candidate" >/dev/null; then
			base_ref="$candidate"
			break
		fi
	done
fi
base_ref="${base_ref:-HEAD~1}"

merge_base="$(git merge-base HEAD "$base_ref" 2>/dev/null || echo "$base_ref")"

# Committed changes since the merge base, plus anything staged or unstaged.
files="$( { git diff --name-only --diff-filter=ACMR "$merge_base" HEAD -- '*.php'; \
            git diff --name-only --diff-filter=ACMR HEAD -- '*.php'; } \
          | grep -vE '^(vendor|node_modules|tests)/' | sort -u || true )"

# Only lint files that still exist.
existing=()
while IFS= read -r f; do
	[[ -n "$f" && -f "$f" ]] && existing+=("$f")
done <<< "$files"

if [[ ${#existing[@]} -eq 0 ]]; then
	echo "lint-changed: no PHP files changed against ${base_ref}. Nothing to lint."
	exit 0
fi

echo "lint-changed: ${#existing[@]} PHP file(s) changed against ${base_ref}:"
printf '  %s\n' "${existing[@]}"

if [[ "${PHPCS_STANDARD:-}" == "PHPCompatibilityWP" ]]; then
	exec vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.2- "${existing[@]}"
fi

exec vendor/bin/phpcs --standard=phpcs.xml.dist "${existing[@]}"
