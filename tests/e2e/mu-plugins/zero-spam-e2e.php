<?php
/**
 * Plugin Name: Zero Spam E2E helpers
 * Description: Test-only helpers for the Zero Spam Playwright suite. Never shipped (tests/ is in .distignore). Inert unless ZEROSPAM_E2E_TOKEN is defined in wp-config.php.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 *
 * What it does when ZEROSPAM_E2E_TOKEN is defined:
 *
 * - While stubs are active (see stubs_active(): every token request, every
 *   request on a disposable wp-env site, and on Local every request while a
 *   run is in progress), blocks every outgoing HTTP request to a host other
 *   than this site, answers the services Zero Spam talks to with canned JSON
 *   and reports "no updates" for core, plugins and themes. Outside a run a
 *   Local site behaves normally.
 * - For requests that carry the matching X-ZeroSpam-E2E-Token header only:
 *   uses X-ZeroSpam-E2E-IP as the visitor IP (TEST-NET addresses only; any
 *   other address is refused with a 400, so a test can never block the
 *   developer's own IP), lifts the comment flood limit, skips outgoing mail,
 *   and records PHP notices, warnings, deprecations, fatal errors and
 *   _doing_it_wrong() calls raised from Zero Spam's own files.
 * - Registers REST routes under zero-spam-e2e/v1 for resets, seeding and
 *   inspection. Every route needs the token header; all but /ping also need
 *   manage_options (network routes manage_network_options). Routes that take
 *   an IP accept TEST-NET addresses only.
 *
 * Its own state lives in options prefixed zs_e2e_ so it never shows up in the
 * uninstall test's search for zero-spam-*, zerospam_* and wpzerospam* data.
 *
 * @package ZeroSpam\Tests\E2E
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests\E2E;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ZEROSPAM_E2E_TOKEN' ) || ! is_string( ZEROSPAM_E2E_TOKEN ) || '' === ZEROSPAM_E2E_TOKEN ) {
	return;
}

const HTTP_CALLS_OPTION = 'zs_e2e_http_calls';
const ERRORS_OPTION     = 'zs_e2e_errors';
const MAILS_OPTION      = 'zs_e2e_mails';
const RUN_OPTION        = 'zs_e2e_run_active';
const MAX_RECORDS       = 200;
// A run that never reaches its restore teardown (Ctrl-C, crash) stops stubbing after this long.
const RUN_MAX_SECONDS = 3 * HOUR_IN_SECONDS;
// Snapshots refuse tables larger than this instead of silently keeping part of them.
const SNAPSHOT_MAX_ROWS = 50000;
const SNAPSHOT_VERSION  = 2;

/**
 * Whether the current request carries the matching token header.
 *
 * @return bool
 */
function is_token_request(): bool {
	static $result = null;

	if ( null === $result ) {
		$header = isset( $_SERVER['HTTP_X_ZEROSPAM_E2E_TOKEN'] ) && is_string( $_SERVER['HTTP_X_ZEROSPAM_E2E_TOKEN'] )
			? wp_unslash( $_SERVER['HTTP_X_ZEROSPAM_E2E_TOKEN'] )
			: '';
		$result = '' !== $header && hash_equals( ZEROSPAM_E2E_TOKEN, $header );
	}

	return $result;
}

/**
 * Whether an address is in a documentation range (TEST-NET-1/2/3,
 * 192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, or IPv6 2001:db8::/32).
 * Only those may be used as fake visitor IPs, blocked or logged, so a test
 * can never block a real visitor or the developer.
 *
 * @param string $ip Address.
 * @return bool
 */
function is_test_net( string $ip ): bool {
	if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	$packed = inet_pton( $ip );
	if ( false === $packed ) {
		return false;
	}

	if ( 4 === strlen( $packed ) ) {
		return in_array( substr( $packed, 0, 3 ), array( "\xC0\x00\x02", "\xC6\x33\x64", "\xCB\x00\x71" ), true );
	}

	return str_starts_with( $packed, "\x20\x01\x0D\xB8" );
}

/**
 * Whether the network stubs (canned HTTP answers, "no updates") apply to the
 * current request: every token request; every request on a disposable wp-env
 * site (ZEROSPAM_E2E_DISPOSABLE); on Local, every request while a run is in
 * progress (the setup project sets zs_e2e_run_active, the restore teardown
 * clears it, and it expires after RUN_MAX_SECONDS). Outside a run a Local site
 * talks to WordPress.org and Zero Spam's services as usual.
 *
 * @return bool
 */
function stubs_active(): bool {
	static $active = null;

	if ( null === $active ) {
		if ( is_token_request() || ( defined( 'ZEROSPAM_E2E_DISPOSABLE' ) && ZEROSPAM_E2E_DISPOSABLE ) ) {
			$active = true;
		} else {
			$until  = (int) ( is_multisite() ? get_site_option( RUN_OPTION, 0 ) : get_option( RUN_OPTION, 0 ) );
			$active = $until > time();
		}
	}

	return $active;
}

/**
 * Appends a record to one of the bounded zs_e2e_* log options.
 *
 * @param string $option Option name.
 * @param array  $entry  Record.
 */
function append_record( string $option, array $entry ): void {
	$records   = read_records( $option );
	$records[] = $entry;
	if ( count( $records ) > MAX_RECORDS ) {
		$records = array_slice( $records, -MAX_RECORDS );
	}

	if ( is_multisite() ) {
		update_site_option( $option, $records );
	} else {
		update_option( $option, $records, false );
	}
}

/**
 * Reads one of the zs_e2e_* logs (network-wide on multisite, so a subsite's
 * records are visible from any site).
 *
 * @param string $option Option name.
 * @return array
 */
function read_records( string $option ): array {
	$records = is_multisite() ? get_site_option( $option, array() ) : get_option( $option, array() );

	return is_array( $records ) ? array_values( $records ) : array();
}

/**
 * Clears one of the zs_e2e_* logs.
 *
 * @param string $option Option name.
 */
function clear_records( string $option ): void {
	if ( is_multisite() ) {
		delete_site_option( $option );
	} else {
		delete_option( $option );
	}
}

/*
 * ---------------------------------------------------------------------------
 * Outgoing HTTP: canned answers for Zero Spam's services, nothing else leaves.
 * ---------------------------------------------------------------------------
 */

/**
 * The canned zerospam.org article feed (RSS), so the news tile, the sidebar
 * and the Help news have the same eight articles on every run. Fixed dates;
 * "Incident Response" marks a security notice (News_Feed::SECURITY_CATEGORY).
 *
 * @return string RSS.
 */
function canned_feed(): string {
	$articles = array(
		array( 'AI Is Finding Critical Bugs Fast: Is Your Website Ready?', 'ai-found-vulnerabilities-website-owners', 'Fri, 23 Oct 2026 09:00:00 +0000', 'Site Security', 'AI tools found PHP2Shell and a Node.js flaw this month. What that means for site owners, and a plan for the next patch emergency.' ),
		array( 'PHP2Shell Explained: What to Do Now (Live Updates)', 'php2shell-php-vulnerability-live-updates', 'Thu, 22 Oct 2026 15:00:00 +0000', 'Incident Response', 'A pair of critical PHP flaws lets attackers run code without logging in. Who is affected, and what to do, updated daily.' ),
		array( 'A Fake Store Every 3 Minutes: How to Protect Your Brand', 'fake-online-stores-brand-clones', 'Thu, 22 Oct 2026 09:00:00 +0000', 'Site Security', 'Fake online stores copying real brands appear about every 3 minutes. How to find clones and report them.' ),
		array( 'How to Stop Calendar Invite Phishing (2026)', 'stop-calendar-invite-phishing', 'Wed, 21 Oct 2026 09:00:00 +0000', 'Site Security', 'Fake meeting invites can land on your calendar even when the email is filtered. How to lock it down.' ),
		array( 'Meta’s Muse AI Agent Is Filling Out Your Website Forms', 'meta-muse-ai-agent-website-forms', 'Tue, 20 Oct 2026 09:00:00 +0000', 'Spam Protection', 'An AI agent is hitting forms from cloud IPs without naming itself. Five decisions site owners should make now.' ),
		array( 'Rank Math PRO 3.0.123 Malware: Check Your Update Time', 'rank-math-pro-3-0-123-malware', 'Mon, 19 Oct 2026 09:00:00 +0000', 'Incident Response', 'Some downloads carried malware in an 11-hour window on Oct 15–16. How to tell if your site got one.' ),
		array( 'Ghostscript CVE-2026-39919: Check WordPress PDF Uploads', 'ghostscript-cve-2026-39919-wordpress-pdf-uploads', 'Wed, 14 Oct 2026 09:00:00 +0000', 'Incident Response', 'Crafted PDFs can corrupt memory when servers make thumbnails. How to check your uploads.' ),
		array( 'Google Flags Fake Author Profiles: 5 Checks for Your Site', 'google-fake-author-profiles-ai-content', 'Mon, 12 Oct 2026 09:00:00 +0000', 'Spam Protection', 'Google now treats fake author profiles as deception. What changed, and five checks for your site.' ),
	);

	$items = '';
	foreach ( $articles as $a ) {
		$items .= sprintf(
			'<item><title>%1$s</title><link>https://www.zerospam.org/%2$s/</link><pubDate>%3$s</pubDate><category><![CDATA[%4$s]]></category><description><![CDATA[%5$s]]></description></item>',
			esc_xml( $a[0] ),
			$a[1],
			$a[2],
			$a[3],
			$a[4]
		);
	}

	return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Zero Spam</title><link>https://www.zerospam.org/articles/</link>' . $items . '</channel></rss>';
}

/**
 * Whether a test made the zerospam.org feed fail (route `/news`).
 *
 * @return bool
 */
function news_down(): bool {
	return (bool) ( is_multisite() ? get_site_option( 'zs_e2e_news_down' ) : get_option( 'zs_e2e_news_down' ) );
}

/**
 * Canned responses keyed by host (and path fragment).
 *
 * The license answer is a valid Business license (what a site on Enhanced
 * Protection sees); a site is on Basic until a test saves a key. The key
 * `e2e-invalid` is answered as unknown (an invalid key).
 *
 * @param string $host  Request host.
 * @param string $path  Request path.
 * @param string $query Request query string.
 * @return array|string|null Body array (sent as JSON), raw body string, or null when the host is not a known service.
 */
function canned_body( string $host, string $path, string $query = '' ) {
	if ( str_ends_with( $host, 'zerospam.org' ) ) {
		if ( str_contains( $path, 'get-license' ) && str_contains( $query, 'license_key=e2e-invalid' ) ) {
			return array( 'error' => 'invalid_license' );
		}
		if ( str_contains( $path, 'get-license' ) ) {
			return array(
				'license_key'         => 'e2e-license',
				'product_id'          => 'ZEROSPAMBUSINESS',
				'queries_limit'       => 50000,
				'queries_made'        => 31200,
				'queries_remaining'   => 18800,
				'subscription_status' => 'active',
			);
		}
		if ( str_contains( $path, 'v3/query' ) ) {
			// The shape Zero_Spam::query() reads: a 200 status and a body_response.
			return array(
				'status'        => 200,
				'body_response' => array( 'ip_addresses' => array() ),
			);
		}
		if ( str_contains( $path, '/articles/feed' ) ) {
			return canned_feed();
		}
		return array( 'status' => 'success' );
	}

	if ( 'api.stopforumspam.org' === $host ) {
		return array(
			'success' => 1,
			'ip'      => array(
				'appears'   => 0,
				'frequency' => 0,
			),
		);
	}

	if ( in_array( $host, array( 'api.ipstack.com', 'ipinfo.io', 'api.ipinfo.io', 'api.ipbase.com' ), true ) ) {
		return array();
	}

	// WordPress.org update checks: "nothing new", so core never prints a
	// "could not establish a secure connection" warning into the page.
	if ( 'api.wordpress.org' === $host ) {
		if ( str_contains( $path, '/plugins/update-check' ) ) {
			return array(
				'plugins'      => array(),
				'translations' => array(),
				'no_update'    => array(),
			);
		}
		if ( str_contains( $path, '/themes/update-check' ) ) {
			return array(
				'themes'       => array(),
				'translations' => array(),
				'no_update'    => array(),
			);
		}
		if ( str_contains( $path, '/core/version-check' ) ) {
			return array(
				'offers'       => array(),
				'translations' => array(),
			);
		}
		return array();
	}

	return null;
}

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( false !== $preempt || ! stubs_active() ) {
			return $preempt;
		}

		$host      = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		$path      = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( '' === $host || $host === $site_host || in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return $preempt;
		}

		// The news is "down" (Help's news-unavailable screen): the feed request fails.
		if ( str_ends_with( $host, 'zerospam.org' ) && str_contains( $path, '/articles/feed' ) && news_down() ) {
			return new WP_Error( 'zs_e2e_news_down', 'Zero Spam e2e: the news feed is down for this test.' );
		}

		$body = canned_body( $host, $path, (string) wp_parse_url( (string) $url, PHP_URL_QUERY ) );

		append_record(
			HTTP_CALLS_OPTION,
			array(
				'time'    => time(),
				'method'  => isset( $args['method'] ) ? (string) $args['method'] : 'GET',
				'host'    => $host,
				'path'    => $path,
				'answer'  => null === $body ? 'blocked' : 'canned',
				'request' => is_token_request(),
			)
		);

		if ( null === $body ) {
			return new WP_Error( 'zs_e2e_blocked', sprintf( 'Zero Spam e2e: outgoing request to %s blocked.', $host ) );
		}

		return array(
			'headers'  => array( 'content-type' => is_string( $body ) ? 'application/rss+xml; charset=UTF-8' : 'application/json' ),
			'body'     => is_string( $body ) ? $body : (string) wp_json_encode( $body ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	PHP_INT_MAX,
	3
);

/**
 * Update-check transients that say "checked just now, nothing new", so admin
 * screens never try to reach WordPress.org and never print the resulting
 * "could not establish a secure connection" warnings into the page.
 *
 * @return object
 */
function no_updates(): object {
	return (object) array(
		'last_checked'    => time(),
		'version_checked' => get_bloginfo( 'version' ),
		'updates'         => array(),
		'response'        => array(),
		'translations'    => array(),
		'checked'         => array(),
		'no_update'       => array(),
	);
}

foreach ( array( 'update_core', 'update_plugins', 'update_themes' ) as $zs_e2e_transient ) {
	add_filter(
		'pre_site_transient_' . $zs_e2e_transient,
		static function ( $pre ) {
			return stubs_active() ? no_updates() : $pre;
		},
		PHP_INT_MAX
	);
}
unset( $zs_e2e_transient );

/*
 * ---------------------------------------------------------------------------
 * Per-request behaviour for token requests only.
 * ---------------------------------------------------------------------------
 */

/**
 * The X-ZeroSpam-E2E-IP header of a token request ('' when absent).
 *
 * @return string
 */
function fake_ip_header(): string {
	if ( ! is_token_request() || ! isset( $_SERVER['HTTP_X_ZEROSPAM_E2E_IP'] ) || ! is_string( $_SERVER['HTTP_X_ZEROSPAM_E2E_IP'] ) ) {
		return '';
	}

	return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ZEROSPAM_E2E_IP'] ) ) );
}

/*
 * A token request with a fake IP outside TEST-NET is refused before any
 * plugin loads, so a test can never act as (and block) a real address.
 */
if ( '' !== fake_ip_header() && ! is_test_net( fake_ip_header() ) ) {
	status_header( 400 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo esc_html( 'Zero Spam e2e: X-ZeroSpam-E2E-IP must be a TEST-NET address (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32).' );
	exit;
}

add_filter(
	'zerospam_get_ip',
	static function ( $ip ) {
		$fake = fake_ip_header();

		return '' !== $fake && is_test_net( $fake ) ? $fake : $ip;
	},
	PHP_INT_MAX
);

/*
 * The Enhanced Protection promo notice is plugin UI, but showing it on every
 * admin screen would put it into every screenshot. Tests that want it set the
 * zs_e2e_show_promo option (reset route, "promo": true).
 */
add_filter(
	'zerospam_display_promo_notice',
	static function ( $display ) {
		if ( ! is_token_request() ) {
			return $display;
		}

		return $display && (bool) ( is_multisite() ? get_site_option( 'zs_e2e_show_promo' ) : get_option( 'zs_e2e_show_promo' ) );
	},
	PHP_INT_MAX
);

add_filter(
	'comment_flood_filter',
	static function ( $flood ) {
		return is_token_request() ? false : $flood;
	},
	PHP_INT_MAX
);

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		if ( ! is_token_request() ) {
			return $short_circuit;
		}

		append_record(
			MAILS_OPTION,
			array(
				'time'    => time(),
				'to'      => $atts['to'] ?? '',
				'subject' => $atts['subject'] ?? '',
			)
		);

		return true;
	},
	PHP_INT_MAX,
	2
);

/*
 * ---------------------------------------------------------------------------
 * PHP error collector (works with WP_DEBUG off, as on Local).
 * ---------------------------------------------------------------------------
 */

/**
 * Real path of the Zero Spam plugin directory, with a trailing slash.
 *
 * @return string
 */
function plugin_dir(): string {
	static $dir = null;

	if ( null === $dir ) {
		$candidate = defined( 'ZEROSPAM_PATH' ) ? ZEROSPAM_PATH : WP_PLUGIN_DIR . '/zero-spam/';
		$real      = realpath( $candidate );
		$dir       = trailingslashit( false === $real ? $candidate : $real );
	}

	return $dir;
}

/**
 * Whether a file belongs to Zero Spam (and not to this helper).
 *
 * @param string $file File path.
 * @return bool
 */
function is_plugin_file( string $file ): bool {
	if ( '' === $file || __FILE__ === $file ) {
		return false;
	}

	$real = realpath( $file );
	$real = false === $real ? $file : $real;
	$dir  = plugin_dir();

	// The plugin's own test code and Composer tooling are not the plugin.
	return str_starts_with( $real, $dir ) && ! str_starts_with( $real, $dir . 'tests/' ) && ! str_starts_with( $real, $dir . 'vendor/' );
}

/**
 * Finds the nearest non-core frame of the current stack.
 *
 * @return string File of that frame, or an empty string.
 */
function nearest_caller(): string {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Attributing notices to the plugin is the purpose of this test helper.
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
		$file = isset( $frame['file'] ) ? (string) $frame['file'] : '';
		if ( '' === $file || __FILE__ === $file || str_contains( $file, '/wp-includes/' ) || str_contains( $file, '/wp-admin/' ) ) {
			continue;
		}
		return $file;
	}

	return '';
}

/**
 * Records one plugin error for the current request.
 *
 * @param string $kind    Kind (notice, warning, deprecated, doing_it_wrong…).
 * @param string $message Message.
 * @param string $file    File.
 * @param int    $line    Line.
 */
function record_error( string $kind, string $message, string $file, int $line ): void {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	append_record(
		ERRORS_OPTION,
		array(
			'time'    => time(),
			'kind'    => $kind,
			'message' => wp_strip_all_tags( $message ),
			'file'    => str_replace( plugin_dir(), '', $file ),
			'line'    => $line,
			'uri'     => $uri,
		)
	);
}

if ( is_token_request() ) {
	$zs_e2e_previous_handler = null;

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Collects notices from the plugin under test; chains to any previous handler.
	$zs_e2e_previous_handler = set_error_handler(
		static function ( int $errno, string $errstr, string $errfile = '', int $errline = 0 ) use ( &$zs_e2e_previous_handler ) {
			// 4437 is error_reporting() inside an @-silenced call on PHP 8; this only reads the level.
			if ( 4437 !== error_reporting() && is_plugin_file( $errfile ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Reads the level to skip @-silenced calls; changes nothing.
				$kinds = array(
					E_WARNING         => 'warning',
					E_NOTICE          => 'notice',
					E_DEPRECATED      => 'deprecated',
					E_USER_WARNING    => 'warning',
					E_USER_NOTICE     => 'notice',
					E_USER_DEPRECATED => 'deprecated',
				);
				record_error( $kinds[ $errno ] ?? 'error', $errstr, $errfile, $errline );
			}

			if ( is_callable( $zs_e2e_previous_handler ) ) {
				return (bool) call_user_func( $zs_e2e_previous_handler, $errno, $errstr, $errfile, $errline );
			}

			// Let PHP's own handler display/log it as configured.
			return false;
		}
	);

	$zs_e2e_hooks = array(
		'doing_it_wrong_run'       => 'doing_it_wrong',
		'deprecated_function_run'  => 'deprecated_function',
		'deprecated_argument_run'  => 'deprecated_argument',
		'deprecated_hook_run'      => 'deprecated_hook',
		'deprecated_file_included' => 'deprecated_file',
	);

	foreach ( $zs_e2e_hooks as $zs_e2e_hook => $zs_e2e_kind ) {
		add_action(
			$zs_e2e_hook,
			static function ( $name = '', $detail = '' ) use ( $zs_e2e_kind ) {
				$file = nearest_caller();
				if ( is_plugin_file( $file ) ) {
					record_error( $zs_e2e_kind, trim( $name . ' ' . ( is_string( $detail ) ? $detail : '' ) ), $file, 0 );
				}
			},
			10,
			2
		);
	}

	unset( $zs_e2e_hooks, $zs_e2e_hook, $zs_e2e_kind );

	// Fatal errors never reach an error handler; record them on shutdown.
	register_shutdown_function(
		static function () {
			$error = error_get_last();
			if ( null === $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
				return;
			}
			if ( is_plugin_file( (string) $error['file'] ) ) {
				record_error( 'fatal', (string) $error['message'], (string) $error['file'], (int) $error['line'] );
			}
		}
	);
}

/*
 * ---------------------------------------------------------------------------
 * REST routes.
 * ---------------------------------------------------------------------------
 */

/**
 * Permission callback: token header plus a capability.
 *
 * @param string $capability Capability.
 * @return callable
 */
function permission( string $capability ): callable {
	return static function () use ( $capability ) {
		if ( ! is_token_request() ) {
			return new WP_Error( 'zs_e2e_forbidden', 'Missing or wrong X-ZeroSpam-E2E-Token header.', array( 'status' => 403 ) );
		}

		if ( '' === $capability ) {
			return true;
		}

		return current_user_can( $capability )
			? true
			: new WP_Error( 'zs_e2e_forbidden', 'Insufficient capability: ' . $capability, array( 'status' => 403 ) );
	};
}

/**
 * Option name prefixes that belong to Zero Spam.
 *
 * @return string[] LIKE patterns.
 */
function plugin_option_patterns(): array {
	return array( 'zero-spam%', 'zerospam%', 'wpzerospam%', 'zero_spam%', '_transient_zerospam%', '_transient_timeout_zerospam%', '_transient_zero_spam%', '_transient_timeout_zero_spam%', '_site_transient_zerospam%', '_site_transient_timeout_zerospam%' );
}

/**
 * Whether an option name matches one of plugin_option_patterns() (with the
 * same meaning as SQL LIKE: % is any run of characters, _ any one character).
 *
 * @param string $name Option name.
 * @return bool
 */
function is_plugin_option_name( string $name ): bool {
	foreach ( plugin_option_patterns() as $pattern ) {
		$regex = '/^' . str_replace( array( '%', '_' ), array( '.*', '.' ), preg_quote( $pattern, '/' ) ) . '$/s';
		if ( 1 === preg_match( $regex, $name ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Plugin-owned options of the current site with their autoload values.
 *
 * @return array<string, array{value: mixed, autoload: string}>
 */
function site_plugin_option_rows(): array {
	global $wpdb;

	$found = array();
	foreach ( plugin_option_patterns() as $pattern ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT option_name, option_value, autoload FROM %i WHERE option_name LIKE %s', $wpdb->options, $pattern ),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$found[ $row['option_name'] ] = array(
				'value'    => maybe_unserialize( $row['option_value'] ),
				'autoload' => (string) $row['autoload'],
			);
		}
	}

	ksort( $found );

	return $found;
}

/**
 * Plugin-owned options of the current site (names and values).
 *
 * @param string $search Optional substring filter.
 * @return array<string, mixed>
 */
function site_plugin_options( string $search = '' ): array {
	$found = array();
	foreach ( site_plugin_option_rows() as $name => $row ) {
		if ( '' === $search || str_contains( $name, $search ) ) {
			$found[ $name ] = $row['value'];
		}
	}

	return $found;
}

/**
 * Plugin-owned network (sitemeta) options.
 *
 * @return array<string, mixed>
 */
function network_plugin_options(): array {
	global $wpdb;

	if ( ! is_multisite() ) {
		return array();
	}

	$found = array();
	foreach ( plugin_option_patterns() as $pattern ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT meta_key, meta_value FROM %i WHERE meta_key LIKE %s', $wpdb->sitemeta, $pattern ),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$found[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] );
		}
	}

	ksort( $found );

	return $found;
}

/**
 * Plugin tables that exist in the database.
 *
 * @return string[]
 */
function plugin_tables(): array {
	global $wpdb;

	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', '%wpzerospam%' ) );
	sort( $tables );

	return array_values( (array) $tables );
}

/**
 * Whether a table name is one of this install's Zero Spam tables: per-site
 * {prefix}wpzerospam_* (any site of a network) or network-wide
 * {base_prefix}wpzerospam_*.
 *
 * @param string $table Table name.
 * @return bool
 */
function is_install_plugin_table( string $table ): bool {
	global $wpdb;

	return 1 === preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(\d+_)?wpzerospam_[a-z0-9_]+$/', $table );
}

/**
 * This install's Zero Spam tables (every site and the network ones).
 *
 * @return string[]
 */
function install_plugin_tables(): array {
	global $wpdb;

	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%wpzerospam\_%' ) );
	$tables = array_values( array_filter( (array) $tables, __NAMESPACE__ . '\is_install_plugin_table' ) );
	sort( $tables );

	return $tables;
}

/**
 * Maps a stored autoload value to the argument add_option() takes.
 *
 * @param string $autoload Stored value (on, off, auto, auto-on, auto-off, yes, no).
 * @return bool|null
 */
function autoload_argument( string $autoload ): ?bool {
	if ( in_array( $autoload, array( 'yes', 'on', 'auto-on' ), true ) ) {
		return true;
	}
	if ( in_array( $autoload, array( 'no', 'off', 'auto-off' ), true ) ) {
		return false;
	}

	return null;
}

/**
 * Plugin cron hooks that are scheduled.
 *
 * @return string[]
 */
function plugin_cron_hooks(): array {
	$hooks = array();
	foreach ( (array) _get_cron_array() as $events ) {
		foreach ( (array) $events as $hook => $unused ) {
			if ( preg_match( '/^(zerospam|zero_spam|wpzerospam)/', (string) $hook ) ) {
				$hooks[] = (string) $hook;
			}
		}
	}

	$hooks = array_values( array_unique( $hooks ) );
	sort( $hooks );

	return $hooks;
}

/**
 * Markup the crafted seed writes into log rows; it sets window.__zsXss if it ever runs.
 */
const CRAFTED_MARKUP = '<img src="x" onerror="window.__zsXss=1"><b>zs-crafted</b>';

/**
 * Resets the current site's plugin state.
 *
 * @param string $preset defaults|recommended|baseline.
 */
function reset_site( string $preset ): void {
	global $wpdb;

	$keep = array( 'zerospam_db_version', 'zerospam_completed_migrations', 'zerospam_davidwalsh_secret', 'wpzerospam_honeypot' );

	foreach ( array_keys( site_plugin_options() ) as $name ) {
		if ( in_array( $name, $keep, true ) ) {
			continue;
		}
		if ( str_starts_with( $name, '_transient_timeout_' ) ) {
			continue;
		}
		if ( str_starts_with( $name, '_transient_' ) ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
			continue;
		}
		delete_option( $name );
	}

	foreach ( array( 'wpzerospam_log', 'wpzerospam_blocked' ) as $table ) {
		$full = $wpdb->prefix . $table;
		if ( $full === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) ) {
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $full ) );
		}
	}

	// Past the plugin's three-day wait, so whether the promo notice shows
	// depends only on the zerospam_display_promo_notice filter below.
	update_option( 'zerospam_activation_time', time() - ( 4 * DAY_IN_SECONDS ) );
	delete_option( 'zerospam_show_settings_review_notice' );

	wp_cache_flush();

	if ( 'defaults' === $preset || ! class_exists( '\ZeroSpam\Core\Settings' ) ) {
		return;
	}

	\ZeroSpam\Core\Settings::auto_configure();

	if ( 'baseline' !== $preset ) {
		return;
	}

	$baseline = array(
		'zerospam'                  => false,
		'stop_forum_spam'           => false,
		'project_honeypot'          => false,
		'share_data'                => false,
		'davidwalsh'                => 'enabled',
		'verify_comments'           => 'enabled',
		'log_blocked_comments'      => 'enabled',
		'verify_login'              => 'enabled',
		'log_blocked_logins'        => 'enabled',
		'verify_registrations'      => 'enabled',
		'log_blocked_registrations' => 'enabled',
		'log_blocked_ips'           => 'enabled',
		'block_handler'             => '403',
		'widget_enabled'            => 'enabled',
		'widget_visibility'         => array( 'administrator' ),
	);

	foreach ( $baseline as $key => $value ) {
		\ZeroSpam\Core\Utilities::update_setting( $key, $value );
	}

	wp_cache_flush();
}

/**
 * Site settings outside the plugin that the suite changes on Local.
 *
 * @return string[]
 */
function touched_core_options(): array {
	return array( 'users_can_register', 'default_role' );
}

/**
 * Captures what a Local run may change, so the restore teardown can put it
 * back: every site's plugin options (with their autoload values), the core
 * options in touched_core_options(), the plugin's error log, the network's
 * plugin options, and every Zero Spam table of the install (schema and rows;
 * uninstall drops them). A table above SNAPSHOT_MAX_ROWS rows is refused
 * rather than captured in part.
 *
 * @return array|WP_Error
 */
function take_snapshot() {
	global $wpdb;

	$tables = array();
	foreach ( install_plugin_tables() as $table ) {
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		if ( $count > SNAPSHOT_MAX_ROWS ) {
			return new WP_Error(
				'zs_e2e_snapshot_too_large',
				sprintf( 'Table %1$s has %2$d rows, more than the %3$d a snapshot can hold, so this site could not be restored after the run. Empty or prune it first (for example lower "Maximum log entries" in Zero Spam\'s settings).', $table, $count, SNAPSHOT_MAX_ROWS ),
				array( 'status' => 409 )
			);
		}
		$create           = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N );
		$tables[ $table ] = array(
			'create' => is_array( $create ) ? (string) $create[1] : '',
			'rows'   => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A ),
		);
	}

	$capture = static function (): array {
		$core = array();
		foreach ( touched_core_options() as $name ) {
			$core[ $name ] = get_option( $name );
		}

		return array(
			'options'  => site_plugin_option_rows(),
			'core'     => $core,
			'errorLog' => class_exists( '\ZeroSpam\Core\Utilities' ) ? \ZeroSpam\Core\Utilities::get_error_log() : false,
		);
	};

	$sites = array();
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
			switch_to_blog( (int) $site->blog_id );
			$sites[ (string) $site->blog_id ] = $capture();
			restore_current_blog();
		}
	} else {
		$sites['1'] = $capture();
	}

	return array(
		'version'  => SNAPSHOT_VERSION,
		'sites'    => $sites,
		'sitemeta' => network_plugin_options(),
		'tables'   => $tables,
	);
}

/**
 * Checks that a snapshot only touches what take_snapshot() captures: the
 * plugin's options, touched_core_options(), the network's plugin options and
 * this install's Zero Spam tables.
 *
 * @param array $snapshot Snapshot.
 * @return true|WP_Error
 */
function validate_snapshot( array $snapshot ) {
	$problems = array();

	if ( SNAPSHOT_VERSION !== (int) ( $snapshot['version'] ?? 0 ) ) {
		$problems[] = 'unsupported snapshot version (take a new one)';
	}

	foreach ( (array) ( $snapshot['sites'] ?? array() ) as $blog_id => $site ) {
		foreach ( array_keys( (array) ( $site['options'] ?? array() ) ) as $name ) {
			if ( ! is_plugin_option_name( (string) $name ) ) {
				$problems[] = sprintf( 'site %s: option %s is not a Zero Spam option', $blog_id, $name );
			}
		}
		foreach ( array_keys( (array) ( $site['core'] ?? array() ) ) as $name ) {
			if ( ! in_array( (string) $name, touched_core_options(), true ) ) {
				$problems[] = sprintf( 'site %s: core option %s is not one the suite changes', $blog_id, $name );
			}
		}
	}

	foreach ( array_keys( (array) ( $snapshot['sitemeta'] ?? array() ) ) as $name ) {
		if ( ! is_plugin_option_name( (string) $name ) ) {
			$problems[] = sprintf( 'network option %s is not a Zero Spam option', $name );
		}
	}

	foreach ( (array) ( $snapshot['tables'] ?? array() ) as $table => $data ) {
		$table = (string) $table;
		if ( ! is_install_plugin_table( $table ) ) {
			$problems[] = sprintf( 'table %s is not a Zero Spam table of this install', $table );
			continue;
		}
		if ( ! str_starts_with( (string) ( $data['create'] ?? '' ), 'CREATE TABLE `' . $table . '` (' ) ) {
			$problems[] = sprintf( 'table %s: the saved schema is not a CREATE TABLE for it', $table );
		}
		foreach ( (array) ( $data['rows'] ?? array() ) as $row ) {
			foreach ( array_keys( (array) $row ) as $column ) {
				if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', (string) $column ) ) {
					$problems[] = sprintf( 'table %s: invalid column name %s', $table, $column );
					break 2;
				}
			}
		}
	}

	return $problems
		? new WP_Error( 'zs_e2e_snapshot_invalid', 'Refusing to restore: ' . implode( '; ', $problems ), array( 'status' => 400 ) )
		: true;
}

/**
 * Restores a snapshot taken by take_snapshot() (validate it first).
 *
 * @param array $snapshot Snapshot.
 */
function restore_snapshot( array $snapshot ): void {
	global $wpdb;

	$restore = static function ( array $site ) use ( $wpdb ): void {
		foreach ( array_keys( site_plugin_options() ) as $name ) {
			delete_option( $name );
		}
		foreach ( (array) ( $site['options'] ?? array() ) as $name => $option ) {
			$name     = (string) $name;
			$autoload = (string) ( $option['autoload'] ?? 'auto' );
			add_option( $name, $option['value'] ?? '', '', autoload_argument( $autoload ) );
			// add_option() normalises the flag (on/off/auto); keep the exact stored value.
			if ( in_array( $autoload, array( 'on', 'off', 'auto', 'auto-on', 'auto-off', 'yes', 'no' ), true ) ) {
				$wpdb->update( $wpdb->options, array( 'autoload' => $autoload ), array( 'option_name' => $name ) );
			}
		}
		foreach ( (array) ( $site['core'] ?? array() ) as $name => $value ) {
			update_option( (string) $name, $value );
		}
		if ( class_exists( '\ZeroSpam\Core\Utilities' ) ) {
			\ZeroSpam\Core\Utilities::delete_error_log();
			if ( ! empty( $site['errorLog'] ) ) {
				$upload = wp_upload_dir();
				// Write the saved log back verbatim (Utilities::log() would add a timestamp).
				file_put_contents( $upload['basedir'] . '/zerospam.log', (string) $site['errorLog'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Restoring a test backup of the plugin's own log file.
			}
		}
		wp_cache_delete( 'alloptions', 'options' );
	};

	$sites = (array) ( $snapshot['sites'] ?? array() );
	if ( is_multisite() ) {
		foreach ( $sites as $blog_id => $site ) {
			if ( ! get_site( (int) $blog_id ) ) {
				continue;
			}
			switch_to_blog( (int) $blog_id );
			$restore( (array) $site );
			restore_current_blog();
		}
		foreach ( array_keys( network_plugin_options() ) as $name ) {
			delete_site_option( $name );
		}
		foreach ( (array) ( $snapshot['sitemeta'] ?? array() ) as $name => $value ) {
			update_site_option( (string) $name, $value );
		}
	} elseif ( isset( $sites['1'] ) ) {
		$restore( (array) $sites['1'] );
	}

	foreach ( (array) ( $snapshot['tables'] ?? array() ) as $table => $data ) {
		$table = (string) $table;
		// A table of a site deleted since the snapshot stays deleted.
		if ( is_multisite() && 1 === preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(\d+)_/', $table, $m ) && ! get_site( (int) $m[1] ) ) {
			continue;
		}
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			// Dropped during the run (uninstall); recreate it from the saved schema, checked by validate_snapshot().
			$wpdb->query( (string) $data['create'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- A CREATE TABLE statement read from this database by take_snapshot() and validated; it has no placeholders.
		}
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );
		foreach ( (array) ( $data['rows'] ?? array() ) as $row ) {
			$wpdb->insert( $table, (array) $row );
		}
	}

	wp_cache_flush();
}

/**
 * Option that turns the fake third-party settings section on (POST /fake-section).
 */
const FAKE_SECTION_OPTION = 'zs_e2e_fake_section';

/*
 * A settings section registered the way an extension would (no area, a
 * verify_* switch, text, radio and multi-select settings), shown in
 * Protection › Integrations by the generic renderer. Off unless a test
 * turns it on; every reset turns it off.
 */
add_filter(
	'zerospam_setting_sections',
	static function ( $sections ) {
		if ( get_option( FAKE_SECTION_OPTION ) && is_array( $sections ) ) {
			$sections['zs_e2e_widgets'] = array( 'title' => 'E2E Widget Forms' );
		}

		return $sections;
	}
);

add_filter(
	'zerospam_settings',
	static function ( $settings ) {
		if ( ! get_option( FAKE_SECTION_OPTION ) || ! is_array( $settings ) ) {
			return $settings;
		}

		$stored = get_option( 'zero-spam-zs_e2e_widgets', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$common = array(
			'section' => 'zs_e2e_widgets',
			'module'  => 'zs_e2e_widgets',
		);

		$settings['verify_zs_e2e_widgets']  = $common + array(
			'title'   => 'Protect widget forms',
			'desc'    => 'Checks every widget form for spam.',
			'type'    => 'checkbox',
			'options' => array( 'enabled' => 'Enabled' ),
			'value'   => $stored['verify_zs_e2e_widgets'] ?? false,
		);
		$settings['zs_e2e_widgets_message'] = $common + array(
			'title' => 'Blocked message',
			'desc'  => 'Shown when a widget form is blocked. <a href="https://example.com/docs">Learn more</a>',
			'type'  => 'text',
			'value' => $stored['zs_e2e_widgets_message'] ?? 'Blocked.',
		);
		$settings['zs_e2e_widgets_mode']    = $common + array(
			'title'   => 'Mode',
			'type'    => 'radio',
			'options' => array(
				'strict'  => 'Strict',
				'relaxed' => 'Relaxed',
			),
			'value'   => $stored['zs_e2e_widgets_mode'] ?? 'strict',
		);
		$settings['zs_e2e_widgets_roles']   = $common + array(
			'title'    => 'Roles that skip the check',
			'type'     => 'select',
			'multiple' => true,
			'options'  => array(
				'administrator' => 'Administrator',
				'editor'        => 'Editor',
			),
			'value'    => $stored['zs_e2e_widgets_roles'] ?? array(),
		);

		return $settings;
	}
);

/**
 * Sample locations for seeded log rows (deterministic).
 *
 * @return array<int, array<string, string>>
 */
function sample_locations(): array {
	return array(
		array( 'US', 'United States', 'CA', 'California', 'San Francisco', '94103', '37.7749', '-122.4194' ),
		array( 'DE', 'Germany', 'BE', 'Berlin', 'Berlin', '10115', '52.5200', '13.4050' ),
		array( 'BR', 'Brazil', 'SP', 'Sao Paulo', 'Sao Paulo', '01000', '-23.5505', '-46.6333' ),
		array( 'IN', 'India', 'MH', 'Maharashtra', 'Mumbai', '400001', '19.0760', '72.8777' ),
		array( 'NG', 'Nigeria', 'LA', 'Lagos', 'Lagos', '100001', '6.5244', '3.3792' ),
	);
}

add_action(
	'rest_api_init',
	static function () {
		$ns = 'zero-spam-e2e/v1';

		register_rest_route(
			$ns,
			'/ping',
			array(
				'methods'             => 'GET',
				'permission_callback' => permission( '' ),
				'callback'            => static function () {
					global $wp_version;

					// The runner writes a random marker into the checkout before each run;
					// returning it proves this site loads that checkout (and not another
					// checkout's wp-env answering on the same port).
					$marker_file = plugin_dir() . 'tests/e2e/.run-marker';
					$marker      = is_readable( $marker_file ) ? trim( (string) file_get_contents( $marker_file ) ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file of the checkout under test.

					return new WP_REST_Response(
						array(
							'ok'         => true,
							'plugin'     => defined( 'ZEROSPAM_VERSION' ) ? ZEROSPAM_VERSION : null,
							'active'     => defined( 'ZEROSPAM_VERSION' ),
							'pluginPath' => defined( 'ZEROSPAM_PATH' ) ? realpath( ZEROSPAM_PATH ) : null,
							'wp'         => $wp_version,
							'php'        => PHP_VERSION,
							'multisite'  => is_multisite(),
							'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
							'rescue'     => defined( 'ZEROSPAM_RESCUE_KEY' ),
							'runMarker'  => $marker,
							'disposable' => defined( 'ZEROSPAM_E2E_DISPOSABLE' ) && ZEROSPAM_E2E_DISPOSABLE,
						)
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/whoami',
			array(
				'methods'             => 'GET',
				'permission_callback' => permission( 'read' ),
				'callback'            => static function () {
					$user = wp_get_current_user();

					return new WP_REST_Response(
						array(
							'id'         => $user->ID,
							'login'      => $user->user_login,
							'roles'      => array_values( $user->roles ),
							'superAdmin' => is_multisite() && is_super_admin( $user->ID ),
							'blogId'     => get_current_blog_id(),
						)
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/reset',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$preset = (string) ( $request->get_param( 'preset' ) ?? 'baseline' );
					if ( ! in_array( $preset, array( 'defaults', 'recommended', 'baseline' ), true ) ) {
						return new WP_Error( 'zs_e2e_preset', 'Unknown preset.', array( 'status' => 400 ) );
					}

					$network = is_multisite() && rest_sanitize_boolean( $request->get_param( 'network' ) ?? false );
					delete_option( FAKE_SECTION_OPTION );
					// Every capability check before the first write.
					if ( $network && ! current_user_can( 'manage_network_options' ) ) {
						return new WP_Error( 'zs_e2e_forbidden', 'Network reset needs manage_network_options.', array( 'status' => 403 ) );
					}

					$promo = rest_sanitize_boolean( $request->get_param( 'promo' ) ?? false );
					if ( is_multisite() ) {
						update_site_option( 'zs_e2e_show_promo', $promo ? 1 : 0 );
					} else {
						update_option( 'zs_e2e_show_promo', $promo ? 1 : 0, false );
					}
					if ( rest_sanitize_boolean( $request->get_param( 'clearErrorLog' ) ?? false ) && class_exists( '\ZeroSpam\Core\Utilities' ) ) {
						\ZeroSpam\Core\Utilities::delete_error_log();
					}

					if ( $network ) {
						global $wpdb;
						// The network audit log grows with every network test; start empty on
						// a disposable site (its screen has a visual baseline). A Local
						// network keeps its audit history.
						$audit = $wpdb->base_prefix . 'wpzerospam_network_audit';
						if ( defined( 'ZEROSPAM_E2E_DISPOSABLE' ) && ZEROSPAM_E2E_DISPOSABLE && $audit === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit ) ) ) {
							$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $audit ) );
						}
						// Network-wide blocks and custom templates (6.0): all of them on a
						// disposable site; on a Local network only the TEST-NET blocks and
						// the e2e- templates the tests make.
						$network_blocked   = $wpdb->base_prefix . 'wpzerospam_network_blocked';
						$network_templates = $wpdb->base_prefix . 'wpzerospam_network_templates';
						if ( $network_blocked === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $network_blocked ) ) ) {
							if ( defined( 'ZEROSPAM_E2E_DISPOSABLE' ) && ZEROSPAM_E2E_DISPOSABLE ) {
								$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $network_blocked ) );
							} else {
								$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_ip LIKE %s OR user_ip LIKE %s OR user_ip LIKE %s OR user_ip LIKE %s', $network_blocked, '192.0.2.%', '198.51.100.%', '203.0.113.%', '2001:db8:%' ) );
							}
						}
						if ( $network_templates === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $network_templates ) ) ) {
							if ( defined( 'ZEROSPAM_E2E_DISPOSABLE' ) && ZEROSPAM_E2E_DISPOSABLE ) {
								$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $network_templates ) );
							} else {
								$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE template_slug LIKE %s', $network_templates, $wpdb->esc_like( 'e2e-' ) . '%' ) );
							}
						}
						foreach ( array( 'zerospam_network_blocks', 'zerospam_network_report', 'zerospam_network_stats_v6_today', 'zerospam_network_stats_v6_yesterday', 'zerospam_network_stats_v6_7', 'zerospam_network_stats_v6_30' ) as $transient ) {
							delete_site_transient( $transient );
						}
						delete_site_option( 'zerospam_network_settings' );
						delete_site_option( 'zero-spam-network-zerospam' );
						delete_site_option( 'zerospam_network_notifications_enabled' );
						delete_site_option( 'zerospam_network_settings_notifications_enabled' );
						delete_site_transient( 'zerospam_network_settings' );
						foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
							switch_to_blog( (int) $site->blog_id );
							reset_site( $preset );
							restore_current_blog();
						}
					} else {
						reset_site( $preset );
					}

					// The 6.0 admin's per-user preferences (theme, live feed, dismissed
					// tiles) and the cached news start fresh too.
					delete_metadata( 'user', 0, 'zerospam_ui', '', true );
					delete_site_transient( 'zerospam_news' );
					delete_site_transient( 'zerospam_news_backoff' );
					if ( is_multisite() ) {
						delete_site_option( 'zs_e2e_news_down' );
					} else {
						delete_option( 'zs_e2e_news_down' );
					}

					// level=enhanced: a saved license key (the canned answer is a valid
					// Business license) with Enhanced Protection on; basic: no key.
					$level = (string) ( $request->get_param( 'level' ) ?? 'basic' );
					if ( 'enhanced' === $level && 'defaults' !== $preset && class_exists( '\ZeroSpam\Core\Utilities' ) ) {
						\ZeroSpam\Core\Utilities::update_setting( 'zerospam_license', 'e2e-license' );
						\ZeroSpam\Core\Utilities::update_setting( 'zerospam', 'enabled' );
						delete_transient( 'zerospam_license_status' );
						wp_cache_flush();
					}

					return new WP_REST_Response(
						array(
							'preset'  => $preset,
							'network' => $network,
							'level'   => 'enhanced' === $level ? 'enhanced' : 'basic',
						)
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/fake-section',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$on = rest_sanitize_boolean( $request->get_param( 'on' ) ?? true );
					if ( $on ) {
						update_option( FAKE_SECTION_OPTION, 1, false );
					} else {
						delete_option( FAKE_SECTION_OPTION );
						delete_option( 'zero-spam-zs_e2e_widgets' );
					}

					return new WP_REST_Response( array( 'on' => $on ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/self-test-reset',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function () {
					// The self-test runs once a minute per site; screens and specs each start fresh.
					delete_transient( 'zerospam_self_test_lock' );

					return new WP_REST_Response( array( 'reset' => true ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/ui-prefs',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$prefs = $request->get_param( 'prefs' );
					if ( ! is_array( $prefs ) || ! $prefs ) {
						delete_user_meta( get_current_user_id(), 'zerospam_ui' );
						return new WP_REST_Response( array() );
					}
					$current = get_user_meta( get_current_user_id(), 'zerospam_ui', true );
					$merged  = array_merge( is_array( $current ) ? $current : array(), $prefs );
					update_user_meta( get_current_user_id(), 'zerospam_ui', $merged );

					return new WP_REST_Response( $merged );
				},
			)
		);

		register_rest_route(
			$ns,
			'/first-run',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					// fresh: a new install waiting for first-run setup (no settings, flag
					// pending), as the activation hook leaves it; provisioned: the same,
					// but with core and comment settings written straight to the options
					// (as provisioning would, without any save hook: comments off);
					// upgraded: a site the v6_whats_new migration flagged; none: neither.
					$mode = (string) ( $request->get_param( 'mode' ) ?? 'none' );
					if ( ! in_array( $mode, array( 'fresh', 'provisioned', 'upgraded', 'none' ), true ) ) {
						return new WP_Error( 'zs_e2e_mode', 'Unknown mode.', array( 'status' => 400 ) );
					}

					delete_option( 'zerospam_onboarding' );
					delete_option( 'zerospam_onboarding_steps' );
					delete_option( 'zerospam_whats_new' );
					delete_user_meta( get_current_user_id(), 'zerospam_ui' );

					if ( 'fresh' === $mode || 'provisioned' === $mode ) {
						reset_site( 'defaults' );
						delete_option( 'zerospam_configured' );
						update_option( 'zerospam_onboarding', 'pending', true );
					}
					if ( 'provisioned' === $mode ) {
						update_option( 'zero-spam-settings', array( 'log_blocked_ips' => 'enabled' ) );
						update_option( 'zero-spam-comments', array( 'verify_comments' => false ) );
						update_option( 'zero-spam-login', array( 'verify_login' => 'enabled' ) );
					} elseif ( 'upgraded' === $mode ) {
						update_option( 'zerospam_whats_new', '6.0.0', true );
					}

					wp_cache_flush();

					return new WP_REST_Response( array( 'mode' => $mode ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/news',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$down = rest_sanitize_boolean( $request->get_param( 'down' ) ?? false );
					if ( is_multisite() ) {
						update_site_option( 'zs_e2e_news_down', $down ? 1 : 0 );
					} else {
						update_option( 'zs_e2e_news_down', $down ? 1 : 0, false );
					}
					delete_site_transient( 'zerospam_news' );
					delete_site_transient( 'zerospam_news_backoff' );

					return new WP_REST_Response( array( 'down' => $down ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/settings',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$values = (array) $request->get_param( 'settings' );
					$result = array();
					foreach ( $values as $key => $value ) {
						$result[ $key ] = \ZeroSpam\Core\Utilities::update_setting( (string) $key, $value );
					}
					wp_cache_flush();

					return new WP_REST_Response( $result );
				},
			)
		);

		register_rest_route(
			$ns,
			'/seed-log',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					global $wpdb;

					$count     = max( 0, min( 2000, (int) ( $request->get_param( 'count' ) ?? 10 ) ) );
					$type      = sanitize_key( (string) ( $request->get_param( 'type' ) ?? 'comment' ) );
					$failed    = sanitize_key( (string) ( $request->get_param( 'failed' ) ?? 'honeypot' ) );
					$ip_prefix = (string) ( $request->get_param( 'ipPrefix' ) ?? '203.0.113.' );
					$minutes   = max( 1, (int) ( $request->get_param( 'spacingMinutes' ) ?? 60 ) );
					$fixed_ip  = (string) ( $request->get_param( 'ip' ) ?? '' );
					$extra     = $request->get_param( 'data' );
					$extra     = is_array( $extra ) ? array_map( 'strval', array_filter( $extra, 'is_scalar' ) ) : array();
					$crafted   = rest_sanitize_boolean( $request->get_param( 'crafted' ) ?? false );
					$locations = sample_locations();
					$now       = time();

					$ips = array();
					for ( $i = 0; $i < $count; $i++ ) {
						$ips[ $i ] = '' !== $fixed_ip ? $fixed_ip : $ip_prefix . ( ( $i % 250 ) + 1 );
						if ( ! is_test_net( $ips[ $i ] ) ) {
							return new WP_Error( 'zs_e2e_ip', sprintf( 'Seeded IPs must be TEST-NET addresses; %s is not (check ip / ipPrefix).', $ips[ $i ] ), array( 'status' => 400 ) );
						}
					}

					for ( $i = 0; $i < $count; $i++ ) {
						$loc = $locations[ $i % count( $locations ) ];
						// crafted: markup written straight to the table (as an old or tampered row could hold) to prove the screens print it as text.
						$row_failed = $failed;
						$page_url   = home_url( '/e2e-comments/' );
						if ( $crafted ) {
							$row_failed = CRAFTED_MARKUP;
							$loc[1]     = CRAFTED_MARKUP;
							$loc[3]     = CRAFTED_MARKUP;
							$loc[4]     = CRAFTED_MARKUP;
							$page_url   = home_url( '/e2e-comments/?q=' ) . CRAFTED_MARKUP;
						}
						$wpdb->insert(
							$wpdb->prefix . 'wpzerospam_log',
							array(
								'blog_id'         => get_current_blog_id(),
								'log_type'        => $type,
								'user_ip'         => $ips[ $i ],
								'date_recorded'   => gmdate( 'Y-m-d H:i:s', $now - ( $i * $minutes * MINUTE_IN_SECONDS ) ),
								'page_url'        => $page_url,
								// `data` adds fields as a form would submit them, stored as given (the XSS fixtures).
								'submission_data' => wp_json_encode(
									array_merge(
										$extra,
										array(
											'type'   => $type,
											'failed' => $row_failed,
											'seq'    => $i + 1,
										)
									)
								),
								'country'         => $loc[0],
								'country_name'    => $loc[1],
								'region'          => $loc[2],
								'region_name'     => $loc[3],
								'city'            => $loc[4],
								'zip'             => $loc[5],
								'latitude'        => $loc[6],
								'longitude'       => $loc[7],
							)
						);
					}

					// keepCache lets a test prove the widget's Refresh button picks the new rows up.
					if ( ! rest_sanitize_boolean( $request->get_param( 'keepCache' ) ?? false ) ) {
						delete_transient( 'zerospam_dashboard_data_site' );
						delete_transient( 'zerospam_dashboard_data_network' );
					}

					return new WP_REST_Response( array( 'inserted' => $count ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/log',
			array(
				'methods'             => 'GET',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					global $wpdb;

					$table = $wpdb->prefix . 'wpzerospam_log';
					$type  = sanitize_key( (string) ( $request->get_param( 'type' ) ?? '' ) );
					$ip    = sanitize_text_field( (string) ( $request->get_param( 'ip' ) ?? '' ) );

					if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
						return new WP_REST_Response( array() );
					}

					$rows = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT log_id, log_type, user_ip, date_recorded, submission_data FROM %i WHERE ( %s = \'\' OR log_type = %s ) AND ( %s = \'\' OR user_ip = %s ) ORDER BY log_id DESC LIMIT 500',
							$table,
							$type,
							$type,
							$ip,
							$ip
						),
						ARRAY_A
					);

					foreach ( $rows as &$row ) {
						$row['submission_data'] = json_decode( (string) $row['submission_data'], true );
					}

					return new WP_REST_Response( $rows );
				},
			)
		);

		register_rest_route(
			$ns,
			'/block',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$ip = (string) $request->get_param( 'ip' );
					if ( ! is_test_net( $ip ) ) {
						return new WP_Error( 'zs_e2e_ip', 'Only TEST-NET addresses (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32) can be blocked by the e2e suite.', array( 'status' => 400 ) );
					}

					$ok = \ZeroSpam\Includes\DB::blocked(
						array(
							'user_ip'      => $ip,
							'blocked_type' => 'permanent',
							'reason'       => (string) ( $request->get_param( 'reason' ) ?? 'e2e' ),
							'start_block'  => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
							// What the "Add Blocked IP" form stores for a permanent block without an end date.
							'end_block'    => '',
						)
					);

					return new WP_REST_Response( array( 'blocked' => (bool) $ok ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/no-lockout',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$ips = array_values( array_filter( array_map( 'strval', (array) $request->get_param( 'ips' ) ) ) );
					foreach ( $ips as $ip ) {
						if ( ! is_test_net( $ip ) ) {
							return new WP_Error( 'zs_e2e_ip', 'Only TEST-NET addresses can be saved as no-lockout addresses by the e2e suite.', array( 'status' => 400 ) );
						}
					}

					// What Admin_Safe_IPs saves when an administrator activates or updates the plugin.
					if ( $ips ) {
						update_option( \ZeroSpam\Core\Admin_Safe_IPs::OPTION, array_slice( $ips, -\ZeroSpam\Core\Admin_Safe_IPs::MAX_IPS ), false );
					} else {
						delete_option( \ZeroSpam\Core\Admin_Safe_IPs::OPTION );
					}

					return new WP_REST_Response( array( 'ips' => \ZeroSpam\Core\Admin_Safe_IPs::get_ips() ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/rescue-token',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$ip = (string) $request->get_param( 'ip' );
					if ( ! is_test_net( $ip ) ) {
						return new WP_Error( 'zs_e2e_ip', 'Only TEST-NET addresses can ask for a rescue token in the e2e suite.', array( 'status' => 400 ) );
					}

					// A valid single-use token, as an emailed rescue link carries (the "Let me back in" screen).
					return new WP_REST_Response( array( 'token' => \ZeroSpam\Core\Rescue_Link::issue_token( $ip ) ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/options',
			array(
				'methods'             => 'GET',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					return new WP_REST_Response( (object) site_plugin_options( (string) ( $request->get_param( 'search' ) ?? '' ) ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/inventory',
			array(
				'methods'             => 'GET',
				'permission_callback' => permission( is_multisite() ? 'manage_network_options' : 'manage_options' ),
				'callback'            => static function () {
					$sites = array();
					if ( is_multisite() ) {
						foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
							switch_to_blog( (int) $site->blog_id );
							$sites[ (string) $site->blog_id ] = array_keys( site_plugin_options() );
							restore_current_blog();
						}
					} else {
						$sites['1'] = array_keys( site_plugin_options() );
					}

					return new WP_REST_Response(
						array(
							'options'  => $sites,
							'sitemeta' => array_keys( network_plugin_options() ),
							'tables'   => plugin_tables(),
							'cron'     => plugin_cron_hooks(),
						)
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/network-setting',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_network_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					if ( ! is_multisite() || ! class_exists( '\ZeroSpam\Includes\Network_Settings_Manager' ) ) {
						return new WP_Error( 'zs_e2e_network', 'Not a network.', array( 'status' => 400 ) );
					}

					$manager = new \ZeroSpam\Includes\Network_Settings_Manager();
					$ok      = $manager->set_setting(
						(string) $request->get_param( 'key' ),
						$request->get_param( 'value' ),
						rest_sanitize_boolean( $request->get_param( 'locked' ) ?? false )
					);

					return new WP_REST_Response( array( 'saved' => (bool) $ok ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/error-log',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					if ( 'clear' === $request->get_param( 'action' ) ) {
						\ZeroSpam\Core\Utilities::delete_error_log();
					} else {
						\ZeroSpam\Core\Utilities::log( (string) ( $request->get_param( 'message' ) ?? 'Zero Spam e2e: sample error log entry.' ) );
					}

					return new WP_REST_Response( array( 'ok' => true ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/snapshot',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => permission( is_multisite() ? 'manage_network_options' : 'manage_options' ),
					'callback'            => static function () {
						$snapshot = take_snapshot();

						return is_wp_error( $snapshot ) ? $snapshot : new WP_REST_Response( $snapshot );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => permission( is_multisite() ? 'manage_network_options' : 'manage_options' ),
					'callback'            => static function ( WP_REST_Request $request ) {
						$snapshot = (array) $request->get_json_params();
						$valid    = validate_snapshot( $snapshot );
						if ( is_wp_error( $valid ) ) {
							return $valid;
						}
						restore_snapshot( $snapshot );

						return new WP_REST_Response( array( 'restored' => true ) );
					},
				),
			)
		);

		// Marks a run as in progress (stubs apply to every request, see
		// stubs_active()) or over. Set by the setup project, cleared by the
		// restore teardown; it expires on its own after RUN_MAX_SECONDS.
		register_rest_route(
			$ns,
			'/run',
			array(
				'methods'             => 'POST',
				'permission_callback' => permission( is_multisite() ? 'manage_network_options' : 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					$active = rest_sanitize_boolean( $request->get_param( 'active' ) ?? false );
					if ( $active ) {
						if ( is_multisite() ) {
							update_site_option( RUN_OPTION, time() + RUN_MAX_SECONDS );
						} else {
							update_option( RUN_OPTION, time() + RUN_MAX_SECONDS, false );
						}
					} elseif ( is_multisite() ) {
						delete_site_option( RUN_OPTION );
					} else {
						delete_option( RUN_OPTION );
					}

					return new WP_REST_Response( array( 'active' => $active ) );
				},
			)
		);

		foreach ( array(
			'http-calls' => HTTP_CALLS_OPTION,
			'errors'     => ERRORS_OPTION,
			'mails'      => MAILS_OPTION,
		) as $route => $option ) {
			register_rest_route(
				$ns,
				'/' . $route,
				array(
					array(
						'methods'             => 'GET',
						'permission_callback' => permission( 'manage_options' ),
						'callback'            => static function () use ( $option ) {
							return new WP_REST_Response( read_records( $option ) );
						},
					),
					array(
						'methods'             => 'DELETE',
						'permission_callback' => permission( 'manage_options' ),
						'callback'            => static function () use ( $option ) {
							clear_records( $option );

							return new WP_REST_Response( array( 'cleared' => true ) );
						},
					),
				)
			);
		}
	}
);
