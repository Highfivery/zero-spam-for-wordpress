#!/usr/bin/env bash
#
# Verify the front-end sources and the committed build (assets/dist/): lint,
# unit tests, a fresh build that must match assets/dist/ byte for byte, and
# the size budgets.
#
# assets/dist/ is committed because WordPress.org installs the plugin without
# running a build. This refuses a push whose assets/dist/ does not match a
# fresh production build of assets/src/, and any built file or font set over
# its budget (AGENTS.md section 5; budgets from the 6.0 build plan).
#
# Usage: bin/check-dist.sh [--force] [--twice] [--budget-only] [base-ref]
#   (default)      only when the build inputs or outputs changed against
#                  base-ref (origin/develop): assets/src/**, assets/dist/**,
#                  assets/fonts/**, package.json, package-lock.json,
#                  webpack.config.js, the lint and test configs, .nvmrc,
#                  bin/check-dist.sh
#   --force        run even when nothing relevant changed
#   --twice        also build a second time and require byte-identical output
#                  (proves the build is reproducible)
#   --budget-only  check the budgets of the committed files; no build
#
# Needs Node 22 (.nvmrc, package.json engines ^22). When the node on PATH is
# not 22, nvm is loaded from $NVM_DIR (default ~/.nvm), because git hooks do
# not read shell profiles. Refuses to run when node_modules is missing or older
# than package-lock.json (run npm ci yourself: installing here would skip or
# redo the e2e packages' install scripts behind your back). The unit tests
# render in Playwright's Chromium (npx playwright install chromium).

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

force=0
twice=0
budget_only=0
base_ref=""
for arg in "$@"; do
	case "$arg" in
		--force) force=1 ;;
		--twice) twice=1; force=1 ;;
		--budget-only) budget_only=1 ;;
		-*) echo "check-dist: unknown option $arg" >&2; exit 2 ;;
		*) base_ref="$arg" ;;
	esac
done

# Budgets in KB (1 KB = 1,024 bytes): gzip -9 size for JavaScript and CSS,
# file size for the fonts (woff2 is already compressed).
# admin JS and CSS: per area entry point (admin-{area}.js and .css), each.
budget_admin_js=75
budget_admin_css=25
budget_network_js=40
budget_network_css=25
budget_widget_js=6
budget_widget_css=6
budget_fonts=110
# Raw size: blocked.css is inlined into every blocked response.
budget_blocked_css_raw=8

dist="assets/dist"

# The admin areas with their own entry point (assets/src/admin/{area}/index.js,
# built to admin-{area}.js and .css): keep in step with adminAreas in
# webpack.config.js and the pages of Admin_App.
admin_areas=( overview activity rules protection settings help network )

# ---------------------------------------------------------------------------
# Is there anything to check?
# ---------------------------------------------------------------------------
if [[ $force -eq 0 && $budget_only -eq 0 ]]; then
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
	inputs=( assets/src assets/dist assets/fonts package.json package-lock.json webpack.config.js \
	         eslint.config.cjs .stylelintrc.json vitest.config.mjs .nvmrc bin/check-dist.sh )
	changed="$( { git diff --name-only "$merge_base" HEAD -- "${inputs[@]}"; \
	              git diff --name-only HEAD -- "${inputs[@]}"; \
	              git ls-files --others --exclude-standard -- "${inputs[@]}"; } | sort -u || true )"
	if [[ -z "$changed" ]]; then
		echo "check-dist: no build inputs or outputs changed against ${base_ref}. Nothing to check."
		exit 0
	fi
	echo "check-dist: build inputs or outputs changed against ${base_ref}:"
	printf '%s\n' "$changed" | sed 's/^/  /'
fi

# ---------------------------------------------------------------------------
# Budgets
# ---------------------------------------------------------------------------
budget_fail=0

gz_bytes() { gzip -9 -n -c "$1" | wc -c | tr -d '[:space:]'; }
file_bytes() { wc -c < "$1" | tr -d '[:space:]'; }
kb() { awk -v b="$1" 'BEGIN { printf "%.1f", b / 1024 }'; }

check_budget() {
	local label="$1" bytes="$2" limit_kb="$3"
	local limit=$(( limit_kb * 1024 ))
	local status="ok"
	if [[ "$bytes" -gt "$limit" ]]; then
		status="OVER"
		budget_fail=1
	fi
	printf '  %-34s %8s KB  (budget %3s KB)  %s\n' "$label" "$(kb "$bytes")" "$limit_kb" "$status"
}

check_budgets() {
	echo "check-dist: budgets (KB = 1,024 bytes; JavaScript and CSS gzip -9 unless marked raw, fonts as stored):"
	local f area
	local expected=( network.js network.css widget-refresh.js widget-refresh.css blocked.css blocked-rtl.css sprite.svg )
	for area in "${admin_areas[@]}"; do
		expected+=( "admin-$area.js" "admin-$area.css" "admin-$area-rtl.css" "admin-$area.asset.php" )
	done
	for f in "${expected[@]}"; do
		if [[ ! -f "$dist/$f" ]]; then
			echo "check-dist: $dist/$f is missing. Run: npm run build" >&2
			budget_fail=1
		fi
	done
	[[ $budget_fail -eq 0 ]] || return 0

	for area in "${admin_areas[@]}"; do
		check_budget "admin-$area.js (gzip)" "$(gz_bytes "$dist/admin-$area.js")" "$budget_admin_js"
		check_budget "admin-$area.css (gzip)" "$(gz_bytes "$dist/admin-$area.css")" "$budget_admin_css"
	done
	check_budget "network.js (gzip)" "$(gz_bytes "$dist/network.js")" "$budget_network_js"
	check_budget "network.css (gzip)" "$(gz_bytes "$dist/network.css")" "$budget_network_css"
	check_budget "widget-refresh.js (gzip)" "$(gz_bytes "$dist/widget-refresh.js")" "$budget_widget_js"
	check_budget "widget-refresh.css (gzip)" "$(gz_bytes "$dist/widget-refresh.css")" "$budget_widget_css"

	local fonts_total=0 font
	while IFS= read -r font; do
		fonts_total=$(( fonts_total + $(file_bytes "$font") ))
	done < <(find assets/fonts -type f -name '*.woff2' | sort)
	check_budget "fonts (assets/fonts/**/*.woff2)" "$fonts_total" "$budget_fonts"

	check_budget "blocked.css (raw, inlined)" "$(file_bytes "$dist/blocked.css")" "$budget_blocked_css_raw"
	check_budget "blocked-rtl.css (raw, inlined)" "$(file_bytes "$dist/blocked-rtl.css")" "$budget_blocked_css_raw"
	printf '  %-34s %8s KB  (no budget; inlined on Zero Spam screens)\n' "sprite.svg (gzip)" "$(kb "$(gz_bytes "$dist/sprite.svg")")"
}

if [[ $budget_only -eq 1 ]]; then
	check_budgets
	if [[ $budget_fail -ne 0 ]]; then
		echo "check-dist: over budget (or a built file is missing)." >&2
		exit 1
	fi
	echo "check-dist: all budgets met."
	exit 0
fi

# ---------------------------------------------------------------------------
# Node 22 and dependencies
# ---------------------------------------------------------------------------
node_major() { node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0; }

if [[ "$(node_major)" != "22" ]]; then
	export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
	if [[ -s "$NVM_DIR/nvm.sh" ]]; then
		# nvm.sh is not written for "set -u".
		set +u
		# shellcheck source=/dev/null
		. "$NVM_DIR/nvm.sh" --no-use
		nvm use --silent >/dev/null 2>&1 || true
		set -u
	fi
fi
if [[ "$(node_major)" != "22" ]]; then
	echo "check-dist: Node 22 is required (.nvmrc); found $(node -v 2>/dev/null || echo 'no node')." >&2
	echo "            Install it with: nvm install 22 (https://github.com/nvm-sh/nvm), then retry." >&2
	exit 1
fi
echo "check-dist: Node $(node -v), npm $(npm -v)."

if [[ ! -f node_modules/.package-lock.json || package-lock.json -nt node_modules/.package-lock.json ]]; then
	echo "check-dist: node_modules is missing or older than package-lock.json. Run: npm ci" >&2
	echo "            (then npx playwright install chromium if it asks), and push again." >&2
	exit 1
fi

# ---------------------------------------------------------------------------
# Lint and unit tests (including the design-token contrast test)
# ---------------------------------------------------------------------------
echo "check-dist: ESLint, stylelint and the Vitest unit tests."
npm run --silent lint:js
npm run --silent lint:css
npm run --silent test:js

# ---------------------------------------------------------------------------
# Build and compare
# ---------------------------------------------------------------------------
tmp="$(mktemp -d "${TMPDIR:-/tmp}/zerospam-dist.XXXXXX")"
trap 'rm -rf "$tmp"' EXIT

build_to() {
	local out="$1"
	if ! ZEROSPAM_DIST_DIR="$out" npx --no-install wp-scripts build >"$tmp/build.log" 2>&1; then
		cat "$tmp/build.log" >&2
		echo "check-dist: the build failed (output above)." >&2
		exit 1
	fi
}

echo "check-dist: building assets/src into a temporary directory."
build_to "$tmp/first"

if [[ $twice -eq 1 ]]; then
	echo "check-dist: building a second time to prove the build is reproducible."
	build_to "$tmp/second"
	if ! diff -rq "$tmp/first" "$tmp/second" >"$tmp/twice.diff"; then
		sed 's/^/  /' "$tmp/twice.diff" >&2
		echo "check-dist: two builds of the same sources differ; the build is not reproducible." >&2
		exit 1
	fi
	echo "check-dist: two builds are byte-identical:"
	(cd "$tmp/first" && find . -type f | sort | xargs shasum -a 256) | sed 's/^/  /'
fi

if [[ ! -d "$dist" ]]; then
	echo "check-dist: $dist/ does not exist. Run: npm run build, then commit $dist/." >&2
	exit 1
fi
if ! diff -rq "$tmp/first" "$dist" >"$tmp/dist.diff"; then
	sed -e "s|$tmp/first|fresh build|g" -e 's/^/  /' "$tmp/dist.diff" >&2
	echo "check-dist: $dist/ does not match a fresh build of assets/src/ (differences above)." >&2
	echo "            Run: npm run build, then commit $dist/. (npm start writes development" >&2
	echo "            builds with source maps; never commit those.)" >&2
	exit 1
fi
echo "check-dist: $dist/ matches a fresh production build."

# Translations survive minification: the text domain and translator comments
# must still be in every admin entry point (WordPress reads each handle's
# strings from its own file).
for area in "${admin_areas[@]}"; do
	if ! grep -q '"zero-spam"' "$dist/admin-$area.js" || ! grep -q 'translators:' "$dist/admin-$area.js"; then
		echo "check-dist: $dist/admin-$area.js lost its zero-spam strings or translator comments." >&2
		exit 1
	fi
done

check_budgets
if [[ $budget_fail -ne 0 ]]; then
	echo "check-dist: over budget (or a built file is missing)." >&2
	exit 1
fi
echo "check-dist: all budgets met."
