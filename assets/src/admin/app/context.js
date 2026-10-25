/**
 * App-wide state: the user's preferences (ui/prefs), the license, the
 * protection modules, the 24-hour count, the news, and the Compare levels
 * drawer.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { boot, PATHS, request, useResource } from '../data/api';
import { createSaveQueue } from '../data/save-queue';
import { motionReduced, resolveTheme, themePref } from '../data/theme';
import { useFeedback } from './primitives/feedback';

const AppContext = createContext( null );

/** @return {Object} App state. */
export const useApp = () => useContext( AppContext );

/** License statuses that mean Enhanced Protection is working. */
const ENHANCED = [ 'valid', 'quota_exceeded', 'unreachable' ];

/**
 * Whether the site is on Enhanced Protection.
 *
 * @param {Object|undefined} license license route answer.
 * @return {boolean} Enhanced.
 */
export function isEnhanced( license ) {
	return Boolean(
		license && license.enabled && ENHANCED.includes( license.status )
	);
}

/**
 * Follows a media query.
 *
 * @param {string} query Media query.
 * @return {boolean} Matches.
 */
export function useMedia( query ) {
	const get = () =>
		'undefined' !== typeof window && window.matchMedia
			? window.matchMedia( query ).matches
			: false;
	const [ matches, setMatches ] = useState( get );
	useEffect( () => {
		const mq = window.matchMedia( query );
		const on = () => setMatches( mq.matches );
		mq.addEventListener( 'change', on );
		return () => mq.removeEventListener( 'change', on );
	}, [ query ] );
	return matches;
}

/**
 * @param {Object}  props          Props.
 * @param {Element} props.root     The app root (#zerospam-app).
 * @param {unknown} props.children App.
 */
export function AppProvider( { root, children } ) {
	const { toast } = useFeedback();
	const network = 'network' === boot.area;

	const prefs = useResource( PATHS.prefs );
	const license = useResource( network ? null : PATHS.license );
	const modules = useResource( network ? null : PATHS.modules );
	const day = useResource( network ? null : PATHS.summary( 1 ) );
	const news = useResource( boot.newsEnabled ? PATHS.news : null, {
		parse: false,
	} );

	const [ compare, setCompare ] = useState( null );

	const osDark = useMedia( '(prefers-color-scheme: dark)' );
	const osReduce = useMedia( '(prefers-reduced-motion: reduce)' );
	const themeValue = themePref( prefs.data?.theme ?? root.dataset.zsTheme );
	const reduceValue = prefs.data
		? prefs.data.reduce_motion
		: 'reduce' === root.dataset.zsMotion;

	// The root carries the theme and motion attributes (tokens.css, base.css).
	useEffect( () => {
		root.setAttribute( 'data-zs-theme', themeValue );
		if ( true === reduceValue ) {
			root.setAttribute( 'data-zs-motion', 'reduce' );
		} else {
			root.removeAttribute( 'data-zs-motion' );
		}
	}, [ root, themeValue, reduceValue ] );

	// One save at a time; on failure the server's state is loaded again.
	const handlers = useRef( {} );
	handlers.current = { reload: prefs.reload, toast };
	const queue = useRef( null );
	if ( ! queue.current ) {
		queue.current = createSaveQueue( {
			send: ( data ) =>
				request( { path: PATHS.prefs, method: 'POST', data } ),
			onSaved: ( saved ) => prefs.setData( saved ),
			onFailed: () => {
				handlers.current.reload();
				handlers.current.toast(
					__(
						'Your preference could not be saved. Please try again.',
						'zero-spam'
					)
				);
			},
		} );
	}

	/**
	 * Saves preferences (optimistic, in order; reloaded with a message on failure).
	 *
	 * @param {Object} changes Preferences to change.
	 * @return {Promise} Request.
	 */
	const savePrefs = useCallback(
		( changes ) => {
			prefs.setData( ( current ) => ( {
				...( current || {} ),
				...changes,
			} ) );
			return queue.current.save( changes );
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps -- setData is stable.
		[]
	);

	/**
	 * Adds an ID to the dismissed list.
	 *
	 * @param {string} id ID.
	 * @return {Promise} Request.
	 */
	// The latest list, for an Undo created several renders ago.
	const latest = useRef( prefs.data );
	latest.current = prefs.data;
	const dismissedNow = () =>
		Array.isArray( latest.current?.dismissed )
			? latest.current.dismissed
			: [];

	const dismiss = useCallback(
		( id ) => {
			const list = dismissedNow();
			return savePrefs( {
				dismissed: [ ...list.filter( ( d ) => d !== id ), id ].slice(
					-200
				),
			} );
		},

		[ savePrefs ]
	);

	/**
	 * Removes an ID from the dismissed list (Undo).
	 *
	 * @param {string} id ID.
	 * @return {Promise} Request.
	 */
	const undismiss = useCallback(
		( id ) => {
			const list = dismissedNow();
			return savePrefs( { dismissed: list.filter( ( d ) => d !== id ) } );
		},

		[ savePrefs ]
	);

	const value = useMemo(
		() => ( {
			root,
			network,
			prefs: prefs.data || {},
			prefsLoaded: undefined !== prefs.data,
			savePrefs,
			dismiss,
			undismiss,
			isDismissed: ( id ) =>
				Array.isArray( prefs.data?.dismissed ) &&
				prefs.data.dismissed.includes( id ),
			theme: resolveTheme( themeValue, osDark ),
			themePref: themeValue,
			reduced: motionReduced( reduceValue, osReduce ),
			osReduce,
			license: license.data,
			licenseLoaded:
				undefined !== license.data || Boolean( license.error ),
			enhanced: isEnhanced( license.data ),
			modules: modules.data,
			modulesState: modules,
			day: day.data,
			setDay: day.setData,
			news: Array.isArray( news.data ) ? news.data : [],
			newsState: news,
			compare,
			openCompare: ( source ) => setCompare( source || 'app' ),
			closeCompare: () => setCompare( null ),
		} ),
		[
			root,
			network,
			prefs,
			savePrefs,
			dismiss,
			undismiss,
			themeValue,
			osDark,
			reduceValue,
			osReduce,
			license,
			modules,
			day,
			news,
			compare,
		]
	);

	return (
		<AppContext.Provider value={ value }>{ children }</AppContext.Provider>
	);
}
