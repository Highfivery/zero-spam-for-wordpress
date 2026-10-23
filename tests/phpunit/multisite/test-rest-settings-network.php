<?php
/**
 * Tests for the settings, schema and license routes on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Network values come from one store (zerospam_network_settings), locked
 * values win and cannot be changed by a site, secrets from the network are
 * never hinted to a site, and nobody reads another site's values.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\Settings_Controller
 * @covers \ZeroSpam\Includes\Settings_Resolver
 * @covers \ZeroSpam\Core\Settings_Schema
 */
class Test_Rest_Settings_Network extends TestCase {

	/**
	 * Settings route.
	 */
	const ROUTE = '/zero-spam/v1/settings';

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
		delete_site_option( Migrations::LEGACY_NETWORK_OPTION );
		delete_site_transient( Network_Settings::CACHE_KEY );

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			delete_option( 'zero-spam-zerospam' );
			delete_option( 'zero-spam-settings' );
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
	 * Who may read which site's settings.
	 */
	public function test_permission_matrix_across_sites(): void {
		switch_to_blog( get_main_site_id() );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4321 ) );
		restore_current_blog();

		wp_set_current_user( $this->site_b_admin() );

		$this->assertSame( 200, $this->rest_on( self::$site_b, 'GET', self::ROUTE )->get_status(), 'An administrator reads their own site.' );
		$this->assertSame( 200, $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/settings/schema' )->get_status() );

		foreach ( array( self::ROUTE, '/zero-spam/v1/settings/schema', '/zero-spam/v1/license' ) as $route ) {
			$other = $this->rest_on( get_main_site_id(), 'GET', $route );
			$this->assertSame( 403, $other->get_status(), "An administrator of another site reads {$route}." );
			$this->assertStringNotContainsString( '4321', (string) wp_json_encode( $other->get_data() ) );
		}

		$write = $this->rest_on( get_main_site_id(), 'PATCH', self::ROUTE, array( 'max_logs' => 1 ) );
		$this->assertSame( 403, $write->get_status() );
		switch_to_blog( get_main_site_id() );
		$this->assertSame( 4321, get_option( 'zero-spam-settings' )['max_logs'] );
		restore_current_blog();

		$network = $this->rest_on( self::$site_b, 'GET', self::ROUTE, array(), array( 'scope' => 'network' ) );
		$this->assertSame( 403, $network->get_status(), 'A site administrator cannot read network values.' );

		$this->super_admin();
		$this->assertSame( 200, $this->rest_on( get_main_site_id(), 'GET', self::ROUTE )->get_status() );
		$this->assertSame( 200, $this->rest_on( self::$site_b, 'GET', self::ROUTE, array(), array( 'scope' => 'network' ) )->get_status() );
	}

	/**
	 * Network values set by the network settings screen or WP-CLI are what
	 * the REST route reads: before 6.0 it read zero-spam-network-zerospam.
	 */
	public function test_network_values_come_from_the_network_store(): void {
		$this->set_network_setting( 'zerospam_confidence_min', 45 );
		update_site_option( Migrations::LEGACY_NETWORK_OPTION, array( 'zerospam_confidence_min' => 99 ) );
		$this->super_admin();

		$network  = $this->rest_on( self::$site_b, 'GET', self::ROUTE, array(), array( 'scope' => 'network' ) )->get_data();
		$resolved = $this->rest_on( self::$site_b, 'GET', self::ROUTE, array(), array( 'include' => 'sources' ) )->get_data();

		$this->assertSame( 45, $network['settings']['zerospam_confidence_min'] );
		$this->assertSame( 45, $resolved['settings']['zerospam_confidence_min'] );
		$this->assertSame( 'network', $resolved['sources']['zerospam_confidence_min'] );
	}

	/**
	 * `wp zerospam network-settings set` and the REST route see the same value.
	 */
	public function test_cli_network_settings_and_rest_agree(): void {
		require_once dirname( __DIR__ ) . '/includes/stubs/wp-cli.php';
		$this->super_admin();

		WP_CLI::reset();
		( new \ZeroSpam\Includes\CLI\Network_Settings_CLI() )->set( array( 'max_logs', '2222' ), array() );

		$this->assertCount( 1, WP_CLI::of( 'success' ) );
		$data = $this->rest_on( self::$site_b, 'GET', self::ROUTE, array(), array( 'scope' => 'network' ) )->get_data();
		$this->assertSame( '2222', $data['settings']['max_logs'] );
	}

	/**
	 * A network PATCH writes the network store and keeps the lock; the old option is left alone.
	 */
	public function test_network_patch_writes_the_store_and_keeps_locks(): void {
		$this->set_network_setting( 'max_logs', 500, true );
		update_site_option( Migrations::LEGACY_NETWORK_OPTION, array( 'zerospam_confidence_min' => 99 ) );
		$this->super_admin();

		$response = $this->rest_on(
			get_main_site_id(),
			'PATCH',
			self::ROUTE,
			array(
				'max_logs'                => 600,
				'zerospam_confidence_min' => 35,
			),
			array( 'scope' => 'network' )
		);

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		$store = get_site_option( Network_Settings::META_KEY )['settings'];
		$this->assertSame( 600, $store['max_logs']['value'] );
		$this->assertTrue( $store['max_logs']['locked'], 'The lock is kept.' );
		$this->assertSame( 35.0, $store['zerospam_confidence_min']['value'] );
		$this->assertFalse( $store['zerospam_confidence_min']['locked'] );
		$this->assertSame( array( 'zerospam_confidence_min' => 99 ), get_site_option( Migrations::LEGACY_NETWORK_OPTION ), 'The 5.x option is never written.' );
	}

	/**
	 * A locked network value wins over a site override, and is reported as locked.
	 */
	public function test_locked_network_value_wins(): void {
		$this->set_network_setting( 'max_logs', 500, true );
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 250 ) );
		restore_current_blog();
		wp_set_current_user( $this->site_b_admin() );

		$data = $this->rest_on( self::$site_b, 'GET', self::ROUTE, array(), array( 'include' => 'sources' ) )->get_data();

		$this->assertSame( 500, $data['settings']['max_logs'] );
		$this->assertSame( 'locked', $data['sources']['max_logs'] );

		$schema = array_column( $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/settings/schema' )->get_data()['settings'], null, 'key' );
		$this->assertTrue( $schema['max_logs']['locked'] );
		$this->assertSame( 'locked', $schema['max_logs']['source'] );
		$this->assertSame( 500, $schema['max_logs']['value'] );
	}

	/**
	 * Any locked setting of the registry is refused with 403, not only the original two.
	 */
	public function test_locked_registry_setting_is_refused(): void {
		$this->set_network_setting( 'comment_spam_message', 'Network message.', true );
		wp_set_current_user( $this->site_b_admin() );

		$response = $this->rest_on( self::$site_b, 'PATCH', self::ROUTE, array( 'comment_spam_message' => 'Mine.' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_setting_locked', $response->get_data()['code'] );
		$this->assertSame( array( 'comment_spam_message' ), $response->get_data()['data']['locked_keys'] );
	}

	/**
	 * A license set by the network is shown to a site as set, without a hint.
	 */
	public function test_network_provided_secret_has_no_hint_on_a_site(): void {
		$this->set_network_setting( 'zerospam_license', 'network-license-NNNN-5678' );
		wp_set_current_user( $this->site_b_admin() );

		$data   = $this->rest_on( self::$site_b, 'GET', self::ROUTE )->get_data();
		$schema = array_column( $this->rest_on( self::$site_b, 'GET', '/zero-spam/v1/settings/schema' )->get_data()['settings'], null, 'key' );

		$expected = array(
			'set'    => true,
			'source' => 'network',
		);
		$this->assertSame( $expected, $data['settings']['zerospam_license'] );
		$this->assertSame( $expected, $schema['zerospam_license']['value'] );
		$this->assertStringNotContainsString( 'NNNN', (string) wp_json_encode( $schema ) );
		$this->assertStringNotContainsString( '5678', (string) wp_json_encode( $data ) );

		$this->set_network_setting( 'zerospam_license', 'network-license-NNNN-5678', true );
		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'locked',
			),
			$this->rest_on( self::$site_b, 'GET', self::ROUTE )->get_data()['settings']['zerospam_license']
		);
	}

	/**
	 * A site's own key is hinted to that site only.
	 */
	public function test_site_secret_stays_on_its_site(): void {
		switch_to_blog( get_main_site_id() );
		update_option( 'zero-spam-zerospam', array( 'zerospam_license' => 'main-site-key-MMMM-1111' ) );
		restore_current_blog();
		wp_set_current_user( $this->site_b_admin() );

		$data = $this->rest_on( self::$site_b, 'GET', self::ROUTE )->get_data();

		$this->assertFalse( $data['settings']['zerospam_license']['set'] );
		$this->assertStringNotContainsString( '1111', (string) wp_json_encode( $data ) );

		switch_to_blog( self::$site_b );
		$this->assertSame( array( 'set' => false, 'source' => 'default' ), Settings_Schema::masked( 'zerospam_license' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short expected value.
		restore_current_blog();
	}
}
