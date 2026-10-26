/**
 * The settings being edited on a page: drafts over the saved schema values,
 * what changed, saving with PATCH /settings, per-setting errors, and a
 * warning before leaving the page with unsaved changes.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { NS, request } from '../../data/api';
import {
	byKey,
	dirtyPayload,
	errorsFrom,
	isChange,
	undoPayload,
	withValues,
} from './settings-model';

/**
 * @param {Object} schema useResource() of settings/schema.
 * @return {Object} The editor: entries, valueOf, draftOf, set, payload, count, errors, message, saving, save, discard, restore.
 */
export function useSettings( schema ) {
	const [ draft, setDraft ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const entries = useMemo( () => byKey( schema.data ), [ schema.data ] );
	const payload = useMemo(
		() => dirtyPayload( entries, draft ),
		[ entries, draft ]
	);
	const count = Object.keys( payload ).length;

	// Leaving the page (another area, a link, closing the tab) asks first.
	useEffect( () => {
		if ( ! count ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ count ] );

	const has = ( key ) => Object.prototype.hasOwnProperty.call( draft, key );

	/**
	 * The current value: the draft, else the saved value (masked for secrets).
	 *
	 * @param {string} key Setting key.
	 * @return {unknown} Value.
	 */
	const valueOf = ( key ) =>
		has( key ) ? draft[ key ] : entries[ key ]?.value;

	/**
	 * The draft only (secrets: what was typed, '' to remove, undefined = no change).
	 *
	 * @param {string} key Setting key.
	 * @return {unknown} Draft.
	 */
	const draftOf = ( key ) => ( has( key ) ? draft[ key ] : undefined );

	const set = useCallback(
		( key, value ) => {
			setDraft( ( current ) => {
				const next = { ...current };
				if ( isChange( entries[ key ], value ) ) {
					next[ key ] = value;
				} else {
					delete next[ key ];
				}
				return next;
			} );
			setErrors( ( current ) => {
				if ( ! current[ key ] ) {
					return current;
				}
				const next = { ...current };
				delete next[ key ];
				return next;
			} );
		},
		[ entries ]
	);

	const applied = ( answer ) => {
		schema.setData( ( current ) =>
			withValues( current, answer?.settings )
		);
		// Sources and locks may have changed with the values (network defaults).
		schema.reload();
	};

	/**
	 * Saves every change.
	 *
	 * @return {Promise<{undo: Object|null, saved: Object}>} Resolves with the
	 *         previous values (for Undo; null when a secret changed); rejects
	 *         with { message, fields }.
	 */
	const save = () => {
		const body = payload;
		const undo = undoPayload( entries, body );
		setSaving( true );
		setMessage( '' );
		return request( {
			path: `${ NS }/settings`,
			method: 'PATCH',
			data: body,
		} )
			.then( ( answer ) => {
				applied( answer );
				setDraft( {} );
				setErrors( {} );
				return { undo, saved: body };
			} )
			.catch( ( error ) => {
				const mapped = errorsFrom( error );
				if ( 'rest_setting_locked' === error?.code ) {
					// The network locked something since the page loaded.
					schema.reload();
				}
				setErrors( mapped.fields );
				setMessage( mapped.message );
				throw mapped;
			} )
			.finally( () => setSaving( false ) );
	};

	/**
	 * Saves values back (Undo).
	 *
	 * @param {Object} values Values by key.
	 * @return {Promise} Request.
	 */
	const restore = ( values ) =>
		request( {
			path: `${ NS }/settings`,
			method: 'PATCH',
			data: values,
		} ).then( applied );

	const discard = () => {
		setDraft( {} );
		setErrors( {} );
		setMessage( '' );
	};

	return {
		entries,
		valueOf,
		draftOf,
		set,
		payload,
		count,
		errors,
		message,
		saving,
		save,
		discard,
		restore,
	};
}
