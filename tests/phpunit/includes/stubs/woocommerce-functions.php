<?php
/**
 * WooCommerce functions the WooCommerce module calls, for tests without WooCommerce.
 *
 * Loaded by the WooCommerce module's tests only (require_once); each is
 * declared only when WooCommerce did not declare it.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

if ( ! function_exists( 'wc_add_notice' ) ) {
	/**
	 * Records a checkout notice in $GLOBALS['zerospam_test_wc_notices'].
	 *
	 * @param string $message Message.
	 * @param string $type    Notice type.
	 */
	function wc_add_notice( $message, $type = 'success' ): void {
		$GLOBALS['zerospam_test_wc_notices'][] = array( $type, $message );
	}
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	/**
	 * Answers a billing-email lookup from the `zerospam_test_paid_emails` filter (a list of emails with a paid order).
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return int[]
	 */
	function wc_get_orders( $args ): array {
		$paid = (array) apply_filters( 'zerospam_test_paid_emails', array() );

		return isset( $args['billing_email'] ) && in_array( $args['billing_email'], $paid, true ) ? array( 1 ) : array();
	}
}
