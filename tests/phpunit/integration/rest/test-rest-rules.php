<?php
/**
 * Tests for zero-spam/v1/rules/blocks, rules/lists and rules/no-lockout.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * Blocks are validated, stored in canonical form, listed with search (IP
 * blocks by address, location blocks by their value or country name) and
 * deleted with undo; the .htaccess rules follow every change, also when the
 * last block goes; the lists are stored like PATCH /settings stores them.
 *
 * @covers \ZeroSpam\Includes\Rest\Rules_Controller
 * @covers \ZeroSpam\Core\Utilities::refresh_htaccess
 */
class Test_Rest_Rules extends TestCase {

	/**
	 * Blocks route.
	 */
	const BLOCKS = '/zero-spam/v1/rules/blocks';

	/**
	 * Temporary .htaccess file.
	 *
	 * @var string
	 */
	private string $htaccess = '';

	/**
	 * Empties the block table and the lists, and logs in an administrator.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . DB::$tables['blocked'] ) );
		delete_option( 'zero-spam-settings' );
		delete_option( 'zerospam_blocked_email_domains' );
		delete_option( Admin_Safe_IPs::OPTION );
		delete_option( 'zerospam_settings_audit' );

		$this->as_role( 'administrator' );
	}

	/**
	 * Removes the temporary .htaccess file.
	 */
	public function tear_down(): void {
		if ( '' !== $this->htaccess && file_exists( $this->htaccess ) ) {
			wp_delete_file( $this->htaccess );
		}

		parent::tear_down();
	}

	/**
	 * Every route of the controller.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	public function data_routes(): array {
		return array(
			'list blocks'       => array( 'GET', self::BLOCKS, array() ),
			'add block'         => array( 'POST', self::BLOCKS, array( 'ip' => '192.0.2.9', 'type' => 'permanent' ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'read block'        => array( 'GET', self::BLOCKS . '/1', array() ),
			'change block'      => array( 'PUT', self::BLOCKS . '/1', array( 'reason' => 'x' ) ),
			'delete block'      => array( 'DELETE', self::BLOCKS . '/1', array() ),
			'bulk delete'       => array( 'POST', self::BLOCKS . '/bulk-delete', array( 'ids' => array( 1 ) ) ),
			'bulk add'          => array( 'POST', self::BLOCKS . '/bulk', array( 'ips' => array( '192.0.2.9' ), 'type' => 'permanent' ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'read list'         => array( 'GET', '/zero-spam/v1/rules/lists/allowed-ips', array() ),
			'change list'       => array( 'PUT', '/zero-spam/v1/rules/lists/allowed-ips', array( 'items' => array( '192.0.2.1' ) ) ),
			'read no-lockout'   => array( 'GET', '/zero-spam/v1/rules/no-lockout', array() ),
			'delete no-lockout' => array( 'DELETE', '/zero-spam/v1/rules/no-lockout/203.0.113.10', array() ),
		);
	}

	/**
	 * Logged-out visitors get 401 and other roles 403; nothing changes.
	 *
	 * @dataProvider data_routes
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $body   Body.
	 */
	public function test_only_administrators( string $method, string $route, array $body ): void {
		$id = $this->block( '198.51.100.50' );
		$this->assertSame( 1, $id > 0 ? 1 : 0 );
		update_option( Admin_Safe_IPs::OPTION, array( '203.0.113.10' ), false );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( $method, str_replace( '/1', '/' . $id, $route ), $body )->get_status() );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( $method, str_replace( '/1', '/' . $id, $route ), $body )->get_status() );

		$this->assertNotNull( DB::blocked( '198.51.100.50' ) );
		$this->assertSame( array( '203.0.113.10' ), Admin_Safe_IPs::get_ips() );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * An IP block is stored in canonical form and returned with UTC dates.
	 */
	public function test_create_ip_block(): void {
		$response = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'ip'     => '2001:DB8:0:0::7',
				'type'   => 'temporary',
				'end'    => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
				'reason' => '<b>Spammer</b>',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $data ) );
		$this->assertSame( '2001:db8::7', $data['ip'] );
		$this->assertSame( 'ip', $data['kind'] );
		$this->assertSame( 'temporary', $data['type'] );
		$this->assertSame( 'Spammer', $data['reason'] );
		$this->assertTrue( $data['active'] );
		$this->assertMatchesRegularExpression( '/Z$/', $data['end'] );
		$this->assertSame( '2001:db8::7', DB::blocked( '2001:db8::7' )['user_ip'] );

		$again = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'ip'   => '2001:db8::7',
				'type' => 'permanent',
			)
		);
		$this->assertSame( 200, $again->get_status(), 'An address that is already blocked has its block replaced.' );
		$this->assertSame( $data['id'], $again->get_data()['id'] );
		$this->assertSame( 'permanent', $again->get_data()['type'] );
		$this->assertNull( $again->get_data()['end'] );
	}

	/**
	 * Invalid blocks are refused with one message per parameter.
	 *
	 * @dataProvider data_invalid_blocks
	 *
	 * @param array<string, mixed> $body  Body.
	 * @param string               $param Parameter in error.
	 */
	public function test_invalid_blocks( array $body, string $param ): void {
		$response = $this->rest( 'POST', self::BLOCKS, $body );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( $param, $response->get_data()['data']['params'] );
		$this->assertSame( 0, (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i', $GLOBALS['wpdb']->prefix . DB::$tables['blocked'] ) ) );
	}

	/**
	 * Invalid block bodies.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public function data_invalid_blocks(): array {
		$future = gmdate( 'Y-m-d', time() + 3 * DAY_IN_SECONDS );

		return array(
			'no ip'                  => array( array( 'type' => 'permanent' ), 'ip' ),
			'bad ip'                 => array( array( 'ip' => '300.1.1.1', 'type' => 'permanent' ), 'ip' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'range'                  => array( array( 'ip' => '192.0.2.0/24', 'type' => 'permanent' ), 'ip' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'no type'                => array( array( 'ip' => '192.0.2.1' ), 'type' ),
			'temporary without end'  => array( array( 'ip' => '192.0.2.1', 'type' => 'temporary' ), 'end' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'end in the past'        => array( array( 'ip' => '192.0.2.1', 'type' => 'temporary', 'end' => '2020-01-01' ), 'end' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'end before start'       => array( array( 'ip' => '192.0.2.1', 'type' => 'temporary', 'start' => $future, 'end' => gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ) ), 'end' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'bad start'              => array( array( 'ip' => '192.0.2.1', 'type' => 'permanent', 'start' => 'soon' ), 'start' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'bad kind'               => array( array( 'kind' => 'email', 'type' => 'permanent' ), 'kind' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'bad location type'      => array( array( 'kind' => 'location', 'key_type' => 'planet', 'key' => 'Mars', 'type' => 'permanent' ), 'key_type' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'unknown country'        => array( array( 'kind' => 'location', 'key_type' => 'country_code', 'key' => 'XX', 'type' => 'permanent' ), 'key' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'location without value' => array( array( 'kind' => 'location', 'key_type' => 'city', 'type' => 'permanent' ), 'key' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
			'reason too long'        => array( array( 'ip' => '192.0.2.1', 'type' => 'permanent', 'reason' => str_repeat( 'x', 300 ) ), 'reason' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
		);
	}

	/**
	 * A location block uses the country code in upper case and its name as label.
	 */
	public function test_create_location_block(): void {
		$response = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'kind'     => 'location',
				'key_type' => 'country_code',
				'key'      => 'de',
				'type'     => 'permanent',
			)
		);

		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'DE', $response->get_data()['key'] );
		$this->assertSame( 'Germany', $response->get_data()['label'] );
		$this->assertNull( $response->get_data()['ip'] );
		$this->assertSame( 'DE', DB::blocked( 'DE', 'country_code' )['blocked_key'] );
	}

	/**
	 * IP blocks are searched by address; location blocks by their value or
	 * the country's name (the legacy Blocked Locations screen searched the
	 * empty IP column).
	 */
	public function test_list_and_search(): void {
		foreach ( array( '198.51.100.1', '198.51.100.2', '203.0.113.9' ) as $ip ) {
			$this->block( $ip );
		}
		foreach ( array(
			'country_code' => 'DE',
			'city'         => 'Springfield',
		) as $key_type => $key ) {
			$this->rest(
				'POST',
				self::BLOCKS,
				array(
					'kind'     => 'location',
					'key_type' => $key_type,
					'key'      => $key,
					'type'     => 'permanent',
				)
			);
		}

		$ips = $this->rest( 'GET', self::BLOCKS, array(), array( 'search' => '198.51', 'per_page' => 1 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( '2', $ips->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $ips->get_data() );

		$this->assertSame( array( 'Springfield' ), array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'kind' => 'location', 'search' => 'spring' ) )->get_data(), 'key' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( 'DE' ), array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'kind' => 'location', 'search' => 'Germ' ) )->get_data(), 'key' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertCount( 2, $this->rest( 'GET', self::BLOCKS, array(), array( 'kind' => 'location' ) )->get_data() );
		$this->assertCount( 3, $this->rest( 'GET', self::BLOCKS )->get_data(), 'Location blocks are not listed with IP blocks.' );
		$this->assertSame( array( 'id', 'ip' ), array_keys( $this->rest( 'GET', self::BLOCKS, array(), array( '_fields' => 'id,ip' ) )->get_data()[0] ) );
		$this->assertSame( 400, $this->rest( 'GET', self::BLOCKS, array(), array( 'per_page' => 101 ) )->get_status() );
	}

	/**
	 * A block is read, changed and deleted by ID; deleting returns an undo token.
	 */
	public function test_read_change_delete_and_undo(): void {
		$id = $this->block( '198.51.100.20' );

		$this->assertSame( '198.51.100.20', $this->rest( 'GET', self::BLOCKS . '/' . $id )->get_data()['ip'] );

		$changed = $this->rest( 'PUT', self::BLOCKS . '/' . $id, array( 'reason' => 'Brute force' ) );
		$this->assertSame( 200, $changed->get_status(), (string) wp_json_encode( $changed->get_data() ) );
		$this->assertSame( 'Brute force', $changed->get_data()['reason'] );
		$this->assertSame( '198.51.100.20', $changed->get_data()['ip'] );

		$kind = $this->rest( 'PUT', self::BLOCKS . '/' . $id, array( 'kind' => 'location' ) );
		$this->assertSame( 400, $kind->get_status() );

		$other = $this->block( '198.51.100.21' );
		$taken = $this->rest( 'PUT', self::BLOCKS . '/' . $id, array( 'ip' => '198.51.100.21' ) );
		$this->assertSame( 409, $taken->get_status() );
		$this->assertSame( $other, $taken->get_data()['data']['block_id'] );

		$deleted = $this->rest( 'DELETE', self::BLOCKS . '/' . $id );
		$this->assertSame( 200, $deleted->get_status() );
		$this->assertNull( DB::blocked( '198.51.100.20' ) );
		$this->assertSame( 404, $this->rest( 'GET', self::BLOCKS . '/' . $id )->get_status() );

		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $deleted->get_data()['undo_token'] );
		$this->assertSame( 'blocks', $undo->get_data()['kind'] );
		$this->assertSame( 'Brute force', $this->rest( 'GET', self::BLOCKS . '/' . $id )->get_data()['reason'], 'Restored with its ID.' );
	}

	/**
	 * Bulk delete removes the blocks (the legacy Blocked IPs bulk action
	 * deletes nothing) and returns a token that restores them.
	 */
	public function test_bulk_delete_blocks_and_undo(): void {
		$ids = array( $this->block( '198.51.100.31' ), $this->block( '198.51.100.32' ), $this->block( '198.51.100.33' ) );

		$response = $this->rest( 'POST', self::BLOCKS . '/bulk-delete', array( 'ids' => array( $ids[0], $ids[2], 999999 ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_data()['deleted'] );
		$this->assertNull( DB::blocked( '198.51.100.31' ) );
		$this->assertNotNull( DB::blocked( '198.51.100.32' ) );

		$this->rest( 'POST', '/zero-spam/v1/undo/' . $response->get_data()['undo_token'] );
		$this->assertNotNull( DB::blocked( '198.51.100.31' ) );
		$this->assertNotNull( DB::blocked( '198.51.100.33' ) );

		$this->assertSame( 400, $this->rest( 'POST', self::BLOCKS . '/bulk-delete', array( 'ids' => array() ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'POST', self::BLOCKS . '/bulk-delete', array( 'ids' => range( 1, 501 ) ) )->get_status() );
	}

	/**
	 * An undo does not restore a block next to a newer block of the same
	 * address or location; it reports it as skipped.
	 */
	public function test_undo_skips_addresses_blocked_again(): void {
		$first   = $this->block( '198.51.100.40' );
		$second  = $this->block( '198.51.100.41' );
		$deleted = $this->rest( 'POST', self::BLOCKS . '/bulk-delete', array( 'ids' => array( $first, $second ) ) )->get_data();

		$again = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'ip'     => '198.51.100.40',
				'type'   => 'permanent',
				'reason' => 'Blocked again',
			)
		)->get_data();

		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $deleted['undo_token'] )->get_data();

		$this->assertSame( 1, $undo['restored'] );
		$this->assertSame( 1, $undo['skipped'] );
		$this->assertSame( '1', $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i WHERE user_ip = %s', $GLOBALS['wpdb']->prefix . DB::$tables['blocked'], '198.51.100.40' ) ) );
		$this->assertSame( 'Blocked again', DB::blocked( '198.51.100.40' )['reason'] );
		$this->assertSame( $again['id'], (int) DB::blocked( '198.51.100.40' )['blocked_id'] );
		$this->assertNotNull( DB::blocked( '198.51.100.41' ) );
	}

	/**
	 * Bulk add: each address once, addresses blocked now skipped, an ended
	 * block replaced, invalid addresses reported; one INSERT; one undo token
	 * that removes the new blocks and puts the replaced one back.
	 */
	public function test_bulk_add_blocks_and_undo(): void {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['blocked'];
		$this->block( '192.0.2.1' );
		$ended = $this->block( '192.0.2.2', 'temporary', wp_date( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ), wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$was   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE blocked_id = %d', $table, $ended ), ARRAY_A );
		$end   = gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS );

		$body     = array(
			'ips'    => array( '192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.3', '2001:DB8::5', 'nope' ),
			'type'   => 'temporary',
			'end'    => $end,
			'reason' => 'Bulk',
		);
		$response = null;
		$queries  = $this->capture_queries(
			function () use ( &$response, $body ) {
				$response = $this->rest( 'POST', self::BLOCKS . '/bulk', $body );
			}
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $data['created'] );
		$this->assertSame( 1, $data['replaced'] );
		$this->assertSame( array( '192.0.2.1' ), $data['skipped'] );
		$this->assertArrayHasKey( 'nope', (array) $data['invalid'] );
		$this->assertCount( 1, preg_grep( '/^INSERT INTO `' . preg_quote( $table, '/' ) . '`/', $queries ), 'One INSERT for every new block.' );
		$this->assertSame( 4, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_ip = '2001:db8::5' AND reason = 'Bulk'", $table ) ), 'Stored in canonical form.' );
		$this->assertTrue( $this->rest( 'GET', self::BLOCKS . '/' . $ended )->get_data()['active'], 'The ended block runs again.' );

		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $data['undo_token'] );

		$this->assertSame( 200, $undo->get_status() );
		$this->assertSame( 'blocks_added', $undo->get_data()['kind'] );
		$this->assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), 'The new blocks are gone.' );
		$this->assertSame( $was, $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE blocked_id = %d', $table, $ended ), ARRAY_A ), 'The replaced block is back as it was.' );
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $data['undo_token'] )->get_status(), 'Once.' );
	}

	/**
	 * A failed INSERT is a 500 (rest_db_error), not a success that created nothing.
	 */
	public function test_bulk_add_reports_a_failed_insert(): void {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['blocked'];
		$break = static function ( $query ) use ( $table ) {
			return str_starts_with( (string) $query, 'INSERT INTO `' . $table . '`' )
				? str_replace( '`' . $table . '`', '`' . $table . '_missing`', (string) $query )
				: $query;
		};
		add_filter( 'query', $break );
		$suppressed = $wpdb->suppress_errors( true );

		try {
			$response = $this->rest(
				'POST',
				self::BLOCKS . '/bulk',
				array(
					'ips'  => array( '192.0.2.61', '192.0.2.62' ),
					'type' => 'permanent',
				)
			);
		} finally {
			remove_filter( 'query', $break );
			$wpdb->suppress_errors( $suppressed );
		}

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'rest_db_error', $response->get_data()['code'] );
		$this->assertSame( array(), $this->rest( 'GET', self::BLOCKS )->get_data() );
	}

	/**
	 * A problem with the shared fields refuses the whole request; nothing is written.
	 */
	public function test_bulk_add_refuses_bad_shared_fields(): void {
		$response = $this->rest(
			'POST',
			self::BLOCKS . '/bulk',
			array(
				'ips'  => array( '192.0.2.3', '192.0.2.4' ),
				'type' => 'temporary',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'end', $response->get_data()['data']['params'] );
		$this->assertSame( array(), $this->rest( 'GET', self::BLOCKS )->get_data() );
		$this->assertSame( 400, $this->rest( 'POST', self::BLOCKS . '/bulk', array( 'ips' => array() ) )->get_status(), 'At least one address.' );
	}

	/**
	 * In an .htaccess block mode a bulk add rewrites the rules once.
	 */
	public function test_bulk_add_refreshes_htaccess_once(): void {
		$this->htaccess = get_temp_dir() . 'zerospam-rules-' . wp_generate_password( 8, false ) . '.htaccess';
		file_put_contents( $this->htaccess, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$file  = $this->htaccess;
		$calls = 0;
		add_filter(
			'zerospam_htaccess_file',
			static function () use ( $file, &$calls ) {
				++$calls;
				return $file;
			}
		);
		$this->set_setting( 'block_method', 'htaccess_modern' );

		$this->rest(
			'POST',
			self::BLOCKS . '/bulk',
			array(
				'ips'  => array( '192.0.2.51', '192.0.2.52', '192.0.2.53' ),
				'type' => 'permanent',
			)
		);

		$this->assertSame( 1, $calls );
		$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.
		$this->assertStringContainsString( 'Require not ip ', $contents );
		foreach ( array( '192.0.2.51', '192.0.2.52', '192.0.2.53' ) as $ip ) {
			$this->assertStringContainsString( $ip, $contents );
		}
	}

	/**
	 * The two .htaccess methods.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_htaccess_methods(): array {
		return array(
			'modern' => array( 'htaccess_modern', 'Require not ip 192.0.2.44' ),
			'legacy' => array( 'htaccess_legacy', 'Deny from 192.0.2.44' ),
		);
	}

	/**
	 * In an .htaccess block mode the rules follow every change, and deleting
	 * the last block clears them (5.x left the last address denied).
	 *
	 * @dataProvider data_htaccess_methods
	 *
	 * @param string $method Block method.
	 * @param string $rule   Rule line for 192.0.2.44.
	 */
	public function test_htaccess_follows_the_blocks( string $method, string $rule ): void {
		$this->htaccess = get_temp_dir() . 'zerospam-rules-' . wp_generate_password( 8, false ) . '.htaccess';
		file_put_contents( $this->htaccess, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$file = $this->htaccess;
		add_filter(
			'zerospam_htaccess_file',
			static function () use ( $file ) {
				return $file;
			}
		);
		$this->set_setting( 'block_method', $method );

		$created = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'ip'   => '192.0.2.44',
				'type' => 'permanent',
			)
		);
		$this->assertStringContainsString( $rule, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.

		$deleted  = $this->rest( 'DELETE', self::BLOCKS . '/' . $created->get_data()['id'] );
		$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.
		$this->assertStringNotContainsString( '192.0.2.44', $contents, 'The last unblocked address stayed denied.' );
		$this->assertStringContainsString( '# BEGIN WordPress', $contents );

		$this->rest( 'POST', '/zero-spam/v1/undo/' . $deleted->get_data()['undo_token'] );
		$this->assertStringContainsString( $rule, (string) file_get_contents( $file ), 'Undo puts the rule back.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.
	}

	/**
	 * The IP Allowlist: read paged, replaced, edited; entries canonical; bad entries refused.
	 */
	public function test_allowed_ips_list(): void {
		$route = '/zero-spam/v1/rules/lists/allowed-ips';
		$this->set_setting( 'ip_whitelist', "192.0.2.1\n192.0.2.2" );

		$data = $this->rest( 'GET', $route )->get_data();
		$this->assertSame( array( '192.0.2.1', '192.0.2.2' ), $data['items'] );
		$this->assertSame( 'ip_whitelist', $data['key'] );
		$this->assertFalse( $data['locked'] );

		$replaced = $this->rest( 'PUT', $route, array( 'items' => array( '2001:DB8::1', '192.0.2.9', '192.0.2.9' ) ) );
		$this->assertSame( 200, $replaced->get_status(), (string) wp_json_encode( $replaced->get_data() ) );
		$this->assertSame( "2001:db8::1\n192.0.2.9", get_option( 'zero-spam-settings' )['ip_whitelist'] );

		$this->rest(
			'PUT',
			$route,
			array(
				'add'    => array( '198.51.100.3' ),
				'remove' => array( '2001:db8:0::1' ),
			)
		);
		$this->assertSame( "192.0.2.9\n198.51.100.3", get_option( 'zero-spam-settings' )['ip_whitelist'] );

		$bad = $this->rest( 'PUT', $route, array( 'items' => array( '192.0.2.1', 'not-an-ip', '10.0.0.0/8' ) ) );
		$this->assertSame( 400, $bad->get_status() );
		$this->assertStringContainsString( 'not-an-ip', $bad->get_data()['data']['params']['items'] );
		$this->assertSame( "192.0.2.9\n198.51.100.3", get_option( 'zero-spam-settings' )['ip_whitelist'], 'Nothing saved.' );

		$this->assertSame( 400, $this->rest( 'PUT', $route, array() )->get_status() );
		$this->assertSame( 400, $this->rest( 'PUT', $route, array( 'items' => array(), 'add' => array( '192.0.2.1' ) ) )->get_status() ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short body.
		$this->assertNotEmpty( get_option( 'zerospam_settings_audit' ), 'Changes are audited like PATCH /settings.' );
	}

	/**
	 * Saving a long list keeps the audit small: an entry records a summary of
	 * the list instead of its whole old and new values, and the audit log
	 * stays within its byte budget however many lists are saved (#404).
	 */
	public function test_long_list_changes_keep_the_audit_small(): void {
		$route   = '/zero-spam/v1/rules/lists/email-domains';
		$domains = array();
		for ( $i = 0; $i < 20000; $i++ ) {
			$domains[] = "spam-{$i}.example";
		}

		for ( $round = 0; $round < 8; $round++ ) {
			$this->assertSame( 200, $this->rest( 'PUT', $route, array( 'items' => array_slice( $domains, $round, 19000 ) ) )->get_status() );
		}

		$audit = get_option( 'zerospam_settings_audit' );
		$this->assertIsArray( $audit );
		$this->assertLessThan( 256 * KB_IN_BYTES, strlen( serialize( $audit ) ), 'The audit log stays within its budget.' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Measures the stored size.

		$change = $audit[0]['changes']['blocked_email_domains'];
		$this->assertSame( 19000, $change['new']['lines'], 'The new list is summarised.' );
		$this->assertSame( 19000, $change['old']['lines'], 'The old list is summarised.' );
		$this->assertArrayHasKey( 'sha1', $change['new'] );
		$this->assertLessThan( 1 * KB_IN_BYTES, strlen( (string) wp_json_encode( $change ) ) );
	}

	/**
	 * A short value is still audited as it is.
	 */
	public function test_short_values_are_audited_as_they_are(): void {
		$this->rest( 'PUT', '/zero-spam/v1/rules/lists/email-domains', array( 'items' => array( 'one.example' ) ) );

		$this->assertSame( 'one.example', get_option( 'zerospam_settings_audit' )[0]['changes']['blocked_email_domains']['new'] );
	}

	/**
	 * Entries saved before 6.0.1 that hold whole lists are summarised on the
	 * next save, and recent entries are kept rather than dropped for them.
	 */
	public function test_old_audit_entries_are_shrunk_on_the_next_save(): void {
		$list = implode( "\n", array_map( static fn( int $i ): string => "old-{$i}.example", range( 1, 30000 ) ) );
		$old  = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$old[] = array(
				'timestamp' => '2026-01-0' . ( $i + 1 ) . ' 00:00:00',
				'scope'     => 'site',
				'changes'   => array(
					'blocked_email_domains' => array(
						'old' => $list,
						'new' => $list . "\nextra-{$i}.example",
					),
				),
			);
		}
		update_option( 'zerospam_settings_audit', $old, false );

		$this->rest( 'PUT', '/zero-spam/v1/rules/lists/email-domains', array( 'items' => array( 'one.example' ) ) );

		$audit = get_option( 'zerospam_settings_audit' );
		$this->assertCount( 7, $audit, 'No entry is dropped once the old ones are summarised.' );
		$this->assertLessThan( 256 * KB_IN_BYTES, strlen( serialize( $audit ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Measures the stored size.
		$this->assertSame( 30000, $audit[1]['changes']['blocked_email_domains']['old']['lines'] );
		$this->assertSame( 30001, $audit[1]['changes']['blocked_email_domains']['new']['lines'] );
	}

	/**
	 * Blocked email domains live in their own non-autoloaded option, can be
	 * searched and paged, and are lower-cased.
	 */
	public function test_email_domains_list(): void {
		global $wpdb;

		$route = '/zero-spam/v1/rules/lists/email-domains';

		$response = $this->rest( 'PUT', $route, array( 'items' => array( '@Mailinator.com', 'spam.example', 'trash-mail.example' ) ) );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( "mailinator.com\nspam.example\ntrash-mail.example", get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertContains( $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'zerospam_blocked_email_domains' ) ), array( 'no', 'off' ) );

		$search = $this->rest( 'GET', $route, array(), array( 'search' => 'mail', 'per_page' => 1, 'page' => 2 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( 'trash-mail.example' ), $search->get_data()['items'] );
		$this->assertSame( 2, $search->get_data()['total'] );
		$this->assertSame( '2', $search->get_headers()['X-WP-TotalPages'] );

		$bad = $this->rest( 'PUT', $route, array( 'add' => array( 'not a domain' ) ) );
		$this->assertSame( 400, $bad->get_status() );
		$this->assertArrayHasKey( 'add', $bad->get_data()['data']['params'] );
	}

	/**
	 * Allowed words come with the shortest disallowed word length.
	 */
	public function test_allowed_words_and_min_length(): void {
		$route = '/zero-spam/v1/rules/lists/allowed-words';

		$response = $this->rest(
			'PUT',
			$route,
			array(
				'items'      => array( 'ugg', 'Viagra' ),
				'min_length' => 4,
			)
		);

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( array( 'ugg', 'Viagra' ), $response->get_data()['items'] );
		$this->assertSame( 4, $response->get_data()['min_length']['value'] );
		$this->assertSame( 4, \ZeroSpam\Core\Utilities::get_min_disallowed_length(), 'The cached length was reset.' );

		foreach ( array( -1, 101, '4' ) as $invalid ) {
			$bad = $this->rest( 'PUT', $route, array( 'min_length' => $invalid ) );
			$this->assertSame( 400, $bad->get_status() );
			$this->assertArrayHasKey( 'min_length', $bad->get_data()['data']['params'] );
		}
	}

	/**
	 * The never-locked-out addresses: listed with the current one marked, removed one by one.
	 */
	public function test_no_lockout(): void {
		update_option( Admin_Safe_IPs::OPTION, array( self::DEFAULT_IP, '2001:db8::5' ), false );
		update_option(
			Admin_Safe_IPs::NOTICE_OPTION,
			array(
				'ip'      => '2001:db8::5',
				'user_id' => 1,
			),
			false
		);

		$data = $this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' )->get_data();
		$this->assertSame(
			array(
				array(
					'ip'      => self::DEFAULT_IP,
					'current' => true,
					'blocked' => false,
				),
				array(
					'ip'      => '2001:db8::5',
					'current' => false,
					'blocked' => false,
				),
			),
			$data['items']
		);
		$this->assertSame( 10, $data['max'] );

		$removed = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/2001:DB8:0::5' );
		$this->assertSame( 200, $removed->get_status() );
		$this->assertSame( '2001:db8::5', $removed->get_data()['removed'] );
		$this->assertSame( array( self::DEFAULT_IP ), Admin_Safe_IPs::get_ips() );
		$this->assertFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ), 'The notice about the removed address is gone.' );

		$this->assertSame( 404, $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/192.0.2.200' )->get_status() );
	}

	/**
	 * Removing a no-lockout address can be undone once, by the administrator
	 * who removed it, for two minutes; an address saved again meanwhile is
	 * not added twice.
	 */
	public function test_no_lockout_removal_can_be_undone(): void {
		update_option( Admin_Safe_IPs::OPTION, array( '203.0.113.10', '2001:db8::5' ), false );

		$removed = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/203.0.113.10' )->get_data();
		$token   = $removed['undo_token'];
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $token );
		$this->assertSame( array( '2001:db8::5' ), Admin_Safe_IPs::get_ips() );

		$admin = get_current_user_id();
		$this->as_role( 'administrator' );
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status(), 'Another administrator cannot redeem the token.' );

		wp_set_current_user( $admin );
		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $token );
		$this->assertSame( 200, $undo->get_status() );
		$this->assertSame(
			array(
				'kind'     => 'no-lockout',
				'restored' => 1,
				'skipped'  => 0,
			),
			$undo->get_data()
		);
		$this->assertSame( array( '2001:db8::5', '203.0.113.10' ), Admin_Safe_IPs::get_ips() );
		$this->assertSame( 404, $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_status(), 'A token works once.' );

		// Saved again before the undo: skipped, never listed twice.
		$again = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/2001:db8::5' )->get_data();
		update_option( Admin_Safe_IPs::OPTION, array( '203.0.113.10', '2001:db8::5' ), false );
		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $again['undo_token'] )->get_data();
		$this->assertSame( 0, $undo['restored'] );
		$this->assertSame( 1, $undo['skipped'] );
		$this->assertSame( array( '203.0.113.10', '2001:db8::5' ), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * Sorted by end, temporary blocks come by their end date and permanent
	 * ones (they never end) last; descending puts them first.
	 */
	public function test_sort_by_end_puts_permanent_blocks_last(): void {
		$this->block( '198.51.100.70' );
		foreach ( array(
			'198.51.100.71' => 5,
			'198.51.100.72' => 2,
		) as $ip => $days ) {
			$this->rest(
				'POST',
				self::BLOCKS,
				array(
					'ip'   => $ip,
					'type' => 'temporary',
					'end'  => gmdate( 'Y-m-d\TH:i:s\Z', time() + $days * DAY_IN_SECONDS ),
				)
			);
		}

		$asc = array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'orderby' => 'end', 'order' => 'asc' ) )->get_data(), 'ip' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( '198.51.100.72', '198.51.100.71', '198.51.100.70' ), $asc );

		$desc = array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'orderby' => 'end', 'order' => 'desc' ) )->get_data(), 'ip' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( '198.51.100.70', '198.51.100.71', '198.51.100.72' ), $desc );
	}

	/**
	 * Locations sorted A–Z go by the country's name, not its code (by code
	 * CH, DE, FR, SE would read Switzerland, Germany, France, Sweden), and
	 * other locations by their value.
	 */
	public function test_locations_sort_by_country_name(): void {
		foreach ( array(
			array( 'country_code', 'DE' ),
			array( 'country_code', 'FR' ),
			array( 'country_code', 'CH' ),
			array( 'country_code', 'SE' ),
			array( 'city', 'Berlin' ),
		) as $location ) {
			$this->rest(
				'POST',
				self::BLOCKS,
				array(
					'kind'     => 'location',
					'key_type' => $location[0],
					'key'      => $location[1],
					'type'     => 'permanent',
				)
			);
		}

		$asc = array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'kind' => 'location', 'orderby' => 'key', 'order' => 'asc' ) )->get_data(), 'label' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( 'Berlin', 'France', 'Germany', 'Sweden', 'Switzerland' ), $asc );

		$desc = array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'kind' => 'location', 'orderby' => 'key', 'order' => 'desc' ) )->get_data(), 'label' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( 'Switzerland', 'Sweden', 'Germany', 'France', 'Berlin' ), $desc );
	}

	/**
	 * Removing your own no-lockout address while it is blocked locks you out
	 * (in .htaccess mode the web server denies it at once, so Undo and the
	 * rescue link never arrive): refused with 409 rest_would_lock_out
	 * unless confirmed; the list and .htaccess are left alone until then.
	 */
	public function test_removing_your_own_blocked_address_needs_confirmation(): void {
		$this->htaccess = get_temp_dir() . 'zerospam-lockout-' . wp_generate_password( 8, false ) . '.htaccess';
		file_put_contents( $this->htaccess, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$file = $this->htaccess;
		add_filter(
			'zerospam_htaccess_file',
			static function () use ( $file ) {
				return $file;
			}
		);
		$this->set_setting( 'block_method', 'htaccess_modern' );
		$this->set_request( '/wp-json/', self::DEFAULT_IP );
		update_option( Admin_Safe_IPs::OPTION, array( self::DEFAULT_IP, '198.51.100.70' ), false );
		$this->block( self::DEFAULT_IP );

		$listed = $this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' )->get_data()['items'];
		$this->assertTrue( $listed[0]['current'] );
		$this->assertTrue( $listed[0]['blocked'], 'The list says the address is blocked.' );
		$this->assertFalse( $listed[1]['blocked'] );

		$refused = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/' . self::DEFAULT_IP );
		$this->assertSame( 409, $refused->get_status() );
		$this->assertSame( 'rest_would_lock_out', $refused->get_data()['code'] );
		$this->assertStringContainsString( self::DEFAULT_IP, $refused->get_data()['message'] );
		$this->assertSame( array( self::DEFAULT_IP, '198.51.100.70' ), Admin_Safe_IPs::get_ips() );
		$this->assertStringNotContainsString( self::DEFAULT_IP, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.

		$confirmed = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/' . self::DEFAULT_IP, array(), array( 'confirm' => '1' ) );
		$this->assertSame( 200, $confirmed->get_status() );
		$this->assertSame( array( '198.51.100.70' ), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * Another administrator's address, or your own while it is not blocked,
	 * is removed at once.
	 */
	public function test_removing_other_or_unblocked_addresses_needs_no_confirmation(): void {
		$this->set_request( '/wp-json/', self::DEFAULT_IP );
		update_option( Admin_Safe_IPs::OPTION, array( self::DEFAULT_IP, '198.51.100.70' ), false );
		$this->block( '198.51.100.70' );

		$this->assertSame( 200, $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/198.51.100.70' )->get_status() );
		$this->assertSame( 200, $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/' . self::DEFAULT_IP )->get_status() );
		$this->assertSame( array(), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * Undo of a no-lockout removal compares addresses in canonical form: an
	 * address saved again in another spelling is not listed twice.
	 */
	public function test_no_lockout_undo_compares_canonical_addresses(): void {
		update_option( Admin_Safe_IPs::OPTION, array( '2001:db8:0:0::5' ), false );
		$token = $this->rest( 'DELETE', '/zero-spam/v1/rules/no-lockout/2001:db8::5' )->get_data()['undo_token'];

		update_option( Admin_Safe_IPs::OPTION, array( '2001:DB8::5' ), false );
		$undo = $this->rest( 'POST', '/zero-spam/v1/undo/' . $token )->get_data();

		$this->assertSame( 0, $undo['restored'] );
		$this->assertSame( array( '2001:DB8::5' ), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * A city or region made only of markup is refused (it was saved as an
	 * empty block), and so is a value with < or >.
	 */
	public function test_location_values_with_markup_are_refused(): void {
		foreach ( array( '<b></b>', '<script>x</script>', 'Spring<field' ) as $key ) {
			$response = $this->rest(
				'POST',
				self::BLOCKS,
				array(
					'kind'     => 'location',
					'key_type' => 'city',
					'key'      => $key,
					'type'     => 'permanent',
				)
			);
			$this->assertSame( 400, $response->get_status(), $key );
			$this->assertArrayHasKey( 'key', $response->get_data()['data']['params'], $key );
		}
		$this->assertCount( 0, $this->rest( 'GET', self::BLOCKS, array(), array( 'kind' => 'location' ) )->get_data() );
	}

	/**
	 * Words with < or > are refused, and the message lists them as sent
	 * (it listed nothing for markup), instead of storing "a&lt;b".
	 */
	public function test_words_with_angle_brackets_are_refused_and_named(): void {
		$response = $this->rest( 'PUT', '/zero-spam/v1/rules/lists/allowed-words', array( 'items' => array( 'ok', 'a<b', '<b>' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$message = $response->get_data()['data']['params']['items'];
		$this->assertStringContainsString( 'a<b', $message );
		$this->assertStringContainsString( '<b>', $message );
		$this->assertStringNotContainsString( '&lt;', $message );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * Blocked IPs sorted by address go by number (192.0.2.9 before
	 * 192.0.2.10), IPv4 before IPv6.
	 */
	public function test_ip_blocks_sort_by_number(): void {
		foreach ( array( '192.0.2.100', '2001:db8::1', '192.0.2.9', '192.0.2.10' ) as $ip ) {
			$this->block( $ip );
		}

		$asc = array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'orderby' => 'key', 'order' => 'asc' ) )->get_data(), 'ip' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( '192.0.2.9', '192.0.2.10', '192.0.2.100', '2001:db8::1' ), $asc );

		$desc = array_column( $this->rest( 'GET', self::BLOCKS, array(), array( 'orderby' => 'key', 'order' => 'desc' ) )->get_data(), 'ip' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.
		$this->assertSame( array( '2001:db8::1', '192.0.2.100', '192.0.2.10', '192.0.2.9' ), $desc );
	}

	/**
	 * The counts the Rules tabs show: blocked addresses and locations, the
	 * three lists and the no-lockout addresses.
	 */
	public function test_counts(): void {
		$this->assertSame(
			array(
				'ips'        => 0,
				'locations'  => 0,
				'allowlist'  => 0,
				'email'      => 0,
				'words'      => 0,
				'no-lockout' => 0,
				'network'    => 0,
			),
			\ZeroSpam\Includes\Rest\Rules_Controller::counts()
		);

		$this->block( '198.51.100.80' );
		$this->block( '198.51.100.81' );
		$this->rest(
			'POST',
			self::BLOCKS,
			array(
				'kind'     => 'location',
				'key_type' => 'zip',
				'key'      => '90210',
				'type'     => 'permanent',
			)
		);
		$this->rest( 'PUT', '/zero-spam/v1/rules/lists/allowed-ips', array( 'items' => array( '192.0.2.1', '192.0.2.2', '192.0.2.3' ) ) );
		$this->rest( 'PUT', '/zero-spam/v1/rules/lists/email-domains', array( 'items' => array( 'spam.example' ) ) );
		update_option( Admin_Safe_IPs::OPTION, array( '203.0.113.10' ), false );

		$counts = \ZeroSpam\Includes\Rest\Rules_Controller::counts();
		$this->assertSame( 2, $counts['ips'] );
		$this->assertSame( 1, $counts['locations'] );
		$this->assertSame( 3, $counts['allowlist'] );
		$this->assertSame( 1, $counts['email'] );
		$this->assertSame( 0, $counts['words'] );
		$this->assertSame( 1, $counts['no-lockout'] );
	}

	/**
	 * Listing the never-locked-out addresses builds the settings registry at
	 * most once (the visitor IP and the trusted proxies read through it).
	 */
	public function test_no_lockout_builds_the_registry_once(): void {
		update_option( Admin_Safe_IPs::OPTION, array( self::DEFAULT_IP ), false );
		$this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' );

		$builds = $this->count_registry_builds(
			function () {
				$this->assertSame( 200, $this->rest( 'GET', '/zero-spam/v1/rules/no-lockout' )->get_status() );
			}
		);

		$this->assertLessThanOrEqual( 1, $builds );
	}

	/**
	 * A time without an offset, in a site's time zone.
	 *
	 * @return array<string, array{0: string, 1: float, 2: string, 3: string}>
	 */
	public static function data_wall_clock_times(): array {
		return array(
			'date alone, UTC'                      => array( '', 0.0, '2025-11-26', '2025-11-26T00:00:00Z' ),
			'date alone, New York in summer'       => array( 'America/New_York', 0.0, '2026-07-15', '2026-07-15T04:00:00Z' ),
			'date alone, New York in winter'       => array( 'America/New_York', 0.0, '2026-01-15', '2026-01-15T05:00:00Z' ),
			'date alone, Kolkata'                  => array( 'Asia/Kolkata', 0.0, '2025-11-26', '2025-11-25T18:30:00Z' ),
			'date alone, manual offset'            => array( '', 5.5, '2025-11-26', '2025-11-25T18:30:00Z' ),
			'time, New York'                       => array( 'America/New_York', 0.0, '2026-07-15T09:30:00', '2026-07-15T13:30:00Z' ),
			'time, manual negative offset'         => array( '', -3.5, '2026-07-15T09:30:00', '2026-07-15T13:00:00Z' ),
			'clocks skip it: moved forward'        => array( 'America/New_York', 0.0, '2026-03-08T02:30:00', '2026-03-08T07:30:00Z' ),
			'clocks repeat it: first occurrence'   => array( 'America/New_York', 0.0, '2026-11-01T01:30:00', '2026-11-01T05:30:00Z' ),
			'clocks skip midnight: first instant'  => array( 'America/Santiago', 0.0, '2026-09-06', '2026-09-06T04:00:00Z' ),
			'an explicit moment stays that moment' => array( 'America/New_York', 0.0, '2026-07-15T00:00:00Z', '2026-07-15T00:00:00Z' ),
		);
	}

	/**
	 * What the block form sends means that wall clock in the site's time zone,
	 * also on the nights the clocks change (the documented behaviour: AGENTS.md).
	 *
	 * @dataProvider data_wall_clock_times
	 *
	 * @param string $zone   Option timezone_string.
	 * @param float  $offset Option gmt_offset.
	 * @param string $sent   Start sent.
	 * @param string $stored Start returned.
	 */
	public function test_times_without_an_offset_are_the_sites_wall_clock( string $zone, float $offset, string $sent, string $stored ): void {
		update_option( 'timezone_string', $zone );
		update_option( 'gmt_offset', $offset );

		$response = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'kind'  => 'ip',
				'ip'    => '198.51.100.70',
				'type'  => 'permanent',
				'start' => $sent,
			)
		);

		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( $stored, $response->get_data()['start'] );
	}

	/**
	 * The block form sends a date only when it was edited: without dates an
	 * edit keeps them to the second, which a wall-clock minute could not (a
	 * date-only end's 23:59:59, the second 01:30 of the night the clocks go
	 * back), however many times it is saved.
	 */
	public function test_an_edit_without_dates_keeps_them_to_the_second(): void {
		update_option( 'timezone_string', 'America/New_York' );

		$created = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'kind'  => 'ip',
				'ip'    => '198.51.100.71',
				'type'  => 'temporary',
				'start' => '2026-11-01T06:30:00Z',
				'end'   => '2099-08-01',
			)
		);
		$this->assertSame( 201, $created->get_status(), (string) wp_json_encode( $created->get_data() ) );
		$this->assertSame( '2099-08-02T03:59:59Z', $created->get_data()['end'], 'A date-only end is 23:59:59 on the site.' );

		$route = self::BLOCKS . '/' . $created->get_data()['id'];

		foreach ( array( 'first save', 'second save' ) as $save ) {
			$saved = $this->rest(
				'PUT',
				$route,
				array(
					'kind'   => 'ip',
					'ip'     => '198.51.100.71',
					'type'   => 'temporary',
					'reason' => 'Edited',
				)
			);
			$this->assertSame( 200, $saved->get_status(), (string) wp_json_encode( $saved->get_data() ) );
			$this->assertSame( '2026-11-01T06:30:00Z', $saved->get_data()['start'], $save );
			$this->assertSame( '2099-08-02T03:59:59Z', $saved->get_data()['end'], $save );
		}
	}

	/**
	 * A block that has ended can still be edited when its end is not sent
	 * (the form does not send an end it did not change).
	 */
	public function test_an_ended_block_can_be_edited_without_its_dates(): void {
		global $wpdb;

		$id = $this->block( '198.51.100.72' );
		$wpdb->update(
			$wpdb->prefix . DB::$tables['blocked'],
			array(
				'blocked_type' => 'temporary',
				'start_block'  => '2020-01-01 00:00:00',
				'end_block'    => '2020-01-02 23:59:59',
			),
			array( 'blocked_id' => $id )
		);

		$saved = $this->rest(
			'PUT',
			self::BLOCKS . '/' . $id,
			array(
				'kind'   => 'ip',
				'ip'     => '198.51.100.72',
				'type'   => 'temporary',
				'reason' => 'Edited',
			)
		);

		$this->assertSame( 200, $saved->get_status(), (string) wp_json_encode( $saved->get_data() ) );
		$this->assertSame( '2020-01-01T00:00:00Z', $saved->get_data()['start'] );
		$this->assertSame( '2020-01-02T23:59:59Z', $saved->get_data()['end'] );
	}

	/**
	 * Clearing the start of a block whose end has passed: the kept end is now
	 * before the new start (now), so the server says the end must be in the
	 * future and after the start (the End field still shows that old date),
	 * not that a temporary block needs an end date.
	 */
	public function test_an_emptied_start_past_a_kept_end_names_the_end(): void {
		global $wpdb;

		$id = $this->block( '198.51.100.74' );
		$wpdb->update(
			$wpdb->prefix . DB::$tables['blocked'],
			array(
				'blocked_type' => 'temporary',
				'start_block'  => '2020-01-01 00:00:00',
				'end_block'    => '2020-01-02 23:59:59',
			),
			array( 'blocked_id' => $id )
		);

		$saved = $this->rest(
			'PUT',
			self::BLOCKS . '/' . $id,
			array(
				'kind'  => 'ip',
				'ip'    => '198.51.100.74',
				'type'  => 'temporary',
				'start' => '',
			)
		);

		$this->assertSame( 400, $saved->get_status() );
		$this->assertSame( 'The end must be in the future and after the start.', $saved->get_data()['data']['params']['end'] );
		$stored = $this->rest( 'GET', self::BLOCKS . '/' . $id )->get_data();
		$this->assertSame( '2020-01-01T00:00:00Z', $stored['start'], 'Nothing was saved.' );
		$this->assertSame( '2020-01-02T23:59:59Z', $stored['end'], 'Nothing was saved.' );
	}

	/**
	 * The block form sends an emptied start as '' ("Empty starts the block
	 * now"): an edit with `start: ''` starts the block now instead of keeping
	 * the stored start. An emptied end is refused, never kept or dropped.
	 */
	public function test_an_emptied_start_starts_the_block_now_and_an_emptied_end_is_refused(): void {
		$created = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'kind'  => 'ip',
				'ip'    => '198.51.100.73',
				'type'  => 'temporary',
				'start' => '2098-01-01',
				'end'   => '2099-01-01',
			)
		);
		$this->assertSame( 201, $created->get_status(), (string) wp_json_encode( $created->get_data() ) );

		$route  = self::BLOCKS . '/' . $created->get_data()['id'];
		$before = time();
		$saved  = $this->rest(
			'PUT',
			$route,
			array(
				'kind'  => 'ip',
				'ip'    => '198.51.100.73',
				'type'  => 'temporary',
				'start' => '',
			)
		);
		$this->assertSame( 200, $saved->get_status(), (string) wp_json_encode( $saved->get_data() ) );
		$start = (int) strtotime( $saved->get_data()['start'] );
		$this->assertGreaterThanOrEqual( $before, $start, 'An emptied start is now.' );
		$this->assertLessThanOrEqual( time(), $start, 'An emptied start is now.' );
		$this->assertSame( $created->get_data()['end'], $saved->get_data()['end'], 'The end was not sent and is kept.' );

		$emptied = $this->rest(
			'PUT',
			$route,
			array(
				'kind' => 'ip',
				'ip'   => '198.51.100.73',
				'type' => 'temporary',
				'end'  => '',
			)
		);
		$this->assertSame( 400, $emptied->get_status() );
		$this->assertArrayHasKey( 'end', $emptied->get_data()['data']['params'] );
		$this->assertSame( $created->get_data()['end'], $this->rest( 'GET', $route )->get_data()['end'] );
	}
}
