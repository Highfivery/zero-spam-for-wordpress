<?php
/**
 * Tests for the emailed rescue link on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Core\Rescue_Link;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * The rescue cookie shares the network's cookie path and domain, so it must
 * only work on the site whose link set it.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Access
 */
class Test_Rescue_Link_Network extends TestCase {

	/**
	 * A site besides the main site.
	 *
	 * @var int
	 */
	private static int $site = 0;

	/**
	 * Creates the site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site = self::create_site_with_tables( $factory );
	}

	/**
	 * A rescue link used on one site does not bypass the checks on another.
	 */
	public function test_rescue_on_one_site_does_not_open_the_others(): void {
		$this->mock_http( '#wp-cron\.php#', '' );
		add_filter( 'zerospam_rescue_email_via_cron', '__return_true' );
		switch_to_blog( self::$site );
		update_option( 'admin_email', 'site-admin@example.com' );

		// Ask for and use a link on the site, as its administrator would.
		$this->set_request( '/wp-admin/admin-post.php' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			Rescue_Link::NONCE_FIELD => wp_create_nonce( Rescue_Link::NONCE_ACTION ),
			Rescue_Link::EMAIL_FIELD => (string) get_option( 'admin_email' ),
		);
		reset_phpmailer_instance();
		try {
			Rescue_Link::handle_request();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[ Rescue_Link::SEND_HOOK ] ?? array() as $event ) {
				wp_unschedule_event( (int) $timestamp, Rescue_Link::SEND_HOOK, $event['args'] );
				do_action_ref_array( Rescue_Link::SEND_HOOK, $event['args'] );
			}
		}
		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertIsObject( $mail, 'No rescue email was sent.' );
		$this->assertSame( 1, preg_match( '/zerospam_rescue_token=([a-f0-9]{64})/', $mail->body, $matches ) );

		$this->set_request( '/' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array( Rescue_Link::TOKEN_PARAM => $matches[1] );
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RedirectException( (string) $location, 302 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			}
		);
		try {
			( new Access() )->init();
			$this->fail( 'The link did not work on its own site.' );
		} catch ( RedirectException $e ) {
			unset( $e );
		}
		$_POST = array();
		$this->assertCount( 1, Stubs::$cookies );
		$cookie = Stubs::$cookies[0]['value'];

		// On its own site the cookie bypasses the checks.
		$this->set_request( '/' );
		$_COOKIE[ Access::RESCUE_COOKIE ] = $cookie;
		$this->assertFalse( Access::process(), 'The cookie does not work on the site that set it.' );
		restore_current_blog();

		// On the main site (same cookie path and domain) it does not.
		$this->set_request( '/' );
		$_COOKIE[ Access::RESCUE_COOKIE ] = $cookie;
		$this->assertTrue( Access::process(), 'A rescue on one site opened every site of the network.' );
	}
}
