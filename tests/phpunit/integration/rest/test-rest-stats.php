<?php
/**
 * Tests for zero-spam/v1/stats/summary and stats/timeseries.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\Stats_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * Totals, deltas, types, countries, the busiest day, the log fill and the
 * daily series; only detections count (rescue rows do not); answers are
 * cached until a new detection; a cold summary costs at most six queries.
 *
 * @covers \ZeroSpam\Includes\Rest\Stats_Controller
 */
class Test_Rest_Stats extends TestCase {

	/**
	 * Summary route.
	 */
	const SUMMARY = '/zero-spam/v1/stats/summary';

	/**
	 * Time series route.
	 */
	const SERIES = '/zero-spam/v1/stats/timeseries';

	/**
	 * Empties the log and the caches and logs in an administrator.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->log_table() ) );
		Stats_Controller::flush_cache();
		delete_option( 'zero-spam-settings' );

		$this->as_role( 'administrator' );
	}

	/**
	 * A site-local date a number of days ago.
	 *
	 * @param float $days Days ago.
	 */
	private function days_ago( float $days ): string {
		return gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - (int) ( $days * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log's clock.
	}

	/**
	 * Only administrators read statistics.
	 */
	public function test_only_administrators(): void {
		foreach ( array( self::SUMMARY, self::SERIES ) as $route ) {
			wp_set_current_user( 0 );
			$this->assertSame( 401, $this->rest( 'GET', $route )->get_status() );
			$this->as_role( 'editor' );
			$this->assertSame( 403, $this->rest( 'GET', $route )->get_status() );
		}
	}

	/**
	 * Ranges and buckets outside the allowed values are refused.
	 */
	public function test_bounds(): void {
		foreach ( array( 0, 2, 14, 31 ) as $range ) {
			$response = $this->rest( 'GET', self::SUMMARY, array(), array( 'range' => $range ) );
			$this->assertSame( 400, $response->get_status(), "summary range {$range}" );
			$this->assertArrayHasKey( 'range', $response->get_data()['data']['params'] );
		}

		foreach ( array( 0, 31 ) as $range ) {
			$this->assertSame( 400, $this->rest( 'GET', self::SERIES, array(), array( 'range' => $range ) )->get_status(), "series range {$range}" );
		}

		$this->assertSame( 400, $this->rest( 'GET', self::SERIES, array(), array( 'bucket' => 'week' ) )->get_status() );
		$this->assertSame( 200, $this->rest( 'GET', self::SERIES, array(), array( 'range' => 14 ) )->get_status() );
	}

	/**
	 * Totals of the period and the one before, by type, top country, busiest
	 * day, and the log fill.
	 */
	public function test_summary(): void {
		update_option( 'zero-spam-settings', array( 'max_logs' => 20 ) );

		$this->insert_log_rows( 3, array( 'log_type' => 'comment', 'country' => 'DE', 'country_name' => 'Germany', 'date_recorded' => $this->days_ago( 1.5 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, array( 'log_type' => 'login', 'country' => 'US', 'country_name' => 'United States', 'date_recorded' => $this->days_ago( 3.5 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 2, array( 'log_type' => 'comment', 'date_recorded' => $this->days_ago( 10 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 5, array( 'log_type' => 'rescue', 'date_recorded' => $this->days_ago( 1 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, array( 'log_type' => 'comment', 'date_recorded' => $this->days_ago( 40 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.

		$data = $this->rest( 'GET', self::SUMMARY, array(), array( 'range' => 7 ) )->get_data();

		$this->assertSame( 7, $data['range'] );
		$this->assertSame( 4, $data['total'], 'Rescue rows are not detections.' );
		$this->assertSame( 2, $data['previous_total'] );
		$this->assertSame( 2, $data['delta'] );
		$this->assertSame( 100.0, (float) $data['delta_percent'] );
		$this->assertSame(
			array(
				array(
					'type'  => 'comment',
					'label' => 'Comment',
					'count' => 3,
				),
				array(
					'type'  => 'login',
					'label' => 'Login',
					'count' => 1,
				),
			),
			$data['by_type']
		);
		$this->assertSame(
			array(
				'code'  => 'DE',
				'name'  => 'Germany',
				'count' => 3,
			),
			$data['top_country']
		);
		$this->assertSame( substr( $this->days_ago( 1.5 ), 0, 10 ), $data['busiest_day']['date'] );
		$this->assertSame( 3, $data['busiest_day']['count'] );
		$this->assertSame(
			array(
				'count'   => 12,
				'max'     => 20,
				'percent' => 60.0,
			),
			array(
				'count'   => $data['log']['count'],
				'max'     => $data['log']['max'],
				'percent' => (float) $data['log']['percent'],
			),
			'The log fill counts every row, as pruning does.'
		);

		$day = $this->rest( 'GET', self::SUMMARY, array(), array( 'range' => 1 ) )->get_data();
		$this->assertSame( 0, $day['total'] );
		$this->assertSame( 3, $day['previous_total'], 'The day before: the three detections 36 hours ago.' );
		$this->assertSame( -100.0, (float) $day['delta_percent'] );
		$this->assertNull( $day['top_country'] );
		$this->assertNull( $day['busiest_day'] );

		$month = $this->rest( 'GET', self::SUMMARY, array(), array( 'range' => 30 ) )->get_data();
		$this->assertSame( 6, $month['total'] );
		$this->assertSame( 1, $month['previous_total'] );
		$this->assertSame( array( 'DE', 'US' ), array_column( $this->rest( 'GET', self::SUMMARY, array(), array( 'range' => 7 ) )->get_data()['top_countries'], 'code' ) );
	}

	/**
	 * Without a stored max_logs the plugin default applies.
	 */
	public function test_log_fill_uses_the_default_maximum(): void {
		$this->insert_log_rows( 2 );

		$this->assertSame( 10000, $this->rest( 'GET', self::SUMMARY )->get_data()['log']['max'] );
	}

	/**
	 * A cold summary (nothing cached, the transient not in the object cache
	 * either) costs exactly four reads of the log table (MAX(log_id), the
	 * days by type, the countries, the row count) plus the transient: two
	 * reads (value and timeout) and two writes. A cached one reads no log
	 * statistics, until a new detection changes the highest log ID.
	 */
	public function test_summary_queries_and_cache(): void {
		$this->insert_log_rows( 5 );
		$this->rest( 'GET', self::SUMMARY );
		Stats_Controller::flush_cache();
		wp_cache_delete( 'notoptions', 'options' );

		$cold = $this->capture_queries(
			function () {
				$this->rest( 'GET', self::SUMMARY );
			}
		);
		$log  = preg_grep( '/wpzerospam_log/', $cold );
		$this->assertCount( 4, $log, implode( "\n", $cold ) );
		$this->assertCount( 8, $cold, implode( "\n", $cold ) );

		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET log_type = %s', $this->log_table(), 'login' ) );

		$warm = $this->capture_queries(
			function () use ( &$data ) {
				$data = $this->rest( 'GET', self::SUMMARY )->get_data();
			}
		);
		$this->assertSame( 'comment', $data['by_type'][0]['type'], 'Served from the cache.' );
		$this->assertEmpty( preg_grep( '/GROUP BY/', $warm ), implode( "\n", $warm ) );

		$this->insert_log_rows( 1 );
		$data = $this->rest( 'GET', self::SUMMARY )->get_data();
		$this->assertSame( 6, $data['total'], 'A new detection refreshes the summary.' );
	}

	/**
	 * `_fields` trims the summary.
	 */
	public function test_fields(): void {
		$data = $this->rest( 'GET', self::SUMMARY, array(), array( '_fields' => 'total,log' ) )->get_data();

		$this->assertSame( array( 'total', 'log' ), array_keys( $data ) );
	}

	/**
	 * Every day of the period, empty ones included, split by type, and the hours of the day.
	 */
	public function test_timeseries(): void {
		$today   = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log's clock.
		$two_ago = substr( $this->days_ago( 2 ), 0, 10 );
		$this->insert_log_rows( 2, array( 'log_type' => 'comment', 'date_recorded' => $today . ' 00:30:00' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, array( 'log_type' => 'login', 'date_recorded' => $two_ago . ' 13:10:00' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, array( 'log_type' => 'rescue', 'date_recorded' => $today . ' 00:40:00' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, array( 'log_type' => 'comment', 'date_recorded' => $this->days_ago( 8 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.

		$data = $this->rest( 'GET', self::SERIES, array(), array( 'range' => 7 ) )->get_data();
		$days = array_column( $data['days'], null, 'date' );

		$this->assertCount( 7, $data['days'] );
		$this->assertSame( $today, $data['days'][6]['date'], 'Oldest first, today last.' );
		$this->assertSame( 2, $days[ $today ]['total'] );
		$this->assertSame( array( 'comment' => 2 ), (array) $days[ $today ]['by_type'] );
		$this->assertSame( array( 'login' => 1 ), (array) $days[ $two_ago ]['by_type'] );
		$this->assertSame( 0, $data['days'][0]['total'] );
		$this->assertSame( array( 'comment', 'login' ), array_column( $data['types'], 'type' ) );
		$this->assertCount( 24, $data['hours'] );
		$this->assertSame( 2, $data['hours'][0] );
		$this->assertSame( 1, $data['hours'][13] );
		$this->assertSame( 3, array_sum( $data['hours'] ) );
	}

	/**
	 * Network-only detections: blocked visits whose failed check is the Zero
	 * Spam network (the `zero_spam` key, or the "High Confidence Score:"
	 * sentence logged before 6.0.0); no other failed check or log type.
	 */
	public function test_network_only_counts(): void {
		$recent = array( 'date_recorded' => $this->days_ago( 2 ) );
		$older  = array( 'date_recorded' => $this->days_ago( 10 ) );
		$json   = static fn ( array $details ): string => (string) wp_json_encode( $details );

		$this->insert_log_rows( 3, $recent + array( 'log_type' => 'blocked', 'submission_data' => $json( array( 'failed' => 'zero_spam', 'confidence_score' => 91 ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, $recent + array( 'log_type' => 'blocked', 'submission_data' => $json( array( 'failed' => 'High Confidence Score: 87%', 'confidence' => 0.87 ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, $recent + array( 'log_type' => 'blocked', 'submission_data' => $json( array( 'failed' => 'stop_forum_spam' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, $recent + array( 'log_type' => 'blocked', 'submission_data' => $json( array( 'failed' => 'blocked' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, $recent + array( 'log_type' => 'comment', 'submission_data' => $json( array( 'failed' => 'honeypot' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, $recent + array( 'log_type' => 'comment', 'submission_data' => $json( array( 'failed' => 'zero_spam' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 1, $recent + array( 'log_type' => 'blocked', 'submission_data' => $json( array( 'failed' => 'zero_spam_extra' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 2, $older + array( 'log_type' => 'blocked', 'submission_data' => $json( array( 'failed' => 'zero_spam' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.

		$week = $this->rest( 'GET', self::SUMMARY, array(), array( 'range' => 7 ) )->get_data();
		$this->assertSame( 9, $week['total'] );
		$this->assertSame(
			array(
				'total'          => 4,
				'previous_total' => 2,
				'basic_total'    => 5,
			),
			$week['network_only'],
			'Three zero_spam rows and one legacy sentence, both only as blocked visits.'
		);

		$series = $this->rest( 'GET', self::SERIES, array(), array( 'range' => 30 ) )->get_data();
		$days   = array_column( $series['days'], null, 'date' );
		$this->assertSame( 4, $days[ substr( $this->days_ago( 2 ), 0, 10 ) ]['network_only'] );
		$this->assertSame( 2, $days[ substr( $this->days_ago( 10 ), 0, 10 ) ]['network_only'] );
		$this->assertSame( 6, array_sum( array_column( $series['days'], 'network_only' ) ) );
		$this->assertSame( 0, $series['days'][29]['network_only'] );
	}

	/**
	 * The network-only counts add no query: the summary still reads the log
	 * table four times when cold.
	 */
	public function test_network_only_adds_no_query(): void {
		$this->insert_log_rows( 2, array( 'log_type' => 'blocked', 'submission_data' => '{"failed":"zero_spam"}' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		wp_cache_delete( 'notoptions', 'options' );

		$cold = $this->capture_queries(
			function () use ( &$data ) {
				$data = $this->rest( 'GET', self::SUMMARY )->get_data();
			}
		);

		$this->assertCount( 4, preg_grep( '/wpzerospam_log/', $cold ), implode( "\n", $cold ) );
		$this->assertSame( 2, $data['network_only']['total'] );
	}
}
