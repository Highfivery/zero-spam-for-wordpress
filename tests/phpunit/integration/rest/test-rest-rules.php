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
	 * In an .htaccess block mode the rules follow every change, and deleting
	 * the last block clears them (5.x left the last address denied).
	 */
	public function test_htaccess_follows_the_blocks(): void {
		$this->htaccess = get_temp_dir() . 'zerospam-rules-' . wp_generate_password( 8, false ) . '.htaccess';
		file_put_contents( $this->htaccess, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$file = $this->htaccess;
		add_filter(
			'zerospam_htaccess_file',
			static function () use ( $file ) {
				return $file;
			}
		);
		$this->set_setting( 'block_method', 'htaccess_modern' );

		$created = $this->rest(
			'POST',
			self::BLOCKS,
			array(
				'ip'   => '192.0.2.44',
				'type' => 'permanent',
			)
		);
		$this->assertStringContainsString( 'Require not ip 192.0.2.44', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.

		$deleted  = $this->rest( 'DELETE', self::BLOCKS . '/' . $created->get_data()['id'] );
		$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.
		$this->assertStringNotContainsString( '192.0.2.44', $contents, 'The last unblocked address stayed denied.' );
		$this->assertStringContainsString( '# BEGIN WordPress', $contents );

		$this->rest( 'POST', '/zero-spam/v1/undo/' . $deleted->get_data()['undo_token'] );
		$this->assertStringContainsString( 'Require not ip 192.0.2.44', (string) file_get_contents( $file ), 'Undo puts the rule back.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.
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
				),
				array(
					'ip'      => '2001:db8::5',
					'current' => false,
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
}
