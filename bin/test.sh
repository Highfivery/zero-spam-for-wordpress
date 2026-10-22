#!/usr/bin/env bash
#
# Run the PHPUnit suite against a real WordPress install and MySQL database.
#
# Usage: bin/test.sh [options] [-- <extra phpunit arguments>]
#
#   --mode=single|multisite|all   Which install to test (default: single).
#   --suite=<name>                Run one test suite only (unit, integration, multisite).
#   --php=8.2|8.4|/path/to/php    PHP binary (default: the php on PATH).
#   --wp=latest|min               WordPress version from tests/phpunit/versions.env (default: latest).
#   --known-defects               Run only the @group known-defect tests. They fail until the
#                                 defect they document is fixed.
#   --coverage                    Write an HTML coverage report to build/coverage/<mode>/.
#   -h, --help                    Show this help.
#
# Requirements: MySQL on 127.0.0.1 (brew services start mysql). WordPress core
# and the matching test library are downloaded once, checksum-verified, into
# ~/Library/Caches/zero-spam-tests/ (override with ZEROSPAM_TESTS_CACHE).
# Database settings come from WP_TESTS_DB_HOST, WP_TESTS_DB_USER,
# WP_TESTS_DB_PASSWORD and WP_TESTS_DB_NAME. The bootstrap refuses any database
# whose name does not start with zerospam_tests, because installing WordPress
# for the tests drops tables.

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

usage() {
	sed -n '3,22p' "$0" | sed 's/^# \{0,1\}//'
}

mode="single"
suite=""
php_arg=""
wp_arg="latest"
known_defects=0
coverage=0
extra_args=()

while [[ $# -gt 0 ]]; do
	case "$1" in
		--mode=*) mode="${1#*=}" ;;
		--suite=*) suite="${1#*=}" ;;
		--php=*) php_arg="${1#*=}" ;;
		--wp=*) wp_arg="${1#*=}" ;;
		--known-defects) known_defects=1 ;;
		--coverage) coverage=1 ;;
		-h | --help) usage; exit 0 ;;
		--) shift; extra_args=("$@"); break ;;
		*) echo "test.sh: unknown option '$1' (see --help)" >&2; exit 2 ;;
	esac
	shift
done

case "$mode" in
	single | multisite | all) ;;
	*) echo "test.sh: --mode must be single, multisite or all." >&2; exit 2 ;;
esac

# shellcheck source=../tests/phpunit/versions.env
. tests/phpunit/versions.env

if [[ "$(uname -s)" == "Darwin" ]]; then
	cache_dir="${ZEROSPAM_TESTS_CACHE:-$HOME/Library/Caches/zero-spam-tests}"
else
	cache_dir="${ZEROSPAM_TESTS_CACHE:-${XDG_CACHE_HOME:-$HOME/.cache}/zero-spam-tests}"
fi
mkdir -p "$cache_dir"

# ---------------------------------------------------------------------------
# PHP binary.
# ---------------------------------------------------------------------------

php_minor() {
	"$1" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || true
}

resolve_php() {
	local want="$1" candidate
	if [[ -z "$want" ]]; then
		command -v php || { echo "test.sh: no php on PATH." >&2; return 1; }
		return 0
	fi
	if [[ "$want" == */* ]]; then
		[[ -x "$want" ]] || { echo "test.sh: '$want' is not an executable PHP binary." >&2; return 1; }
		echo "$want"
		return 0
	fi
	local candidates=(
		"/opt/homebrew/opt/php@${want}/bin/php"
		"/usr/local/opt/php@${want}/bin/php"
		"/opt/homebrew/bin/php"
		"/usr/local/bin/php"
	)
	command -v "php${want}" >/dev/null 2>&1 && candidates+=("$(command -v "php${want}")")
	# LocalWP ships PHP without a php.ini; take the newest patch release.
	local local_php
	local_php="$(ls -d "$HOME/Library/Application Support/Local/lightning-services/php-${want}."*/bin/darwin-*/bin/php 2>/dev/null | sort -V | tail -1 || true)"
	[[ -n "$local_php" ]] && candidates+=("$local_php")
	for candidate in "${candidates[@]}"; do
		if [[ -x "$candidate" && "$(php_minor "$candidate")" == "$want" ]]; then
			echo "$candidate"
			return 0
		fi
	done
	echo "test.sh: no PHP $want binary found (looked in Homebrew and LocalWP)." >&2
	return 1
}

php_bin="$(resolve_php "$php_arg")"
php_cmd=("$php_bin" -d memory_limit=512M)
php_version="$("$php_bin" -r 'echo PHP_VERSION;')"

# ---------------------------------------------------------------------------
# MySQL.
# ---------------------------------------------------------------------------

export WP_TESTS_DB_HOST="${WP_TESTS_DB_HOST:-127.0.0.1}"
export WP_TESTS_DB_USER="${WP_TESTS_DB_USER:-root}"
export WP_TESTS_DB_PASSWORD="${WP_TESTS_DB_PASSWORD:-}"

if ! "${php_cmd[@]}" -r '
	mysqli_report( MYSQLI_REPORT_OFF );
	$host = getenv( "WP_TESTS_DB_HOST" );
	$port = null;
	if ( preg_match( "/^(.+):(\d+)$/", $host, $m ) ) { $host = $m[1]; $port = (int) $m[2]; }
	$link = @mysqli_connect( $host, getenv( "WP_TESTS_DB_USER" ), getenv( "WP_TESTS_DB_PASSWORD" ), "", $port );
	exit( $link ? 0 : 1 );
'; then
	echo "test.sh: cannot connect to MySQL at ${WP_TESTS_DB_HOST} as ${WP_TESTS_DB_USER}." >&2
	echo "         Start it with: brew services start mysql" >&2
	exit 1
fi

# ---------------------------------------------------------------------------
# WordPress core and test library.
# ---------------------------------------------------------------------------

sha_of() {
	shasum -a "$1" "$2" | awk '{print $1}'
}

download() {
	curl --fail --silent --show-error --location --retry 2 --output "$2" "$1"
}

ensure_core() {
	local version="$1" sha1="$2" dir="$cache_dir/wordpress-$1"
	if [[ -f "$dir/wp-includes/version.php" ]]; then
		echo "$dir"
		return 0
	fi
	local url="https://wordpress.org/wordpress-${version}.tar.gz" tmp published
	tmp="$(mktemp -d "$cache_dir/.download.XXXXXX")"
	echo "test.sh: downloading WordPress ${version}..." >&2
	download "$url" "$tmp/wordpress.tar.gz"
	published="$(curl --fail --silent --show-error --location "${url}.sha1" | tr -d '[:space:]')"
	if [[ "$published" != "$sha1" ]]; then
		echo "test.sh: the published sha1 for WordPress ${version} (${published}) does not match versions.env (${sha1})." >&2
		rm -rf "$tmp"
		return 1
	fi
	if [[ "$(sha_of 1 "$tmp/wordpress.tar.gz")" != "$sha1" ]]; then
		echo "test.sh: checksum mismatch for WordPress ${version}; refusing to use it." >&2
		rm -rf "$tmp"
		return 1
	fi
	tar -xzf "$tmp/wordpress.tar.gz" -C "$tmp"
	rm -rf "$dir"
	mv "$tmp/wordpress" "$dir"
	rm -rf "$tmp"
	echo "$dir"
}

ensure_testlib_release() {
	local version="$1" sha256="$2" dir="$cache_dir/wp-phpunit-$1"
	if [[ -f "$dir/includes/bootstrap.php" ]]; then
		echo "$dir"
		return 0
	fi
	local url="https://github.com/wp-phpunit/wp-phpunit/archive/refs/tags/${version}.tar.gz" tmp
	tmp="$(mktemp -d "$cache_dir/.download.XXXXXX")"
	echo "test.sh: downloading the WordPress test library ${version}..." >&2
	download "$url" "$tmp/wp-phpunit.tar.gz"
	if [[ "$(sha_of 256 "$tmp/wp-phpunit.tar.gz")" != "$sha256" ]]; then
		echo "test.sh: checksum mismatch for wp-phpunit ${version}; refusing to use it." >&2
		rm -rf "$tmp"
		return 1
	fi
	tar -xzf "$tmp/wp-phpunit.tar.gz" -C "$tmp"
	rm -rf "$dir"
	mv "$tmp/wp-phpunit-${version}" "$dir"
	rm -rf "$tmp"
	echo "$dir"
}

ensure_testlib_vendor() {
	local version="$1" installed
	installed="$("${php_cmd[@]}" -r 'require "vendor/autoload.php"; echo \Composer\InstalledVersions::getPrettyVersion( "wp-phpunit/wp-phpunit" );' 2>/dev/null || true)"
	if [[ "$installed" != "$version" ]]; then
		echo "test.sh: vendor/wp-phpunit/wp-phpunit is '${installed:-missing}', expected ${version}. Run: composer install" >&2
		return 1
	fi
	echo "$PWD/vendor/wp-phpunit/wp-phpunit"
}

if [[ ! -x vendor/bin/phpunit ]]; then
	echo "test.sh: vendor/bin/phpunit is missing. Run: composer install" >&2
	exit 1
fi

case "$wp_arg" in
	latest)
		wp_version="$WP_LATEST"
		core_dir="$(ensure_core "$WP_LATEST" "$WP_LATEST_SHA1")"
		testlib_version="$WP_LATEST_TESTLIB"
		testlib_dir="$(ensure_testlib_vendor "$WP_LATEST_TESTLIB")"
		;;
	min)
		wp_version="$WP_MIN"
		core_dir="$(ensure_core "$WP_MIN" "$WP_MIN_SHA1")"
		testlib_version="$WP_MIN_TESTLIB"
		testlib_dir="$(ensure_testlib_release "$WP_MIN_TESTLIB" "$WP_MIN_TESTLIB_SHA256")"
		;;
	*)
		echo "test.sh: --wp must be latest or min." >&2
		exit 2
		;;
esac

export ZEROSPAM_TESTS_CACHE="$cache_dir"
export ZEROSPAM_WP_VERSION="$wp_version"
export ZEROSPAM_WP_CORE_DIR="$core_dir"
export ZEROSPAM_WP_TESTS_DIR="$testlib_dir"

# ---------------------------------------------------------------------------
# Coverage.
# ---------------------------------------------------------------------------

coverage_env=()
if [[ $coverage -eq 1 ]]; then
	driver="$("${php_cmd[@]}" -r 'echo extension_loaded( "pcov" ) ? "pcov" : ( extension_loaded( "xdebug" ) ? "xdebug" : "" );')"
	if [[ -z "$driver" ]]; then
		cat >&2 <<-MSG
		test.sh: no code coverage driver is loaded in PHP ${php_version} (${php_bin}).
		         Install pcov (fast, coverage only) for Homebrew PHP:
		           pecl install pcov
		           php -m | grep pcov
		         then run again. Xdebug also works (XDEBUG_MODE=coverage is set for you).
		MSG
		exit 1
	fi
	if [[ "$driver" == "pcov" ]]; then
		php_cmd+=(-d pcov.enabled=1 -d "pcov.directory=$PWD")
	else
		coverage_env=(XDEBUG_MODE=coverage)
	fi
fi

# ---------------------------------------------------------------------------
# Run.
# ---------------------------------------------------------------------------

run_mode() {
	local run="$1" config args=() started status
	if [[ "$run" == "multisite" ]]; then
		config="phpunit-multisite.xml.dist"
	else
		config="phpunit.xml.dist"
	fi
	args=(--configuration "$config")
	[[ -n "$suite" ]] && args+=(--testsuite "$suite")
	[[ $known_defects -eq 1 ]] && args+=(--group known-defect)
	if [[ $coverage -eq 1 ]]; then
		args+=(--coverage-html "build/coverage/$run" --coverage-text)
	fi

	printf '\n\033[1m==> PHPUnit (%s): PHP %s, WordPress %s, test library %s\033[0m\n' \
		"$run" "$php_version" "$wp_version" "$testlib_version"
	started=$(date +%s)
	set +e
	env ${coverage_env[@]+"${coverage_env[@]}"} "${php_cmd[@]}" vendor/bin/phpunit "${args[@]}" ${extra_args[@]+"${extra_args[@]}"}
	status=$?
	set -e
	printf 'test.sh: %s run finished in %ss (exit %s).\n' "$run" "$(( $(date +%s) - started ))" "$status"
	return $status
}

overall=0
if [[ "$mode" == "single" || "$mode" == "all" ]]; then
	run_mode single || overall=1
fi
if [[ "$mode" == "multisite" || "$mode" == "all" ]]; then
	run_mode multisite || overall=1
fi

exit $overall
