<?php
/**
 * Tests for the migrations of the 6.0 admin cutover.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Notices;
use ZeroSpam\Core\Admin\Shared_IP_Warning;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The migrations v6_dismissals, v6_allowlist_follows_network and v6_remove_google_api,
 * each from 5.7.14-shaped data and each run twice (the second run changes
 * nothing).
 *
 * @covers \ZeroSpam\Includes\Migrations::move_dismissals
 * @covers \ZeroSpam\Includes\Migrations::allowlist_follows_network
 * @covers \ZeroSpam\Includes\Migrations::remove_google_api
 */
class Test_Migrations_Cutover extends TestCase {

	/**
	 * Network migration state starts fresh.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		delete_option( Notices::OPTION );
		delete_option( Shared_IP_Warning::DISMISSED_OPTION );
		delete_option( 'zero-spam-google' );
		delete_option( 'zero-spam-settings' );

		if ( is_multisite() ) {
			delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->base_prefix . DB::$tables['network_templates'] ) );
		}
	}

	/**
	 * Runs a migration twice; both runs must report success.
	 *
	 * @param string $method Migration method.
	 */
	private function run_twice( string $method ): void {
		$this->assertTrue( ( new Migrations() )->{$method}() );
		$this->assertTrue( ( new Migrations() )->{$method}() );
	}

	/**
	 * The shared-IP dismissal moves into the notices store; the 5.x option stays for a downgrade.
	 */
	public function test_dismissals_move_into_the_store(): void {
		update_option( Shared_IP_Warning::DISMISSED_OPTION, 'shared_ip:198.51.100.50', false );
		update_option( 'zerospam_api_monitoring_notice_dismissed', true );

		$this->run_twice( 'move_dismissals' );

		$this->assertSame( 'shared_ip:198.51.100.50', Notices::dismissed( 'shared-ip' ) );
		$this->assertSame( 'shared_ip:198.51.100.50', get_option( Shared_IP_Warning::DISMISSED_OPTION ), 'Kept for a downgrade.' );
		$this->assertTrue( (bool) get_option( 'zerospam_api_monitoring_notice_dismissed' ), 'Left for uninstall.' );
	}

	/**
	 * A dismissal already in the store wins; nothing stored, nothing moved.
	 */
	public function test_dismissals_keep_the_store(): void {
		$this->run_twice( 'move_dismissals' );
		$this->assertFalse( get_option( Notices::OPTION ) );

		Notices::remember( 'shared-ip', 'trusted_proxy' );
		update_option( Shared_IP_Warning::DISMISSED_OPTION, 'shared_ip:198.51.100.50', false );

		$this->run_twice( 'move_dismissals' );

		$this->assertSame( 'trusted_proxy', Notices::dismissed( 'shared-ip' ) );
	}

	/**
	 * A single site keeps whatever it stores.
	 *
	 * @group ms-excluded
	 */
	public function test_allowlist_single_site_untouched(): void {
		update_option(
			'zero-spam-settings',
			array(
				'ip_whitelist' => false,
				'max_logs'     => 100,
			)
		);

		$this->run_twice( 'allowlist_follows_network' );

		$this->assertSame(
			array(
				'ip_whitelist' => false,
				'max_logs'     => 100,
			),
			get_option( 'zero-spam-settings' )
		);
	}

	/**
	 * Stored values: whether the migration removes them.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function data_allowlist_values(): array {
		return array(
			'false'       => array( false, true ),
			'empty'       => array( '', true ),
			'white space' => array( "  \n ", true ),
			'null'        => array( null, true ),
			'one address' => array( '192.0.2.1', false ),
			'a list'      => array( "192.0.2.1\n198.51.100.2", false ),
		);
	}

	/**
	 * On a network, an empty stored list is removed (the site follows the
	 * network's list); a list with entries stays. Other settings stay.
	 *
	 * @group ms-required
	 * @dataProvider data_allowlist_values
	 *
	 * @param mixed $stored  Stored value.
	 * @param bool  $removed Whether it is removed.
	 */
	public function test_allowlist_on_a_network( $stored, bool $removed ): void {
		update_option(
			'zero-spam-settings',
			array(
				'ip_whitelist' => $stored,
				'max_logs'     => 100,
			)
		);
		$this->set_network_setting( 'ip_whitelist', '203.0.113.0' );

		$this->run_twice( 'allowlist_follows_network' );

		$options = get_option( 'zero-spam-settings' );
		$this->assertSame( 100, $options['max_logs'] );
		$this->assertSame( ! $removed, array_key_exists( 'ip_whitelist', $options ) );

		if ( $removed ) {
			self::reset_static_caches();
			delete_site_transient( Network_Settings::cache_key() );
			$this->assertSame( '203.0.113.0', \ZeroSpam\Core\Settings::get_settings( 'ip_whitelist' ), 'The site follows the network list.' );
		} else {
			$this->assertSame( $stored, $options['ip_whitelist'] );
		}
	}

	/**
	 * A network that locks the setting does not keep the empty site value
	 * (it would come back when the lock is lifted).
	 *
	 * @group ms-required
	 */
	public function test_allowlist_with_a_locked_network_value(): void {
		update_option( 'zero-spam-settings', array( 'ip_whitelist' => '' ) );
		$this->set_network_setting( 'ip_whitelist', '203.0.113.0', true );

		$this->run_twice( 'allowlist_follows_network' );

		$this->assertArrayNotHasKey( 'ip_whitelist', get_option( 'zero-spam-settings' ) );
		$this->assertFalse( ms_is_switched(), 'The switch is restored.' );
	}

	/**
	 * A subsite is migrated the same way as the main site.
	 *
	 * @group ms-required
	 */
	public function test_allowlist_on_a_subsite(): void {
		$site = self::factory()->blog->create();
		switch_to_blog( $site );
		update_option( 'zero-spam-settings', array( 'ip_whitelist' => false ) );

		$this->run_twice( 'allowlist_follows_network' );

		$this->assertSame( array(), get_option( 'zero-spam-settings' ) );
		restore_current_blog();
	}

	/**
	 * Without a stored key there is nothing to do.
	 *
	 * @group ms-required
	 */
	public function test_allowlist_without_a_stored_key(): void {
		$this->run_twice( 'allowlist_follows_network' );

		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * The site's Google Maps option goes; the 5.x backup keeps it.
	 */
	public function test_google_key_removed_from_the_site(): void {
		update_option( 'zero-spam-google', array( 'google_api' => 'AIza-legacy-key-1234' ) );
		update_option( Migrations::BACKUP_5X_OPTION, array( 'options' => array( 'zero-spam-google' => array( 'google_api' => 'AIza-legacy-key-1234' ) ) ), false );

		$this->run_twice( 'remove_google_api' );

		$this->assertFalse( get_option( 'zero-spam-google' ) );
		$this->assertSame( 'AIza-legacy-key-1234', get_option( Migrations::BACKUP_5X_OPTION )['options']['zero-spam-google']['google_api'] );
	}

	/**
	 * Once per network, the key leaves the network settings and custom templates; other keys stay.
	 *
	 * @group ms-required
	 */
	public function test_google_key_removed_from_the_network(): void {
		global $wpdb;

		update_site_option(
			Network_Settings::META_KEY,
			array(
				'settings' => array(
					'google_api' => array(
						'value'  => 'AIza-network-key',
						'locked' => true,
					),
					'max_logs'   => array(
						'value'  => 5000,
						'locked' => false,
					),
				),
			)
		);
		$table = $wpdb->base_prefix . DB::$tables['network_templates'];
		foreach ( array(
			'maps'  => array(
				'google_api' => 'AIza-template-key',
				'max_logs'   => 100,
			),
			'plain' => array( 'max_logs' => 200 ),
		) as $slug => $settings ) {
			$wpdb->insert(
				$table,
				array(
					'network_id'    => (int) get_current_network_id(),
					'template_name' => ucfirst( $slug ),
					'template_slug' => $slug,
					'template_type' => 'custom',
					'settings'      => wp_json_encode( $settings ),
					'description'   => '',
					'created_by'    => 1,
					'created_at'    => '2026-01-01 00:00:00',
					'updated_at'    => '2026-01-01 00:00:00',
				)
			);
		}

		$this->run_twice( 'remove_google_api' );

		$store = get_site_option( Network_Settings::META_KEY )['settings'];
		$this->assertSame( array( 'max_logs' ), array_keys( $store ) );
		$this->assertSame( array( 'max_logs' => 100 ), json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT settings FROM %i WHERE template_slug = %s', $table, 'maps' ) ), true ) );
		$this->assertSame( array( 'max_logs' => 200 ), json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT settings FROM %i WHERE template_slug = %s', $table, 'plain' ) ), true ) );
		$this->assertContains( 'v6_remove_google_api', get_site_option( Migrations::NETWORK_COMPLETED_OPTION ) );

		// Once per network: a key put back later is not removed again by this migration.
		$again                                    = get_site_option( Network_Settings::META_KEY );
		$again['settings']['google_api']['value'] = 'later';
		update_site_option( Network_Settings::META_KEY, $again );
		$this->assertTrue( ( new Migrations() )->remove_google_api() );
		$this->assertArrayHasKey( 'google_api', get_site_option( Network_Settings::META_KEY )['settings'] );
	}

	/**
	 * Uninstall still removes the old option.
	 */
	public function test_uninstall_still_lists_the_google_module(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own uninstall script.
		$this->assertStringContainsString( "'google',", (string) file_get_contents( ZEROSPAM_PATH . 'uninstall.php' ) );
	}
}
