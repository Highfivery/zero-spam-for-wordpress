/**
 * Checks for what an administrator types into the Rules forms (pure; unit
 * tested). They mirror the server's checks in
 * includes/rest/class-rulescontroller.php, so a mistake is explained next
 * to the field before anything is sent; the server stays the authority and
 * its field errors are shown the same way.
 *
 * IP addresses are compared and stored in canonical form, as
 * User::normalize_ip() writes them: IPv4 as typed (no leading zeros), IPv6
 * lower case and compressed, an IPv4-mapped IPv6 address as its IPv4 form.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/** Longest word or location the server accepts. */
export const MAX_TEXT = 100;

/** Largest "Minimum disallowed word length". */
export const MAX_MIN_LENGTH = 100;

/** Location key types a block can use (Rules_Controller::LOCATION_KEYS). */
export const LOCATION_TYPES = [ 'country_code', 'region_code', 'city', 'zip' ];

const IPV4 =
	/^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/;
const DOMAIN =
	/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/;

/**
 * Two 16-bit groups as a dotted IPv4 address.
 *
 * @param {number} a High group.
 * @param {number} b Low group.
 * @return {string} a.b.c.d
 */
const quad = ( a, b ) =>
	[ Math.floor( a / 256 ), a % 256, Math.floor( b / 256 ), b % 256 ].join(
		'.'
	);

/**
 * The 16-bit groups of an IPv6 address, or null.
 *
 * @param {string} value Address.
 * @return {number[]|null} Eight groups.
 */
function ipv6Groups( value ) {
	let text = value.toLowerCase();
	let tail = [];
	const lastColon = text.lastIndexOf( ':' );
	const after = text.slice( lastColon + 1 );

	// An embedded IPv4 address (::ffff:192.0.2.1).
	if ( after.includes( '.' ) ) {
		if ( ! IPV4.test( after ) ) {
			return null;
		}
		const o = after.split( '.' ).map( Number );
		tail = [ o[ 0 ] * 256 + o[ 1 ], o[ 2 ] * 256 + o[ 3 ] ];
		text = text.slice( 0, lastColon + 1 ) + '0:0';
	}

	const halves = text.split( '::' );
	if ( halves.length > 2 ) {
		return null;
	}
	const part = ( s ) => ( '' === s ? [] : s.split( ':' ) );
	const head = part( halves[ 0 ] );
	const rest = 2 === halves.length ? part( halves[ 1 ] ) : [];
	const given = head.length + rest.length;

	if ( 2 === halves.length ? given > 7 : 8 !== given ) {
		return null;
	}
	if (
		! [ ...head, ...rest ].every( ( g ) => /^[0-9a-f]{1,4}$/.test( g ) )
	) {
		return null;
	}

	const groups = [ ...head, ...Array( 8 - given ).fill( '0' ), ...rest ].map(
		( g ) => parseInt( g, 16 )
	);

	if ( tail.length ) {
		groups[ 6 ] = tail[ 0 ];
		groups[ 7 ] = tail[ 1 ];
	}
	return groups;
}

/**
 * Writes IPv6 groups the way inet_ntop() does: the longest run of two or
 * more zero groups (the first one on a tie) becomes "::", and an
 * IPv4-compatible address keeps its dotted tail.
 *
 * @param {number[]} groups Eight groups.
 * @return {string} Address.
 */
function ipv6Text( groups ) {
	let best = { base: -1, len: 0 };
	let cur = { base: -1, len: 0 };
	groups.forEach( ( g, i ) => {
		if ( 0 === g ) {
			cur =
				-1 === cur.base
					? { base: i, len: 1 }
					: { ...cur, len: cur.len + 1 };
			if ( cur.len > best.len ) {
				best = cur;
			}
		} else {
			cur = { base: -1, len: 0 };
		}
	} );
	if ( best.len < 2 ) {
		best = { base: -1, len: 0 };
	}

	const dotted = ( a, b ) => quad( a, b );
	let out = '';
	for ( let i = 0; i < 8; i++ ) {
		if ( i === best.base ) {
			out += ':';
			i += best.len - 1;
			if ( 8 === i + 1 ) {
				out += ':';
			}
			continue;
		}
		if ( i > 0 ) {
			out += ':';
		}
		if ( 6 === i && 0 === best.base && 6 === best.len ) {
			return out + dotted( groups[ 6 ], groups[ 7 ] );
		}
		out += groups[ i ].toString( 16 );
	}
	return out;
}

/**
 * An IP address in canonical form, or '' when it is not one.
 *
 * @param {string} value Address as typed.
 * @return {string} Canonical address.
 */
export function canonicalIp( value ) {
	const text = String( value ?? '' ).trim();
	if ( ! text ) {
		return '';
	}
	if ( ! text.includes( ':' ) ) {
		return IPV4.test( text ) ? text : '';
	}
	const groups = ipv6Groups( text );
	if ( ! groups ) {
		return '';
	}
	// IPv4-mapped (::ffff:a.b.c.d): the IPv4 address.
	if (
		groups.slice( 0, 5 ).every( ( g ) => 0 === g ) &&
		0xffff === groups[ 5 ]
	) {
		return quad( groups[ 6 ], groups[ 7 ] );
	}
	return ipv6Text( groups );
}

/**
 * Whether a value is written as a range (CIDR, 203.0.113.0/24).
 *
 * @param {string} value Value.
 * @return {boolean} A range.
 */
export const isCidr = ( value ) =>
	/\/\d{0,3}$/.test( String( value ?? '' ).trim() );

/**
 * An address to block.
 *
 * @param {string} value Address as typed.
 * @return {{value?: string, error?: string}} The canonical address, or what is wrong.
 */
export function checkBlockIp( value ) {
	const text = String( value ?? '' ).trim();
	if ( ! text ) {
		return {
			error: __(
				'Enter the IP address to block, like 203.0.113.9.',
				'zero-spam'
			),
		};
	}
	if ( text.includes( '/' ) ) {
		// The route's own message (Rules_Controller::check_block()).
		return {
			error: __(
				'Blocks are for single addresses; ranges (CIDR) are not supported yet.',
				'zero-spam'
			),
		};
	}
	const ip = canonicalIp( text );
	return ip
		? { value: ip }
		: { error: __( 'Must be an IPv4 or IPv6 address.', 'zero-spam' ) };
}

/**
 * A location to block.
 *
 * @param {string} type Location type (LOCATION_TYPES).
 * @param {string} key  Value as typed.
 * @return {{value?: string, error?: string}} The value to send, or what is wrong.
 */
export function checkLocation( type, key ) {
	const text = String( key ?? '' ).trim();
	if ( /[<>]/.test( text ) ) {
		return { error: __( 'Must not contain < or >.', 'zero-spam' ) };
	}
	if ( ! text ) {
		return { error: __( 'Enter the location to block.', 'zero-spam' ) };
	}
	if ( 'country_code' === type ) {
		const code = text.toUpperCase();
		return /^[A-Z]{2}$/.test( code )
			? { value: code }
			: {
					error: __(
						'Must be a two-letter country code, such as DE or US.',
						'zero-spam'
					),
				};
	}
	if ( 'zip' === type ) {
		return /^[A-Za-z0-9 -]{1,10}$/.test( text )
			? { value: text }
			: {
					error: __(
						'Must be a postal code of at most 10 letters, digits, spaces or dashes.',
						'zero-spam'
					),
				};
	}
	return text.length <= MAX_TEXT
		? { value: text }
		: { error: __( 'Must be at most 100 characters.', 'zero-spam' ) };
}

/**
 * One entry of a list in the form the server stores it, or '' when it is
 * not valid (Rules_Controller::check_entry()).
 *
 * @param {string} list  allowed-ips, email-domains or allowed-words.
 * @param {string} entry Entry as typed.
 * @return {string} Stored form.
 */
export function listEntry( list, entry ) {
	const text = String( entry ?? '' ).trim();
	if ( ! text ) {
		return '';
	}
	if ( 'allowed-ips' === list ) {
		return text.includes( '/' ) ? '' : canonicalIp( text );
	}
	if ( 'email-domains' === list ) {
		const domain = text.replace( /^@+/, '' ).toLowerCase();
		return DOMAIN.test( domain ) ? domain : '';
	}
	return text.length <= MAX_TEXT && ! /[\r\n<>]/.test( text ) ? text : '';
}

/**
 * Splits what was typed into entries: IP addresses and domains by spaces,
 * commas, semicolons or lines; words by commas or lines (a word may hold a
 * space).
 *
 * @param {string} list List.
 * @param {string} text Text.
 * @return {string[]} Entries.
 */
export function splitEntries( list, text ) {
	const sep = 'allowed-words' === list ? /[,\n\r]+/ : /[\s,;]+/;
	return String( text ?? '' )
		.split( sep )
		.map( ( s ) => s.trim() )
		.filter( Boolean );
}

/**
 * What was typed into a list's add field, checked.
 *
 * @param {string} list List.
 * @param {string} text Text.
 * @return {{entries: string[], error: string}} Valid entries in stored form, and what is wrong with the others.
 */
export function checkEntries( list, text ) {
	const parts = splitEntries( list, text );
	if ( ! parts.length ) {
		const empty = {
			'allowed-ips': __(
				'Enter an IP address, like 203.0.113.9.',
				'zero-spam'
			),
			'email-domains': __(
				'Enter a domain, like example.com.',
				'zero-spam'
			),
			'allowed-words': __( 'Enter a word.', 'zero-spam' ),
		};
		return { entries: [], error: empty[ list ] || '' };
	}

	const entries = [];
	const bad = [];
	const ranges = [];
	parts.forEach( ( p ) => {
		const stored = listEntry( list, p );
		if ( stored ) {
			entries.push( stored );
		} else if ( 'allowed-ips' === list && isCidr( p ) ) {
			ranges.push( p );
		} else {
			bad.push( p );
		}
	} );

	const shown = ( items ) =>
		items.slice( 0, 10 ).join( ', ' ) + ( items.length > 10 ? ', …' : '' );
	let error = '';
	if ( ranges.length ) {
		error = sprintf(
			/* translators: %s: comma-separated list of entries */
			__(
				'Ranges (CIDR) are not supported in the IP Allowlist; add single addresses: %s',
				'zero-spam'
			),
			shown( ranges )
		);
	} else if ( bad.length ) {
		if ( 'allowed-ips' === list ) {
			error = sprintf(
				/* translators: %s: comma-separated list of entries */
				__( 'These are not IP addresses: %s', 'zero-spam' ),
				shown( bad )
			);
		} else if ( 'email-domains' === list ) {
			error = sprintf(
				/* translators: %s: comma-separated list of entries */
				__(
					'These are not domain names (example.com): %s',
					'zero-spam'
				),
				shown( bad )
			);
		} else {
			error = sprintf(
				/* translators: %s: comma-separated list of entries */
				__(
					'These are not words of at most 100 characters: %s',
					'zero-spam'
				),
				shown( bad )
			);
		}
	}
	return { entries: error ? [] : entries, error };
}

/**
 * The shortest disallowed word checked.
 *
 * @param {string|number} value Value as typed.
 * @return {{value?: number, error?: string}} The number, or what is wrong.
 */
export function checkMinLength( value ) {
	const text = String( value ?? '' ).trim();
	const n = Number( text );
	if ( '' === text || ! /^\d+$/.test( text ) || n > MAX_MIN_LENGTH ) {
		return {
			error: sprintf(
				/* translators: %d: largest allowed number */
				__( 'Must be a whole number from 0 to %d.', 'zero-spam' ),
				MAX_MIN_LENGTH
			),
		};
	}
	return { value: n };
}
