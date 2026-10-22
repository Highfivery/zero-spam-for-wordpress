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
 * The log screen pages through detections 50 at a time, filtered and sorted
 * by request arguments.
 *
 * @covers \ZeroSpam\Core\Admin\Tables\LogTable
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
	 * Clears the request arguments the tests set.
	 */
	public function tear_down(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Builds the table for a request.
	 *
	 * @param array<string, mixed> $request Query arguments.
	 */
	private function prepared_table( array $request ): LogTable {
		$_GET     = $request;
		$_REQUEST = $request;

		$table = new LogTable();
		$table->prepare_items();

		return $table;
	}

	/**
	 * Log IDs of a prepared table's rows, in display order.
	 *
	 * @param LogTable $table Prepared table.
	 * @return int[]
	 */
	private function item_ids( LogTable $table ): array {
		return array_map( 'intval', array_column( $table->items, 'log_id' ) );
	}

	/**
	 * Page 2 of a filtered list holds rows 51-100, newest first.
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
	 * Page 3 of 120 rows holds the 20 oldest, and the three pages cover every
	 * row exactly once.
	 */
	public function test_pages_cover_every_row_once(): void {
		$third = $this->prepared_table(
			array(
				'type'  => 'comment',
				'paged' => '3',
			)
		);

		$this->assertCount( 20, $third->items );
		$this->assertSame( array_reverse( array_slice( $this->comment_ids, 0, 20 ) ), $this->item_ids( $third ) );
		$this->assertSame( 3, (int) $third->get_pagination_arg( 'total_pages' ) );

		$seen = array();
		foreach ( array( '1', '2', '3' ) as $paged ) {
			$seen = array_merge(
				$seen,
				$this->item_ids(
					$this->prepared_table(
						array(
							'type'  => 'comment',
							'paged' => $paged,
						)
					)
				)
			);
		}

		$this->assertCount( 120, $seen );
		$this->assertSame( array_reverse( $this->comment_ids ), $seen );
	}

	/**
	 * The page query binds LIMIT and OFFSET as count and offset.
	 */
	public function test_page_query_uses_limit_and_offset(): void {
		$queries = $this->capture_queries(
			function () {
				$this->prepared_table( array( 'paged' => '2' ) );
			}
		);

		$selects = preg_grep( '/^SELECT \* FROM `' . preg_quote( $this->log_table(), '/' ) . '`/', $queries );

		$this->assertCount( 1, $selects );
		$this->assertStringEndsWith( 'LIMIT 50 OFFSET 50', (string) reset( $selects ) );
	}

	/**
	 * The total respects the active filter.
	 */
	public function test_total_items_respects_the_filter(): void {
		$table = $this->prepared_table( array( 'type' => 'comment' ) );

		$this->assertSame( 120, (int) $table->get_pagination_arg( 'total_items' ) );
	}

	/**
	 * The total respects an IP search.
	 */
	public function test_total_items_respects_the_search(): void {
		$this->insert_log_rows( 3, array( 'user_ip' => '203.0.113.9' ) );

		$table = $this->prepared_table( array( 's' => '203.0.113.9' ) );

		$this->assertCount( 3, $table->items );
		$this->assertSame( 3, (int) $table->get_pagination_arg( 'total_items' ) );
	}

	/**
	 * Without filters the query has no value placeholders, and wpdb::prepare()
	 * must not report incorrect usage (the test framework fails the test on it).
	 */
	public function test_unfiltered_list_does_not_misuse_wpdb_prepare(): void {
		$table = $this->prepared_table( array() );

		$this->assertCount( 50, $table->items );
		$this->assertSame( 150, (int) $table->get_pagination_arg( 'total_items' ) );
	}

	/**
	 * Order accepts ASC and DESC in any case.
	 */
	public function test_order_accepts_asc_and_desc(): void {
		$ascending = $this->prepared_table(
			array(
				'type'  => 'comment',
				'order' => 'ASC',
			)
		);
		$this->assertSame( $this->comment_ids[0], (int) $ascending->items[0]['log_id'] );

		$descending = $this->prepared_table(
			array(
				'type'  => 'comment',
				'order' => 'desc',
			)
		);
		$this->assertSame( $this->comment_ids[119], (int) $descending->items[0]['log_id'] );
	}

	/**
	 * Any other order or orderby value falls back to newest first and never
	 * reaches the SQL.
	 *
	 * @dataProvider data_unexpected_sort_arguments
	 *
	 * @param array<string, string> $sort Sort arguments.
	 */
	public function test_unexpected_sort_arguments_fall_back_to_newest_first( array $sort ): void {
		global $wpdb;

		$table   = null;
		$queries = $this->capture_queries(
			function () use ( $sort, &$table ) {
				$table = $this->prepared_table( array_merge( array( 'type' => 'comment' ), $sort ) );
			}
		);

		$this->assertSame( '', $wpdb->last_error );
		$this->assertSame( $this->comment_ids[119], (int) $table->items[0]['log_id'] );
		foreach ( $queries as $query ) {
			$this->assertStringNotContainsStringIgnoringCase( 'sleep', $query );
			$this->assertStringNotContainsStringIgnoringCase( 'submission_data', $query );
		}
	}

	/**
	 * Sort arguments that are not a sortable column or ASC/DESC.
	 *
	 * @return array<string, array{0: array<string, string>}>
	 */
	public function data_unexpected_sort_arguments(): array {
		return array(
			'injected order'         => array( array( 'order' => 'asc, SLEEP(1)' ) ),
			'unknown order'          => array( array( 'order' => 'sideways' ) ),
			'injected orderby'       => array( array( 'orderby' => 'date_recorded, SLEEP(1)' ) ),
			'non-sortable column'    => array( array( 'orderby' => 'submission_data' ) ),
			'array instead of value' => array( array( 'order' => array( 'asc' ) ) ),
		);
	}

	/**
	 * Sorting by region orders by the region column, not the country.
	 */
	public function test_region_sort_orders_by_region(): void {
		// Country order (BR, DE, IN, US) differs from region order (BE, CA, MH, SP).
		$locations = array(
			'US' => 'CA',
			'DE' => 'BE',
			'BR' => 'SP',
			'IN' => 'MH',
		);
		foreach ( $locations as $country => $region ) {
			$this->insert_log_rows(
				1,
				array(
					'log_type' => 'geo',
					'country'  => $country,
					'region'   => $region,
				)
			);
		}

		$ascending = $this->prepared_table(
			array(
				'type'    => 'geo',
				'orderby' => 'region',
				'order'   => 'asc',
			)
		);
		$this->assertSame( array( 'BE', 'CA', 'MH', 'SP' ), array_column( $ascending->items, 'region' ) );

		$descending = $this->prepared_table(
			array(
				'type'    => 'geo',
				'orderby' => 'region',
				'order'   => 'desc',
			)
		);
		$this->assertSame( array( 'SP', 'MH', 'CA', 'BE' ), array_column( $descending->items, 'region' ) );
	}

	/**
	 * Filters sent by the (POST) filter form are carried into the pagination
	 * links under the keys the table reads.
	 */
	public function test_pagination_links_keep_the_filters(): void {
		$this->insert_log_rows( 1, array( 'user_ip' => '203.0.113.9' ) );
		$_SERVER['REQUEST_URI'] = '/wp-admin/index.php?page=wordpress-zero-spam-dashboard&subview=log';

		$this->prepared_table(
			array(
				'type' => 'comment',
				's'    => '203.0.113.9',
			)
		);

		$query = array();
		wp_parse_str( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ), $query );

		$this->assertSame( 'comment', $query['type'] ?? null );
		$this->assertSame( '203.0.113.9', $query['s'] ?? null );
		$this->assertArrayNotHasKey( 'log_type', $query );
	}

	/**
	 * Logged values are escaped on output.
	 */
	public function test_cells_escape_logged_values(): void {
		$table = new LogTable();
		$item  = array(
			'log_id'   => '1',
			'log_type' => '"><script>alert(1)</script>',
			'user_ip'  => '<b>203.0.113.9</b>',
			'region'   => '<img src=x onerror=alert(1)>',
		);

		foreach ( array( 'log_type', 'user_ip', 'region' ) as $column ) {
			$html = $table->column_default( $item, $column );
			$this->assertStringNotContainsString( '<script', $html, $column );
			$this->assertStringNotContainsString( '<img', $html, $column );
			$this->assertStringNotContainsString( '<b>', $html, $column );
		}
	}

	/**
	 * Deleting selected rows needs the nonce and the manage_options capability.
	 */
	public function test_bulk_delete_requires_nonce_and_capability(): void {
		$target = $this->comment_ids[0];
		$delete = static function ( int $log_id ): array {
			return array(
				'action'         => 'delete',
				'ids'            => array( (string) $log_id ),
				'zerospam_nonce' => wp_create_nonce( 'zerospam_nonce' ),
			);
		};

		// A user without manage_options and a valid nonce for themselves.
		$this->as_role( 'editor' );
		$this->prepared_table( $delete( $target ) );
		$this->assertSame( 1, $this->count_log_rows( array( 'log_id' => (string) $target ) ) );

		// An administrator with a wrong nonce.
		$this->as_role( 'administrator' );
		$this->prepared_table( array_merge( $delete( $target ), array( 'zerospam_nonce' => 'nope' ) ) );
		$this->assertSame( 1, $this->count_log_rows( array( 'log_id' => (string) $target ) ) );

		// An administrator with a valid nonce.
		$this->prepared_table( $delete( $target ) );
		$this->assertSame( 0, $this->count_log_rows( array( 'log_id' => (string) $target ) ) );
		$this->assertSame( 149, $this->count_log_rows() );
	}
}
