<?php
/**
 * Tests for the admin log table.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Tables\LogTable;
use ZeroSpam\Tests\TestCase;

/**
 * The log screen pages through detections 50 at a time.
 *
 * Every test here documents a confirmed bug in LogTable::prepare_items().
 *
 * @group known-defect
 * @covers \ZeroSpam\Core\Admin\Tables\LogTable::prepare_items
 */
class Test_Log_Table extends TestCase {

	/**
	 * Log IDs of the "comment" rows, oldest first.
	 *
	 * @var int[]
	 */
	private array $comment_ids = array();

	/**
	 * Seeds 120 comment detections and 30 of another type.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'dashboard_page_wordpress-zero-spam-dashboard' );
		$this->as_role( 'administrator' );

		$this->insert_log_rows( 30, array( 'log_type' => 'registration' ) );
		$this->comment_ids = $this->insert_log_rows( 120, array( 'log_type' => 'comment' ) );
	}

	/**
	 * Builds the table for a request.
	 *
	 * @param array<string, string> $request Query arguments.
	 */
	private function prepared_table( array $request ): LogTable {
		$_GET     = $request;
		$_REQUEST = $request;

		$table = new LogTable();
		$table->prepare_items();

		return $table;
	}

	/**
	 * Page 2 of a filtered list holds rows 51-100, newest first.
	 *
	 * The LIMIT clause has count and offset swapped and the offset is
	 * per_page * page instead of per_page * (page - 1).
	 */
	public function test_second_page_holds_the_next_fifty_rows(): void {
		$table = $this->prepared_table(
			array(
				'type'  => 'comment',
				'paged' => '2',
			)
		);

		$this->assertCount( 50, $table->items );

		// Newest first: the 51st newest is the 70th oldest (index 69).
		$this->assertSame( $this->comment_ids[69], (int) $table->items[0]['log_id'] );
	}

	/**
	 * The total respects the active filter.
	 */
	public function test_total_items_respects_the_filter(): void {
		$table = $this->prepared_table( array( 'type' => 'comment' ) );

		$this->assertSame( 120, (int) $table->get_pagination_arg( 'total_items' ) );
	}

	/**
	 * Without filters the query has no placeholders, and wpdb::prepare()
	 * reports incorrect usage (the test framework fails the test on it).
	 */
	public function test_unfiltered_list_does_not_misuse_wpdb_prepare(): void {
		$table = $this->prepared_table( array() );

		$this->assertCount( 50, $table->items );
	}
}
