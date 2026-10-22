<?php
/**
 * Exception thrown instead of redirecting.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

use RuntimeException;

/**
 * Thrown by the `wp_redirect` filter that TestCase::expect_redirect() adds, so
 * code that calls wp_redirect() and then exit() can be tested.
 */
final class RedirectException extends RuntimeException {

	/**
	 * Redirect target.
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	public int $status;

	/**
	 * Constructor.
	 *
	 * @param string $location Redirect target; also the exception message.
	 * @param int    $status   HTTP status code.
	 */
	public function __construct( string $location, int $status ) {
		parent::__construct( $location, $status );
		$this->location = $location;
		$this->status   = $status;
	}
}
