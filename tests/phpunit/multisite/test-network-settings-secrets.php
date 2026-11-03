<?php
/**
 * Tests that the network-settings routes never return a secret, and stay bounded.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Secrets are write-only over REST: `GET network-settings/*` shows a network
 * secret as `{ set, hint }` and a site's as `{ set, source }`. `compare` and
 * `status` are paged and cached, with one registry build per request.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\Network_Settings_Controller
 * @covers \ZeroSpam\Includes\Network_Report
 */
class Test_Network_Settings_Secrets extends TestCase {

	/**
	 * Parts of the secret values that must never appear in an answer (a hint
	 * shows only each value's last four characters).
	 *
	 * @var string[]
	 */
	const MARKERS = array( 'NETSECRETONE', 'NETSECRETTWO', 'NETSECRETLOCKED', 'NETSECRETUNKNOWN', 'SITEBSECRETONE', 'SITEBSECRETTWO', 'MAINSECRETONE', 'TEMPLATESECRET' );

	/**
	 * Another site of the network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * Creates the second site with its plugin tables.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );
	}

	/**
	 * A super admin; secrets on the network, on the main site and on site B.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::cache_key() );
		Network_Report::flush();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );

		$this->set_network_setting( 'ipinfo_access_token', 'NETSECRETONE-wxyz' );
		$this->set_network_setting( 'alert_webhook_url', 'https://hooks.example.com/NETSECRETTWO/hook' );
		$this->set_network_setting( 'ipbase_api_key', 'NETSECRETLOCKED-lock', true );
		$this->set_network_setting( 'an_inactive_extensions_key', 'NETSECRETUNKNOWN-gone' );
		$this->set_network_setting( 'max_logs', 9000 );

		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-ipinfo', array( 'ipinfo_access_token' => 'SITEBSECRETONE-1234' ) );
		update_option( 'zero-spam-ipstack', array( 'ipstack_api' => 'SITEBSECRETTWO-5678' ) );
		restore_current_blog();

		update_option( 'zero-spam-zerospam', array( 'zerospam_license' => 'MAINSECRETONE-9999' ) );
		$this->set_network_setting( 'zerospam_license', 'NETSECRETONE-lice' );
		Network_Report::flush();
		self::reset_static_caches();
	}

	/**
	 * A 5.x custom template saved with a secret in it.
	 */
	private function legacy_template_with_a_secret(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			array(
				'network_id'    => (int) get_current_network_id(),
				'template_name' => 'Old template',
				'template_slug' => 'old-template',
				'template_type' => 'custom',
				'settings'      => (string) wp_json_encode(
					array(
						'ipinfo_access_token' => 'TEMPLATESECRET-0000',
						'max_logs'            => 100,
					)
				),
				'created_by'    => 1,
				'created_at'    => '2026-01-01 00:00:00',
				'updated_at'    => '2026-01-01 00:00:00',
			)
		);
	}

	/**
	 * Every GET of `network-settings/*` as a request that succeeds.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>}> Route pattern => request path and query.
	 */
	private function get_routes(): array {
		$base = '/zero-spam/v1/network-settings';

		return array(
			$base                                 => array( $base, array() ),
			$base . '/site/(?P<site_id>\d+)'      => array( $base . '/site/' . self::$site_b, array() ),
			$base . '/compare'                    => array( $base . '/compare', array() ),
			$base . '/status'                     => array( $base . '/status', array() ),
			$base . '/audit'                      => array( $base . '/audit', array( 'page' => 1 ) ),
			$base . '/export'                     => array( $base . '/export', array() ),
			$base . '/templates'                  => array( $base . '/templates', array() ),
			$base . '/templates/(?P<slug>[\w-]+)' => array( $base . '/templates/old-template', array() ),
			$base . '/(?P<key>[\w-]+)'            => array( $base . '/ipinfo_access_token', array() ),
		);
	}

	/**
	 * No GET of `network-settings/*` returns a secret; a new GET route must join this list.
	 */
	public function test_secret_sweep_over_every_get_route(): void {
		$this->legacy_template_with_a_secret();
		// A network change, so the audit log has entries for secrets too.
		$this->assertSame( 200, $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'ipinfo_access_token' => 'NETSECRETONE-newer' ), array( 'scope' => 'network' ) )->get_status() );
		$this->set_network_setting( 'ipinfo_access_token', 'NETSECRETONE-wxyz' );
		Network_Report::flush();

		$routes = $this->get_routes();
		$found  = array();
		foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
			if ( ! str_starts_with( $route, '/zero-spam/v1/network-settings' ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				if ( ! empty( $handler['methods']['GET'] ) ) {
					$found[] = $route;
					break;
				}
			}
		}
		$this->assertEqualsCanonicalizing( array_keys( $routes ), $found, 'A GET route of network-settings is not in this sweep.' );

		foreach ( $routes as $pattern => list( $path, $query ) ) {
			foreach ( array( $query, array( 'site_id' => self::$site_b ) + $query ) as $params ) {
				$response = $this->rest( 'GET', $path, array(), $params );
				$json     = (string) wp_json_encode( $response->get_data() );

				$this->assertSame( 200, $response->get_status(), $pattern );
				foreach ( self::MARKERS as $marker ) {
					$this->assertStringNotContainsString( $marker, $json, "{$pattern} returned {$marker}." );
				}
			}
		}

		// The sweep looks at real answers: the control value is there, and the main site's own key is in site/{id}.
		$this->assertStringContainsString( '9000', (string) wp_json_encode( $this->rest( 'GET', '/zero-spam/v1/network-settings' )->get_data() ) );
		foreach ( self::MARKERS as $marker ) {
			$this->assertStringNotContainsString( $marker, (string) wp_json_encode( $this->rest( 'GET', '/zero-spam/v1/network-settings/site/' . get_main_site_id() )->get_data() ), $marker );
		}
	}

	/**
	 * `GET network-settings`: `{ set, hint }` for secrets (and keys the registry does not know), values for the rest.
	 */
	public function test_settings_list_masks_secrets(): void {
		$data = $this->rest( 'GET', '/zero-spam/v1/network-settings' )->get_data();

		$this->assertSame(
			array(
				'set'  => true,
				'hint' => "\u{2022}\u{2022}\u{2022}\u{2022}wxyz",
			),
			$data['ipinfo_access_token']['value']
		);
		$this->assertTrue( $data['an_inactive_extensions_key']['value']['set'], 'A key the registry does not know could be an inactive extension\'s secret.' );
		$this->assertSame( 9000, $data['max_logs']['value'] );
		$this->assertTrue( $data['ipbase_api_key']['locked'] );
		$this->assertSame( 2, $data['max_logs']['total_sites'] );
		$this->assertSame( 2, $data['ipbase_api_key']['using_default'] );
		$this->assertSame( 1, $data['ipinfo_access_token']['overridden'] );
		$this->assertSame( 1, $data['ipinfo_access_token']['using_default'] );

		$this->set_network_setting( 'ipinfo_access_token', '' );
		$data = $this->rest( 'GET', '/zero-spam/v1/network-settings' )->get_data();
		$this->assertSame(
			array(
				'set'  => false,
				'hint' => null,
			),
			$data['ipinfo_access_token']['value']
		);
	}

	/**
	 * `GET network-settings/site/{id}`: `{ set, source }` for secrets, `{ value, source }` for the rest.
	 */
	public function test_site_settings_mask_secrets(): void {
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4000 ) );
		restore_current_blog();

		$data = $this->rest( 'GET', '/zero-spam/v1/network-settings/site/' . self::$site_b )->get_data();

		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'override',
			),
			$data['ipinfo_access_token']
		);
		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'locked',
			),
			$data['ipbase_api_key']
		);
		$this->assertSame(
			array(
				'value'  => 4000,
				'source' => 'override',
			),
			$data['max_logs']
		);

		$main = $this->rest( 'GET', '/zero-spam/v1/network-settings/site/' . get_main_site_id() )->get_data();
		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'default',
			),
			$main['ipinfo_access_token']
		);
		$this->assertSame(
			array(
				'value'  => 9000,
				'source' => 'default',
			),
			$main['max_logs']
		);
	}

	/**
	 * `GET network-settings/compare`: masked, at most 100 sites a page, with paging headers.
	 */
	public function test_compare_is_masked_and_paged(): void {
		$this->assertSame( 400, $this->rest( 'GET', '/zero-spam/v1/network-settings/compare', array(), array( 'per_page' => 101 ) )->get_status() );

		$response = $this->rest(
			'GET',
			'/zero-spam/v1/network-settings/compare',
			array(),
			array(
				'page'     => 2,
				'per_page' => 1,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '2', $response->get_headers()['X-WP-Total'] );
		$this->assertSame( array( self::$site_b ), array_column( $data['sites'], 'id' ) );

		$rows = (array) $data['comparison'];
		$row  = $rows['ipinfo_access_token'];
		$this->assertSame(
			array(
				'set'  => true,
				'hint' => "\u{2022}\u{2022}\u{2022}\u{2022}wxyz",
			),
			$row['network_value']
		);
		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'override',
			),
			( (array) $row['sites'] )[ self::$site_b ]
		);
		$this->assertCount( 1, (array) $row['sites'], 'Only the page\'s sites.' );
		$this->assertSame( 9000, $rows['max_logs']['network_value'] );
	}

	/**
	 * `compare` and `status` build the registry at most once per request, and
	 * `compare` reads a page only once until a setting changes.
	 */
	public function test_compare_and_status_are_cached_and_build_the_registry_once(): void {
		$builds  = 0;
		$counter = static function ( $settings ) use ( &$builds ) {
			++$builds;

			return $settings;
		};
		add_filter( 'zerospam_settings', $counter, 1000 );

		foreach ( array( 'compare', 'status' ) as $route ) {
			Network_Report::flush();
			self::reset_static_caches();
			$builds = 0;
			$this->assertSame( 200, $this->rest( 'GET', '/zero-spam/v1/network-settings/' . $route )->get_status() );
			$this->assertLessThanOrEqual( 1, $builds, "{$route} rebuilt the registry." );
		}

		remove_filter( 'zerospam_settings', $counter, 1000 );

		// Cached: no site's options are read again; a change of a site's settings is seen at once.
		$this->rest( 'GET', '/zero-spam/v1/network-settings/compare' );
		$queries = $this->capture_queries(
			function () {
				$this->rest( 'GET', '/zero-spam/v1/network-settings/compare' );
			}
		);
		$this->assertSame( array(), preg_grep( '/option_value FROM/', $queries ), 'The page was read again.' );

		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4000 ) );
		restore_current_blog();

		$row = ( (array) $this->rest( 'GET', '/zero-spam/v1/network-settings/compare' )->get_data()['comparison'] )['max_logs'];
		$this->assertSame(
			array(
				'value'  => 4000,
				'source' => 'override',
			),
			( (array) $row['sites'] )[ self::$site_b ]
		);
	}

	/**
	 * `GET network-settings/status` counts locks and site changes.
	 */
	public function test_status_counts(): void {
		$data = $this->rest( 'GET', '/zero-spam/v1/network-settings/status' )->get_data();

		$this->assertSame( 2, $data['total_sites'] );
		$this->assertSame( 2, $data['checked_sites'] );
		$this->assertFalse( $data['capped'] );
		$this->assertSame( 6, $data['total_settings'] );
		$this->assertSame( 1, $data['locked_settings'] );
		$this->assertSame( 0, $data['total_overrides'], 'Site B\'s token and the main site\'s license are secrets, which Apply keeps.' );
	}

	/**
	 * A cached compare page goes when a site's stored settings change: an
	 * option that already existed (updated_option) and one removed (deleted_option).
	 */
	public function test_compare_cache_follows_a_change_of_a_stored_option(): void {
		$cell = function (): array {
			$row = ( (array) $this->rest( 'GET', '/zero-spam/v1/network-settings/compare' )->get_data()['comparison'] )['max_logs'];

			return ( (array) $row['sites'] )[ self::$site_b ];
		};

		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4000 ) );
		restore_current_blog();
		$this->assertSame( 4000, $cell()['value'] );

		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4001 ) );
		restore_current_blog();
		$this->assertSame( 4001, $cell()['value'], 'updated_option' );

		switch_to_blog( self::$site_b );
		delete_option( 'zero-spam-settings' );
		restore_current_blog();
		$this->assertSame( 'default', $cell()['source'], 'deleted_option' );
	}
}
