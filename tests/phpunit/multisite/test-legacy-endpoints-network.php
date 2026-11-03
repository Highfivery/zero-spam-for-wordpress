<?php
/**
 * Tests for the deprecated AJAX actions of the 5.x network screens.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Legacy_Endpoints;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The 13 AJAX actions of the 5.x network settings screen and the network
 * statistics export stay one release (build plan C10): with the 5.x nonce
 * and `manage_network_options` they run the REST route that replaced them
 * and answer in the 5.x shape; without either they are refused.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Legacy_Endpoints
 */
class Test_Legacy_Endpoints_Network extends TestCase {

	/**
	 * A super admin with clean network settings and templates.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$this->as_role( 'administrator' );
		grant_super_admin( get_current_user_id() );
		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::cache_key() );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE template_type = 'custom'", $wpdb->base_prefix . DB::$tables['network_templates'] ) );
	}

	/**
	 * Runs one 5.x AJAX action as admin-ajax.php would.
	 *
	 * @param string               $action AJAX action (without wp_ajax_).
	 * @param array<string, mixed> $fields POST fields besides the nonce.
	 * @param string|null          $nonce  Nonce (default: a valid one).
	 * @return array<string, mixed>|null JSON answer.
	 */
	private function call( string $action, array $fields = array(), ?string $nonce = null ): ?array {
		$endpoints = new Legacy_Endpoints();
		$handler   = 'zerospam_export_network_stats' === $action ? array( $endpoints, 'export_network_stats' ) : array( $endpoints, 'network_ajax' );
		$nonce     = $nonce ?? wp_create_nonce( 'zerospam_export_network_stats' === $action ? 'zerospam_network_stats' : 'zerospam_network_settings' );

		$GLOBALS['wp_current_filter'][] = 'wp_ajax_' . $action;

		try {
			return $this->ajax( $handler, array( 'nonce' => $nonce ) + $fields );
		} finally {
			array_pop( $GLOBALS['wp_current_filter'] );
		}
	}

	/**
	 * The stored network entry of a setting.
	 *
	 * @param string $key Setting key.
	 * @return array<string, mixed>|null
	 */
	private function network_entry( string $key ): ?array {
		$stored = get_site_option( Network_Settings::META_KEY, array() );

		return $stored['settings'][ $key ] ?? null;
	}

	/**
	 * Every 5.x action is registered on a network.
	 */
	public function test_every_action_is_registered(): void {
		$endpoints = new Legacy_Endpoints();

		foreach ( array_keys( Legacy_Endpoints::NETWORK_AJAX ) as $action ) {
			$this->assertNotFalse( has_action( 'wp_ajax_' . $action, array( $endpoints, 'network_ajax' ) ), $action );
		}
		$this->assertCount( 13, Legacy_Endpoints::NETWORK_AJAX );
		$this->assertNotFalse( has_action( 'wp_ajax_zerospam_export_network_stats', array( $endpoints, 'export_network_stats' ) ) );
	}

	/**
	 * Set, lock and unlock reach the network store.
	 */
	public function test_set_lock_and_unlock(): void {
		$this->setExpectedDeprecated( 'zerospam_network_set_setting' );
		$this->setExpectedDeprecated( 'zerospam_network_lock_setting' );
		$this->setExpectedDeprecated( 'zerospam_network_unlock_setting' );

		$answer = $this->call(
			'zerospam_network_set_setting',
			array(
				'key'   => 'max_logs',
				'value' => '4321',
			)
		);
		$this->assertTrue( $answer['success'], (string) wp_json_encode( $answer ) );
		$this->assertSame( 4321, (int) $this->network_entry( 'max_logs' )['value'] );
		$this->assertEmpty( $this->network_entry( 'max_logs' )['locked'] );

		$this->assertTrue( $this->call( 'zerospam_network_lock_setting', array( 'key' => 'max_logs' ) )['success'] );
		$this->assertTrue( $this->network_entry( 'max_logs' )['locked'] );

		$this->assertTrue( $this->call( 'zerospam_network_unlock_setting', array( 'key' => 'max_logs' ) )['success'] );
		$this->assertFalse( $this->network_entry( 'max_logs' )['locked'] );
	}

	/**
	 * `locked=1` locks after saving, as the 5.x screen's lock switch did.
	 */
	public function test_set_with_lock(): void {
		$this->setExpectedDeprecated( 'zerospam_network_set_setting' );

		$this->call(
			'zerospam_network_set_setting',
			array(
				'key'    => 'max_logs',
				'value'  => '5000',
				'locked' => '1',
			)
		);

		$this->assertTrue( $this->network_entry( 'max_logs' )['locked'] );
	}

	/**
	 * A bad nonce and a site administrator are refused, and nothing is stored.
	 */
	public function test_nonce_and_capability(): void {
		$answer = $this->call(
			'zerospam_network_set_setting',
			array(
				'key'   => 'max_logs',
				'value' => '1',
			),
			'bad'
		);
		$this->assertNull( $answer, 'check_ajax_referer() ends the request.' );
		$this->assertNull( $this->network_entry( 'max_logs' ) );

		revoke_super_admin( get_current_user_id() );
		$answer = $this->call(
			'zerospam_network_set_setting',
			array(
				'key'   => 'max_logs',
				'value' => '1',
			)
		);
		$this->assertFalse( $answer['success'] );
		$this->assertNull( $this->network_entry( 'max_logs' ) );

		$this->assertFalse( $this->call( 'zerospam_export_network_stats' )['success'] );
	}

	/**
	 * Templates: save, apply to the network, and delete; a built-in one cannot be deleted.
	 */
	public function test_templates(): void {
		$this->setExpectedDeprecated( 'zerospam_network_save_template' );
		$this->setExpectedDeprecated( 'zerospam_network_apply_template' );
		$this->setExpectedDeprecated( 'zerospam_network_delete_template' );
		$this->set_network_setting( 'max_logs', 777 );

		$saved = $this->call(
			'zerospam_network_save_template',
			array(
				'name' => 'Mine',
				'slug' => 'mine',
			)
		);
		$this->assertTrue( $saved['success'], (string) wp_json_encode( $saved ) );

		$applied = $this->call(
			'zerospam_network_apply_template',
			array(
				'slug'  => 'balanced',
				'scope' => 'network',
			)
		);
		$this->assertTrue( $applied['success'], (string) wp_json_encode( $applied ) );

		$this->assertFalse( $this->call( 'zerospam_network_delete_template', array( 'slug' => 'balanced' ) )['success'], 'Built-in templates stay.' );
		$this->assertTrue( $this->call( 'zerospam_network_delete_template', array( 'slug' => 'mine' ) )['success'] );
		$this->assertSame( 404, $this->rest( 'GET', '/zero-spam/v1/network-settings/templates/mine' )->get_status() );
	}

	/**
	 * The notification switches reach the network options.
	 */
	public function test_notifications(): void {
		$this->setExpectedDeprecated( 'zerospam_network_save_notification_settings' );
		$this->setExpectedDeprecated( 'zerospam_network_toggle_notifications' );

		$this->assertTrue(
			$this->call(
				'zerospam_network_save_notification_settings',
				array(
					'weekly_enabled'   => '0',
					'settings_enabled' => '0',
				)
			)['success']
		);
		$this->assertSame( '0', (string) get_site_option( 'zerospam_network_notifications_enabled' ) );
		$this->assertSame( '0', (string) get_site_option( 'zerospam_network_settings_notifications_enabled' ) );

		$this->assertTrue( $this->call( 'zerospam_network_toggle_notifications', array( 'enabled' => '1' ) )['success'] );
		$this->assertSame( '1', (string) get_site_option( 'zerospam_network_notifications_enabled' ) );
	}

	/**
	 * The export never shows a secret, as the REST export (it showed them in clear in 5.x).
	 */
	public function test_export_leaves_secrets_out(): void {
		$this->setExpectedDeprecated( 'zerospam_network_export' );
		$this->set_network_setting( 'zerospam_license', 'network-secret-license-0042' );
		$this->set_network_setting( 'max_logs', 900 );

		$answer = $this->call( 'zerospam_network_export' );

		$this->assertTrue( $answer['success'] );
		$this->assertStringNotContainsString( 'network-secret-license-0042', (string) wp_json_encode( $answer ) );
		$this->assertArrayHasKey( 'max_logs', json_decode( $answer['data']['json'], true ) );
	}

	/**
	 * Import, apply-all, reset and the comparison answer through their routes.
	 */
	public function test_import_apply_reset_compare(): void {
		$this->setExpectedDeprecated( 'zerospam_network_import' );
		$this->setExpectedDeprecated( 'zerospam_network_apply_all' );
		$this->setExpectedDeprecated( 'zerospam_network_reset_site' );
		$this->setExpectedDeprecated( 'zerospam_network_get_comparison' );

		$imported = $this->call( 'zerospam_network_import', array( 'json' => (string) wp_json_encode( array( 'max_logs' => array( 'value' => 3000 ) ) ) ) );
		$this->assertTrue( $imported['success'], (string) wp_json_encode( $imported ) );
		$this->assertSame( 3000, (int) $this->network_entry( 'max_logs' )['value'] );

		$this->assertFalse( $this->call( 'zerospam_network_import', array( 'json' => 'not json' ) )['success'] );

		$this->assertTrue( $this->call( 'zerospam_network_apply_all', array( 'mode' => 'defaults_only' ) )['success'] );
		$reset = $this->call( 'zerospam_network_reset_site', array( 'site_id' => (string) get_current_blog_id() ) );
		$this->assertTrue( $reset['success'], (string) wp_json_encode( $reset ) );
		$compare = $this->call( 'zerospam_network_get_comparison' );
		$this->assertTrue( $compare['success'], (string) wp_json_encode( $compare ) );
	}

	/**
	 * The statistics export keeps the 5.x CSV rows, from network/stats.
	 */
	public function test_export_network_stats(): void {
		$this->setExpectedDeprecated( 'zerospam_export_network_stats' );

		$answer = $this->call( 'zerospam_export_network_stats', array( 'period' => 'week' ) );

		$this->assertTrue( $answer['success'], (string) wp_json_encode( $answer ) );
		$this->assertSame( array( 'Site Name', 'Site URL', 'Spam Count', 'Unique IPs', 'Top Type', 'Top Country', 'Trend %', 'Protection' ), $answer['data']['csv'][0] );
		$this->assertGreaterThan( 1, count( $answer['data']['csv'] ) );
		$this->assertStringStartsWith( 'zero-spam-network-stats-', $answer['data']['filename'] );
	}
}
