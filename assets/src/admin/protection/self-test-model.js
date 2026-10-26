/**
 * "Test my protection" (pure; unit tested): the state machine of the
 * self-test panel.
 *
 * The self-test route runs every check in one request, so the panel shows
 * the steps pending, the first one running while the request is out, then
 * lays the answers down one by one (all at once with reduced motion).
 *
 * idle → running → revealing → done; a failed request goes to error, and a
 * second run within the minute to limited (the route answers 429).
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/** The checks, in the order the route runs them (Self_Test_Controller::run()). */
export const STEPS = [
	'honeypot',
	'browser_check',
	'lists',
	'reputation',
	'ip_source',
];

/**
 * Names of the checks and what each does while it waits.
 *
 * @return {Object<string, [string, string]>} Name and description by check.
 */
export const stepNames = () => ( {
	honeypot: [
		__( 'Honeypot trap', 'zero-spam' ),
		__(
			'Sends a test comment that fills in the hidden field.',
			'zero-spam'
		),
	],
	browser_check: [
		__( 'Browser check', 'zero-spam' ),
		__( 'Sends a form without the browser check key.', 'zero-spam' ),
	],
	lists: [
		__( 'Block and allow lists', 'zero-spam' ),
		__( 'Loads your block and allow lists.', 'zero-spam' ),
	],
	reputation: [
		__( 'Reputation lookup', 'zero-spam' ),
		__( 'Asks the reputation services about a test address.', 'zero-spam' ),
	],
	ip_source: [
		__( 'Visitor IP address', 'zero-spam' ),
		__( 'Checks where visitor addresses come from.', 'zero-spam' ),
	],
} );

/** The panel before the first run. */
export const initialTest = {
	phase: 'idle',
	steps: [],
	shown: 0,
	result: null,
	message: '',
	retryAfter: 0,
};

/**
 * @param {Object} state  State.
 * @param {Object} action { type: start | answer | reveal | fail, data?, error?, all? }.
 * @return {Object} Next state.
 */
export function testReducer( state, action ) {
	switch ( action.type ) {
		case 'start':
			return {
				...initialTest,
				phase: 'running',
				steps: STEPS.map( ( id, i ) => ( {
					id,
					status: 0 === i ? 'running' : 'pending',
				} ) ),
			};

		case 'answer': {
			const answered = Array.isArray( action.data?.steps )
				? action.data.steps.filter( ( s ) => s && s.id )
				: [];
			const ids = answered.map( ( s ) => s.id );
			const order = [
				...STEPS.filter( ( id ) => ids.includes( id ) ),
				...ids.filter( ( id ) => ! STEPS.includes( id ) ),
			];
			const byId = {};
			answered.forEach( ( s ) => {
				byId[ s.id ] = s;
			} );
			return {
				...state,
				phase: order.length ? 'revealing' : 'done',
				result: action.data,
				shown: 0,
				steps: order.map( ( id, i ) => ( {
					id,
					status: 0 === i ? 'running' : 'pending',
					final: byId[ id ],
				} ) ),
			};
		}

		case 'reveal': {
			if ( 'revealing' !== state.phase ) {
				return state;
			}
			const shown = action.all
				? state.steps.length
				: Math.min( state.steps.length, state.shown + 1 );
			const steps = state.steps.map( ( step, i ) => {
				if ( i < shown ) {
					return {
						...step,
						status: [ 'pass', 'warn', 'fail' ].includes(
							step.final?.status
						)
							? step.final.status
							: 'warn',
						message: step.final?.message || '',
						ms: Number( step.final?.ms ) || 0,
					};
				}
				return i === shown ? { ...step, status: 'running' } : step;
			} );
			return {
				...state,
				steps,
				shown,
				phase: shown >= steps.length ? 'done' : 'revealing',
			};
		}

		case 'fail': {
			const error = action.error || {};
			if ( 'zerospam_self_test_rate_limited' === error.code ) {
				const done = 'done' === state.phase || null !== state.result;
				return {
					...state,
					phase: 'limited',
					steps: done ? state.steps : [],
					message:
						error.message ||
						__(
							'The self-test just ran. Try again in a minute.',
							'zero-spam'
						),
					retryAfter: Number( error.data?.retry_after ) || 60,
				};
			}
			return {
				...initialTest,
				phase: 'error',
				message:
					error.message ||
					__(
						'The self-test could not run. Please try again.',
						'zero-spam'
					),
			};
		}

		default:
			return state;
	}
}

/**
 * Whether a run is in progress.
 *
 * @param {Object} state State.
 * @return {boolean} Busy.
 */
export const isBusy = ( state ) =>
	'running' === state.phase || 'revealing' === state.phase;

/**
 * Counts of the finished checks.
 *
 * @param {Object} state State.
 * @return {{pass: number, warn: number, fail: number, total: number, ms: number}} Counts.
 */
export function counts( state ) {
	const out = { pass: 0, warn: 0, fail: 0, total: state.steps.length, ms: 0 };
	state.steps.forEach( ( step ) => {
		if ( out[ step.status ] !== undefined && 'total' !== step.status ) {
			out[ step.status ] += 1;
		}
		out.ms += Number( step.ms ) || 0;
	} );
	return out;
}

/**
 * Seconds as "1.2".
 *
 * @param {number} ms Milliseconds.
 * @return {string} Seconds.
 */
const seconds = ( ms ) => ( Math.max( 0, ms ) / 1000 ).toFixed( 1 );

/**
 * The panel's heading.
 *
 * @param {Object} state State.
 * @return {string} Heading.
 */
export function heading( state ) {
	if ( isBusy( state ) ) {
		return __( 'Testing your protection…', 'zero-spam' );
	}
	if ( 'error' === state.phase ) {
		return __( 'The self-test could not run', 'zero-spam' );
	}
	if ( 'limited' === state.phase ) {
		return __( 'The self-test just ran', 'zero-spam' );
	}
	const c = counts( state );
	if ( c.fail ) {
		return __( 'A check failed', 'zero-spam' );
	}
	if ( c.warn ) {
		return __( 'Some checks need a look', 'zero-spam' );
	}
	return __( 'All checks passed', 'zero-spam' );
}

/**
 * The result line (spoken in a polite live region as it changes).
 *
 * @param {Object} state State.
 * @return {{tone: string, text: string}} Line.
 */
export function summary( state ) {
	const total = state.steps.length || STEPS.length;
	switch ( state.phase ) {
		case 'running':
			return {
				tone: '',
				text: sprintf(
					/* translators: %d: number of checks */
					_n(
						'Running %d check…',
						'Running %d checks…',
						total,
						'zero-spam'
					),
					total
				),
			};
		case 'revealing':
			return {
				tone: '',
				text: sprintf(
					/* translators: 1: number of the check, 2: number of checks */
					__( 'Checking %1$d of %2$d…', 'zero-spam' ),
					Math.min( total, state.shown + 1 ),
					total
				),
			};
		case 'error':
			return { tone: 'fail', text: state.message };
		case 'limited':
			return { tone: 'warn', text: state.message };
		case 'done': {
			const c = counts( state );
			if ( c.fail ) {
				return {
					tone: 'fail',
					text: sprintf(
						/* translators: 1: checks that failed, 2: number of checks */
						_n(
							'%1$d of %2$d checks failed. The details are below.',
							'%1$d of %2$d checks failed. The details are below.',
							c.fail,
							'zero-spam'
						),
						c.fail,
						c.total
					),
				};
			}
			if ( c.warn ) {
				return {
					tone: 'warn',
					text: sprintf(
						/* translators: 1: checks that passed, 2: number of checks, 3: checks to look at */
						_n(
							'%1$d of %2$d checks passed; %3$d needs a look.',
							'%1$d of %2$d checks passed; %3$d need a look.',
							c.warn,
							'zero-spam'
						),
						c.pass,
						c.total,
						c.warn
					),
				};
			}
			return {
				tone: 'ok',
				text: sprintf(
					/* translators: 1: number of checks, 2: seconds */
					__(
						'All %1$d checks passed in %2$s s. Your site is protected.',
						'zero-spam'
					),
					c.total,
					seconds( c.ms )
				),
			};
		}
		default:
			return { tone: '', text: '' };
	}
}

/**
 * The time column of a step.
 *
 * @param {Object} step Step.
 * @return {string} Label.
 */
export function stepTime( step ) {
	switch ( step.status ) {
		case 'pending':
			return __( 'Waiting', 'zero-spam' );
		case 'running':
			return __( 'Checking…', 'zero-spam' );
		case 'pass':
			return sprintf(
				/* translators: %s: seconds */
				__( 'Passed · %s s', 'zero-spam' ),
				seconds( step.ms )
			);
		case 'warn':
			return sprintf(
				/* translators: %s: seconds */
				__( 'Needs a look · %s s', 'zero-spam' ),
				seconds( step.ms )
			);
		default:
			return sprintf(
				/* translators: %s: seconds */
				__( 'Failed · %s s', 'zero-spam' ),
				seconds( step.ms )
			);
	}
}

/**
 * How long the Zero Spam network took to answer the last self-test, if it was asked.
 *
 * @param {Object} state State.
 * @return {number|null} Milliseconds.
 */
export function networkLatency( state ) {
	const step = ( state.result?.steps || [] ).find(
		( s ) => 'reputation' === s.id
	);
	const service = ( step?.services || [] ).find(
		( s ) => 'zero_spam' === s.id
	);
	return service && service.ok ? Number( service.ms ) || 0 : null;
}
