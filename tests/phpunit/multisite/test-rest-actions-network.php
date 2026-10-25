<?php
/**
 * Tests for the settings actions, import and self-test on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Rest\Self_Test_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * An administrator of one site cannot run another site's actions, and
 * settings the network administrator has locked are never changed by an
 * action or an import.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\Settings_Actions_Controller
 * @covers \ZeroSpam\Includes\Rest\Self_Test_Controller
 */
class Test_Rest_Actions_Network extends TestCase {

	/**
	 * Another site of the network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * Creates the second site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );
	}

	/**
	 * Clears the network store and the settings of both sites.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( array( 'settings', 'comments' ) as $module ) {
				delete_option( "zero-spam-{$module}" );
			}
			delete_option( 'zerospam_blocked_email_domains' );
			delete_transient( Self_Test_Controller::LOCK_TRANSIENT );
			restore_current_blog();
		}
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
	 * Sends a request on a given site.
	 *
	 * @param int                  $blog_id Site.
	 * @param string               $method  HTTP method.
	 * @param string               $route   Route.
	 * @param array<string, mixed> $body    JSON body.
	 */
	private function rest_on( int $blog_id, string $method, string $route, array $body = array() ): WP_REST_Response {
		switch_to_blog( $blog_id );
		self::reset_static_caches();

		try {
			return $this->rest( $method, $route, $body );
		} finally {
			restore_current_blog();
			self::reset_static_caches();
		}
	}

	/**
	 * An administrator of site B can use the routes on site B only.
	 */
	public function test_admin_of_another_site_is_refused(): void {
		$this->site_b_admin();

		$routes = array(
			array( 'POST', '/zero-spam/v1/settings/actions/regenerate-honeypot' ),
			array( 'GET', '/zero-spam/v1/settings/export' ),
			array( 'POST', '/zero-spam/v1/settings/import' ),
			array( 'POST', '/zero-spam/v1/self-test' ),
		);

		foreach ( $routes as $route ) {
			$body = 'POST' === $route[0] && false !== strpos( $route[1], 'import' ) ? array( 'settings' => array( 'max_logs' => 5 ) ) : array();
			$this->assertSame( 403, $this->rest_on( get_main_site_id(), $route[0], $route[1], $body )->get_status(), "{$route[0]} {$route[1]} on another site." );
		}

		$this->assertSame( 200, $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/settings/export' )->get_status() );
		switch_to_blog( get_main_site_id() );
		$this->assertFalse( get_option( 'zero-spam-settings' ), 'Nothing was imported into the other site.' );
		restore_current_blog();
	}

	/**
	 * Autoconfigure leaves locked settings alone and says which.
	 */
	public function test_autoconfigure_skips_locked_settings(): void {
		$this->set_network_setting( 'verify_comments', 'disabled', true );
		$this->site_b_admin();

		$response = $this->rest_on( self::$site_b, 'POST', '/zero-spam/v1/settings/actions/autoconfigure' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'verify_comments' ), $response->get_data()['skipped_locked'] );
		switch_to_blog( self::$site_b );
		$this->assertArrayNotHasKey( 'verify_comments', (array) get_option( 'zero-spam-comments' ) );
		$this->assertSame( 'enabled', get_option( 'zero-spam-comments' )['log_blocked_comments'] );
		restore_current_blog();
	}

	/**
	 * The recommended blocked email domains are refused when the setting is locked.
	 */
	public function test_blocked_email_domains_locked(): void {
		$this->set_network_setting( 'blocked_email_domains', 'network.example', true );
		$this->site_b_admin();

		$response = $this->rest_on( self::$site_b, 'POST', '/zero-spam/v1/settings/actions/update-blocked-email-domains' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_setting_locked', $response->get_data()['code'] );
		switch_to_blog( self::$site_b );
		$this->assertFalse( get_option( 'zerospam_blocked_email_domains' ) );
		restore_current_blog();
	}

	/**
	 * An import skips locked settings and reports them; the rest is stored.
	 */
	public function test_import_skips_locked_settings(): void {
		$this->set_network_setting( 'max_logs', 500, true );
		$this->site_b_admin();

		$response = $this->rest_on(
			self::$site_b,
			'POST',
			'/zero-spam/v1/settings/import',
			array(
				'settings' => array(
					'max_logs'      => 9,
					'block_handler' => 'redirect',
				),
			)
		);

		$data = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'max_logs' ), $data['skipped_locked'] );
		$this->assertSame( array( 'block_handler' ), $data['imported'] );
		switch_to_blog( self::$site_b );
		$this->assertSame( array( 'block_handler' => 'redirect' ), get_option( 'zero-spam-settings' ) );
		restore_current_blog();
	}

	/**
	 * The self-test is limited per site: a run on one site does not hold up another.
	 */
	public function test_self_test_rate_limit_is_per_site(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$this->assertSame( 200, $this->rest_on( get_main_site_id(), 'POST', '/zero-spam/v1/self-test' )->get_status() );
		$this->assertSame( 429, $this->rest_on( get_main_site_id(), 'POST', '/zero-spam/v1/self-test' )->get_status() );
		$this->assertSame( 200, $this->rest_on( self::$site_b, 'POST', '/zero-spam/v1/self-test' )->get_status() );
	}
}
