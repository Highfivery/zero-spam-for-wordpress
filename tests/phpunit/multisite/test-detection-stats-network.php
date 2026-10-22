<?php
/**
 * Tests that rescue-link entries are kept out of the network statistics.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Includes\Network_Stats_Tracker;
use ZeroSpam\Includes\Stats_Aggregator;
use ZeroSpam\Tests\TestCase;

/**
 * The daily and monthly aggregation, the network statistics and the network
 * dashboard widget leave rescue entries out.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Stats_Aggregator
 * @covers \ZeroSpam\Includes\Network_Stats_Tracker
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget
 */
class Test_Detection_Stats_Network extends TestCase {

	/**
	 * IP of the rescue rows (never used by a detection row).
	 */
	const RESCUE_IP = '192.0.2.200';

	/**
	 * Seeds three comment detections and four rescue entries on the main site, on one day.
	 *
	 * @param string $date Day (Y-m-d).
	 */
	private function seed( string $date ): void {
		$this->insert_log_rows(
			3,
			array(
				'log_type'      => 'comment',
				'date_recorded' => $date . ' 12:00:00',
			)
		);
		$this->insert_log_rows(
			4,
			array(
				'log_type'      => 'rescue',
				'user_ip'       => self::RESCUE_IP,
				'date_recorded' => $date . ' 13:00:00',
			)
		);
	}

	/**
	 * The main site's stored daily or monthly statistics.
	 *
	 * @param string $table Table name without prefix.
	 * @return array<string, mixed>
	 */
	private function stored_stats( string $table ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}{$table} WHERE site_id = %d", get_main_site_id() ), ARRAY_A );
		$this->assertIsArray( $row, "No {$table} row was written." );

		return $row;
	}

	/**
	 * Daily aggregation counts detections only.
	 */
	public function test_daily_aggregation_skips_rescue_rows(): void {
		$this->seed( '2026-01-15' );

		( new Stats_Aggregator() )->aggregate_date( '2026-01-15' );

		$row = $this->stored_stats( 'wpzerospam_stats_daily' );
		$this->assertSame( 3, (int) $row['total_spam_blocked'] );
		$this->assertSame( 3, (int) $row['unique_ips'] );
		$this->assertNotContains( 'rescue', array_column( json_decode( $row['spam_by_type'], true ), 'log_type' ) );
		$this->assertNotContains( 'rescue', array_column( json_decode( $row['top_log_types'], true ), 'log_type' ) );
		$this->assertNotContains( self::RESCUE_IP, array_column( json_decode( $row['top_ips'], true ), 'user_ip' ) );
	}

	/**
	 * Monthly aggregation from the raw log counts detections only.
	 */
	public function test_monthly_aggregation_skips_rescue_rows(): void {
		$this->seed( '2026-02-10' );

		( new Stats_Aggregator() )->aggregate_month( 2026, 2 );

		$row = $this->stored_stats( 'wpzerospam_stats_monthly' );
		$this->assertSame( 3, (int) $row['total_spam_blocked'] );
		$this->assertStringNotContainsString( 'rescue', (string) $row['spam_by_type'] );
	}

	/**
	 * Network statistics for a date range (read from the raw logs) count detections only.
	 */
	public function test_network_stats_skip_rescue_rows(): void {
		$this->seed( '2026-03-05' );

		$stats = Network_Stats_Tracker::get_network_stats( 'custom', '2026-03-05', '2026-03-05' );

		$this->assertSame( 3, $stats['total_spam'] );
		$this->assertSame( 3, $stats['unique_ips'] );
		$this->assertArrayNotHasKey( 'rescue', $stats['spam_by_type'] );
		$this->assertNotContains( self::RESCUE_IP, array_column( $stats['top_ips'], 'user_ip' ) );
	}

	/**
	 * An administrator's IP from rescue entries on several sites is not a "multi-site attacker".
	 */
	public function test_multi_site_attackers_skip_rescue_rows(): void {
		$this->seed( gmdate( 'Y-m-d' ) );

		$attackers = Network_Stats_Tracker::get_multi_site_attackers( 1, 7 );

		$this->assertNotContains( self::RESCUE_IP, array_column( $attackers, 'user_ip' ) );
	}

	/**
	 * The network dashboard widget counts detections only.
	 */
	public function test_network_widget_counts_skip_rescue_rows(): void {
		$this->seed( gmdate( 'Y-m-d' ) );

		global $wpdb;
		$widget = ( new ReflectionClass( Dashboard_Widget::class ) )->newInstanceWithoutConstructor();
		$data   = ( new ReflectionMethod( Dashboard_Widget::class, 'get_network_data' ) )->invoke( $widget, $wpdb );

		$this->assertSame( 3, $data['total_blocked'] );
		$this->assertSame( 3, (int) $data['top_sites'][0]['spam_count'] );
		$this->assertSame( 3, array_sum( array_map( 'intval', array_column( $data['trend_data'], 'count' ) ) ) );
	}
}
