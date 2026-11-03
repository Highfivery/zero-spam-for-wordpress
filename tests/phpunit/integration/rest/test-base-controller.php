<?php
/**
 * Tests for the shared REST controller base.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\Base_Controller;
use ZeroSpam\Tests\BaseControllerProbe;
use ZeroSpam\Tests\TestCase;

/**
 * Permission checks, error shapes, paging headers, `_fields` and the
 * per-request cache behave the same for every controller.
 *
 * @covers \ZeroSpam\Includes\Rest\Base_Controller
 */
class Test_Base_Controller extends TestCase {

	/**
	 * Controller under test.
	 *
	 * @var BaseControllerProbe
	 */
	private BaseControllerProbe $controller;

	/**
	 * Creates the controller.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->controller = new BaseControllerProbe();
	}

	/**
	 * Page sizes, requested and clamped.
	 *
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public static function data_per_page(): array {
		return array(
			'normal'       => array( 25, 25 ),
			'string'       => array( '50', 50 ),
			'maximum'      => array( 100, 100 ),
			'above max'    => array( 101, 100 ),
			'huge'         => array( PHP_INT_MAX, 100 ),
			'zero'         => array( 0, 1 ),
			'negative'     => array( -5, 1 ),
			'not a number' => array( 'all', 20 ),
			'missing'      => array( null, 20 ),
		);
	}

	/**
	 * The page size always lands between 1 and 100.
	 *
	 * @dataProvider data_per_page
	 *
	 * @param mixed $requested Requested page size.
	 * @param int   $expected  Page size used.
	 */
	public function test_per_page_is_clamped( $requested, int $expected ): void {
		$this->assertSame( $expected, $this->controller->call( 'clamp_per_page', array( $requested ) ) );
	}

	/**
	 * Paging headers use WordPress's names.
	 */
	public function test_pagination_headers(): void {
		$response = $this->controller->call( 'add_pagination_headers', array( new WP_REST_Response( array() ), 205, 20 ) );

		$this->assertSame( '205', $response->get_headers()['X-WP-Total'] );
		$this->assertSame( '11', $response->get_headers()['X-WP-TotalPages'] );
	}

	/**
	 * Invalid parameters use WordPress's error shape.
	 */
	public function test_invalid_params_error_shape(): void {
		$error = $this->controller->call( 'invalid_params_error', array( array( 'per_page' => 'Too big.' ) ) );

		$this->assertSame( 'rest_invalid_param', $error->get_error_code() );
		$this->assertSame(
			array(
				'status' => 400,
				'params' => array( 'per_page' => 'Too big.' ),
			),
			$error->get_error_data()
		);
	}

	/**
	 * Locked settings use the error the settings route has always returned.
	 */
	public function test_locked_error_shape(): void {
		$error = $this->controller->call( 'locked_error', array( array( 'max_logs' ) ) );

		$this->assertSame( 'rest_setting_locked', $error->get_error_code() );
		$this->assertSame(
			array(
				'status'      => 403,
				'locked_keys' => array( 'max_logs' ),
			),
			$error->get_error_data()
		);
	}

	/**
	 * Permission helpers per role; logged-out users get 401, others 403.
	 */
	public function test_permission_helpers(): void {
		$request = new WP_REST_Request( 'GET', '/zero-spam/v1/x' );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->controller->site_permissions_check( $request )->get_error_data()['status'] );
		$this->assertSame( 401, $this->controller->logged_in_permissions_check( $request )->get_error_data()['status'] );

		$this->as_role( 'subscriber' );
		$this->assertTrue( $this->controller->logged_in_permissions_check( $request ) );
		$this->assertSame( 403, $this->controller->site_permissions_check( $request )->get_error_data()['status'] );

		$this->as_role( 'administrator' );
		$this->assertTrue( $this->controller->site_permissions_check( $request ) );
	}

	/**
	 * Network routes are refused to site administrators, and on a single site.
	 */
	public function test_network_permission_needs_a_network_administrator(): void {
		$request = new WP_REST_Request( 'GET', '/zero-spam/v1/x' );
		$request->set_param( 'scope', 'network' );

		$this->as_role( 'administrator' );

		$this->assertWPError( $this->controller->network_permissions_check( $request ), 'A scope flag must not grant network access.' );
	}

	/**
	 * `_fields` keeps only the named fields.
	 */
	public function test_filter_fields(): void {
		$request = new WP_REST_Request( 'GET', '/zero-spam/v1/x' );
		$item    = array(
			'id'    => 1,
			'title' => 'x',
			'meta'  => array( 'a' => 1 ),
		);

		$this->assertSame( $item, $this->controller->call( 'filter_fields', array( $item, $request ) ) );

		$request->set_param( '_fields', 'id,meta.a' );
		$this->assertSame(
			array(
				'id'   => 1,
				'meta' => array( 'a' => 1 ),
			),
			$this->controller->call( 'filter_fields', array( $item, $request ) )
		);
	}

	/**
	 * The per-request cache computes a value once until it is reset.
	 */
	public function test_request_cache(): void {
		$calls    = 0;
		$producer = static function () use ( &$calls ): int {
			return ++$calls;
		};

		$this->assertSame( 1, BaseControllerProbe::cache( 'k', $producer ) );
		$this->assertSame( 1, BaseControllerProbe::cache( 'k', $producer ) );

		Base_Controller::reset_request_cache();

		$this->assertSame( 2, BaseControllerProbe::cache( 'k', $producer ) );
	}
}
