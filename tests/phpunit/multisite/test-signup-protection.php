<?php
/**
 * Tests for registration protection on a network's sign-up page.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Modules\DavidWalsh\DavidWalsh;
use ZeroSpam\Modules\Registration\Registration;
use ZeroSpam\Modules\StopForumSpam;
use ZeroSpam\Tests\TestCase;

/**
 * A network registers through wp-signup.php, which fires neither
 * `registration_errors` nor `register_form`. Each test runs what the page runs:
 * `before_signup_header`, then wpmu_validate_user_signup().
 *
 * @group ms-required
 * @covers \ZeroSpam\Modules\Registration\Registration
 */
class Test_Signup_Protection extends TestCase {

	const SFS_API = '#^https://api\.stopforumspam\.org/api\?#';

	const USERNAME = 'realperson';

	const EMAIL = 'real-person@example.com';

	/**
	 * A visitor on the sign-up page with registration protection on.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( 0 );
		$this->set_request( '/wp-signup.php' );
		$this->set_setting( 'verify_registrations', 'enabled', 'registration' );
		$this->set_setting( 'log_blocked_registrations', 'enabled', 'registration' );
		$this->set_setting( 'registration_spam_message', 'No spam here, thanks.', 'registration' );
		$this->set_setting( 'davidwalsh', false, 'davidwalsh' );
		$this->set_setting( 'stop_forum_spam', false, 'stop_forum_spam' );
	}

	/**
	 * Clears the simulated form.
	 */
	public function tear_down(): void {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Runs a sign-up as wp-signup.php does.
	 *
	 * @param array  $post     Form fields besides the name and email.
	 * @param string $username Username.
	 * @param string $email    Email address.
	 * @return WP_Error The sign-up's errors.
	 */
	private function sign_up( array $post = array(), string $username = self::USERNAME, string $email = self::EMAIL ): WP_Error {
		( new Registration() )->init();
		$_POST = $post;
		do_action( 'before_signup_header' );

		$result = wpmu_validate_user_signup( $username, $email );

		return $result['errors'];
	}

	/**
	 * The reasons logged for registrations, oldest first.
	 *
	 * @return string[]
	 */
	private function logged_reasons(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads what the test wrote.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT submission_data FROM %i WHERE log_type = %s ORDER BY log_id', $this->log_table(), 'registration' ) );

		return array_map(
			static function ( $row ): string {
				$data = (array) json_decode( (string) $row, true );

				return (string) ( $data['failed'] ?? '' );
			},
			$rows
		);
	}

	/**
	 * A bot that fills the trap is refused with the configured message, in the
	 * error wp-signup.php prints above the form, logged and shared with what
	 * it submitted.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_filled_trap_is_refused_logged_and_shared(): void {
		$this->set_setting( 'share_data', 'enabled', 'settings' );

		$shared = array();
		add_action(
			'zerospam_share_detection',
			static function ( $details ) use ( &$shared ): void {
				$shared[] = $details;
			}
		);

		$errors = $this->sign_up( array( Utilities::get_honeypot() => 'filled-by-bot' ) );

		$this->assertSame( 'No spam here, thanks.', $errors->get_error_message( 'generic' ) );
		$this->assertSame( array( 'honeypot' ), $this->logged_reasons() );

		$this->assertCount( 1, $shared );
		$this->assertSame( 'registration', $shared[0]['type'] );
		$this->assertSame( 'honeypot', $shared[0]['failed'] );
		$this->assertSame( self::USERNAME, $shared[0]['user_login'] );
		$this->assertSame( self::EMAIL, $shared[0]['user_email'] );
	}

	/**
	 * A blocked email domain, disposable domains included, is refused.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_blocked_email_domain_is_refused(): void {
		$this->set_setting( 'blocked_email_domains', "mailinator.com\nexample.org", 'settings' );

		$errors = $this->sign_up( array(), self::USERNAME, 'someone@mailinator.com' );

		$this->assertSame( 'No spam here, thanks.', $errors->get_error_message( 'generic' ) );
		$this->assertSame( array( 'blocked_email_domain' ), $this->logged_reasons() );
	}

	/**
	 * A submission without the browser check's key is refused; one with a key
	 * this site issued goes through.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_browser_check_applies_to_the_sign_up_form(): void {
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );
		( new DavidWalsh() )->init();

		$errors = $this->sign_up();

		$this->assertSame( 'No spam here, thanks.', $errors->get_error_message( 'generic' ) );
		$this->assertSame( array( 'david_walsh' ), $this->logged_reasons() );
	}

	/**
	 * A person who passes the browser check signs up.
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_valid_browser_key_goes_through(): void {
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );
		( new DavidWalsh() )->init();

		$errors = $this->sign_up( array( 'zerospam_david_walsh_key' => DavidWalsh::generate_token() ) );

		$this->assertFalse( $errors->has_errors(), implode( ' ', $errors->get_error_messages() ) );
		$this->assertSame( array(), $this->logged_reasons() );
	}

	/**
	 * Stop Forum Spam looks up the sign-up's email; a listed one is refused.
	 * The username is never sent.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_stop_forum_spam_refuses_a_listed_email(): void {
		$this->set_setting( 'stop_forum_spam', 'enabled', 'stop_forum_spam' );
		$this->mock_http(
			self::SFS_API,
			array(
				'success' => 1,
				'email'   => array(
					'appears'    => 1,
					'frequency'  => 3,
					'confidence' => 90,
				),
			)
		);

		( new StopForumSpam() )->init();

		$requested = array();
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$requested ) {
				$requested[] = $url;

				return $pre;
			},
			0,
			3
		);

		$errors = $this->sign_up();

		$this->assertSame( 'No spam here, thanks.', $errors->get_error_message( 'generic' ) );
		$this->assertSame( array( 'stop_forum_spam_email' ), $this->logged_reasons() );
		$this->assertStringNotContainsString( self::USERNAME, implode( ' ', $requested ) );
	}

	/**
	 * A clean sign-up goes through and leaves no trace in the log.
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_clean_sign_up_goes_through(): void {
		$errors = $this->sign_up( array( Utilities::get_honeypot() => '' ) );

		$this->assertFalse( $errors->has_errors(), implode( ' ', $errors->get_error_messages() ) );
		$this->assertSame( array(), $this->logged_reasons() );
	}

	/**
	 * Logging is the site's choice, as on a single site: the refusal is not.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_refused_without_a_log_row_when_logging_is_off(): void {
		$this->set_setting( 'log_blocked_registrations', false, 'registration' );

		$errors = $this->sign_up( array( Utilities::get_honeypot() => 'filled-by-bot' ) );

		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( array(), $this->logged_reasons() );
	}

	/**
	 * The message goes through the page unescaped, so it is cleaned here.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_signup
	 */
	public function test_message_is_safe_to_print(): void {
		$this->set_setting( 'registration_spam_message', 'No<script>alert(1)</script> spam', 'registration' );

		$errors = $this->sign_up( array( Utilities::get_honeypot() => 'filled-by-bot' ) );

		$this->assertStringContainsString( 'spam', $errors->get_error_message( 'generic' ) );
		$this->assertStringNotContainsString( '<script', $errors->get_error_message( 'generic' ) );
	}

	/**
	 * With the setting off, nothing on the page is checked.
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::init
	 */
	public function test_setting_off_checks_nothing(): void {
		$this->set_setting( 'verify_registrations', false, 'registration' );

		$errors = $this->sign_up( array( Utilities::get_honeypot() => 'filled-by-bot' ) );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( array(), $this->logged_reasons() );
	}

	/**
	 * A logged-in person is not checked, as everywhere else.
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::init
	 */
	public function test_logged_in_person_is_not_checked(): void {
		$this->as_role( 'subscriber' );

		$errors = $this->sign_up( array( Utilities::get_honeypot() => 'filled-by-bot' ) );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( array(), $this->logged_reasons() );
	}

	/**
	 * Core runs the same validation for other callers (wp user create, REST,
	 * the admin's Add User, plugins with forms of their own), which have no
	 * trap or browser key: only wp-signup.php is checked.
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::init
	 */
	public function test_other_callers_of_the_validation_are_not_checked(): void {
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );
		( new DavidWalsh() )->init();
		( new Registration() )->init();

		$result = wpmu_validate_user_signup( self::USERNAME, self::EMAIL );

		$this->assertFalse( $result['errors']->has_errors(), implode( ' ', $result['errors']->get_error_messages() ) );
		$this->assertSame( array(), $this->logged_reasons() );
	}

	/**
	 * Both steps of the form carry the trap and load the browser check, and
	 * the browser check knows the form.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Modules\Registration\Registration::init
	 */
	public function test_the_form_carries_the_checks(): void {
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );
		( new DavidWalsh() )->init();
		( new Registration() )->init();

		foreach ( array( 'validate-user', 'validate-site' ) as $step ) {
			ob_start();
			do_action( 'signup_hidden_fields', $step );
			$this->assertStringContainsString( 'name="' . Utilities::get_honeypot() . '"', (string) ob_get_clean(), $step );
		}

		wp_register_script( 'zerospam-davidwalsh', 'https://example.com/script.js', array(), '1', true );
		do_action( 'before_signup_form' );

		$this->assertTrue( wp_script_is( 'zerospam-davidwalsh', 'enqueued' ) );
		$this->assertContains( '#setupform', DavidWalsh::get_all_selectors() );
	}

	/**
	 * A blocked address is stopped before the form, as it is at wp-login.php
	 * on a single site: wp-signup.php never fires `template_redirect`.
	 *
	 * @group known-defect
	 *
	 * @covers \ZeroSpam\Core\Access::init
	 */
	public function test_blocked_address_is_stopped_before_the_form(): void {
		$this->block( self::DEFAULT_IP );

		( new Access() )->init();

		$this->assertTrue(
			Access::intercept_termination(
				static function (): void {
					do_action( 'before_signup_header' );
				}
			)
		);
	}
}
