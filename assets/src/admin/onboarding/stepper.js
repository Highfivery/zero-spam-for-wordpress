/**
 * First-run setup, as a state machine: three steps (protection level, what
 * to protect, how to block) and the done screen, what each step changes,
 * and how far the mark is built. No JSX, so the unit tests run in Node.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/** Number of steps (the done screen comes after them). */
export const STEPS = 3;

/** The done screen. */
export const DONE = STEPS + 1;

/** The core entry points and the browser check, always offered. */
export const CORE = [ 'comments', 'registration', 'login', 'davidwalsh' ];

/** Bricks rows of the mark, top (0) to bottom (3). */
export const ROWS = 4;

/**
 * Clamps a step number to 1 … DONE.
 *
 * @param {number} step Step.
 * @return {number} Step.
 */
export const clampStep = ( step ) =>
	Math.min( DONE, Math.max( 1, Math.round( Number( step ) || 1 ) ) );

/**
 * The state the setup starts in.
 *
 * @param {Object} [seed]          Values to start from.
 * @param {number} [seed.step]     Step (a saved onboarding_step resumes there).
 * @param {Object} [seed.protect]  Module key => ticked.
 * @param {Object} [seed.blocking] block_method, share_data, log_blocked_ips.
 * @return {Object} State.
 */
export function initialState( seed = {} ) {
	return {
		step: clampStep( seed.step || 1 ),
		level: 'basic',
		key: '',
		protect: { ...( seed.protect || {} ) },
		blocking: {
			block_method: 'php',
			share_data: true,
			log_blocked_ips: true,
			...( seed.blocking || {} ),
		},
		errors: {},
		busy: false,
		// The highest step reached, so the mark never takes bricks away when going back.
		reached: clampStep( seed.step || 1 ),
	};
}

/**
 * The reducer.
 *
 * @param {Object} state  State.
 * @param {Object} action Action: level, key, protect, blocking, go, next, back, restart, error, busy.
 * @return {Object} State.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'level':
			return {
				...state,
				level: 'enhanced' === action.level ? 'enhanced' : 'basic',
				errors: {},
			};
		case 'key':
			return { ...state, key: String( action.key ?? '' ), errors: {} };
		case 'protect':
			return {
				...state,
				protect: {
					...state.protect,
					[ action.key ]: Boolean( action.on ),
				},
			};
		case 'blocking':
			return {
				...state,
				blocking: { ...state.blocking, [ action.name ]: action.value },
			};
		case 'go': {
			const step = clampStep( action.step );
			return {
				...state,
				step,
				reached: Math.max( state.reached, step ),
				errors: {},
				busy: false,
			};
		}
		case 'next':
			return reducer( state, { type: 'go', step: state.step + 1 } );
		case 'back':
			return reducer( state, { type: 'go', step: state.step - 1 } );
		case 'restart':
			return { ...state, step: 1, reached: 1, errors: {}, busy: false };
		case 'error':
			return { ...state, errors: action.errors || {}, busy: false };
		case 'busy':
			return { ...state, busy: Boolean( action.busy ) };
	}
	return state;
}

/**
 * Checks the current step before it is saved.
 *
 * @param {Object} state State.
 * @return {Object} Field => message; empty when the step can be saved.
 */
export function validateStep( state ) {
	if (
		1 === state.step &&
		'enhanced' === state.level &&
		! state.key.trim()
	) {
		return {
			key: __(
				'Enter your license key, or choose Basic for now.',
				'zero-spam'
			),
		};
	}
	return {};
}

/**
 * The progress list: each step's state.
 *
 * @param {number} step Current step.
 * @return {Array<{n: number, status: string}>} done, current or todo.
 */
export function progress( step ) {
	return Array.from( { length: STEPS }, ( _, i ) => {
		const n = i + 1;
		let status = 'todo';
		if ( n < step ) {
			status = 'done';
		} else if ( n === step ) {
			status = 'current';
		}
		return { n, status };
	} );
}

/**
 * Whether a row of the mark is laid at a step: the bottom row at step 1,
 * one more row per step, the top row on the done screen.
 *
 * @param {number} row  Row, 0 (top) to 3 (bottom).
 * @param {number} step Step (or the highest reached).
 * @return {boolean} Laid.
 */
export const rowLaid = ( row, step ) => step >= ROWS - row;

/**
 * Hints under each item of step 2.
 *
 * @return {Object} Module key => hint.
 */
const hints = () => ( {
	comments: __( 'Posts and pages', 'zero-spam' ),
	registration: __( 'New accounts', 'zero-spam' ),
	login: __( 'wp-login.php', 'zero-spam' ),
	davidwalsh: __( 'David Walsh technique, every form', 'zero-spam' ),
	woocommerce: __( 'Checkout and account sign-ups', 'zero-spam' ),
	givewp: __( 'Donation forms', 'zero-spam' ),
	mailchimp4wp: __( 'Sign-up forms', 'zero-spam' ),
	'wp-user-avatar': __( 'Registrations', 'zero-spam' ),
} );

/**
 * The items of step 2: the core entry points and the browser check, then
 * every form plugin Zero Spam found active. Each item switches all of its
 * module's switches; locked ones (set by the network) are shown, not changed.
 *
 * @param {Object|undefined} modules protection/modules answer.
 * @return {Array<{key: string, group: string, title: string, hint: string, switches: string[], locked: boolean}>} Items.
 */
export function protectItems( modules ) {
	const list = Array.isArray( modules?.modules ) ? modules.modules : [];
	const byKey = Object.fromEntries( list.map( ( m ) => [ m.key, m ] ) );
	const hint = hints();
	const item = ( m, group ) => ( {
		key: m.key,
		group,
		title: m.title,
		hint: hint[ m.key ] || __( 'Every form', 'zero-spam' ),
		switches: Array.isArray( m.switches ) ? m.switches : [],
		locked:
			Array.isArray( m.switches ) &&
			m.switches.length > 0 &&
			m.switches.every( ( s ) => ( m.locked_keys || [] ).includes( s ) ),
		enabled: true === m.enabled,
	} );

	const core = CORE.map( ( key ) => byKey[ key ] )
		.filter( ( m ) => m && m.switches?.length )
		.map( ( m ) => item( m, 'core' ) );
	const forms = list
		.filter(
			( m ) =>
				'integrations' === m.group &&
				m.available !== false &&
				true === m.plugin?.active &&
				m.switches?.length
		)
		.map( ( m ) => item( m, 'forms' ) );

	return [ ...core, ...forms ];
}

/**
 * Step 2 starts with everything ticked (a locked item shows the network's value).
 *
 * @param {Array} items protectItems().
 * @return {Object} Module key => ticked.
 */
export const defaultProtect = ( items ) =>
	Object.fromEntries(
		items.map( ( i ) => [ i.key, i.locked ? i.enabled : true ] )
	);

/**
 * What a step writes: the license key (step 1, Enhanced) and the settings
 * to PATCH. Locked settings are left out (the route refuses them).
 *
 * @param {Object} state State.
 * @param {Array}  items protectItems().
 * @return {{license: string|null, settings: Object}} Changes.
 */
export function changesFor( state, items = [] ) {
	if ( 1 === state.step ) {
		const enhanced = 'enhanced' === state.level;
		return {
			license: enhanced ? state.key.trim() : null,
			settings: { zerospam: enhanced ? 'enabled' : false },
		};
	}

	if ( 2 === state.step ) {
		const settings = {};
		items.forEach( ( i ) => {
			if ( i.locked ) {
				return;
			}
			const on = false !== state.protect[ i.key ];
			i.switches.forEach( ( key ) => {
				settings[ key ] = on;
			} );
		} );
		return { license: null, settings };
	}

	if ( 3 === state.step ) {
		const {
			block_method: method,
			share_data: share,
			log_blocked_ips: log,
		} = state.blocking;
		return {
			license: null,
			settings: {
				block_method: method,
				share_data: Boolean( share ),
				log_blocked_ips: Boolean( log ),
			},
		};
	}

	return { license: null, settings: {} };
}

/**
 * Leaves out settings the network locked, and settings this site does not
 * have (a module that is not loaded).
 *
 * @param {Object}   settings Settings to write.
 * @param {Object[]} schema   settings/schema `settings`.
 * @return {Object} Settings that can be written.
 */
export function writable( settings, schema ) {
	const byKey = Object.fromEntries(
		( schema || [] ).map( ( entry ) => [ entry.key, entry ] )
	);
	return Object.fromEntries(
		Object.entries( settings ).filter(
			( [ key ] ) => byKey[ key ] && ! byKey[ key ].locked
		)
	);
}

/**
 * The setting keys a step changes (the "Settings this step changes" note).
 *
 * @param {number} step  Step.
 * @param {Array}  items protectItems().
 * @return {string[]} Keys.
 */
export function stepKeys( step, items = [] ) {
	if ( 1 === step ) {
		return [ 'zerospam', 'zerospam_license' ];
	}
	if ( 2 === step ) {
		return items.flatMap( ( i ) => i.switches );
	}
	if ( 3 === step ) {
		return [ 'block_method', 'share_data', 'log_blocked_ips' ];
	}
	return [];
}
