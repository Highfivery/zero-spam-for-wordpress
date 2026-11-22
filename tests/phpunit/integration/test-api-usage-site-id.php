<?php
/**
 * Tests the API usage routes' site ID on a single site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * On a single site there is one site: its own ID reads it, any other is not found.
 *
 * @group ms-excluded
 * @covers \ZeroSpam\Includes\Rest\Base_Controller::requested_site_id
 * @covers \ZeroSpam\Includes\Rest\API_Usage_Controller
 */
class Test_API_Usage_Site_Id extends TestCase {

	/**
	 * Monitoring on, an administrator logged in.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_setting( 'api_monitoring', 'enabled' );
		$this->as_role( 'administrator' );
	}

	/**
	 * The site's own ID reads it; another ID is a 404, never this site's data.
	 */
	public function test_only_the_sites_own_id_is_read(): void {
		foreach ( array( '/zero-spam/v1/api-usage', '/zero-spam/v1/api-usage/export' ) as $route ) {
			$this->assertSame( 200, $this->rest( 'GET', $route, array(), array( 'site_id' => 1 ) )->get_status(), $route );

			$other = $this->rest( 'GET', $route, array(), array( 'site_id' => 2 ) );

			$this->assertSame( 404, $other->get_status(), $route );
			$this->assertSame( 'rest_site_not_found', $other->get_data()['code'], $route );
		}
	}

	/**
	 * On a single site, scope=network is this site's usage and still checks the site ID.
	 */
	public function test_network_scope_on_a_single_site(): void {
		$this->assertSame(
			404,
			$this->rest(
				'GET',
				'/zero-spam/v1/api-usage',
				array(),
				array(
					'scope'   => 'network',
					'site_id' => 2,
				)
			)->get_status()
		);
		$this->assertSame( 200, $this->rest( 'GET', '/zero-spam/v1/api-usage', array(), array( 'scope' => 'network' ) )->get_status() );
	}
}
