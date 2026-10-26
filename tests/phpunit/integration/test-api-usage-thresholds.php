<?php
/**
 * Tests for the API alert thresholds and the quota reset date.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\License_Status;
use ZeroSpam\Includes\API_Usage_Alerts;
use ZeroSpam\Includes\API_Usage_Tracker;
use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * Since 6.0.0 the five threshold settings of API Monitoring decide when
 * API_Usage_Tracker::detect_anomalies() reports a problem (before, fixed
 * values were used and the settings were never read), and the Quota Reset
 * Date decides which quota reports count.
 *
 * @covers \ZeroSpam\Includes\API_Usage_Tracker::detect_anomalies
 * @covers \ZeroSpam\Includes\API_Usage_Tracker::quota_snapshot
 * @covers \ZeroSpam\Includes\API_Usage_Alerts::threshold
 * @covers \ZeroSpam\Core\License_Status::quota_period
 */
class Test_API_Usage_Thresholds extends TestCase {

	/**
	 * Starts each test with an empty usage table for this site and no cache.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE site_id = %d', $wpdb->base_prefix . DB::$tables['api_usage'], get_current_blog_id() ) );
		delete_option( 'zero-spam-api-monitoring' );
		API_Usage_Tracker::clear_usage_cache();
		self::reset_static_caches();
	}

	/**
	 * Records usage rows, `$days_ago` days before now (site time).
	 *
	 * @param int                  $count    Rows.
	 * @param array<string, mixed> $fields   Columns.
	 * @param int                  $days_ago Days before now.
	 */
	private function usage( int $count, array $fields = array(), int $days_ago = 0 ): void {
		global $wpdb;

		$when = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days_ago * DAY_IN_SECONDS );

		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->base_prefix . DB::$tables['api_usage'],
				array_merge(
					array(
						'site_id'       => get_current_blog_id(),
						'event_type'    => 'api_call',
						'endpoint'      => 'v3/query',
						'response_code' => 200,
						'date_recorded' => $when,
						'hour_bucket'   => substr( $when, 0, 13 ) . ':00:00',
						'day_bucket'    => substr( $when, 0, 10 ),
					),
					$fields
				)
			);
		}

		API_Usage_Tracker::clear_usage_cache();
	}

	/**
	 * Types of the anomalies detected now.
	 *
	 * @return string[]
	 */
	private function detected(): array {
		API_Usage_Tracker::clear_usage_cache();
		self::reset_static_caches();

		return array_column( API_Usage_Tracker::detect_anomalies(), 'type' );
	}

	/**
	 * Sets a threshold.
	 *
	 * @param string $type  Alert type.
	 * @param mixed  $value Value.
	 */
	private function threshold( string $type, $value ): void {
		$this->set_setting( 'threshold_' . $type, $value, 'api-monitoring' );
	}

	/**
	 * A threshold is its setting, within its range, or the default.
	 */
	public function test_threshold_reads_the_setting_within_its_range(): void {
		$this->assertSame( 10, API_Usage_Alerts::threshold( 'high_error_rate' ), 'Default.' );

		$this->threshold( 'high_error_rate', 25 );
		$this->assertSame( 25, API_Usage_Alerts::threshold( 'high_error_rate' ) );

		$this->threshold( 'high_error_rate', 400 );
		$this->assertSame( 100, API_Usage_Alerts::threshold( 'high_error_rate' ), 'At most 100%.' );

		$this->threshold( 'slow_response', '2500' );
		$this->assertSame( 2500, API_Usage_Alerts::threshold( 'slow_response' ) );

		$this->threshold( 'usage_spike', 'lots' );
		$this->assertSame( 300, API_Usage_Alerts::threshold( 'usage_spike' ), 'Not a number: the default.' );

		$this->assertSame( 0, API_Usage_Alerts::threshold( 'nope' ) );
	}

	/**
	 * The error-rate alert follows its threshold.
	 */
	public function test_error_rate_follows_its_threshold(): void {
		$this->usage( 8 );
		$this->usage( 2, array( 'event_type' => 'error' ) );

		// 20% of today's requests failed.
		$this->assertContains( 'high_error_rate', $this->detected(), 'Above the default 10%.' );

		$this->threshold( 'high_error_rate', 25 );
		$this->assertNotContains( 'high_error_rate', $this->detected() );

		$this->threshold( 'high_error_rate', 15 );
		$this->assertContains( 'high_error_rate', $this->detected() );
	}

	/**
	 * The usage spike alert compares today with the threshold's share of the weekly average.
	 */
	public function test_usage_spike_follows_its_threshold(): void {
		for ( $day = 1; $day <= 6; $day++ ) {
			$this->usage( 1, array(), $day );
		}
		$this->usage( 10 );

		// Week: 16 calls, a daily average of 3; today 10 > 300% of 3.
		$this->assertContains( 'usage_spike', $this->detected() );

		$this->threshold( 'usage_spike', 400 );
		$this->assertNotContains( 'usage_spike', $this->detected(), '10 is not above 400% of 3.' );
	}

	/**
	 * The slow response alert follows its threshold in milliseconds.
	 */
	public function test_slow_response_follows_its_threshold(): void {
		$this->usage( 4, array( 'response_time_ms' => 3000 ) );

		$this->assertNotContains( 'slow_response', $this->detected(), '3000 ms is under the default 5000 ms.' );

		$this->threshold( 'slow_response', 2000 );
		$this->assertContains( 'slow_response', $this->detected() );
	}

	/**
	 * The quota alerts follow their thresholds (share of the quota used).
	 */
	public function test_quota_alerts_follow_their_thresholds(): void {
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 150,
			)
		);

		// 85% used.
		$this->assertSame( array( 'quota_warning' ), array_values( array_intersect( $this->detected(), array( 'quota_warning', 'quota_critical' ) ) ) );

		$this->threshold( 'quota_warning', 90 );
		$this->assertEmpty( array_intersect( $this->detected(), array( 'quota_warning', 'quota_critical' ) ) );

		$this->threshold( 'quota_critical', 85 );
		$this->assertContains( 'quota_critical', $this->detected() );
	}

	/**
	 * A used-up quota (0 remaining) is critical.
	 */
	public function test_used_up_quota_is_critical(): void {
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 0,
			)
		);

		$this->assertContains( 'quota_critical', $this->detected() );
	}

	/**
	 * With a Quota Reset Date, quota reports from before the last reset no
	 * longer count, and the latest report since then is used.
	 */
	public function test_quota_counts_only_since_the_last_reset(): void {
		$today = new DateTimeImmutable( substr( current_time( 'mysql' ), 0, 10 ) );
		$this->set_setting( 'quota_reset_date', $today->modify( '-5 days' )->format( 'Y-m-d' ), 'api-monitoring' );

		// Before the reset: 99% used.
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 10,
			),
			10
		);
		$this->assertEmpty( array_intersect( $this->detected(), array( 'quota_warning', 'quota_critical' ) ), 'A report from before the reset does not count.' );

		// Since the reset, two days ago: 90% used (no report today).
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 100,
			),
			2
		);
		$this->assertContains( 'quota_critical', $this->detected(), 'The latest report of the period counts, even from an earlier day.' );

		// A later report wins.
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 500,
			),
			1
		);
		$this->assertEmpty( array_intersect( $this->detected(), array( 'quota_warning', 'quota_critical' ) ) );
	}

	/**
	 * Without a reset date, only today's reports count (as before 6.0.0).
	 */
	public function test_quota_without_a_reset_date_counts_today_only(): void {
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 10,
			),
			2
		);

		$this->assertEmpty( array_intersect( $this->detected(), array( 'quota_warning', 'quota_critical' ) ) );
	}

	/**
	 * The quota period rolls the set date forward a month at a time,
	 * keeping its day of the month.
	 *
	 * @dataProvider provide_periods
	 *
	 * @param string $set   Quota Reset Date.
	 * @param string $today Today.
	 * @param string $start Last reset.
	 * @param string $next  Next reset.
	 */
	public function test_quota_period( string $set, string $today, string $start, string $next ): void {
		$this->set_setting( 'quota_reset_date', $set, 'api-monitoring' );

		$this->assertSame(
			array(
				'start' => $start,
				'next'  => $next,
			),
			License_Status::quota_period( new DateTimeImmutable( $today ) )
		);
	}

	/**
	 * Quota periods.
	 *
	 * @return array<string, string[]>
	 */
	public static function provide_periods(): array {
		return array(
			'still to come'          => array( '2026-11-15', '2026-10-25', '2026-10-15', '2026-11-15' ),
			'in the past'            => array( '2026-03-05', '2026-10-25', '2026-10-05', '2026-11-05' ),
			'due today'              => array( '2026-10-25', '2026-10-25', '2026-10-25', '2026-11-25' ),
			'across a year'          => array( '2025-12-30', '2026-12-31', '2026-12-30', '2027-01-30' ),
			'31st in February'       => array( '2026-01-31', '2026-02-10', '2026-01-31', '2026-02-28' ),
			'31st after February'    => array( '2026-01-31', '2026-03-01', '2026-02-28', '2026-03-31' ),
			'29 February, leap year' => array( '2028-02-29', '2028-03-10', '2028-02-29', '2028-03-29' ),
		);
	}

	/**
	 * No period without a valid date.
	 */
	public function test_no_quota_period_without_a_valid_date(): void {
		$this->assertNull( License_Status::quota_period() );

		$this->set_setting( 'quota_reset_date', '2026-02-30', 'api-monitoring' );
		$this->assertNull( License_Status::quota_period() );
	}

	/**
	 * Monitoring is on only when its switch is.
	 */
	public function test_monitoring_follows_its_setting(): void {
		$this->assertFalse( API_Usage_Tracker::is_monitoring_enabled() );

		$this->set_setting( 'api_monitoring', 'enabled', 'api-monitoring' );
		$this->assertTrue( API_Usage_Tracker::is_monitoring_enabled() );
	}

	/**
	 * A used-up quota sends its critical alert once per quota period, not on
	 * every hourly check; a new period (the quota reset) sends it again.
	 */
	public function test_quota_alert_once_per_period(): void {
		$this->set_setting( 'api_monitoring', 'enabled', 'api-monitoring' );
		$this->set_setting( 'alert_quota_critical', 'enabled', 'api-monitoring' );
		$this->set_setting( 'alert_email', 'enabled', 'api-monitoring' );
		$this->usage(
			1,
			array(
				'queries_limit'     => 1000,
				'queries_remaining' => 0,
			)
		);
		reset_phpmailer_instance();
		$alerts = new API_Usage_Alerts();

		$alerts->check_anomalies();
		API_Usage_Tracker::clear_usage_cache();
		$alerts->check_anomalies();
		$alerts->check_anomalies();

		$this->assertCount( 1, $this->sent_mail(), 'One email for the period, however often the check runs.' );

		// Another quota period (here: one that began yesterday, so the report of today is in it).
		$yesterday = ( new DateTimeImmutable( substr( current_time( 'mysql' ), 0, 10 ) ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$this->set_setting( 'quota_reset_date', $yesterday, 'api-monitoring' );
		$alerts->check_anomalies();
		$alerts->check_anomalies();

		$this->assertCount( 2, $this->sent_mail() );
	}
}
