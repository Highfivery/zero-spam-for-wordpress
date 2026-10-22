<?php
/**
 * Tests for the Login module's checks on multi-step and alternate login flows
 * (two-factor plugins, login intent token, skip filter, bypass logging).
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
 * Login::process_form() on `wp_authenticate_user`, for the second and later
 * steps of a login and for flows that never post the login form.
 *
 * @covers \ZeroSpam\Modules\Login\Login
 */
class Test_Login_Flows extends TestCase {

	/**
	 * User agent of the simulated browser.
	 */
	const USER_AGENT = 'Mozilla/5.0 (Zero Spam tests)';

	/**
	 * The user logging in.
	 *
	 * @var WP_User
	 */
	private WP_User $user;

	/**
	 * Simulates a browser on wp-login.php (restored after the test).
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_actions']['login_init'] = 1;
		$_SERVER['HTTP_USER_AGENT']          = self::USER_AGENT;
		$_COOKIE                             = array();

		$this->user = get_userdata( self::factory()->user->create() );
	}

	/**
	 * Turns the David Walsh technique on for this test, as its module would.
	 */
	private function enable_davidwalsh(): void {
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );

		$davidwalsh = ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor();
		add_filter( 'zerospam_preprocess_login_attempt', array( $davidwalsh, 'validate_post' ), 10, 3 );
	}

	/**
	 * Loads the login page: issues the intent token and stores its cookie in
	 * the browser.
	 *
	 * @return string The token.
	 */
	private function load_login_page(): string {
		( new Login() )->set_intent_token();

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
	 * Fields the core login form posts, with the honeypot and an optional key.
	 *
	 * @param string|null $key David Walsh key, or null to leave it out.
	 * @return array<string, string>
	 */
	private function full_form( ?string $key = null ): array {
		$fields = array(
			'log'                     => $this->user->user_login,
			'pwd'                     => 'password',
			Utilities::get_honeypot() => '',
		);

		if ( null !== $key ) {
			$fields['zerospam_david_walsh_key'] = $key;
		}

		return $fields;
	}

	/**
	 * Credentials only: what arrives when another plugin strips the
	 * honeypot and key from the login form.
	 *
	 * @return array<string, string>
	 */
	private function stripped_form(): array {
		return array(
			'log' => $this->user->user_login,
			'pwd' => 'password',
		);
	}

	/**
	 * Runs the login check on a POST.
	 *
	 * @param array<string, mixed> $post POST fields.
	 * @return WP_User|WP_Error
	 */
	private function authenticate( array $post ) {
		$_POST = $post;

		return ( new Login() )->process_form( $this->user );
	}

	/**
	 * Asserts that the check let the login through.
	 *
	 * @param mixed  $result  process_form() result.
	 * @param string $message Failure message.
	 */
	private function assertPasses( $result, string $message = '' ): void {
		$this->assertInstanceOf( WP_User::class, $result, $message . ( $result instanceof WP_Error ? ' Error: ' . $result->get_error_message() : '' ) );
	}

	/**
	 * Asserts that the check refused the login.
	 *
	 * @param mixed  $result  process_form() result.
	 * @param string $message Failure message.
	 */
	private function assertRefused( $result, string $message = '' ): void {
		$this->assertInstanceOf( WP_Error::class, $result, $message );
		$this->assertSame( 'failed_zerospam', $result->get_error_code() );
	}

	/**
	 * Decoded submission data of the newest log row of a type.
	 *
	 * @param string $type Log type.
	 * @return array<string, mixed>
	 */
	private function last_log( string $type ): array {
		global $wpdb;

		$data = $wpdb->get_var( $wpdb->prepare( 'SELECT submission_data FROM %i WHERE log_type = %s ORDER BY log_id DESC LIMIT 1', $this->log_table(), $type ) );

		return is_string( $data ) ? (array) json_decode( $data, true ) : array();
	}

	/**
	 * (a) The intent token is not one-time: a second stripped POST in the
	 * window (e.g. the next step of a two-step login) still passes.
	 */
	public function test_intent_token_stays_valid_for_a_second_post_in_the_window(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$this->assertPasses( $this->authenticate( $this->stripped_form() ), 'First stripped POST.' );
		$this->assertPasses( $this->authenticate( $this->stripped_form() ), 'Second stripped POST in the window.' );
	}

	/**
	 * Each use renews the token: its cookie is sent again with a fresh expiry.
	 */
	public function test_a_successful_use_renews_the_intent_token(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$token                               = $this->load_login_page();

		$this->assertPasses( $this->authenticate( $this->stripped_form() ) );

		$cookie = $this->last_intent_cookie();
		$this->assertNotNull( $cookie, 'The intent cookie is sent again.' );
		$this->assertSame( $token, $cookie['value'] );
		$this->assertEqualsWithDelta( time() + 10 * MINUTE_IN_SECONDS, $cookie['options']['expires'], 5 );
		$this->assertTrue( $cookie['options']['httponly'] );
	}

	/**
	 * Loading the login page again keeps a valid token, so every step of one
	 * login uses the same token.
	 */
	public function test_reloading_the_login_page_keeps_a_valid_intent_token(): void {
		$token = $this->load_login_page();

		( new Login() )->set_intent_token();

		$cookie = $this->last_intent_cookie();
		$this->assertNotNull( $cookie );
		$this->assertSame( $token, $cookie['value'] );
	}

	/**
	 * (b) Once a step passed the full checks (honeypot and key), a later step
	 * with neither passes on the intent token alone, without the David Walsh
	 * key cookie.
	 */
	public function test_missing_honeypot_and_key_pass_on_an_intent_proven_by_an_earlier_step(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();

		$this->assertPasses( $this->authenticate( $this->full_form( DavidWalsh::generate_token() ) ), 'Step one, full form.' );

		$this->assertArrayNotHasKey( 'zerospam_david_walsh_key', $_COOKIE );
		$this->assertPasses( $this->authenticate( $this->stripped_form() ), 'Later step without honeypot or key.' );
	}

	/**
	 * The 5.7.12 hardening stays: with David Walsh enabled, an intent token
	 * that no full step has proven is not enough without the key cookie, since
	 * any client can request the login page.
	 */
	public function test_unproven_intent_without_david_walsh_key_is_still_refused(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();

		$this->assertRefused( $this->authenticate( $this->stripped_form() ) );
	}

	/**
	 * (c) A filled honeypot is never waived, whatever the intent token says.
	 */
	public function test_filled_honeypot_blocks_even_with_a_valid_intent(): void {
		$this->enable_davidwalsh();
		$this->load_login_page();
		$this->assertPasses( $this->authenticate( $this->full_form( DavidWalsh::generate_token() ) ) );

		$post                              = $this->full_form( DavidWalsh::generate_token() );
		$post[ Utilities::get_honeypot() ] = 'spam';

		$result = $this->authenticate( $post );
		$this->assertRefused( $result );
		$this->assertSame( 'Your IP has been flagged as spam/malicious.', $result->get_error_message() );
	}

	/**
	 * (d) An invalid David Walsh key is never waived, whatever the intent token says.
	 */
	public function test_invalid_david_walsh_key_blocks_even_with_a_valid_intent(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();
		$this->assertPasses( $this->authenticate( $this->full_form( DavidWalsh::generate_token() ) ) );

		$result = $this->authenticate( $this->full_form( 'not-a-valid-key' ) );
		$this->assertRefused( $result );
		$this->assertSame( 'Your IP has been flagged as spam/malicious.', $result->get_error_message() );
	}

	/**
	 * Two-factor second steps (field names taken from each plugin's source,
	 * see Login::is_later_auth_step()).
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
					'redirect_to'    => 'http://example.org/wp-admin/',
				),
			),
			'Two Factor (validate_2fa)'      => array(
				array(
					'provider'      => 'Two_Factor_Totp',
					'wp-auth-id'    => '1',
					'wp-auth-nonce' => 'nonce',
					'authcode'      => '123456',
					'redirect_to'   => 'http://example.org/wp-admin/',
				),
			),
			'no POST at all (XML-RPC)'       => array( array() ),
		);
	}

	/**
	 * (e) An authentication that carries no login form fields (no password,
	 * honeypot or key), such as a two-factor code step, is not checked for
	 * the honeypot or key, even with David Walsh on and no intent token.
	 *
	 * @dataProvider data_two_factor_steps
	 *
	 * @param array<string, string> $post Second-step POST.
	 */
	public function test_second_step_without_login_form_fields_passes( array $post ): void {
		$this->enable_davidwalsh();

		$this->assertPasses( $this->authenticate( $post ) );
	}

	/**
	 * A second step that does carry the honeypot field is checked: a filled
	 * honeypot still blocks.
	 */
	public function test_second_step_with_a_filled_honeypot_is_still_checked(): void {
		$this->enable_davidwalsh();

		$post = array(
			'login_token'             => 'abc123',
			'password'                => '123456',
			Utilities::get_honeypot() => 'spam',
		);

		$this->assertRefused( $this->authenticate( $post ) );
	}

	/**
	 * A request carrying a valid logged-in cookie for the same user already
	 * passed step one; one for another user did not.
	 */
	public function test_logged_in_cookie_for_the_same_user_skips_the_checks(): void {
		$this->enable_davidwalsh();

		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $this->user->ID, time() + HOUR_IN_SECONDS, 'logged_in' );
		$this->assertPasses( $this->authenticate( $this->stripped_form() ) );

		$other                       = self::factory()->user->create();
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $other, time() + HOUR_IN_SECONDS, 'logged_in' );
		$this->assertRefused( $this->authenticate( $this->stripped_form() ) );
	}

	/**
	 * (f) The token is bound to the client: the same token from another IP
	 * or another user agent is refused.
	 */
	public function test_intent_token_is_bound_to_the_client(): void {
		$this->enable_davidwalsh();
		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();

		$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
		$this->assertRefused( $this->authenticate( $this->stripped_form() ), 'Another IP.' );

		$_SERVER['REMOTE_ADDR']     = self::DEFAULT_IP;
		$_SERVER['HTTP_USER_AGENT'] = 'curl/8.0';
		$this->assertRefused( $this->authenticate( $this->stripped_form() ), 'Another user agent.' );

		$_SERVER['HTTP_USER_AGENT'] = self::USER_AGENT;
		$this->assertPasses( $this->authenticate( $this->stripped_form() ), 'The original client.' );
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

		$this->assertRefused( $this->authenticate( $this->stripped_form() ) );
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

	/**
	 * (g) zerospam_login_skip_check exempts a flow, and can also force the
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

		$post                 = $this->stripped_form();
		$post['my_sso_token'] = 'xyz';
		$this->assertPasses( $this->authenticate( $post ), 'Exempted by the filter.' );
		$this->assertFalse( $seen[0], 'Not detected as a later step.' );
		$this->assertSame( 'xyz', $seen[1]['my_sso_token'] ?? null );
		$this->assertSame( $this->user, $seen[2] );

		$this->assertRefused( $this->authenticate( array( 'password' => '123456' ) ), 'Checks forced on a detected later step.' );
		$this->assertTrue( $seen[0], 'Detected as a later step.' );
	}

	/**
	 * (h) Waived checks are logged as login_bypass when "Log Blocked Logins" is on.
	 */
	public function test_bypasses_are_logged_when_logging_is_on(): void {
		$this->enable_davidwalsh();
		$this->set_setting( 'log_blocked_logins', 'enabled', 'login' );

		$this->assertPasses( $this->authenticate( array( 'password' => '123456' ) ) );
		$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'login_bypass' ) ) );
		$this->assertSame( 'second_step', $this->last_log( 'login_bypass' )['failed'] ?? null );
		$this->assertSame( $this->user->user_login, $this->last_log( 'login_bypass' )['user'] ?? null );

		$_COOKIE['zerospam_david_walsh_key'] = DavidWalsh::generate_token();
		$this->load_login_page();
		$this->assertPasses( $this->authenticate( $this->stripped_form() ) );
		$this->assertSame( 2, $this->count_log_rows( array( 'log_type' => 'login_bypass' ) ) );
		$this->assertSame( 'bypassed_by_intent', $this->last_log( 'login_bypass' )['failed'] ?? null );

		$this->assertSame( 0, $this->count_log_rows( array( 'log_type' => 'login' ) ), 'A bypass is not logged as a blocked login.' );
	}

	/**
	 * Nothing is logged for a bypass when "Log Blocked Logins" is off.
	 */
	public function test_bypasses_are_not_logged_when_logging_is_off(): void {
		$this->enable_davidwalsh();

		$this->assertPasses( $this->authenticate( array( 'password' => '123456' ) ) );
		$this->assertSame( 0, $this->count_log_rows( array( 'log_type' => 'login_bypass' ) ) );
	}

	/**
	 * Credentials without honeypot, key or intent are still refused when
	 * David Walsh is on (the later-step rule needs the password to be absent).
	 */
	public function test_stripped_form_without_intent_is_refused(): void {
		$this->enable_davidwalsh();

		$this->assertRefused( $this->authenticate( $this->stripped_form() ) );
	}
}
