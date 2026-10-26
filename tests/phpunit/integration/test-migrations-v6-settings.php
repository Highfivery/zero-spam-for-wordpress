<?php
/**
 * Tests for the 6.0 migrations of the settings that start to take effect.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The v6_uninstall_flag migration turns "Delete Data on Uninstall" on (5.x always
 * deleted everything); v6_thresholds_sanitize keeps the five API alert
 * thresholds within their ranges. Both can run again without changing
 * anything.
 *
 * @covers \ZeroSpam\Includes\Migrations::uninstall_flag
 * @covers \ZeroSpam\Includes\Migrations::sanitize_thresholds
 */
class Test_Migrations_V6_Settings extends TestCase {

	/**
	 * Starts each test without the API monitoring option or the network guards.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( 'zero-spam-api-monitoring' );
		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );
	}

	/**
	 * The stored "Delete Data on Uninstall" value, or null when none is stored.
	 *
	 * @return mixed
	 */
	private function stored_flag() {
		$options = get_option( 'zero-spam-api-monitoring' );

		return is_array( $options ) && array_key_exists( 'delete_data_on_uninstall', $options ) ? $options['delete_data_on_uninstall'] : null;
	}

	/**
	 * Both migrations run after the 6.0 settings migrations, in order.
	 */
	public function test_registered_in_order(): void {
		$method = new ReflectionMethod( Migrations::class, 'get_migrations' );
		$keys   = array_keys( $method->invoke( new Migrations() ) );

		$this->assertGreaterThan( array_search( 'v6_network_store', $keys, true ), array_search( 'v6_uninstall_flag', $keys, true ) );
		$this->assertSame( array_search( 'v6_uninstall_flag', $keys, true ) + 1, array_search( 'v6_thresholds_sanitize', $keys, true ) );
	}

	/**
	 * On a single site every 5.x value that is not `enabled` becomes
	 * `enabled`, other API monitoring settings are kept, and a second run
	 * changes nothing.
	 *
	 * @dataProvider provide_five_x_values
	 * @group ms-excluded
	 *
	 * @param array<string, mixed>|null $stored 5.x option (null: none).
	 */
	public function test_uninstall_flag_turns_it_on( ?array $stored ): void {
		if ( null !== $stored ) {
			update_option( 'zero-spam-api-monitoring', $stored );
		}

		$migrations = new Migrations();
		$this->assertTrue( $migrations->uninstall_flag() );
		$this->assertSame( 'enabled', $this->stored_flag() );

		$after = get_option( 'zero-spam-api-monitoring' );
		if ( null !== $stored && isset( $stored['api_retention'] ) ) {
			$this->assertSame( $stored['api_retention'], $after['api_retention'], 'Other settings are kept.' );
		}

		$this->assertTrue( $migrations->uninstall_flag() );
		$this->assertSame( $after, get_option( 'zero-spam-api-monitoring' ), 'A second run changes nothing.' );
	}

	/**
	 * 5.x values of the API monitoring option.
	 *
	 * @return array<string, array{0: array<string, mixed>|null}>
	 */
	public static function provide_five_x_values(): array {
		return array(
			'no option'            => array( null ),
			'option without it'    => array( array( 'api_retention' => 30 ) ),
			'unticked box (false)' => array(
				array(
					'api_retention'            => 30,
					'delete_data_on_uninstall' => false,
				),
			),
			'empty string'         => array( array( 'delete_data_on_uninstall' => '' ) ),
			'already on'           => array( array( 'delete_data_on_uninstall' => 'enabled' ) ),
		);
	}

	/**
	 * An explicit 5.x "off" (false) becomes on once; a site that turns it
	 * off after the upgrade keeps it off, because the migration never runs
	 * again.
	 */
	public function test_off_saved_after_the_migration_is_kept(): void {
		update_option( 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => false ) );
		delete_option( Migrations::COMPLETED_OPTION );

		$this->as_role( 'administrator' );
		( new Migrations() )->run();
		$this->assertSame( 'enabled', $this->stored_flag(), 'The 5.x false is turned on.' );

		$response = $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'delete_data_on_uninstall' => false ) );
		$this->assertSame( 200, $response->get_status() );
		$off = $this->stored_flag();
		$this->assertNotSame( 'enabled', $off );

		( new Migrations() )->run();
		$this->assertSame( $off, $this->stored_flag(), 'A later run leaves the choice alone.' );
		$this->assertFalse( \ZeroSpam\Core\Settings::get_settings( 'delete_data_on_uninstall' ) );
	}

	/**
	 * The 6.0 admin shows it with the data settings; its option is unchanged.
	 */
	public function test_shown_with_the_data_settings(): void {
		$entry = \ZeroSpam\Core\Settings_Schema::setting( 'delete_data_on_uninstall' );

		$this->assertSame( array( 'settings', 'data', 'zero-spam-api-monitoring' ), array( $entry['area'], $entry['group'], $entry['option'] ) );
	}

	/**
	 * Through the registry, a site that never stored it is on (the default).
	 */
	public function test_setting_is_on_by_default(): void {
		$registry = \ZeroSpam\Core\Settings::get_settings();

		$this->assertSame( 'enabled', $registry['delete_data_on_uninstall']['value'] );
		$this->assertSame( 'enabled', $registry['delete_data_on_uninstall']['default'] );

		$this->set_setting( 'delete_data_on_uninstall', false, 'api-monitoring' );
		$this->assertFalse( \ZeroSpam\Core\Settings::get_settings( 'delete_data_on_uninstall' ) );

		$this->set_setting( 'delete_data_on_uninstall', 'disabled', 'api-monitoring' );
		$this->assertFalse( \ZeroSpam\Core\Settings::get_settings( 'delete_data_on_uninstall' ), 'Off as a default-on switch stores it.' );
	}

	/**
	 * On a network, a site that stores no value keeps following the
	 * network; stored values and the network's value that are not
	 * `enabled` become `enabled`, keeping the lock. Runs once per network.
	 *
	 * @group ms-required
	 */
	public function test_uninstall_flag_on_a_network(): void {
		$migrations = new Migrations();

		$this->assertTrue( $migrations->uninstall_flag() );
		$this->assertNull( $this->stored_flag(), 'No site override is created.' );
		$this->assertFalse( get_site_option( Network_Settings::META_KEY ), 'No network value is added.' );
		$this->assertContains( 'v6_uninstall_flag', get_site_option( Migrations::NETWORK_COMPLETED_OPTION ) );

		// A site that stored false is turned on, and so is the network's own value.
		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
		update_option( 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => false ) );
		$this->set_network_setting( 'delete_data_on_uninstall', false );

		$this->assertTrue( $migrations->uninstall_flag() );
		$this->assertSame( 'enabled', $this->stored_flag() );
		$this->assertSame( 'enabled', get_site_option( Network_Settings::META_KEY )['settings']['delete_data_on_uninstall']['value'] );

		// A locked network value keeps its lock (sites never store a locked setting).
		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
		$this->set_network_setting( 'delete_data_on_uninstall', '', true );

		$this->assertTrue( $migrations->uninstall_flag() );
		$entry = get_site_option( Network_Settings::META_KEY )['settings']['delete_data_on_uninstall'];
		$this->assertSame( 'enabled', $entry['value'] );
		$this->assertTrue( $entry['locked'], 'The lock is kept.' );

		// The network part runs once: a later change is left alone.
		$this->set_network_setting( 'delete_data_on_uninstall', false, false );
		$this->assertTrue( $migrations->uninstall_flag() );
		$this->assertFalse( get_site_option( Network_Settings::META_KEY )['settings']['delete_data_on_uninstall']['value'] );
	}

	/**
	 * Out-of-range thresholds are brought to the nearest limit, values that
	 * are not numbers are removed, valid ones are kept as stored.
	 */
	public function test_thresholds_are_kept_within_their_ranges(): void {
		update_option(
			'zero-spam-api-monitoring',
			array(
				'threshold_quota_warning'   => 0,
				'threshold_quota_critical'  => '150',
				'threshold_usage_spike'     => 50,
				'threshold_high_error_rate' => 'lots',
				'threshold_slow_response'   => 999999,
				'api_retention'             => 30,
			)
		);

		$this->assertTrue( ( new Migrations() )->sanitize_thresholds() );

		$this->assertSame(
			array(
				'threshold_quota_warning'  => 1,
				'threshold_quota_critical' => 100,
				'threshold_usage_spike'    => 100,
				'threshold_slow_response'  => 60000,
				'api_retention'            => 30,
			),
			get_option( 'zero-spam-api-monitoring' )
		);
	}

	/**
	 * Valid thresholds, stored as text or numbers, are left as they are;
	 * running again changes nothing.
	 */
	public function test_threshold_sanitizing_is_idempotent(): void {
		$stored = array(
			'threshold_quota_warning' => '80',
			'threshold_usage_spike'   => 250,
			'threshold_slow_response' => true,
		);
		update_option( 'zero-spam-api-monitoring', $stored );

		$migrations = new Migrations();
		$migrations->sanitize_thresholds();
		$first = get_option( 'zero-spam-api-monitoring' );

		$this->assertSame(
			array(
				'threshold_quota_warning' => '80',
				'threshold_usage_spike'   => 250,
			),
			$first
		);

		$this->assertTrue( $migrations->sanitize_thresholds() );
		$this->assertSame( $first, get_option( 'zero-spam-api-monitoring' ) );

		delete_option( 'zero-spam-api-monitoring' );
		$this->assertTrue( $migrations->sanitize_thresholds(), 'Nothing stored: nothing to do.' );
		$this->assertFalse( get_option( 'zero-spam-api-monitoring' ) );
	}
}
