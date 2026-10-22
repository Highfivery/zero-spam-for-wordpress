#!/usr/bin/env bash
#
# Verify the three places the plugin version lives agree with each other and
# never go backwards. In March 2026 a release shipped as 5.5.8 instead of
# 5.7.8, so sites on 5.7.7 were never offered the update. This guards that.
#
#   - "Version:" in the wordpress-zero-spam.php plugin header
#   - ZEROSPAM_VERSION constant in wordpress-zero-spam.php
#   - "Stable tag:" in readme.txt
#   - the newest = vX.Y.Z = entry in the readme.txt changelog
#
# The versions must also be >= the highest vX.Y.Z git tag that is reachable.

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

header="$(sed -nE 's/^ \* Version:[[:space:]]+([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' wordpress-zero-spam.php | head -1)"
constant="$(sed -nE "s/^define\( 'ZEROSPAM_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)' \);.*/\1/p" wordpress-zero-spam.php | head -1)"
stable="$(sed -nE 's/^Stable tag:[[:space:]]+([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' readme.txt | head -1)"
changelog="$(sed -nE 's/^= v?([0-9]+\.[0-9]+\.[0-9]+) =.*/\1/p' readme.txt | head -1)"

fail=0
report() { printf '  %-32s %s\n' "$1" "${2:-<not found>}"; }

report "plugin header Version:" "$header"
report "ZEROSPAM_VERSION constant:" "$constant"
report "readme.txt Stable tag:" "$stable"
report "newest readme.txt changelog:" "$changelog"

for v in "$header" "$constant" "$stable" "$changelog"; do
	if [[ -z "$v" ]]; then
		echo "check-version: a version string could not be read." >&2
		fail=1
	fi
done

if [[ $fail -eq 0 ]]; then
	if [[ "$header" != "$constant" || "$header" != "$stable" || "$header" != "$changelog" ]]; then
		echo "check-version: the versions above do not match." >&2
		fail=1
	fi
fi

# Highest reachable release tag, if any.
latest_tag="$(git tag --list 'v[0-9]*' --merged HEAD 2>/dev/null | sed 's/^v//' | sort -V | tail -1 || true)"
if [[ $fail -eq 0 && -n "$latest_tag" ]]; then
	highest="$(printf '%s\n%s\n' "$latest_tag" "$header" | sort -V | tail -1)"
	report "highest reachable release tag:" "v$latest_tag"
	if [[ "$highest" != "$header" ]]; then
		echo "check-version: version $header is lower than the already released v$latest_tag." >&2
		fail=1
	fi
fi

if [[ $fail -ne 0 ]]; then
	exit 1
fi

echo "check-version: OK ($header)"
