<?php
/**
 * Tests that the network page stays on its own network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Blocks;
use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * With a real second network and a site on it: nothing the network page
 * reads or writes reaches that site, and no cache (also under a persistent
 * object cache, where core's site transients ignore the network) answers for
 * another network.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Report
 * @covers \ZeroSpam\Includes\Network_Blocks
 * @covers \ZeroSpam\Includes\Network_Settings
 * @covers \ZeroSpam\Includes\Network_Settings_Manager
 * @covers \ZeroSpam\Includes\Network_Templates::apply_to_sites
 * @covers \ZeroSpam\Includes\Rest\Network_Settings_Controller
 * @covers \ZeroSpam\Includes\Rest\Network_Controller
 */
class Test_Network_Scope extends TestCase {

	/**
	 * Another site of this network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * The second network.
	 *
	 * @var int
	 */
	private static int $other_network = 0;

	/**
	 * A site on the second network.
	 *
	 * @var int
	 */
	private static int $other_site = 0;

	/**
	 * Creates a second site here, and a real second network with a site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );

		list( self::$other_network, self::$other_site ) = self::create_network_with_site( $factory );
	}

	/**
	 * A super admin; empty stores and caches on both networks; the other site has settings of its own.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );

		foreach ( array( (int) get_current_network_id(), self::$other_network ) as $network_id ) {
			$this->on_network(
				$network_id,
				static function () {
					delete_site_option( Network_Settings::META_KEY );
					delete_site_transient( Network_Settings::cache_key() );
					Network_Report::flush();
					Network_Report::flush_stats();
					Network_Blocks::flush();
				}
			);
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Network_Blocks::table() ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->base_prefix . DB::$tables['network_audit'] ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->base_prefix . DB::$tables['network_templates'] ) );

		foreach ( array( get_main_site_id(), self::$site_b, self::$other_site ) as $blog_id ) {
			switch_to_blog( $blog_id );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['log'] ) );
			delete_option( 'zero-spam-settings' );
			delete_option( 'zero-spam-ipinfo' );
			restore_current_blog();
		}

		$this->set_network_setting( 'max_logs', 9000 );
		$this->set_network_setting( 'block_handler', 'redirect', true );

		switch_to_blog( self::$other_site );
		update_option(
			'zero-spam-settings',
			array(
				'max_logs'      => 1234,
				'block_handler' => '403',
			)
		);
		update_option( 'zero-spam-ipinfo', array( 'ipinfo_access_token' => 'other-network-token' ) );
		restore_current_blog();
		Network_Report::flush();
	}

	/**
	 * Puts WordPress back on no external object cache.
	 */
	public function tear_down(): void {
		wp_using_ext_object_cache( false );

		parent::tear_down();
	}

	/**
	 * The other site's stored settings.
	 *
	 * @return array<string, mixed>
	 */
	private function other_site_settings(): array {
		switch_to_blog( self::$other_site );
		$settings = get_option( 'zero-spam-settings', array() );
		restore_current_blog();

		return (array) $settings;
	}

	/**
	 * Inserts log rows on a site.
	 *
	 * @param int    $blog_id Site.
	 * @param int    $count   Rows.
	 * @param string $ip      Address.
	 */
	private function log_on( int $blog_id, int $count, string $ip ): void {
		switch_to_blog( $blog_id );
		$this->insert_log_rows( $count, array( 'user_ip' => $ip ) );
		restore_current_blog();
	}

	/**
	 * Every read of the page counts and lists this network's sites only.
	 */
	public function test_reads_stay_on_the_network(): void {
		$here = array( get_main_site_id(), self::$site_b );

		$this->assertSame( 2, Network_Report::active_sites_count() );

		$overview = $this->rest( 'GET', '/zero-spam/v1/network/overview' )->get_data();
		$this->assertSame( 2, $overview['sites'] );
		$this->assertSame( 2, $overview['checked'] );
		$this->assertSame( 0, $overview['overrides'], 'The other network\'s max_logs is not a site change here.' );

		$this->assertSame( 2, $this->rest( 'GET', '/zero-spam/v1/network-settings/status' )->get_data()['total_sites'] );
		$this->assertSame( 2, $this->rest( 'GET', '/zero-spam/v1/network-settings' )->get_data()['max_logs']['total_sites'] );
		$this->assertSame( 2, $this->rest( 'GET', '/zero-spam/v1/network-settings/max_logs' )->get_data()['total_sites'] );

		$sites = $this->rest( 'GET', '/zero-spam/v1/network/sites' );
		$this->assertSame( '2', $sites->get_headers()['X-WP-Total'] );
		$this->assertSame( $here, array_column( $sites->get_data(), 'id' ) );

		$comparison = $this->rest( 'GET', '/zero-spam/v1/network/comparison', array(), array( 'per_page' => 20 ) );
		$this->assertSame( '2', $comparison->get_headers()['X-WP-Total'] );
		$this->assertSame( $here, array_column( $comparison->get_data()['sites'], 'id' ) );

		$compare = $this->rest( 'GET', '/zero-spam/v1/network-settings/compare' );
		$this->assertSame( '2', $compare->get_headers()['X-WP-Total'] );
		$this->assertSame( $here, array_column( $compare->get_data()['sites'], 'id' ) );
		$this->assertStringNotContainsString( '1234', (string) wp_json_encode( $compare->get_data() ) );

		$this->assertSame( $here, Network_Report::sites_following( array( 'block_handler' ) ) );
		$this->assertSame( $here, Network_Report::sites_following( array( 'max_logs' ) ) );
	}

	/**
	 * Statistics read this network's log tables, and an address on the other network is no multi-site attacker here.
	 */
	public function test_stats_stay_on_the_network(): void {
		$this->log_on( get_main_site_id(), 2, '203.0.113.60' );
		$this->log_on( self::$other_site, 5, '203.0.113.60' );

		$tables = Network_Report::log_tables();
		$this->assertSame( array( get_main_site_id(), self::$site_b ), array_keys( $tables['tables'] ) );
		$this->assertFalse( $tables['capped'] );

		$data = $this->rest( 'GET', '/zero-spam/v1/network/stats' )->get_data();
		$this->assertSame( 2, $data['totals']['blocked'] );
		$this->assertSame( 2, $data['totals']['sites'] );
		$this->assertSame( 2, $data['checked'] );
		$this->assertNotContains( self::$other_site, array_column( $data['sites'], 'id' ) );
		$this->assertSame( array(), $data['attackers'] );
	}

	/**
	 * The other network's site, and one that does not exist: 404, and nothing happens to it.
	 */
	public function test_site_routes_refuse_a_site_on_another_network(): void {
		$this->assertSame( 200, $this->rest( 'GET', '/zero-spam/v1/network-settings/site/' . self::$site_b )->get_status() );

		foreach ( array( self::$other_site, 999999 ) as $site ) {
			$this->assertSame( 404, $this->rest( 'GET', '/zero-spam/v1/network-settings/site/' . $site )->get_status() );
			$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/network-settings/site/' . $site . '/reset' )->get_status() );
		}

		$this->assertSame( 1234, $this->other_site_settings()['max_logs'], 'The reset reached the other network.' );
		$this->assertFalse( ( new \ZeroSpam\Includes\Network_Settings_Manager() )->reset_site( self::$other_site ) );
		$this->assertSame( 1234, $this->other_site_settings()['max_logs'] );
	}

	/**
	 * Apply to all sites: every mode, with or without a list of sites, leaves the other network alone.
	 *
	 * @dataProvider data_apply_bodies
	 *
	 * @param array<string, mixed> $body Body.
	 */
	public function test_apply_stays_on_the_network( array $body ): void {
		$before = $this->other_site_settings();

		$all = $this->rest( 'POST', '/zero-spam/v1/network-settings/apply-all', $body )->get_data();
		$this->assertSame( 2, $all['total_sites'] );
		$this->assertNotContains( self::$other_site, $all['updated_sites'] );
		$this->assertSame( $before, $this->other_site_settings() );

		$listed = $this->rest( 'POST', '/zero-spam/v1/network-settings/apply-all', $body + array( 'sites' => array( self::$other_site ) ) )->get_data();
		$this->assertSame( 0, $listed['total_sites'], 'A site named in the request is not on this network.' );
		$this->assertSame( $before, $this->other_site_settings() );
	}

	/**
	 * Modes of apply-all.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function data_apply_bodies(): array {
		return array(
			'follow'        => array( array( 'mode' => 'follow' ) ),
			'all'           => array( array( 'mode' => 'all' ) ),
			'all, force'    => array(
				array(
					'mode'  => 'all',
					'force' => true,
				),
			),
			'locked_only'   => array( array( 'mode' => 'locked_only' ) ),
			'defaults_only' => array( array( 'mode' => 'defaults_only' ) ),
		);
	}

	/**
	 * Follow with an empty list does work here: this is not a test of nothing.
	 */
	public function test_follow_still_works_here(): void {
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4000 ) );
		restore_current_blog();

		$data = $this->rest( 'POST', '/zero-spam/v1/network-settings/apply-all', array( 'mode' => 'follow' ) )->get_data();

		$this->assertSame( array( self::$site_b ), array_map( 'intval', $data['updated_sites'] ) );
		$this->assertSame( 1234, $this->other_site_settings()['max_logs'] );
	}

	/**
	 * A template applied to sites, all or named, leaves the other network alone.
	 */
	public function test_template_apply_stays_on_the_network(): void {
		$this->assertSame(
			201,
			$this->rest(
				'POST',
				'/zero-spam/v1/network-settings/templates',
				array(
					'name'     => 'Scoped',
					'slug'     => 'scoped',
					'settings' => array( 'max_logs' => 7777 ),
				)
			)->get_status()
		);

		$apply = function ( array $sites ): void {
			$this->assertSame(
				200,
				$this->rest(
					'POST',
					'/zero-spam/v1/network-settings/templates/scoped/apply',
					array(
						'scope' => 'sites',
						'force' => true,
						'sites' => $sites,
					)
				)->get_status()
			);
		};

		$apply( array() );
		$this->assertSame( 1234, $this->other_site_settings()['max_logs'] );

		$apply( array( self::$other_site ) );
		$this->assertSame( 1234, $this->other_site_settings()['max_logs'] );

		switch_to_blog( self::$site_b );
		$this->assertSame( 7777, get_option( 'zero-spam-settings' )['max_logs'], 'The template did reach this network.' );
		restore_current_blog();
	}

	/**
	 * Settings change emails go to this network's sites only.
	 */
	public function test_change_emails_stay_on_the_network(): void {
		switch_to_blog( self::$other_site );
		update_option( 'admin_email', 'other-network@example.com' );
		restore_current_blog();
		new \ZeroSpam\Includes\Network_Notifications();
		reset_phpmailer_instance();

		$this->set_network_setting( 'block_handler', '404', true );

		$to = array();
		foreach ( (array) tests_retrieve_phpmailer_instance()->mock_sent as $mail ) {
			$to[] = $mail['to'][0][0] ?? '';
		}
		reset_phpmailer_instance();

		$this->assertNotEmpty( $to );
		$this->assertNotContains( 'other-network@example.com', $to );
	}

	/**
	 * Under a persistent object cache the index of blocks answers for one network only.
	 */
	public function test_block_index_is_per_network_under_an_object_cache(): void {
		wp_using_ext_object_cache( true );

		$this->assertSame( 201, $this->rest( 'POST', '/zero-spam/v1/network/blocks', array( 'ip' => '203.0.113.91' ) )->get_status() );
		$this->assertNotNull( Network_Blocks::find( '203.0.113.91' ) );

		$this->on_network(
			self::$other_network,
			function () {
				$this->assertSame( 0, Network_Blocks::count(), 'The other network read this network\'s index.' );
				$this->assertNull( Network_Blocks::find( '203.0.113.91' ) );

				switch_to_blog( self::$other_site );
				delete_option( 'zero-spam-ipinfo' ); // Nothing to look the address up with: no request.
				$checks = ( new Access() )->check_blocked( array(), '203.0.113.91', array() );
				restore_current_blog();
				$this->assertTrue( empty( $checks['blocked']['blocked'] ), 'A visitor of the other network was blocked.' );
			}
		);

		$this->assertSame( 1, Network_Blocks::count() );
	}

	/**
	 * Another network's block with this network's index: its rows are not in the index.
	 */
	public function test_block_index_rows_are_of_this_network(): void {
		$this->on_network(
			self::$other_network,
			function () {
				$this->assertNotNull(
					Network_Blocks::save(
						array(
							'user_ip'      => '203.0.113.92',
							'blocked_type' => 'permanent',
							'start_block'  => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
						)
					)
				);
			}
		);
		$this->assertSame( 201, $this->rest( 'POST', '/zero-spam/v1/network/blocks', array( 'ip' => '203.0.113.93' ) )->get_status() );

		Network_Blocks::flush();
		$index = Network_Blocks::index();

		$this->assertSame( 1, $index['count'] );
		$this->assertSame( array( '203.0.113.93' ), array_keys( (array) $index['ips'] ) );
		$this->assertNull( Network_Blocks::find( '203.0.113.92' ) );
	}

	/**
	 * Under a persistent object cache the overview, compare pages and statistics are per network.
	 */
	public function test_report_caches_are_per_network_under_an_object_cache(): void {
		wp_using_ext_object_cache( true );
		$this->log_on( get_main_site_id(), 2, '203.0.113.61' );

		$this->assertSame( 2, Network_Report::overview()['sites'] );
		$this->assertSame( 2, Network_Report::compare( 1, 100 )['total'] );
		$this->assertSame( 2, Network_Report::stats( '30' )['totals']['blocked'] );

		$this->on_network(
			self::$other_network,
			function () {
				$this->assertSame( 1, Network_Report::overview()['sites'] );
				$this->assertSame( 1, Network_Report::compare( 1, 100 )['total'] );
				$this->assertSame( 0, Network_Report::stats( '30' )['totals']['blocked'] );

				// Clearing there leaves this network's caches.
				Network_Report::flush();
				Network_Report::flush_stats();
			}
		);

		$queries = $this->capture_queries(
			function () {
				$this->assertSame( 2, Network_Report::overview()['sites'] );
				$this->assertSame( 2, Network_Report::compare( 1, 100 )['total'] );
				$this->assertSame( 2, Network_Report::stats( '30' )['totals']['blocked'] );
			}
		);
		$this->assertSame( array(), $queries, 'A cache of this network was cleared or missed.' );
	}

	/**
	 * Under a persistent object cache, one network's settings do not answer for another.
	 */
	public function test_network_settings_cache_is_per_network_under_an_object_cache(): void {
		wp_using_ext_object_cache( true );

		$settings = new Network_Settings();
		$this->assertTrue( $settings->is_locked( 'block_handler' ) );

		$this->on_network(
			self::$other_network,
			function () use ( $settings ) {
				$this->assertFalse( $settings->is_locked( 'block_handler' ), 'This network\'s lock applied on the other network.' );
				$this->assertSame( array(), $settings->get_network_settings()['settings'] );
			}
		);

		$this->assertTrue( $settings->is_locked( 'block_handler' ) );
	}

	/**
	 * Uninstall removes the per-network cache entries of every network.
	 */
	public function test_cache_keys_are_built_from_the_names_uninstall_knows(): void {
		$uninstall = (string) file_get_contents( dirname( __DIR__, 3 ) . '/uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file of the plugin.

		$this->assertSame( Network_Blocks::TRANSIENT . '_' . get_current_network_id(), Network_Blocks::transient_key() );

		foreach ( array( Network_Blocks::TRANSIENT, Network_Report::REPORT_TRANSIENT, Network_Report::COMPARE_TRANSIENT, Network_Settings::CACHE_KEY ) as $name ) {
			$this->assertStringContainsString( "'{$name}'", $uninstall );
		}
		$this->assertStringContainsString( 'get_networks(', $uninstall );
	}

	/**
	 * A super admin of the other network, logged in there, runs the code.
	 *
	 * @param callable $callback Code.
	 * @return mixed What the code returned.
	 */
	private function as_other_networks_admin( callable $callback ) {
		$previous = get_current_user_id();
		$user     = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_network_option( self::$other_network, 'site_admins', array( get_userdata( $user )->user_login ) );
		wp_set_current_user( $user );

		try {
			return $this->on_network( self::$other_network, $callback );
		} finally {
			wp_set_current_user( $previous );
		}
	}

	/**
	 * Another network's administrator does not read this network's audit
	 * entries (who, from which address, what changed), nor counts them.
	 */
	public function test_audit_log_stays_on_the_network(): void {
		global $wpdb;

		$manager = new \ZeroSpam\Includes\Network_Settings_Manager();
		$manager->record( 'block', null, null, 'first-network-entry' );

		list( $json, $total, $own ) = $this->as_other_networks_admin(
			function () use ( $manager ) {
				$manager->record( 'block', null, null, 'second-network-entry' );
				$page = $this->rest( 'GET', '/zero-spam/v1/network-settings/audit', array(), array( 'page' => 1 ) );

				return array(
					(string) wp_json_encode( $page->get_data() ),
					(int) $page->get_headers()['X-WP-Total'],
					$manager->count_audit_log( array( 'action_type' => 'block' ) ),
				);
			}
		);

		$this->assertStringNotContainsString( 'first-network-entry', $json );
		$this->assertStringContainsString( 'second-network-entry', $json );
		$this->assertSame( 1, $total );
		$this->assertSame( 1, $own );

		// And the other way round; each entry records its network.
		$here = (string) wp_json_encode( $this->rest( 'GET', '/zero-spam/v1/network-settings/audit', array(), array( 'page' => 1 ) )->get_data() );
		$this->assertStringContainsString( 'first-network-entry', $here );
		$this->assertStringNotContainsString( 'second-network-entry', $here );

		$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT network_id FROM %i WHERE new_value = %s', $wpdb->base_prefix . DB::$tables['network_audit'], 'second-network-entry' ) );
		$this->assertSame( self::$other_network, (int) $stored );
	}

	/**
	 * Saves a custom template here.
	 *
	 * @param string $slug Slug.
	 * @param int    $max  Its `max_logs`.
	 */
	private function custom_template( string $slug, int $max = 7777 ): void {
		$this->assertSame(
			201,
			$this->rest(
				'POST',
				'/zero-spam/v1/network-settings/templates',
				array(
					'name'     => 'Template ' . $slug,
					'slug'     => $slug,
					'settings' => array( 'max_logs' => $max ),
				)
			)->get_status()
		);
	}

	/**
	 * Another network's administrator does not list, read, change, apply or
	 * delete this network's custom template: 404, and it is unchanged.
	 */
	public function test_custom_templates_stay_on_the_network(): void {
		$this->custom_template( 'first-network' );
		$route = '/zero-spam/v1/network-settings/templates/first-network';

		$this->as_other_networks_admin(
			function () use ( $route ) {
				$list = (array) $this->rest( 'GET', '/zero-spam/v1/network-settings/templates' )->get_data();
				$this->assertArrayNotHasKey( 'first-network', $list );

				$this->assertSame( 404, $this->rest( 'GET', $route )->get_status() );
				$this->assertSame( 404, $this->rest( 'PUT', $route, array( 'name' => 'Taken' ) )->get_status() );
				$this->assertSame( 404, $this->rest( 'DELETE', $route )->get_status() );
				$this->assertSame( 404, $this->rest( 'POST', $route . '/apply', array( 'scope' => 'network' ) )->get_status() );
				$this->assertSame(
					404,
					$this->rest(
						'POST',
						$route . '/apply',
						array(
							'scope' => 'sites',
							'sites' => array( self::$other_site ),
						)
					)->get_status()
				);

				$manager = new \ZeroSpam\Includes\Network_Templates();
				$this->assertNull( $manager->get_template( 'first-network' ) );
				$this->assertFalse( $manager->update_template( 'first-network', array( 'name' => 'Taken' ) ) );
				$this->assertFalse( $manager->delete_template( 'first-network' ) );
			}
		);

		$template = $this->rest( 'GET', $route )->get_data();
		$this->assertSame( 'Template first-network', $template['name'] );
		$this->assertSame( array( 'max_logs' => 7777 ), $template['settings'] );

		$store = get_network_option( self::$other_network, Network_Settings::META_KEY, array() );
		$this->assertArrayNotHasKey( 'max_logs', $store['settings'] ?? array(), 'The template reached the other network.' );
	}

	/**
	 * A slug is unique per network: each network has its own, and changing or deleting one leaves the other.
	 */
	public function test_template_slugs_are_per_network(): void {
		$this->custom_template( 'shared-slug', 1111 );
		$route = '/zero-spam/v1/network-settings/templates/shared-slug';

		$this->assertSame(
			400,
			$this->rest(
				'POST',
				'/zero-spam/v1/network-settings/templates',
				array(
					'name'     => 'Again',
					'slug'     => 'shared-slug',
					'settings' => array( 'max_logs' => 1 ),
				)
			)->get_status(),
			'One slug per network.'
		);

		$this->as_other_networks_admin(
			function () use ( $route ) {
				$this->custom_template( 'shared-slug', 2222 );
				$this->assertSame( array( 'max_logs' => 2222 ), $this->rest( 'GET', $route )->get_data()['settings'] );

				$this->assertSame( 200, $this->rest( 'PUT', $route, array( 'settings' => array( 'max_logs' => 3333 ) ) )->get_status() );
				$this->assertSame( 200, $this->rest( 'POST', $route . '/apply', array( 'scope' => 'network' ) )->get_status() );
				$this->assertSame( 3333, get_site_option( Network_Settings::META_KEY )['settings']['max_logs']['value'] );
				$this->assertSame( 200, $this->rest( 'DELETE', $route )->get_status() );
				$this->assertSame( 404, $this->rest( 'GET', $route )->get_status() );
			}
		);

		$this->assertSame( array( 'max_logs' => 1111 ), $this->rest( 'GET', $route )->get_data()['settings'], 'The other network\'s change or delete reached this one.' );
	}

	/**
	 * How often a template was applied is counted per network.
	 */
	public function test_template_usage_is_per_network(): void {
		$manager = new \ZeroSpam\Includes\Network_Templates();
		$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/network-settings/templates/balanced/apply', array( 'scope' => 'network' ) )->get_status() );
		$this->assertSame( 1, $manager->get_template_usage( 'balanced' )['count'] );

		$this->as_other_networks_admin(
			function () use ( $manager ) {
				$this->assertSame( 0, $manager->get_template_usage( 'balanced' )['count'] );
			}
		);
	}

	/**
	 * Built-in templates are on every network, and applying one writes that network's values.
	 *
	 * @dataProvider data_built_in_templates
	 *
	 * @param string $slug Built-in slug.
	 */
	public function test_built_in_templates_resolve_on_every_network( string $slug ): void {
		$before = get_site_option( Network_Settings::META_KEY );

		$this->as_other_networks_admin(
			function () use ( $slug ) {
				$route    = '/zero-spam/v1/network-settings/templates/' . $slug;
				$template = $this->rest( 'GET', $route )->get_data();

				$this->assertSame( 'built_in', $template['type'] );
				$this->assertArrayHasKey( $slug, (array) $this->rest( 'GET', '/zero-spam/v1/network-settings/templates' )->get_data() );
				$this->assertSame( 200, $this->rest( 'POST', $route . '/apply', array( 'scope' => 'network' ) )->get_status() );
				$this->assertNotEmpty( array_intersect_key( $template['settings'], get_site_option( Network_Settings::META_KEY )['settings'] ) );
			}
		);

		$this->assertSame( $before, get_site_option( Network_Settings::META_KEY ), 'It wrote to this network.' );
	}

	/**
	 * The built-in templates.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_built_in_templates(): array {
		return array(
			'strict_protection' => array( 'strict_protection' ),
			'balanced'          => array( 'balanced' ),
			'relaxed'           => array( 'relaxed' ),
		);
	}
}
