<?php
/**
 * Tests for the database schema (DB 1.5).
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * DB 1.5 adds the network-wide blocks table and three audit actions. The
 * tables WordPress created while loading match DB::schema(), so dbDelta()
 * has nothing left to change. DB::update() itself is never called in a test.
 *
 * @covers \ZeroSpam\Includes\DB::schema
 */
class Test_DB_Schema extends TestCase {

	/**
	 * The version and the new table's definition.
	 */
	public function test_schema_has_the_network_blocks_table(): void {
		global $wpdb;

		$this->assertSame( '1.5', DB::DB_VERSION );
		$this->assertSame( 'wpzerospam_network_blocked', DB::$tables['network_blocked'] );

		$create = preg_grep( '/^CREATE TABLE ' . preg_quote( $wpdb->base_prefix . 'wpzerospam_network_blocked', '/' ) . ' \(/', DB::schema() );
		$this->assertCount( 1, $create );
		$sql = (string) reset( $create );
		$this->assertStringContainsString( 'UNIQUE KEY user_ip (user_ip)', $sql );
		$this->assertStringContainsString( "blocked_type enum('permanent','temporary')", $sql );

		$audit = (string) implode( "\n", preg_grep( '/wpzerospam_network_audit/', DB::schema() ) );
		foreach ( DB::AUDIT_ACTIONS as $action ) {
			$this->assertStringContainsString( "'{$action}'", $audit, "The audit action enum lacks {$action}." );
		}
		foreach ( array( 'block', 'unblock', 'migrate' ) as $action ) {
			$this->assertContains( $action, DB::AUDIT_ACTIONS );
		}
	}

	/**
	 * The table exists with its columns.
	 */
	public function test_network_blocks_table_exists(): void {
		global $wpdb;

		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->base_prefix . DB::$tables['network_blocked'] ) );

		$this->assertSame(
			array( 'blocked_id', 'blocked_type', 'user_ip', 'date_added', 'start_block', 'end_block', 'reason', 'created_by' ),
			$columns
		);
	}

	/**
	 * The installed tables match the schema: dbDelta() has nothing to change and creates or alters nothing.
	 */
	public function test_installed_tables_match_the_schema(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$changes = array();
		$queries = $this->capture_queries(
			function () use ( &$changes ) {
				$changes = dbDelta( DB::schema(), false );
			}
		);

		$this->assertSame( array(), $changes );
		$this->assertSame( array(), preg_grep( '/^\s*(CREATE|ALTER|DROP)\b/i', $queries ) );
	}
}
