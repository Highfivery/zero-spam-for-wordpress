<?php
/**
 * Tests for the emailed rescue link.
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
 * The blocked page's rescue form, the request handler, the email and the link.
 *
 * @covers \ZeroSpam\Core\Rescue_Link
 * @covers \ZeroSpam\Core\Access
 */
class Test_Rescue_Link extends TestCase {

	/**
	 * Second visitor IP (TEST-NET-2).
	 */
	const OTHER_IP = '198.51.100.7';

	/**
	 * Clears the mail spy and the rate limits.
	 */
	public function set_up(): void {
		parent::set_up();

		reset_phpmailer_instance();
		delete_transient( Rescue_Link::SITE_LIMIT_TRANSIENT );
		$GLOBALS['pagenow'] = 'index.php';

		// The test framework defines DISABLE_WP_CRON; send through WP-Cron as a normal site does.
		add_filter( 'zerospam_rescue_email_via_cron', '__return_true' );

		// Scheduling the email spawns WP-Cron with a non-blocking loopback request.
		$this->mock_http( '#wp-cron\.php#', '' );
	}

	/**
	 * Restores the request globals the tests change.
	 */
	public function tear_down(): void {
		$_POST              = array();
		$_REQUEST           = array();
		$GLOBALS['pagenow'] = 'index.php';
		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Submits the blocked page's rescue form and returns the response.
	 *
	 * The email is sent by a WP-Cron event; unless $run_cron is false, this
	 * runs the scheduled rescue events afterwards, as WP-Cron would.
	 *
	 * @param string      $email    Submitted address.
	 * @param string      $ip       Visitor IP.
	 * @param string|null $nonce    Nonce; a valid one when null.
	 * @param bool        $run_cron Whether to run the scheduled rescue events.
	 * @return array{message: string, code: int}
	 */
	private function submit( string $email, string $ip = self::DEFAULT_IP, ?string $nonce = null, bool $run_cron = true ): array {
		$this->set_request( '/wp-admin/admin-post.php', $ip );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$_POST    = array(
			'action'                 => Rescue_Link::ACTION,
			Rescue_Link::NONCE_FIELD => $nonce ?? wp_create_nonce( Rescue_Link::NONCE_ACTION ),
			Rescue_Link::EMAIL_FIELD => wp_slash( $email ),
		);
		$_REQUEST = $_POST;

		try {
			Rescue_Link::handle_request();
		} catch ( WPDieException $e ) {
			if ( $run_cron ) {
				$this->run_rescue_events();
			}

			return array(
				'message' => $e->getMessage(),
				'code'    => (int) $e->getCode(),
			);
		}

		$this->fail( 'The rescue request handler returned instead of ending the request.' );
	}

	/**
	 * Scheduled rescue email events.
	 *
	 * @return array<int, array{timestamp: int, args: array}>
	 */
	private function rescue_events(): array {
		$events = array();
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[ Rescue_Link::SEND_HOOK ] ?? array() as $event ) {
				$events[] = array(
					'timestamp' => (int) $timestamp,
					'args'      => $event['args'],
				);
			}
		}

		return $events;
	}

	/**
	 * Runs and unschedules the scheduled rescue email events, as WP-Cron would.
	 */
	private function run_rescue_events(): void {
		foreach ( $this->rescue_events() as $event ) {
			wp_unschedule_event( $event['timestamp'], Rescue_Link::SEND_HOOK, $event['args'] );
			do_action_ref_array( Rescue_Link::SEND_HOOK, $event['args'] );
		}
	}

	/**
	 * Emails sent so far.
	 *
	 * @return array<int, object>
	 */
	private function sent(): array {
		return array_map(
			static fn ( array $mail ): object => (object) $mail,
			tests_retrieve_phpmailer_instance()->mock_sent
		);
	}

	/**
	 * Rescue token from a sent email.
	 *
	 * @param int $index Email index.
	 */
	private function token_from_email( int $index = 0 ): string {
		$sent = $this->sent();
		$this->assertArrayHasKey( $index, $sent, 'No rescue email was sent.' );
		$this->assertSame( 1, preg_match( '/[?&]' . Rescue_Link::TOKEN_PARAM . '=([a-f0-9]{64})\b/', $sent[ $index ]->body, $matches ), 'The email has no rescue link.' );

		return $matches[1];
	}

	/**
	 * Makes wp_redirect() throw a RedirectException.
	 */
	private function catch_redirects(): void {
		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the caller, never rendered.
			},
			1,
			2
		);
	}

	/**
	 * Opens a rescue link (GET) and returns the page it shows, or null when the request continues.
	 *
	 * @param string $token Token.
	 * @param string $ip    Visitor IP.
	 * @return array{message: string, code: int}|null
	 */
	private function open_link( string $token, string $ip = self::DEFAULT_IP ): ?array {
		$this->set_request( '/?' . Rescue_Link::TOKEN_PARAM . '=' . $token, $ip );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->catch_redirects();

		try {
			( new Access() )->init();
		} catch ( WPDieException $e ) {
			return array(
				'message' => $e->getMessage(),
				'code'    => (int) $e->getCode(),
			);
		} catch ( RedirectException $e ) {
			$this->fail( 'Opening the link (GET) used the token and redirected to ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Presses "Let me back in" (POST of the token) and returns where it redirected, or null.
	 *
	 * @param string $token Token.
	 * @param string $ip    Visitor IP.
	 */
	private function confirm_link( string $token, string $ip = self::DEFAULT_IP ): ?string {
		$this->set_request( '/', $ip );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array( Rescue_Link::TOKEN_PARAM => $token );
		$_REQUEST                  = $_POST;
		$this->catch_redirects();

		try {
			( new Access() )->init();
		} catch ( RedirectException $e ) {
			return $e->getMessage();
		} finally {
			$_POST    = array();
			$_REQUEST = array();
		}

		return null;
	}

	/**
	 * Rescue log rows, newest last.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rescue_log_rows(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name only.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$this->log_table()}` WHERE log_type = %s ORDER BY log_id ASC", 'rescue' ), ARRAY_A );
	}

	/**
	 * The site administrator's address gets one email with a rescue link, in any letter case.
	 */
	public function test_admin_email_sends_one_link(): void {
		$response = $this->submit( strtoupper( (string) get_option( 'admin_email' ) ) );

		$this->assertSame( 200, $response['code'] );
		$this->assertStringContainsString( 'If that address belongs to a site administrator, a rescue link has been sent.', $response['message'] );
		$this->assertStringContainsString( 'It can take a few minutes to arrive.', $response['message'] );
		$this->assertSame( 'Check your email', $this->parse( $response['message'] )->evaluate( 'normalize-space(//h1)' ) );

		$sent = $this->sent();
		$this->assertCount( 1, $sent );
		$this->assertSame( get_option( 'admin_email' ), $sent[0]->to[0][0] );
		$this->assertStringContainsString( 'text/plain', $sent[0]->header );

		$token = $this->token_from_email();
		$this->assertStringContainsString( home_url( '/?' . Rescue_Link::TOKEN_PARAM . '=' . $token ), $sent[0]->body );
		$this->assertStringContainsString( '15 minutes', $sent[0]->body );
		$this->assertStringContainsString( 'Settings → Zero Spam → Settings', $sent[0]->body );
		$this->assertStringContainsString( self::DEFAULT_IP, $sent[0]->body, 'The email does not say which IP asked for it.' );
		$this->assertStringNotContainsString( '@', home_url( '/?' . Rescue_Link::TOKEN_PARAM . '=' . $token ) );
	}

	/**
	 * The email is sent by a WP-Cron event, never during the request, so the answer takes no mail-server time.
	 *
	 * Requests for other addresses schedule the same kind of event, which sends nothing.
	 */
	public function test_email_is_sent_by_cron_not_during_the_request(): void {
		$sent_answer = $this->submit( (string) get_option( 'admin_email' ), self::DEFAULT_IP, null, false );

		$this->assertSame( array(), $this->sent(), 'The email was sent during the request.' );
		$this->assertCount( 1, $this->rescue_events() );

		$other_answer = $this->submit( 'nobody@example.com', self::OTHER_IP, null, false );
		$this->assertSame( $sent_answer, $other_answer );
		$this->assertCount( 2, $this->rescue_events(), 'A non-administrator address does different work, which shows in the response time.' );
		$this->assertSame( array(), $this->sent() );

		$this->run_rescue_events();
		$this->assertCount( 1, $this->sent() );
		$this->assertSame( get_option( 'admin_email' ), $this->sent()[0]->to[0][0] );

		// An event runs once: its payload is gone.
		$this->assertSame( array(), $this->rescue_events() );
		do_action( Rescue_Link::SEND_HOOK, str_repeat( 'a', 64 ) );
		$this->assertCount( 1, $this->sent() );

		// The scheduled payload never holds the token.
		global $wpdb;
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' . Rescue_Link::JOB_TRANSIENT_PREFIX ) . '%' ) ) );
	}

	/**
	 * Without WP-Cron the email is sent on `shutdown`, never during the request.
	 */
	public function test_email_is_sent_on_shutdown_without_cron(): void {
		add_filter( 'zerospam_rescue_email_via_cron', '__return_false', 20 );

		$this->submit( (string) get_option( 'admin_email' ), self::DEFAULT_IP, null, false );

		$this->assertSame( array(), $this->sent(), 'The email was sent during the request.' );
		$this->assertSame( array(), $this->rescue_events() );
		$this->assertNotFalse( has_action( 'shutdown', array( Rescue_Link::class, 'send_queued' ) ) );

		Rescue_Link::send_queued();
		$this->assertCount( 1, $this->sent() );

		Rescue_Link::send_queued();
		$this->assertCount( 1, $this->sent(), 'The queue sent twice.' );
	}

	/**
	 * Both rate counters are written for any address, so the work does not show whether it is an administrator's.
	 */
	public function test_rate_counters_are_written_for_any_address(): void {
		$this->submit( 'nobody@example.com' );

		$address = get_transient( Rescue_Link::RECIPIENT_LIMIT_TRANSIENT_PREFIX . hash( 'sha256', 'nobody@example.com' ) );
		$site    = get_transient( Rescue_Link::SITE_LIMIT_TRANSIENT );

		$this->assertIsArray( $address, 'The per-address counter was not written for a non-administrator address.' );
		$this->assertIsArray( $site, 'The site counter was not written for a non-administrator address.' );
		$this->assertSame( 0, $address['count'], 'A request that sends nothing was counted.' );
		$this->assertSame( 0, $site['count'] );
	}

	/**
	 * A request without a usable visitor IP is refused (no shared "unknown" bucket).
	 */
	public function test_request_without_an_ip_is_refused(): void {
		$response = $this->submit( (string) get_option( 'admin_email' ), '' );

		$this->assertSame( 200, $response['code'] );
		$this->assertSame( array(), $this->rescue_events() );
		$this->assertSame( array(), $this->sent() );
	}

	/**
	 * At most three emails per hour to one address, so one inbox cannot use up the site's quota.
	 */
	public function test_recipient_limit(): void {
		$admin = (string) get_option( 'admin_email' );
		self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'second-admin@example.com',
			)
		);

		$first = $this->submit( $admin, '192.0.2.1' );
		$this->submit( strtoupper( $admin ), '192.0.2.2' );
		$this->submit( $admin, '192.0.2.3' );
		$this->assertCount( 3, $this->sent() );

		$fourth = $this->submit( $admin, '192.0.2.4' );
		$this->assertCount( 3, $this->sent(), 'A fourth email to one address was sent within the hour.' );
		$this->assertSame( $first, $fourth );

		$this->submit( 'second-admin@example.com', '192.0.2.5' );
		$this->assertCount( 4, $this->sent(), 'The limit of one address blocked another administrator.' );
	}

	/**
	 * The zerospam_rescue_form_enabled filter hides the form and stops sending.
	 */
	public function test_rescue_form_can_be_turned_off(): void {
		add_filter( 'zerospam_rescue_form_enabled', '__return_false' );
		$this->block( self::DEFAULT_IP );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );
		try {
			$access->run_block_check();
			$this->fail( 'A blocked visitor was let through.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, (int) $e->getCode() );
			$this->assertStringContainsString( 'blocked', $e->getMessage() );
			$this->assertStringNotContainsString( '<details', $e->getMessage() );
			$this->assertStringNotContainsString( '<form', $e->getMessage() );
		}

		$response = $this->submit( (string) get_option( 'admin_email' ) );
		$this->assertSame( 200, $response['code'] );
		$this->assertSame( array(), $this->sent(), 'A disabled form still sent an email.' );
	}

	/**
	 * Opening a link (GET, as email scanners do) shows a "Let me back in" button and leaves the token unused.
	 */
	public function test_opening_the_link_shows_a_button_and_keeps_the_token(): void {
		$this->block( self::DEFAULT_IP );
		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();
		$key   = Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token );

		$first = $this->open_link( $token );
		$this->open_link( $token, self::OTHER_IP );

		$this->assertIsArray( $first, 'Opening the link showed no page.' );
		$this->assertSame( 200, $first['code'] );
		$this->assertNotFalse( get_transient( $key ), 'Opening the link used the token.' );
		$this->assertSame( array(), Stubs::$cookies );
		$this->assertSame( array(), $this->rescue_log_rows() );

		$xpath = $this->parse( $first['message'] );
		$form  = $xpath->query( '//form' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $form );
		$this->assertSame( 'post', strtolower( $form->getAttribute( 'method' ) ) );
		$this->assertSame( home_url( '/' ), $form->getAttribute( 'action' ) );
		$this->assertSame( 1, $xpath->query( '//form//input[@type="hidden"][@name="' . Rescue_Link::TOKEN_PARAM . '"][@value="' . $token . '"]' )->length );
		$this->assertSame( 'Let me back in', $xpath->evaluate( 'normalize-space(//form//button[@type="submit"])' ) );
		$this->assertSame( 'Let me back in', $xpath->evaluate( 'normalize-space(//h1)' ) );
		$this->assertStringContainsString( 'outline: 2px solid', $first['message'] );

		$this->assertSame( wp_login_url(), $this->confirm_link( $token ) );
		$this->assertFalse( get_transient( $key ) );
	}

	/**
	 * A POST with the token only in the URL is treated like opening the link.
	 */
	public function test_post_without_the_token_field_does_not_use_it(): void {
		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();

		$this->set_request( '/?' . Rescue_Link::TOKEN_PARAM . '=' . $token );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array();
		$this->catch_redirects();
		try {
			( new Access() )->init();
			$this->fail( 'No page was shown.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 200, (int) $e->getCode() );
		}

		$this->assertNotFalse( get_transient( Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) ) );
		$this->assertSame( array(), Stubs::$cookies );
	}

	/**
	 * Any user who can manage options (and, on a network, any super admin) can ask.
	 */
	public function test_manage_options_user_email_sends_a_link(): void {
		self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'owner@example.com',
			)
		);

		$this->submit( 'Owner@Example.com' );

		$this->assertCount( 1, $this->sent() );
		$this->assertSame( 'owner@example.com', $this->sent()[0]->to[0][0] );
	}

	/**
	 * Addresses that are not an administrator's get no email and the same answer.
	 */
	public function test_other_addresses_send_nothing_and_respond_identically(): void {
		self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'editor@example.com',
			)
		);

		$editor  = $this->submit( 'editor@example.com' );
		$unknown = $this->submit( 'nobody@example.com', self::OTHER_IP );

		$this->assertSame( array(), $this->sent() );

		$admin = $this->submit( (string) get_option( 'admin_email' ), '192.0.2.44' );
		$this->assertCount( 1, $this->sent() );

		$this->assertSame( $admin, $editor, 'A non-administrator address gets a different answer, which reveals who the administrators are.' );
		$this->assertSame( $admin, $unknown );
	}

	/**
	 * At most ten emails per hour per site, whichever administrators they go to.
	 */
	public function test_site_send_limit(): void {
		$addresses = array( (string) get_option( 'admin_email' ) );
		for ( $i = 1; $i <= 3; $i++ ) {
			$addresses[] = "admin{$i}@example.com";
			self::factory()->user->create(
				array(
					'role'       => 'administrator',
					'user_email' => "admin{$i}@example.com",
				)
			);
		}

		$ip    = 0;
		$first = null;
		foreach ( $addresses as $address ) {
			for ( $n = 0; $n < Rescue_Link::RECIPIENT_LIMIT; $n++ ) {
				if ( $ip >= Rescue_Link::SITE_LIMIT ) {
					break 2;
				}
				$response = $this->submit( $address, '192.0.2.' . ( ++$ip ) );
				$first    = $first ?? $response;
			}
		}
		$this->assertCount( 10, $this->sent() );

		$eleventh = $this->submit( 'admin3@example.com', '192.0.2.' . ( ++$ip ) );

		$this->assertCount( 10, $this->sent(), 'An eleventh rescue email was sent within the hour.' );
		$this->assertSame( $first, $eleventh );
	}

	/**
	 * At most five requests per hour per IP, whatever the address.
	 */
	public function test_ip_request_limit(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->submit( "nobody{$i}@example.com" );
		}

		$sixth = $this->submit( (string) get_option( 'admin_email' ) );

		$this->assertSame( array(), $this->sent(), 'A sixth request from one IP sent an email.' );
		$this->assertSame( 200, $sixth['code'] );
		$this->assertStringContainsString( 'If that address belongs to a site administrator, a rescue link has been sent.', $sixth['message'] );

		$this->submit( (string) get_option( 'admin_email' ), self::OTHER_IP );
		$this->assertCount( 1, $this->sent(), 'The limit of one IP blocked another.' );
	}

	/**
	 * A request without a valid nonce sends nothing.
	 */
	public function test_invalid_nonce_sends_nothing(): void {
		$response = $this->submit( (string) get_option( 'admin_email' ), self::DEFAULT_IP, 'not-a-nonce' );

		$this->assertSame( array(), $this->sent() );
		$this->assertSame( 403, $response['code'] );
		$this->assertStringContainsString( 'name="' . Rescue_Link::EMAIL_FIELD . '"', $response['message'], 'The form is not shown again.' );
	}

	/**
	 * An address that is not an email address gets the form back with an error tied to the field.
	 */
	public function test_invalid_address_shows_an_associated_error(): void {
		$response = $this->submit( 'not an email' );

		$this->assertSame( array(), $this->sent() );
		$this->assertSame( 400, $response['code'] );

		$xpath = $this->parse( $response['message'] );
		$input = $xpath->query( '//input[@id="zerospam-rescue-email"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertSame( 'true', $input->getAttribute( 'aria-invalid' ) );

		$error = $xpath->query( '//*[@id="zerospam-rescue-email-error"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $error );
		$this->assertSame( 'alert', $error->getAttribute( 'role' ) );
		$this->assertTrue( $input->hasAttribute( 'autofocus' ), 'Focus is not moved to the field with the error.' );
		$this->assertSame( 1, $xpath->query( '//h1' )->length );
		$this->assertStringContainsString( 'zerospam-rescue-email-error', $input->getAttribute( 'aria-describedby' ) );
		$this->assertNotSame( '', $xpath->evaluate( 'normalize-space(//*[@id="zerospam-rescue-email-error"])' ) );
		$this->assertSame( 'not an email', $input->getAttribute( 'value' ) );
	}

	/**
	 * Only a hash of the token is stored, for 15 minutes, with the requester's IP.
	 */
	public function test_only_the_token_hash_is_stored(): void {
		global $wpdb;

		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();

		$stored = get_transient( Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) );
		$this->assertIsArray( $stored );
		$this->assertSame( self::DEFAULT_IP, $stored['ip'] );

		$timeout = (int) get_option( '_transient_timeout_' . Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) );
		if ( ! wp_using_ext_object_cache() ) {
			$this->assertEqualsWithDelta( time() + 15 * MINUTE_IN_SECONDS, $timeout, 5 );
		}

		$like = '%' . $wpdb->esc_like( $token ) . '%';
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s", $like, $like ) ), 'The raw token is stored.' );
	}

	/**
	 * A valid link sets the one-hour rescue cookie, logs its use and goes to the login page.
	 */
	public function test_valid_link_grants_rescue_and_redirects_to_login(): void {
		$this->block( self::DEFAULT_IP );
		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();

		$location = $this->confirm_link( $token );

		$this->assertSame( wp_login_url(), $location );

		$this->assertCount( 1, Stubs::$cookies );
		$cookie = Stubs::$cookies[0];
		$this->assertSame( Access::RESCUE_COOKIE, $cookie['name'] );
		$this->assertTrue( $cookie['options']['httponly'] );
		$this->assertSame( 'Lax', $cookie['options']['samesite'] );
		$this->assertEqualsWithDelta( time() + HOUR_IN_SECONDS, $cookie['options']['expires'], 5 );
		$this->assertStringNotContainsString( $token, $cookie['value'] );

		// The next request (here: the login page) carries the cookie and is not checked.
		$this->set_request( '/wp-login.php' );
		$_COOKIE[ Access::RESCUE_COOKIE ] = $cookie['value'];
		$this->assertFalse( Access::process( true ) );

		$rows = $this->rescue_log_rows();
		$this->assertCount( 1, $rows );
		$this->assertStringNotContainsString( $token, (string) wp_json_encode( $rows[0] ), 'The token was logged.' );
	}

	/**
	 * A link works once: a second "Let me back in" is refused, and opening it again shows nothing.
	 */
	public function test_link_is_single_use(): void {
		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();

		$this->assertSame( wp_login_url(), $this->confirm_link( $token ) );
		Stubs::$cookies = array();
		unset( $_COOKIE[ Access::RESCUE_COOKIE ] );

		$this->assertNull( $this->confirm_link( $token ), 'A used link worked again.' );
		$this->assertNull( $this->open_link( $token ), 'A used link still shows the button.' );
		$this->assertSame( array(), Stubs::$cookies );
		$this->assertCount( 1, $this->rescue_log_rows() );
	}

	/**
	 * A link stops working after 15 minutes.
	 */
	public function test_link_expires(): void {
		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();
		$key   = Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token );

		// Age the stored token past its 15 minutes: both the transient timeout and the expiry stored with it.
		$stored            = get_transient( $key );
		$stored['expires'] = time() - 1;
		set_transient( $key, $stored, MINUTE_IN_SECONDS );

		$this->assertNull( $this->open_link( $token ), 'An expired link shows the button.' );
		$this->assertNull( $this->confirm_link( $token ), 'An expired link worked.' );
		$this->assertSame( array(), Stubs::$cookies );

		$this->submit( (string) get_option( 'admin_email' ), self::OTHER_IP );
		$token = $this->token_from_email( 1 );
		if ( ! wp_using_ext_object_cache() ) {
			update_option( '_transient_timeout_' . Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ), time() - 1 );
			$this->assertNull( $this->confirm_link( $token ), 'A link whose transient expired worked.' );
		}
	}

	/**
	 * Links are often opened on another device, so another IP may use one; both IPs are logged.
	 */
	public function test_link_from_another_ip_works_and_logs_both_ips(): void {
		$this->submit( (string) get_option( 'admin_email' ) );
		$token = $this->token_from_email();

		$this->assertSame( wp_login_url(), $this->confirm_link( $token, self::OTHER_IP ) );

		$rows = $this->rescue_log_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( self::OTHER_IP, $rows[0]['user_ip'] );

		$details = json_decode( (string) $rows[0]['submission_data'], true );
		$this->assertSame( self::DEFAULT_IP, $details['requested_ip'] ?? null );
		$this->assertSame( self::OTHER_IP, $details['used_ip'] ?? null );
	}

	/**
	 * Made-up or malformed tokens do nothing.
	 *
	 * @dataProvider data_bad_tokens
	 *
	 * @param string $token Token.
	 */
	public function test_unknown_tokens_are_ignored( string $token ): void {
		$this->assertNull( $this->open_link( rawurlencode( $token ) ) );
		$this->assertNull( $this->confirm_link( $token ) );
		$this->assertSame( array(), Stubs::$cookies );
		$this->assertSame( array(), $this->rescue_log_rows() );
	}

	/**
	 * Tokens that were never issued.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_bad_tokens(): array {
		return array(
			'unknown'   => array( str_repeat( 'ab', 32 ) ),
			'too short' => array( 'abc123' ),
			'not hex'   => array( str_repeat( 'zz', 32 ) ),
			'empty'     => array( '' ),
		);
	}

	/**
	 * A correctly signed link cookie stops working once its hour is over, whatever the browser does.
	 */
	public function test_expired_link_cookie_is_ignored(): void {
		$sign = new ReflectionMethod( Access::class, 'link_cookie_value' );

		$_COOKIE[ Access::RESCUE_COOKIE ] = $sign->invoke( null, time() + MINUTE_IN_SECONDS );
		$this->assertFalse( Access::process(), 'A valid link cookie was refused.' );

		$_COOKIE[ Access::RESCUE_COOKIE ] = $sign->invoke( null, time() - 1 );
		$this->assertTrue( Access::process(), 'An expired link cookie still bypasses the checks.' );
	}

	/**
	 * A tampered rescue-link cookie is ignored.
	 */
	public function test_tampered_link_cookie_is_ignored(): void {
		$this->submit( (string) get_option( 'admin_email' ) );
		$this->confirm_link( $this->token_from_email() );
		$value = Stubs::$cookies[0]['value'];

		$this->set_request( '/' );
		$_COOKIE[ Access::RESCUE_COOKIE ] = substr( $value, 0, -1 ) . ( 'a' === substr( $value, -1 ) ? 'b' : 'a' );
		$this->assertTrue( Access::process() );

		$_COOKIE[ Access::RESCUE_COOKIE ] = $value;
		$this->assertFalse( Access::process() );
	}

	/**
	 * The rescue request is reachable for a blocked IP, and only on admin-post.php.
	 */
	public function test_rescue_request_is_reachable_for_a_blocked_ip(): void {
		$this->block( self::DEFAULT_IP );
		$this->assertNotFalse( has_action( 'admin_post_nopriv_' . Rescue_Link::ACTION ), 'The rescue request handler is not registered for visitors.' );
		$this->assertNotFalse( has_action( 'admin_post_' . Rescue_Link::ACTION ) );

		$this->set_request( '/wp-admin/admin-post.php' );
		$this->admin_screen();
		$GLOBALS['pagenow']        = 'admin-post.php';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'action'                 => Rescue_Link::ACTION,
			Rescue_Link::NONCE_FIELD => wp_create_nonce( Rescue_Link::NONCE_ACTION ),
			Rescue_Link::EMAIL_FIELD => (string) get_option( 'admin_email' ),
		);
		$_REQUEST                  = $_POST;

		$this->assertFalse( Access::process() );
		$this->assertFalse( Access::process( true ) );

		try {
			do_action( 'admin_post_nopriv_' . Rescue_Link::ACTION );
			$this->fail( 'The handler did not answer.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 200, (int) $e->getCode() );
		}

		$this->run_rescue_events();
		$this->assertCount( 1, $this->sent() );
	}

	/**
	 * The action name alone does not exempt a front-end request.
	 */
	public function test_action_parameter_on_the_front_end_is_still_checked(): void {
		$this->set_request( '/?action=' . Rescue_Link::ACTION );

		$this->assertTrue( Access::process() );
	}

	/**
	 * The blocked page keeps the message and 403, with a collapsed, labelled rescue form.
	 */
	public function test_blocked_page_has_a_labelled_rescue_form(): void {
		$this->set_setting( 'blocked_message', '<style>body { display: none; }</style>Custom <strong>blocked</strong> message.<input type="text" name="injected">', 'settings' );
		$this->block( self::DEFAULT_IP );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		try {
			$access->run_block_check();
			$this->fail( 'A blocked visitor was let through.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, (int) $e->getCode() );
			$html = $e->getMessage();
		}

		$this->assertStringContainsString( 'Custom <strong>blocked</strong> message.', $html );
		$this->assertStringNotContainsString( '<style>body', $html, 'The blocked message may now carry <style>.' );
		$this->assertStringNotContainsString( 'name="injected"', $html, 'The blocked message may now carry form controls.' );

		$xpath   = $this->parse( $html );
		$details = $xpath->query( '//details' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $details );
		$this->assertFalse( $details->hasAttribute( 'open' ), 'The site owner section is not collapsed.' );
		$this->assertSame( 1, $xpath->query( '//details/summary' )->length );

		$form = $xpath->query( '//details//form' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $form );
		$this->assertSame( 'post', strtolower( $form->getAttribute( 'method' ) ) );
		$this->assertSame( admin_url( 'admin-post.php' ), $form->getAttribute( 'action' ) );

		$input = $xpath->query( '//form//input[@type="email"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertSame( Rescue_Link::EMAIL_FIELD, $input->getAttribute( 'name' ) );
		$this->assertTrue( $input->hasAttribute( 'required' ) );
		$label = $xpath->query( '//form//label[@for="' . $input->getAttribute( 'id' ) . '"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $label, 'The email field has no label.' );
		$this->assertNotSame( '', $xpath->evaluate( 'normalize-space(//form//label[@for="' . $input->getAttribute( 'id' ) . '"])' ) );

		$this->assertSame( 1, $xpath->query( '//form//input[@type="hidden"][@name="action"][@value="' . Rescue_Link::ACTION . '"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form//input[@type="hidden"][@name="' . Rescue_Link::NONCE_FIELD . '"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form//button[@type="submit"]' )->length );
		$this->assertSame( 1, $xpath->query( '//details//a[@href="https://wordpress.org/plugins/zero-spam/#faq"]' )->length );
		$this->assertStringContainsString( 'Site owner? If you administer this site, enter the email address of an administrator account on this site', $html );
		$this->assertSame( 'Blocked', $xpath->evaluate( 'normalize-space(//h1)' ) );

		// A 2px focus outline with an offset for the form controls.
		$this->assertMatchesRegularExpression( '/<style>[^<]*\.zerospam-rescue input:focus-visible[^<]*outline: 2px solid #2271b1; outline-offset: 2px;/', $html );
	}

	/**
	 * Other pages that use terminate_execution() (spam detections) have no rescue form.
	 */
	public function test_detection_pages_have_no_rescue_form(): void {
		try {
			Access::terminate_execution( 'Blocked', 'Spam detected.' );
			$this->fail( 'terminate_execution() returned.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, (int) $e->getCode() );
			$this->assertStringNotContainsString( '<form', $e->getMessage() );
		}
	}

	/**
	 * Parses an HTML fragment.
	 *
	 * @param string $html Markup.
	 */
	private function parse( string $html ): DOMXPath {
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();

		return new DOMXPath( $document );
	}
}
