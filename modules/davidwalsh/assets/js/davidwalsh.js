/**
 * Zero Spam for WordPress David Walsh vanilla JavaScript implementation.
 *
 * Handles adding the required functionality for spam detections.
 * Features:
 * - No jQuery dependency
 * - MutationObserver for dynamically loaded forms
 * - AJAX key refresh for cached pages
 * - Centralized selector management
 *
 * @package ZeroSpam
 */
( function() {
	'use strict';

	/**
	 * Configuration from WordPress.
	 *
	 * @type {Object}
	 */
	const config = window.ZeroSpamDavidWalsh || {};

	/**
	 * Input field name for the David Walsh key.
	 *
	 * @type {string}
	 */
	const INPUT_NAME = 'zerospam_david_walsh_key';

	/**
	 * Data attribute to mark protected forms.
	 *
	 * @type {string}
	 */
	const DATA_ATTR = 'data-zerospam-davidwalsh';

	/**
	 * Threshold in seconds to trigger AJAX key refresh (12 hours).
	 *
	 * @type {number}
	 */
	const REFRESH_THRESHOLD = 43200;

	/**
	 * Current key value (may be updated via AJAX).
	 *
	 * @type {string}
	 */
	let currentKey = config.key || '';

	/**
	 * Flag to track if we've already attempted a key refresh.
	 *
	 * @type {boolean}
	 */
	let keyRefreshAttempted = false;

	/**
	 * Initialize protection on a single form element.
	 *
	 * @param {HTMLFormElement} form - The form element to protect.
	 */
	function initForm( form ) {
		// Skip if already initialized.
		if ( form.getAttribute( DATA_ATTR ) === 'protected' ) {
			return;
		}

		// Skip if no key available.
		if ( ! currentKey ) {
			return;
		}

		// Mark as protected.
		form.setAttribute( DATA_ATTR, 'protected' );

		// Check if the hidden input already exists.
		let input = form.querySelector( `input[name="${INPUT_NAME}"]` );

		if ( input ) {
			// Update existing input's value.
			input.value = currentKey;
		} else {
			// Create and append new hidden input.
			input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = INPUT_NAME;
			input.value = currentKey;
			form.appendChild( input );
		}
	}

	/**
	 * Initialize protection on all matching forms.
	 */
	function initAllForms() {
		if ( ! config.selectors ) {
			return;
		}

		try {
			const forms = document.querySelectorAll( config.selectors );
			forms.forEach( initForm );
		} catch ( e ) {
			// Invalid selector, fail silently.
			if ( console && console.warn ) {
				console.warn( 'Zero Spam: Invalid selector in davidwalsh.js', e );
			}
		}
	}

	/**
	 * Refresh the key via AJAX if it's stale.
	 *
	 * @return {Promise<void>}
	 */
	async function maybeRefreshKey() {
		// Only attempt refresh once per page load.
		if ( keyRefreshAttempted ) {
			return;
		}

		// Check if we have the necessary data.
		if ( ! config.restUrl || ! config.generated ) {
			return;
		}

		// Check if key is older than threshold.
		const now = Math.floor( Date.now() / 1000 );
		const keyAge = now - config.generated;

		if ( keyAge < REFRESH_THRESHOLD ) {
			return;
		}

		keyRefreshAttempted = true;

		try {
			const response = await fetch( config.restUrl, {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce || '',
				},
				credentials: 'same-origin',
			} );

			if ( ! response.ok ) {
				return;
			}

			const data = await response.json();

			if ( data && data.key ) {
				currentKey = data.key;
				config.generated = data.generated;

				// Update all already-initialized forms with the new key.
				updateExistingForms();
			}
		} catch ( e ) {
			// Fetch failed, continue with existing key.
			if ( console && console.warn ) {
				console.warn( 'Zero Spam: Failed to refresh David Walsh key', e );
			}
		}
	}

	/**
	 * Update the key value in all already-protected forms.
	 */
	function updateExistingForms() {
		const protectedForms = document.querySelectorAll( `form[${DATA_ATTR}="protected"]` );

		protectedForms.forEach( function( form ) {
			const input = form.querySelector( `input[name="${INPUT_NAME}"]` );
			if ( input ) {
				input.value = currentKey;
			}
		} );
	}

	/**
	 * Set up MutationObserver to handle dynamically loaded forms.
	 */
	function setupMutationObserver() {
		// Check for MutationObserver support.
		if ( typeof MutationObserver === 'undefined' ) {
			return;
		}

		const observer = new MutationObserver( function( mutations ) {
			let shouldInit = false;

			mutations.forEach( function( mutation ) {
				if ( mutation.addedNodes.length > 0 ) {
					shouldInit = true;
				}
			} );

			if ( shouldInit ) {
				// Debounce the initialization.
				clearTimeout( observer.timeout );
				observer.timeout = setTimeout( initAllForms, 100 );
			}
		} );

		observer.observe( document.body, {
			childList: true,
			subtree: true,
		} );
	}

	/**
	 * Main initialization.
	 */
	function init() {
		// Check for required config.
		if ( typeof config.key === 'undefined' ) {
			return;
		}

		// Initialize all forms on page load.
		initAllForms();

		// Set up observer for dynamic forms.
		setupMutationObserver();

		// Attempt key refresh if page might be cached.
		maybeRefreshKey();
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		// DOM is already ready.
		init();
	}
} )();
