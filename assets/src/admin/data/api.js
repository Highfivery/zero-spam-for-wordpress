/**
 * The REST client: the api-fetch package (with WordPress's nonce and the
 * preloading middleware the page adds, see Admin_App::enqueue()), plus a
 * count of requests in flight that drives the mark's Thinking state.
 */

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

export const NS = '/zero-spam/v1';

/** Fields of a live feed entry (the Overview's latest blocks). */
const FEED_FIELDS =
	'id,date,type,type_label,ip,country,country_name,failed,failed_label';

/** Paths, exactly as preloaded by Admin_App (the preload cache matches them). */
export const PATHS = {
	license: `${ NS }/license`,
	prefs: `${ NS }/ui/prefs`,
	schema: `${ NS }/settings/schema`,
	summary: ( range ) => `${ NS }/stats/summary?range=${ range }`,
	timeseries: `${ NS }/stats/timeseries`,
	modules: `${ NS }/protection/modules`,
	// The latest entries are not paged: no_count skips the total.
	activity: `${ NS }/activity?per_page=6&no_count=1&_fields=${ FEED_FIELDS }`,
	news: `${ NS }/news`,
	since: ( id ) =>
		`${ NS }/activity?since_id=${ Number( id ) || 0 }&per_page=20&_fields=${ FEED_FIELDS }`,
};

/** The page's bootstrap data (Admin_App::bootstrap()). */
export const boot = Object.assign(
	{
		area: 'overview',
		tab: '',
		version: '',
		nowLocal: Date.now(),
		site: { name: '', url: '' },
		urls: {},
		isMultisite: false,
		newsEnabled: true,
		environment: { wordpress: '', php: '', ipSource: 'direct' },
	},
	typeof window !== 'undefined' ? window.zeroSpamAdmin || {} : {}
);

const offset = boot.nowLocal - Date.now();

/** @return {number} Site-local now in milliseconds (read as UTC). */
export const nowLocal = () => Date.now() + offset;

let inFlight = 0;
const listeners = new Set();
const notify = () => listeners.forEach( ( fn ) => fn( inFlight ) );

/**
 * Subscribes to the number of requests in flight.
 *
 * @param {(...args: unknown[]) => unknown} fn Listener.
 * @return {(...args: unknown[]) => unknown} Unsubscribe.
 */
export function onBusy( fn ) {
	listeners.add( fn );
	return () => listeners.delete( fn );
}

/**
 * apiFetch, counted.
 *
 * @param {Object} options apiFetch options.
 * @return {Promise} Response.
 */
export function request( options ) {
	inFlight++;
	notify();
	return apiFetch( options ).finally( () => {
		inFlight--;
		notify();
	} );
}

/**
 * Takes a preloaded answer out of the page (Admin_App prints the same object
 * it hands to apiFetch's preloading middleware as `zeroSpamPreload`). Read
 * once, so a later reload goes to the server.
 *
 * The middleware keeps its own copy of the answers and serves each one to
 * the first apiFetch() of its path: that copy is used up here too (it is
 * answered from memory, no request is sent), or the first reload, after a
 * change, would get the answer of the page load again.
 *
 * @param {string|null} path Route, exactly as preloaded.
 * @return {{body: unknown, headers: Object}|undefined} The answer.
 */
export function takePreloaded( path ) {
	const store =
		'undefined' !== typeof window ? window.zeroSpamPreload : undefined;
	if ( ! path || ! store || ! store[ path ] ) {
		return undefined;
	}
	const entry = store[ path ];
	delete store[ path ];
	if ( 'undefined' !== typeof window && window.wp?.apiFetch ) {
		apiFetch( { path, parse: false } ).catch( () => {} );
	}
	return entry;
}

/**
 * Reads a route once. A preloaded answer is used for the first render, so
 * the screen paints with its data and nothing shifts when it arrives.
 *
 * @param {string|null} path         Route, or null to skip.
 * @param {Object}      [opts]       Options.
 * @param {boolean}     [opts.parse] false to keep the response (headers).
 * @return {{data: unknown, error: unknown, loading: boolean, headers: Object, reload: () => Promise<void>, setData: (updater: unknown) => void}} State.
 */
export function useResource( path, { parse = true } = {} ) {
	const seeded = useRef( null );
	const [ state, setState ] = useState( () => {
		const entry = takePreloaded( path );
		seeded.current = entry ? path : null;
		return {
			data: entry ? entry.body : undefined,
			error: null,
			loading: Boolean( path ) && ! entry,
			// Lower-case names, as Headers.entries() gives them.
			headers: Object.fromEntries(
				Object.entries( entry?.headers || {} ).map( ( [ k, v ] ) => [
					k.toLowerCase(),
					v,
				] )
			),
		};
	} );
	const alive = useRef( true );

	const load = useCallback( () => {
		if ( ! path ) {
			return Promise.resolve();
		}
		setState( ( s ) => ( { ...s, loading: true } ) );
		const req = parse
			? request( { path } ).then( ( data ) => ( { data, headers: {} } ) )
			: request( { path, parse: false } ).then( ( response ) =>
					response.json().then( ( data ) => ( {
						data,
						headers: Object.fromEntries(
							response.headers.entries()
						),
					} ) )
				);
		return req.then(
			( { data, headers } ) => {
				if ( alive.current ) {
					setState( { data, headers, error: null, loading: false } );
				}
			},
			( error ) => {
				if ( alive.current ) {
					setState( ( s ) => ( {
						...s,
						error: error || {},
						loading: false,
					} ) );
				}
			}
		);
	}, [ path, parse ] );

	useEffect( () => {
		alive.current = true;
		if ( seeded.current && seeded.current === path ) {
			seeded.current = null;
			return () => {
				alive.current = false;
			};
		}
		load();
		return () => {
			alive.current = false;
		};
	}, [ load, path ] );

	const setData = useCallback(
		( updater ) =>
			setState( ( s ) => ( {
				...s,
				data:
					'function' === typeof updater ? updater( s.data ) : updater,
			} ) ),
		[]
	);

	return { ...state, reload: load, setData };
}
