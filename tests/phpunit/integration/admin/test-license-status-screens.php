<?php
/**
 * Tests that the admin header, the dashboard widget and the notices agree
 * on the license status.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Core\License_Status;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\TestCase;

/**
 * Before 6.0 the widget asked a second endpoint (and treated its errors as
 * "valid"), so it could say "Enhanced Protection Active" while the header
 * said the key was invalid or out of queries (inventory §12.20). Both now
 * read License_Status, with one request between them.
 *
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget::render_widget
 * @covers \ZeroSpam\Core\Admin\Admin::admin_notices
 * @covers \ZeroSpam\Core\License_Status
 */
class Test_License_Status_Screens extends TestCase {

	/**
	 * Pattern of the license endpoint the plugin uses.
	 */
	const ENDPOINT = '#/wp-json/v2/get-license#';

	/**
	 * A license key.
	 */
	const KEY = 'screens-license-key-7777';

	/**
	 * Enhanced Protection on with a key, as an administrator on the dashboard.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->as_role( 'administrator' );
		$this->admin_screen( 'dashboard' );

		delete_transient( License_Status::TRANSIENT );
		delete_transient( sanitize_title( 'license_' . self::KEY ) );
		delete_transient( 'zerospam_stats_timeseries' );
		update_option(
			'zero-spam-zerospam',
			array(
				'zerospam'         => 'enabled',
				'zerospam_license' => self::KEY,
			)
		);
		self::reset_static_caches();
	}

	/**
	 * The admin header's HTML.
	 */
	private function header(): string {
		ob_start();
		require ZEROSPAM_PATH . 'includes/templates/admin-header.php';

		return (string) ob_get_clean();
	}

	/**
	 * The dashboard widget's HTML.
	 */
	private function widget(): string {
		$widget = ( new ReflectionClass( Dashboard_Widget::class ) )->newInstanceWithoutConstructor();

		ob_start();
		$widget->render_widget();

		return (string) ob_get_clean();
	}

	/**
	 * The admin notices' HTML.
	 */
	private function notices(): string {
		$admin = ( new ReflectionClass( Admin::class ) )->newInstanceWithoutConstructor();

		ob_start();
		$admin->admin_notices();

		return (string) ob_get_clean();
	}

	/**
	 * API answer, what the header says, and the widget's protection level line.
	 *
	 * @return array<string, array{0: array<string, mixed>|string, 1: int, 2: string, 3: string|null}>
	 */
	public static function data_statuses(): array {
		return array(
			'valid'           => array(
				array(
					'license_key'       => self::KEY,
					'queries_limit'     => 1000,
					'queries_made'      => 100,
					'queries_remaining' => 900,
				),
				200,
				'900 queries available',
				'Enhanced · Active',
			),
			'quota used up'   => array(
				array(
					'license_key'       => self::KEY,
					'queries_limit'     => 1000,
					'queries_made'      => 1000,
					'queries_remaining' => 0,
				),
				200,
				'License query limit reached.',
				'Enhanced · Query limit reached',
			),
			'invalid'         => array(
				array(
					'status'   => 400,
					'response' => 'invalid_license',
				),
				200,
				'(invalid license)',
				'Enhanced · License not valid',
			),
			'API unreachable' => array( 'down', 503, 'Couldn\'t verify your license right now', 'Enhanced · Couldn’t verify the license right now' ),
		);
	}

	/**
	 * The header and the widget say the same thing, from one request.
	 *
	 * @dataProvider data_statuses
	 *
	 * @param array<string, mixed>|string $body   API answer.
	 * @param int                         $code   HTTP status.
	 * @param string                      $header Text in the header.
	 * @param string|null                 $banner Widget protection level, or null for none.
	 */
	public function test_header_and_widget_agree( $body, int $code, string $header, ?string $banner ): void {
		$this->mock_http( self::ENDPOINT, $body, $code );

		$header_html = $this->header();
		$widget_html = $this->widget();

		$this->assertStringContainsString( esc_html( $header ), $header_html );

		foreach ( array( 'Enhanced · Active', 'Enhanced · Query limit reached', 'Enhanced · License not valid', 'Enhanced · Couldn’t verify the license right now', 'Protection level: <b>Basic' ) as $candidate ) {
			if ( $candidate === $banner ) {
				$this->assertStringContainsString( $candidate, $widget_html );
			} else {
				$this->assertStringNotContainsString( $candidate, $widget_html, "The widget says \"{$candidate}\" while the header says \"{$header}\"." );
			}
		}

		$this->assertSame( 1, HttpMock::hits( self::ENDPOINT ), 'The header and the widget share one license request.' );
	}

	/**
	 * The notices use the same key and setting, and make no request.
	 */
	public function test_notices_follow_license_status(): void {
		$this->assertStringNotContainsString( 'missing a valid license key', $this->notices() );

		update_option( 'zero-spam-zerospam', array( 'zerospam' => 'enabled' ) );
		self::reset_static_caches();

		$this->assertStringContainsString( 'missing a valid license key', $this->notices() );
		$this->assertSame( array(), HttpMock::requests() );
	}

	/**
	 * With Enhanced Protection off the header says so and nothing is requested.
	 */
	public function test_disabled_makes_no_request(): void {
		update_option( 'zero-spam-zerospam', array( 'zerospam_license' => self::KEY ) );
		self::reset_static_caches();

		$this->assertStringContainsString( 'DISABLED', $this->header() );
		$this->assertSame( array(), HttpMock::requests() );
	}
}
