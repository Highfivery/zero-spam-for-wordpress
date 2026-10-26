<?php
/**
 * Tests for the 6.0 network page's REST routes.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\License_Status;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Blocks;
use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * `zero-spam/v1/network/*` and the changed `network-settings/*` routes:
 * only super admins reach them, a site administrator never reads network
 * data, secrets never leave the network store, and the reads stay bounded.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\Network_Controller
 * @covers \ZeroSpam\Includes\Rest\Network_Settings_Controller
 * @covers \ZeroSpam\Includes\Network_Report
 * @covers \ZeroSpam\Includes\Network_Settings_Manager
 */
class Test_Rest_Network extends TestCase {

	/**
	 * Another site of the network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * Creates the second site with its plugin tables.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );
	}

	/**
	 * Empties the network store, the caches, the logs and the network blocks.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );
		Network_Report::flush();
		Network_Report::flush_stats();
		Network_Blocks::flush();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Network_Blocks::table() ) );

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['log'] ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['blocked'] ) );
			delete_option( 'zero-spam-settings' );
			delete_option( 'zero-spam-zerospam' );
			delete_transient( License_Status::TRANSIENT );
			restore_current_blog();
		}
	}

	/**
	 * A super admin, logged in.
	 */
	private function super_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * A user who administers only site B, logged in.
	 */
	private function site_b_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		remove_user_from_blog( $user_id, get_main_site_id() );
		add_user_to_blog( self::$site_b, $user_id, 'administrator' );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * An administrator of the main site who is not a super admin, logged in.
	 */
	private function main_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		revoke_super_admin( $user_id );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Sends a request on a given site.
	 *
	 * @param int                  $blog_id Site.
	 * @param string               $method  HTTP method.
	 * @param string               $route   Route.
	 * @param array<string, mixed> $body    JSON body.
	 * @param array<string, mixed> $query   Query parameters.
	 */
	private function rest_on( int $blog_id, string $method, string $route, array $body = array(), array $query = array() ): WP_REST_Response {
		switch_to_blog( $blog_id );
		self::reset_static_caches();

		try {
			return $this->rest( $method, $route, $body, $query );
		} finally {
			restore_current_blog();
			self::reset_static_caches();
		}
	}

	/**
	 * Inserts log rows on a site.
	 *
	 * @param int                  $blog_id   Site.
	 * @param int                  $count     Rows.
	 * @param array<string, mixed> $overrides Column values.
	 */
	private function log_on( int $blog_id, int $count, array $overrides = array() ): void {
		switch_to_blog( $blog_id );

		try {
			$this->insert_log_rows( $count, $overrides );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Every new or changed network route, with a request that succeeds for a super admin.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	private function routes(): array {
		return array(
			'overview'          => array( 'GET', '/zero-spam/v1/network/overview', array() ),
			'sites'             => array( 'GET', '/zero-spam/v1/network/sites', array() ),
			'comparison'        => array( 'GET', '/zero-spam/v1/network/comparison', array() ),
			'stats'             => array( 'GET', '/zero-spam/v1/network/stats', array() ),
			'blocks'            => array( 'GET', '/zero-spam/v1/network/blocks', array() ),
			'block'             => array( 'POST', '/zero-spam/v1/network/blocks', array( 'ip' => '192.0.2.77' ) ),
			'notifications'     => array( 'GET', '/zero-spam/v1/network/notifications', array() ),
			'notifications-put' => array( 'PUT', '/zero-spam/v1/network/notifications', array( 'changes' => true ) ),
			'audit'             => array( 'GET', '/zero-spam/v1/network-settings/audit', array() ),
			'export'            => array( 'GET', '/zero-spam/v1/network-settings/export', array() ),
			'import'            => array(
				'POST',
				'/zero-spam/v1/network-settings/import',
				array(
					'settings' => array( 'max_logs' => 7000 ),
					'mode'     => 'merge',
				),
			),
			'template-put'      => array( 'PUT', '/zero-spam/v1/network-settings/templates/e2e-unknown', array( 'name' => 'X' ) ),
		);
	}

	/**
	 * Logged out: 401; a site administrator (of the main site or another): 403; a super admin: 2xx.
	 */
	public function test_permission_matrix(): void {
		wp_set_current_user( 0 );
		foreach ( $this->routes() as $name => list( $method, $route, $body ) ) {
			$this->assertSame( 401, $this->rest( $method, $route, $body )->get_status(), "{$name}: logged out" );
		}

		$this->main_admin();
		foreach ( $this->routes() as $name => list( $method, $route, $body ) ) {
			$this->assertSame( 403, $this->rest( $method, $route, $body )->get_status(), "{$name}: main site administrator" );
		}

		$this->site_b_admin();
		foreach ( $this->routes() as $name => list( $method, $route, $body ) ) {
			$response = $this->rest_on( self::$site_b, $method, $route, $body );
			$this->assertSame( 403, $response->get_status(), "{$name}: site B administrator" );
		}

		$this->super_admin();
		foreach ( $this->routes() as $name => list( $method, $route, $body ) ) {
			$status = $this->rest( $method, $route, $body )->get_status();
			if ( 'template-put' === $name ) {
				$this->assertSame( 404, $status, 'An unknown template is 404 for a super admin.' );
				continue;
			}
			$this->assertContains( $status, array( 200, 201 ), "{$name}: super admin" );
		}
	}

	/**
	 * A site administrator sees the network's blocks read-only (without who
	 * added them), never another site's blocks or the network's statistics.
	 */
	public function test_site_admin_sees_network_blocks_only_read_only(): void {
		$this->super_admin();
		$this->assertSame( 201, $this->rest( 'POST', '/zero-spam/v1/network/blocks', array( 'ip' => '203.0.113.50' ) )->get_status() );
		switch_to_blog( get_main_site_id() );
		$this->block( '192.0.2.201' );
		restore_current_blog();

		$this->site_b_admin();
		$list = $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/rules/network-blocks' );
		$this->assertSame( 200, $list->get_status() );
		$this->assertSame( array( '203.0.113.50' ), array_column( $list->get_data(), 'ip' ) );
		$this->assertArrayNotHasKey( 'added_by', $list->get_data()[0] );

		$own = $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/rules/blocks' )->get_data();
		$this->assertSame( array(), $own, 'Site B lists neither the network blocks nor site A blocks.' );

		foreach ( array( '/zero-spam/v1/network/stats', '/zero-spam/v1/network/comparison', '/zero-spam/v1/network/blocks' ) as $route ) {
			$this->assertSame( 403, $this->rest_on( self::$site_b, 'GET', $route )->get_status(), $route );
		}
	}

	/**
	 * The undo token of a removed network block is the super admin's alone, and single use.
	 */
	public function test_network_block_undo_token(): void {
		$admin = $this->super_admin();
		$id    = (int) $this->rest( 'POST', '/zero-spam/v1/network/blocks', array( 'ip' => '203.0.113.51' ) )->get_data()['id'];
		$token = $this->rest( 'DELETE', '/zero-spam/v1/network/blocks/' . $id )->get_data()['undo_token'];
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $token );
		$this->assertNull( Network_Blocks::read( $id ) );

		// A subsite administrator holding the token gets nothing back.
		$this->main_admin();
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status() );

		wp_set_current_user( $admin );
		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $token );
		$this->assertSame( 200, $undo->get_status() );
		$this->assertSame( 1, $undo->get_data()['restored'] );
		$this->assertNotNull( Network_Blocks::read( $id ), 'The block is back with its ID.' );
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status(), 'Single use.' );
	}

	/**
	 * A setting locked through network-settings/{key}/lock cannot be saved on a subsite.
	 */
	public function test_locked_key_refused_on_a_subsite(): void {
		$this->super_admin();
		$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/network-settings/max_logs/lock' )->get_status() );

		$this->site_b_admin();
		$response = $this->rest_on( self::$site_b, 'PATCH', '/zero-spam/v1/settings', array( 'max_logs' => 1234 ) );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_setting_locked', $response->get_data()['code'] );
	}

	/**
	 * The export leaves secrets out.
	 */
	public function test_export_leaves_secrets_out(): void {
		$this->super_admin();
		$this->set_network_setting( 'ipinfo_access_token', 'ipinfo-secret-token-1234' );
		$this->set_network_setting( 'alert_webhook_url', 'https://hooks.example.com/secret-hook' );
		$this->set_network_setting( 'max_logs', 9000 );

		$response = $this->rest( 'GET', '/zero-spam/v1/network-settings/export' );
		$json     = (string) wp_json_encode( $response->get_data() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'max_logs', $json );
		$this->assertStringNotContainsString( 'ipinfo', $json );
		$this->assertStringNotContainsString( 'secret', $json );
		$this->assertStringNotContainsString( 'alert_webhook_url', $json );
	}

	/**
	 * The import keeps valid settings and reports unknown keys and invalid values.
	 */
	public function test_import_checks_keys_and_values(): void {
		$this->super_admin();

		$response = $this->rest(
			'POST',
			'/zero-spam/v1/network-settings/import',
			array(
				'settings' => array(
					'max_logs'         => array(
						'value'  => 8000,
						'locked' => true,
					),
					'not_a_setting'    => 'x',
					'comments_enabled' => 'enabled',
					'block_handler'    => 'nonsense',
				),
				'mode'     => 'replace',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $data['imported_count'] );
		$this->assertEqualsCanonicalizing( array( 'not_a_setting', 'comments_enabled' ), $data['skipped_keys'] );
		$this->assertArrayHasKey( 'block_handler', (array) $data['invalid'] );

		$store = get_site_option( Network_Settings::META_KEY );
		$this->assertSame( 8000, $store['settings']['max_logs']['value'] );
		$this->assertTrue( $store['settings']['max_logs']['locked'] );
		$this->assertArrayNotHasKey( 'not_a_setting', $store['settings'] );
		$this->assertArrayNotHasKey( 'block_handler', $store['settings'] );
	}

	/**
	 * Adds an audit row directly.
	 *
	 * @param array<string, mixed> $row Columns.
	 */
	private function audit_row( array $row ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->base_prefix . DB::$tables['network_audit'],
			array_merge(
				array(
					'site_id'      => 0,
					'action_type'  => 'set',
					'setting_key'  => 'max_logs',
					'old_value'    => '1',
					'new_value'    => '2',
					'user_id'      => 1,
					'user_login'   => 'admin',
					'ip_address'   => '192.0.2.1',
					'date_created' => '2026-10-20 10:00:00',
				),
				$row
			)
		);
	}

	/**
	 * The audit log pages and filters, in plain language, secrets masked.
	 */
	public function test_audit_paging_filters_and_masking(): void {
		global $wpdb;

		$this->super_admin();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->base_prefix . DB::$tables['network_audit'] ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->audit_row( array( 'date_created' => '2026-10-1' . $i . ' 10:00:00' ) );
		}
		$this->audit_row(
			array(
				'site_id'      => self::$site_b,
				'action_type'  => 'reset',
				'setting_key'  => null,
				'user_id'      => 77,
				'user_login'   => 'other',
				'date_created' => '2026-10-25 09:00:00',
			)
		);
		$this->audit_row(
			array(
				'setting_key'  => 'ipinfo_access_token',
				'old_value'    => 'rawsecretvalue1234',
				'new_value'    => 'newsecretvalue5678',
				'date_created' => '2026-10-24 09:00:00',
			)
		);

		$page = $this->rest(
			'GET',
			'/zero-spam/v1/network-settings/audit',
			array(),
			array(
				'page'     => 1,
				'per_page' => 2,
			)
		);
		$this->assertSame( '5', $page->get_headers()['X-WP-Total'] );
		$this->assertSame( '3', $page->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 2, $page->get_data() );

		$filtered = static function ( Test_Rest_Network $test, array $query ): array {
			return $test->rest( 'GET', '/zero-spam/v1/network-settings/audit', array(), array( 'page' => 1 ) + $query )->get_data();
		};

		$this->assertCount( 1, $filtered( $this, array( 'site_id' => self::$site_b ) ) );
		$this->assertCount( 1, $filtered( $this, array( 'user_id' => 77 ) ) );
		$this->assertCount( 1, $filtered( $this, array( 'action_type' => 'reset' ) ) );
		$this->assertCount( 1, $filtered( $this, array( 'setting_key' => 'ipinfo_access_token' ) ) );
		$this->assertCount( 2, $filtered( $this, array( 'after' => '2026-10-24' ) ) );
		$this->assertCount( 2, $filtered( $this, array( 'before' => '2026-10-11' ) ) );

		$reset = $filtered( $this, array( 'action_type' => 'reset' ) )[0];
		$this->assertSame( 'Reset a site to the network settings.', $reset['summary'] );
		$this->assertSame( 'Reset', $reset['action_label'] );
		$this->assertNotEmpty( $reset['site_name'] );

		$set = $filtered( $this, array( 'setting_key' => 'max_logs' ) )[0];
		$this->assertStringContainsString( 'from 1 to 2', $set['summary'] );

		$secret = $filtered( $this, array( 'setting_key' => 'ipinfo_access_token' ) )[0];
		$json   = (string) wp_json_encode( $secret );
		$this->assertStringNotContainsString( 'rawsecret', $json );
		$this->assertStringNotContainsString( 'newsecret', $json );
		$this->assertSame( "\u{2022}\u{2022}\u{2022}\u{2022}5678", $secret['new_value'] );
	}

	/**
	 * Comparison pages are at most 20 sites, and read only the page's sites.
	 */
	public function test_comparison_is_paged_and_bounded(): void {
		$this->super_admin();
		$this->set_network_setting( 'max_logs', 9000 );
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4000 ) );
		restore_current_blog();
		Network_Report::flush();

		$this->assertSame( 400, $this->rest( 'GET', '/zero-spam/v1/network/comparison', array(), array( 'per_page' => 21 ) )->get_status() );

		// The overview is cached first (it reads every site, five-minute cache); the page reads only its sites.
		$this->rest( 'GET', '/zero-spam/v1/network/overview' );

		global $wpdb;
		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response ) {
				$response = $this->rest(
					'GET',
					'/zero-spam/v1/network/comparison',
					array(),
					array(
						'page'     => 2,
						'per_page' => 1,
					)
				);
			}
		);

		$reads = preg_grep( '/option_value FROM/', $queries );
		$this->assertNotEmpty( $reads );
		foreach ( $reads as $query ) {
			$this->assertStringContainsString( $wpdb->get_blog_prefix( self::$site_b ) . 'options', $query );
			$this->assertStringNotContainsString( '`' . $wpdb->base_prefix . 'options`', $query );
		}

		$data = $response->get_data();
		$this->assertSame( '2', $response->get_headers()['X-WP-Total'] );
		$this->assertSame( array( self::$site_b ), array_column( $data['sites'], 'id' ) );
		$row = $data['settings'][ array_search( 'max_logs', array_column( $data['settings'], 'key' ), true ) ];
		$this->assertSame( 'override', $row['sites']->{ (string) self::$site_b}['source'] );
		$this->assertSame( 1, $row['custom'] );
	}

	/**
	 * Network statistics: per-site rows, totals, multi-site attackers and levels, without an HTTP request.
	 */
	public function test_stats(): void {
		$this->super_admin();
		$this->log_on( get_main_site_id(), 3, array( 'user_ip' => '203.0.113.60' ) );
		$this->log_on( self::$site_b, 2, array( 'user_ip' => '203.0.113.60' ) );
		$this->log_on( self::$site_b, 1, array( 'user_ip' => '198.51.100.9' ) );

		// The main site: Enhanced with a key its last check found valid; site B: on with a key never checked.
		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			update_option(
				'zero-spam-zerospam',
				array(
					'zerospam'         => 'enabled',
					'zerospam_license' => 'key-' . $blog_id,
				)
			);
			restore_current_blog();
		}
		switch_to_blog( get_main_site_id() );
		set_transient(
			License_Status::TRANSIENT,
			array(
				'hash'   => md5( 'key-' . get_main_site_id() ),
				'answer' => array( 'status' => License_Status::VALID ),
			)
		);
		restore_current_blog();

		$response = $this->rest( 'GET', '/zero-spam/v1/network/stats', array(), array( 'period' => '30' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 6, $data['totals']['blocked'] );
		$this->assertSame( 2, $data['totals']['unique_ips'] );
		$this->assertSame( 1, $data['totals']['types'] );

		$by_id = array_column( $data['sites'], null, 'id' );
		$this->assertSame( 3, $by_id[ get_main_site_id() ]['blocked'] );
		$this->assertSame( 3, $by_id[ self::$site_b ]['blocked'] );
		$this->assertSame( 2, $by_id[ self::$site_b ]['unique_ips'] );
		$this->assertSame( 'enhanced', $by_id[ get_main_site_id() ]['level'] );
		$this->assertSame( 'unverified', $by_id[ self::$site_b ]['level'] );

		$this->assertSame( array( '203.0.113.60' ), array_column( $data['attackers'], 'ip' ) );
		$this->assertSame( 2, $data['attackers'][0]['sites'] );
		$this->assertFalse( $data['attackers'][0]['blocked'] );

		// An invalid answer, and Enhanced off.
		switch_to_blog( self::$site_b );
		set_transient(
			License_Status::TRANSIENT,
			array(
				'hash'   => md5( 'key-' . self::$site_b ),
				'answer' => array( 'status' => License_Status::INVALID ),
			)
		);
		restore_current_blog();
		$this->assertSame( License_Status::LEVEL_INVALID, License_Status::site_level( self::$site_b ) );
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-zerospam', array( 'zerospam' => false ) );
		restore_current_blog();
		$this->assertSame( License_Status::LEVEL_BASIC, License_Status::site_level( self::$site_b ) );
	}

	/**
	 * Saving the weekly summary schedules or unschedules its event.
	 */
	public function test_notifications_schedule_the_weekly_summary(): void {
		$this->super_admin();

		$off = $this->rest(
			'PUT',
			'/zero-spam/v1/network/notifications',
			array(
				'weekly'  => false,
				'changes' => false,
			)
		);
		$this->assertSame(
			array(
				'weekly'  => false,
				'changes' => false,
			),
			$off->get_data()
		);
		$this->assertFalse( wp_next_scheduled( 'zerospam_network_weekly_summary' ) );

		$on = $this->rest( 'PUT', '/zero-spam/v1/network/notifications', array( 'weekly' => true ) );
		$this->assertTrue( $on->get_data()['weekly'] );
		$this->assertFalse( $on->get_data()['changes'] );
		$this->assertNotFalse( wp_next_scheduled( 'zerospam_network_weekly_summary' ) );

		$this->assertSame( 400, $this->rest( 'PUT', '/zero-spam/v1/network/notifications', array( 'x' => 1 ) )->get_status() );
	}
}
