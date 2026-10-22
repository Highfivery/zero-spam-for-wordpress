<?php
/**
 * Tests for the Login module's checks on multi-step and alternate login flows
 * (login intent token, later authentication steps, skip filter, lapsed keys).
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Modules\DavidWalsh\DavidWalsh;
use ZeroSpam\Modules\Login\Login;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * Login checks through real wp_authenticate() calls, so the password check
 * that runs after `wp_authenticate_user` is part of every scenario.
 *
 * @covers \ZeroSpam\Modules\Login\Login
 * @covers \ZeroSpam\Modules\DavidWalsh\DavidWalsh
 */
class Test_Login_Flows extends TestCase {

	/**
	 * User agent of the simulated browser.
	 */
	const USER_AGENT = 'Mozilla/5.0 (Zero Spam tests)';

	/**
	 * The user's password.
	 */
	const PASSWORD = 'correct horse battery staple';

	/**
	 * The user logging in.
	 *
	 * @var WP_User
	 */
	private WP_User $user;

	/**
	 * The module under test, with its hooks registered.
	 *
	 * @var Login
	 */
	private Login $login;

	/**
	 * Simulates a browser on wp-login.php with login protection on (restored
	 * after the test).
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_actions']['login_init'] = 1;
		$_SERVER['HTTP_USER_AGENT']          = self::USER_AGENT;
		$_COOKIE                             = array();

		$this->user = get_userdata( self::factory()->user->create( array( 'user_pass' => self::PASSWORD ) ) );

		// Only this test's instance checks logins.
		remove_all_filters( 'wp_authenticate_user' );
		$this->set_setting( 'verify_login', 'enabled', 'login' );
		$this->login = new Login();
		$this->login->init();
	}

	/**
	 * Turns the David Walsh technique on for this test, as its module would.
	 */
	private function enable_davidwalsh(): void {
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );

		$davidwalsh = ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor();
		remove_all_filters( 'zerospam_preprocess_login_attempt' );
		add_filter( 'zerospam_preprocess_login_attempt', array( $davidwalsh, 'validate_post' ), 10, 3 );
	}

	/**
	 * Loads the login page: issues the intent token and stores its cookie in
	 * the browser.
	 *
	 * @return string The token.
	 */
	private function load_login_page(): string {
		$this->login->set_intent_token();

		$cookie = $this->last_intent_cookie();
		$this->assertNotNull( $cookie, 'The login page sets the intent cookie.' );

		$_COOKIE['zerospam_login_intent'] = $cookie['value'];
		Stubs::$cookies                   = array();

		return $cookie['value'];
	}

	/**
	 * The last intent cookie the module tried to set, if any.
	 *
	 * @return array{name: string, value: string, options: mixed}|null
	 */
	private function last_intent_cookie(): ?array {
		$found = null;
		foreach ( Stubs::$cookies as $cookie ) {
			if ( 'zerospam_login_intent' === $cookie['name'] ) {
				$found = $cookie;
			}
		}

		return $found;
	}

	/**
	 * The stored intent for the browser's token.
	 *
	 * @return mixed
	 */
	private function stored_intent() {
		return get_transient( 'zerospam_login_intent_' . $_COOKIE['zerospam_login_intent'] );
	}

	/**
	 * Fields the core login form posts, with the honeypot and a key.
	 *
	 * @param string $password Password.
	 * @param string $key      David Walsh key.
	 * @return array<string, string>
	 */
	private function full_form( string $password, string $key ): array {
		return array(
			'log'                      => $this->user->user_login,
			'pwd'                      => $password,
			Utilities::get_honeypot()  => '',
			'zerospam_david_walsh_key' => $key,
		);
	}

	/**
	 * Credentials only: what arrives when another plugin strips the
	 * honeypot and key from the login form.
	 *
	 * @param string $password Password.
	 * @return array<string, string>
	 */
	private function stripped_form( string $password ): array {
		return array(
			'log' => $this->user->user_login,
			'pwd' => $password,
		);
	}

	/**
	 * Logs in the way wp-login.php does: wp_authenticate() with the POST set.
	 *
	 * @param array<string, string> $post     POST fields.
	 * @param string                $password Password checked by core.
	 * @return WP_User|WP_Error
	 */
	private function log_in( array $post, string $password ) {
		$_POST = $post;

		return wp_authenticate( $this->user->user_login, $password );
	}

	/**
	 * Runs the check alone for a request without credentials in the POST
	 * (a later step, or an endpoint with its own field names).
	 *
	 * @param array<string, string> $post POST fields.
	 * @return WP_User|WP_Error
	 */
	private function check_step( array $post ) {
		$_POST = $post;

		return $this->login->process_form( $this->user );
	}

	/**
	 * Completes a real first step: full form, valid key, right password.
	 */
	private function pass_password_step(): void {
		$this->assertPasses( $this->log_in( $this->full_form( self::PASSWORD, DavidWalsh::generate_token() ), self::PASSWORD ), 'Password step.' );
	}

	/**
	 * Asserts that the login went through.
	 *
	 * @param mixed  $result  Result.
	 * @param string $message Failure message.
	 */
	private function assertPasses( $result, string $message = '' ): void {
		$this->assertInstanceOf( WP_User::class, $result, $message . ( $result instanceof WP_Error ? ' Error: ' . $result->get_error_message() : '' ) );
	}

	/**
	 * Asserts that Zero Spam refused the login.
	 *
	 * @param mixed  $result  Result.
	 * @param string $message Failure message.
	 */
	private function assertRefused( $result, string $message = '' ): void {
		$this->assertInstanceOf( WP_Error::class, $result, $message );
		$this->assertSame( 'failed_zerospam', $result->get_error_code(), $message );
	}

	/**
	 * Two-factor second steps (field names from each plugin's source, see
	 * Login::is_later_auth_step()).
	 *
	 * @return array<string, array{0: array<string, string>}>
	 */
	public function data_two_factor_steps(): array {
		return array(
			'Defender (defender-verify-otp)' => array(
				array(
					'login_token'    => 'abc123',
					'requested_user' => '1',
					'auth_method'    => 'totp',
					'password'       => '123456',
					'_wpnonce'       => 'nonce',
				),
			),
			'Two Factor (validate_2fa)'      => array(
				array(
					'provider'      => 'Two_Factor_Totp',
					'wp-auth-id'    => '1',
					'wp-auth-nonce' => 'nonce',
					'authcode'      => '123456',
				),
			),
		);
	}

	/**
	 * Requests without the login form's fields that must not skip the checks
	 * on their own.
	 *
	 * @return array<string, array{0: array<string, string>}>
	 */
	public function data_form_less_requests(): array {
		return array_merge(
			$this->data_two_factor_steps(),
			array(
				'custom endpoint (username/password)' => array(
					array(
						'username' => 'admin',
						'password' => 'guess',
					),
				),
				'no POST at all (XML-RPC)'            => array( array() ),
			)
		);
	}

	/*
	 * Intent token lifetime.
	 */

	/**
	 * The intent token is not one-time: a second stripped login in the
	 * window still passes.
	 */
	public function test_intent_token_stays_valid_for_a_second_login_in_the_window(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$this->assertPasses( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'First.' );
		$this->assertPasses( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Second, in the window.' );
	}

	/**
	 * A successful login renews the token (same token, fresh expiry).
	 */
	public function test_successful_login_renews_the_intent_token(): void {
		$this->enable_davidwalsh();
		$token = $this->load_login_page();

		$this->pass_password_step();

		$cookie = $this->last_intent_cookie();
		$this->assertNotNull( $cookie, 'The intent cookie is sent again.' );
		$this->assertSame( $token, $cookie['value'] );
		$this->assertEqualsWithDelta( time() + 10 * MINUTE_IN_SECONDS, $cookie['options']['expires'], 5 );
		$this->assertTrue( $cookie['options']['httponly'] );
	}

	/**
	 * A failed attempt never renews the token, even when the intent let the
	 * stripped form through Zero Spam's check.
	 */
	public function test_failed_attempt_does_not_renew_the_intent_token(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$result = $this->log_in( $this->stripped_form( 'wrong' ), 'wrong' );

		$this->assertSame( 'incorrect_password', $result->get_error_code() );
		$this->assertNull( $this->last_intent_cookie(), 'No renewed cookie after a failed attempt.' );
	}

	/**
	 * Loading the login page again keeps a valid token, so every step of one
	 * login uses the same token.
	 */
	public function test_reloading_the_login_page_keeps_a_valid_intent_token(): void {
		$token = $this->load_login_page();

		$this->login->set_intent_token();

		$cookie = $this->last_intent_cookie();
		$this->assertNotNull( $cookie );
		$this->assertSame( $token, $cookie['value'] );
	}

	/**
	 * The zerospam_login_intent_ttl filter sets the window, within bounds.
	 */
	public function test_intent_ttl_filter_sets_the_window(): void {
		add_filter(
			'zerospam_login_intent_ttl',
			static function (): int {
				return 2 * MINUTE_IN_SECONDS;
			}
		);

		$token = $this->load_login_page();

		$this->assertEqualsWithDelta( time() + 2 * MINUTE_IN_SECONDS, (int) get_option( '_transient_timeout_zerospam_login_intent_' . $token ), 5 );

		add_filter(
			'zerospam_login_intent_ttl',
			static function (): int {
				return 30 * DAY_IN_SECONDS;
			},
			20
		);
		$this->assertSame( HOUR_IN_SECONDS, Login::get_intent_ttl(), 'Capped at an hour.' );
	}

	/*
	 * Item 1: only a successful password step verifies the intent; uses are capped.
	 */

	/**
	 * Probe: ten stripped wrong-password attempts with an intent and a key
	 * cookie, then the right password, stripped: refused, because one token
	 * only covers INTENT_MAX_USES attempts.
	 */
	public function test_intent_uses_are_capped_against_password_probing(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$codes = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$codes[ $i ] = $this->log_in( $this->stripped_form( 'guess-' . $i ), 'guess-' . $i )->get_error_code();
		}

		$this->assertSame( array_fill( 1, Login::INTENT_MAX_USES, 'incorrect_password' ), array_slice( $codes, 0, Login::INTENT_MAX_USES, true ), 'Attempts 1-5 reach the password check.' );
		$this->assertSame( 'failed_zerospam', $codes[ Login::INTENT_MAX_USES + 1 ], 'Attempt 6 is refused by Zero Spam.' );

		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ) );
	}

	/**
	 * A full form with a valid key but the wrong password does not verify
	 * the intent: wp_authenticate_user runs before core checks the password.
	 */
	public function test_wrong_password_with_full_form_does_not_verify_the_intent(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();

		$result = $this->log_in( $this->full_form( 'wrong', DavidWalsh::generate_token() ), 'wrong' );
		$this->assertSame( 'incorrect_password', $result->get_error_code() );
		$this->assertEmpty( $this->stored_intent()['verified'] ?? null );

		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Stripped login without key cookie.' );
	}

	/**
	 * A key cookie (which any client can set) lets a stripped attempt through
	 * Zero Spam's check, but never verifies the intent.
	 */
	public function test_key_cookie_set_by_the_client_does_not_verify_the_intent(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$result = $this->log_in( $this->stripped_form( 'wrong' ), 'wrong' );
		$this->assertSame( 'incorrect_password', $result->get_error_code(), 'Passed the check, failed the password.' );
		$this->assertEmpty( $this->stored_intent()['verified'] ?? null );

		unset( $_COOKIE['zerospam_david_walsh_key'] );
		$this->assertRefused( $this->check_step( $this->data_two_factor_steps()['Defender (defender-verify-otp)'][0] ), 'Form-less step.' );
	}

	/**
	 * A successful password step verifies the intent: later stripped logins
	 * pass without the key cookie (until the uses run out).
	 */
	public function test_successful_password_step_verifies_the_intent(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();

		$this->pass_password_step();
		$this->assertTrue( $this->stored_intent()['verified'] ?? null );

		$this->assertArrayNotHasKey( 'zerospam_david_walsh_key', $_COOKIE );
		$this->assertPasses( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Later step without honeypot or key.' );
	}

	/**
	 * The 5.7.12 hardening stays: an unverified intent without the key
	 * cookie is not enough, since any client can request the login page.
	 */
	public function test_unverified_intent_without_david_walsh_key_is_refused(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();

		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ) );
	}

	/**
	 * A filled honeypot is never waived, even with a verified intent.
	 */
	public function test_filled_honeypot_blocks_even_with_a_verified_intent(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$post                              = $this->full_form( self::PASSWORD, DavidWalsh::generate_token() );
		$post[ Utilities::get_honeypot() ] = 'spam';

		$result = $this->log_in( $post, self::PASSWORD );
		$this->assertRefused( $result );
		$this->assertSame( 'Your IP has been flagged as spam/malicious.', $result->get_error_message() );
	}

	/**
	 * A forged key is never waived, even with a verified intent, and its
	 * block is logged with the reason.
	 */
	public function test_forged_key_blocks_even_with_a_verified_intent(): void {
		$this->enable_davidwalsh();
		$this->set_setting( 'log_blocked_logins', 'enabled', 'login' );
		$this->load_login_page();
		$this->pass_password_step();

		$result = $this->log_in( $this->full_form( self::PASSWORD, 'not-a-valid-key' ), self::PASSWORD );
		$this->assertRefused( $result );
		$this->assertSame( 'Your IP has been flagged as spam/malicious.', $result->get_error_message() );
		$this->assertSame( 'david_walsh_invalid', $this->last_login_log()['failed'] ?? null );
	}

	/*
	 * Item 2: a request without login form fields is a later step only with
	 * an intent verified by a successful password step.
	 */

	/**
	 * After a successful password step, a two-factor code step (which posts
	 * no password, honeypot or key) is not checked for them.
	 *
	 * @dataProvider data_two_factor_steps
	 *
	 * @param array<string, string> $post Second-step POST.
	 */
	public function test_second_step_passes_after_a_successful_password_step( array $post ): void {
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$this->assertPasses( $this->check_step( $post ) );
	}

	/**
	 * Without a verified intent, a request without login form fields gets
	 * the normal checks, so a custom login endpoint can't skip them.
	 *
	 * @dataProvider data_form_less_requests
	 *
	 * @param array<string, string> $post POST.
	 */
	public function test_form_less_request_without_verified_intent_is_checked( array $post ): void {
		$this->enable_davidwalsh();

		$this->assertRefused( $this->check_step( $post ), 'No intent.' );

		$this->load_login_page();
		$this->assertRefused( $this->check_step( $post ), 'Unverified intent.' );
	}

	/**
	 * The verified intent belongs to the user whose password step succeeded:
	 * it waives nothing for another username (here a subscriber's login
	 * must not cover attempts against an administrator).
	 */
	public function test_verified_intent_is_bound_to_the_user_who_logged_in(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$other = get_userdata( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array( 'password' => '123456' );
		$this->assertRefused( $this->login->process_form( $other ), 'Form-less step naming another user.' );

		$_POST = array(
			'log' => $other->user_login,
			'pwd' => 'guess',
		);
		$this->assertRefused( $this->login->process_form( $other ), 'Stripped attempt against another user.' );

		$this->assertPasses( $this->check_step( array( 'password' => '123456' ) ), 'The same user still passes.' );
	}

	/**
	 * A later step that does carry the honeypot field gets the normal
	 * checks: a filled honeypot blocks.
	 */
	public function test_second_step_with_a_filled_honeypot_is_still_checked(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$post = array(
			'login_token'             => 'abc123',
			'password'                => '123456',
			Utilities::get_honeypot() => 'spam',
		);

		$this->assertRefused( $this->check_step( $post ) );
	}

	/*
	 * Client binding.
	 */

	/**
	 * The token is bound to the client's network (/24 for IPv4) and user
	 * agent: another address on the same /24 is accepted, another network or
	 * another user agent is not.
	 */
	public function test_intent_token_is_bound_to_the_client_network_and_user_agent(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
		$this->assertPasses( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Same /24.' );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Another network.' );

		$_SERVER['REMOTE_ADDR']     = self::DEFAULT_IP;
		$_SERVER['HTTP_USER_AGENT'] = 'curl/8.0';
		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Another user agent.' );
	}

	/**
	 * IPv6 clients are bound to their /64.
	 */
	public function test_ipv6_intent_token_is_bound_to_the_slash_64(): void {
		$this->set_request( '/', '2001:db8:1:2::10' );
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$this->set_request( '/', '2001:db8:1:2:ffff::1' );
		$this->assertPasses( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Same /64.' );

		$this->set_request( '/', '2001:db8:1:3::10' );
		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ), 'Another /64.' );
	}

	/**
	 * A token stored before 5.7.14 (a bare timestamp, not bound to a client)
	 * is not accepted.
	 */
	public function test_unbound_legacy_intent_token_is_refused(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();

		$token = str_repeat( 'a', 32 );
		set_transient( 'zerospam_login_intent_' . $token, time(), 10 * MINUTE_IN_SECONDS );
		$_COOKIE['zerospam_login_intent'] = $token;

		$this->assertRefused( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ) );
	}

	/*
	 * Skip filter and waived-check reporting.
	 */

	/**
	 * The zerospam_login_skip_check filter exempts a flow, and can also force the
	 * checks on a request detected as a later step.
	 */
	public function test_skip_filter_exempts_or_forces_the_checks(): void {
		$this->enable_davidwalsh();

		$seen = array();
		add_filter(
			'zerospam_login_skip_check',
			static function ( $skip, $post, $user ) use ( &$seen ) {
				$seen = array( $skip, $post, $user );

				return ! empty( $post['my_sso_token'] );
			},
			10,
			3
		);

		$post                 = $this->stripped_form( self::PASSWORD );
		$post['my_sso_token'] = 'xyz';
		$this->assertPasses( $this->check_step( $post ), 'Exempted by the filter.' );
		$this->assertFalse( $seen[0], 'Not detected as a later step.' );
		$this->assertSame( 'xyz', $seen[1]['my_sso_token'] ?? null );
		$this->assertSame( $this->user, $seen[2] );

		$waived = array();
		add_action(
			'zerospam_login_checks_waived',
			static function ( $reason ) use ( &$waived ) {
				$waived[] = $reason;
			}
		);

		$this->load_login_page();
		$this->pass_password_step();
		$this->check_step( array( 'password' => '123456' ) );
		$this->assertTrue( $seen[0], 'Detected as a later step.' );
		$this->assertSame( array( 'bypassed_by_intent' ), $waived, 'The checks ran (and the missing fields fell back to the intent) instead of being skipped.' );
	}

	/**
	 * Waived checks fire zerospam_login_checks_waived and are never written
	 * to the detection log, even with "Log Blocked Login Attempts" on.
	 */
	public function test_waived_checks_fire_an_action_and_stay_out_of_the_detection_log(): void {
		$this->enable_davidwalsh();
		$this->set_setting( 'log_blocked_logins', 'enabled', 'login' );

		$waived = array();
		add_action(
			'zerospam_login_checks_waived',
			static function ( $reason, $user ) use ( &$waived ) {
				$waived[] = array( $reason, $user instanceof WP_User ? $user->ID : null );
			},
			10,
			2
		);

		$this->load_login_page();
		$this->pass_password_step();
		$this->assertPasses( $this->check_step( array( 'password' => '123456' ) ) );
		$this->assertPasses( $this->log_in( $this->stripped_form( self::PASSWORD ), self::PASSWORD ) );

		$this->assertSame(
			array(
				array( 'second_step', $this->user->ID ),
				array( 'bypassed_by_intent', $this->user->ID ),
			),
			$waived
		);
		$this->assertSame( 0, $this->count_log_rows(), 'Nothing in the detection log.' );
	}

	/**
	 * With WP_DEBUG on (as in this suite), waived checks go to the plugin's
	 * debug log, without the submitted fields.
	 */
	public function test_waived_checks_go_to_the_debug_log_when_wp_debug_is_on(): void {
		$this->assertTrue( WP_DEBUG );
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->pass_password_step();

		$file   = wp_upload_dir()['basedir'] . '/zerospam.log';
		$before = file_exists( $file ) ? (int) filesize( $file ) : 0;

		$this->check_step( array( 'password' => 'otp-123456' ) );

		clearstatcache();
		$written = (string) file_get_contents( $file, false, null, $before ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the local log file the plugin just wrote.
		$this->assertStringContainsString( 'second_step', $written );
		$this->assertStringNotContainsString( $this->user->user_login, $written, 'No username: the file may be web-readable.' );
		$this->assertStringNotContainsString( self::DEFAULT_IP, $written, 'No IP address.' );
		$this->assertStringNotContainsString( 'otp-123456', $written );
	}

	/*
	 * Lapsed keys (the Defender Pro report): expired or used-up keys signed
	 * by this site are treated as missing on login, not as spam.
	 */

	/**
	 * An expired key gets "Verification missing", not the spam message.
	 */
	public function test_expired_key_on_login_is_treated_as_missing(): void {
		$this->enable_davidwalsh();

		$result = $this->log_in( $this->full_form( self::PASSWORD, self::signed_token( DavidWalsh::KEY_TTL + 120 ) ), self::PASSWORD );

		$this->assertRefused( $result );
		$this->assertSame( 'Verification missing. Please try again.', $result->get_error_message() );
	}

	/**
	 * A used-up key falls back to the intent token like a missing key.
	 */
	public function test_used_up_key_on_login_falls_back_to_the_intent(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$this->assertPasses( $this->log_in( $this->full_form( self::PASSWORD, self::used_up_token() ), self::PASSWORD ) );
	}

	/**
	 * A blocked login with a lapsed key is logged with the specific reason.
	 */
	public function test_lapsed_key_block_is_logged_with_its_reason(): void {
		$this->enable_davidwalsh();
		$this->set_setting( 'log_blocked_logins', 'enabled', 'login' );

		$this->log_in( $this->full_form( self::PASSWORD, self::signed_token( DavidWalsh::KEY_TTL + 120 ) ), self::PASSWORD );

		$this->assertSame( 'david_walsh_expired', $this->last_login_log()['failed'] ?? null );
	}

	/**
	 * A token signed by this site, issued $age seconds ago.
	 *
	 * @param int $age Seconds since issue.
	 */
	private static function signed_token( int $age ): string {
		$payload = base_convert( (string) ( time() - $age ), 10, 36 ) . '.' . wp_generate_password( 12, false, false );
		$sign    = new ReflectionMethod( DavidWalsh::class, 'sign' );

		return $payload . '.' . $sign->invoke( null, $payload );
	}

	/**
	 * A fresh token that has already been accepted MAX_USES times.
	 */
	private static function used_up_token(): string {
		$token = DavidWalsh::generate_token();
		set_transient( 'zerospam_dw_' . md5( $token ), DavidWalsh::MAX_USES, HOUR_IN_SECONDS );

		return $token;
	}

	/**
	 * Decoded submission data of the newest login log row.
	 *
	 * @return array<string, mixed>
	 */
	private function last_login_log(): array {
		global $wpdb;

		$data = $wpdb->get_var( $wpdb->prepare( 'SELECT submission_data FROM %i WHERE log_type = %s ORDER BY log_id DESC LIMIT 1', $this->log_table(), 'login' ) );

		return is_string( $data ) ? (array) json_decode( $data, true ) : array();
	}
}
