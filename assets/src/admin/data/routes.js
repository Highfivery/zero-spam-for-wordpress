/**
 * Page addresses and sub-tabs.
 *
 * Every area is a real admin page (admin.php?page=zero-spam-{area}); views
 * inside a page are query arguments (&tab=, &card=, &section=, &item=)
 * kept in the address bar with the History API, so the back button and
 * bookmarks work and the server can open the same view.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { boot } from './api';

export const AREAS = [
	'overview',
	'activity',
	'rules',
	'protection',
	'settings',
	'help',
];

/**
 * The address of an area, with optional view arguments.
 *
 * @param {string} area   Area.
 * @param {Object} [args] Query arguments.
 * @return {string} URL.
 */
export function areaUrl( area, args = {} ) {
	const base = boot.urls[ area ] || boot.urls.overview || '';
	const extra = Object.entries( args )
		.filter( ( [ , v ] ) => v !== undefined && v !== null && '' !== v )
		.map(
			( [ k, v ] ) =>
				`${ encodeURIComponent( k ) }=${ encodeURIComponent( v ) }`
		)
		.join( '&' );
	if ( ! extra ) {
		return base;
	}
	return base + ( base.includes( '?' ) ? '&' : '?' ) + extra;
}

/**
 * Adds UTM arguments to a zerospam.org link.
 *
 * @param {string} url    Address.
 * @param {string} medium Where in the plugin the link is.
 * @return {string} URL.
 */
export function utm( url, medium ) {
	const sep = url.includes( '?' ) ? '&' : '?';
	return `${ url }${ sep }utm_source=plugin&utm_medium=${ encodeURIComponent( medium ) }&utm_campaign=zero-spam-6`;
}

const readTab = ( fallback ) => {
	const value = new URLSearchParams( window.location.search ).get( 'tab' );
	return value && /^[a-z0-9_-]{1,32}$/.test( value ) ? value : fallback;
};

/**
 * The current sub-tab (?tab=), changed with history.pushState.
 *
 * @param {string}   fallback Default tab.
 * @param {string[]} [valid]  Allowed tabs.
 * @return {[string, (tab: string) => void]} Tab and setter.
 */
export function useTab( fallback, valid ) {
	const check = useCallback(
		( tab ) => ( ! valid || valid.includes( tab ) ? tab : fallback ),
		// eslint-disable-next-line react-hooks/exhaustive-deps -- valid is a constant list.
		[ fallback ]
	);
	const [ tab, setTabState ] = useState( () => check( readTab( fallback ) ) );

	useEffect( () => {
		const onPop = () => setTabState( check( readTab( fallback ) ) );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [ check, fallback ] );

	const setTab = useCallback(
		( next ) => {
			const value = check( next );
			const url = new URL( window.location.href );
			if ( value === fallback ) {
				url.searchParams.delete( 'tab' );
			} else {
				url.searchParams.set( 'tab', value );
			}
			window.history.pushState( { tab: value }, '', url.toString() );
			setTabState( value );
		},
		[ check, fallback ]
	);

	return [ tab, setTab ];
}
