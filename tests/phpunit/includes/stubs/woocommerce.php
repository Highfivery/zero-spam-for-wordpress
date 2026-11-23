<?php
/**
 * The Store API exception, for tests without WooCommerce.
 *
 * Loaded by the WooCommerce module's tests only (require_once), and only when
 * WooCommerce itself did not define it.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\StoreApi\Exceptions;

if ( ! class_exists( RouteException::class, false ) ) {
	/**
	 * Thrown by a Store API route to answer with an error.
	 */
	class RouteException extends \Exception {

		/**
		 * Machine-readable error code.
		 *
		 * @var string
		 */
		public string $error_code;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		public int $http_status_code;

		/**
		 * Constructor.
		 *
		 * @param string $error_code       Error code.
		 * @param string $message          Message.
		 * @param int    $http_status_code HTTP status.
		 */
		public function __construct( string $error_code, string $message, int $http_status_code = 400 ) {
			$this->error_code       = $error_code;
			$this->http_status_code = $http_status_code;
			parent::__construct( $message, $http_status_code );
		}
	}
}
