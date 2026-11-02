<?php
/**
 * Tests for the database schema (DB 1.6).
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

		$this->assertSame( '1.6', DB::DB_VERSION );
		$this->assertSame( 'wpzerospam_network_blocked', DB::$tables['network_blocked'] );

		$create = preg_grep( '/^CREATE TABLE ' . preg_quote( $wpdb->base_prefix . 'wpzerospam_network_blocked', '/' ) . ' \(/', DB::schema() );
		$this->assertCount( 1, $create );
		$sql = (string) reset( $create );
		$this->assertStringContainsString( 'UNIQUE KEY network_ip (network_id,user_ip)', $sql );
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
			array( 'blocked_id', 'network_id', 'blocked_type', 'user_ip', 'date_added', 'start_block', 'end_block', 'reason', 'created_by' ),
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

	/**
	 * A copy that ran this table before `network_id` (DB 1.5, development
	 * only, never released) gets the column at its next upgrade: why DB 1.6
	 * exists. Its rows belong to network 1.
	 */
	public function test_upgrade_adds_the_network_column_to_a_table_without_it(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$real = $wpdb->base_prefix . DB::$tables['network_blocked'];
		$old  = $real . '_old';
		$sql  = preg_grep( '/^CREATE TABLE ' . preg_quote( $real, '/' ) . ' \(/', DB::schema() );
		$sql  = str_replace( $real, $old, (string) reset( $sql ) );

		// The DB 1.5 table: no network_id, one key per address.
		$legacy = str_replace(
			array( "network_id bigint(20) unsigned NOT NULL DEFAULT 1,\n", 'UNIQUE KEY network_ip (network_id,user_ip)' ),
			array( '', 'UNIQUE KEY user_ip (user_ip)' ),
			$sql
		);
		$wpdb->query( $legacy ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own CREATE TABLE statement from DB::schema().
		$this->assertNotContains( 'network_id', $wpdb->get_col( "SHOW COLUMNS FROM {$old}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A table name built above.
		$wpdb->insert(
			$old,
			array(
				'blocked_type' => 'permanent',
				'user_ip'      => '203.0.113.5',
				'date_added'   => '2026-10-01 00:00:00',
			)
		);

		dbDelta( array( $sql ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A table name built above.
		$this->assertContains( 'network_id', $wpdb->get_col( "SHOW COLUMNS FROM {$old}" ) );
		$this->assertSame( array( '203.0.113.5', '1' ), array_values( (array) $wpdb->get_row( "SELECT user_ip, network_id FROM {$old}", ARRAY_A ) ) );
		$this->assertContains( 'network_ip', $wpdb->get_col( "SHOW INDEX FROM {$old}", 2 ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$wpdb->query( "DROP TABLE {$old}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A table name built above.
	}
}
