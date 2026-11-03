<?php
/**
 * Tests for the network defects AGENTS.md §8 listed that 6.0 fixes.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\License_Status;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Includes\Network_Stats_Tracker;
use ZeroSpam\Tests\TestCase;

/**
 * Network Stats calls
 * a site "Enhanced" only when its license is valid; locking a secret the
 * network does not set never copies the main site's key, and locking any
 * other uses the plugin default (undoing it removes that value); unlocking
 * a setting the network does not set creates nothing.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Stats_Tracker::get_site_breakdown
 * @covers \ZeroSpam\Core\License_Status::site_level
 * @covers \ZeroSpam\Includes\Network_Settings_Manager::lock_setting
 * @covers \ZeroSpam\Includes\Network_Settings_Manager::unlock_setting
 */
class Test_Network_Legacy_Fixes extends TestCase {

	/**
	 * Empty network store, no license answer; a super admin is logged in.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::cache_key() );
		delete_option( 'zero-spam-zerospam' );
		delete_option( 'zero-spam-ipinfo' );
		delete_transient( License_Status::TRANSIENT );
		delete_transient( 'zerospam_site_breakdown_month_10_spam_count' );

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
	}

	/**
	 * "Enhanced" only with a license the last check found valid.
	 */
	public function test_site_breakdown_enhanced_needs_a_valid_license(): void {
		update_option(
			'zero-spam-zerospam',
			array(
				'zerospam'         => 'enabled',
				'zerospam_license' => 'not-a-real-key',
			)
		);

		$site = static function (): array {
			delete_transient( 'zerospam_site_breakdown_month_10_spam_count' );
			$rows = array_column( Network_Stats_Tracker::get_site_breakdown(), null, 'site_id' );

			return $rows[ get_main_site_id() ];
		};

		$this->assertFalse( $site()['has_enhanced'], 'On, with a key nobody checked: not Enhanced.' );

		set_transient(
			License_Status::TRANSIENT,
			array(
				'hash'   => md5( 'not-a-real-key' ),
				'answer' => array( 'status' => License_Status::INVALID ),
			)
		);
		$this->assertFalse( $site()['has_enhanced'], 'An invalid key is not Enhanced.' );

		set_transient(
			License_Status::TRANSIENT,
			array(
				'hash'   => md5( 'not-a-real-key' ),
				'answer' => array( 'status' => License_Status::VALID ),
			)
		);
		$this->assertTrue( $site()['has_enhanced'] );

		// A cached answer for another key does not count.
		update_option(
			'zero-spam-zerospam',
			array(
				'zerospam'         => 'enabled',
				'zerospam_license' => 'another-key',
			)
		);
		$this->assertFalse( $site()['has_enhanced'] );
	}

	/**
	 * Locking a secret the network does not set locks it empty.
	 */
	public function test_locking_a_secret_never_copies_the_main_sites_key(): void {
		update_option( 'zero-spam-ipinfo', array( 'ipinfo_access_token' => 'main-site-secret-1234' ) );
		self::reset_static_caches();

		$this->assertTrue( ( new Network_Settings_Manager() )->lock_setting( 'ipinfo_access_token' ) );

		$store = get_site_option( Network_Settings::META_KEY );
		$this->assertSame( '', $store['settings']['ipinfo_access_token']['value'] );
		$this->assertTrue( $store['settings']['ipinfo_access_token']['locked'] );
		$this->assertStringNotContainsString( 'main-site-secret', (string) wp_json_encode( $store ) );
	}

	/**
	 * Locking a setting the network does not set uses the plugin default, never a site's value.
	 */
	public function test_locking_an_unset_key_uses_the_plugin_default(): void {
		update_option( 'zero-spam-settings', array( 'max_logs' => 4321 ) );
		self::reset_static_caches();

		$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/network-settings/max_logs/lock' )->get_status() );

		$store = get_site_option( Network_Settings::META_KEY );
		$this->assertSame( 10000, $store['settings']['max_logs']['value'] );
		$this->assertTrue( $store['settings']['max_logs']['locked'] );
	}

	/**
	 * Undo of such a lock (unlock) removes the value the lock created: the network sets nothing again.
	 */
	public function test_unlocking_after_such_a_lock_removes_the_created_value(): void {
		update_option( 'zero-spam-settings', array( 'max_logs' => 4321 ) );
		$manager = new Network_Settings_Manager();
		$this->assertTrue( $manager->lock_setting( 'max_logs' ) );
		$this->assertTrue( $manager->unlock_setting( 'max_logs' ) );

		$store = get_site_option( Network_Settings::META_KEY, array() );
		$this->assertArrayNotHasKey( 'max_logs', $store['settings'] ?? array() );
		$this->assertSame( array( 'max_logs' => 4321 ), get_option( 'zero-spam-settings' ), 'The site keeps its own value.' );
	}

	/**
	 * A value the network chose stays when its lock is undone, also when it was saved after the lock.
	 *
	 * @dataProvider data_network_writers
	 *
	 * @param string $writer How the value is saved.
	 */
	public function test_unlocking_keeps_a_value_the_network_chose( string $writer ): void {
		$manager = new Network_Settings_Manager();

		if ( 'before' === $writer ) {
			$this->set_network_setting( 'max_logs', 7777 );
		}

		$this->assertTrue( $manager->lock_setting( 'max_logs' ) );

		switch ( $writer ) {
			case 'rest':
				$this->assertSame( 200, $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'max_logs' => 7777 ), array( 'scope' => 'network' ) )->get_status() );
				break;
			case 'manager':
				$this->assertTrue( $manager->set_setting( 'max_logs', 7777, true ) );
				break;
			case 'template':
				$this->assertSame(
					201,
					$this->rest(
						'POST',
						'/zero-spam/v1/network-settings/templates',
						array(
							'name'     => 'Seven',
							'slug'     => 'seven',
							'settings' => array( 'max_logs' => 7777 ),
						)
					)->get_status()
				);
				$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/network-settings/templates/seven/apply' )->get_status() );
				break;
		}

		$this->assertTrue( $manager->unlock_setting( 'max_logs' ) );

		$store = get_site_option( Network_Settings::META_KEY );
		$this->assertSame( 7777, $store['settings']['max_logs']['value'] );
		$this->assertFalse( $store['settings']['max_logs']['locked'] );
	}

	/**
	 * The ways a network value is saved.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_network_writers(): array {
		return array(
			'before the lock'              => array( 'before' ),
			'PATCH settings?scope=network' => array( 'rest' ),
			'set_setting()'                => array( 'manager' ),
			'a template'                   => array( 'template' ),
		);
	}

	/**
	 * Unlocking a setting the network does not set creates no network value.
	 */
	public function test_unlocking_an_unset_key_creates_nothing(): void {
		$this->assertTrue( ( new Network_Settings_Manager() )->unlock_setting( 'max_logs' ) );

		$store = get_site_option( Network_Settings::META_KEY, array() );
		$this->assertArrayNotHasKey( 'max_logs', $store['settings'] ?? array() );
	}
}
