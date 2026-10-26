<?php
/**
 * Tests for network templates.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Templates;
use ZeroSpam\Tests\TestCase;

/**
 * Built-in templates use real setting keys; custom templates are checked,
 * created and changed through REST; applying one writes the network store
 * once (keeping locks) or the chosen sites (never a locked setting), with
 * one audit entry.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Templates
 * @covers \ZeroSpam\Includes\Rest\Network_Settings_Controller
 */
class Test_Network_Templates extends TestCase {

	/**
	 * Keys of integrations whose plugin is not active in the tests.
	 */
	const INTEGRATION_KEYS = array( 'verify_contactform7', 'verify_wpforms', 'verify_gravityforms', 'verify_fluentforms', 'verify_woocommerce_checkout' );

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
	 * Empty network store and templates; a super admin is logged in.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->base_prefix . DB::$tables['network_templates'] ) );
		switch_to_blog( self::$site_b );
		delete_option( 'zero-spam-settings' );
		restore_current_blog();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
	}

	/**
	 * Template audit entries of a slug.
	 *
	 * @param string $slug Template slug.
	 */
	private function applications( string $slug ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE action_type = 'template' AND old_value = %s", $wpdb->base_prefix . DB::$tables['network_audit'], $slug ) );
	}

	/**
	 * Every built-in key is a real setting (or an integration's), none is an orphan.
	 */
	public function test_built_in_templates_use_real_keys(): void {
		foreach ( Network_Templates::built_in_settings() as $slug => $settings ) {
			foreach ( array_keys( $settings ) as $key ) {
				$this->assertNotContains( $key, Migrations::TEMPLATE_ORPHAN_KEYS, "{$slug}: {$key}" );
				$this->assertTrue( null !== Settings_Schema::setting( $key ) || in_array( $key, self::INTEGRATION_KEYS, true ), "{$slug}: {$key} is not a setting." );
			}

			$checked = Network_Templates::check_settings( $settings );
			$this->assertSame( array(), $checked['errors'], "{$slug} does not pass its own check." );
		}
	}

	/**
	 * A template's settings are checked before it is created.
	 */
	public function test_create_checks_settings(): void {
		$create = fn ( array $settings, string $slug = 'clinic-sites' ) => $this->rest(
			'POST',
			'/zero-spam/v1/network-settings/templates',
			array(
				'name'     => 'Clinic sites',
				'slug'     => $slug,
				'settings' => $settings,
			)
		);

		foreach ( array(
			'secret'  => array( 'ipinfo_access_token' => 'abc' ),
			'unknown' => array( 'not_a_setting' => 'enabled' ),
			'invalid' => array( 'block_handler' => 'nonsense' ),
		) as $case => $settings ) {
			$response = $create( $settings );
			$this->assertSame( 400, $response->get_status(), $case );
			$this->assertArrayHasKey( 'settings', $response->get_data()['data']['params'], $case );
		}

		$this->assertSame( 400, $create( array( 'max_logs' => 5000 ), 'Bad Slug' )->get_status() );

		$created = $create(
			array(
				'max_logs'        => 5000,
				'verify_comments' => 'enabled',
			)
		);
		$this->assertSame( 201, $created->get_status() );
		$this->assertSame(
			array(
				'max_logs'        => 5000,
				'verify_comments' => 'enabled',
			),
			$created->get_data()['template']['settings']
		);
		$this->assertSame( 400, $create( array( 'max_logs' => 1 ) )->get_status(), 'The slug is taken.' );
	}

	/**
	 * A custom template can be changed; a built-in one cannot; an unknown one is 404.
	 */
	public function test_edit(): void {
		( new Network_Templates() )->create_template( 'Mine', 'mine', array( 'max_logs' => 5000 ), '' );

		$edited = $this->rest(
			'PUT',
			'/zero-spam/v1/network-settings/templates/mine',
			array(
				'name'     => 'Mine, changed',
				'settings' => array( 'max_logs' => 6000 ),
			)
		);
		$this->assertSame( 200, $edited->get_status() );
		$this->assertSame( 'Mine, changed', $edited->get_data()['name'] );
		$this->assertSame( array( 'max_logs' => 6000 ), $edited->get_data()['settings'] );

		$built_in = $this->rest( 'PUT', '/zero-spam/v1/network-settings/templates/balanced', array( 'name' => 'X' ) );
		$this->assertSame( 403, $built_in->get_status() );
		$this->assertSame( 'zerospam_template_built_in', $built_in->get_data()['code'] );

		$this->assertSame( 404, $this->rest( 'PUT', '/zero-spam/v1/network-settings/templates/nope', array( 'name' => 'X' ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'PUT', '/zero-spam/v1/network-settings/templates/mine', array( 'settings' => array( 'google_api' => 'x' ) ) )->get_status() );
	}

	/**
	 * Applying to the network writes the store once and keeps each lock.
	 */
	public function test_apply_to_network_writes_once(): void {
		$this->set_network_setting( 'zerospam_confidence_min', 10, true );
		$writes  = 0;
		$counter = static function () use ( &$writes ) {
			++$writes;
		};
		add_action( 'update_site_option_' . Network_Settings::META_KEY, $counter );

		$response = $this->rest( 'POST', '/zero-spam/v1/network-settings/templates/balanced/apply', array( 'scope' => 'network' ) );

		remove_action( 'update_site_option_' . Network_Settings::META_KEY, $counter );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $writes );

		$store = get_site_option( Network_Settings::META_KEY )['settings'];
		$this->assertSame( 50, $store['zerospam_confidence_min']['value'] );
		$this->assertTrue( $store['zerospam_confidence_min']['locked'] );
		$this->assertFalse( $store['verify_comments']['locked'] );
		$this->assertSame( 1, $this->applications( 'balanced' ) );
	}

	/**
	 * Applying to sites keeps a site's own value unless forced, and never writes a locked setting.
	 */
	public function test_apply_to_sites(): void {
		( new Network_Templates() )->create_template(
			'Mine',
			'mine',
			array(
				'max_logs'        => 5000,
				'log_blocked_ips' => 'enabled',
			),
			''
		);
		$this->set_network_setting( 'log_blocked_ips', false, true );
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4000 ) );
		restore_current_blog();

		$apply  = fn ( bool $force ) => $this->rest(
			'POST',
			'/zero-spam/v1/network-settings/templates/mine/apply',
			array(
				'scope' => 'sites',
				'sites' => array( self::$site_b ),
				'force' => $force,
			)
		);
		$stored = static function (): array {
			switch_to_blog( self::$site_b );
			$option = get_option( 'zero-spam-settings', array() );
			restore_current_blog();

			return $option;
		};

		$this->assertSame( 200, $apply( false )->get_status() );
		$this->assertSame( 4000, $stored()['max_logs'], 'The site keeps its own value.' );
		$this->assertArrayNotHasKey( 'log_blocked_ips', $stored(), 'A locked setting was written to the site.' );

		$forced = $apply( true )->get_data();
		$this->assertSame( array( self::$site_b ), $forced['updated_sites'] );
		$this->assertSame( 5000, $stored()['max_logs'] );
		$this->assertArrayNotHasKey( 'log_blocked_ips', $stored() );

		$this->assertSame( 2, $this->applications( 'mine' ), 'One audit entry per apply.' );
	}
}
