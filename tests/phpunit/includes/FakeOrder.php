<?php
/**
 * A stand-in for WC_Order in tests without WooCommerce.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

/**
 * Answers the WC_Order methods the WooCommerce module calls, from an array.
 */
final class FakeOrder {

	/**
	 * Order data.
	 *
	 * @var array<string, mixed>
	 */
	public array $data;

	/**
	 * Order meta.
	 *
	 * @var array<string, mixed>
	 */
	public array $meta = array();

	/**
	 * Creates an order placed through the Store API.
	 *
	 * @param array<string, mixed> $data Overrides.
	 */
	public function __construct( array $data = array() ) {
		$this->data = $data + array(
			'billing_email'      => 'tester@example.com',
			'billing_first_name' => 'Card',
			'billing_last_name'  => 'Tester',
			'billing_country'    => 'US',
			'payment_method'     => 'stripe',
			'total'              => '1.00',
			'created_via'        => 'store-api',
			'customer_id'        => 0,
			'customer_ip'        => '',
			'status'             => 'checkout-draft',
		);
	}

	/**
	 * Billing email.
	 */
	public function get_billing_email(): string {
		return (string) $this->data['billing_email'];
	}

	/**
	 * Billing first name.
	 */
	public function get_billing_first_name(): string {
		return (string) $this->data['billing_first_name'];
	}

	/**
	 * Billing last name.
	 */
	public function get_billing_last_name(): string {
		return (string) $this->data['billing_last_name'];
	}

	/**
	 * Billing country.
	 */
	public function get_billing_country(): string {
		return (string) $this->data['billing_country'];
	}

	/**
	 * Payment method ID.
	 */
	public function get_payment_method(): string {
		return (string) $this->data['payment_method'];
	}

	/**
	 * Order total.
	 */
	public function get_total(): string {
		return (string) $this->data['total'];
	}

	/**
	 * How the order was created.
	 */
	public function get_created_via(): string {
		return (string) $this->data['created_via'];
	}

	/**
	 * Customer user ID.
	 */
	public function get_customer_id(): int {
		return (int) $this->data['customer_id'];
	}

	/**
	 * IP address WooCommerce recorded.
	 */
	public function get_customer_ip_address(): string {
		return (string) $this->data['customer_ip'];
	}

	/**
	 * Whether the order has a status.
	 *
	 * @param string $status Status.
	 */
	public function has_status( string $status ): bool {
		return $status === $this->data['status'];
	}

	/**
	 * Reads meta.
	 *
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}

	/**
	 * Sets meta.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}
}
