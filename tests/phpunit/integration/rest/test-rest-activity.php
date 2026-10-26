<?php
/**
 * Tests for zero-spam/v1/activity, activity/{id}, activity/bulk-delete and undo/{token}.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Rest\Rules_Controller;
use ZeroSpam\Includes\Undo_Store;
use ZeroSpam\Tests\TestCase;

/**
 * The activity log through the REST API: filters, sorting, paging (a page
 * past the end shows the last page), the live feed, facets, the blocked
 * state of every row from one query, details, and deletions with undo.
 *
 * @covers \ZeroSpam\Includes\Rest\Activity_Controller
 * @covers \ZeroSpam\Includes\Rest\Undo_Controller
 * @covers \ZeroSpam\Includes\Undo_Store
 */
class Test_Rest_Activity extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/activity';

	/**
	 * Empties the tables and logs in an administrator.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->log_table() ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['blocked'] ) );

		$this->as_role( 'administrator' );
	}

	/**
	 * Routes and methods of this controller.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	public function data_routes(): array {
		return array(
			'list'        => array( 'GET', self::ROUTE, array() ),
			'detail'      => array( 'GET', self::ROUTE . '/1', array() ),
			'delete'      => array( 'DELETE', self::ROUTE . '/1', array() ),
			'bulk delete' => array( 'POST', self::ROUTE . '/bulk-delete', array( 'ids' => array( 1 ) ) ),
			'undo'        => array( 'POST', '/zero-spam/v1/undo/' . str_repeat( 'a', 32 ), array() ),
		);
	}

	/**
	 * Logged-out visitors get 401, other roles 403, and nothing is deleted.
	 *
	 * @dataProvider data_routes
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $body   Body.
	 */
	public function test_only_administrators( string $method, string $route, array $body ): void {
		$this->insert_log_rows( 1 );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( $method, $route, $body )->get_status() );

		foreach ( array( 'subscriber', 'editor', 'author' ) as $role ) {
			$this->as_role( $role );
			$this->assertSame( 403, $this->rest( $method, $route, $body )->get_status(), $role );
		}

		$this->assertSame( 1, $this->count_log_rows() );
	}

	/**
	 * A cookie request without the REST nonce runs as a visitor.
	 */
	public function test_cookie_request_without_nonce_is_refused(): void {
		global $wp_rest_auth_cookie;

		$wp_rest_auth_cookie = true;
		unset( $_REQUEST['_wpnonce'], $_SERVER['HTTP_X_WP_NONCE'] );

		try {
			rest_cookie_check_errors( null );
			$response = $this->rest( 'GET', self::ROUTE );
		} finally {
			$wp_rest_auth_cookie = false;
		}

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * The date field is the site's local time (as WordPress writes post dates)
	 * and date_gmt is UTC with its offset, so a browser never reads it as local time.
	 */
	public function test_date_gmt_has_a_utc_offset(): void {
		update_option( 'timezone_string', 'America/Chicago' );
		$this->insert_log_rows( 1, array( 'date_recorded' => '2026-07-01 07:00:00' ) );

		$item = $this->rest( 'GET', self::ROUTE )->get_data()[0];

		$this->assertSame( '2026-07-01T07:00:00', $item['date'] );
		$this->assertSame( '2026-07-01T12:00:00+00:00', $item['date_gmt'] );
		$this->assertSame( gmmktime( 12, 0, 0, 7, 1, 2026 ), strtotime( $item['date_gmt'] ) );
	}

	/**
	 * Newest first, paged, with the WordPress paging headers.
	 */
	public function test_pages_newest_first_with_headers(): void {
		$ids = $this->insert_log_rows( 25 );

		$response = $this->rest( 'GET', self::ROUTE, array(), array( 'per_page' => 10, 'page' => 2 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$headers  = $response->get_headers();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '25', $headers['X-WP-Total'] );
		$this->assertSame( '3', $headers['X-WP-TotalPages'] );
		$this->assertSame( '2', $headers['X-ZeroSpam-Page'] );
		$this->assertSame( array_slice( array_reverse( $ids ), 10, 10 ), array_column( $response->get_data(), 'id' ) );
	}

	/**
	 * A page past the end shows the last page instead of nothing (the legacy
	 * Log screen shows "No items found" without pagination there).
	 */
	public function test_page_past_the_end_is_the_last_page(): void {
		$ids = $this->insert_log_rows( 25 );

		$response = $this->rest( 'GET', self::ROUTE, array(), array( 'per_page' => 10, 'page' => 99 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '3', $response->get_headers()['X-ZeroSpam-Page'] );
		$this->assertSame( array_slice( array_reverse( $ids ), 20 ), array_column( $response->get_data(), 'id' ) );
	}

	/**
	 * Out-of-range paging and unknown sort columns are refused with data.params.
	 *
	 * @dataProvider data_invalid_queries
	 *
	 * @param array<string, mixed> $query Query.
	 * @param string               $param Parameter named in the error.
	 */
	public function test_invalid_queries_are_refused( array $query, string $param ): void {
		$response = $this->rest( 'GET', self::ROUTE, array(), $query );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( $param, $response->get_data()['data']['params'] );
	}

	/**
	 * Invalid queries.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public function data_invalid_queries(): array {
		return array(
			'per_page 0'          => array( array( 'per_page' => 0 ), 'per_page' ),
			'per_page 101'        => array( array( 'per_page' => 101 ), 'per_page' ),
			'page 0'              => array( array( 'page' => 0 ), 'page' ),
			'orderby column'      => array( array( 'orderby' => 'submission_data' ), 'orderby' ),
			'order'               => array( array( 'order' => 'sideways' ), 'order' ),
			'ip'                  => array( array( 'ip' => '999.1.1.1' ), 'ip' ),
			'after'               => array( array( 'after' => 'yesterday' ), 'after' ),
			'before'              => array( array( 'before' => '2026-02-30' ), 'before' ),
			'type with SQL'       => array( array( 'type' => array( "comment' OR 1=1" ) ), 'type' ),
			'country three chars' => array( array( 'country' => array( 'USA' ) ), 'country' ),
			'since_id negative'   => array( array( 'since_id' => -1 ), 'since_id' ),
		);
	}

	/**
	 * Type, country, IP, date and text filters combine.
	 */
	public function test_filters(): void {
		$this->insert_log_rows( 3, array( 'log_type' => 'comment', 'country' => 'DE', 'country_name' => 'Germany' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 2, array( 'log_type' => 'login', 'country' => 'US', 'country_name' => 'United States' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$old = $this->insert_log_rows(
			1,
			array(
				'log_type'      => 'comment',
				'country'       => 'DE',
				'date_recorded' => '2020-01-15 10:00:00',
				'page_url'      => 'https://example.org/contact-us/',
			)
		);

		$this->assertCount( 4, $this->rest( 'GET', self::ROUTE, array(), array( 'type' => array( 'comment' ) ) )->get_data() );
		$this->assertCount( 6, $this->rest( 'GET', self::ROUTE, array(), array( 'type' => array( 'comment', 'login' ) ) )->get_data() );
		$this->assertCount( 2, $this->rest( 'GET', self::ROUTE, array(), array( 'country' => array( 'us' ) ) )->get_data(), 'Country codes are matched in upper case.' );
		$this->assertSame( $old, array_column( $this->rest( 'GET', self::ROUTE, array(), array( 'before' => '2020-01-15' ) )->get_data(), 'id' ), 'A date as before includes that day.' );
		$this->assertCount( 0, $this->rest( 'GET', self::ROUTE, array(), array( 'before' => '2020-01-15T09:00:00' ) )->get_data() );
		$this->assertCount( 5, $this->rest( 'GET', self::ROUTE, array(), array( 'after' => '2021-01-01' ) )->get_data() );
		$this->assertSame( $old, array_column( $this->rest( 'GET', self::ROUTE, array(), array( 'search' => 'contact-us' ) )->get_data(), 'id' ) );
		$this->assertCount( 3, $this->rest( 'GET', self::ROUTE, array(), array( 'search' => 'German' ) )->get_data() );
	}

	/**
	 * The IP filter finds an address whatever form it was typed in.
	 */
	public function test_ip_filter_uses_the_canonical_form(): void {
		$ids = $this->insert_log_rows( 1, array( 'user_ip' => '2001:db8::1' ) );
		$this->insert_log_rows( 2 );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( 'ip' => '2001:DB8:0:0::1' ) )->get_data();

		$this->assertSame( $ids, array_column( $data, 'id' ) );
	}

	/**
	 * Sorting is limited to known columns and stable.
	 */
	public function test_sorting(): void {
		$this->insert_log_rows( 1, array( 'country' => 'US' ) );
		$this->insert_log_rows( 1, array( 'country' => 'BR' ) );
		$this->insert_log_rows( 1, array( 'country' => 'DE' ) );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( 'orderby' => 'country', 'order' => 'asc' ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		$this->assertSame( array( 'BR', 'DE', 'US' ), array_column( $data, 'country' ) );
	}

	/**
	 * Each row says whether its IP is blocked right now, from one query for
	 * the whole page (the legacy Log screen runs one per row), and a page
	 * costs at most four queries.
	 */
	public function test_blocked_state_comes_from_one_query(): void {
		$this->insert_log_rows( 50 );
		$this->block( '198.51.100.1' );
		$this->block( '198.51.100.2', 'temporary', null, wp_date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$this->block( '198.51.100.3', 'temporary', wp_date( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ), wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );

		$this->rest( 'GET', self::ROUTE, array(), array( 'per_page' => 50 ) );

		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response ) {
				$response = $this->rest( 'GET', self::ROUTE, array(), array( 'per_page' => 50 ) );
			}
		);

		$blocked_queries = preg_grep( '/wpzerospam_blocked/', $queries );
		$this->assertCount( 1, $blocked_queries, implode( "\n", $queries ) );
		$this->assertLessThanOrEqual( 4, count( $queries ), implode( "\n", $queries ) );

		$state = array_column( $response->get_data(), 'blocked', 'ip' );
		$this->assertTrue( $state['198.51.100.1'], 'Permanent block.' );
		$this->assertTrue( $state['198.51.100.2'], 'Temporary block running.' );
		$this->assertFalse( $state['198.51.100.3'], 'Temporary block that ended.' );
		$this->assertFalse( $state['198.51.100.4'] );
	}

	/**
	 * The live feed reads the entries after since_id, newest first, without a
	 * total, in at most three queries.
	 */
	public function test_since_id_live_feed(): void {
		$ids = $this->insert_log_rows( 30 );

		$this->rest( 'GET', self::ROUTE, array(), array( 'since_id' => $ids[25] ) );

		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response, $ids ) {
				$response = $this->rest( 'GET', self::ROUTE, array(), array( 'since_id' => $ids[25] ) );
			}
		);

		$this->assertSame( array_reverse( array_slice( $ids, 26 ) ), array_column( $response->get_data(), 'id' ) );
		$this->assertArrayNotHasKey( 'X-WP-Total', $response->get_headers() );
		$this->assertLessThanOrEqual( 3, count( $queries ), implode( "\n", $queries ) );
		$this->assertEmpty( preg_grep( '/COUNT\(\*\)/i', $queries ), 'The live feed counts nothing.' );
		$this->assertNotEmpty( preg_grep( '/`log_id` > ' . $ids[25] . '/', $queries ) );

		$this->assertSame( array(), $this->rest( 'GET', self::ROUTE, array(), array( 'since_id' => end( $ids ) ) )->get_data() );
	}

	/**
	 * `no_count` skips the total for callers that do not page: no COUNT
	 * query, no paging headers, and a page past the end is empty.
	 */
	public function test_no_count_skips_the_total(): void {
		$ids = $this->insert_log_rows( 30 );

		$query = array(
			'per_page' => 10,
			'no_count' => true,
			'_fields'  => 'id',
		);
		$this->rest( 'GET', self::ROUTE, array(), $query );

		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response, $query ) {
				$response = $this->rest( 'GET', self::ROUTE, array(), $query );
			}
		);

		$this->assertSame( array_slice( array_reverse( $ids ), 0, 10 ), array_column( $response->get_data(), 'id' ) );
		$this->assertEmpty( preg_grep( '/COUNT\(\*\)/i', $queries ), implode( "\n", $queries ) );
		$this->assertCount( 1, $queries, 'One read of the page.' );
		$this->assertArrayNotHasKey( 'X-WP-Total', $response->get_headers() );
		$this->assertArrayNotHasKey( 'X-WP-TotalPages', $response->get_headers() );

		$past = $this->rest( 'GET', self::ROUTE, array(), array_merge( $query, array( 'page' => 9 ) ) );
		$this->assertSame( array(), $past->get_data(), 'Without a count a page past the end is empty, not the last page.' );
		$this->assertSame( '9', $past->get_headers()['X-ZeroSpam-Page'] );

		$counted = $this->rest( 'GET', self::ROUTE, array(), array( 'per_page' => 10 ) );
		$this->assertSame( '30', $counted->get_headers()['X-WP-Total'], 'Without no_count the total is still sent.' );
	}

	/**
	 * `days` keeps the last N days of the site's calendar, today included,
	 * and combines with `after` (the later start applies).
	 */
	public function test_days_filter(): void {
		$now    = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log's clock.
		$today  = $this->insert_log_rows( 2, array( 'date_recorded' => gmdate( 'Y-m-d', $now ) . ' 00:00:01' ) );
		$sixth  = $this->insert_log_rows( 1, array( 'date_recorded' => gmdate( 'Y-m-d', $now - 6 * DAY_IN_SECONDS ) . ' 00:00:00' ) );
		$eighth = $this->insert_log_rows( 1, array( 'date_recorded' => gmdate( 'Y-m-d', $now - 7 * DAY_IN_SECONDS ) . ' 23:59:59' ) );

		$week = array_column( $this->rest( 'GET', self::ROUTE, array(), array( 'days' => 7 ) )->get_data(), 'id' );
		sort( $week );
		$this->assertSame( array_merge( $today, $sixth ), $week, 'Seven days: today and the six before it.' );

		$this->assertSame( array_merge( $today, $sixth, $eighth ), $this->sorted_ids( array( 'days' => 30 ) ) );
		$this->assertCount( 2, $this->rest( 'GET', self::ROUTE, array(), array( 'days' => 1 ) )->get_data(), 'One day is today.' );
		$this->assertCount( 2, $this->rest( 'GET', self::ROUTE, array(), array( 'days' => 30, 'after' => gmdate( 'Y-m-d', $now ) ) )->get_data(), 'The later of after and days applies.' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		$facets = $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'days' => 7 ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( 3, $facets['total'] );

		foreach ( array( 0, 366 ) as $days ) {
			$this->assertSame( 400, $this->rest( 'GET', self::ROUTE, array(), array( 'days' => $days ) )->get_status(), "days={$days}" );
		}
	}

	/**
	 * IDs of a list, ascending.
	 *
	 * @param array<string, mixed> $query Query.
	 * @return int[]
	 */
	private function sorted_ids( array $query ): array {
		$ids = array_column( $this->rest( 'GET', self::ROUTE, array(), $query )->get_data(), 'id' );
		sort( $ids );

		return $ids;
	}

	/**
	 * `detections_only` leaves out entries that are not detections (sent rescue links).
	 */
	public function test_detections_only(): void {
		$this->insert_log_rows( 3 );
		$this->insert_log_rows( 2, array( 'log_type' => 'rescue' ) );

		$this->assertCount( 5, $this->rest( 'GET', self::ROUTE )->get_data() );
		$this->assertCount( 3, $this->rest( 'GET', self::ROUTE, array(), array( 'detections_only' => true ) )->get_data() );

		$facets = $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'detections_only' => true ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( 3, $facets['total'] );
		$this->assertSame( array( 'comment' ), array_column( $facets['type'], 'value' ) );
	}

	/**
	 * Every row says whether the Zero Spam network made the detection (also
	 * for rows logged before 6.0.0); `network_only` keeps those rows and the
	 * facets count them.
	 */
	public function test_network_only(): void {
		$network = $this->insert_log_rows( 2, array( 'log_type' => 'blocked', 'submission_data' => wp_json_encode( array( 'failed' => 'zero_spam' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$legacy  = $this->insert_log_rows( 1, array( 'log_type' => 'blocked', 'submission_data' => wp_json_encode( array( 'failed' => 'High Confidence Score: 91%' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$other   = $this->insert_log_rows( 1, array( 'log_type' => 'blocked', 'submission_data' => wp_json_encode( array( 'failed' => 'blocked' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$comment = $this->insert_log_rows( 1, array( 'log_type' => 'comment', 'submission_data' => wp_json_encode( array( 'failed' => 'zero_spam' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.

		$flag = array_column( $this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'id,network' ) )->get_data(), 'network', 'id' );
		foreach ( array_merge( $network, $legacy ) as $id ) {
			$this->assertTrue( $flag[ $id ], "Entry {$id}." );
		}
		$this->assertFalse( $flag[ $other[0] ], 'Another blocked visit.' );
		$this->assertFalse( $flag[ $comment[0] ], 'A comment that names the check is not a network detection.' );

		$only = $this->sorted_ids( array( 'network_only' => true ) );
		$this->assertSame( array_merge( $network, $legacy ), $only );

		$facets = $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true ) )->get_data();
		$this->assertSame( 3, $facets['network_only'] );
		$this->assertSame( 5, $facets['total'] );

		$typed = $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'type' => array( 'comment' ) ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( 0, $typed['network_only'], 'The network count follows the other filters.' );
	}

	/**
	 * The facets name the newest entry the filters match, where a live feed
	 * for those filters starts (whatever order the list is shown in).
	 */
	public function test_facets_max_id(): void {
		$logins   = $this->insert_log_rows( 2, array( 'log_type' => 'login' ) );
		$comments = $this->insert_log_rows( 2, array( 'log_type' => 'comment' ) );

		$this->assertSame( end( $comments ), $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true ) )->get_data()['max_id'] );
		$this->assertSame( end( $logins ), $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'type' => array( 'login' ) ) )->get_data()['max_id'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( 0, $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'type' => array( 'registration' ) ) )->get_data()['max_id'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
	}

	/**
	 * The detail says whether the address is on the IP Allowlist, in any form.
	 */
	public function test_detail_says_whether_the_ip_is_allowed(): void {
		$ids = $this->insert_log_rows( 1, array( 'user_ip' => '2001:db8::5' ) );
		$this->insert_log_rows( 1, array( 'user_ip' => '203.0.113.8' ) );

		$this->assertFalse( $this->rest( 'GET', self::ROUTE . '/' . $ids[0] )->get_data()['allowed'] );

		$this->set_setting( 'ip_whitelist', "192.0.2.1\n2001:DB8:0:0::5" );
		Rules_Controller::reset_request_cache();

		$this->assertTrue( $this->rest( 'GET', self::ROUTE . '/' . $ids[0] )->get_data()['allowed'] );
		$this->assertFalse( $this->rest( 'GET', self::ROUTE . '/' . ( $ids[0] + 1 ) )->get_data()['allowed'] );
	}

	/**
	 * `_fields` trims every row and the submitted data is not even read when not needed.
	 */
	public function test_fields(): void {
		$this->insert_log_rows( 3 );

		$this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'id,ip' ) );

		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response ) {
				$response = $this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'id,ip' ) );
			}
		);

		foreach ( $response->get_data() as $row ) {
			$this->assertSame( array( 'id', 'ip' ), array_keys( $row ) );
		}
		$this->assertEmpty( preg_grep( '/submission_data/', $queries ) );
		$this->assertEmpty( preg_grep( '/wpzerospam_blocked/', $queries ), 'No blocked lookup when blocked is not asked for.' );
	}

	/**
	 * A row carries its type label and the label of the failed check.
	 */
	public function test_rows_carry_labels(): void {
		$this->insert_log_rows( 1, array( 'submission_data' => wp_json_encode( array( 'failed' => 'honeypot' ) ) ) );

		$row = $this->rest( 'GET', self::ROUTE )->get_data()[0];

		$this->assertSame( 'comment', $row['type'] );
		$this->assertSame( 'Comment', $row['type_label'] );
		$this->assertSame( 'honeypot', $row['failed'] );
		$this->assertSame( 'Honeypot', $row['failed_label'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $row['date'] );
	}

	/**
	 * Facets count entries per type, country and IP; each facet ignores its own filter.
	 */
	public function test_facets(): void {
		$this->insert_log_rows( 3, array( 'log_type' => 'comment', 'country' => 'DE', 'country_name' => 'Germany', 'user_ip' => '203.0.113.5' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->insert_log_rows( 2, array( 'log_type' => 'login', 'country' => 'US', 'country_name' => 'United States' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.
		$this->block( '203.0.113.5' );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'type' => array( 'comment' ) ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		$this->assertSame( 3, $data['total'] );
		$this->assertSame( array( 'comment' => 3, 'login' => 2 ), array_column( $data['type'], 'count', 'value' ), 'The type facet ignores the type filter.' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short expected value.
		$this->assertSame( array( 'DE' => 3 ), array_column( $data['country'], 'count', 'value' ) );
		$this->assertSame( 'Germany', $data['country'][0]['label'] );
		$this->assertSame( '203.0.113.5', $data['ip'][0]['value'] );
		$this->assertTrue( $data['ip'][0]['blocked'] );
		$this->assertSame( 'Comment', $data['type'][0]['label'] );
	}

	/**
	 * The IP facet ignores the IP filter, like every facet ignores its own.
	 */
	public function test_ip_facet_ignores_the_ip_filter(): void {
		$this->insert_log_rows( 3, array( 'user_ip' => '203.0.113.5' ) );
		$this->insert_log_rows( 2, array( 'user_ip' => '203.0.113.6' ) );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( 'facets' => true, 'ip' => '203.0.113.6' ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		$this->assertSame( 2, $data['total'], 'The total still follows every filter.' );
		$this->assertSame( array( '203.0.113.5' => 3, '203.0.113.6' => 2 ), array_column( $data['ip'], 'count', 'value' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short expected value.
		$this->assertSame( array( 'comment' => 2 ), array_column( $data['type'], 'count', 'value' ), 'Other facets apply the IP filter.' );
	}

	/**
	 * One entry with its stored data (returned as stored: clients escape it),
	 * geolocation, a map link and its block.
	 */
	public function test_detail(): void {
		$stored = array(
			'failed'  => 'disallowed_list',
			'comment' => '<b>"quoted" & tagged</b>',
		);
		$ids    = $this->insert_log_rows(
			1,
			array(
				'user_ip'         => '203.0.113.77',
				'submission_data' => wp_json_encode( $stored ),
				'latitude'        => '52.52',
				'longitude'       => '13.405',
				'city'            => 'Berlin',
			)
		);
		$block  = $this->block( '203.0.113.77' );

		$data = $this->rest( 'GET', self::ROUTE . '/' . $ids[0] )->get_data();

		$this->assertSame( $stored, $data['details'] );
		$this->assertSame( 'disallowed_list', $data['failed'] );
		$this->assertSame( 'Berlin', $data['city'] );
		$this->assertSame( '52.52', $data['latitude'] );
		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=52.52%2C13.405', $data['map_url'] );
		$this->assertSame( $block, $data['block']['id'] );
		$this->assertTrue( $data['block']['active'] );
		$this->assertTrue( $data['blocked'] );

		$this->assertSame( 404, $this->rest( 'GET', self::ROUTE . '/' . ( $ids[0] + 1 ) )->get_status() );
	}

	/**
	 * Deleting one entry returns a token that restores it, once, with its ID.
	 */
	public function test_delete_and_undo(): void {
		$ids = $this->insert_log_rows( 3 );

		$response = $this->rest( 'DELETE', self::ROUTE . '/' . $ids[1] );
		$token    = $response->get_data()['undo_token'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( Undo_Store::is_token( (string) $token ) );
		$this->assertSame( 2, $this->count_log_rows() );

		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $token );
		$this->assertSame( 200, $undo->get_status() );
		$this->assertSame(
			array(
				'kind'     => 'activity',
				'restored' => 1,
				'skipped'  => 0,
			),
			$undo->get_data()
		);
		$this->assertSame( 1, $this->count_log_rows( array( 'log_id' => (string) $ids[1] ) ), 'Restored with its own ID.' );

		$again = $this->rest( 'POST', '/zero-spam/v1/undo/' . $token );
		$this->assertSame( 404, $again->get_status(), 'A token works once.' );
		$this->assertSame( 'zerospam_undo_expired', $again->get_data()['code'] );
	}

	/**
	 * Bulk delete keeps the rows for undo; the restore puts every row back.
	 */
	public function test_bulk_delete_and_undo(): void {
		$ids = $this->insert_log_rows( 5 );

		$response = $this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'ids' => array( $ids[0], $ids[2], $ids[4], 999999 ) ) );

		$this->assertSame( 3, $response->get_data()['deleted'] );
		$this->assertSame( 2, $this->count_log_rows() );

		$this->rest( 'POST', '/zero-spam/v1/undo/' . $response->get_data()['undo_token'] );

		$this->assertSame( 5, $this->count_log_rows() );
	}

	/**
	 * A token belongs to the user who deleted the rows.
	 */
	public function test_undo_is_bound_to_the_user(): void {
		$owner = get_current_user_id();
		$ids   = $this->insert_log_rows( 1 );
		$token = $this->rest( 'DELETE', self::ROUTE . '/' . $ids[0] )->get_data()['undo_token'];

		$this->as_role( 'administrator' );
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status() );
		$this->assertSame( 0, $this->count_log_rows() );

		wp_set_current_user( $owner );
		$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status(), 'Another user\'s attempt does not use the token up.' );
		$this->assertSame( 1, $this->count_log_rows() );
	}

	/**
	 * After two minutes the transient is gone and so is the undo.
	 */
	public function test_undo_expires(): void {
		$ids   = $this->insert_log_rows( 1 );
		$token = $this->rest( 'DELETE', self::ROUTE . '/' . $ids[0] )->get_data()['undo_token'];

		$this->assertSame( Undo_Store::TTL, 120 );
		delete_transient( Undo_Store::PREFIX . $token );

		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status() );
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/not-a-token' )->get_status(), 'Malformed tokens never reach the store.' );
	}

	/**
	 * Bulk delete limits: at most 500 IDs, something to delete, and `all` needs confirm.
	 */
	public function test_bulk_delete_limits(): void {
		$this->insert_log_rows( 3 );

		$too_many = $this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'ids' => range( 1, 501 ) ) );
		$this->assertSame( 400, $too_many->get_status() );
		$this->assertArrayHasKey( 'ids', $too_many->get_data()['data']['params'] );

		$nothing = $this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'all' => false ) );
		$this->assertSame( 400, $nothing->get_status() );
		$this->assertArrayHasKey( 'ids', $nothing->get_data()['data']['params'] );

		$unconfirmed = $this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'all' => true ) );
		$this->assertSame( 400, $unconfirmed->get_status() );
		$this->assertArrayHasKey( 'confirm', $unconfirmed->get_data()['data']['params'] );
		$this->assertSame( 3, $this->count_log_rows() );

		$all = $this->rest(
			'POST',
			self::ROUTE . '/bulk-delete',
			array(
				'all'     => true,
				'confirm' => true,
			)
		);
		$this->assertSame( 3, $all->get_data()['deleted'] );
		$this->assertNull( $all->get_data()['undo_token'], 'Deleting everything cannot be undone.' );
		$this->assertSame( 0, $this->count_log_rows() );
	}

	/**
	 * Deleting entries, one or several, refreshes the statistics and the
	 * protection counts at once (the highest log ID does not change).
	 */
	public function test_deleting_refreshes_the_statistics(): void {
		$ids = $this->insert_log_rows( 4 );

		$this->assertSame( 4, $this->rest( 'GET', '/zero-spam/v1/stats/summary' )->get_data()['total'] );
		$this->rest( 'GET', '/zero-spam/v1/protection/modules' );
		$this->assertNotFalse( get_transient( \ZeroSpam\Includes\Rest\Protection_Controller::TRANSIENT ) );

		$this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'ids' => array( $ids[0] ) ) );

		$this->assertSame( 3, $this->rest( 'GET', '/zero-spam/v1/stats/summary' )->get_data()['total'], 'Bulk delete left the cached summary.' );
		$this->assertFalse( get_transient( \ZeroSpam\Includes\Rest\Protection_Controller::TRANSIENT ), 'Bulk delete left the cached protection counts.' );

		$this->rest( 'DELETE', self::ROUTE . '/' . $ids[1] );

		$this->assertSame( 2, $this->rest( 'GET', '/zero-spam/v1/stats/summary' )->get_data()['total'], 'Deleting one entry left the cached summary.' );
	}

	/**
	 * An undo cannot push the log past max_logs: the oldest rows are pruned.
	 */
	public function test_undo_keeps_the_log_within_its_maximum(): void {
		$this->set_setting( 'max_logs', 5 );
		$ids   = $this->insert_log_rows( 3 );
		$token = $this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'ids' => $ids ) )->get_data()['undo_token'];
		$this->insert_log_rows( 4 );

		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_data();

		$this->assertSame( 3, $undo['restored'] );
		$this->assertSame( 0, $undo['skipped'] );
		$this->assertSame( 5, $this->count_log_rows() );
	}

	/**
	 * A full live-feed page says how many detections are new in all (rescue
	 * rows left out), so a burst bigger than a page is counted; one count.
	 */
	public function test_since_id_full_page_counts_new_detections(): void {
		$ids = $this->insert_log_rows( 30 );
		$this->insert_log_rows( 4, array( 'log_type' => 'rescue' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.

		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response, $ids ) {
				$response = $this->rest(
					'GET',
					self::ROUTE,
					array(),
					array(
						'since_id' => $ids[0],
						'per_page' => 20,
					)
				);
			}
		);

		$this->assertCount( 20, $response->get_data() );
		$this->assertSame( '29', $response->get_headers()['X-ZeroSpam-New-Detections'] );
		$this->assertCount( 1, preg_grep( '/COUNT\(\*\)/i', $queries ), implode( "\n", $queries ) );
		$this->assertLessThanOrEqual( 4, count( $queries ), implode( "\n", $queries ) );
	}
}
