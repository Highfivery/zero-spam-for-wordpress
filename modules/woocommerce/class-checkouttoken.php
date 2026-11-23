<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Checkout_Token to this file name.
/**
 * The Checkout block's browser token.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Modules\WooCommerce;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * A token the store's checkout page sends with its order.
 *
 * The Checkout block renders in the browser, so it cannot carry the classic
 * checkout's honeypot field or David Walsh key. Instead, a small script on the
 * page adds this token to the Store API request (`extensions['zero-spam']`).
 * Bots that post straight to `wc/store/v1/checkout` never ran the page, so
 * their orders carry no token.
 *
 * A missing token alone never refuses an order: express payment buttons
 * (Apple Pay, Google Pay, PayPal) can place orders through the Store API from
 * product and cart pages, where the script does not run. It marks the order
 * unverified, which Card_Testing counts more strictly, and only during a surge
 * are unverified orders refused.
 *
 * The token changes every hour and stays valid for three, so a page served
 * from a cache up to two hours old still sends a valid one. It is the same
 * for every visitor in that hour: it proves a page of this store was loaded,
 * not who loaded it.
 *
 * @since 6.0.2
 */
final class Checkout_Token {

	/**
	 * The Store API extension namespace.
	 */
	public const NAMESPACE = 'zero-spam';

	/**
	 * The script handle.
	 */
	public const HANDLE = 'zerospam-checkout-token';

	/**
	 * The token for the current hour.
	 */
	public static function current(): string {
		return self::for_hour( self::hour() );
	}

	/**
	 * Whether a token is one of the last three hours'.
	 *
	 * @param mixed $token Token from the request.
	 */
	public static function is_valid( $token ): bool {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}

		$hour = self::hour();

		for ( $back = 0; $back < 3; $back++ ) {
			if ( hash_equals( self::for_hour( $hour - $back ), $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The token a Store API request carries, or ''.
	 *
	 * @param mixed $request The Store API request (WP_REST_Request).
	 */
	public static function from_request( $request ): string {
		if ( ! $request instanceof \WP_REST_Request ) {
			return '';
		}

		$extensions = $request->get_param( 'extensions' );
		$token      = is_array( $extensions ) && isset( $extensions[ self::NAMESPACE ]['token'] ) ? $extensions[ self::NAMESPACE ]['token'] : '';

		return is_string( $token ) ? $token : '';
	}

	/**
	 * Adds the token's field to the Store API checkout schema, so WooCommerce
	 * passes it through (on `woocommerce_blocks_loaded`).
	 */
	public static function register_schema(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE,
				'schema_callback' => static fn(): array => array(
					'token' => array(
						'description' => __( 'Zero Spam checkout token.', 'zero-spam' ),
						'type'        => array( 'string', 'null' ),
						'context'     => array(),
						'arg_options' => array(
							'validate_callback' => static fn( $value ): bool => null === $value || is_string( $value ),
						),
					),
				),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Enqueues the token script on pages with the Checkout block (on `wp_enqueue_scripts`).
	 */
	public static function enqueue(): void {
		if ( ! self::page_has_checkout_block() ) {
			return;
		}

		$deps = array( 'wp-data' );
		if ( wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			$deps[] = 'wc-blocks-checkout';
		}

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'modules/woocommerce/assets/checkout-token.js', ZEROSPAM ),
			$deps,
			\ZeroSpam\Core\Utilities::asset_version( 'modules/woocommerce/assets/checkout-token.js' ),
			array( 'in_footer' => true )
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.zeroSpamCheckout = ' . wp_json_encode(
				array(
					'namespace' => self::NAMESPACE,
					'token'     => self::current(),
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ';',
			'before'
		);
	}

	/**
	 * Whether the current page shows the Checkout block.
	 */
	private static function page_has_checkout_block(): bool {
		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return true;
		}

		return function_exists( 'is_checkout' ) && is_checkout()
			&& function_exists( 'wc_get_page_id' ) && has_block( 'woocommerce/checkout', (int) wc_get_page_id( 'checkout' ) );
	}

	/**
	 * The hour number.
	 */
	private static function hour(): int {
		return intdiv( Card_Testing::now(), 3600 );
	}

	/**
	 * The token of an hour.
	 *
	 * @param int $hour Hour number.
	 */
	private static function for_hour( int $hour ): string {
		return substr( hash_hmac( 'sha256', 'checkout|' . $hour, wp_salt( 'nonce' ) ), 0, 32 );
	}
}
