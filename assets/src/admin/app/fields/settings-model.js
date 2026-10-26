/**
 * The settings model behind the generic field renderer (pure; unit tested):
 * which control a schema entry gets, how values compare, what PATCH
 * /settings is sent, and how its errors map back to fields.
 *
 * Schema entries come from `zero-spam/v1/settings/schema`
 * (Settings_Schema::get()); third-party sections render with the same rules.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { plainText } from './rich-text';

/** Html settings that are action buttons, and the settings/actions route they run. */
export const ACTIONS = {
	use_recommended_settings: 'autoconfigure',
	regenerate_honeypot: 'regenerate-honeypot',
	update_blocked_email_domains: 'update-blocked-email-domains',
	update_disallowed_words: 'update-disallowed-words',
};

/** Actions that replace a list or settings: they ask before they run. */
export const CONFIRM_ACTIONS = [
	'autoconfigure',
	'update-blocked-email-domains',
	'update-disallowed-words',
];

/**
 * The value a checkbox stores when it is on (its first choice).
 *
 * @param {Object} entry Schema entry.
 * @return {string} Value.
 */
export function onValue( entry ) {
	const first = Array.isArray( entry?.options ) ? entry.options[ 0 ] : null;
	return first && '' !== first.value ? first.value : 'enabled';
}

/**
 * Whether a checkbox value means on.
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} value Value.
 * @return {boolean} On.
 */
export function isOn( entry, value ) {
	return true === value || ( '' !== value && value === onValue( entry ) );
}

/**
 * The control a schema entry is rendered with.
 *
 * @param {Object} entry Schema entry.
 * @return {string} switch, secret, text, url, number, date, textarea,
 *                  segmented, select, tags, action, help or none.
 */
export function controlFor( entry ) {
	switch ( entry?.type ) {
		case 'html':
			if ( ACTIONS[ entry.key ] ) {
				return 'action';
			}
			return entry.html ? 'help' : 'none';
		case 'checkbox':
			return 'switch';
		case 'radio':
			return 'segmented';
		case 'select':
			return entry.multiple ? 'tags' : 'select';
		case 'number':
		case 'date':
		case 'textarea':
		case 'url':
			return entry.secret ? 'secret' : entry.type;
		default:
			return entry?.secret ? 'secret' : 'text';
	}
}

/**
 * Whether a schema entry holds a value (everything but html).
 *
 * @param {Object} entry Schema entry.
 * @return {boolean} Holds a value.
 */
export const holdsValue = ( entry ) =>
	Boolean( entry ) && 'html' !== entry.type;

/**
 * A value in a form that compares equal however it was typed or stored.
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} value Value.
 * @return {string} Comparable form.
 */
export function comparable( entry, value ) {
	switch ( entry?.type ) {
		case 'checkbox':
			return isOn( entry, value ) ? '1' : '0';
		case 'number':
			return null === value || undefined === value || '' === value
				? ''
				: String( Number( value ) );
		case 'select':
			if ( entry.multiple ) {
				return JSON.stringify(
					( Array.isArray( value ) ? value : [] ).map( String ).sort()
				);
			}
			return null === value || undefined === value || false === value
				? ''
				: String( value );
		default:
			return null === value || undefined === value || false === value
				? ''
				: String( value );
	}
}

/**
 * Whether two values of a setting are the same.
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} a     Value.
 * @param {unknown} b     Value.
 * @return {boolean} Same.
 */
export const sameValue = ( entry, a, b ) =>
	comparable( entry, a ) === comparable( entry, b );

/**
 * Schema entries by key.
 *
 * @param {Object|undefined} schema settings/schema answer.
 * @return {Object<string, Object>} Entries.
 */
export function byKey( schema ) {
	const map = {};
	( schema?.settings || [] ).forEach( ( entry ) => {
		map[ entry.key ] = entry;
	} );
	return map;
}

/**
 * Whether a draft value is a change.
 *
 * Secrets are write-only: a draft is a new value, or '' to remove the
 * saved one (only a change when one is set).
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} draft Draft value.
 * @return {boolean} Changed.
 */
export function isChange( entry, draft ) {
	if ( ! entry || undefined === draft ) {
		return false;
	}
	if ( entry.secret ) {
		return '' !== draft || Boolean( entry.value?.set );
	}
	return ! sameValue( entry, draft, entry.value );
}

/**
 * The value PATCH /settings is sent for a draft.
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} draft Draft value.
 * @return {unknown} Value to send.
 */
export function toPayloadValue( entry, draft ) {
	switch ( entry.type ) {
		case 'checkbox':
			// The server stores `disabled` for a switch that is on unless turned off.
			return isOn( entry, draft ) ? onValue( entry ) : false;
		case 'number': {
			const text = String( draft ?? '' ).trim();
			return '' !== text && Number.isFinite( Number( text ) )
				? Number( text )
				: text;
		}
		case 'select':
			return entry.multiple
				? ( Array.isArray( draft ) ? draft : [] ).map( String )
				: draft;
		default:
			return 'string' === typeof draft ? draft : String( draft ?? '' );
	}
}

/**
 * The PATCH /settings body: every changed setting.
 *
 * @param {Object<string, Object>}  entries Schema entries by key.
 * @param {Object<string, unknown>} draft   Draft values by key.
 * @return {Object<string, unknown>} Body.
 */
export function dirtyPayload( entries, draft ) {
	const payload = {};
	Object.keys( draft || {} ).forEach( ( key ) => {
		const entry = entries[ key ];
		if (
			holdsValue( entry ) &&
			! entry.locked &&
			isChange( entry, draft[ key ] )
		) {
			payload[ key ] = toPayloadValue( entry, draft[ key ] );
		}
	} );
	return payload;
}

/**
 * The saved values of the settings a save changes (secrets left out), for
 * an Undo.
 *
 * @param {Object<string, Object>}  entries Schema entries by key.
 * @param {Object<string, unknown>} payload Body that was saved.
 * @return {Object<string, unknown>|null} Previous values, or null when a secret changed.
 */
export function undoPayload( entries, payload ) {
	const before = {};
	for ( const key of Object.keys( payload ) ) {
		const entry = entries[ key ];
		if ( ! entry || entry.secret ) {
			return null;
		}
		before[ key ] = toPayloadValue( entry, entry.value );
	}
	return before;
}

/**
 * The schema with the values a save returned.
 *
 * @param {Object}                  schema   settings/schema answer.
 * @param {Object<string, unknown>} settings Resolved values (secrets masked).
 * @return {Object} Schema.
 */
export function withValues( schema, settings ) {
	if ( ! schema || ! settings ) {
		return schema;
	}
	return {
		...schema,
		settings: schema.settings.map( ( entry ) =>
			Object.prototype.hasOwnProperty.call( settings, entry.key )
				? { ...entry, value: settings[ entry.key ] }
				: entry
		),
	};
}

/**
 * Strips the "Invalid value for "key"." prefix the route puts before the reason.
 *
 * @param {string} message Message.
 * @return {string} Reason.
 */
const reason = ( message ) =>
	String( message || '' ).replace( /^[^"]*"[^"]+"\.\s*/, '' ) ||
	String( message || '' );

/**
 * Maps a failed save to messages: one per field, and one for the form.
 *
 * @param {Object} error apiFetch error.
 * @return {{message: string, fields: Object<string, string>}} Errors.
 */
export function errorsFrom( error ) {
	const data = error?.data || {};
	const fields = {};

	if ( 'rest_setting_locked' === error?.code ) {
		( data.locked_keys || [] ).forEach( ( key ) => {
			fields[ key ] = __(
				'Locked by the network administrator. This setting cannot be changed on this site.',
				'zero-spam'
			);
		} );
		return {
			message: __(
				'Some settings are locked by your network administrator. Nothing was saved.',
				'zero-spam'
			),
			fields,
		};
	}

	const params = data.params || data.errors;
	if ( params && 'object' === typeof params ) {
		Object.keys( params ).forEach( ( key ) => {
			fields[ key ] = reason( params[ key ] );
		} );
	}

	const count = Object.keys( fields ).length;
	if ( count ) {
		return {
			message: sprintf(
				/* translators: %d: number of settings */
				_n(
					'Nothing was saved. Check the %d highlighted setting.',
					'Nothing was saved. Check the %d highlighted settings.',
					count,
					'zero-spam'
				),
				count
			),
			fields,
		};
	}

	return {
		message:
			error?.message ||
			__(
				'Your settings could not be saved. Please try again.',
				'zero-spam'
			),
		fields,
	};
}

/**
 * The label of a choice.
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} value Choice.
 * @return {string} Label.
 */
export function optionLabel( entry, value ) {
	const option = ( entry?.options || [] ).find(
		( o ) => String( o.value ) === String( value )
	);
	return option && option.label ? option.label : String( value );
}

/**
 * A value as text (network defaults, recommendations).
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} value Value.
 * @return {string} Text ('' when empty).
 */
export function formatValue( entry, value ) {
	if ( ! entry ) {
		return '';
	}
	if ( 'checkbox' === entry.type ) {
		return isOn( entry, value )
			? __( 'On', 'zero-spam' )
			: __( 'Off', 'zero-spam' );
	}
	if ( Array.isArray( value ) ) {
		return value.map( ( v ) => optionLabel( entry, v ) ).join( ', ' );
	}
	if (
		null === value ||
		undefined === value ||
		false === value ||
		'' === value
	) {
		return '';
	}
	if ( [ 'radio', 'select' ].includes( entry.type ) ) {
		return optionLabel( entry, value );
	}
	if ( 'number' === entry.type && Number.isFinite( Number( value ) ) ) {
		const number = Number( value ).toLocaleString();
		if ( ! entry.suffix ) {
			return number;
		}
		// "80%", "90 days", "5,000 ms".
		return '%' === entry.suffix
			? `${ number }%`
			: `${ number } ${ entry.suffix }`;
	}
	return String( value );
}

/**
 * The recommended value as text, or '' (not shown for free text, secrets,
 * or when the description already recommends something).
 *
 * @param {Object} entry Schema entry.
 * @return {string} Text.
 */
export function recommendedText( entry ) {
	if (
		! entry ||
		null === entry.recommended ||
		undefined === entry.recommended ||
		entry.secret ||
		[ 'html', 'text', 'url', 'textarea', 'date' ].includes( entry.type ) ||
		/recommended/i.test( plainText( entry.description || '' ) )
	) {
		return '';
	}
	if ( 'checkbox' === entry.type ) {
		return isOn( entry, entry.recommended ) ? __( 'On', 'zero-spam' ) : '';
	}
	if ( [ 'radio', 'select' ].includes( entry.type ) ) {
		const label = optionLabel( entry, entry.recommended );
		return /recommended/i.test( label ) ? '' : label;
	}
	return formatValue( entry, entry.recommended );
}

/**
 * When a setting applies, as a sentence ('' when always).
 *
 * @param {Object}                 entry   Schema entry.
 * @param {Object<string, Object>} entries Entries by key.
 * @return {{title: string, value: string}|null} The setting it depends on and the value it needs ('' = on).
 */
export function dependency( entry, entries ) {
	const dep = entry?.depends_on;
	if ( ! dep || ! entries[ dep.key ] ) {
		return null;
	}
	const parent = entries[ dep.key ];
	if ( 'checkbox' === parent.type && dep.value === onValue( parent ) ) {
		return { title: parent.title, value: '' };
	}
	return { title: parent.title, value: optionLabel( parent, dep.value ) };
}

/**
 * An ID made from a setting key (keys of extensions may hold any character).
 *
 * @param {string} key Setting key.
 * @return {string} ID-safe key.
 */
export const idOf = ( key ) => String( key ).replace( /[^A-Za-z0-9_-]/g, '-' );
