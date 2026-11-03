<?php
/**
 * Tests that blocks refuse addresses no visitor has.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\Rules_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * Since 6.0.0 `rules/blocks` and `rules/blocks/bulk` refuse loopback and
 * unspecified addresses, as the network page's blocks do; private ranges
 * stay allowed (visitors behind a proxy or on an intranet have them). A 5.x
 * block of such an address can still be edited.
 *
 * @covers \ZeroSpam\Includes\Rest\Rules_Controller::is_never_blocked
 * @covers \ZeroSpam\Includes\Rest\Rules_Controller::create_block
 * @covers \ZeroSpam\Includes\Rest\Rules_Controller::bulk_create_blocks
 * @covers \ZeroSpam\Includes\Rest\Rules_Controller::update_block
 */
class Test_Rest_Rules_Loopback extends TestCase {

	/**
	 * An administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->as_role( 'administrator' );
	}

	/**
	 * Addresses no visitor has.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_never_blocked(): array {
		return array(
			'IPv4 loopback'        => array( '127.0.0.1' ),
			'IPv4 loopback range'  => array( '127.5.5.5' ),
			'IPv6 loopback'        => array( '::1' ),
			'IPv4 unspecified'     => array( '0.0.0.0' ),
			'IPv6 unspecified'     => array( '::' ),
			'IPv6 loopback, long'  => array( '0:0:0:0:0:0:0:1' ),
			'IPv4-mapped loopback' => array( '::ffff:127.0.0.1' ),
			'loopback with spaces' => array( ' 127.0.0.1 ' ),
		);
	}

	/**
	 * POST rules/blocks refuses them with the field's error.
	 *
	 * @dataProvider data_never_blocked
	 *
	 * @param string $ip Address.
	 */
	public function test_create_refuses( string $ip ): void {
		$response = $this->rest(
			'POST',
			'/zero-spam/v1/rules/blocks',
			array(
				'kind' => 'ip',
				'ip'   => $ip,
				'type' => 'permanent',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'ip', $response->get_data()['data']['params'] );
		$this->assertSame( 0, $this->count_blocks() );
	}

	/**
	 * POST rules/blocks/bulk lists them as invalid and blocks the rest.
	 */
	public function test_bulk_refuses_and_keeps_the_others(): void {
		$response = $this->rest(
			'POST',
			'/zero-spam/v1/rules/blocks/bulk',
			array(
				'ips'  => array( '127.0.0.1', '127.5.5.5', '::1', '0.0.0.0', '::', '10.0.0.1', '192.168.1.1' ),
				'type' => 'permanent',
			)
		);

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertSame( 2, $data['created'] );
		$this->assertEqualsCanonicalizing( array( '127.0.0.1', '127.5.5.5', '::1', '0.0.0.0', '::' ), array_keys( (array) $data['invalid'] ) );
	}

	/**
	 * Private ranges can be blocked.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_private(): array {
		return array(
			'10/8'       => array( '10.0.0.1' ),
			'192.168/16' => array( '192.168.1.1' ),
			'172.16/12'  => array( '172.16.0.9' ),
			'IPv6 ULA'   => array( 'fd00::1' ),
			'128.0.0.1'  => array( '128.0.0.1' ),
			'1.27.0.0.1' => array( '1.27.0.1' ),
		);
	}

	/**
	 * POST rules/blocks accepts them.
	 *
	 * @dataProvider data_private
	 *
	 * @param string $ip Address.
	 */
	public function test_private_ranges_are_accepted( string $ip ): void {
		$response = $this->rest(
			'POST',
			'/zero-spam/v1/rules/blocks',
			array(
				'kind' => 'ip',
				'ip'   => $ip,
				'type' => 'permanent',
			)
		);

		$this->assertContains( $response->get_status(), array( 200, 201 ), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 1, $this->count_blocks() );
	}

	/**
	 * A 5.x block of a loopback address can still be edited, but not moved to another one.
	 */
	public function test_existing_loopback_block_can_be_edited(): void {
		$id = $this->block( '127.0.0.1' );

		$response = $this->rest( 'PUT', '/zero-spam/v1/rules/blocks/' . $id, array( 'reason' => 'Kept from 5.x' ) );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'Kept from 5.x', $response->get_data()['reason'] );

		$response = $this->rest( 'PUT', '/zero-spam/v1/rules/blocks/' . $id, array( 'ip' => '::1' ) );
		$this->assertSame( 400, $response->get_status() );

		$this->assertSame( 200, $this->rest( 'DELETE', '/zero-spam/v1/rules/blocks/' . $id )->get_status(), 'It can be removed.' );
	}

	/**
	 * The shared check.
	 */
	public function test_is_never_blocked(): void {
		$this->assertTrue( Rules_Controller::is_never_blocked( '127.0.0.1' ) );
		$this->assertTrue( Rules_Controller::is_never_blocked( '::' ) );
		$this->assertFalse( Rules_Controller::is_never_blocked( '10.0.0.1' ) );
		$this->assertFalse( Rules_Controller::is_never_blocked( '2001:db8::1' ) );
	}

	/**
	 * Rows in the blocked table.
	 */
	private function count_blocks(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . \ZeroSpam\Includes\DB::$tables['blocked'] ) );
	}
}
