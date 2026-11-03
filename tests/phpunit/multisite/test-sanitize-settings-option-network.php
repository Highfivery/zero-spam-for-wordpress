<?php
/**
 * Tests for Settings_Schema::sanitize_settings_option() with the network writers.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Includes\Network_Templates;
use ZeroSpam\Tests\TestCase;

/**
 * "Apply to sites" and applying a template write a network
 * `blocked_email_domains` into the site's `zero-spam-settings` option; the
 * filter moves it into the site's own, non-autoloaded
 * `zerospam_blocked_email_domains` option, which is the one read. The 5.x
 * settings screen did this on admin requests only.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Settings_Schema::sanitize_settings_option
 */
class Test_Sanitize_Settings_Option_Network extends TestCase {

	/**
	 * The network's list.
	 *
	 * @var string
	 */
	const DOMAINS = "spam-network.example\nfake-network.example";

	/**
	 * A site of the network.
	 *
	 * @var int
	 */
	private static int $site = 0;

	/**
	 * Creates the site with its plugin tables.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site = self::create_site_with_tables( $factory );
	}

	/**
	 * A super admin, a network list of blocked email domains, nothing on the site.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::cache_key() );
		Network_Report::flush();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );

		$this->set_network_setting( 'blocked_email_domains', self::DOMAINS );

		switch_to_blog( self::$site );
		delete_option( 'zero-spam-settings' );
		delete_option( 'zerospam_blocked_email_domains' );
		restore_current_blog();
	}

	/**
	 * What the site stores after a network write.
	 *
	 * @return array{settings: mixed, domains: mixed, autoload: string|null}
	 */
	private function site_state(): array {
		global $wpdb;

		switch_to_blog( self::$site );
		wp_cache_flush();
		$state = array(
			'settings' => get_option( 'zero-spam-settings' ),
			'domains'  => get_option( 'zerospam_blocked_email_domains' ),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the autoload flag itself.
			'autoload' => $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'zerospam_blocked_email_domains' ) ),
		);
		restore_current_blog();

		return $state;
	}

	/**
	 * The domains are in their own option, not autoloaded, and not in the module option.
	 *
	 * @param array{settings: mixed, domains: mixed, autoload: string|null} $state Site state.
	 */
	private function assert_moved( array $state ): void {
		$this->assertSame( self::DOMAINS, $state['domains'] );
		$this->assertContains( $state['autoload'], array( 'no', 'off' ), 'The domains option is not autoloaded.' );
		$this->assertIsArray( $state['settings'] );
		$this->assertArrayNotHasKey( 'blocked_email_domains', $state['settings'] );
	}

	/**
	 * Apply to sites (5.x mode `all`, forced).
	 */
	public function test_apply_all_moves_the_domains_to_their_own_option(): void {
		$result = ( new Network_Settings_Manager() )->apply_to_sites( true, array( self::$site ), 'all' );
		$this->assertTrue( $result['success'] );

		$this->assert_moved( $this->site_state() );
	}

	/**
	 * Applying a custom template to the site.
	 */
	public function test_template_apply_moves_the_domains_to_their_own_option(): void {
		$templates = new Network_Templates();
		$this->assertNotFalse( $templates->save_current_as_template( 'Domains', 'e2e-domains', '' ) );

		$result = $templates->apply_to_sites( 'e2e-domains', array( self::$site ), true );
		$this->assertTrue( $result['success'] );

		$this->assert_moved( $this->site_state() );
	}

	/**
	 * Without the filter the domains land in the module option (proves the tests above test it).
	 */
	public function test_without_the_filter_the_domains_stay_in_the_module_option(): void {
		remove_filter( 'sanitize_option_zero-spam-settings', array( Settings_Schema::class, 'sanitize_settings_option' ), 5 );

		try {
			( new Network_Settings_Manager() )->apply_to_sites( true, array( self::$site ), 'all' );
		} finally {
			add_filter( 'sanitize_option_zero-spam-settings', array( Settings_Schema::class, 'sanitize_settings_option' ), 5, 1 );
		}

		$state = $this->site_state();
		$this->assertSame( self::DOMAINS, $state['settings']['blocked_email_domains'] ?? null );
		$this->assertFalse( $state['domains'] );
	}
}
