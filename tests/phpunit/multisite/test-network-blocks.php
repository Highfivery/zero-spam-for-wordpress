<?php
/**
 * Tests for network-wide IP blocks.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Blocks;
use ZeroSpam\Tests\TestCase;

/**
 * A network block stops the address on every site, matched in canonical
 * form like site blocks, from one cached read on the front end; removing it
 * can be undone; both are in the network audit log.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Blocks
 * @covers \ZeroSpam\Core\Access::check_blocked
 * @covers \ZeroSpam\Includes\Rest\Network_Controller
 */
class Test_Network_Blocks extends TestCase {

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
	 * Empties the network blocks and their index; logs in a super admin.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Network_Blocks::table() ) );
		Network_Blocks::flush();
		$this->set_request( '/', self::DEFAULT_IP );

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
	}

	/**
	 * Whether the access check on a site blocks an address, and with which failed key.
	 *
	 * @param int    $blog_id Site.
	 * @param string $ip      Visitor IP.
	 * @return string|null Failed key, or null when not blocked.
	 */
	private function blocked_on( int $blog_id, string $ip ): ?string {
		switch_to_blog( $blog_id );
		self::reset_static_caches();

		try {
			$checks = ( new Access() )->check_blocked( array(), $ip, array() );
		} finally {
			restore_current_blog();
		}

		return empty( $checks['blocked']['blocked'] ) ? null : (string) $checks['blocked']['details']['failed'];
	}

	/**
	 * Blocks an address network-wide through the route.
	 *
	 * @param array<string, mixed> $body Body.
	 */
	private function network_block( array $body ): WP_REST_Response {
		return $this->rest( 'POST', '/zero-spam/v1/network/blocks', $body );
	}

	/**
	 * A network block applies on every site; an unrelated address is not matched.
	 */
	public function test_enforced_on_every_site(): void {
		$response = $this->network_block(
			array(
				'ip'     => '203.0.113.70',
				'reason' => 'Spam on several sites',
			)
		);
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '203.0.113.70', $response->get_data()['ip'] );
		$this->assertTrue( $response->get_data()['active'] );

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( $blog_id, '203.0.113.70' ), "Site {$blog_id}" );
			$this->assertNull( $this->blocked_on( $blog_id, '203.0.113.71' ), "Site {$blog_id}: unrelated address" );
		}

		// Blocking it again replaces the block (200), it does not add a second one.
		$this->assertSame( 200, $this->network_block( array( 'ip' => '203.0.113.70' ) )->get_status() );
		$this->assertSame( 1, Network_Blocks::count() );
	}

	/**
	 * IPv6 addresses match in canonical form, whichever way they were written.
	 */
	public function test_ipv6_matched_in_canonical_form(): void {
		$this->assertSame( '2001:db8::5', $this->network_block( array( 'ip' => '2001:DB8:0:0::5' ) )->get_data()['ip'] );

		$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( self::$site_b, '2001:db8::5' ) );
		$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( self::$site_b, '2001:DB8::5' ) );
	}

	/**
	 * A temporary block that has ended is not enforced; ranges are refused.
	 */
	public function test_ended_blocks_and_ranges(): void {
		global $wpdb;

		$wpdb->insert(
			Network_Blocks::table(),
			array(
				'blocked_type' => 'temporary',
				'user_ip'      => '203.0.113.72',
				'date_added'   => current_time( 'mysql' ),
				'start_block'  => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'end_block'    => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			)
		);
		Network_Blocks::flush();
		$this->assertNull( $this->blocked_on( self::$site_b, '203.0.113.72' ) );

		$range = $this->network_block( array( 'ip' => '203.0.113.0/24' ) );
		$this->assertSame( 400, $range->get_status() );
		$this->assertSame( 'Blocks are for single addresses; ranges (CIDR) are not supported yet.', $range->get_data()['data']['params']['ip'] );

		$this->assertSame( 400, $this->network_block( array( 'ip' => 'not an ip' ) )->get_status() );
		$this->assertSame(
			400,
			$this->network_block(
				array(
					'ip'   => '203.0.113.73',
					'type' => 'temporary',
				)
			)->get_status(),
			'A temporary block needs an end.'
		);
	}

	/**
	 * A network block whose start is NULL (an imported or hand-written row)
	 * is enforced on every request on every site.
	 */
	public function test_block_with_a_null_start_is_always_enforced(): void {
		global $wpdb;

		$wpdb->insert(
			Network_Blocks::table(),
			array(
				'network_id'   => Network_Blocks::network_id(),
				'blocked_type' => 'permanent',
				'user_ip'      => '203.0.113.74',
				'date_added'   => current_time( 'mysql' ),
				'start_block'  => null,
				'end_block'    => null,
			)
		);
		Network_Blocks::flush();

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			for ( $i = 0; $i < 25; $i++ ) {
				$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( $blog_id, '203.0.113.74' ), "Site {$blog_id}, request {$i}" );
			}
		}
	}

	/**
	 * The front-end check reads the cached index, never the table, while it is complete.
	 */
	public function test_hot_path_is_one_cached_read(): void {
		$this->network_block( array( 'ip' => '203.0.113.74' ) );
		Network_Blocks::find( '203.0.113.74' );

		Network_Blocks::reset_request_cache();
		$queries = $this->capture_queries(
			function () {
				$this->assertNotNull( Network_Blocks::find( '203.0.113.74' ) );
				$this->assertNull( Network_Blocks::find( '203.0.113.75' ) );
			}
		);
		$this->assertSame( array(), preg_grep( '/wpzerospam_network_blocked/', $queries ), 'The table was read.' );
		$this->assertLessThanOrEqual( 2, count( $queries ), 'More than one cached read.' );

		// No network blocks at all: nothing is read once the index is cached.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Network_Blocks::table() ) );
		Network_Blocks::flush();
		Network_Blocks::find( '203.0.113.74' );
		$this->assertSame( array(), $this->capture_queries( fn () => Network_Blocks::find( '203.0.113.74' ) ) );
	}

	/**
	 * Beyond INDEX_MAX blocks, each check is one indexed query.
	 */
	public function test_large_lists_use_one_indexed_query(): void {
		$this->network_block( array( 'ip' => '203.0.113.76' ) );
		set_site_transient(
			Network_Blocks::transient_key(),
			array(
				'count' => Network_Blocks::INDEX_MAX + 1,
				'ips'   => null,
			)
		);
		Network_Blocks::reset_request_cache();
		Network_Blocks::index();

		$found   = null;
		$queries = $this->capture_queries(
			function () use ( &$found ) {
				$found = Network_Blocks::find( '203.0.113.76' );
			}
		);

		$this->assertNotNull( $found );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( '`user_ip` = ', $queries[0] );
		$this->assertStringContainsString( 'LIMIT 1', $queries[0] );
	}

	/**
	 * Removing a block returns an undo token that restores it; both are audited.
	 */
	public function test_delete_undo_and_audit(): void {
		global $wpdb;

		$id = (int) $this->network_block( array( 'ip' => '203.0.113.77' ) )->get_data()['id'];
		$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( self::$site_b, '203.0.113.77' ) );

		$deleted = $this->rest( 'DELETE', '/zero-spam/v1/network/blocks/' . $id );
		$this->assertSame( 200, $deleted->get_status() );
		$this->assertNull( $this->blocked_on( self::$site_b, '203.0.113.77' ) );
		$this->assertSame( 404, $this->rest( 'DELETE', '/zero-spam/v1/network/blocks/' . $id )->get_status() );

		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $deleted->get_data()['undo_token'] );
		$this->assertSame( 1, $undo->get_data()['restored'] );
		$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( self::$site_b, '203.0.113.77' ) );

		$actions = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT action_type FROM %i WHERE new_value LIKE %s ORDER BY audit_id ASC',
				$wpdb->base_prefix . DB::$tables['network_audit'],
				'%203.0.113.77%'
			)
		);
		$this->assertSame( array( 'block', 'unblock' ), $actions );
	}

	/**
	 * A row of another network of the same install.
	 *
	 * @param string $ip IP address.
	 * @return int Block ID.
	 */
	private function other_networks_block( string $ip ): int {
		global $wpdb;

		$wpdb->insert(
			Network_Blocks::table(),
			array(
				'network_id'   => (int) get_current_network_id() + 1,
				'blocked_type' => 'permanent',
				'user_ip'      => $ip,
				'date_added'   => current_time( 'mysql' ),
				'start_block'  => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			)
		);
		$id = (int) $wpdb->insert_id;
		$this->assertGreaterThan( 0, $id );
		Network_Blocks::flush();

		return $id;
	}

	/**
	 * Blocks carry their network: another network's are not listed, enforced, counted or removable from here.
	 */
	public function test_blocks_are_scoped_to_the_current_network(): void {
		$id = $this->other_networks_block( '203.0.113.80' );

		$this->assertSame( 0, Network_Blocks::count() );
		$this->assertNull( Network_Blocks::read( $id ) );
		$this->assertNull( Network_Blocks::read_by_ip( '203.0.113.80' ) );
		$this->assertSame( array(), Network_Blocks::by_ips( array( '203.0.113.80' ) ) );
		$this->assertNull( $this->blocked_on( self::$site_b, '203.0.113.80' ) );
		$this->assertSame( array(), $this->rest( 'GET', '/zero-spam/v1/network/blocks' )->get_data() );
		$this->assertSame( 404, $this->rest( 'DELETE', '/zero-spam/v1/network/blocks/' . $id )->get_status() );
		$this->assertFalse( Network_Blocks::delete( $id ) );

		// This network can block the same address; its row is its own.
		$saved = $this->network_block( array( 'ip' => '203.0.113.80' ) );
		$this->assertSame( 201, $saved->get_status() );
		$this->assertSame( (int) get_current_network_id(), (int) Network_Blocks::read( (int) $saved->get_data()['id'] )['network_id'] );
		$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( self::$site_b, '203.0.113.80' ) );

		// Replacing it leaves the other network's row alone.
		$this->assertSame( 200, $this->network_block( array( 'ip' => '203.0.113.80', 'reason' => 'Again' ) )->get_status() ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->assertSame( 1, Network_Blocks::count() );

		// Undo writes the row back to this network.
		$undo = $this->rest( 'DELETE', '/zero-spam/v1/network/blocks/' . $saved->get_data()['id'] )->get_data()['undo_token'];
		$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/undo/' . $undo )->get_status() );
		$this->assertSame( 1, Network_Blocks::count() );
		$this->assertSame( Access::FAILED_NETWORK_BLOCK, $this->blocked_on( self::$site_b, '203.0.113.80' ) );
	}

	/**
	 * Loopback and unspecified addresses cannot be blocked; private ranges can.
	 *
	 * @dataProvider data_addresses_never_blocked
	 *
	 * @param string $ip Address.
	 */
	public function test_loopback_and_unspecified_addresses_are_refused( string $ip ): void {
		$response = $this->network_block( array( 'ip' => $ip ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'ip', $response->get_data()['data']['params'] );
		$this->assertSame( 0, Network_Blocks::count() );
	}

	/**
	 * Addresses that are this server, or no address.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_addresses_never_blocked(): array {
		return array(
			'127.0.0.1'        => array( '127.0.0.1' ),
			'127.9.9.9'        => array( '127.9.9.9' ),
			'::1'              => array( '::1' ),
			'long ::1'         => array( '0:0:0:0:0:0:0:1' ),
			'0.0.0.0'          => array( '0.0.0.0' ),
			'::'               => array( '::' ),
			'mapped 127.0.0.1' => array( '::ffff:127.0.0.1' ),
		);
	}

	/**
	 * Private ranges stay allowed (a proxy's clients may really use them).
	 */
	public function test_private_ranges_can_be_blocked(): void {
		$this->assertSame( 201, $this->network_block( array( 'ip' => '10.0.0.5' ) )->get_status() );
		$this->assertSame( 201, $this->network_block( array( 'ip' => '192.168.1.5' ) )->get_status() );
	}

	/**
	 * Blocking your own address on every site needs `confirm`.
	 */
	public function test_own_address_needs_confirmation(): void {
		$this->set_request( '/', '2001:db8::77' );
		self::reset_static_caches();

		$refused = $this->network_block( array( 'ip' => '2001:DB8:0:0::77' ) );
		$this->assertSame( 409, $refused->get_status() );
		$this->assertSame( 'rest_would_lock_out', $refused->get_data()['code'] );
		$this->assertSame( '2001:db8::77', $refused->get_data()['data']['ip'] );
		$this->assertSame( 0, Network_Blocks::count() );

		$this->assertSame(
			201,
			$this->network_block(
				array(
					'ip'      => '2001:db8::77',
					'confirm' => true,
				)
			)->get_status()
		);
		$this->assertSame( 201, $this->network_block( array( 'ip' => '203.0.113.81' ) )->get_status(), 'Another address needs no confirmation.' );
	}

	/**
	 * A network block counts as "blocked now" for the no-lockout list and its guard.
	 */
	public function test_no_lockout_guard_sees_network_blocks(): void {
		$ip = '203.0.113.82';
		$this->set_request( '/', $ip );
		self::reset_static_caches();
		update_option( \ZeroSpam\Core\Admin_Safe_IPs::OPTION, array( $ip ), false );

		$before = $this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' )->get_data()['items'][0];
		$this->assertFalse( $before['blocked'] );

		$this->assertSame(
			201,
			$this->network_block(
				array(
					'ip'      => $ip,
					'confirm' => true,
				)
			)->get_status()
		);

		$after = $this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' )->get_data()['items'][0];
		$this->assertTrue( $after['blocked'] );

		$removal = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/' . $ip );
		$this->assertSame( 409, $removal->get_status() );
		$this->assertSame( 'rest_would_lock_out', $removal->get_data()['code'] );
	}

	/**
	 * Two requests blocking one address at once: the loser gets 409, not 500.
	 */
	public function test_save_race_on_the_unique_key_is_a_conflict(): void {
		$existing = (int) $this->network_block( array( 'ip' => '203.0.113.83' ) )->get_data()['id'];

		// The other request's row is not there yet when this request looks, once.
		$hide = static function ( $query ) use ( &$hide ) {
			if ( str_starts_with( (string) $query, 'SELECT * FROM' ) && str_contains( (string) $query, "`user_ip` = '203.0.113.83'" ) ) {
				remove_filter( 'query', $hide );

				return str_replace( "`user_ip` = '203.0.113.83'", '1 = 0', (string) $query );
			}

			return $query;
		};
		add_filter( 'query', $hide );

		$response = $this->network_block( array( 'ip' => '203.0.113.83' ) );

		remove_filter( 'query', $hide );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'zerospam_block_exists', $response->get_data()['code'] );
		$this->assertSame( $existing, $response->get_data()['data']['block_id'] );
		$this->assertSame( 1, Network_Blocks::count() );
	}

	/**
	 * A network block that has ended does not count as blocked now.
	 */
	public function test_no_lockout_guard_ignores_an_ended_network_block(): void {
		global $wpdb;

		$ip = '203.0.113.84';
		$wpdb->insert(
			Network_Blocks::table(),
			array(
				'network_id'   => (int) get_current_network_id(),
				'blocked_type' => 'temporary',
				'user_ip'      => $ip,
				'date_added'   => current_time( 'mysql' ),
				'start_block'  => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'end_block'    => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			)
		);
		Network_Blocks::flush();
		update_option( \ZeroSpam\Core\Admin_Safe_IPs::OPTION, array( $ip ), false );

		$this->assertFalse( $this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' )->get_data()['items'][0]['blocked'] );
	}
}
