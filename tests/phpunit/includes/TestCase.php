<?php
/**
 * Base test case for Zero Spam for WordPress.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

use LogicException;
use ReflectionClass;
use ReflectionProperty;
use WP_UnitTest_Factory;
use WP_UnitTestCase;
use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\User;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Rest\Base_Controller;
use ZeroSpam\Includes\Settings_Resolver;

/**
 * Shared set-up and helpers.
 *
 * Every test runs inside a database transaction that WordPress rolls back
 * afterwards, with CREATE/DROP TABLE rewritten to TEMPORARY tables. The plugin
 * tables are created for real when WordPress loads, so tests must never call
 * DB::update() themselves.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Visitor IP used unless a test sets another (TEST-NET-3, never routable).
	 */
	const DEFAULT_IP = '203.0.113.10';

	/**
	 * Every module key used in the source (`'module' => '…'`); each stores its
	 * settings in the option `zero-spam-{module}`.
	 */
	const PLUGIN_MODULES = array(
		'api-monitoring',
		'comments',
		'contactform7',
		'davidwalsh',
		'debug',
		'elementor',
		'fluent_form',
		'formidable',
		'givewp',
		'google',
		'gravityforms',
		'ipbase',
		'ipinfo',
		'ipstack',
		'login',
		'mailchimp4wp',
		'project_honeypot',
		'registration',
		'security',
		'settings',
		'stop_forum_spam',
		'woocommerce',
		'wp-user-avatar',
		'wpforms',
		'zerospam',
	);

	/**
	 * Every cron hook the plugin schedules.
	 */
	const PLUGIN_CRON_HOOKS = array(
		'zerospam_davidwalsh_rotate_key',
		'zerospam_aggregate_daily_stats',
		'zerospam_check_api_anomalies',
		'zerospam_aggregate_api_data',
		'zerospam_async_share_detection',
		'zerospam_network_weekly_summary',
		'zerospam_send_rescue_link',
	);

	/**
	 * Cron hooks only older versions scheduled; uninstall still clears them.
	 */
	const PLUGIN_LEGACY_CRON_HOOKS = array(
		'zerospam_api_usage_cleanup',
		'zerospam_update_blacklist_terms',
	);

	/**
	 * User meta keys the plugin writes or older versions wrote.
	 */
	const PLUGIN_USER_META = array(
		'zero_spam_dismiss_notice_enhanced_protection',
		'zero_spam_dismiss_notice_missing_license',
		'zerospam_promo_dismissed',
		'zerospam_ui',
	);

	/**
	 * Per-site options the plugin writes besides the module options (some
	 * only by older versions).
	 */
	const PLUGIN_SITE_OPTIONS = array(
		'wpzerospam',
		'wpzerospam_honeypot',
		'zerospam_activation_time',
		'zerospam_admin_safe_ip_notice',
		'zerospam_admin_safe_ips',
		'zerospam_api_monitoring_notice_dismissed',
		'zerospam_api_notices',
		'zerospam_backup_5x',
		'zerospam_blocked_email_domains',
		'zerospam_completed_migrations',
		'zerospam_configured',
		'zerospam_davidwalsh',
		'zerospam_davidwalsh_data',
		'zerospam_davidwalsh_secret',
		'zerospam_db_version',
		'zerospam_migration_cursor_canonical_blocked_ips',
		'zerospam_migration_lock_canonical_blocked_ips',
		'zerospam_migration_lock_network',
		'zerospam_proxy_detection_since',
		'zerospam_settings_audit',
		'zerospam_share_queue',
		'zerospam_shared_ip_warning_dismissed',
		'zerospam_show_settings_review_notice',
		'zero-spam-last-update',
		'zero_spam_last_api_report',
	);

	/**
	 * Per-site transients the plugin sets (fixed names and examples of the
	 * runtime-built ones).
	 */
	const PLUGIN_TRANSIENTS = array(
		'zero_spam_circuit_open',
		'zero_spam_failure_count',
		'zerospam_dashboard_data_network',
		'zerospam_dashboard_data_site',
		'zerospam_dw_test',
		'zerospam_geo_test',
		'zerospam_license_status',
		'zerospam_ipinfo_test',
		'zerospam_login_intent_test',
		'zerospam_protection_detections',
		'zerospam_rescue_ip_test',
		'zerospam_rescue_job_test',
		'zerospam_rescue_site_sends',
		'zerospam_rescue_to_test',
		'zerospam_rescue_token_test',
		'zerospam_self_test_lock',
		'zerospam_shared_ip_warning',
		'zerospam_stats_summary',
		'zerospam_stats_timeseries',
		'zerospam_undo_test',
		'zerospam_woo_options_migrated',
	);

	/**
	 * Network options the plugin writes besides `zero-spam-network-{module}`
	 * (sitemeta on a network, the options table on a single site).
	 */
	const PLUGIN_NETWORK_OPTIONS = array(
		'zero_spam_last_api_request',
		'zerospam_backup_5x',
		'zerospam_network_completed_migrations',
		'zerospam_network_notifications_enabled',
		'zerospam_network_settings',
		'zerospam_network_settings_notifications_enabled',
	);

	/**
	 * Network (site) transients the plugin sets with fixed names.
	 */
	const PLUGIN_NETWORK_TRANSIENTS = array(
		'zerospam_network_settings',
		'zerospam_news',
		'zerospam_news_backoff',
	);

	/**
	 * Sites created by create_site_with_tables(), deleted after the class.
	 *
	 * @var int[]
	 */
	private static array $sites_with_tables = array();

	/**
	 * $_SERVER as it was before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $server_backup = array();

	/**
	 * $_COOKIE as it was before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $cookie_backup = array();

	/**
	 * Resets plugin state that would otherwise leak between tests.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->server_backup = $_SERVER;
		$this->cookie_backup = $_COOKIE;

		// Requests made since the last test ended came from wpSetUpBeforeClass()
		// or a class tear-down, outside any test.
		$outside_tests = HttpMock::take_unmocked();
		HttpMock::reset();
		Stubs::reset();
		self::reset_static_caches();

		// The debug module can replace the visitor IP through zerospam_get_ip.
		delete_option( 'zero-spam-debug' );

		$this->set_request( '/' );

		if ( $outside_tests ) {
			$this->fail( "Outbound HTTP requests were made outside a test (wpSetUpBeforeClass or a class tear-down):\n" . implode( "\n", $outside_tests ) );
		}
	}

	/**
	 * Restores globals and fails the test if it made an unmocked HTTP request.
	 */
	public function tear_down(): void {
		$unmocked = HttpMock::take_unmocked();

		$_SERVER = $this->server_backup;
		$_COOKIE = $this->cookie_backup;
		self::reset_static_caches();

		// Routes are registered afresh by the next rest() call.
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();

		if ( $unmocked ) {
			$this->fail( "The test made outbound HTTP requests that were not mocked:\n" . implode( "\n", $unmocked ) );
		}
	}

	/**
	 * Deletes the sites create_site_with_tables() made for this class.
	 */
	public static function tear_down_after_class(): void {
		global $wpdb;

		foreach ( self::$sites_with_tables as $blog_id ) {
			$prefix = $wpdb->get_blog_prefix( $blog_id );
			wp_delete_site( $blog_id );
			foreach ( array( 'log', 'blocked' ) as $key ) {
				$wpdb->query( 'DROP TABLE IF EXISTS `' . $prefix . DB::$tables[ $key ] . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Fixed table names.
			}
		}
		self::$sites_with_tables = array();

		parent::tear_down_after_class();
	}

	/**
	 * Clears the per-request static caches in the plugin.
	 *
	 * DavidWalsh::get_token_status() (behind validate_token()) also keeps a function-level
	 * `static $accepted`, which cannot be reset from outside: a token accepted
	 * in one test stays accepted for the rest of the run. Use a fresh token per
	 * test until that cache moves to a resettable property.
	 */
	protected static function reset_static_caches(): void {
		self::set_static( Utilities::class, 'allowed_words_cache', null );
		self::set_static( Utilities::class, 'min_length_cache', null );
		self::set_static( Utilities::class, 'script_debug', null );
		self::set_static( Settings_Resolver::class, 'cache', array() );
		Settings::$settings = array();
		Settings::$sections = array();
		Settings_Schema::reset_cache();
		License_Status::reset_request_cache();
		Base_Controller::reset_request_cache();
		User::reset_cache();
	}

	/**
	 * Sets a static property, including private ones.
	 *
	 * @param string $class_name Class name.
	 * @param string $property   Property name.
	 * @param mixed  $value      New value.
	 */
	protected static function set_static( string $class_name, string $property, $value ): void {
		( new ReflectionProperty( $class_name, $property ) )->setValue( null, $value );
	}

	/**
	 * Stores a plugin setting the way the settings page does.
	 *
	 * @param string      $key    Setting key.
	 * @param mixed       $value  Value.
	 * @param string|null $module Module option to write; looked up in the registry when null.
	 */
	protected function set_setting( string $key, $value, ?string $module = null ): void {
		if ( null === $module ) {
			$registry = Settings::get_settings();
			if ( empty( $registry[ $key ]['module'] ) ) {
				$this->fail( "Unknown Zero Spam setting: {$key}" );
			}
			$module = (string) $registry[ $key ]['module'];
		}

		$option = get_option( "zero-spam-{$module}", array() );
		$option = is_array( $option ) ? $option : array();

		$option[ $key ] = $value;
		update_option( "zero-spam-{$module}", $option );

		self::reset_static_caches();
	}

	/**
	 * Stores a network-wide setting (multisite) and clears its cache.
	 *
	 * @param string $key    Setting key.
	 * @param mixed  $value  Network value.
	 * @param bool   $locked Whether sites are prevented from overriding it.
	 */
	protected function set_network_setting( string $key, $value, bool $locked = false ): void {
		$settings = get_site_option( Network_Settings::META_KEY, array() );
		if ( ! is_array( $settings ) || ! isset( $settings['settings'] ) || ! is_array( $settings['settings'] ) ) {
			$settings = array( 'settings' => array() );
		}

		$settings['settings'][ $key ] = array(
			'value'  => $value,
			'locked' => $locked,
		);

		update_site_option( Network_Settings::META_KEY, $settings );
		delete_site_transient( Network_Settings::CACHE_KEY );
		self::reset_static_caches();
	}

	/**
	 * Simulates the current request.
	 *
	 * @param string $uri Request URI, including any query string (copied to $_GET).
	 * @param string $ip  Visitor IP (REMOTE_ADDR).
	 */
	protected function set_request( string $uri, string $ip = self::DEFAULT_IP ): void {
		$_SERVER['REQUEST_URI'] = $uri;
		$_SERVER['REMOTE_ADDR'] = $ip;

		$_GET  = array();
		$query = wp_parse_url( $uri, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			parse_str( $query, $_GET );
		}
		$_REQUEST = array_merge( $_GET, $_POST );
	}

	/**
	 * Full name of the current site's log table.
	 */
	protected function log_table(): string {
		global $wpdb;

		return $wpdb->prefix . DB::$tables['log'];
	}

	/**
	 * Inserts log rows with distinct dates, oldest first.
	 *
	 * Row N of M is dated M - N minutes ago, so the last row inserted is the
	 * newest.
	 *
	 * @param int                  $count     Number of rows.
	 * @param array<string, mixed> $overrides Column values for every row.
	 * @return int[] Inserted log IDs, oldest first.
	 */
	protected function insert_log_rows( int $count, array $overrides = array() ): array {
		global $wpdb;

		$now = time();
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$row = array_merge(
				array(
					'log_type'        => 'comment',
					'user_ip'         => '198.51.100.' . ( ( $i % 250 ) + 1 ),
					// Same clock as DB::log(), which stores current_time( 'mysql' ).
					'date_recorded'   => wp_date( 'Y-m-d H:i:s', $now - ( $count - $i ) * MINUTE_IN_SECONDS ),
					'page_url'        => 'http://example.org/',
					'submission_data' => '{}',
				),
				$overrides
			);

			$wpdb->insert( $this->log_table(), $row );
			$ids[] = (int) $wpdb->insert_id;
		}

		return $ids;
	}

	/**
	 * Counts log rows, optionally matching column values.
	 *
	 * @param array<string, string> $where Column => value.
	 */
	protected function count_log_rows( array $where = array() ): int {
		global $wpdb;

		$sql    = 'SELECT COUNT(*) FROM `' . $this->log_table() . '`';
		$values = array();
		if ( $where ) {
			$clauses = array();
			foreach ( $where as $column => $value ) {
				$clauses[] = '`' . preg_replace( '/[^a-z_]/', '', $column ) . '` = %s';
				$values[]  = $value;
			}
			$sql = $wpdb->prepare( $sql . ' WHERE ' . implode( ' AND ', $clauses ), $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are built above; column names are filtered.
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above when it has values.
	}

	/**
	 * Adds a block record for an IP.
	 *
	 * @param string      $ip          IP address.
	 * @param string      $type        permanent or temporary.
	 * @param string|null $start_block Start (Y-m-d H:i:s, site time); an hour ago when null.
	 * @param string|null $end_block   End (Y-m-d H:i:s, site time).
	 * @return int Block ID.
	 */
	protected function block( string $ip, string $type = 'permanent', ?string $start_block = null, ?string $end_block = null ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . DB::$tables['blocked'],
			array(
				'blocked_type' => $type,
				'user_ip'      => $ip,
				'key_type'     => 'ip',
				'date_added'   => current_time( 'mysql' ),
				'start_block'  => $start_block ?? wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
				'end_block'    => $end_block,
				'reason'       => 'Test block',
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Runs a callback and returns every SQL query it sent, as written by the
	 * caller (before the test framework rewrites CREATE/DROP TABLE).
	 *
	 * @param callable $callback Code to run.
	 * @return string[]
	 */
	protected function capture_queries( callable $callback ): array {
		$queries = array();
		$spy     = static function ( $query ) use ( &$queries ) {
			$queries[] = (string) $query;

			return $query;
		};

		add_filter( 'query', $spy, 1 );
		try {
			$callback();
		} finally {
			remove_filter( 'query', $spy, 1 );
		}

		return $queries;
	}

	/**
	 * Makes wp_redirect() throw a RedirectException and expects it.
	 *
	 * The exception message is the redirect location, so tests can follow
	 * this with expectExceptionMessage().
	 */
	protected function expect_redirect(): void {
		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the test, never rendered.
			},
			1,
			2
		);

		$this->expectException( RedirectException::class );
	}

	/**
	 * Answers HTTP requests whose URL matches a pattern.
	 *
	 * @param string       $pattern PCRE pattern matched against the full URL.
	 * @param string|array $body    Response body; arrays are JSON encoded.
	 * @param int          $code    HTTP status code.
	 */
	protected function mock_http( string $pattern, $body, int $code = 200 ): void {
		HttpMock::mock( $pattern, $body, $code );
	}

	/**
	 * Sends a request to the REST API as the current user.
	 *
	 * Permission callbacks run as for a real request; the cookie nonce check
	 * does not (see rest_cookie_check_errors()), so tests of it call that
	 * function themselves.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route, e.g. /zero-spam/v1/settings.
	 * @param array<string, mixed> $body   JSON body.
	 * @param array<string, mixed> $query  Query parameters.
	 */
	protected function rest( string $method, string $route, array $body = array(), array $query = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		$request->set_query_params( $query );

		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}

	/**
	 * Creates a user with a role and logs them in.
	 *
	 * @param string $role Role name.
	 * @return int User ID.
	 */
	protected function as_role( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Loads the admin API and switches to an admin screen, so is_admin() is true.
	 *
	 * The plugin creates its admin classes only when is_admin() is true while
	 * it loads, which it is not during the test bootstrap; tests instantiate
	 * them directly after calling this.
	 *
	 * @param string $screen Screen ID.
	 */
	protected function admin_screen( string $screen = 'dashboard' ): void {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		set_current_screen( $screen );
	}

	/**
	 * Creates a network site with real plugin tables (multisite).
	 *
	 * Call it from wpSetUpBeforeClass(): inside a test, WordPress rewrites
	 * CREATE TABLE to CREATE TEMPORARY TABLE, which SHOW TABLES (and therefore
	 * DB::log() and DB::query()) cannot see. The site and its tables are
	 * deleted after the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return int Blog ID.
	 * @throws LogicException When called inside a test.
	 */
	protected static function create_site_with_tables( WP_UnitTest_Factory $factory ): int {
		global $wp_filter;

		if ( isset( $wp_filter['query'] ) ) {
			foreach ( $wp_filter['query']->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && '_create_temporary_tables' === $callback['function'][1] ) {
						throw new LogicException( 'create_site_with_tables() must be called from wpSetUpBeforeClass(), not inside a test.' );
					}
				}
			}
		}

		$blog_id = (int) $factory->blog->create();

		switch_to_blog( $blog_id );
		( new ReflectionClass( DB::class ) )->newInstanceWithoutConstructor()->update();
		restore_current_blog();

		self::$sites_with_tables[] = $blog_id;

		return $blog_id;
	}
}
