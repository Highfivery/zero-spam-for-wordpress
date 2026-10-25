<?php
/**
 * Tests for the 6.0 data routes on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Rest\Stats_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * Each site sees only its own activity, blocks and statistics; an
 * administrator of one site reads nothing of another; locked network
 * values are honoured; an undo token works only on its own site.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\Activity_Controller
 * @covers \ZeroSpam\Includes\Rest\Stats_Controller
 * @covers \ZeroSpam\Includes\Rest\Rules_Controller
 * @covers \ZeroSpam\Includes\Rest\Protection_Controller
 * @covers \ZeroSpam\Includes\Undo_Store
 */
class Test_Rest_Data_Network extends TestCase {

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
	 * Clears the network store, both sites' logs, blocks, caches and settings.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['log'] ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['blocked'] ) );
			delete_option( 'zero-spam-settings' );
			delete_option( 'zero-spam-comments' );
			Stats_Controller::flush_cache();
			delete_transient( \ZeroSpam\Includes\Rest\Protection_Controller::TRANSIENT );
			restore_current_blog();
		}
	}

	/**
	 * A user who administers only site B.
	 */
	private function site_b_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		remove_user_from_blog( $user_id, get_main_site_id() );
		add_user_to_blog( self::$site_b, $user_id, 'administrator' );

		return $user_id;
	}

	/**
	 * A super admin.
	 */
	private function super_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
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
	 * @return int[]
	 */
	private function log_on( int $blog_id, int $count, array $overrides = array() ): array {
		switch_to_blog( $blog_id );

		try {
			return $this->insert_log_rows( $count, $overrides );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * An administrator of site B reads site B only.
	 */
	public function test_admin_of_another_site_reads_nothing(): void {
		$this->log_on( get_main_site_id(), 2, array( 'user_ip' => '192.0.2.201' ) );
		$this->log_on( self::$site_b, 1, array( 'user_ip' => '192.0.2.202' ) );
		switch_to_blog( get_main_site_id() );
		$main_block = $this->block( '192.0.2.201' );
		restore_current_blog();

		wp_set_current_user( $this->site_b_admin() );

		$routes = array(
			'/zero-spam/v1/activity',
			'/zero-spam/v1/activity/1',
			'/zero-spam/v1/stats/summary',
			'/zero-spam/v1/stats/timeseries',
			'/zero-spam/v1/rules/blocks',
			'/zero-spam/v1/rules/blocks/' . $main_block,
			'/zero-spam/v1/rules/lists/allowed-ips',
			'/zero-spam/v1/rules/no-lockout',
			'/zero-spam/v1/protection/modules',
		);

		foreach ( $routes as $route ) {
			$response = $this->rest_on( get_main_site_id(), 'GET', $route );
			$this->assertSame( 403, $response->get_status(), $route );
			$this->assertStringNotContainsString( '192.0.2.201', (string) wp_json_encode( $response->get_data() ), $route );
		}

		$delete = $this->rest_on( get_main_site_id(), 'DELETE', '/zero-spam/v1/rules/blocks/' . $main_block );
		$this->assertSame( 403, $delete->get_status() );

		$own = $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/activity' )->get_data();
		$this->assertSame( array( '192.0.2.202' ), array_column( $own, 'ip' ), 'Site B sees its own log only.' );
		$this->assertSame( 1, $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/stats/summary' )->get_data()['total'] );
		$this->assertSame( array(), $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/rules/blocks' )->get_data() );
	}

	/**
	 * An undo token works only on the site the rows were deleted from.
	 */
	public function test_undo_token_is_bound_to_its_site(): void {
		$this->super_admin();
		$ids   = $this->log_on( get_main_site_id(), 1 );
		$token = $this->rest_on( get_main_site_id(), 'DELETE', '/zero-spam/v1/activity/' . $ids[0] )->get_data()['undo_token'];

		$this->assertSame( 404, $this->rest_on( self::$site_b, 'POST', '/zero-spam/v1/undo/' . $token )->get_status() );
		$this->assertSame( 200, $this->rest_on( get_main_site_id(), 'POST', '/zero-spam/v1/undo/' . $token )->get_status() );
	}

	/**
	 * A locked list is shown as locked and cannot be changed by the site.
	 */
	public function test_locked_list_is_refused(): void {
		$this->set_network_setting( 'ip_whitelist', '192.0.2.1', true );
		wp_set_current_user( $this->site_b_admin() );

		$data = $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/rules/lists/allowed-ips' )->get_data();
		$this->assertSame( array( '192.0.2.1' ), $data['items'] );
		$this->assertTrue( $data['locked'] );
		$this->assertSame( 'locked', $data['source'] );

		$response = $this->rest_on( self::$site_b, 'PUT', '/zero-spam/v1/rules/lists/allowed-ips', array( 'items' => array( '192.0.2.9' ) ) );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_setting_locked', $response->get_data()['code'] );
		$this->assertSame( array( 'ip_whitelist' ), $response->get_data()['data']['locked_keys'] );

		switch_to_blog( self::$site_b );
		$this->assertFalse( get_option( 'zero-spam-settings' ), 'Nothing was stored on the site.' );
		restore_current_blog();
	}

	/**
	 * A locked switch makes the module locked.
	 */
	public function test_locked_switch_locks_the_module(): void {
		$this->set_network_setting( 'verify_comments', 'enabled', true );
		wp_set_current_user( $this->site_b_admin() );

		$comments = array_column( $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/protection/modules' )->get_data()['modules'], null, 'key' )['comments'];

		$this->assertTrue( $comments['locked'] );
		$this->assertTrue( $comments['enabled'] );
		$this->assertContains( 'verify_comments', $comments['locked_keys'] );
	}

	/**
	 * The log fill uses the locked network maximum over the site's own.
	 */
	public function test_log_fill_uses_the_locked_network_maximum(): void {
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 20 ) );
		restore_current_blog();
		$this->set_network_setting( 'max_logs', 50, true );
		wp_set_current_user( $this->site_b_admin() );

		$this->assertSame( 50, $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/stats/summary' )->get_data()['log']['max'] );

		$this->set_network_setting( 'max_logs', 50, false );
		$this->assertSame( 20, $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/stats/summary' )->get_data()['log']['max'], 'Unlocked: the site value wins.' );
	}
}
