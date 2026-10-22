<?php
/**
 * Tests for DB::log().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * Detections are written to the log table, which is capped at max_logs.
 *
 * @covers \ZeroSpam\Includes\DB::log
 */
class Test_DB_Log extends TestCase {

	/**
	 * A detection is stored with the visitor IP, type and page.
	 */
	public function test_logs_a_detection(): void {
		$this->set_request( '/contact/' );

		$this->assertSame( 1, DB::log( 'comment', array( 'failed' => 'honeypot' ) ) );

		$row = $GLOBALS['wpdb']->get_row( 'SELECT * FROM `' . $this->log_table() . '`', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed table name.
		$this->assertSame( 'comment', $row['log_type'] );
		$this->assertSame( self::DEFAULT_IP, $row['user_ip'] );
		$this->assertSame( 'http://example.org/contact/', $row['page_url'] );
		$this->assertSame( array( 'failed' => 'honeypot' ), json_decode( $row['submission_data'], true ) );
	}

	/**
	 * When the table is over the limit, the oldest rows are removed first.
	 */
	public function test_pruning_removes_the_oldest_rows_first(): void {
		$this->set_setting( 'max_logs', 5 );
		$ids = $this->insert_log_rows( 8 );

		DB::log( 'comment', array() );

		$remaining = array_map( 'intval', $GLOBALS['wpdb']->get_col( 'SELECT log_id FROM `' . $this->log_table() . '` ORDER BY log_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed table name.
		foreach ( array_slice( $ids, 0, 3 ) as $oldest ) {
			$this->assertNotContains( $oldest, $remaining, 'One of the three oldest rows survived.' );
		}

		// However many seeded rows survive, they are the newest ones.
		$survivors = array_values( array_intersect( $ids, $remaining ) );
		$this->assertNotEmpty( $survivors );
		$this->assertSame( array_slice( $ids, -count( $survivors ) ), $survivors, 'Pruning removed a newer row before an older one.' );
	}

	/**
	 * The table never holds more than max_logs rows after a write.
	 *
	 * Regression: DB::log() pruned only when the count was already above the
	 * limit (`>`), then inserted, so the table settled at max_logs + 1.
	 */
	public function test_table_never_exceeds_max_logs(): void {
		$this->set_setting( 'max_logs', 5 );
		$this->insert_log_rows( 5 );

		DB::log( 'comment', array() );
		DB::log( 'comment', array() );

		$this->assertSame( 5, $this->count_log_rows(), 'The log table holds more rows than max_logs.' );
	}

	/**
	 * With max_logs = 1 only the entry just written is kept.
	 */
	public function test_max_logs_of_one_keeps_only_the_new_entry(): void {
		$this->set_setting( 'max_logs', 1 );
		$ids = $this->insert_log_rows( 3 );

		$this->assertSame( 1, DB::log( 'blocked', array() ) );

		$remaining = array_map( 'intval', $GLOBALS['wpdb']->get_col( 'SELECT log_id FROM `' . $this->log_table() . '`' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed table name.
		$this->assertCount( 1, $remaining );
		$this->assertGreaterThan( max( $ids ), $remaining[0], 'A seeded row survived instead of the new entry.' );
		$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'blocked' ) ) );
	}

	/**
	 * Empty and zero max_logs values.
	 *
	 * @return array<string, array{0: int|string}>
	 */
	public static function data_unset_max_logs(): array {
		return array(
			'zero'         => array( 0 ),
			'empty string' => array( '' ),
		);
	}

	/**
	 * An empty or zero max_logs falls back to the 10,000 default instead of
	 * deleting every entry.
	 *
	 * @dataProvider data_unset_max_logs
	 *
	 * @param int|string $value Stored max_logs value.
	 */
	public function test_unset_max_logs_uses_the_default_limit( $value ): void {
		$this->set_setting( 'max_logs', $value );
		$this->insert_log_rows( 3 );

		$this->assertSame( 10000, \ZeroSpam\Core\Settings::get_settings( 'max_logs' ) );

		DB::log( 'comment', array() );

		$this->assertSame( 4, $this->count_log_rows(), 'Entries were pruned below the default limit.' );
	}

	/**
	 * The zerospam_log_record filter still shapes the stored row.
	 */
	public function test_log_record_filter_is_applied(): void {
		add_filter(
			'zerospam_log_record',
			static function ( array $record ): array {
				$record['log_type'] = 'filtered';
				return $record;
			}
		);

		DB::log( 'comment', array() );

		$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'filtered' ) ) );
	}

	/**
	 * Asset requests (404s for missing files) are not logged.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_asset_urls(): array {
		return array(
			'script'     => array( '/wp-content/plugins/missing/app.js' ),
			'stylesheet' => array( '/wp-content/themes/missing/style.css' ),
			'source map' => array( '/wp-includes/js/missing.min.js.map' ),
			'favicon'    => array( '/favicon.ico' ),
		);
	}

	/**
	 * Asset URLs are skipped.
	 *
	 * @dataProvider data_asset_urls
	 *
	 * @param string $uri Request URI.
	 */
	public function test_skips_asset_urls( string $uri ): void {
		$this->set_request( $uri );

		$this->assertFalse( DB::log( 'blocked', array() ) );
		$this->assertSame( 0, $this->count_log_rows() );
	}

	/**
	 * Regression for CVE-2026-96752: a submitted field name such as
	 * `f[<img src=x onerror=alert(1)>]` becomes an array key, and was stored
	 * as HTML. Keys and values are now sanitized before they are stored.
	 */
	public function test_stores_nested_keys_without_markup(): void {
		DB::log(
			'comment',
			array(
				'data' => array(
					'f' => array(
						'<img src=x onerror=alert(1)>' => '<script>alert(2)</script>spam',
					),
				),
			)
		);

		$stored = (string) $GLOBALS['wpdb']->get_var( 'SELECT submission_data FROM `' . $this->log_table() . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed table name.

		$this->assertNotSame( '', $stored );
		$this->assertStringNotContainsString( '<', $stored );
		$this->assertStringNotContainsString( 'onerror', $stored );
		$this->assertStringContainsString( 'spam', $stored );
	}
}
