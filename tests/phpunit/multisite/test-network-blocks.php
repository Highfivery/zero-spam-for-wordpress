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
			Network_Blocks::TRANSIENT,
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
}
