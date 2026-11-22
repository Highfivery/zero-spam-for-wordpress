/**
 * Zero Spam: adds the checkout token to the Checkout block's order.
 *
 * The token (window.zeroSpamCheckout, printed before this script) goes in the
 * Store API request's `extensions['zero-spam']` through the checkout data
 * store's setExtensionData(), and is set again whenever the store loses it
 * (a reset after a failed payment, another script replacing the data).
 * See modules/woocommerce/class-checkouttoken.php.
 */
( function () {
	'use strict';

	const config = window.zeroSpamCheckout;
	const data = window.wp && window.wp.data;

	if (
		! config ||
		! config.token ||
		! data ||
		'function' !== typeof data.subscribe
	) {
		return;
	}

	function current() {
		try {
			const checkout = data.select( 'wc/store/checkout' );
			const extensions =
				checkout && 'function' === typeof checkout.getExtensionData
					? checkout.getExtensionData()
					: null;

			return extensions && extensions[ config.namespace ]
				? extensions[ config.namespace ].token
				: undefined;
		} catch {
			return undefined;
		}
	}

	function apply() {
		if ( config.token === current() ) {
			return;
		}

		try {
			const actions = data.dispatch( 'wc/store/checkout' );

			if ( actions && 'function' === typeof actions.setExtensionData ) {
				actions.setExtensionData( config.namespace, {
					token: config.token,
				} );
			}
		} catch {
			// The checkout store is not registered yet.
		}
	}

	apply();
	data.subscribe( apply );
} )();
