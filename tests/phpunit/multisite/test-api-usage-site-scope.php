<?php
/**
 * Tests that the API usage routes only answer for a site the user may see.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * `GET api-usage` and `GET api-usage/export` take a `site_id`. A site
 * administrator may only read the current site; a network administrator may
 * read any site of the current network, never one of another network.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\API_Usage_Controller
 * @covers \ZeroSpam\Includes\Rest\Base_Controller::requested_site_id
 */
class Test_API_Usage_Site_Scope extends TestCase {

	/**
	 * Another site of this network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * A site on a second network.
	 *
	 * @var int
	 */
	private static int $other_site = 0;

	/**
	 * Creates a second site here and a site on a second network.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );

		list( , self::$other_site ) = self::create_network_with_site( $factory );
	}

	/**
	 * Monitoring on, and usage rows for site B and the other network's site only.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$this->set_setting( 'api_monitoring', 'enabled' );

		$table = $wpdb->base_prefix . DB::$tables['api_usage'];
		$now   = current_time( 'mysql' );

		foreach ( array(
			self::$site_b     => 7,
			self::$other_site => 11,
		) as $site => $calls ) {
			for ( $i = 0; $i < $calls; $i++ ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture in the plugin's own table.
				$wpdb->insert(
					$table,
					array(
						'site_id'       => $site,
						'event_type'    => 'api_call',
						'endpoint'      => 'https://www.zerospam.org/wp-json/v3/query',
						'queries_limit' => 1000,
						'queries_made'  => 500,
						'date_recorded' => $now,
						'hour_bucket'   => substr( $now, 0, 13 ) . ':00:00',
						'day_bucket'    => substr( $now, 0, 10 ),
					)
				);
			}
		}

		foreach ( array( get_current_blog_id(), self::$site_b, self::$other_site ) as $site ) {
			\ZeroSpam\Includes\API_Usage_Tracker::clear_usage_cache( $site );
		}
	}

	/**
	 * Both routes, with a site ID.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function routes(): array {
		return array(
			'usage'  => array( '/zero-spam/v1/api-usage' ),
			'export' => array( '/zero-spam/v1/api-usage/export' ),
		);
	}

	/**
	 * A subsite administrator cannot read another site's usage.
	 *
	 * @dataProvider routes
	 *
	 * @param string $route Route.
	 */
	public function test_site_admin_cannot_read_another_site( string $route ): void {
		$this->as_role( 'administrator' );

		$response = $this->rest( 'GET', $route, array(), array( 'site_id' => self::$site_b ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
		$this->assertArrayNotHasKey( 'stats', $response->get_data() );
	}

	/**
	 * A site administrator still reads their own site, with or without its ID.
	 *
	 * @dataProvider routes
	 *
	 * @param string $route Route.
	 */
	public function test_site_admin_reads_own_site( string $route ): void {
		$this->as_role( 'administrator' );

		$this->assertSame( 200, $this->rest( 'GET', $route )->get_status() );

		$response = $this->rest( 'GET', $route, array(), array( 'site_id' => get_current_blog_id() ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( get_current_blog_id(), $response->get_data()['site_id'] );
	}

	/**
	 * A network administrator reads another site of the network.
	 *
	 * @dataProvider routes
	 *
	 * @param string $route Route.
	 */
	public function test_network_admin_reads_site_of_network( string $route ): void {
		grant_super_admin( $this->as_role( 'administrator' ) );

		$response = $this->rest( 'GET', $route, array(), array( 'site_id' => self::$site_b ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::$site_b, $response->get_data()['site_id'] );
		$this->assertSame( 7, (int) $response->get_data()['stats']['api_calls'] );
	}

	/**
	 * A network administrator cannot read a site of another network.
	 *
	 * @dataProvider routes
	 *
	 * @param string $route Route.
	 */
	public function test_network_admin_cannot_read_other_network( string $route ): void {
		grant_super_admin( $this->as_role( 'administrator' ) );

		$response = $this->rest( 'GET', $route, array(), array( 'site_id' => self::$other_site ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rest_site_not_found', $response->get_data()['code'] );
	}

	/**
	 * A site ID that is not a site is refused, not read as "this site".
	 *
	 * @dataProvider routes
	 *
	 * @param string $route Route.
	 */
	public function test_invalid_site_ids_are_refused( string $route ): void {
		grant_super_admin( $this->as_role( 'administrator' ) );

		$this->assertSame( 400, $this->rest( 'GET', $route, array(), array( 'site_id' => 0 ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'GET', $route, array(), array( 'site_id' => '2abc' ) )->get_status() );
		$this->assertSame( 404, $this->rest( 'GET', $route, array(), array( 'site_id' => 999999 ) )->get_status() );
	}

	/**
	 * The permission callback refuses another site by itself, before the route runs.
	 */
	public function test_permission_callback_checks_the_site(): void {
		$this->as_role( 'administrator' );

		$request = new WP_REST_Request( 'GET', '/zero-spam/v1/api-usage/export' );
		$request->set_param( 'site_id', self::$site_b );

		$result = ( new \ZeroSpam\Includes\Rest\API_Usage_Controller() )->get_usage_permissions_check( $request );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * A site administrator cannot read the network's usage.
	 */
	public function test_site_admin_cannot_read_network_scope(): void {
		$this->as_role( 'administrator' );

		$response = $this->rest( 'GET', '/zero-spam/v1/api-usage', array(), array( 'scope' => 'network' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertArrayNotHasKey( 'stats', $response->get_data() );
	}

	/**
	 * The network's usage holds this network's sites only, never another network's.
	 */
	public function test_network_scope_stays_on_this_network(): void {
		grant_super_admin( $this->as_role( 'administrator' ) );

		$response = $this->rest( 'GET', '/zero-spam/v1/api-usage', array(), array( 'scope' => 'network' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 7, (int) $data['stats']['api_calls'] );
		$this->assertSame( 1, (int) $data['stats']['total_sites'] );
		$this->assertSame( array( self::$site_b ), array_map( 'intval', wp_list_pluck( $data['breakdown'], 'site_id' ) ) );
	}

	/**
	 * Another site's figures are cached once: that site's own clear empties the copy a network administrator read.
	 */
	public function test_another_sites_cache_is_cleared_by_that_site(): void {
		global $wpdb;

		grant_super_admin( $this->as_role( 'administrator' ) );

		$this->assertSame( 7, (int) $this->rest( 'GET', '/zero-spam/v1/api-usage/export', array(), array( 'site_id' => self::$site_b ) )->get_data()['stats']['api_calls'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture in the plugin's own table.
		$wpdb->delete( $wpdb->base_prefix . DB::$tables['api_usage'], array( 'site_id' => self::$site_b ) );

		switch_to_blog( self::$site_b );
		\ZeroSpam\Includes\API_Usage_Tracker::clear_usage_cache();
		restore_current_blog();

		$this->assertSame( 0, (int) $this->rest( 'GET', '/zero-spam/v1/api-usage/export', array(), array( 'site_id' => self::$site_b ) )->get_data()['stats']['api_calls'] );
	}
}
