<?php
/**
 * Plugin Name: Zero Spam E2E helpers
 * Description: Test-only helpers for the Zero Spam Playwright suite. Never shipped (tests/ is in .distignore). Inert unless ZEROSPAM_E2E_TOKEN is defined in wp-config.php.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 *
 * What it does when ZEROSPAM_E2E_TOKEN is defined:
 *
 * - Blocks every outgoing HTTP request to a host other than this site and
 *   answers the services Zero Spam talks to with canned JSON, recording each
 *   call (so background cron jobs can never reach live services either).
 * - For requests that carry the matching X-ZeroSpam-E2E-Token header only:
 *   uses X-ZeroSpam-E2E-IP as the visitor IP (TEST-NET addresses, so a test
 *   can never block the developer's own IP), lifts the comment flood limit,
 *   skips outgoing mail, and records PHP notices, warnings, deprecations and
 *   _doing_it_wrong() calls raised from Zero Spam's own files.
 * - Registers REST routes under zero-spam-e2e/v1 for resets, seeding and
 *   inspection. Every route needs the token header; all but /ping also need
 *   manage_options (network routes manage_network_options).
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
const MAX_RECORDS       = 200;

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
 * Canned responses keyed by host (and path fragment).
 *
 * @param string $host Request host.
 * @param string $path Request path.
 * @return array|null Body array, or null when the host is not a known service.
 */
function canned_body( string $host, string $path ): ?array {
	if ( str_ends_with( $host, 'zerospam.org' ) ) {
		if ( str_contains( $path, 'get-license' ) ) {
			return array(
				'license_key'       => 'e2e-license',
				'queries_remaining' => 1000,
			);
		}
		if ( str_contains( $path, 'v3/query' ) ) {
			return array(
				'status' => 200,
				'result' => array(),
			);
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
		if ( false !== $preempt ) {
			return $preempt;
		}

		$host      = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		$path      = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( '' === $host || $host === $site_host || in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return $preempt;
		}

		$body = canned_body( $host, $path );

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
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => (string) wp_json_encode( $body ),
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
	add_filter( 'pre_site_transient_' . $zs_e2e_transient, __NAMESPACE__ . '\no_updates', PHP_INT_MAX );
}
unset( $zs_e2e_transient );

/*
 * ---------------------------------------------------------------------------
 * Per-request behaviour for token requests only.
 * ---------------------------------------------------------------------------
 */

add_filter(
	'zerospam_get_ip',
	static function ( $ip ) {
		if ( ! is_token_request() || empty( $_SERVER['HTTP_X_ZEROSPAM_E2E_IP'] ) || ! is_string( $_SERVER['HTTP_X_ZEROSPAM_E2E_IP'] ) ) {
			return $ip;
		}

		$fake = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ZEROSPAM_E2E_IP'] ) );

		return rest_is_ip_address( $fake ) ? $fake : $ip;
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

	return str_starts_with( $real, plugin_dir() ) && ! str_contains( $real, '/tests/' ) && ! str_contains( $real, '/vendor/' );
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
 * Plugin-owned options of the current site (names and values).
 *
 * @param string $search Optional substring filter.
 * @return array<string, mixed>
 */
function site_plugin_options( string $search = '' ): array {
	global $wpdb;

	$found = array();
	foreach ( plugin_option_patterns() as $pattern ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT option_name, option_value FROM %i WHERE option_name LIKE %s', $wpdb->options, $pattern ),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			if ( '' !== $search && ! str_contains( $row['option_name'], $search ) ) {
				continue;
			}
			$found[ $row['option_name'] ] = maybe_unserialize( $row['option_value'] );
		}
	}

	ksort( $found );

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
 * Captures the plugin state of every site (Local runs restore it afterwards).
 *
 * @return array
 */
function take_snapshot(): array {
	global $wpdb;

	$capture = static function () use ( $wpdb ): array {
		$tables = array();
		foreach ( array( 'wpzerospam_log', 'wpzerospam_blocked' ) as $table ) {
			$full = $wpdb->prefix . $table;
			if ( $full === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) ) {
				$tables[ $table ] = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 50000', $full ), ARRAY_A );
			}
		}

		$core = array();
		foreach ( touched_core_options() as $name ) {
			$core[ $name ] = get_option( $name );
		}

		return array(
			'options'  => site_plugin_options(),
			'core'     => $core,
			'tables'   => $tables,
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
		'sites'    => $sites,
		'sitemeta' => network_plugin_options(),
	);
}

/**
 * Restores a snapshot taken by take_snapshot().
 *
 * @param array $snapshot Snapshot.
 */
function restore_snapshot( array $snapshot ): void {
	global $wpdb;

	$restore = static function ( array $site ) use ( $wpdb ): void {
		foreach ( array_keys( site_plugin_options() ) as $name ) {
			delete_option( $name );
		}
		foreach ( (array) ( $site['options'] ?? array() ) as $name => $value ) {
			update_option( (string) $name, $value );
		}
		foreach ( (array) ( $site['core'] ?? array() ) as $name => $value ) {
			update_option( (string) $name, $value );
		}
		foreach ( (array) ( $site['tables'] ?? array() ) as $table => $rows ) {
			$full = $wpdb->prefix . $table;
			if ( $full !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) ) {
				continue;
			}
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $full ) );
			foreach ( (array) $rows as $row ) {
				$wpdb->insert( $full, (array) $row );
			}
		}
		if ( class_exists( '\ZeroSpam\Core\Utilities' ) ) {
			\ZeroSpam\Core\Utilities::delete_error_log();
			if ( ! empty( $site['errorLog'] ) ) {
				$upload = wp_upload_dir();
				// Write the saved log back verbatim (Utilities::log() would add a timestamp).
				file_put_contents( $upload['basedir'] . '/zerospam.log', (string) $site['errorLog'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Restoring a test backup of the plugin's own log file.
			}
		}
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

	wp_cache_flush();
}

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
					$promo   = rest_sanitize_boolean( $request->get_param( 'promo' ) ?? false );
					if ( is_multisite() ) {
						update_site_option( 'zs_e2e_show_promo', $promo ? 1 : 0 );
					} else {
						update_option( 'zs_e2e_show_promo', $promo ? 1 : 0, false );
					}
					if ( rest_sanitize_boolean( $request->get_param( 'clearErrorLog' ) ?? false ) && class_exists( '\ZeroSpam\Core\Utilities' ) ) {
						\ZeroSpam\Core\Utilities::delete_error_log();
					}
					if ( $network && ! current_user_can( 'manage_network_options' ) ) {
						return new WP_Error( 'zs_e2e_forbidden', 'Network reset needs manage_network_options.', array( 'status' => 403 ) );
					}

					if ( $network ) {
						global $wpdb;
						// The network audit log grows with every network test; start empty.
						$audit = $wpdb->base_prefix . 'wpzerospam_network_audit';
						if ( $audit === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit ) ) ) {
							$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $audit ) );
						}
						delete_site_option( 'zerospam_network_settings' );
						delete_site_option( 'zero-spam-network-zerospam' );
						delete_site_transient( 'zerospam_network_settings' );
						foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
							switch_to_blog( (int) $site->blog_id );
							reset_site( $preset );
							restore_current_blog();
						}
					} else {
						reset_site( $preset );
					}

					return new WP_REST_Response(
						array(
							'preset'  => $preset,
							'network' => $network,
						)
					);
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
					$locations = sample_locations();
					$now       = time();

					for ( $i = 0; $i < $count; $i++ ) {
						$loc = $locations[ $i % count( $locations ) ];
						$wpdb->insert(
							$wpdb->prefix . 'wpzerospam_log',
							array(
								'blog_id'         => get_current_blog_id(),
								'log_type'        => $type,
								'user_ip'         => '' !== $fixed_ip ? $fixed_ip : $ip_prefix . ( ( $i % 250 ) + 1 ),
								'date_recorded'   => gmdate( 'Y-m-d H:i:s', $now - ( $i * $minutes * MINUTE_IN_SECONDS ) ),
								'page_url'        => home_url( '/e2e-comments/' ),
								'submission_data' => wp_json_encode(
									array(
										'type'   => $type,
										'failed' => $failed,
										'seq'    => $i + 1,
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
					if ( ! rest_is_ip_address( $ip ) ) {
						return new WP_Error( 'zs_e2e_ip', 'Invalid IP.', array( 'status' => 400 ) );
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
						return new WP_REST_Response( take_snapshot() );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => permission( is_multisite() ? 'manage_network_options' : 'manage_options' ),
					'callback'            => static function ( WP_REST_Request $request ) {
						restore_snapshot( (array) $request->get_json_params() );

						return new WP_REST_Response( array( 'restored' => true ) );
					},
				),
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
