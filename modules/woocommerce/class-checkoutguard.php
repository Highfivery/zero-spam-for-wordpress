<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Checkout_Guard to this file name.
/**
 * The checks every WooCommerce checkout goes through.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Modules\WooCommerce;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * One set of checks for both checkouts: the classic (shortcode) checkout and
 * the Checkout block / Store API (`POST wc/store/v1/checkout`).
 *
 * Neither path runs Zero Spam's page-view checks (the Store API is a REST
 * request, and the classic checkout posts to `?wc-ajax=checkout`, which
 * WooCommerce answers on `template_redirect` before them), so the visitor
 * checks run here: blocked IPs and locations, network-wide blocks and the
 * reputation services that are switched on.
 *
 * What a refused checkout logs is a short list of order fields, never the
 * request: payment gateways that post card fields to the store would
 * otherwise put card numbers into the log.
 *
 * @since 6.0.2
 */
final class Checkout_Guard {

	/**
	 * The classic checkout.
	 */
	public const CLASSIC = 'classic';

	/**
	 * The Checkout block and the Store API.
	 */
	public const STORE_API = 'store_api';

	/**
	 * What paused the visitor in the last check(), for record(): `ip`, `user`, `email` or ''.
	 *
	 * @var string
	 */
	private static string $paused_by = '';

	/**
	 * Why a checkout is refused, or no reasons.
	 *
	 * Shop staff (`manage_woocommerce`) and addresses on the IP Allowlist or
	 * the no-lockout list are never refused. Logged-in customers go through
	 * the card-testing and email checks like everyone else; the page-view
	 * visitor checks (blocked IPs and locations, reputation) apply to guests,
	 * as they do on the rest of the site.
	 *
	 * @param array  $order    Order fields (see order_fields()).
	 * @param string $path     self::CLASSIC or self::STORE_API.
	 * @param bool   $verified Whether the order came from the store's own checkout page.
	 * @param array  $posted   The classic checkout's posted fields (sanitized), for the honeypot and the David Walsh check.
	 * @return string[] Failed check keys.
	 */
	public static function check( array $order, string $path, bool $verified, array $posted = array() ): array {
		self::$paused_by = '';

		$ip = (string) \ZeroSpam\Core\User::get_ip();

		if ( self::is_trusted( $ip ) ) {
			return array();
		}

		$reasons = array();
		$email   = (string) ( $order['email'] ?? '' );

		if ( Card_Testing::enabled() ) {
			$paused = Card_Testing::paused_by( $ip, $email, get_current_user_id() );

			// Anyone can type someone else's email: a pause on the email alone only refuses orders that did not come from the checkout page.
			if ( 'ip' === $paused || 'user' === $paused || ( 'email' === $paused && ! $verified ) ) {
				$reasons[]       = 'card_testing';
				self::$paused_by = $paused;
			} elseif ( ! $verified && Card_Testing::surge_active() ) {
				$reasons[] = 'card_testing_surge';
			}
		}

		if ( self::CLASSIC === $path ) {
			$honeypot = \ZeroSpam\Core\Utilities::get_honeypot();

			if ( ! empty( $posted[ $honeypot ] ) ) {
				$reasons[] = 'honeypot';
			}
		}

		if ( '' !== $email && \ZeroSpam\Core\Utilities::is_email_domain_blocked( $email ) ) {
			$reasons[] = 'blocked_email_domain';
		}

		if ( ! is_user_logged_in() ) {
			foreach ( self::access_reasons() as $reason ) {
				$reasons[] = $reason;
			}
		}

		if ( self::CLASSIC === $path ) {
			// The David Walsh check and other modules' checks of the posted form.
			$filtered = apply_filters( 'zerospam_preprocess_woocommerce_checkout', array(), $posted, 'woocommerce_checkout_spam_message' );

			if ( is_array( $filtered ) ) {
				foreach ( array_keys( $filtered ) as $key ) {
					$reasons[] = str_replace( 'zerospam_', '', (string) $key );
				}
			}
		}

		return array_values( array_unique( $reasons ) );
	}

	/**
	 * Whether the current visitor is never checked: shop staff, or an address
	 * on the IP Allowlist or the no-lockout list.
	 *
	 * @param string $ip The visitor's IP address.
	 */
	public static function is_trusted( string $ip ): bool {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce's shop management capability.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return '' !== $ip && ( \ZeroSpam\Core\Utilities::is_whitelisted( $ip ) || \ZeroSpam\Core\Admin_Safe_IPs::contains( $ip ) );
	}

	/**
	 * Logs and shares a refused checkout.
	 *
	 * Card-testing refusals are always logged, so the owner can see why a
	 * customer was turned away; others follow "Log blocked checkouts". With
	 * Usage Data Sharing on, a card-testing refusal shares the IP address only
	 * (never the email), and not at all when only the email was paused, since
	 * anyone can type someone else's email.
	 *
	 * @param string[] $reasons Failed check keys.
	 * @param array    $order   Order fields.
	 * @param string   $path    The checkout path.
	 */
	public static function record( array $reasons, array $order, string $path ): void {
		$details = array(
			'email' => (string) ( $order['email'] ?? '' ),
			'name'  => trim( ( $order['first_name'] ?? '' ) . ' ' . ( $order['last_name'] ?? '' ) ),
			'type'  => 'woocommerce_checkout',
			'data'  => self::log_fields( $order, $path ),
		);

		$log   = 'enabled' === \ZeroSpam\Core\Settings_Schema::value( 'log_blocked_woocommerce_checkouts' );
		$share = 'enabled' === \ZeroSpam\Core\Settings_Schema::value( 'share_data' );

		foreach ( $reasons as $reason ) {
			$details['failed'] = $reason;
			$card_testing      = 0 === strpos( $reason, 'card_testing' );

			if ( $log || $card_testing ) {
				\ZeroSpam\Includes\DB::log( 'woocommerce_checkout', $details );
			}

			if ( ! $share ) {
				continue;
			}

			if ( ! $card_testing ) {
				do_action( 'zerospam_share_detection', $details );
			} elseif ( 'email' !== self::$paused_by ) {
				do_action(
					'zerospam_share_detection',
					array(
						'type'   => 'woocommerce_checkout',
						'failed' => $reason,
					)
				);
			}
		}
	}

	/**
	 * The order fields the checks and the log use, from a WooCommerce order.
	 *
	 * @param object $order WC_Order.
	 * @return array{email: string, first_name: string, last_name: string, country: string, payment_method: string, total: string}
	 */
	public static function order_fields( $order ): array {
		$get = static fn( string $method ): string => is_object( $order ) && method_exists( $order, $method ) ? (string) $order->{$method}() : '';

		return array(
			'email'          => sanitize_email( $get( 'get_billing_email' ) ),
			'first_name'     => sanitize_text_field( $get( 'get_billing_first_name' ) ),
			'last_name'      => sanitize_text_field( $get( 'get_billing_last_name' ) ),
			'country'        => sanitize_text_field( $get( 'get_billing_country' ) ),
			'payment_method' => sanitize_key( $get( 'get_payment_method' ) ),
			'total'          => sanitize_text_field( $get( 'get_total' ) ),
		);
	}

	/**
	 * The order fields the checks and the log use, from the classic checkout's posted fields.
	 *
	 * @param array $posted Posted fields (sanitized).
	 * @return array{email: string, first_name: string, last_name: string, country: string, payment_method: string, total: string}
	 */
	public static function posted_fields( array $posted ): array {
		$total = '';
		if ( function_exists( 'WC' ) && WC()->cart && method_exists( WC()->cart, 'get_total' ) ) {
			$total = (string) WC()->cart->get_total( 'edit' );
		}

		return array(
			'email'          => sanitize_email( (string) ( $posted['billing_email'] ?? '' ) ),
			'first_name'     => sanitize_text_field( (string) ( $posted['billing_first_name'] ?? '' ) ),
			'last_name'      => sanitize_text_field( (string) ( $posted['billing_last_name'] ?? '' ) ),
			'country'        => sanitize_text_field( (string) ( $posted['billing_country'] ?? '' ) ),
			'payment_method' => sanitize_key( (string) ( $posted['payment_method'] ?? '' ) ),
			'total'          => sanitize_text_field( $total ),
		);
	}

	/**
	 * What a refused checkout logs: the order fields and the path, nothing else.
	 *
	 * @param array  $order Order fields.
	 * @param string $path  The checkout path.
	 * @return array<string, string>
	 */
	public static function log_fields( array $order, string $path ): array {
		$fields = array();

		foreach ( array( 'email', 'first_name', 'last_name', 'country', 'payment_method', 'total' ) as $key ) {
			if ( isset( $order[ $key ] ) && '' !== (string) $order[ $key ] ) {
				$fields[ $key ] = (string) $order[ $key ];
			}
		}

		$fields['checkout'] = $path;

		return $fields;
	}

	/**
	 * The visitor checks Zero Spam runs on page views, as failed check keys.
	 *
	 * @return string[]
	 */
	private static function access_reasons(): array {
		$access = \ZeroSpam\Core\Access::get_access();

		if ( empty( $access['blocked'] ) || empty( $access['details'] ) || ! is_array( $access['details'] ) ) {
			return array();
		}

		$reasons = array();

		foreach ( $access['details'] as $key => $detail ) {
			if ( ! empty( $detail['blocked'] ) ) {
				$reasons[] = ! empty( $detail['details']['failed'] ) && is_string( $detail['details']['failed'] ) ? $detail['details']['failed'] : (string) $key;
			}
		}

		return $reasons;
	}
}
