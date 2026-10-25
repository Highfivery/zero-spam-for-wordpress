<?php
/**
 * Tests for zero-spam/v1/settings/export and settings/import.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * The export holds every setting with a value except secrets; the import
 * accepts it and the 5.x export, checks every value like PATCH /settings,
 * stores the valid ones and reports the rest.
 *
 * @covers \ZeroSpam\Includes\Rest\Settings_Actions_Controller
 * @covers \ZeroSpam\Includes\Rest\Settings_Controller::validate_values
 * @covers \ZeroSpam\Includes\Rest\Settings_Controller::write_site_values
 */
class Test_Rest_Settings_Import_Export extends TestCase {

	/**
	 * Export route.
	 */
	const EXPORT = '/zero-spam/v1/settings/export';

	/**
	 * Import route.
	 */
	const IMPORT = '/zero-spam/v1/settings/import';

	/**
	 * A license key.
	 */
	const LICENSE = 'import-key-SECRET-7788';

	/**
	 * Logs in an administrator and clears the settings the tests write.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'zerospam', 'settings', 'comments', 'api-monitoring', 'ipstack' ) as $module ) {
			delete_option( "zero-spam-{$module}" );
		}
		delete_option( 'zerospam_blocked_email_domains' );
		delete_option( 'zerospam_settings_audit' );

		$this->as_role( 'administrator' );
	}

	/**
	 * Sends a raw body to the import route.
	 *
	 * @param string $body         Request body.
	 * @param string $content_type Content type.
	 */
	private function import_raw( string $body, string $content_type = 'application/json' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', self::IMPORT );
		$request->set_header( 'Content-Type', $content_type );
		$request->set_body( $body );

		return rest_do_request( $request );
	}

	/**
	 * Only administrators export or import.
	 */
	public function test_permissions(): void {
		foreach ( array( 'editor', 'subscriber' ) as $role ) {
			$this->as_role( $role );
			$this->assertSame( 403, $this->rest( 'GET', self::EXPORT )->get_status() );
			$this->assertSame( 403, $this->rest( 'POST', self::IMPORT, array( 'settings' => array( 'max_logs' => 5 ) ) )->get_status() );
		}

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', self::EXPORT )->get_status() );
		$this->assertFalse( get_option( 'zero-spam-settings' ), 'A refused import stored nothing.' );
	}

	/**
	 * The export has the format header, every value setting by module, and no secret or html setting.
	 */
	public function test_export_omits_secrets_and_html(): void {
		update_option(
			'zero-spam-zerospam',
			array(
				'zerospam_license'        => self::LICENSE,
				'zerospam_confidence_min' => 42,
			)
		);
		update_option( 'zero-spam-api-monitoring', array( 'alert_webhook_url' => 'https://hooks.example.com/SECRET' ) );

		$data = $this->rest( 'GET', self::EXPORT )->get_data();

		$this->assertSame( 'zero-spam-settings', $data['format'] );
		$this->assertSame( 1, $data['version'] );
		$this->assertSame( ZEROSPAM_VERSION, $data['plugin_version'] );
		$this->assertNotFalse( strtotime( $data['exported_at'] ) );
		$this->assertSame( 42, $data['settings']['zerospam']['zerospam_confidence_min'] );
		$this->assertArrayHasKey( 'max_logs', $data['settings']['settings'] );
		$this->assertArrayHasKey( 'blocked_email_domains', $data['settings']['settings'] );
		$this->assertArrayNotHasKey( 'use_recommended_settings', $data['settings']['settings'], 'html settings are left out.' );
		$this->assertArrayNotHasKey( 'zerospam_license', $data['settings']['zerospam'] );
		$this->assertContains( 'zerospam_license', $data['omitted_secrets'] );
		$this->assertContains( 'alert_webhook_url', $data['omitted_secrets'] );
		$this->assertStringNotContainsString( 'SECRET', (string) wp_json_encode( $data ) );
	}

	/**
	 * An export imported again restores the values.
	 */
	public function test_round_trip(): void {
		update_option(
			'zero-spam-settings',
			array(
				'max_logs'      => 1234,
				'block_handler' => 'redirect',
			)
		);
		$export = $this->rest( 'GET', self::EXPORT )->get_data();

		update_option(
			'zero-spam-settings',
			array(
				'max_logs'      => 50,
				'block_handler' => '403',
			)
		);

		$response = $this->rest( 'POST', self::IMPORT, $export );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( array(), (array) $response->get_data()['errors'] );
		$this->assertContains( 'max_logs', $response->get_data()['changed'] );
		$this->assertSame( 1234, get_option( 'zero-spam-settings' )['max_logs'] );
		$this->assertSame( 'redirect', get_option( 'zero-spam-settings' )['block_handler'] );
	}

	/**
	 * The 5.x Import/Export JSON is accepted: html settings are ignored, secrets stored.
	 */
	public function test_legacy_shape_and_secrets(): void {
		$response = $this->rest(
			'POST',
			self::IMPORT,
			array(
				'settings' => array(
					'max_logs'                 => '777',
					'use_recommended_settings' => false,
					'blocked_email_domains'    => "spam.example\nbad.example",
				),
				'zerospam' => array( 'zerospam_license' => self::LICENSE ),
			)
		);

		$data = $response->get_data();
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $data ) );
		$this->assertEqualsCanonicalizing( array( 'max_logs', 'blocked_email_domains', 'zerospam_license' ), $data['imported'] );
		$this->assertSame( array( 'use_recommended_settings' ), $data['ignored'] );
		$this->assertSame( 777, get_option( 'zero-spam-settings' )['max_logs'] );
		$this->assertSame( "spam.example\nbad.example", get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertSame( self::LICENSE, get_option( 'zero-spam-zerospam' )['zerospam_license'] );
		$this->assertStringNotContainsString( 'SECRET', (string) wp_json_encode( get_option( 'zerospam_settings_audit' ) ), 'The audit masks secrets.' );
		$this->assertStringNotContainsString( 'SECRET', (string) wp_json_encode( $data ) );
	}

	/**
	 * Invalid values and unknown keys are reported; the valid values are still stored.
	 */
	public function test_partial_import_reports_errors(): void {
		$response = $this->rest(
			'POST',
			self::IMPORT,
			array(
				'format'   => 'zero-spam-settings',
				'version'  => 1,
				'settings' => array(
					'settings' => array(
						'max_logs'      => 'lots',
						'block_handler' => 'redirect',
						'no_such_key'   => 1,
					),
					'oops'     => 'not a module',
				),
			)
		);

		$data = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'block_handler' ), $data['imported'] );
		$this->assertEqualsCanonicalizing( array( 'max_logs', 'no_such_key' ), array_keys( (array) $data['errors'] ) );
		$this->assertSame( array( 'oops' ), $data['ignored'] );
		$this->assertSame( array( 'block_handler' => 'redirect' ), get_option( 'zero-spam-settings' ) );
	}

	/**
	 * A body that is not a JSON object is refused and stores nothing.
	 *
	 * @dataProvider provide_bad_bodies
	 *
	 * @param string $body Request body.
	 */
	public function test_invalid_body_is_refused( string $body ): void {
		$response = $this->import_raw( $body, 'text/plain' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( 'body', $response->get_data()['data']['params'] );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * Bodies that are not a settings object.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_bad_bodies(): array {
		return array(
			'broken JSON'    => array( '{"settings": {' ),
			'a list'         => array( '[1, 2]' ),
			'a string'       => array( '"settings"' ),
			'empty'          => array( '' ),
			'foreign format' => array( '{"format": "other-plugin", "settings": {"settings": {"max_logs": 5}}}' ),
		);
	}

	/**
	 * Broken JSON sent as JSON is refused by WordPress before the route runs.
	 */
	public function test_broken_json_body_is_refused(): void {
		$response = $this->import_raw( '{"settings": {' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_json', $response->get_data()['code'] );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * More than 5 MB is refused before it is parsed.
	 */
	public function test_too_large_is_refused(): void {
		$body = '{"settings":{"allowed_words":"' . str_repeat( 'a', 5 * MB_IN_BYTES ) . '"}}';

		$response = $this->import_raw( $body );

		$this->assertSame( 413, $response->get_status() );
		$this->assertSame( 'zerospam_import_too_large', $response->get_data()['code'] );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}
}
