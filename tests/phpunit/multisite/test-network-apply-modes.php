<?php
/**
 * Tests for "Apply to all sites" (`network-settings/apply-all`).
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * `follow` removes a site's own values of unlocked settings the network sets,
 * never its keys; the 5.x modes copy values as they always did.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Settings_Manager::apply_to_sites
 */
class Test_Network_Apply_Modes extends TestCase {

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
	 * A super admin, network values and site B's own values.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );
		Network_Report::flush();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );

		// Unlocked with a site override, unlocked with none, locked, and a secret.
		$this->set_network_setting( 'max_logs', 9000 );
		$this->set_network_setting( 'blocked_message', 'Network message' );
		$this->set_network_setting( 'block_handler', 'redirect', true );
		$this->set_network_setting( 'ipinfo_access_token', 'network-token-1234' );

		switch_to_blog( self::$site_b );
		update_option(
			'zero-spam-settings',
			array(
				'max_logs'      => 4000,
				'block_handler' => '403',
				'ip_whitelist'  => '203.0.113.9',
			)
		);
		update_option( 'zero-spam-ipinfo', array( 'ipinfo_access_token' => 'site-own-token-5678' ) );
		restore_current_blog();
	}

	/**
	 * Runs apply-all on site B and returns its stored settings.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>} `zero-spam-settings` and `zero-spam-ipinfo`.
	 */
	private function apply( array $body ): array {
		$response = $this->rest( 'POST', '/zero-spam/v1/network-settings/apply-all', $body + array( 'sites' => array( self::$site_b ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		switch_to_blog( self::$site_b );
		$settings = get_option( 'zero-spam-settings', array() );
		$ipinfo   = get_option( 'zero-spam-ipinfo', array() );
		restore_current_blog();

		return array( $settings, $ipinfo );
	}

	/**
	 * Follow: overrides of unlocked settings go; locked ones, ones the network does not set, and secrets stay.
	 */
	public function test_follow(): void {
		list( $settings, $ipinfo ) = $this->apply( array( 'mode' => 'follow' ) );

		$this->assertArrayNotHasKey( 'max_logs', $settings, 'An unlocked override is removed.' );
		$this->assertSame( '403', $settings['block_handler'], 'A locked setting is not touched.' );
		$this->assertSame( '203.0.113.9', $settings['ip_whitelist'], 'A setting the network does not set is kept.' );
		$this->assertArrayNotHasKey( 'blocked_message', $settings, 'Nothing is copied to the site.' );
		$this->assertSame( 'site-own-token-5678', $ipinfo['ipinfo_access_token'], 'A site keeps its own secret.' );
	}

	/**
	 * Follow on a site that only differs by a secret changes nothing.
	 */
	public function test_follow_with_only_a_secret_left_updates_no_site(): void {
		$this->apply( array( 'mode' => 'follow' ) );

		$response = $this->rest(
			'POST',
			'/zero-spam/v1/network-settings/apply-all',
			array(
				'mode'  => 'follow',
				'sites' => array( self::$site_b ),
			)
		);

		$this->assertSame( 0, $response->get_data()['updated_count'] );
	}

	/**
	 * All (the default): copies locked values and values the site does not store; keeps its overrides.
	 */
	public function test_all_keeps_overrides(): void {
		list( $settings, $ipinfo ) = $this->apply( array() );

		$this->assertSame( 4000, $settings['max_logs'] );
		$this->assertSame( 'Network message', $settings['blocked_message'] );
		$this->assertSame( 'redirect', $settings['block_handler'] );
		$this->assertSame( 'site-own-token-5678', $ipinfo['ipinfo_access_token'] );
	}

	/**
	 * All with force: copies every network value.
	 */
	public function test_all_with_force_replaces_overrides(): void {
		list( $settings, $ipinfo ) = $this->apply( array( 'force' => true ) );

		$this->assertSame( 9000, $settings['max_logs'] );
		$this->assertSame( 'Network message', $settings['blocked_message'] );
		$this->assertSame( 'redirect', $settings['block_handler'] );
		$this->assertSame( '203.0.113.9', $settings['ip_whitelist'] );
		$this->assertSame( 'network-token-1234', $ipinfo['ipinfo_access_token'] );
	}

	/**
	 * Locked only: writes locked values alone.
	 */
	public function test_locked_only(): void {
		list( $settings, $ipinfo ) = $this->apply( array( 'mode' => 'locked_only' ) );

		$this->assertSame( 'redirect', $settings['block_handler'] );
		$this->assertSame( 4000, $settings['max_logs'] );
		$this->assertArrayNotHasKey( 'blocked_message', $settings );
		$this->assertSame( 'site-own-token-5678', $ipinfo['ipinfo_access_token'] );
	}

	/**
	 * Defaults only: writes what the site does not store (a stored null counts as not stored, as in 5.x).
	 */
	public function test_defaults_only(): void {
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array_merge( get_option( 'zero-spam-settings' ), array( 'blocked_message' => null ) ) );
		restore_current_blog();

		list( $settings, $ipinfo ) = $this->apply( array( 'mode' => 'defaults_only' ) );

		$this->assertSame( 'Network message', $settings['blocked_message'] );
		$this->assertSame( 4000, $settings['max_logs'] );
		$this->assertSame( '403', $settings['block_handler'], 'It is stored, even though it is locked.' );
		$this->assertSame( 'site-own-token-5678', $ipinfo['ipinfo_access_token'] );
	}
}
