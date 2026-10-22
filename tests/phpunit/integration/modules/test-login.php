<?php
/**
 * Tests for the Login module's intent cookie.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Modules\Login\Login;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * The login intent token is set as a hardened cookie, and only when it can be.
 *
 * @covers \ZeroSpam\Modules\Login\Login::set_intent_token
 */
class Test_Login extends TestCase {

	/**
	 * Marks the request as a wp-login.php request (restored after the test).
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_actions']['login_init'] = 1;
	}

	/**
	 * Runs set_intent_token() on a fresh module.
	 */
	private function set_intent_token(): void {
		( new Login() )->set_intent_token();
	}

	/**
	 * The intent cookie is HttpOnly, SameSite=Lax, short-lived, and backed by a transient.
	 */
	public function test_intent_cookie_is_httponly_and_samesite_lax(): void {
		$this->set_intent_token();

		$this->assertCount( 1, Stubs::$cookies );
		$cookie = Stubs::$cookies[0];
		$this->assertSame( 'zerospam_login_intent', $cookie['name'] );
		$this->assertSame( 32, strlen( $cookie['value'] ) );
		$this->assertTrue( $cookie['options']['httponly'] );
		$this->assertSame( 'Lax', $cookie['options']['samesite'] );
		$this->assertFalse( $cookie['options']['secure'], 'Not marked secure on plain HTTP.' );
		$this->assertSame( COOKIEPATH, $cookie['options']['path'] );
		$this->assertSame( COOKIE_DOMAIN, $cookie['options']['domain'] );
		$this->assertEqualsWithDelta( time() + 10 * MINUTE_IN_SECONDS, $cookie['options']['expires'], 5 );

		$this->assertNotFalse( get_transient( 'zerospam_login_intent_' . $cookie['value'] ) );
	}

	/**
	 * Over HTTPS the intent cookie is marked secure.
	 */
	public function test_intent_cookie_is_secure_over_https(): void {
		$_SERVER['HTTPS'] = 'on';

		$this->set_intent_token();

		$this->assertTrue( Stubs::$cookies[0]['options']['secure'] ?? null );
	}

	/**
	 * When headers are already sent (WP-CLI, output started), setcookie() is
	 * not called (it would warn) and no token or transient is created.
	 */
	public function test_no_intent_cookie_once_headers_are_sent(): void {
		Stubs::$headers_sent = true;

		$queries = $this->capture_queries(
			function (): void {
				$this->set_intent_token();
			}
		);

		$this->assertSame( array(), Stubs::$cookies );
		$this->assertSame( array(), preg_grep( '/zerospam_login_intent_/', $queries ), 'No intent transient is stored when the cookie cannot be sent.' );
	}

	/**
	 * Outside wp-login.php nothing is set.
	 */
	public function test_no_intent_cookie_outside_the_login_page(): void {
		unset( $GLOBALS['wp_actions']['login_init'] );

		$this->set_intent_token();

		$this->assertSame( array(), Stubs::$cookies );
	}
}
