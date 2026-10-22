<?php
/**
 * Tests for blocked IP addresses written in different forms.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\TestCase;

/**
 * Visitors are reported in canonical form (User::normalize_ip()), so blocks
 * are stored, looked up and searched that way, and older blocks are rewritten
 * once, keeping the longer-lasting block when two collide.
 *
 * @covers \ZeroSpam\Includes\DB::blocked
 * @covers \ZeroSpam\Includes\Migrations::canonicalize_blocked_ips
 * @covers \ZeroSpam\Core\Admin\Tables\LogTable::prepare_items
 * @covers \ZeroSpam\Core\Admin\Tables\BlockedTable::prepare_items
 */
class Test_Blocked_IP_Forms extends TestCase {

	/**
	 * The blocked table's rows for an address, as stored.
	 *
	 * @param string $ip IP address (compared by MySQL's case-insensitive collation).
	 * @return array<int, array<string, mixed>>
	 */
	private function blocked_rows( string $ip ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE user_ip = %s ORDER BY blocked_id', $wpdb->prefix . DB::$tables['blocked'], $ip ),
			ARRAY_A
		);
	}

	/**
	 * Whether the block check stops the current visitor.
	 */
	private function visitor_is_blocked(): bool {
		$checks = ( new Access() )->check_blocked( array(), (string) \ZeroSpam\Core\User::get_ip(), array() );

		return ! empty( $checks['blocked']['blocked'] );
	}

	/**
	 * A block saved from a server that reported an uppercase, uncompressed
	 * address still blocks that visitor, now reported in canonical form,
	 * once the update has run.
	 */
	public function test_uncompressed_block_still_blocks_the_canonical_visitor(): void {
		$this->block( '2001:DB8:0:0:0:0:0:1' );
		$this->set_request( '/', '2001:DB8:0:0:0:0:0:1' );

		$this->assertSame( '2001:db8::1', \ZeroSpam\Core\User::get_ip() );

		( new Migrations() )->canonicalize_blocked_ips();

		$this->assertTrue( $this->visitor_is_blocked() );
	}

	/**
	 * Looking up a block finds it whichever form the address is given in.
	 */
	public function test_lookup_accepts_any_form(): void {
		$this->block( '2001:db8::7' );

		$this->assertIsArray( DB::blocked( '2001:DB8:0:0::7' ) );
		$this->assertIsArray( DB::blocked( '2001:db8::7' ) );
		$this->assertNull( DB::blocked( '2001:db8::8' ) );
	}

	/**
	 * New and updated blocks are saved in canonical form.
	 */
	public function test_new_block_is_saved_canonical(): void {
		DB::blocked(
			array(
				'user_ip'      => '2001:0DB8:0000:0000:0000:0000:0000:0009',
				'blocked_type' => 'permanent',
				'key_type'     => 'ip',
				'reason'       => 'Test',
			)
		);

		$rows = $this->blocked_rows( '2001:db8::9' );
		$this->assertCount( 1, $rows );
		$this->assertSame( '2001:db8::9', $rows[0]['user_ip'] );

		// Blocking the same address in another form updates that block.
		DB::blocked(
			array(
				'user_ip'      => '2001:DB8::9',
				'blocked_type' => 'temporary',
				'key_type'     => 'ip',
				'reason'       => 'Again',
			)
		);
		$rows = $this->blocked_rows( '2001:db8::9' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'Again', $rows[0]['reason'] );

		DB::blocked(
			array(
				'user_ip'      => '::ffff:192.0.2.44',
				'blocked_type' => 'permanent',
				'key_type'     => 'ip',
			)
		);
		$this->assertCount( 1, $this->blocked_rows( '192.0.2.44' ), 'IPv4-mapped addresses are stored as IPv4.' );
	}

	/**
	 * The migration rewrites IPv6 rows and, when two rows collide, keeps the
	 * longer-lasting block; IPv4 and canonical rows are left alone.
	 */
	public function test_migration_rewrites_and_dedupes(): void {
		$future = wp_date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
		$later  = wp_date( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS );

		$ipv4            = $this->block( '192.0.2.50' );
		$canonical       = $this->block( '2001:db8::a' );
		$short           = $this->block( '2001:db8::1', 'temporary', null, $future );
		$long            = $this->block( '2001:DB8:0:0:0:0:0:1', 'temporary', null, $later );
		$permanent_dup   = $this->block( '2001:0db8::2' );
		$temporary_canon = $this->block( '2001:db8::2', 'temporary', null, $later );
		$mapped          = $this->block( '::ffff:198.51.100.9' );
		$lone            = $this->block( '2001:DB8::B' );

		( new Migrations() )->canonicalize_blocked_ips();

		$this->assertSame( '192.0.2.50', DB::blocked( $ipv4 )['user_ip'] );
		$this->assertSame( '2001:db8::a', DB::blocked( $canonical )['user_ip'] );

		$rows = $this->blocked_rows( '2001:db8::1' );
		$this->assertCount( 1, $rows, 'Colliding rows are merged.' );
		$this->assertSame( (string) $long, (string) $rows[0]['blocked_id'], 'The block ending later is kept.' );
		$this->assertSame( '2001:db8::1', $rows[0]['user_ip'] );
		$this->assertNull( DB::blocked( $short ) );

		$rows = $this->blocked_rows( '2001:db8::2' );
		$this->assertCount( 1, $rows );
		$this->assertSame( (string) $permanent_dup, (string) $rows[0]['blocked_id'], 'A permanent block outlasts a temporary one.' );
		$this->assertNull( DB::blocked( $temporary_canon ) );

		$this->assertSame( '198.51.100.9', DB::blocked( $mapped )['user_ip'] );
		$this->assertSame( '2001:db8::b', DB::blocked( $lone )['user_ip'] );
	}

	/**
	 * The migration reads the table in batches.
	 */
	public function test_migration_handles_more_rows_than_one_batch(): void {
		for ( $i = 1; $i <= Migrations::BLOCKED_IP_BATCH_SIZE + 5; $i++ ) {
			$this->block( sprintf( '2001:DB8:0:0:0:0:1:%X', $i ) );
		}

		( new Migrations() )->canonicalize_blocked_ips();

		$last = Migrations::BLOCKED_IP_BATCH_SIZE + 5;
		$this->assertCount( 1, $this->blocked_rows( sprintf( '2001:db8::1:%x', $last ) ) );
		$this->assertSame( sprintf( '2001:db8::1:%x', $last ), $this->blocked_rows( sprintf( '2001:db8::1:%x', $last ) )[0]['user_ip'] );
	}

	/**
	 * Searching the Detection Log for an address finds rows logged as typed
	 * and rows logged in canonical form.
	 */
	public function test_log_search_matches_canonical_form(): void {
		$this->admin_screen( 'dashboard_page_wordpress-zero-spam-dashboard' );
		$this->as_role( 'administrator' );
		$this->insert_log_rows( 1, array( 'user_ip' => '2001:db8::c' ) );
		$this->insert_log_rows( 1, array( 'user_ip' => '2001:DB8:0:0:0:0:0:C' ) );
		$this->insert_log_rows( 2 );

		$_GET     = array( 's' => '2001:DB8:0:0:0:0:0:C' );
		$_REQUEST = $_GET;

		try {
			$table = new \ZeroSpam\Core\Admin\Tables\LogTable();
			$table->prepare_items();
		} finally {
			$_GET     = array();
			$_REQUEST = array();
		}

		$this->assertCount( 2, $table->items, 'The canonical row, and the row logged in the form searched for.' );
	}

	/**
	 * Searching Blocked IPs in any form finds the canonical block.
	 */
	public function test_blocked_search_matches_canonical_form(): void {
		$this->admin_screen( 'dashboard_page_wordpress-zero-spam-dashboard' );
		$this->as_role( 'administrator' );
		$this->block( '2001:db8::d' );
		$this->block( '192.0.2.60' );

		$_GET     = array( 's' => '2001:DB8:0:0:0:0:0:D' );
		$_REQUEST = $_GET;

		try {
			$table = new \ZeroSpam\Core\Admin\Tables\BlockedTable();
			$table->prepare_items();
		} finally {
			$_GET     = array();
			$_REQUEST = array();
		}

		$this->assertCount( 1, $table->items );
		$this->assertSame( '2001:db8::d', $table->items[0]['user_ip'] );
	}
}
