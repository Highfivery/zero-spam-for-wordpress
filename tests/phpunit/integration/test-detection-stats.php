<?php
/**
 * Tests that rescue-link entries are kept out of the detection statistics.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Core\Admin\Tables\LogTable;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Stats_Tracker;
use ZeroSpam\Tests\TestCase;

/**
 * A used rescue link is written to the Detection Log (type `rescue`) but is
 * not a detection: totals, charts, top lists and the dashboard widget leave
 * it out (DB::NON_DETECTION_TYPES), while the Log still lists it.
 *
 * @covers \ZeroSpam\Includes\DB::query
 * @covers \ZeroSpam\Includes\DB::detections_sql
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget
 * @covers \ZeroSpam\Includes\Network_Stats_Tracker::get_site_stats
 */
class Test_Detection_Stats extends TestCase {

	/**
	 * IP of the rescue rows (never used by a detection row).
	 */
	const RESCUE_IP = '192.0.2.200';

	/**
	 * Seeds three comment detections from three IPs and four rescue entries.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->insert_log_rows( 3, array( 'log_type' => 'comment' ) );
		$this->insert_log_rows(
			4,
			array(
				'log_type' => 'rescue',
				'user_ip'  => self::RESCUE_IP,
			)
		);
	}

	/**
	 * Calls a private method of the dashboard widget.
	 *
	 * @param string $method Method name.
	 * @return array<string, mixed>
	 */
	private function widget_data( string $method ): array {
		global $wpdb;

		$widget = ( new ReflectionClass( Dashboard_Widget::class ) )->newInstanceWithoutConstructor();
		$call   = new ReflectionMethod( Dashboard_Widget::class, $method );

		return $call->invoke( $widget, $wpdb );
	}

	/**
	 * The dashboard's DB::query( 'log' ) (charts, top IPs, countries) skips rescue rows unless asked.
	 */
	public function test_db_query_skips_rescue_rows(): void {
		$this->assertCount( 3, DB::query( 'log' ) );
		$this->assertSame( array( 'comment' ), array_values( array_unique( array_column( DB::query( 'log' ), 'log_type' ) ) ) );

		$this->assertCount( 7, DB::query( 'log', array( 'include_non_detections' => true ) ) );

		$filtered = DB::query(
			'log',
			array(
				'where' => array(
					'user_ip' => array( 'value' => self::RESCUE_IP ),
				),
			)
		);
		$this->assertSame( array(), $filtered );
	}

	/**
	 * The dashboard widget's site counts skip rescue rows.
	 */
	public function test_dashboard_widget_site_counts_skip_rescue_rows(): void {
		$data = $this->widget_data( 'get_site_data' );

		$this->assertSame( 3, $data['total_blocked'] );
		$this->assertSame( 3, $data['unique_ips'] );
		$this->assertSame( 3, array_sum( array_map( 'intval', array_column( $data['trend_data'], 'count' ) ) ) );
		$this->assertNotContains( 'rescue', array_column( $data['spam_types'], 'log_type' ) );
	}

	/**
	 * Per-site statistics (network stats screen, reports) skip rescue rows.
	 */
	public function test_site_stats_skip_rescue_rows(): void {
		$stats = Network_Stats_Tracker::get_site_stats( get_current_blog_id(), 'all' );

		$this->assertSame( 3, $stats['total_spam'] );
		$this->assertSame( 3, $stats['unique_ips'] );
		$this->assertSame( 'comment', $stats['top_spam_type'], 'Four rescue rows outnumber three comments only if they are counted.' );
	}

	/**
	 * The Detection Log still lists rescue entries, with their own label.
	 */
	public function test_log_still_lists_rescue_entries(): void {
		$this->admin_screen( 'dashboard_page_wordpress-zero-spam-dashboard' );
		$this->as_role( 'administrator' );

		$table = new LogTable();
		$table->prepare_items();

		$types = array_column( $table->items, 'log_type' );
		$this->assertSame( 4, count( array_keys( $types, 'rescue', true ) ) );
		$this->assertSame( 3, count( array_keys( $types, 'comment', true ) ) );

		$rescue = $table->items[ array_search( 'rescue', $types, true ) ];
		$this->assertStringContainsString( 'Rescue link used', $table->column_default( $rescue, 'log_type' ) );
	}
}
