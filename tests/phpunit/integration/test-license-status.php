<?php
/**
 * Tests for License_Status and the zero-spam/v1/license route.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\TestCase;

/**
 * One license status, cached, for every screen and the REST API.
 *
 * @covers \ZeroSpam\Core\License_Status
 * @covers \ZeroSpam\Includes\Rest\License_Controller
 */
class Test_License_Status extends TestCase {

	/**
	 * Pattern of the license endpoint.
	 */
	const ENDPOINT = '#/wp-json/v2/get-license#';

	/**
	 * A license key.
	 */
	const KEY = 'test-license-ABCD-z9y8';

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/license';

	/**
	 * Name of the constant the constant-source test uses.
	 */
	const TEST_CONSTANT = 'ZEROSPAM_TESTS_LICENSE_ROUTE_KEY';

	/**
	 * Settings_Schema::$constants before the test.
	 *
	 * @var array<string, string>
	 */
	private array $constants_backup = array();

	/**
	 * Starts every test without a key or cached status.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->constants_backup = ( new ReflectionProperty( Settings_Schema::class, 'constants' ) )->getValue();

		delete_option( 'zero-spam-zerospam' );
		delete_option( 'zero-spam-api-monitoring' );
		delete_transient( License_Status::TRANSIENT );
		delete_transient( 'zero_spam_circuit_open' );
		delete_transient( 'zero_spam_failure_count' );
		delete_transient( sanitize_title( 'license_' . self::KEY ) );
		delete_transient( sanitize_title( 'license_other-key-1111' ) );
	}

	/**
	 * Restores the constants map.
	 */
	public function tear_down(): void {
		self::set_static( Settings_Schema::class, 'constants', $this->constants_backup );

		parent::tear_down();
	}

	/**
	 * Maps the license to a test constant, with a registry filter doing what
	 * modules/class-zerospam.php does for ZEROSPAM_LICENSE_KEY.
	 */
	private function use_test_constant(): void {
		if ( ! defined( self::TEST_CONSTANT ) ) {
			define( self::TEST_CONSTANT, 'route-CONSTANTKEY-4321' );
		}

		self::set_static( Settings_Schema::class, 'constants', array( 'zerospam_license' => self::TEST_CONSTANT ) );
		add_filter(
			'zerospam_settings',
			static function ( array $settings ): array {
				if ( empty( $settings['zerospam_license']['value'] ) ) {
					$settings['zerospam_license']['value'] = constant( self::TEST_CONSTANT );
				}

				return $settings;
			},
			20
		);
		Settings_Schema::reset_cache();
	}

	/**
	 * A valid license as the API answers it.
	 *
	 * @param int $remaining Queries left.
	 * @return array<string, mixed>
	 */
	private static function valid_body( int $remaining = 900 ): array {
		return array(
			'license_key'       => self::KEY,
			'product_id'        => 'ZEROSPAMBUSINESS',
			'queries_limit'     => 1000,
			'queries_made'      => 1000 - $remaining,
			'queries_remaining' => $remaining,
			'entitled'          => true,
		);
	}

	/**
	 * Without a key the status is `none` and nothing is requested.
	 */
	public function test_no_key(): void {
		$status = License_Status::get();

		$this->assertSame( License_Status::NONE, $status['status'] );
		$this->assertSame( 0, HttpMock::hits( self::ENDPOINT ) );
		$this->assertFalse( License_Status::has_key() );
	}

	/**
	 * Each API answer maps to one status.
	 *
	 * @return array<string, array{0: array<string, mixed>|string, 1: int, 2: string}>
	 */
	public static function data_answers(): array {
		return array(
			'valid'               => array( self::valid_body(), 200, License_Status::VALID ),
			'quota used up'       => array( self::valid_body( 0 ), 200, License_Status::QUOTA_EXCEEDED ),
			'rejected key'        => array(
				array(
					'status'   => 400,
					'response' => 'invalid_license',
				),
				200,
				License_Status::INVALID,
			),
			'server error'        => array( 'Oops', 500, License_Status::UNREACHABLE ),
			'unexpected response' => array( array( 'foo' => 'bar' ), 200, License_Status::UNREACHABLE ),
		);
	}

	/**
	 * Status for each answer of the API.
	 *
	 * @dataProvider data_answers
	 *
	 * @param array<string, mixed>|string $body     Response body.
	 * @param int                         $code     HTTP status.
	 * @param string                      $expected License status.
	 */
	public function test_status_for_each_answer( $body, int $code, string $expected ): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, $body, $code );

		$this->assertSame( $expected, License_Status::get()['status'] );
	}

	/**
	 * A valid answer carries the plan and the query counts.
	 */
	public function test_valid_details(): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, self::valid_body( 250 ) );

		$status = License_Status::get();

		$this->assertSame(
			array(
				'id'   => 'ZEROSPAMBUSINESS',
				'name' => 'Business',
			),
			$status['plan']
		);
		$this->assertSame(
			array(
				'limit'     => 1000,
				'used'      => 750,
				'remaining' => 250,
			),
			$status['queries']
		);
		$this->assertNull( $status['reset_date'] );
	}

	/**
	 * The reset date comes from the Quota Reset Date setting when it is a valid date.
	 */
	public function test_reset_date_honours_the_setting(): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, self::valid_body() );

		$this->set_setting( 'quota_reset_date', '2026-11-15' );
		$this->assertSame( '2026-11-15', License_Status::get()['reset_date'] );

		$this->set_setting( 'quota_reset_date', '2026-02-31' );
		$this->assertNull( License_Status::get()['reset_date'] );
	}

	/**
	 * An answer is cached for 12 hours; later reads make no request.
	 */
	public function test_answer_is_cached_for_twelve_hours(): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, self::valid_body() );

		License_Status::get();
		License_Status::reset_request_cache();
		License_Status::get();

		$this->assertSame( 1, HttpMock::hits( self::ENDPOINT ) );
		$timeout = (int) get_option( '_transient_timeout_' . License_Status::TRANSIENT );
		$this->assertEqualsWithDelta( time() + 12 * HOUR_IN_SECONDS, $timeout, 5 );
	}

	/**
	 * "Unreachable" is cached for 15 minutes only.
	 */
	public function test_unreachable_is_cached_for_fifteen_minutes(): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, 'down', 503 );

		$this->assertSame( License_Status::UNREACHABLE, License_Status::get()['status'] );

		$timeout = (int) get_option( '_transient_timeout_' . License_Status::TRANSIENT );
		$this->assertEqualsWithDelta( time() + 15 * MINUTE_IN_SECONDS, $timeout, 5 );
	}

	/**
	 * Refreshing asks the API again; the status can move from unreachable to valid.
	 */
	public function test_refresh_asks_again(): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, 'down', 503 );
		$this->assertSame( License_Status::UNREACHABLE, License_Status::get()['status'] );

		HttpMock::reset();
		$this->mock_http( self::ENDPOINT, self::valid_body() );

		$this->assertSame( License_Status::UNREACHABLE, License_Status::get()['status'], 'Still cached.' );
		$this->assertSame( License_Status::VALID, License_Status::refresh()['status'] );
		$this->assertSame( 1, HttpMock::hits( self::ENDPOINT ) );
	}

	/**
	 * A new key is checked even while the old answer is cached.
	 */
	public function test_key_change_invalidates_the_cache(): void {
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, self::valid_body() );
		$this->assertSame( License_Status::VALID, License_Status::get()['status'] );

		HttpMock::reset();
		$this->mock_http(
			self::ENDPOINT,
			array(
				'status'   => 400,
				'response' => 'invalid_license',
			)
		);
		$this->set_setting( 'zerospam_license', 'other-key-1111' );

		$this->assertSame( License_Status::INVALID, License_Status::get()['status'] );
	}

	/**
	 * GET returns the masked key and the status, never the key.
	 */
	public function test_route_get(): void {
		$this->as_role( 'administrator' );
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http( self::ENDPOINT, self::valid_body() );

		$response = $this->rest( 'GET', self::ROUTE );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( License_Status::VALID, $data['status'] );
		$this->assertSame( "\u{2022}\u{2022}\u{2022}\u{2022}z9y8", $data['key']['hint'] );
		$this->assertStringNotContainsString( 'ABCD', (string) wp_json_encode( $data ) );
	}

	/**
	 * PUT checks the key and saves it; a rejected key is not saved.
	 */
	public function test_route_put(): void {
		$this->as_role( 'administrator' );
		$this->mock_http(
			self::ENDPOINT,
			array(
				'status'   => 400,
				'response' => 'invalid_license',
			)
		);

		$rejected = $this->rest( 'PUT', self::ROUTE, array( 'license_key' => 'other-key-1111' ) );

		$this->assertSame( 400, $rejected->get_status() );
		$this->assertSame( 'rest_invalid_param', $rejected->get_data()['code'] );
		$this->assertArrayHasKey( 'license_key', $rejected->get_data()['data']['params'] );
		$this->assertFalse( get_option( 'zero-spam-zerospam' ) );

		HttpMock::reset();
		$this->mock_http( self::ENDPOINT, self::valid_body() );

		$saved = $this->rest( 'PUT', self::ROUTE, array( 'license_key' => ' ' . self::KEY . ' ' ) );

		$this->assertSame( 200, $saved->get_status() );
		$this->assertSame( License_Status::VALID, $saved->get_data()['status'] );
		$this->assertSame( self::KEY, get_option( 'zero-spam-zerospam' )['zerospam_license'] );
	}

	/**
	 * A key that could not be checked is saved, as the settings page does.
	 */
	public function test_route_put_saves_when_unreachable(): void {
		$this->as_role( 'administrator' );
		$this->mock_http( self::ENDPOINT, 'down', 503 );

		$response = $this->rest( 'PUT', self::ROUTE, array( 'license_key' => self::KEY ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( License_Status::UNREACHABLE, $response->get_data()['status'] );
		$this->assertSame( self::KEY, get_option( 'zero-spam-zerospam' )['zerospam_license'] );
		$this->assertSame( 1, HttpMock::hits( self::ENDPOINT ), 'The answer of the check is reused, not asked again.' );
	}

	/**
	 * A key from wp-config.php cannot be removed over REST, and is not revealed.
	 */
	public function test_route_delete_refuses_a_constant_key(): void {
		$this->as_role( 'administrator' );
		$this->use_test_constant();
		$this->mock_http( self::ENDPOINT, self::valid_body() );

		$response = $this->rest( 'DELETE', self::ROUTE );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'rest_license_constant', $response->get_data()['code'] );
		$this->assertStringNotContainsString( 'CONSTANTKEY', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * DELETE removes the site's key.
	 */
	public function test_route_delete(): void {
		$this->as_role( 'administrator' );
		update_option(
			'zero-spam-zerospam',
			array(
				'zerospam'         => 'enabled',
				'zerospam_license' => self::KEY,
			)
		);

		$response = $this->rest( 'DELETE', self::ROUTE );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( License_Status::NONE, $response->get_data()['status'] );
		$this->assertSame( array( 'zerospam' => 'enabled' ), get_option( 'zero-spam-zerospam' ) );
	}

	/**
	 * Who may use the license route.
	 *
	 * @return array<string, array{0: string|null, 1: int}>
	 */
	public static function data_permissions(): array {
		return array(
			'anonymous'  => array( null, 401 ),
			'subscriber' => array( 'subscriber', 403 ),
			'editor'     => array( 'editor', 403 ),
		);
	}

	/**
	 * Only administrators can read, save or remove the key.
	 *
	 * @dataProvider data_permissions
	 *
	 * @param string|null $role     Role, or null for a logged-out visitor.
	 * @param int         $expected HTTP status.
	 */
	public function test_route_permissions( ?string $role, int $expected ): void {
		update_option( 'zero-spam-zerospam', array( 'zerospam_license' => self::KEY ) );

		if ( null === $role ) {
			wp_set_current_user( 0 );
		} else {
			$this->as_role( $role );
		}

		$this->assertSame( $expected, $this->rest( 'GET', self::ROUTE )->get_status() );
		$this->assertSame( $expected, $this->rest( 'PUT', self::ROUTE, array( 'license_key' => 'x-key' ) )->get_status() );
		$this->assertSame( $expected, $this->rest( 'DELETE', self::ROUTE )->get_status() );
		$this->assertSame( self::KEY, get_option( 'zero-spam-zerospam' )['zerospam_license'] );
		$this->assertSame( 0, HttpMock::hits( self::ENDPOINT ) );
	}
}
