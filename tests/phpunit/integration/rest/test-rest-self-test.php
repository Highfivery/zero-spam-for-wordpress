<?php
/**
 * Tests for zero-spam/v1/self-test.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\Self_Test_Controller;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * The self-test runs its five steps for administrators, at most once a
 * minute, asks only the services that are on, and writes nothing to the
 * detection log.
 *
 * @covers \ZeroSpam\Includes\Rest\Self_Test_Controller
 */
class Test_Rest_Self_Test extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/self-test';

	/**
	 * Stop Forum Spam test lookup.
	 */
	const SFS = '#^https://api\.stopforumspam\.org/api\?ip=192\.0\.2\.1&json$#';

	/**
	 * Zero Spam network test lookup.
	 */
	const ZERO_SPAM = '#/wp-json/v3/query\?.*ip=192\.0\.2\.1#';

	/**
	 * Logs in an administrator, turns the checks on and clears the rate limit.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'zerospam', 'settings', 'comments', 'davidwalsh', 'stop_forum_spam' ) as $module ) {
			delete_option( "zero-spam-{$module}" );
		}
		delete_transient( Self_Test_Controller::LOCK_TRANSIENT );
		update_option( 'disallowed_keys', '' );

		$this->set_setting( 'verify_comments', 'enabled', 'comments' );
		$this->set_setting( 'log_blocked_comments', 'enabled', 'comments' );
		$this->set_setting( 'share_data', 'enabled', 'settings' );
		$this->set_setting( 'davidwalsh', 'enabled', 'davidwalsh' );

		$this->as_role( 'administrator' );
	}

	/**
	 * Runs the self-test and returns its steps by ID.
	 *
	 * @return array{0: WP_REST_Response, 1: array<string, array<string, mixed>>}
	 */
	private function run_self_test(): array {
		delete_transient( Self_Test_Controller::LOCK_TRANSIENT );
		$response = $this->rest( 'POST', self::ROUTE );
		$data     = $response->get_data();

		return array( $response, isset( $data['steps'] ) ? array_column( $data['steps'], null, 'id' ) : array() );
	}

	/**
	 * Only administrators run it.
	 */
	public function test_permissions(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'POST', self::ROUTE )->get_status() );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( 'POST', self::ROUTE )->get_status() );

		$this->assertSame( 404, $this->rest( 'GET', self::ROUTE )->get_status(), 'Only POST runs it.' );
		$this->assertFalse( get_transient( Self_Test_Controller::LOCK_TRANSIENT ), 'A refused request does not use up the minute.' );
	}

	/**
	 * Every step reports a status, a message and its duration.
	 */
	public function test_every_step_is_reported(): void {
		list( $response, $steps ) = $this->run_self_test();

		$data = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'honeypot', 'browser_check', 'lists', 'reputation', 'ip_source' ), array_keys( $steps ) );
		foreach ( $steps as $step ) {
			$this->assertContains( $step['status'], array( 'pass', 'warn', 'fail' ) );
			$this->assertIsString( $step['message'] );
			$this->assertNotSame( '', $step['message'] );
			$this->assertIsInt( $step['ms'] );
		}
		$this->assertContains( $data['status'], array( 'pass', 'warn', 'fail' ) );
		$this->assertNotFalse( strtotime( $data['ran_at'] ) );
	}

	/**
	 * The trapped comment is stopped, and nothing is logged or shared, with logging and sharing on.
	 */
	public function test_writes_nothing_to_the_log(): void {
		$rows   = $this->count_log_rows();
		$shared = did_action( 'zerospam_share_detection' );

		list( , $steps ) = $this->run_self_test();

		$this->assertSame( 'pass', $steps['honeypot']['status'], $steps['honeypot']['message'] );
		$this->assertSame( $rows, $this->count_log_rows(), 'The self-test wrote to the detection log.' );
		$this->assertSame( $shared, did_action( 'zerospam_share_detection' ), 'The self-test shared a detection.' );
		$this->assertSame( 'enabled', \ZeroSpam\Core\Settings::get_settings( 'log_blocked_comments' ), 'Logging is on again afterwards.' );
		$this->assertSame( array(), $_POST, 'The request data is restored.' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Asserted, not used.
	}

	/**
	 * The trapped comment never reaches the blocked page: no page header
	 * (HTML content type, CSP, no-cache) is sent, so the REST answer stays JSON.
	 */
	public function test_sends_no_page_headers(): void {
		list( $response, $steps ) = $this->run_self_test();

		$this->assertSame( 'pass', $steps['honeypot']['status'], $steps['honeypot']['message'] );
		$this->assertSame( array(), Stubs::$headers, 'The self-test sent the blocked page\'s headers: ' . implode( ' | ', Stubs::$headers ) );
		$this->assertFalse( \ZeroSpam\Core\Access::is_intercepting_termination(), 'Interception is still on after the self-test.' );
		$this->assertIsArray( $response->get_data() );

		foreach ( $response->get_headers() as $name => $value ) {
			$this->assertStringNotContainsStringIgnoringCase( 'text/html', (string) $value, $name );
			$this->assertStringNotContainsStringIgnoringCase( 'content-security-policy', (string) $name );
		}
	}

	/**
	 * Interception ends when the callback stops, is stopped, or throws.
	 */
	public function test_interception_is_reset_after_the_callback(): void {
		$this->assertFalse( \ZeroSpam\Core\Access::intercept_termination( static function (): void {} ) );
		$this->assertFalse( \ZeroSpam\Core\Access::is_intercepting_termination(), 'Still on after a callback that did not stop.' );

		$this->assertTrue(
			\ZeroSpam\Core\Access::intercept_termination(
				static function (): void {
					\ZeroSpam\Core\Access::terminate_execution( 'Stopped', 'Stopped.' );
				}
			)
		);
		$this->assertFalse( \ZeroSpam\Core\Access::is_intercepting_termination(), 'Still on after a stopped callback.' );

		$thrown = null;
		try {
			\ZeroSpam\Core\Access::intercept_termination(
				static function (): void {
					throw new RuntimeException( 'Unrelated failure.' );
				}
			);
		} catch ( RuntimeException $e ) {
			$thrown = $e;
		}

		$this->assertInstanceOf( RuntimeException::class, $thrown, 'An unrelated exception is passed on.' );
		$this->assertFalse( \ZeroSpam\Core\Access::is_intercepting_termination(), 'Still on after a callback that threw.' );
		$this->assertSame( array(), Stubs::$headers );
	}

	/**
	 * With comment protection off the honeypot still works but a warning says comments are not checked.
	 */
	public function test_honeypot_warns_when_comments_are_off(): void {
		$this->set_setting( 'verify_comments', false, 'comments' );

		list( , $steps ) = $this->run_self_test();

		$this->assertSame( 'warn', $steps['honeypot']['status'] );
	}

	/**
	 * The browser check passes when on and warns when off.
	 */
	public function test_browser_check(): void {
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'pass', $steps['browser_check']['status'] );

		$this->set_setting( 'davidwalsh', false, 'davidwalsh' );
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'warn', $steps['browser_check']['status'] );
	}

	/**
	 * The lists step counts each list.
	 */
	public function test_lists_counts(): void {
		update_option( 'zerospam_blocked_email_domains', "a.example\nb.example", false );
		update_option( 'disallowed_keys', "viagra\ncasino\n\nloans" );
		$this->set_setting( 'ip_whitelist', "198.51.100.1\n198.51.100.2", 'settings' );
		$this->block( '203.0.113.50' );

		list( , $steps ) = $this->run_self_test();

		$counts = $steps['lists']['counts'];
		$this->assertSame( 'pass', $steps['lists']['status'] );
		$this->assertSame( 2, $counts['blocked_email_domains'] );
		$this->assertSame( 3, $counts['disallowed_words'] );
		$this->assertSame( 2, $counts['allowed_ips'] );
		$this->assertGreaterThanOrEqual( 1, $counts['blocked_ips'] );

		update_option( 'disallowed_keys', '' );
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'warn', $steps['lists']['status'], 'An empty disallowed words list is worth a look.' );
	}

	/**
	 * No service is asked when none is on.
	 */
	public function test_reputation_asks_nothing_when_off(): void {
		list( , $steps ) = $this->run_self_test();

		$this->assertSame( 'warn', $steps['reputation']['status'] );
		$this->assertSame( array(), $steps['reputation']['services'] );
		$this->assertSame( array(), HttpMock::requests(), 'The self-test made a request to a service that is off.' );
	}

	/**
	 * Stop Forum Spam is asked about a TEST-NET address; an error answer fails the step.
	 */
	public function test_reputation_stop_forum_spam(): void {
		$this->set_setting( 'stop_forum_spam', 'enabled', 'stop_forum_spam' );
		$this->mock_http( self::SFS, array( 'success' => 1 ) );

		list( , $steps ) = $this->run_self_test();

		$this->assertSame( 'pass', $steps['reputation']['status'] );
		$this->assertSame( 'stop_forum_spam', $steps['reputation']['services'][0]['id'] );
		$this->assertTrue( $steps['reputation']['services'][0]['ok'] );
		$this->assertSame( 1, HttpMock::hits( self::SFS ) );
		$this->assertSame( 0, HttpMock::hits( self::ZERO_SPAM ), 'The Zero Spam network is asked only with Enhanced Protection.' );

		$this->mock_http( self::SFS, 'Service Unavailable', 500 );
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'fail', $steps['reputation']['status'] );
		$this->assertSame( 500, $steps['reputation']['services'][0]['code'] );

		$this->mock_http( self::SFS, '<html>not json</html>' );
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'fail', $steps['reputation']['status'], 'A malformed answer fails.' );
	}

	/**
	 * With Enhanced Protection the Zero Spam network is asked as well.
	 */
	public function test_reputation_zero_spam_network(): void {
		$this->set_setting( 'zerospam', 'enabled', 'zerospam' );
		$this->set_setting( 'zerospam_license', 'selftest-key-0001', 'zerospam' );
		$this->mock_http(
			self::ZERO_SPAM,
			array(
				'status'        => 200,
				'body_response' => array(),
			)
		);

		list( , $steps ) = $this->run_self_test();

		$this->assertSame( 'pass', $steps['reputation']['status'] );
		$this->assertSame( array( 'zero_spam' ), array_column( $steps['reputation']['services'], 'id' ) );
		$this->assertSame( 1, HttpMock::hits( self::ZERO_SPAM ) );
		$this->assertStringNotContainsString( 'selftest-key', (string) wp_json_encode( $steps ) );
	}

	/**
	 * The Zero Spam network test lookup is a plain request: it is not counted
	 * in the API usage statistics and a failure does not move the circuit breaker.
	 */
	public function test_zero_spam_ping_leaves_usage_and_breaker_alone(): void {
		global $wpdb;

		$this->set_setting( 'zerospam', 'enabled', 'zerospam' );
		$this->set_setting( 'zerospam_license', 'selftest-key-0002', 'zerospam' );
		$this->mock_http( self::ZERO_SPAM, 'Server error', 500 );
		delete_transient( 'zero_spam_failure_count' );

		$usage = $wpdb->base_prefix . 'wpzerospam_api_usage';
		$rows  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $usage ) );

		list( , $steps ) = $this->run_self_test();

		$this->assertSame( 'fail', $steps['reputation']['status'] );
		$this->assertSame( 1, HttpMock::hits( self::ZERO_SPAM ) );
		$this->assertSame( $rows, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $usage ) ), 'The test lookup was recorded as API usage.' );
		$this->assertFalse( get_transient( 'zero_spam_failure_count' ), 'The test lookup moved the circuit breaker.' );
	}

	/**
	 * Where the visitor address comes from.
	 */
	public function test_ip_source(): void {
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'direct', $steps['ip_source']['source'] );
		$this->assertSame( self::DEFAULT_IP, $steps['ip_source']['ip'] );
		$this->assertSame( 'pass', $steps['ip_source']['status'] );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
		\ZeroSpam\Core\User::reset_cache();
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'untrusted_proxy', $steps['ip_source']['source'] );
		$this->assertSame( 'warn', $steps['ip_source']['status'] );
		$this->assertSame( self::DEFAULT_IP, $steps['ip_source']['ip'], 'An untrusted header is never used.' );

		$this->set_setting( 'trusted_proxies', "203.0.113.0/24\n", 'settings' );
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'trusted_proxy', $steps['ip_source']['source'] );
		$this->assertSame( '198.51.100.7', $steps['ip_source']['ip'] );

		$this->set_request( '/', '173.245.48.10' );
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.8';
		\ZeroSpam\Core\User::reset_cache();
		list( , $steps ) = $this->run_self_test();
		$this->assertSame( 'cloudflare', $steps['ip_source']['source'] );
		$this->assertSame( '198.51.100.8', $steps['ip_source']['ip'] );
	}

	/**
	 * A second run within a minute is refused with the seconds to wait.
	 */
	public function test_rate_limit(): void {
		$first  = $this->rest( 'POST', self::ROUTE );
		$second = $this->rest( 'POST', self::ROUTE );

		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 429, $second->get_status() );
		$this->assertSame( 'zerospam_self_test_rate_limited', $second->get_data()['code'] );
		$retry = $second->get_data()['data']['retry_after'];
		$this->assertGreaterThan( 0, $retry );
		$this->assertLessThanOrEqual( 60, $retry );

		set_transient( Self_Test_Controller::LOCK_TRANSIENT, time() - 61, 60 );
		$this->assertSame( 200, $this->rest( 'POST', self::ROUTE )->get_status(), 'After a minute it runs again.' );
	}
}
