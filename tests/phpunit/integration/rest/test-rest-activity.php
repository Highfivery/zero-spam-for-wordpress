<?php
/**
 * Tests for zero-spam/v1/activity, activity/{id}, activity/bulk-delete and undo/{token}.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
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
	 * Deleting entries refreshes the statistics at once.
	 */
	public function test_deleting_refreshes_the_statistics(): void {
		$ids = $this->insert_log_rows( 4 );

		$this->assertSame( 4, $this->rest( 'GET', '/zero-spam/v1/stats/summary' )->get_data()['total'] );

		$this->rest( 'POST', self::ROUTE . '/bulk-delete', array( 'ids' => array( $ids[0] ) ) );

		$this->assertSame( 3, $this->rest( 'GET', '/zero-spam/v1/stats/summary' )->get_data()['total'], 'The cached summary was not cleared (the highest log ID did not change).' );
	}
}
