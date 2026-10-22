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
 * The fix for the .htaccess refresh must use the nonce action
 * `zerospam-refresh-htaccess` in the `_wpnonce` field (for example
 * check_admin_referer( 'zerospam-refresh-htaccess' ) behind a
 * manage_options check); test_refresh_htaccess_runs_for_an_admin_with_a_nonce
 * pins that contract.
 *
 * Utilities::refresh_htaccess() starts by reading the `block_method` setting,
 * which reads the zero-spam-settings option; a spy on that option shows
 * whether it ran.
 *
 * @covers \ZeroSpam\Core\Admin\Dashboard::admin_init
 */
class Test_Dashboard extends TestCase {

	/**
	 * Number of times refresh_htaccess() started.
	 *
	 * @var int
	 */
	private int $refreshes = 0;

	/**
	 * Installs the spy and the admin screen.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$this->refreshes = 0;
		add_filter(
			'pre_option_zero-spam-settings',
			function ( $pre ) {
				++$this->refreshes;

				return $pre;
			}
		);
	}

	/**
	 * Runs admin_init; a refused request may end in wp_die().
	 */
	private function run_admin_init(): void {
		try {
			( new Dashboard() )->admin_init();
		} catch ( WPDieException $e ) {
			// A nonce or capability failure; the spy count is what matters.
			unset( $e );
		}
	}

	/**
	 * A subscriber cannot make the plugin rewrite .htaccess with a bare query flag.
	 *
	 * @group known-defect
	 */
	public function test_refresh_htaccess_requires_capability_and_nonce(): void {
		$this->as_role( 'subscriber' );
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$this->run_admin_init();

		$this->assertSame( 0, $this->refreshes, 'refresh_htaccess() ran for a subscriber without a nonce.' );
	}

	/**
	 * An administrator without a nonce cannot trigger it either.
	 *
	 * @group known-defect
	 */
	public function test_refresh_htaccess_requires_a_nonce_for_admins(): void {
		$this->as_role( 'administrator' );
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$this->run_admin_init();

		$this->assertSame( 0, $this->refreshes, 'refresh_htaccess() ran for an administrator without a nonce.' );
	}

	/**
	 * An administrator with a valid nonce does refresh .htaccess.
	 */
	public function test_refresh_htaccess_runs_for_an_admin_with_a_nonce(): void {
		$this->as_role( 'administrator' );
		$nonce                                 = wp_create_nonce( 'zerospam-refresh-htaccess' );
		$_GET['_wpnonce']                      = $nonce;
		$_REQUEST['_wpnonce']                  = $nonce;
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$this->run_admin_init();

		$this->assertGreaterThan( 0, $this->refreshes, 'refresh_htaccess() did not run for an administrator with a valid nonce.' );
	}

	/**
	 * Without the flag nothing runs.
	 */
	public function test_admin_init_without_flag_does_nothing(): void {
		$this->as_role( 'administrator' );

		$this->run_admin_init();

		$this->assertSame( 0, $this->refreshes );
	}
}
