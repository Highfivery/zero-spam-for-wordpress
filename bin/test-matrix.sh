#!/usr/bin/env bash
#
# Run the PHPUnit suite across the supported matrix and check the readme.txt
# WordPress headers against the versions tested.
#
#   PHP (PHP_MATRIX in tests/phpunit/versions.env) x WordPress {min, latest}
#   x {single, multisite}
#
# Each run's full output is written to build/test-matrix/<run>.log. Extra
# arguments are passed to every bin/test.sh run (for example --known-defects).
#
# Usage: bin/test-matrix.sh [bin/test.sh options]

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

# shellcheck source=../tests/phpunit/versions.env
. tests/phpunit/versions.env

log_dir="build/test-matrix"
mkdir -p "$log_dir"

rows=()
failed=0
started_all=$(date +%s)

for php in $PHP_MATRIX; do
	for wp in min latest; do
		for mode in single multisite; do
			name="php-${php}_wp-${wp}_${mode}"
			printf 'test-matrix: PHP %s, WordPress %s, %s ... ' "$php" "$wp" "$mode"
			started=$(date +%s)
			if bin/test.sh --php="$php" --wp="$wp" --mode="$mode" "$@" >"$log_dir/$name.log" 2>&1; then
				result="pass"
			else
				result="FAIL"
				failed=1
			fi
			elapsed=$(( $(date +%s) - started ))
			summary="$(grep -E '^(OK \(|Tests: )' "$log_dir/$name.log" | tail -1 || true)"
			php_version="$(sed -nE 's/.*PHPUnit \([a-z]+\): PHP ([0-9.]+), WordPress ([0-9.]+).*/\1 \2/p' "$log_dir/$name.log" | head -1)"
			echo "$result (${elapsed}s)"
			rows+=("$(printf '%-6s %-9s %-10s %-9s %-5s %5ss  %s' "${php_version%% *}" "${php_version##* }" "$wp" "$mode" "$result" "$elapsed" "${summary:-see $log_dir/$name.log}")")
		done
	done
done

echo
printf '%-6s %-9s %-10s %-9s %-5s %6s  %s\n' "PHP" "WordPress" "Target" "Mode" "Result" "Time" "Summary"
for row in "${rows[@]}"; do
	echo "$row"
done
echo "test-matrix: ${#rows[@]} runs in $(( $(date +%s) - started_all ))s; logs in $log_dir/"

# readme.txt headers must match what was just tested (major.minor).
major_minor() { echo "$1" | cut -d. -f1-2; }
tested_up_to="$(sed -nE 's/^Tested up to:[[:space:]]*([0-9.]+).*/\1/p' readme.txt | head -1)"
requires_at_least="$(sed -nE 's/^Requires at least:[[:space:]]*([0-9.]+).*/\1/p' readme.txt | head -1)"

echo
if [[ "$(major_minor "$tested_up_to")" == "$(major_minor "$WP_LATEST")" ]]; then
	echo "test-matrix: readme.txt Tested up to ${tested_up_to} matches WP_LATEST ${WP_LATEST}: OK"
else
	echo "test-matrix: readme.txt Tested up to ${tested_up_to:-<missing>} does not match WP_LATEST ${WP_LATEST} (major.minor)." >&2
	failed=1
fi
if [[ "$(major_minor "$requires_at_least")" == "$(major_minor "$WP_MIN")" ]]; then
	echo "test-matrix: readme.txt Requires at least ${requires_at_least} matches WP_MIN ${WP_MIN}: OK"
else
	echo "test-matrix: readme.txt Requires at least ${requires_at_least:-<missing>} does not match WP_MIN ${WP_MIN} (major.minor)." >&2
	failed=1
fi

exit $failed
