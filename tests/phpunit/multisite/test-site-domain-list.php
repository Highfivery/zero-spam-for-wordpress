<?php
/**
 * Tests for a site's own blocked email domains list on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Tests\TestCase;

/**
 * Since 6.0.1 a site's own list (`zerospam_blocked_email_domains`) overrides
 * an unlocked network list (Ben, 2026-11-12). It is reported as the site's,
 * a copy identical to the network's follows the network, the network admin
 * compares lists without loading them, and Apply and reset clear it.
 *
 * @group ms-required
 *
 * @covers \ZeroSpam\Includes\Network_Settings::own_list
 * @covers \ZeroSpam\Includes\Network_Settings::is_using_default
 * @covers \ZeroSpam\Includes\Network_Settings::resolve_all_settings
 * @covers \ZeroSpam\Core\Settings_Schema::resolve
 * @covers \ZeroSpam\Includes\Network_Settings_Manager::apply_to_sites
 * @covers \ZeroSpam\Includes\Network_Settings_Manager::reset_site
 */
class Test_Site_Domain_List extends TestCase {

	/**
	 * A site of the network besides the main one.
	 *
	 * @var int
	 */
	private static int $site = 0;

	/**
	 * The option a list is kept in.
	 */
	private const OPTION = 'zerospam_blocked_email_domains';

	/**
	 * One more site.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site = self::create_site_with_tables( $factory );
	}

	/**
	 * An unlocked network list, a super admin.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( self::OPTION );
		$this->set_network_setting( 'blocked_email_domains', "network.example\nother.example" );
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
	}

	/**
	 * Stores a list on a site.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $value   List.
	 */
	private function store_on( int $site_id, string $value ): void {
		switch_to_blog( $site_id );
		update_option( self::OPTION, $value, false );
		restore_current_blog();
	}

	/**
	 * The schema reports a site's own list as the site's.
	 */
	public function test_schema_reports_the_site_list(): void {
		update_option( self::OPTION, 'site.example', false );
		self::reset_static_caches();

		$resolved = Settings_Schema::resolve( 'blocked_email_domains' );
		$this->assertSame( Settings_Schema::SOURCE_SITE, $resolved['source'] );
		$this->assertSame( 'site.example', $resolved['value'] );
	}

	/**
	 * A copy identical to the network's list follows the network, in the
	 * resolver and in the schema.
	 */
	public function test_identical_copy_follows_the_network(): void {
		update_option( self::OPTION, "network.example\nother.example", false );
		self::reset_static_caches();

		$this->assertSame( Settings_Schema::SOURCE_NETWORK, Settings_Schema::resolve( 'blocked_email_domains' )['source'] );
		$this->assertTrue( ( new Network_Settings() )->is_using_default( 'blocked_email_domains' ) );
	}

	/**
	 * The network admin tells a site with its own list from one without, and
	 * never loads a site's list to do so.
	 */
	public function test_status_compares_lists_without_loading_them(): void {
		$this->store_on( self::$site, 'site.example' );

		$reads = 0;
		$count = function ( $value ) use ( &$reads ) {
			++$reads;
			return $value;
		};
		add_filter( 'option_' . self::OPTION, $count );
		try {
			$network = new Network_Settings();
			$this->assertFalse( $network->is_using_default( 'blocked_email_domains', self::$site ) );
			$this->assertTrue( $network->is_using_default( 'blocked_email_domains', get_main_site_id() ) );
		} finally {
			remove_filter( 'option_' . self::OPTION, $count );
		}

		$this->assertSame( 0, $reads, 'A site\'s list was loaded to compare it.' );
	}

	/**
	 * A whitespace-only stored list follows the network.
	 */
	public function test_whitespace_list_follows_the_network(): void {
		update_option( self::OPTION, " \n ", false );
		self::reset_static_caches();

		$this->assertNull( Network_Settings::own_list( 'blocked_email_domains' ) );
		$this->assertTrue( ( new Network_Settings() )->is_using_default( 'blocked_email_domains' ) );
		$this->assertSame( "network.example\nother.example", Settings::get_settings( 'blocked_email_domains' ) );
	}

	/**
	 * "Sites follow the network again" removes a site's own list.
	 */
	public function test_follow_removes_the_site_list(): void {
		$this->store_on( self::$site, 'site.example' );

		( new Network_Settings_Manager() )->apply_to_sites( false, array( self::$site ), 'follow' );

		switch_to_blog( self::$site );
		$this->assertFalse( get_option( self::OPTION ) );
		restore_current_blog();
	}

	/**
	 * Apply "all" never copies the network's list into a site (a copy would
	 * stop later network changes from reaching it); it removes the site's own.
	 */
	public function test_apply_all_does_not_copy_the_list(): void {
		( new Network_Settings_Manager() )->apply_to_sites( true, array( self::$site ), 'all' );

		switch_to_blog( self::$site );
		$this->assertFalse( get_option( self::OPTION ) );
		$stored = get_option( 'zero-spam-settings' );
		$this->assertFalse( is_array( $stored ) && array_key_exists( 'blocked_email_domains', $stored ) );
		restore_current_blog();
	}

	/**
	 * Apply "defaults only" leaves a site's own list alone.
	 */
	public function test_defaults_only_keeps_the_site_list(): void {
		$this->store_on( self::$site, 'site.example' );

		( new Network_Settings_Manager() )->apply_to_sites( false, array( self::$site ), 'defaults_only' );

		switch_to_blog( self::$site );
		$this->assertSame( 'site.example', get_option( self::OPTION ) );
		restore_current_blog();
	}

	/**
	 * Resetting a site to the network defaults removes its own list.
	 */
	public function test_reset_removes_the_site_list(): void {
		$this->store_on( self::$site, 'site.example' );

		( new Network_Settings_Manager() )->reset_site( self::$site );

		switch_to_blog( self::$site );
		$this->assertFalse( get_option( self::OPTION ) );
		restore_current_blog();
	}
}
