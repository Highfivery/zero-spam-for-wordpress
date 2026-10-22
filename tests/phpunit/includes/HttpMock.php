<?php
/**
 * Outbound HTTP blocker and mock for the test suite.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

use WP_Error;

/**
 * Answers every WordPress HTTP API request without touching the network.
 *
 * Registered on `pre_http_request` at priority 1 before WordPress loads, so it
 * is part of the hook state the test framework restores after every test.
 * Requests whose URL matches a mocked pattern get the canned response; any
 * other request is recorded and answered with a WP_Error, and the base
 * TestCase fails the test that made it.
 */
final class HttpMock {

	/**
	 * Mocked responses.
	 *
	 * @var array<string, array{body: string, code: int, hits: int}> Pattern => response.
	 */
	private static array $mocks = array();

	/**
	 * Every request seen since the last reset.
	 *
	 * @var array<int, array{url: string, method: string, mocked: bool}>
	 */
	private static array $requests = array();

	/**
	 * URLs of requests that matched no mock since the last reset.
	 *
	 * @var string[]
	 */
	private static array $unmocked = array();

	/**
	 * Hooks the mock into the HTTP API. Call before WordPress loads.
	 */
	public static function register(): void {
		tests_add_filter( 'pre_http_request', array( self::class, 'filter' ), 1, 3 );
	}

	/**
	 * Short-circuits an HTTP API request.
	 *
	 * @param false|array|WP_Error $preempt Response from an earlier filter.
	 * @param array                $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return array|WP_Error
	 */
	public static function filter( $preempt, $args, $url ) {
		$url    = (string) $url;
		$method = is_array( $args ) && ! empty( $args['method'] ) ? (string) $args['method'] : 'GET';

		foreach ( self::$mocks as $pattern => $mock ) {
			if ( preg_match( $pattern, $url ) ) {
				++self::$mocks[ $pattern ]['hits'];
				self::$requests[] = array(
					'url'    => $url,
					'method' => $method,
					'mocked' => true,
				);

				return array(
					'headers'  => array(),
					'body'     => $mock['body'],
					'response' => array(
						'code'    => $mock['code'],
						'message' => get_status_header_desc( $mock['code'] ),
					),
					'cookies'  => array(),
					'filename' => null,
				);
			}
		}

		self::$requests[] = array(
			'url'    => $url,
			'method' => $method,
			'mocked' => false,
		);
		self::$unmocked[] = $method . ' ' . $url;

		return new WP_Error( 'zerospam_tests_http_blocked', 'Outbound HTTP is blocked in tests. Mock it with mock_http(): ' . $url );
	}

	/**
	 * Answers requests whose URL matches a pattern.
	 *
	 * @param string       $pattern PCRE pattern matched against the full URL.
	 * @param string|array $body    Response body; arrays are JSON encoded.
	 * @param int          $code    HTTP status code.
	 */
	public static function mock( string $pattern, $body, int $code = 200 ): void {
		self::$mocks[ $pattern ] = array(
			'body' => is_array( $body ) ? (string) wp_json_encode( $body ) : (string) $body,
			'code' => $code,
			'hits' => 0,
		);
	}

	/**
	 * Number of requests a mocked pattern answered.
	 *
	 * @param string $pattern Pattern passed to mock().
	 */
	public static function hits( string $pattern ): int {
		return self::$mocks[ $pattern ]['hits'] ?? 0;
	}

	/**
	 * Every request seen since the last reset.
	 *
	 * @return array<int, array{url: string, method: string, mocked: bool}>
	 */
	public static function requests(): array {
		return self::$requests;
	}

	/**
	 * Returns and clears the unmocked requests, for tests that expect one.
	 *
	 * @return string[] "METHOD url" entries.
	 */
	public static function take_unmocked(): array {
		$unmocked       = self::$unmocked;
		self::$unmocked = array();

		return $unmocked;
	}

	/**
	 * Forgets all mocks and recorded requests.
	 */
	public static function reset(): void {
		self::$mocks    = array();
		self::$requests = array();
		self::$unmocked = array();
	}
}
