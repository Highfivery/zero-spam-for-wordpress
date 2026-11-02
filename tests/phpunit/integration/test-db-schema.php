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
 * DB 1.6 adds the network-wide blocks table, three audit actions and the
 * `network_id` of audit entries and custom templates. The
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

	/**
	 * Audit entries and templates carry their network; a slug is unique per network.
	 */
	public function test_schema_scopes_audit_and_templates_by_network(): void {
		global $wpdb;

		foreach ( array( 'network_audit', 'network_templates' ) as $table ) {
			$this->assertContains( 'network_id', $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->base_prefix . DB::$tables[ $table ] ) ), $table );
		}

		$templates = (string) implode( "\n", preg_grep( '/wpzerospam_network_templates/', DB::schema() ) );
		$this->assertStringContainsString( 'UNIQUE KEY network_slug (network_id,template_slug)', $templates );
		$this->assertStringNotContainsString( 'UNIQUE KEY template_slug', $templates );
	}

	/**
	 * The tables exactly as 5.7.14 (DB 1.4) created them, renamed for this test.
	 *
	 * @param string $audit     Audit table name.
	 * @param string $templates Templates table name.
	 * @return string[]
	 */
	private function tables_of_5_7_14( string $audit, string $templates ): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return array(
			"CREATE TABLE {$templates} (
			template_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			template_name varchar(255) NOT NULL,
			template_slug varchar(255) NOT NULL,
			template_type enum('built_in','custom') NOT NULL DEFAULT 'custom',
			settings longtext NOT NULL,
			description text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (template_id),
			UNIQUE KEY template_slug (template_slug),
			KEY template_type (template_type)
		) {$charset_collate};",
			"CREATE TABLE {$audit} (
			audit_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action_type enum('set','lock','unlock','apply','bulk','template','import','reset') NOT NULL DEFAULT 'set',
			setting_key varchar(255) DEFAULT NULL,
			old_value text DEFAULT NULL,
			new_value text DEFAULT NULL,
			affected_sites text DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL,
			user_login varchar(60) NOT NULL,
			ip_address varchar(39) NOT NULL,
			date_created datetime NOT NULL,
			PRIMARY KEY  (audit_id),
			KEY site_id (site_id),
			KEY action_type (action_type),
			KEY setting_key (setting_key),
			KEY user_id (user_id),
			KEY date_created (date_created)
		) {$charset_collate};",
		);
	}

	/**
	 * From 5.7.14: every row stays and goes to the main network (whichever
	 * that is); the old unique key goes, so a slug can repeat on another
	 * network; running it again, as every site does, changes nothing.
	 */
	public function test_upgrade_from_5_7_14_keeps_every_row_on_the_main_network(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$real_audit     = $wpdb->base_prefix . DB::$tables['network_audit'];
		$real_templates = $wpdb->base_prefix . DB::$tables['network_templates'];
		$audit          = $real_audit . '_old';
		$templates      = $real_templates . '_old';

		foreach ( $this->tables_of_5_7_14( $audit, $templates ) as $create ) {
			$wpdb->query( $create ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- A fixed statement above.
		}

		$wpdb->insert(
			$templates,
			array(
				'template_name' => 'Clinic',
				'template_slug' => 'clinic',
				'settings'      => '{"max_logs":5}',
				'created_by'    => 1,
				'created_at'    => '2026-01-01 00:00:00',
				'updated_at'    => '2026-01-01 00:00:00',
			)
		);
		foreach ( array( 'first', 'second' ) as $text ) {
			$wpdb->insert(
				$audit,
				array(
					'action_type'  => 'set',
					'new_value'    => $text,
					'user_id'      => 1,
					'user_login'   => 'admin',
					'ip_address'   => '192.0.2.1',
					'date_created' => '2026-01-01 00:00:00',
				)
			);
		}

		// What DB::update() does, on the renamed tables.
		$sql = array();
		foreach ( array( $real_audit => $audit, $real_templates => $templates ) as $real => $renamed ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short list.
			$sql[] = str_replace( $real, $renamed, (string) current( (array) preg_grep( '/^CREATE TABLE ' . preg_quote( $real, '/' ) . ' \(/', DB::schema() ) ) );
		}
		dbDelta( $sql );

		// A main network that is not number 1 (on a single site there is only network 1).
		$main = static fn () => 42;
		add_filter( 'get_main_network_id', $main );
		try {
			$expected = (int) get_main_network_id();
			DB::upgrade_network_tables( $audit, $templates );
		} finally {
			remove_filter( 'get_main_network_id', $main );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table names built above.
		$this->assertSame( array( 'clinic' ), $wpdb->get_col( "SELECT template_slug FROM {$templates} WHERE network_id = {$expected}" ) );
		$this->assertSame( array( 'first', 'second' ), $wpdb->get_col( "SELECT new_value FROM {$audit} WHERE network_id = {$expected} ORDER BY audit_id" ) );
		$this->assertSame( '{"max_logs":5}', $wpdb->get_var( "SELECT settings FROM {$templates}" ) );

		$keys = $wpdb->get_col( "SHOW INDEX FROM {$templates}", 2 );
		$this->assertContains( 'network_slug', $keys );
		$this->assertNotContains( 'template_slug', $keys );

		// The new audit actions are accepted.
		$this->assertSame(
			1,
			$wpdb->insert(
				$audit,
				array(
					'network_id'   => $expected,
					'action_type'  => 'block',
					'new_value'    => 'third',
					'user_id'      => 1,
					'user_login'   => 'admin',
					'ip_address'   => '192.0.2.1',
					'date_created' => '2026-01-01 00:00:00',
				)
			)
		);
		$this->assertSame( 'block', $wpdb->get_var( "SELECT action_type FROM {$audit} WHERE new_value = 'third'" ) );

		// A slug repeats on another network, not on the same one.
		$row = array(
			'template_name' => 'Clinic',
			'template_slug' => 'clinic',
			'settings'      => '{}',
			'created_by'    => 1,
			'created_at'    => '2026-01-01 00:00:00',
			'updated_at'    => '2026-01-01 00:00:00',
		);
		$this->assertSame( 1, $wpdb->insert( $templates, $row + array( 'network_id' => 7 ) ) );
		$suppress = $wpdb->suppress_errors( true );
		$this->assertFalse( $wpdb->insert( $templates, $row + array( 'network_id' => 7 ) ) );
		$wpdb->suppress_errors( $suppress );

		// Again, from another site, with another main network: nothing moves.
		DB::upgrade_network_tables( $audit, $templates );
		$this->assertSame( (string) $expected, $wpdb->get_var( "SELECT network_id FROM {$templates} WHERE template_id = 1" ) );
		$this->assertSame( '7', $wpdb->get_var( "SELECT network_id FROM {$templates} WHERE network_id = 7" ) );
		$this->assertSame( '3', $wpdb->get_var( "SELECT COUNT(*) FROM {$audit}" ) );

		$wpdb->query( "DROP TABLE {$audit}" );
		$wpdb->query( "DROP TABLE {$templates}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * DB::update() cannot run in a test (WordPress makes its tables temporary):
	 * it must at least call the upgrade, after dbDelta() and before the version is saved.
	 */
	public function test_update_runs_the_network_upgrade(): void {
		$method = new ReflectionMethod( DB::class, 'update' );
		$lines  = file( (string) $method->getFileName() );
		$body   = implode( '', array_slice( (array) $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );

		$delta   = strpos( $body, 'dbDelta(' );
		$upgrade = strpos( $body, 'self::upgrade_network_tables()' );
		$version = strpos( $body, "update_option( 'zerospam_db_version'" );

		$this->assertNotFalse( $upgrade );
		$this->assertGreaterThan( $delta, $upgrade );
		$this->assertGreaterThan( $upgrade, $version );
	}
}
