<?php
/**
 * Tests for the network defects AGENTS.md §8 listed that 6.0 fixes.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\License_Status;
use ZeroSpam\Includes\Admin\Site_Admin_Overrides;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Includes\Network_Stats_Tracker;
use ZeroSpam\Tests\TestCase;

/**
 * An empty network default reads "(none)" on a subsite; Network Stats calls
 * a site "Enhanced" only when its license is valid; locking a secret the
 * network does not set never copies the main site's key; unlocking a
 * setting the network does not set creates nothing.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Admin\Site_Admin_Overrides::render_override_notice
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
		delete_site_transient( Network_Settings::CACHE_KEY );
		delete_option( 'zero-spam-zerospam' );
		delete_option( 'zero-spam-ipinfo' );
		delete_transient( License_Status::TRANSIENT );
		delete_transient( 'zerospam_site_breakdown_month_10_spam_count' );

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
	}

	/**
	 * An empty network value is shown as "(none)", not an empty box.
	 */
	public function test_empty_network_default_reads_none(): void {
		$this->set_network_setting( 'comment_spam_message', '' );

		ob_start();
		( new Site_Admin_Overrides() )->render_override_notice( 'comment_spam_message' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<code>(none)</code>', $html );
		$this->assertStringNotContainsString( '<code></code>', $html );
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
	 * Unlocking a setting the network does not set creates no network value.
	 */
	public function test_unlocking_an_unset_key_creates_nothing(): void {
		$this->assertTrue( ( new Network_Settings_Manager() )->unlock_setting( 'max_logs' ) );

		$store = get_site_option( Network_Settings::META_KEY, array() );
		$this->assertArrayNotHasKey( 'max_logs', $store['settings'] ?? array() );
	}
}
