<?php
/**
 * Hand-written stubs for the parts of the WooCommerce Store API that
 * modules/woocommerce/ uses, so PHPStan can check the calls without
 * WooCommerce installed. Signatures follow WooCommerce 9.x
 * (src/StoreApi/Exceptions/RouteException.php, src/StoreApi/Schemas/V1/CheckoutSchema.php).
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\StoreApi\Exceptions {

	/**
	 * The exception a Store API route throws to answer with an error.
	 */
	class RouteException extends \Exception {
		/**
		 * Constructor.
		 *
		 * @param string $error_code       Machine-readable error code.
		 * @param string $message          User-friendly translated message.
		 * @param int    $http_status_code HTTP status code to respond with.
		 * @param array  $additional_data  Extra data to return.
		 */
		public function __construct( $error_code, $message, $http_status_code = 400, $additional_data = array() ) {}
	}
}

namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {

	/**
	 * The checkout route's schema.
	 */
	class CheckoutSchema {
		/**
		 * Schema identifier, used to extend it.
		 */
		const IDENTIFIER = 'checkout';
	}
}
