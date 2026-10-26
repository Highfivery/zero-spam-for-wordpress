/**
 * Reads a route whose path follows the view (filters, page, period): the
 * last answer stays shown while the next loads, and an answer that arrives
 * after a newer request was sent is dropped.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { request } from '../data/api';

/**
 * @param {string|null} path         Route, or null to wait.
 * @param {Object}      [opts]       Options.
 * @param {boolean}     [opts.total] Read X-WP-Total too.
 * @return {{data: unknown, total: number|null, error: unknown, loading: boolean, reload: () => Promise<void>, setData: (updater: unknown) => void}} State.
 */
export function useQuery( path, { total = false } = {} ) {
	const [ state, setState ] = useState( {
		data: undefined,
		total: null,
		error: null,
		loading: Boolean( path ),
	} );
	const seq = useRef( 0 );
	const alive = useRef( true );

	const load = useCallback( () => {
		if ( ! path ) {
			return Promise.resolve();
		}
		const mine = ++seq.current;
		setState( ( s ) => ( { ...s, loading: true } ) );
		const req = total
			? request( { path, parse: false } ).then( ( response ) =>
					response.json().then( ( data ) => ( {
						data,
						total: response.headers.has( 'X-WP-Total' )
							? Number( response.headers.get( 'X-WP-Total' ) )
							: null,
					} ) )
				)
			: request( { path } ).then( ( data ) => ( { data, total: null } ) );
		return req.then(
			( answer ) => {
				if ( alive.current && mine === seq.current ) {
					setState( { ...answer, error: null, loading: false } );
				}
			},
			( error ) => {
				if ( alive.current && mine === seq.current ) {
					setState( ( s ) => ( {
						...s,
						error: error || {},
						loading: false,
					} ) );
				}
			}
		);
	}, [ path, total ] );

	useEffect( () => {
		alive.current = true;
		load();
		return () => {
			alive.current = false;
		};
	}, [ load ] );

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
