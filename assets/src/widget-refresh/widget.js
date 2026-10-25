/**
 * The dashboard widget's Refresh button.
 *
 * Reads zero-spam/v1/stats/timeseries (the sparkline and the figures) and
 * zero-spam/v1/activity (the latest detections) with the REST nonce, updates
 * the elements the server rendered (data-zs-field), and announces the result
 * in the widget's polite live region: "Data refreshed." or the error. While it
 * loads, the button is marked busy and its icon turns (slowly under reduced
 * motion; widget.css). Only text is written into the page (textContent), never
 * HTML: detection data comes from visitors.
 */

/**
 * Internal dependencies
 */
import {
	deltaText,
	endpoint,
	figures,
	latestItems,
	numberFormat,
	relativeTime,
	sparkPaths,
	sparkText,
} from './model';

/**
 * Wires one widget root.
 *
 * @param {HTMLElement}              root            Element with data-zs-widget.
 * @param {Object}                   config          window.zerospamWidget (Dashboard_Widget::script_config()).
 * @param {Object}                   [deps]          Replaceable for tests.
 * @param {typeof window.fetch}      [deps.fetch]    fetch().
 * @param {() => number}             [deps.now]      Current time in milliseconds.
 * @param {typeof window.setTimeout} [deps.schedule] setTimeout().
 * @return {{refresh: () => Promise<boolean>}|null} Controls, or null without a Refresh button.
 */
export function initWidget( root, config, deps = {} ) {
	const button = root.querySelector( '[data-zs-refresh]' );
	if ( ! button || ! config || ! config.root ) {
		return null;
	}

	const doFetch = deps.fetch || window.fetch.bind( window );
	const now = deps.now || Date.now;
	const schedule = deps.schedule || window.setTimeout.bind( window );
	const i18n = config.i18n || {};
	const number = numberFormat( config.locale );
	const field = ( name ) =>
		root.querySelector( `[data-zs-field="${ name }"]` );
	const status = field( 'status' );
	let busy = false;

	/**
	 * Puts a message in the live region; cleared first so the same message is
	 * announced again.
	 *
	 * @param {string}  text  Message.
	 * @param {boolean} error Whether it reports a failure.
	 */
	const announce = ( text, error = false ) => {
		if ( ! status ) {
			return;
		}
		status.textContent = '';
		status.toggleAttribute( 'data-zs-error', error );
		schedule( () => {
			status.textContent = text;
		}, 50 );
	};

	const get = async ( path, params ) => {
		const response = await doFetch( endpoint( config.root, path, params ), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce, Accept: 'application/json' },
		} );
		if ( ! response.ok ) {
			throw new Error( `HTTP ${ response.status }` );
		}
		return response.json();
	};

	const render = ( series, activity ) => {
		const days = ( series.days || [] ).map(
			( day ) => Number( day.total ) || 0
		);
		const fig = figures( days );
		const delta = deltaText( fig, i18n, number );
		const paths = sparkPaths( days );

		field( 'total' ).textContent = number( fig.total );
		field( 'delta' ).setAttribute( 'data-zs-direction', delta.direction );
		field( 'delta' ).textContent = delta.text;
		field( 'spark-line' ).setAttribute( 'd', paths.line );
		field( 'spark-area' ).setAttribute( 'd', paths.area );
		field( 'spark-text' ).textContent = sparkText( days, i18n, number );

		const list = field( 'latest' );
		const empty = field( 'empty' );
		const items = latestItems( activity, config.skip, config.latest || 3 );
		const doc = root.ownerDocument;

		list.replaceChildren(
			...items.map( ( item ) => {
				const li = doc.createElement( 'li' );
				li.className = 'zs-w-item';
				const name = doc.createElement( 'span' );
				name.className = 'zs-w-name';
				name.textContent = item.label;
				const ip = doc.createElement( 'span' );
				ip.className = 'zs-w-ip zs-mono';
				ip.textContent = item.ip;
				const when = doc.createElement( 'time' );
				when.className = 'zs-w-when';
				when.setAttribute( 'datetime', item.date );
				when.textContent = relativeTime(
					item.date,
					now(),
					config.locale
				);
				li.append( name, ip, when );
				return li;
			} )
		);
		list.hidden = ! items.length;
		empty.hidden = !! items.length;
	};

	const refresh = async () => {
		if ( busy ) {
			return false;
		}
		busy = true;
		button.setAttribute( 'aria-disabled', 'true' );
		root.setAttribute( 'data-zs-busy', '' );
		announce( i18n.refreshing || '' );

		let ok = true;
		try {
			const [ series, activity ] = await Promise.all( [
				get( 'stats/timeseries', {
					range: String( config.days || 30 ),
					_fields: 'days',
				} ),
				get( 'activity', {
					per_page: '10',
					_fields: 'id,type,type_label,ip,date_gmt',
				} ),
			] );
			render( series, activity );
			announce( i18n.refreshed || '' );
		} catch {
			ok = false;
			announce( i18n.failed || '', true );
		} finally {
			busy = false;
			button.removeAttribute( 'aria-disabled' );
			root.removeAttribute( 'data-zs-busy' );
		}
		return ok;
	};

	button.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		refresh();
	} );

	return { refresh };
}
