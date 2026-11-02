<?php
/**
 * Tests for the Stop Forum Spam module.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Modules\Registration\Registration;
use ZeroSpam\Modules\StopForumSpam;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\TestCase;

/**
 * Stop Forum Spam lookups and caching.
 *
 * @covers \ZeroSpam\Modules\StopForumSpam
 */
class Test_Stop_Forum_Spam extends TestCase {

	const API = '#^https://api\.stopforumspam\.org/api\?#';

	/**
	 * Enables the module.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_setting( 'stop_forum_spam', 'enabled', 'stop_forum_spam' );
	}

	/**
	 * A lookup is cached, so repeating it makes no second API request.
	 *
	 * Regression: query() read wp_cache_get() from the default group but wrote
	 * wp_cache_set() to the `zerospam` group, so the cache never hit.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_repeated_query_is_served_from_cache(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'ip'      => array( 'appears' => 0 ),
			)
		);

		$module = new StopForumSpam();
		$module->query( array( 'ip' => self::DEFAULT_IP ) );
		$module->query( array( 'ip' => self::DEFAULT_IP ) );

		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'The second lookup went back to the API.' );
	}

	/**
	 * Different lookups never share a cache entry.
	 *
	 * The key used to be built from the values alone through sanitize_title(),
	 * which strips the colons from IPv6 addresses, so these two IPs collided.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_distinct_lookups_do_not_share_a_cache_entry(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'ip'      => array( 'appears' => 0 ),
			)
		);

		$module = new StopForumSpam();
		$module->query( array( 'ip' => '2001:db8::1:23' ) );
		$module->query( array( 'ip' => '2001:db8::12:3' ) );

		$this->assertSame( 2, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'The second IP was answered from the first IP\'s cache entry.' );
	}

	/**
	 * An error answer is not cached, so the next lookup asks the API again.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_failed_lookup_is_not_cached(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 0,
				'error'   => 'rate limit exceeded',
			)
		);

		$module = new StopForumSpam();
		$module->query( array( 'ip' => self::DEFAULT_IP ) );
		$module->query( array( 'ip' => self::DEFAULT_IP ) );

		$this->assertSame( 2, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'An error answer was served from the cache.' );
	}

	/**
	 * The request carries only the lookup parameters and the JSON flag.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_request_url_carries_only_the_lookup_parameters(): void {
		$this->mock_http( self::API, array( 'success' => 1 ) );

		( new StopForumSpam() )->query( array( 'ip' => self::DEFAULT_IP ) );

		$requests = \ZeroSpam\Tests\HttpMock::requests();
		$this->assertCount( 1, $requests );
		$this->assertSame( 'https://api.stopforumspam.org/api?ip=' . rawurlencode( self::DEFAULT_IP ) . '&json=', $requests[0]['url'] );
	}

	/**
	 * The module's comment check runs through the
	 * zerospam_preprocess_comment_submission filter, called the way the
	 * comments module calls it (three arguments, the callback accepts two).
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::init
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_comments
	 */
	public function test_comment_filter_reports_a_listed_email(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'email'   => array(
					'appears'    => 1,
					'confidence' => 90,
				),
			)
		);

		( new StopForumSpam() )->init();

		$errors = apply_filters(
			'zerospam_preprocess_comment_submission',
			array(),
			array( 'comment_author_email' => 'spammer@example.com' ),
			'comment_spam_message'
		);

		$this->assertArrayHasKey( 'zerospam_stop_forum_spam_email', $errors );
		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( self::API ) );
	}

	/**
	 * A listed username, email or IP is refused through the filter, called the
	 * way the registration module calls it, without a fatal error.
	 *
	 * @dataProvider data_listed_fields
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 *
	 * @param string $field Field the service lists.
	 */
	public function test_registration_filter_reports_a_listed_field( string $field ): void {
		$this->mock_http( self::API, $this->answer( array( $field => 90.5 ) ) );

		( new StopForumSpam() )->init();

		$errors = apply_filters(
			'zerospam_preprocess_registration_submission',
			array(),
			array(
				'user_login' => 'spammer',
				'user_email' => 'spammer@example.com',
			),
			'registration_spam_message'
		);

		$this->assertSame( array( 'zerospam_stop_forum_spam_' . $field ), array_keys( $errors ) );
		$this->assertSame( 1, HttpMock::hits( self::API ) );
	}

	/**
	 * Fields the service can list on a registration.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_listed_fields(): array {
		return array(
			'username' => array( 'username' ),
			'email'    => array( 'email' ),
			'ip'       => array( 'ip' ),
		);
	}

	/**
	 * A registration WordPress is handling is refused with the configured
	 * message, logged with its reason and type, and shared. No account is made.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_form
	 */
	public function test_listed_email_is_refused_logged_and_shared_on_wordpress_registration(): void {
		$this->enable_registration_protection();
		$this->set_setting( 'registration_spam_message', 'No spam here, thanks.', 'registration' );
		$this->set_setting( 'share_data', 'enabled', 'settings' );
		$this->mock_http( self::API, $this->answer( array( 'email' => 99 ) ) );

		$shared = array();
		add_action(
			'zerospam_share_detection',
			static function ( $details ) use ( &$shared ): void {
				$shared[] = $details;
			}
		);

		( new StopForumSpam() )->init();
		( new Registration() )->init();

		$result = register_new_user( 'sfsrefused', 'sfs-refused@example.com' );

		$this->assertWPError( $result );
		$this->assertSame( 'No spam here, thanks.', $result->get_error_message( 'zerospam_error' ) );
		$this->assertFalse( get_user_by( 'login', 'sfsrefused' ), 'The account was created.' );

		$rows = $this->log_rows( 'registration' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'stop_forum_spam_email', $rows[0]['failed'] );
		$this->assertSame( 'sfsrefused', $rows[0]['user_login'] );
		$this->assertSame( 'sfs-refused@example.com', $rows[0]['user_email'] );

		$this->assertCount( 1, $shared );
		$this->assertSame( 'registration', $shared[0]['type'] );
		$this->assertSame( 'stop_forum_spam_email', $shared[0]['failed'] );
	}

	/**
	 * Every listed field is its own detection, as the module's siblings do.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_each_listed_field_logs_its_own_reason(): void {
		$this->enable_registration_protection();
		$this->mock_http(
			self::API,
			$this->answer(
				array(
					'username' => 90,
					'email'    => 90,
					'ip'       => 90,
				)
			)
		);

		( new StopForumSpam() )->init();
		( new Registration() )->init();

		$this->assertWPError( register_new_user( 'sfsrefused', 'sfs-refused@example.com' ) );

		$failed = array_column( $this->log_rows( 'registration' ), 'failed' );
		sort( $failed );
		$this->assertSame( array( 'stop_forum_spam_email', 'stop_forum_spam_ip', 'stop_forum_spam_username' ), $failed );
		$this->assertSame( 1, HttpMock::hits( self::API ), 'One registration must make one request.' );
	}

	/**
	 * A clean registration goes through.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_clean_registration_passes(): void {
		$this->enable_registration_protection();
		$this->mock_http( self::API, $this->answer( array() ) );

		( new StopForumSpam() )->init();
		( new Registration() )->init();

		$user_id = register_new_user( 'sfsclean', 'sfs-clean@example.com' );

		$this->assertIsInt( $user_id );
		$this->assertSame( array(), $this->log_rows( 'registration' ) );
		$this->assertSame( 1, HttpMock::hits( self::API ), 'The registration was not checked.' );
	}

	/**
	 * Another registration path that fires the filter is checked too, with the
	 * field names such forms commonly use.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_other_registration_paths_are_checked(): void {
		$this->mock_http( self::API, $this->answer( array( 'username' => 90 ) ) );

		$errors = ( new StopForumSpam() )->preprocess_registrations(
			array(),
			array(
				'username' => 'spammer',
				'email'    => 'spammer@example.com',
			),
			'registration_spam_message'
		);

		$this->assertArrayHasKey( 'zerospam_stop_forum_spam_username', $errors );
		$this->assertSame(
			array(
				'username' => 'spammer',
				'email'    => 'spammer@example.com',
				'ip'       => self::DEFAULT_IP,
				'json'     => '',
			),
			$this->last_query()
		);
	}

	/**
	 * Errors other checks added before this one are kept.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_earlier_errors_are_kept(): void {
		$this->mock_http( self::API, $this->answer( array( 'email' => 90 ) ) );

		$errors = ( new StopForumSpam() )->preprocess_registrations(
			array( 'zerospam_david_walsh' => 'Earlier' ),
			array( 'user_email' => 'spammer@example.com' ),
			'registration_spam_message'
		);

		$this->assertSame( array( 'zerospam_david_walsh', 'zerospam_stop_forum_spam_email' ), array_keys( $errors ) );
	}

	/**
	 * The module's own switch turns the check off.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::init
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_disabled_module_makes_no_request(): void {
		$this->enable_registration_protection();
		$this->set_setting( 'stop_forum_spam', false, 'stop_forum_spam' );
		$this->mock_http( self::API, $this->answer( array( 'email' => 99 ) ) );

		$module = new StopForumSpam();
		$module->init();
		( new Registration() )->init();

		$this->assertIsInt( register_new_user( 'sfsoff', 'sfs-off@example.com' ) );
		$this->assertSame( array(), $module->preprocess_registrations( array(), array( 'user_email' => 'a@example.com' ), 'registration_spam_message' ) );
		$this->assertSame( 0, HttpMock::hits( self::API ) );
	}

	/**
	 * Access::process() decides whether the check runs: a whitelisted visitor
	 * or a logged-in user is never looked up.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::init
	 */
	public function test_visitors_access_process_skips_are_not_checked(): void {
		$this->mock_http( self::API, $this->answer( array( 'email' => 99 ) ) );

		$post = array( 'user_email' => 'spammer@example.com' );

		$this->set_setting( 'ip_whitelist', self::DEFAULT_IP, 'settings' );
		$this->assertFalse( Access::process(), 'The whitelisted visitor should be skipped.' );
		( new StopForumSpam() )->init();
		$this->assertSame( array(), apply_filters( 'zerospam_preprocess_registration_submission', array(), $post, 'registration_spam_message' ) );

		$this->set_setting( 'ip_whitelist', '', 'settings' );
		$this->as_role( 'subscriber' );
		$this->assertFalse( Access::process(), 'The logged-in user should be skipped.' );
		( new StopForumSpam() )->init();
		$this->assertSame( array(), apply_filters( 'zerospam_preprocess_registration_submission', array(), $post, 'registration_spam_message' ) );

		$this->assertSame( 0, HttpMock::hits( self::API ) );
	}

	/**
	 * Only a confidence at or above the configured minimum counts.
	 *
	 * @dataProvider data_confidence
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 *
	 * @param mixed $minimum    Configured minimum.
	 * @param array $field      The answer's `email` entry.
	 * @param bool  $is_refused Whether the registration is refused.
	 */
	public function test_confidence_threshold( $minimum, array $field, bool $is_refused ): void {
		$this->set_setting( 'stop_forum_spam_confidence_min', $minimum, 'stop_forum_spam' );
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'email'   => $field,
			)
		);

		$errors = ( new StopForumSpam() )->preprocess_registrations( array(), array( 'user_email' => 'a@example.com' ), 'registration_spam_message' );

		$this->assertSame( $is_refused, isset( $errors['zerospam_stop_forum_spam_email'] ) );
	}

	/**
	 * Minimums and answers around the threshold.
	 *
	 * @return array<string, array{0: mixed, 1: array, 2: bool}>
	 */
	public function data_confidence(): array {
		return array(
			'below the default'          => array(
				50,
				array(
					'appears'    => 1,
					'confidence' => '49.99',
				),
				false,
			),
			'exactly the default'        => array(
				50,
				array(
					'appears'    => 1,
					'confidence' => '50.00',
				),
				true,
			),
			'below a raised minimum'     => array(
				80,
				array(
					'appears'    => 1,
					'confidence' => 75,
				),
				false,
			),
			'above a raised minimum'     => array(
				80,
				array(
					'appears'    => 1,
					'confidence' => 80.5,
				),
				true,
			),
			'high but does not appear'   => array(
				50,
				array(
					'appears'    => 0,
					'confidence' => 99,
				),
				false,
			),
			'appears with no confidence' => array(
				50,
				array(
					'appears'   => 1,
					'frequency' => 3,
				),
				false,
			),
		);
	}

	/**
	 * An answer that is not a clean success never blocks anyone, never
	 * raises an error and is not remembered.
	 *
	 * @dataProvider data_failed_answers
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_comments
	 * @covers \ZeroSpam\Modules\StopForumSpam::access_check
	 *
	 * @param string $body Response body.
	 * @param int    $code HTTP status.
	 */
	public function test_failed_answers_fail_open( string $body, int $code ): void {
		$this->mock_http( self::API, $body, $code );

		$module = new StopForumSpam();

		for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
			$this->assertSame( array(), $module->preprocess_registrations( array(), array( 'user_email' => 'a@example.com' ), 'registration_spam_message' ) );
			$this->assertSame( array(), $module->preprocess_comments( array(), array( 'comment_author_email' => 'a@example.com' ), 'comment_spam_message' ) );
			$this->assertFalse( $module->access_check( array(), self::DEFAULT_IP )['stop_forum_spam']['blocked'] );
		}

		$this->assertSame( 6, HttpMock::hits( self::API ), 'A failed answer was served from the cache.' );
	}

	/**
	 * Answers that must not count.
	 *
	 * @return array<string, array{0: string, 1: int}>
	 */
	public function data_failed_answers(): array {
		$listed = (string) wp_json_encode(
			array(
				'success'  => 1,
				'username' => array(
					'appears'    => 1,
					'confidence' => 99,
				),
				'email'    => array(
					'appears'    => 1,
					'confidence' => 99,
				),
				'ip'       => array(
					'appears'    => 1,
					'confidence' => 99,
				),
			)
		);

		return array(
			'500 with a page'          => array( '<html>Server error</html>', 500 ),
			'500 with a listed answer' => array( $listed, 500 ),
			'429 with a listed answer' => array( $listed, 429 ),
			'an empty body'            => array( '', 200 ),
			'not JSON'                 => array( '<html>Maintenance</html>', 200 ),
			'cut-off JSON'             => array( '{"success":1,"email":{"appears":1,"confid', 200 ),
			'JSON null'                => array( 'null', 200 ),
			'a JSON string'            => array( '"listed"', 200 ),
			'a JSON list'              => array( '[1,2,3]', 200 ),
			'no success'               => array( '{"email":{"appears":1,"confidence":99}}', 200 ),
			'success 0, rate limited'  => array( '{"success":0,"error":"rate limit exceeded","email":{"appears":1,"confidence":99}}', 200 ),
		);
	}

	/**
	 * Success with fields of the wrong shape counts as "not listed".
	 *
	 * @dataProvider data_malformed_fields
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 * @covers \ZeroSpam\Modules\StopForumSpam::access_check
	 *
	 * @param mixed $field The answer's entry for each field.
	 */
	public function test_malformed_fields_fail_open( $field ): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'email'   => $field,
				'ip'      => $field,
			)
		);

		$module = new StopForumSpam();

		$this->assertSame( array(), $module->preprocess_registrations( array(), array( 'user_email' => 'a@example.com' ), 'registration_spam_message' ) );
		$this->assertFalse( $module->access_check( array(), self::DEFAULT_IP )['stop_forum_spam']['blocked'] );
	}

	/**
	 * Malformed field values.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function data_malformed_fields(): array {
		return array(
			'a string'        => array( 'listed' ),
			'a number'        => array( 99 ),
			'null'            => array( null ),
			'text confidence' => array(
				array(
					'appears'    => 1,
					'confidence' => 'very',
				),
			),
			'number and text' => array(
				array(
					'appears'    => 1,
					'confidence' => '99 percent',
				),
			),
			'list confidence' => array(
				array(
					'appears'    => 1,
					'confidence' => array( 99 ),
				),
			),
		);
	}

	/**
	 * A network's locked minimum applies to a site's lookups.
	 *
	 * @group ms-required
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_locked_network_minimum_applies(): void {
		$this->set_setting( 'stop_forum_spam_confidence_min', 10, 'stop_forum_spam' );
		$this->set_network_setting( 'stop_forum_spam_confidence_min', 90, true );
		$this->mock_http( self::API, $this->answer( array( 'email' => 75 ) ) );

		$errors = ( new StopForumSpam() )->preprocess_registrations( array(), array( 'user_email' => 'a@example.com' ), 'registration_spam_message' );

		$this->assertSame( array(), $errors );
	}

	/**
	 * A timeout lets the registration through and is retried next time.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_timeout_fails_open(): void {
		$this->mock_http( self::API, $this->answer( array( 'email' => 99 ) ) );

		$args = array();
		add_filter(
			'pre_http_request',
			static function ( $preempt, $request ) use ( &$args ) {
				$args[] = $request;

				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
			},
			10,
			2
		);
		$this->set_setting( 'stop_forum_spam_timeout', 3, 'stop_forum_spam' );

		$module = new StopForumSpam();

		for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
			$this->assertSame( array(), $module->preprocess_registrations( array(), array( 'user_email' => 'a@example.com' ), 'registration_spam_message' ) );
		}

		$this->assertCount( 2, $args, 'A timeout was served from the cache.' );
		$this->assertSame( 3, $args[0]['timeout'], 'The configured timeout was not used.' );
		$this->assertSame( StopForumSpam::MAX_RESPONSE_BYTES, $args[0]['limit_response_size'], 'The size of the answer is not limited.' );
	}

	/**
	 * A registration sends its username, email and IP and nothing else from
	 * the form, as readme.txt says.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 * @covers \ZeroSpam\Modules\Registration\Registration::process_form
	 */
	public function test_registration_sends_exactly_username_email_and_ip(): void {
		$this->enable_registration_protection();
		$this->mock_http( self::API, $this->answer( array() ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- A test's form post.
		$_POST = array(
			'user_login'   => '  raw-form-login  ',
			'user_email'   => 'raw-form@example.com',
			'user_pass'    => 'do-not-send',
			'redirect_to'  => 'https://example.com/do-not-send',
			'secret_field' => 'do-not-send',
		);

		try {
			( new StopForumSpam() )->init();
			( new Registration() )->init();

			$this->assertIsInt( register_new_user( 'sfsexact', 'sfs.exact+tag@example.com' ) );
		} finally {
			$_POST = array();
		}

		$this->assertSame(
			array(
				'username' => 'sfsexact',
				'email'    => 'sfs.exact+tag@example.com',
				'ip'       => self::DEFAULT_IP,
				'json'     => '',
			),
			$this->last_query()
		);
		$this->assertStringNotContainsString( 'do-not-send', HttpMock::requests()[0]['url'] );
		$this->assertStringStartsWith( 'https://api.stopforumspam.org/api?', HttpMock::requests()[0]['url'] );
		$this->assertSame( 'GET', HttpMock::requests()[0]['method'] );
	}

	/**
	 * A comment sends its author's email and nothing else.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_comments
	 */
	public function test_comment_sends_only_the_email(): void {
		$this->mock_http( self::API, $this->answer( array() ) );

		( new StopForumSpam() )->preprocess_comments(
			array(),
			array(
				'comment_author_email' => 'author@example.com',
				'comment_content'      => 'do-not-send',
			),
			'comment_spam_message'
		);

		$this->assertSame(
			array(
				'email' => 'author@example.com',
				'json'  => '',
			),
			$this->last_query()
		);
	}

	/**
	 * Fields that are missing, not text, invalid or absurdly long are not sent.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_unusable_fields_are_not_sent(): void {
		$this->mock_http( self::API, $this->answer( array() ) );

		( new StopForumSpam() )->preprocess_registrations(
			array(),
			array(
				'user_login' => array( 'nested' => 'value' ),
				'user_email' => 'not-an-email',
			),
			'registration_spam_message'
		);
		$this->assertSame(
			array(
				'ip'   => self::DEFAULT_IP,
				'json' => '',
			),
			$this->last_query()
		);

		$this->set_request( '/', '203.0.113.11' );
		( new StopForumSpam() )->preprocess_registrations(
			array(),
			array(
				'user_login' => str_repeat( 'a', 61 ),
				'user_email' => str_repeat( 'a', 250 ) . '@example.com',
			),
			'registration_spam_message'
		);
		$this->assertSame(
			array(
				'ip'   => '203.0.113.11',
				'json' => '',
			),
			$this->last_query()
		);
	}

	/**
	 * With nothing to look up there is no request.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_comments
	 */
	public function test_nothing_to_look_up_makes_no_request(): void {
		$this->mock_http( self::API, $this->answer( array() ) );

		$this->set_request( '/', 'not-an-address' );

		$module = new StopForumSpam();

		$this->assertSame( array(), $module->preprocess_registrations( array(), array(), 'registration_spam_message' ) );
		$this->assertSame( array(), $module->preprocess_comments( array(), array( 'comment_author_email' => '' ), 'comment_spam_message' ) );
		$this->assertSame( 0, HttpMock::hits( self::API ) );
	}

	/**
	 * The result outlasts the request on a site without a persistent object
	 * cache: emptying the object cache, as the end of a request does, must not
	 * lose it. It is kept as long as the setting says.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_result_is_cached_across_requests_for_the_configured_days(): void {
		$this->set_setting( 'stop_forum_spam_cache', 3, 'stop_forum_spam' );
		$this->mock_http( self::API, $this->answer( array( 'email' => 99 ) ) );

		$module = new StopForumSpam();
		$post   = array( 'user_email' => 'spammer@example.com' );

		$first = $module->preprocess_registrations( array(), $post, 'registration_spam_message' );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checks what is stored.
		$timeout = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_timeout_zerospam_sfs_' ) . '%' ) );
		$this->assertEqualsWithDelta( time() + 3 * DAY_IN_SECONDS, $timeout, 30, 'The result is not kept for the configured days.' );

		wp_cache_flush();

		$second = $module->preprocess_registrations( array(), $post, 'registration_spam_message' );

		$this->assertSame( array_keys( $first ), array_keys( $second ) );
		$this->assertArrayHasKey( 'zerospam_stop_forum_spam_email', $second );
		$this->assertSame( 1, HttpMock::hits( self::API ), 'The second attempt went back to the API.' );
	}

	/**
	 * The stored result does not repeat the visitor's details.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_stored_result_leaves_out_the_looked_up_values(): void {
		$this->mock_http(
			self::API,
			array(
				'success'  => 1,
				'username' => array(
					'value'   => 'stored-username',
					'appears' => 0,
				),
				'email'    => array(
					'value'      => 'stored@example.com',
					'appears'    => 1,
					'confidence' => '90.00',
				),
			)
		);

		$module = new StopForumSpam();
		$params = array(
			'username' => 'stored-username',
			'email'    => 'stored@example.com',
		);

		$this->assertStringNotContainsString( 'stored', (string) $module->query( $params ) );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checks what is stored.
		$stored = $wpdb->get_col( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_zerospam_sfs_' ) . '%' ) );

		$this->assertCount( 1, $stored );
		$this->assertStringNotContainsString( 'stored', $stored[0] );
		$this->assertStringContainsString( '90.00', $stored[0] );
	}

	/**
	 * A listed IP on a page request is still blocked, from the same cache.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::access_check
	 */
	public function test_access_check_blocks_a_listed_ip_and_sends_only_the_ip(): void {
		$this->mock_http( self::API, $this->answer( array( 'ip' => 90 ) ) );

		$module = new StopForumSpam();
		$check  = $module->access_check( array(), self::DEFAULT_IP )['stop_forum_spam'];

		$this->assertTrue( $check['blocked'] );
		$this->assertSame( 'blocked', $check['type'] );
		$this->assertSame(
			array(
				'ip'   => self::DEFAULT_IP,
				'json' => '',
			),
			$this->last_query()
		);

		$this->assertTrue( $module->access_check( array(), self::DEFAULT_IP )['stop_forum_spam']['blocked'] );
		$this->assertSame( 1, HttpMock::hits( self::API ) );
	}

	/**
	 * A comment detection is logged with a real reason, not the numeric key.
	 *
	 * Regression: preprocess_comments() appended to the errors array, and the
	 * comments module logs each error's key, so the reason was `0`.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_comments
	 * @covers \ZeroSpam\Modules\Comments\Comments::preprocess_comments
	 */
	public function test_comment_detection_logs_a_real_reason(): void {
		$this->set_setting( 'verify_comments', 'enabled', 'comments' );
		$this->set_setting( 'log_blocked_comments', 'enabled', 'comments' );
		$this->mock_http( self::API, $this->answer( array( 'email' => 90 ) ) );

		( new StopForumSpam() )->init();

		$stopped = Access::intercept_termination(
			static function (): void {
				( new Comments() )->preprocess_comments(
					array(
						'comment_author'       => 'Spammer',
						'comment_author_email' => 'spammer@example.com',
						'comment_author_url'   => '',
						'comment_content'      => 'Hello',
						'comment_agent'        => '',
					)
				);
			}
		);

		$this->assertTrue( $stopped, 'The comment was not stopped.' );

		$rows = $this->log_rows( 'comment' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'stop_forum_spam_email', $rows[0]['failed'] );
	}

	/**
	 * Every reason this module logs has a readable label.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::failed_types
	 */
	public function test_reasons_have_labels(): void {
		$types = apply_filters( 'zerospam_failed_types', array() );

		foreach ( array( 'stop_forum_spam', 'stop_forum_spam_username', 'stop_forum_spam_email', 'stop_forum_spam_ip' ) as $reason ) {
			$this->assertArrayHasKey( $reason, $types );
			$this->assertStringContainsString( 'Stop Forum Spam', $types[ $reason ] );
		}
	}

	/**
	 * Turns registration protection and its log on.
	 */
	private function enable_registration_protection(): void {
		$this->set_setting( 'verify_registrations', 'enabled', 'registration' );
		$this->set_setting( 'log_blocked_registrations', 'enabled', 'registration' );
	}

	/**
	 * Builds a successful answer in the API's shape.
	 *
	 * @param array<string, float|int> $confidence Listed field => confidence.
	 * @return array<string, mixed>
	 */
	private function answer( array $confidence ): array {
		$answer = array( 'success' => 1 );

		foreach ( $confidence as $field => $value ) {
			$answer[ $field ] = array(
				'appears'    => 1,
				'frequency'  => 3,
				'confidence' => $value,
			);
		}

		return $answer;
	}

	/**
	 * The query string of the last request, in the order it was sent.
	 *
	 * @return array<string, string>
	 */
	private function last_query(): array {
		$requests = HttpMock::requests();
		$this->assertNotEmpty( $requests, 'No request was made.' );

		parse_str( (string) wp_parse_url( end( $requests )['url'], PHP_URL_QUERY ), $query );

		return $query;
	}

	/**
	 * The submission data of the log rows of one type.
	 *
	 * @param string $type Log type.
	 * @return array<int, array<string, mixed>>
	 */
	private function log_rows( string $type ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads what the test wrote.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT submission_data FROM %i WHERE log_type = %s ORDER BY log_id', $wpdb->prefix . 'wpzerospam_log', $type ) );

		return array_map(
			static function ( $row ): array {
				return (array) json_decode( (string) $row, true );
			},
			$rows
		);
	}
}
