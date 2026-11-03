<?php
/**
 * The e2e mu-plugin's reset and snapshot restore stop loudly when the
 * database refuses a statement.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * Loads tests/e2e/mu-plugins/zero-spam-e2e.php (it is inert without
 * ZEROSPAM_E2E_TOKEN) and makes one statement fail through the `query`
 * filter, so the database answers with a real error. The hooks the file adds
 * are removed after each test with the rest; its stubs stay off (no token
 * header, no ZEROSPAM_E2E_DISPOSABLE, no run mark).
 */
class Test_E2E_Mu_Plugin extends TestCase {

	/**
	 * Statements seen by the query filter.
	 *
	 * @var string[]
	 */
	private array $seen = array();

	/**
	 * Whether wpdb suppressed errors before the test.
	 *
	 * @var bool
	 */
	private bool $suppressed = false;

	/**
	 * Loads the mu-plugin once.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! defined( 'ZEROSPAM_E2E_TOKEN' ) ) {
			define( 'ZEROSPAM_E2E_TOKEN', 'phpunit-' . wp_generate_password( 12, false ) );
		}
		require_once dirname( __DIR__, 2 ) . '/e2e/mu-plugins/zero-spam-e2e.php';

		$this->seen = array();
		add_filter(
			'query',
			function ( $query ) {
				$this->seen[] = (string) $query;
				return $query;
			},
			999
		);

		// The forced errors are expected: keep them out of the PHP error log.
		global $wpdb;
		$this->suppressed = $wpdb->suppress_errors( true );
	}

	/**
	 * Puts wpdb's error reporting back.
	 */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->suppress_errors( $this->suppressed );

		parent::tear_down();
	}

	/**
	 * Breaks the statements that match a pattern: they are sent to a table
	 * that does not exist, or replaced by $with.
	 *
	 * @param string      $pattern Regular expression on the statement.
	 * @param string|null $with    Replacement statement, or null for a missing table.
	 */
	private function break_query( string $pattern, ?string $with = null ): void {
		add_filter(
			'query',
			static function ( $query ) use ( $pattern, $with ) {
				if ( ! preg_match( $pattern, (string) $query ) ) {
					return $query;
				}
				return $with ?? 'SELECT * FROM `zs_e2e_no_such_table`';
			},
			1000
		);
	}

	/**
	 * A minimal snapshot with the given tables.
	 *
	 * @param array<string, array<string, mixed>> $tables Tables.
	 * @return array<string, mixed>
	 */
	private function snapshot( array $tables ): array {
		return array(
			'sites'  => array(),
			'tables' => $tables,
		);
	}

	/**
	 * A reset whose TRUNCATE fails answers 500 naming the table.
	 */
	public function test_reset_site_stops_on_a_failed_truncate(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'wpzerospam_log';
		$this->break_query( '/^TRUNCATE TABLE `' . preg_quote( $table, '/' ) . '`/' );

		$error = \ZeroSpam\Tests\E2E\reset_site( 'defaults' );

		$this->assertWPError( $error );
		$this->assertSame( 'zs_e2e_reset_failed', $error->get_error_code() );
		$this->assertStringStartsWith( "Reset could not empty {$table}: ", $error->get_error_message() );
		$this->assertStringContainsString( 'zs_e2e_no_such_table', $error->get_error_message(), 'The database error is in the message.' );
		$this->assertSame( 500, $error->get_error_data()['status'] );
		$this->assertSame( $table, $error->get_error_data()['table'] );
	}

	/**
	 * A restore whose TRUNCATE fails stops there, before the next table.
	 */
	public function test_restore_stops_on_a_failed_truncate(): void {
		global $wpdb;

		$blocked = $wpdb->prefix . 'wpzerospam_blocked';
		$log     = $wpdb->prefix . 'wpzerospam_log';
		$this->break_query( '/^TRUNCATE TABLE `' . preg_quote( $blocked, '/' ) . '`/' );

		$error = \ZeroSpam\Tests\E2E\restore_snapshot(
			$this->snapshot(
				array(
					$blocked => array( 'rows' => array() ),
					$log     => array( 'rows' => array() ),
				)
			)
		);

		$this->assertWPError( $error );
		$this->assertSame( 'zs_e2e_restore_failed', $error->get_error_code() );
		$this->assertStringStartsWith( "Restore could not empty {$blocked}: ", $error->get_error_message() );
		$this->assertSame( 500, $error->get_error_data()['status'] );
		$this->assertSame( array(), preg_grep( '/' . preg_quote( $log, '/' ) . '/', $this->seen ), 'The next table was not touched.' );
	}

	/**
	 * A restore whose INSERT fails stops at that row, naming the table.
	 */
	public function test_restore_stops_on_a_failed_insert(): void {
		global $wpdb;

		$blocked = $wpdb->prefix . 'wpzerospam_blocked';
		$log     = $wpdb->prefix . 'wpzerospam_log';
		// The TRUNCATE becomes a no-op (a real one would commit the test's
		// transaction); the INSERT fails.
		$this->break_query( '/^TRUNCATE TABLE `' . preg_quote( $blocked, '/' ) . '`/', 'SELECT 1' );
		$this->break_query( '/^INSERT INTO `' . preg_quote( $blocked, '/' ) . '`/' );

		$error = \ZeroSpam\Tests\E2E\restore_snapshot(
			$this->snapshot(
				array(
					$blocked => array(
						'rows' => array(
							array(
								'blocked_type' => 'permanent',
								'user_ip'      => '192.0.2.1',
							),
							array(
								'blocked_type' => 'permanent',
								'user_ip'      => '192.0.2.2',
							),
						),
					),
					$log     => array( 'rows' => array() ),
				)
			)
		);

		$this->assertWPError( $error );
		$this->assertSame( 'zs_e2e_restore_failed', $error->get_error_code() );
		$this->assertStringStartsWith( "Restore stopped: could not insert row 1 of 2 into {$blocked}: ", $error->get_error_message() );
		$this->assertCount( 1, preg_grep( '/^INSERT INTO `' . preg_quote( $blocked, '/' ) . '`/', $this->seen ), 'It stopped at the first row.' );
		$this->assertSame( array(), preg_grep( '/' . preg_quote( $log, '/' ) . '/', $this->seen ), 'The next table was not touched.' );
	}

	/**
	 * A restore that cannot recreate a dropped table stops there.
	 */
	public function test_restore_stops_on_a_failed_create(): void {
		global $wpdb;

		$gone = $wpdb->prefix . 'wpzerospam_e2e_gone';
		$this->break_query( '/^CREATE (TEMPORARY )?TABLE `' . preg_quote( $gone, '/' ) . '`/', "CREATE TABLE `{$gone}` (" );

		$error = \ZeroSpam\Tests\E2E\restore_snapshot(
			$this->snapshot(
				array(
					$gone => array(
						'create' => "CREATE TABLE `{$gone}` (id int NOT NULL)",
						'rows'   => array(),
					),
				)
			)
		);

		$this->assertWPError( $error );
		$this->assertSame( 'zs_e2e_restore_failed', $error->get_error_code() );
		$this->assertStringStartsWith( "Restore stopped: could not recreate {$gone}: ", $error->get_error_message() );
		$this->assertSame( array(), preg_grep( '/^TRUNCATE TABLE `' . preg_quote( $gone, '/' ) . '`/', $this->seen ), 'It did not go on to empty it.' );
	}

	/**
	 * Without a failure, a restore answers null.
	 */
	public function test_restore_without_tables_succeeds(): void {
		$this->assertNull( \ZeroSpam\Tests\E2E\restore_snapshot( $this->snapshot( array() ) ) );
	}
}
