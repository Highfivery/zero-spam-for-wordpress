<?php
/**
 * Tests for the v6_template_orphans migration.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The 18 keys the 5.x built-in templates used that no setting has are
 * removed from the network settings and from custom templates, once per
 * network, with one audit entry; every other key stays, including keys of
 * integrations whose plugin is not active. A single site is untouched.
 *
 * @covers \ZeroSpam\Includes\Migrations::remove_template_orphans
 */
class Test_Migrations_Template_Orphans extends TestCase {

	/**
	 * Real keys in the fixture (verify_wpforms: WPForms is not active).
	 */
	const REAL_KEYS = array( 'zerospam', 'zerospam_confidence_min', 'verify_wpforms', 'max_logs' );

	/**
	 * Orphan keys in the fixture.
	 */
	const FIXTURE_ORPHANS = array( 'comments_enabled', 'cf7_log_flagged_attempts', 'api_daily_limit', 'woocommerce_enabled' );

	/**
	 * Network migration state starts fresh.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		if ( is_multisite() ) {
			delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->base_prefix . DB::$tables['network_templates'] ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE action_type = 'migrate'", $wpdb->base_prefix . DB::$tables['network_audit'] ) );
		}
	}

	/**
	 * A 5.7.14 network: orphan and real keys in the store and in a custom template.
	 */
	private function fixture(): void {
		global $wpdb;

		$settings = array();
		foreach ( array_merge( self::FIXTURE_ORPHANS, self::REAL_KEYS ) as $key ) {
			$settings[ $key ] = array(
				'value'  => 'max_logs' === $key ? 5000 : 'enabled',
				'locked' => 'max_logs' === $key,
			);
		}
		update_site_option( Network_Settings::META_KEY, array( 'settings' => $settings ) );

		$wpdb->insert(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			array(
				'template_name' => 'Clinic',
				'template_slug' => 'clinic',
				'template_type' => 'custom',
				'settings'      => wp_json_encode(
					array(
						'comments_enabled'        => 'enabled',
						'api_hourly_limit'        => 50,
						'verify_wpforms'          => 'enabled',
						'zerospam_confidence_min' => 40,
					)
				),
				'description'   => '',
				'created_by'    => 1,
				'created_at'    => '2026-01-01 00:00:00',
				'updated_at'    => '2026-01-01 00:00:00',
			)
		);
	}

	/**
	 * Settings of the fixture's custom template.
	 *
	 * @return array<string, mixed>
	 */
	private function template_settings(): array {
		global $wpdb;

		return (array) json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT settings FROM %i WHERE template_slug = 'clinic'", $wpdb->base_prefix . DB::$tables['network_templates'] ) ), true );
	}

	/**
	 * Audit entries the migration wrote.
	 */
	private function audit_entries(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE action_type = 'migrate'", $wpdb->base_prefix . DB::$tables['network_audit'] ) );
	}

	/**
	 * Run twice on a network: only the orphans go, once, with one audit entry.
	 *
	 * @group ms-required
	 */
	public function test_removes_only_orphans_once(): void {
		$this->fixture();

		$this->assertTrue( ( new Migrations() )->remove_template_orphans() );

		$store = get_site_option( Network_Settings::META_KEY )['settings'];
		$this->assertEqualsCanonicalizing( self::REAL_KEYS, array_keys( $store ) );
		$this->assertTrue( $store['max_logs']['locked'], 'Locks are kept.' );
		$this->assertSame(
			array(
				'verify_wpforms'          => 'enabled',
				'zerospam_confidence_min' => 40,
			),
			$this->template_settings()
		);
		$this->assertSame( 1, $this->audit_entries() );
		$this->assertContains( 'v6_template_orphans', get_site_option( Migrations::NETWORK_COMPLETED_OPTION ) );

		// A second run changes nothing and records nothing, even with an orphan back in the store.
		$again              = get_site_option( Network_Settings::META_KEY );
		$again['settings'] += array(
			'cf7_enabled' => array(
				'value'  => 'enabled',
				'locked' => false,
			),
		);
		update_site_option( Network_Settings::META_KEY, $again );
		$this->assertTrue( ( new Migrations() )->remove_template_orphans() );
		$this->assertArrayHasKey( 'cf7_enabled', get_site_option( Network_Settings::META_KEY )['settings'] );
		$this->assertSame( 1, $this->audit_entries() );
	}

	/**
	 * Nothing to remove: no audit entry.
	 *
	 * @group ms-required
	 */
	public function test_clean_network_records_nothing(): void {
		update_site_option(
			Network_Settings::META_KEY,
			array(
				'settings' => array(
					'max_logs' => array(
						'value'  => 5000,
						'locked' => false,
					),
				),
			)
		);

		$this->assertTrue( ( new Migrations() )->remove_template_orphans() );
		$this->assertSame( 0, $this->audit_entries() );
		$this->assertArrayHasKey( 'max_logs', get_site_option( Network_Settings::META_KEY )['settings'] );
	}

	/**
	 * A single site has no network settings: nothing changes.
	 *
	 * @group ms-excluded
	 */
	public function test_single_site_changes_nothing(): void {
		update_option( 'zero-spam-settings', array( 'max_logs' => 5000 ) );
		$queries = $this->capture_queries( fn () => $this->assertTrue( ( new Migrations() )->remove_template_orphans() ) );

		$this->assertSame( array(), $queries );
		$this->assertSame( array( 'max_logs' => 5000 ), get_option( 'zero-spam-settings' ) );
	}
}
