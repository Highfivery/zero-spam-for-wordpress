<?php
/**
 * Tests for the admin dashboard handlers.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard;
use ZeroSpam\Tests\TestCase;

/**
 * Admin actions need a capability check and a nonce.
 *
 * @covers \ZeroSpam\Core\Admin\Dashboard::admin_init
 */
class Test_Dashboard extends TestCase {

	/**
	 * A subscriber cannot make the plugin rewrite .htaccess with a bare query flag.
	 *
	 * Utilities::refresh_htaccess() starts by reading the `block_method`
	 * setting, which reads the zero-spam-settings option; the spy on that
	 * option shows whether it ran.
	 *
	 * @group known-defect
	 */
	public function test_refresh_htaccess_requires_capability_and_nonce(): void {
		$this->admin_screen();
		$this->as_role( 'subscriber' );
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$reads = 0;
		add_filter(
			'pre_option_zero-spam-settings',
			static function ( $pre ) use ( &$reads ) {
				++$reads;

				return $pre;
			}
		);

		( new Dashboard() )->admin_init();

		$this->assertSame( 0, $reads, 'refresh_htaccess() ran for a subscriber without a nonce.' );
	}

	/**
	 * Without the flag nothing runs.
	 */
	public function test_admin_init_without_flag_does_nothing(): void {
		$this->admin_screen();
		$this->as_role( 'administrator' );

		$reads = 0;
		add_filter(
			'pre_option_zero-spam-settings',
			static function ( $pre ) use ( &$reads ) {
				++$reads;

				return $pre;
			}
		);

		( new Dashboard() )->admin_init();

		$this->assertSame( 0, $reads );
	}
}
