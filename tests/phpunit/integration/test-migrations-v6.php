<?php
/**
 * Tests for the 6.0 settings migrations.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The v6_backup_5x migration keeps a copy of the 5.x settings; v6_network_store merges the
 * 5.x REST network option into the network settings store. Both can run
 * again without changing anything, and the old option is never touched.
 *
 * @covers \ZeroSpam\Includes\Migrations::backup_5x_settings
 * @covers \ZeroSpam\Includes\Migrations::merge_network_store
 * @covers \ZeroSpam\Includes\Migrations::security_switches
 */
class Test_Migrations_V6 extends TestCase {

	/**
	 * 5.7.14-shaped site settings.
	 *
	 * @var array<string, mixed>
	 */
	const SITE_FIXTURE = array(
		'zero-spam-settings'    => array(
			'max_logs'        => 5000,
			'log_blocked_ips' => 'enabled',
			'block_handler'   => 403,
		),
		'zero-spam-zerospam'    => array(
			'zerospam'                => 'enabled',
			'zerospam_license'        => 'fixture-license-key',
			'zerospam_confidence_min' => 40,
		),
		'zero-spam-comments'    => array( 'verify_comments' => 'enabled' ),
		'zero-spam-last-update' => '5.7.14',
	);

	/**
	 * 5.7.14-shaped network store (zerospam_network_settings).
	 *
	 * @var array<string, mixed>
	 */
	const NETWORK_STORE_FIXTURE = array(
		'settings' => array(
			'max_logs'                => array(
				'value'  => 800,
				'locked' => true,
			),
			'zerospam_confidence_min' => array(
				'value'  => 50,
				'locked' => false,
			),
		),
	);

	/**
	 * 5.7.14-shaped REST network option (zero-spam-network-zerospam).
	 *
	 * @var array<string, mixed>
	 */
	const LEGACY_NETWORK_FIXTURE = array(
		'zerospam_confidence_min' => 70,
		'zerospam'                => 'enabled',
		'zerospam_timeout'        => 8,
	);

	/**
	 * Seeds the fixtures and forgets earlier backups.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::SITE_FIXTURE as $option => $value ) {
			update_option( $option, $value );
		}

		update_site_option( Network_Settings::META_KEY, self::NETWORK_STORE_FIXTURE );
		update_site_option( Migrations::LEGACY_NETWORK_OPTION, self::LEGACY_NETWORK_FIXTURE );
		delete_site_transient( Network_Settings::CACHE_KEY );

		delete_option( Migrations::BACKUP_5X_OPTION );
		delete_site_option( Migrations::BACKUP_5X_OPTION );
		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
	}

	/**
	 * The site backup holds every zero-spam-* option and is not autoloaded.
	 */
	public function test_backup_copies_every_site_option(): void {
		global $wpdb;

		$this->assertTrue( ( new Migrations() )->backup_5x_settings() );

		$backup = get_option( Migrations::BACKUP_5X_OPTION );

		foreach ( self::SITE_FIXTURE as $option => $value ) {
			$this->assertSame( $value, $backup['options'][ $option ], $option );
		}
		$this->assertSame( '5.7.14', $backup['from_version'] );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Migrations::BACKUP_5X_OPTION ) );
		$this->assertContains( $autoload, array( 'off', 'no' ) );
	}

	/**
	 * On a single site the network options go into the same backup.
	 *
	 * @group ms-excluded
	 */
	public function test_backup_on_a_single_site_includes_network_options(): void {
		( new Migrations() )->backup_5x_settings();

		$backup = get_option( Migrations::BACKUP_5X_OPTION );

		$this->assertSame( self::NETWORK_STORE_FIXTURE, $backup['options'][ Network_Settings::META_KEY ] );
		$this->assertSame( self::LEGACY_NETWORK_FIXTURE, $backup['options'][ Migrations::LEGACY_NETWORK_OPTION ] );
	}

	/**
	 * On a network the network options go into the site-option twin, once.
	 *
	 * @group ms-required
	 */
	public function test_backup_on_a_network_has_a_site_option_twin(): void {
		( new Migrations() )->backup_5x_settings();

		$twin = get_site_option( Migrations::BACKUP_5X_OPTION );

		$this->assertSame( self::NETWORK_STORE_FIXTURE, $twin['options'][ Network_Settings::META_KEY ] );
		$this->assertSame( self::LEGACY_NETWORK_FIXTURE, $twin['options'][ Migrations::LEGACY_NETWORK_OPTION ] );
		$this->assertContains( 'v6_backup_5x', get_site_option( Migrations::NETWORK_COMPLETED_OPTION ) );
		$this->assertArrayNotHasKey( Network_Settings::META_KEY, get_option( Migrations::BACKUP_5X_OPTION )['options'] );
	}

	/**
	 * Running the backup again never replaces the first copy.
	 */
	public function test_backup_runs_twice_without_change(): void {
		$migrations = new Migrations();
		$migrations->backup_5x_settings();
		$first      = get_option( Migrations::BACKUP_5X_OPTION );
		$first_twin = get_site_option( Migrations::BACKUP_5X_OPTION );

		update_option( 'zero-spam-settings', array( 'max_logs' => 1 ) );
		update_site_option( Network_Settings::META_KEY, array( 'settings' => array() ) );

		$this->assertTrue( $migrations->backup_5x_settings() );
		$this->assertSame( $first, get_option( Migrations::BACKUP_5X_OPTION ) );
		$this->assertSame( $first_twin, get_site_option( Migrations::BACKUP_5X_OPTION ) );
	}

	/**
	 * The 5.x REST network values are added where the store has none; the store wins otherwise.
	 *
	 * @group ms-required
	 */
	public function test_network_store_merge(): void {
		$this->assertTrue( ( new Migrations() )->merge_network_store() );

		$store = get_site_option( Network_Settings::META_KEY )['settings'];

		$this->assertSame( 50, $store['zerospam_confidence_min']['value'], 'The store\'s own value wins.' );
		$this->assertSame( 800, $store['max_logs']['value'] );
		$this->assertTrue( $store['max_logs']['locked'] );
		$this->assertSame( 'enabled', $store['zerospam']['value'] );
		$this->assertFalse( $store['zerospam']['locked'] );
		$this->assertSame( 8, $store['zerospam_timeout']['value'] );
		$this->assertSame( self::LEGACY_NETWORK_FIXTURE, get_site_option( Migrations::LEGACY_NETWORK_OPTION ), 'The 5.x option is left in place.' );
		$this->assertContains( 'v6_network_store', get_site_option( Migrations::NETWORK_COMPLETED_OPTION ) );
	}

	/**
	 * The merge runs once per network: a second run changes nothing.
	 *
	 * @group ms-required
	 */
	public function test_network_store_merge_runs_once(): void {
		$migrations = new Migrations();
		$migrations->merge_network_store();
		$after_first = get_site_option( Network_Settings::META_KEY );

		update_site_option( Migrations::LEGACY_NETWORK_OPTION, array( 'zerospam_cache' => 3 ) );

		$this->assertTrue( $migrations->merge_network_store() );
		$this->assertSame( $after_first, get_site_option( Network_Settings::META_KEY ) );
	}

	/**
	 * Without the guard (a fresh network), merging twice still gives the same store.
	 *
	 * @group ms-required
	 */
	public function test_network_store_merge_is_idempotent(): void {
		$migrations = new Migrations();
		$migrations->merge_network_store();
		$after_first = get_site_option( Network_Settings::META_KEY );

		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
		$migrations->merge_network_store();

		$this->assertSame( $after_first, get_site_option( Network_Settings::META_KEY ) );
	}

	/**
	 * While another request holds the network lock, the merge waits for a later request.
	 *
	 * @group ms-required
	 */
	public function test_network_store_merge_waits_for_the_lock(): void {
		switch_to_blog( get_main_site_id() );
		add_option( Migrations::NETWORK_LOCK_OPTION, (string) time(), '', false );
		restore_current_blog();

		$this->assertFalse( ( new Migrations() )->merge_network_store() );
		$this->assertArrayNotHasKey( 'zerospam', get_site_option( Network_Settings::META_KEY )['settings'] );
	}

	/**
	 * On a single site there is no network store to merge.
	 *
	 * @group ms-excluded
	 */
	public function test_network_store_merge_on_a_single_site(): void {
		$this->assertTrue( ( new Migrations() )->merge_network_store() );
		$this->assertSame( self::NETWORK_STORE_FIXTURE, get_site_option( Network_Settings::META_KEY ) );
	}

	/**
	 * The v6_security_switches migration records both new switches as on, next to the
	 * module's other settings, on a single site.
	 *
	 * @group ms-excluded
	 */
	public function test_security_switches_are_stored_as_on(): void {
		update_option( 'zero-spam-security', array( 'disable_emojis' => 'enabled' ) );

		$this->assertTrue( ( new Migrations() )->security_switches() );

		$this->assertSame(
			array(
				'disable_emojis'  => 'enabled',
				'disable_xmlrpc'  => 'enabled',
				'hide_wp_version' => 'enabled',
			),
			get_option( 'zero-spam-security' )
		);
	}

	/**
	 * A switch the site already turned off stays off (`disabled`), and a
	 * second run changes nothing.
	 *
	 * @group ms-excluded
	 */
	public function test_security_switches_keep_stored_values_and_run_twice(): void {
		update_option( 'zero-spam-security', array( 'disable_xmlrpc' => 'disabled' ) );

		$migrations = new Migrations();
		$migrations->security_switches();
		$after_first = get_option( 'zero-spam-security' );
		$migrations->security_switches();

		$this->assertSame( 'disabled', $after_first['disable_xmlrpc'] );
		$this->assertSame( 'enabled', $after_first['hide_wp_version'] );
		$this->assertSame( $after_first, get_option( 'zero-spam-security' ) );
	}

	/**
	 * Without the module option the migration creates it.
	 *
	 * @group ms-excluded
	 */
	public function test_security_switches_without_an_option(): void {
		delete_option( 'zero-spam-security' );

		( new Migrations() )->security_switches();

		$this->assertSame(
			array(
				'disable_xmlrpc'  => 'enabled',
				'hide_wp_version' => 'enabled',
			),
			get_option( 'zero-spam-security' )
		);
	}

	/**
	 * On a network nothing is stored (a stored value would be a site override
	 * of a later network default); the switches are on all the same.
	 *
	 * @group ms-required
	 */
	public function test_security_switches_store_nothing_on_a_network(): void {
		delete_option( 'zero-spam-security' );

		$this->assertTrue( ( new Migrations() )->security_switches() );

		$this->assertFalse( get_option( 'zero-spam-security' ) );
		self::reset_static_caches();
		$this->assertSame( 'enabled', \ZeroSpam\Core\Settings_Schema::value( 'disable_xmlrpc' ) );
		$this->assertSame( 'enabled', \ZeroSpam\Core\Settings_Schema::value( 'hide_wp_version' ) );
	}

	/**
	 * The migration is registered after the 5.x backup, which keeps the
	 * settings as they were before it.
	 */
	public function test_security_switches_run_after_the_backup(): void {
		$method = new ReflectionMethod( Migrations::class, 'get_migrations' );
		$keys   = array_keys( $method->invoke( new Migrations() ) );

		$this->assertContains( 'v6_security_switches', $keys );
		$this->assertGreaterThan( array_search( 'v6_backup_5x', $keys, true ), array_search( 'v6_security_switches', $keys, true ) );
	}
}
