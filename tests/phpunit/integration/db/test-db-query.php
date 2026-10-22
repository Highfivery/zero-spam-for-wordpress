<?php
/**
 * Tests for DB::query().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * DB::query() builds SELECT statements from caller arguments, so every part
 * of the statement must be whitelisted, sanitized or prepared.
 *
 * @covers \ZeroSpam\Includes\DB::query
 */
class Test_DB_Query extends TestCase {

	/**
	 * Runs DB::query() and returns its result and the SELECT it sent.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{0: mixed, 1: string}
	 */
	private function query_log( array $args ): array {
		$result  = null;
		$queries = $this->capture_queries(
			static function () use ( $args, &$result ) {
				$result = DB::query( 'log', $args );
			}
		);

		$selects = array_values( preg_grep( '/^SELECT/', $queries ) );
		$this->assertCount( 1, $selects, 'Expected exactly one SELECT.' );

		return array( $result, $selects[0] );
	}

	/**
	 * Unknown table keys are rejected.
	 */
	public function test_unknown_table_returns_false(): void {
		$this->assertFalse( DB::query( 'users' ) );
		$this->assertFalse( DB::query( 'log; DROP TABLE wp_users' ) );
	}

	/**
	 * Only whitelisted columns are selected; none left means `*`.
	 */
	public function test_select_keeps_only_whitelisted_columns(): void {
		list( , $sql ) = $this->query_log( array( 'select' => array( 'user_ip', 'user_pass', '(SELECT 1)' ) ) );
		$this->assertStringStartsWith( 'SELECT user_ip FROM ', $sql );

		list( , $sql ) = $this->query_log( array( 'select' => array( 'user_pass', 'SLEEP(5)' ) ) );
		$this->assertStringStartsWith( 'SELECT * FROM ', $sql );
	}

	/**
	 * WHERE keys are reduced to [A-Za-z0-9_] and values are prepared.
	 */
	public function test_where_key_is_stripped_and_value_prepared(): void {
		$this->insert_log_rows( 1, array( 'log_type' => 'comment' ) );

		list( $rows, $sql ) = $this->query_log(
			array(
				'where' => array(
					'log_type` -- ' => array( 'value' => "comment' OR '1'='1" ),
				),
			)
		);

		$this->assertStringContainsString( "WHERE log_type = 'comment\\' OR \\'1\\'=\\'1'", $sql );
		$this->assertSame( array(), $rows, 'The injected condition must not match.' );
	}

	/**
	 * An unknown relation falls back to `=`.
	 */
	public function test_unknown_relation_falls_back_to_equals(): void {
		list( , $sql ) = $this->query_log(
			array(
				'where' => array(
					'log_type' => array(
						'value'    => 'comment',
						'relation' => '= 1 OR 1 =',
					),
				),
			)
		);

		$this->assertStringContainsString( "WHERE log_type = 'comment'", $sql );
	}

	/**
	 * Allowed relations are used.
	 */
	public function test_allowed_relation_is_used(): void {
		list( , $sql ) = $this->query_log(
			array(
				'where' => array(
					'log_type' => array(
						'value'    => 'comm%',
						'relation' => 'like',
					),
				),
			)
		);

		$this->assertStringContainsString( "WHERE log_type LIKE 'comm%'", $sql );
	}

	/**
	 * An ORDER BY that is not a plain column list is dropped.
	 */
	public function test_injected_orderby_is_ignored(): void {
		list( , $sql ) = $this->query_log(
			array(
				'orderby' => 'date_recorded; DROP TABLE wp_users',
				'order'   => 'DESC',
			)
		);
		$this->assertStringNotContainsString( 'ORDER BY', $sql );

		list( , $sql ) = $this->query_log(
			array(
				'orderby' => 'date_recorded',
				'order'   => 'DESC',
			)
		);
		$this->assertStringContainsString( 'ORDER BY date_recorded DESC', $sql );
	}

	/**
	 * LIMIT and OFFSET are cast to non-negative integers.
	 */
	public function test_limit_and_offset_are_absint(): void {
		list( , $sql ) = $this->query_log(
			array(
				'limit'  => '5; DROP TABLE wp_users',
				'offset' => '-3',
			)
		);

		$this->assertStringEndsWith( ' LIMIT 5 OFFSET 3', $sql );
	}
}
