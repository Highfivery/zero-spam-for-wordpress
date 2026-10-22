/**
 * Zero Spam for WordPress David Walsh vanilla JavaScript implementation.
 *
 * Handles adding the required functionality for spam detections.
 * Features:
 * - No jQuery dependency
 * - MutationObserver for dynamically loaded forms
 * - Fresh per-visitor key fetched on form interaction (cached pages, AJAX forms)
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
	 * Key age in seconds after which the page is assumed to be cached and a
	 * fresh key is fetched right away.
	 *
	 * @type {number}
	 */
	const REFRESH_THRESHOLD = 300;

	/**
	 * Longest a submission waits for an in-flight key refresh, in ms.
	 *
	 * @type {number}
	 */
	const REFRESH_WAIT_MS = 3000;

	/**
	 * Attribute marking a form whose submission is being held for a refresh.
	 *
	 * @type {string}
	 */
	const HOLD_ATTR = 'data-zerospam-davidwalsh-hold';

	/**
	 * Cookie carrying the key on login pages, so the login check still has it
	 * when another plugin strips unknown fields from the login POST.
	 *
	 * @type {string}
	 */
	const LOGIN_COOKIE = 'zerospam_david_walsh_key';

	/**
	 * Login form selector.
	 *
	 * @type {string}
	 */
	const LOGIN_FORM_SELECTOR = '#loginform, form[name="loginform"]';

	/**
	 * Current key value (may be updated via AJAX).
	 *
	 * @type {string}
	 */
	let currentKey = config.key || '';

	/**
	 * In-flight key refresh, if any.
	 *
	 * @type {Promise<void>|null}
	 */
	let refreshing = null;

	/**
	 * Forms being resubmitted after a held submission.
	 *
	 * @type {WeakSet<HTMLFormElement>}
	 */
	const resubmitting = new WeakSet();

	/**
	 * Whether a key has already been fetched for this page view.
	 *
	 * @type {boolean}
	 */
	let keyFetched = false;

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

		// Keys expire and can only be used a few times, so get a fresh one as
		// soon as the visitor starts using the form, and again after each
		// submission (AJAX forms can be resubmitted without a reload).
		form.addEventListener( 'focusin', refreshOnce );
		form.addEventListener( 'pointerdown', refreshOnce );

		// If a refresh is still in flight when the form is submitted (e.g. it
		// started on pointerdown of the submit button), hold the submission
		// until the new key is in the form, so the old one isn't sent. Runs in
		// the capture phase so other submit handlers (AJAX forms) only see
		// the resubmitted event.
		form.addEventListener( 'submit', waitForRefresh, true );

		form.addEventListener( 'submit', function() {
			setTimeout( refreshKey, 1000 );
		} );

		if ( form.matches( LOGIN_FORM_SELECTOR ) ) {
			setLoginCookie();
		}

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
	 * Hold a form submission while a key refresh is in flight, then submit
	 * again with the new key (submit event listener, capture phase).
	 *
	 * While a submission is held, further submits (double clicks, Enter)
	 * are swallowed, so the old key is never sent and the form is sent once;
	 * the programmatic resubmission is let through by the `resubmitting`
	 * set. Gives up waiting after REFRESH_WAIT_MS so a stalled request never
	 * blocks the form, and always clears the hold, even if the refresh
	 * rejects or requestSubmit() throws. Only used where requestSubmit()
	 * exists, since it re-runs the form's submit handlers and keeps the
	 * submit button.
	 *
	 * @param {SubmitEvent} event - The submit event.
	 */
	function waitForRefresh( event ) {
		const form = event.currentTarget;

		// The resubmission below: let it through.
		if ( resubmitting.has( form ) ) {
			return;
		}

		// A repeat submit while held: swallow it.
		if ( form.hasAttribute( HOLD_ATTR ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}

		if ( ! refreshing || typeof form.requestSubmit !== 'function' ) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();
		form.setAttribute( HOLD_ATTR, '' );

		const submitter = event.submitter || null;
		const timeout = new Promise( function( resolve ) {
			setTimeout( resolve, REFRESH_WAIT_MS );
		} );

		Promise.race( [ refreshing, timeout ] )
			.catch( function() {
				// Submit with whatever key the form has.
			} )
			.then( function() {
				form.removeAttribute( HOLD_ATTR );
				resubmitting.add( form );

				try {
					if ( submitter && submitter.form === form ) {
						form.requestSubmit( submitter );
					} else {
						form.requestSubmit();
					}
				} finally {
					resubmitting.delete( form );
				}
			} )
			.catch( function( e ) {
				form.removeAttribute( HOLD_ATTR );

				if ( console && console.warn ) {
					console.warn( 'Zero Spam: could not resubmit the form', e );
				}
			} );
	}

	/**
	 * Add a cache-busting parameter to a key endpoint URL, so no page,
	 * proxy or CDN cache can serve an old (expired or used-up) key.
	 *
	 * @param {string} url Endpoint URL.
	 * @return {string} URL with a unique query parameter.
	 */
	function noCacheUrl( url ) {
		try {
			const parsed = new URL( url, window.location.href );
			parsed.searchParams.set( '_zs', Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 ) );

			return parsed.toString();
		} catch ( e ) {
			return url;
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
	 * Fetch a key from a URL.
	 *
	 * @param {string} url Endpoint URL.
	 * @return {Promise<Object|null>} Key data or null.
	 */
	async function fetchKey( url ) {
		try {
			const response = await fetch( noCacheUrl( url ), {
				method: 'GET',
				cache: 'no-store',
				credentials: 'same-origin',
			} );

			if ( ! response.ok ) {
				return null;
			}

			const data = await response.json();

			return data && data.key ? data : null;
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Fetch a fresh key (REST API, falling back to admin-ajax) and apply it
	 * to all protected forms.
	 *
	 * @return {Promise<void>}
	 */
	function refreshKey() {
		if ( refreshing ) {
			return refreshing;
		}

		refreshing = ( async function() {
			let data = config.restUrl ? await fetchKey( config.restUrl ) : null;

			if ( ! data && config.ajaxUrl ) {
				data = await fetchKey( config.ajaxUrl );
			}

			if ( data ) {
				keyFetched = true;
				currentKey = data.key;
				config.generated = data.generated;

				// Update all already-initialized forms with the new key.
				updateExistingForms();
			} else if ( console && console.warn ) {
				console.warn( 'Zero Spam: Failed to refresh David Walsh key' );
			}

			refreshing = null;
		} )();

		return refreshing;
	}

	/**
	 * Fetch a fresh key once per page view (on first form interaction).
	 */
	function refreshOnce() {
		if ( ! keyFetched && ! refreshing ) {
			refreshKey();
		}
	}

	/**
	 * Fetch a fresh key right away if the page looks cached.
	 */
	function maybeRefreshKey() {
		if ( ! config.generated ) {
			return;
		}

		const now = Math.floor( Date.now() / 1000 );

		if ( now - config.generated >= REFRESH_THRESHOLD ) {
			refreshKey();
		}
	}

	/**
	 * Store the key in a cookie for the login check.
	 */
	function setLoginCookie() {
		const secure = 'https:' === window.location.protocol ? '; Secure' : '';

		document.cookie = LOGIN_COOKIE + '=' + encodeURIComponent( currentKey ) +
			'; path=/; max-age=' + ( config.ttl || 3600 ) + '; SameSite=Lax' + secure;
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

			if ( form.matches( LOGIN_FORM_SELECTOR ) ) {
				setLoginCookie();
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

		// A page restored from the back/forward cache still holds the key it
		// was first shown with, which may since have expired or been used.
		window.addEventListener( 'pageshow', function( event ) {
			if ( event.persisted ) {
				refreshKey();
			}
		} );
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		// DOM is already ready.
		init();
	}
} )();
