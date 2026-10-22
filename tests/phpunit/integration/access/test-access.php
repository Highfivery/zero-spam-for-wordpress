<?php
/**
 * Tests for the Access firewall.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * Who gets checked, rescue mode, and IP blocks.
 *
 * @covers \ZeroSpam\Core\Access
 */
class Test_Access extends TestCase {

	/**
	 * The rescue cookie value for the test key.
	 */
	private function rescue_token(): string {
		return hash_hmac( 'sha256', 'zerospam_rescue', ZEROSPAM_RESCUE_KEY );
	}

	/**
	 * An anonymous front-end visitor is checked.
	 */
	public function test_process_checks_anonymous_visitors(): void {
		$this->assertTrue( Access::process() );
	}

	/**
	 * Requests without a URI (cron, CLI) are not checked.
	 */
	public function test_process_skips_requests_without_uri(): void {
		$_SERVER['REQUEST_URI'] = '';

		$this->assertFalse( Access::process() );
	}

	/**
	 * Favicon requests are not checked.
	 */
	public function test_process_skips_favicon(): void {
		$this->set_request( '/favicon.ico' );

		$this->assertFalse( Access::process() );
	}

	/**
	 * Logged-in users are not checked.
	 */
	public function test_process_skips_logged_in_users(): void {
		$this->as_role( 'subscriber' );

		$this->assertFalse( Access::process() );
	}

	/**
	 * Whitelisted IPs are not checked.
	 */
	public function test_process_skips_whitelisted_ips(): void {
		$this->set_setting( 'ip_whitelist', "192.0.2.1\n" . self::DEFAULT_IP, 'settings' );

		$this->assertFalse( Access::process() );
	}

	/**
	 * Admin page loads are not checked, whether or not AJAX is ignored.
	 */
	public function test_process_skips_admin_pages(): void {
		$this->admin_screen();

		$this->assertFalse( Access::process() );
		$this->assertFalse( Access::process( true ) );
	}

	/**
	 * Anonymous admin-ajax.php requests are checked, unless the caller ignores AJAX.
	 */
	public function test_process_checks_anonymous_admin_ajax(): void {
		$this->set_request( '/wp-admin/admin-ajax.php?action=zerospam_test' );
		$this->admin_screen();
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertTrue( Access::process() );
		$this->assertFalse( Access::process( true ) );
	}

	/**
	 * The right rescue key bypasses checks and sets the rescue cookie.
	 */
	public function test_rescue_key_bypasses_checks(): void {
		$this->set_request( '/wp-admin/?zerospam_rescue=' . rawurlencode( ZEROSPAM_RESCUE_KEY ) );

		$this->assertFalse( Access::process() );
		$this->assertSame( $this->rescue_token(), $_COOKIE[ Access::RESCUE_COOKIE ] ?? null );

		$this->assertCount( 1, Stubs::$cookies );
		$cookie = Stubs::$cookies[0];
		$this->assertSame( Access::RESCUE_COOKIE, $cookie['name'] );
		$this->assertSame( $this->rescue_token(), $cookie['value'], 'The cookie carries the raw key instead of the HMAC token.' );
		$this->assertTrue( $cookie['options']['httponly'] );
		$this->assertSame( 'Lax', $cookie['options']['samesite'] );
		$this->assertFalse( $cookie['options']['secure'], 'Not marked secure on plain HTTP.' );
		$this->assertEqualsWithDelta( time() + HOUR_IN_SECONDS, $cookie['options']['expires'], 5 );
	}

	/**
	 * Over HTTPS the rescue cookie is marked secure.
	 */
	public function test_rescue_cookie_is_secure_over_https(): void {
		$this->set_request( '/?zerospam_rescue=' . rawurlencode( ZEROSPAM_RESCUE_KEY ) );
		$_SERVER['HTTPS'] = 'on';

		$this->assertFalse( Access::process() );
		$this->assertTrue( Stubs::$cookies[0]['options']['secure'] ?? null );
	}

	/**
	 * When headers are already sent, no cookie is attempted but the request is still rescued.
	 */
	public function test_rescue_key_without_headers_sets_no_cookie(): void {
		Stubs::$headers_sent = true;
		$this->set_request( '/?zerospam_rescue=' . rawurlencode( ZEROSPAM_RESCUE_KEY ) );

		$this->assertFalse( Access::process() );
		$this->assertSame( array(), Stubs::$cookies );
	}

	/**
	 * A wrong rescue key changes nothing.
	 */
	public function test_wrong_rescue_key_is_ignored(): void {
		$this->set_request( '/?zerospam_rescue=guess' );

		$this->assertTrue( Access::process() );
		$this->assertArrayNotHasKey( Access::RESCUE_COOKIE, $_COOKIE );
		$this->assertSame( array(), Stubs::$cookies );
	}

	/**
	 * A valid rescue cookie keeps rescue mode on.
	 */
	public function test_valid_rescue_cookie_bypasses_checks(): void {
		$_COOKIE[ Access::RESCUE_COOKIE ] = $this->rescue_token();

		$this->assertFalse( Access::process() );
	}

	/**
	 * A tampered rescue cookie is ignored.
	 */
	public function test_tampered_rescue_cookie_is_ignored(): void {
		$_COOKIE[ Access::RESCUE_COOKIE ] = substr( $this->rescue_token(), 0, -1 ) . 'x';

		$this->assertTrue( Access::process() );
	}

	/**
	 * Block records and whether they block now.
	 *
	 * @return array<string, array{0: string, 1: int, 2: int|null, 3: bool}>
	 */
	public static function data_block_records(): array {
		return array(
			'permanent'                => array( 'permanent', -HOUR_IN_SECONDS, null, true ),
			'permanent, starts later'  => array( 'permanent', DAY_IN_SECONDS, null, false ),
			'temporary, active'        => array( 'temporary', -HOUR_IN_SECONDS, DAY_IN_SECONDS, true ),
			'temporary, expired'       => array( 'temporary', -DAY_IN_SECONDS, -HOUR_IN_SECONDS, false ),
			'temporary, starts later'  => array( 'temporary', HOUR_IN_SECONDS, DAY_IN_SECONDS, false ),
			'temporary without an end' => array( 'temporary', -HOUR_IN_SECONDS, null, false ),
		);
	}

	/**
	 * Permanent, expired and future blocks.
	 *
	 * @dataProvider data_block_records
	 *
	 * @param string   $type     Block type.
	 * @param int      $start    Start, seconds from now.
	 * @param int|null $end      End, seconds from now.
	 * @param bool     $expected Whether the IP is blocked now.
	 */
	public function test_check_blocked( string $type, int $start, ?int $end, bool $expected ): void {
		$this->block(
			self::DEFAULT_IP,
			$type,
			wp_date( 'Y-m-d H:i:s', time() + $start ),
			null === $end ? null : wp_date( 'Y-m-d H:i:s', time() + $end )
		);

		$checks = ( new Access() )->check_blocked( array(), self::DEFAULT_IP, array() );

		$this->assertSame( $expected, ! empty( $checks['blocked']['blocked'] ) );
	}

	/**
	 * An IP without a block record is not blocked.
	 */
	public function test_check_blocked_without_record(): void {
		$checks = ( new Access() )->check_blocked( array(), self::DEFAULT_IP, array() );

		$this->assertFalse( $checks['blocked'] );
	}

	/**
	 * A blocked visitor gets a 403 and the block is logged.
	 */
	public function test_run_block_check_dies_with_403_and_logs(): void {
		$this->set_setting( 'log_blocked_ips', 'enabled', 'settings' );
		$this->block( self::DEFAULT_IP );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		try {
			$access->run_block_check();
			$this->fail( 'A blocked visitor was let through.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode() );
			$this->assertStringContainsString( 'blocked', $e->getMessage() );
		}

		$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'blocked' ) ) );
	}

	/**
	 * Blocks are not logged when logging is off.
	 */
	public function test_run_block_check_does_not_log_when_disabled(): void {
		$this->block( self::DEFAULT_IP );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		$this->expectException( WPDieException::class );
		try {
			$access->run_block_check();
		} finally {
			$this->assertSame( 0, $this->count_log_rows() );
		}
	}

	/**
	 * The redirect handler sends blocked visitors to the configured URL.
	 */
	public function test_run_block_check_redirects_when_configured(): void {
		$this->set_setting( 'block_handler', 'redirect', 'settings' );
		$this->set_setting( 'blocked_redirect_url', 'https://example.com/blocked', 'settings' );
		$this->block( self::DEFAULT_IP );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		$this->expect_redirect();
		$this->expectExceptionMessage( 'https://example.com/blocked' );

		$access->run_block_check();
	}

	/**
	 * A visitor who is not blocked passes.
	 */
	public function test_run_block_check_lets_others_through(): void {
		$this->set_setting( 'log_blocked_ips', 'enabled', 'settings' );
		$this->block( '192.0.2.99' );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		$access->run_block_check();

		$this->assertSame( 0, $this->count_log_rows() );
	}
}
