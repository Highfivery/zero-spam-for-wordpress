<?php
/**
 * Tests for the routes the 6.0 Settings page adds or calls its own way.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\Settings_Actions_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * The settings/error-log route returns the end of the error log to administrators
 * only; settings/import, as the page sends it (a file's text, with
 * include_secrets=1 when asked), reports every group the page lists.
 *
 * @covers \ZeroSpam\Includes\Rest\Settings_Actions_Controller
 */
class Test_Rest_Settings_Page extends TestCase {

	/**
	 * Error log route.
	 */
	const ERROR_LOG = '/zero-spam/v1/settings/error-log';

	/**
	 * Import route.
	 */
	const IMPORT = '/zero-spam/v1/settings/import';

	/**
	 * Path of this site's error log.
	 */
	private static function log_file(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'zerospam.log';
	}

	/**
	 * No error log, no settings the tests write.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_delete_file( self::log_file() );
		foreach ( array( 'settings', 'zerospam', 'api-monitoring' ) as $module ) {
			delete_option( "zero-spam-{$module}" );
		}
	}

	/**
	 * Removes the error log.
	 */
	public function tear_down(): void {
		wp_delete_file( self::log_file() );

		parent::tear_down();
	}

	/**
	 * Writes the error log.
	 *
	 * @param string $text Contents.
	 */
	private function write_log( string $text ): void {
		wp_mkdir_p( dirname( self::log_file() ) );
		file_put_contents( self::log_file(), $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Seeds a fixture file.
	}

	/**
	 * Only administrators read the log (it can hold visitor IP addresses).
	 */
	public function test_error_log_needs_an_administrator(): void {
		$this->write_log( "[25-Oct-2026 09:14:02 UTC] 203.0.113.10 timed out\n" );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( 'GET', self::ERROR_LOG )->get_status() );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', self::ERROR_LOG )->get_status() );
	}

	/**
	 * Without a log the answer is empty.
	 */
	public function test_error_log_empty(): void {
		$this->as_role( 'administrator' );

		$this->assertSame(
			array(
				'log'       => '',
				'size'      => 0,
				'truncated' => false,
			),
			$this->rest( 'GET', self::ERROR_LOG )->get_data()
		);
	}

	/**
	 * A small log comes back whole.
	 */
	public function test_error_log_whole(): void {
		$this->as_role( 'administrator' );
		$text = "[25-Oct-2026 09:14:02 UTC] Stop Forum Spam timed out\n[25-Oct-2026 09:15:00 UTC] IPinfo returned 429\n";
		$this->write_log( $text );

		$data = $this->rest( 'GET', self::ERROR_LOG )->get_data();

		$this->assertSame( $text, $data['log'] );
		$this->assertSame( strlen( $text ), $data['size'] );
		$this->assertFalse( $data['truncated'] );
	}

	/**
	 * A large log comes back as its last 64 KB, from a whole line.
	 */
	public function test_error_log_end_from_a_whole_line(): void {
		$this->as_role( 'administrator' );
		$line = str_repeat( 'x', 99 ) . "\n";
		$text = 'FIRST LINE' . "\n" . str_repeat( $line, 1000 ) . "LAST LINE\n";
		$this->write_log( $text );

		$data = $this->rest( 'GET', self::ERROR_LOG )->get_data();

		$this->assertTrue( $data['truncated'] );
		$this->assertSame( strlen( $text ), $data['size'] );
		$this->assertLessThanOrEqual( Settings_Actions_Controller::ERROR_LOG_BYTES, strlen( $data['log'] ) );
		$this->assertStringStartsWith( str_repeat( 'x', 99 ), $data['log'], 'Starts at a whole line.' );
		$this->assertStringEndsWith( "LAST LINE\n", $data['log'] );
		$this->assertStringNotContainsString( 'FIRST LINE', $data['log'] );
	}

	/**
	 * Clear error log empties what the route returns.
	 */
	public function test_clear_error_log(): void {
		$this->as_role( 'administrator' );
		$this->write_log( "something\n" );

		$this->assertSame( 200, $this->rest( 'POST', '/zero-spam/v1/settings/actions/clear-error-log' )->get_status() );
		$this->assertSame( '', $this->rest( 'GET', self::ERROR_LOG )->get_data()['log'] );
	}

	/**
	 * The page sends the file's text and `include_secrets=1` when the box is ticked.
	 */
	public function test_import_as_the_page_sends_it(): void {
		$this->as_role( 'administrator' );
		$file = (string) wp_json_encode(
			array(
				'format'   => 'zero-spam-settings',
				'version'  => 1,
				'settings' => array(
					'settings'       => array( 'max_logs' => 600 ),
					'api-monitoring' => array(
						'threshold_quota_warning'  => 70,
						'delete_data_on_uninstall' => false,
						'quota_reset_date'         => '2026-02-30',
						'alert_webhook_url'        => 'https://hooks.example.com/abc',
					),
				),
			)
		);

		$send = static function ( array $query ) use ( $file ): WP_REST_Response {
			$request = new WP_REST_Request( 'POST', self::IMPORT );
			$request->set_query_params( $query );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( $file );

			return rest_do_request( $request );
		};

		$data = $send( array() )->get_data();
		$this->assertEqualsCanonicalizing( array( 'max_logs', 'threshold_quota_warning', 'delete_data_on_uninstall' ), $data['imported'] );
		$this->assertSame( array( 'alert_webhook_url' ), $data['skipped_secrets'] );
		$this->assertSame( array( 'quota_reset_date' ), array_keys( (array) $data['errors'] ) );
		$this->assertSame( 70, get_option( 'zero-spam-api-monitoring' )['threshold_quota_warning'] );

		$data = $send( array( 'include_secrets' => '1' ) )->get_data();
		$this->assertContains( 'alert_webhook_url', $data['imported'] );
		$this->assertSame( array(), $data['skipped_secrets'] );
	}

	/**
	 * A threshold outside its range is refused, like PATCH /settings refuses it.
	 */
	public function test_threshold_out_of_range_is_refused(): void {
		$this->as_role( 'administrator' );

		$response = $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'threshold_slow_response' => 50 ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'threshold_slow_response', $response->get_data()['data']['params'] );
	}

	/**
	 * On a subsite, locked settings are skipped and reported.
	 *
	 * @group ms-required
	 */
	public function test_import_skips_locked_settings(): void {
		$this->as_role( 'administrator' );
		$this->set_network_setting( 'max_logs', 900, true );

		$data = $this->rest(
			'POST',
			self::IMPORT,
			array(
				'settings' => array(
					'max_logs'   => 600,
					'share_data' => 'enabled',
				),
			)
		)->get_data();

		$this->assertSame( array( 'max_logs' ), $data['skipped_locked'] );
		$this->assertSame( array( 'share_data' ), $data['imported'] );
	}
}
