/**
 * Setting descriptions and help as React elements, without innerHTML.
 *
 * The settings schema sends descriptions and help that the server already
 * limited (wp_kses) to text formatting, lists and links. They are parsed
 * here again with an allowlist of their own: every other tag is dropped
 * (its text kept), only `href` survives (http, https, mailto, relative),
 * and text is decoded and rendered as text, so nothing in a description can
 * run script even if the server's filter were bypassed.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';

const ALLOWED = [
	'a',
	'b',
	'br',
	'code',
	'em',
	'li',
	'ol',
	'p',
	'strong',
	'ul',
];
const BLOCKS = [ 'p', 'ol', 'ul' ];

const NAMED = {
	amp: '&',
	lt: '<',
	gt: '>',
	quot: '"',
	apos: "'",
	nbsp: ' ',
	hellip: '…',
	ndash: '–',
	mdash: '—',
	lsquo: '‘',
	rsquo: '’',
	ldquo: '“',
	rdquo: '”',
	laquo: '«',
	raquo: '»',
	rsaquo: '›',
	lsaquo: '‹',
	middot: '·',
	bull: '•',
	copy: '©',
	reg: '®',
	trade: '™',
};

/**
 * Decodes HTML entities in text.
 *
 * @param {string} text Text with entities.
 * @return {string} Text.
 */
export function decodeEntities( text ) {
	return String( text ).replace(
		/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi,
		( match, entity ) => {
			if ( '#' === entity[ 0 ] ) {
				const hex = 'x' === entity[ 1 ].toLowerCase();
				const code = parseInt(
					entity.slice( hex ? 2 : 1 ),
					hex ? 16 : 10
				);
				return Number.isFinite( code ) && code > 0 && code < 0x110000
					? String.fromCodePoint( code )
					: match;
			}
			const name = entity.toLowerCase();
			return Object.prototype.hasOwnProperty.call( NAMED, name )
				? NAMED[ name ]
				: match;
		}
	);
}

/**
 * A link target that is safe to render, or null.
 *
 * @param {string} href Raw href attribute.
 * @return {string|null} Address.
 */
export function safeHref( href ) {
	const value = decodeEntities( href || '' ).trim();
	if ( /^(https?:\/\/|mailto:)/i.test( value ) ) {
		return value;
	}
	// Relative to the site (one slash, not a protocol-relative address: browsers read "/\" as "//") or a fragment.
	if ( /^(\/(?![\/\\])|#|\?)/.test( value ) ) {
		return value;
	}
	return null;
}

/**
 * Parses limited markup into a tree of { tag, href, children } and strings.
 *
 * @param {string} html Markup.
 * @return {Array<Object|string>} Nodes.
 */
export function parseRich( html ) {
	const root = { tag: null, children: [] };
	const stack = [ root ];
	const re = /<(\/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>|([^<]+)|(<)/g;
	let match;

	while ( null !== ( match = re.exec( String( html || '' ) ) ) ) {
		const top = stack[ stack.length - 1 ];
		const [ , closing, rawTag, attrs, text, stray ] = match;

		if ( undefined !== text || undefined !== stray ) {
			top.children.push( decodeEntities( text ?? stray ) );
			continue;
		}

		const tag = rawTag.toLowerCase();
		if ( ! ALLOWED.includes( tag ) ) {
			continue;
		}

		if ( closing ) {
			const at = stack.map( ( n ) => n.tag ).lastIndexOf( tag );
			if ( at > 0 ) {
				stack.length = at;
			}
			continue;
		}

		const node = { tag, children: [] };
		if ( 'a' === tag ) {
			const href = /\bhref\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i.exec(
				attrs || ''
			);
			node.href = href
				? safeHref( href[ 1 ] ?? href[ 2 ] ?? href[ 3 ] )
				: null;
		}
		top.children.push( node );
		if ( 'br' !== tag && ! /\/\s*$/.test( attrs || '' ) ) {
			stack.push( node );
		}
	}

	return root.children;
}

/**
 * The text of limited markup, without tags.
 *
 * @param {string} html Markup.
 * @return {string} Text.
 */
export function plainText( html ) {
	const walk = ( nodes ) =>
		nodes
			.map( ( n ) => ( 'string' === typeof n ? n : walk( n.children ) ) )
			.join( '' );
	return walk( parseRich( html ) ).replace( /\s+/g, ' ' ).trim();
}

/**
 * Whether markup has block elements (paragraphs, lists).
 *
 * @param {string} html Markup.
 * @return {boolean} Has blocks.
 */
export function hasBlocks( html ) {
	const find = ( nodes ) =>
		nodes.some(
			( n ) =>
				'string' !== typeof n &&
				( BLOCKS.includes( n.tag ) || find( n.children ) )
		);
	return find( parseRich( html ) );
}

/**
 * React elements of parsed nodes.
 *
 * @param {Array<Object|string>} nodes Nodes.
 * @param {string}               key   Key prefix.
 * @return {Array} Elements.
 */
function toElements( nodes, key = 'r' ) {
	return nodes.map( ( node, i ) => {
		const k = `${ key }-${ i }`;
		if ( 'string' === typeof node ) {
			return node;
		}
		if ( 'br' === node.tag ) {
			return createElement( 'br', { key: k } );
		}
		const children = toElements( node.children, k );
		if ( 'a' === node.tag ) {
			if ( ! node.href ) {
				return createElement( 'span', { key: k }, ...children );
			}
			const external = /^https?:\/\//i.test( node.href );
			return createElement(
				'a',
				{
					key: k,
					href: node.href,
					...( external
						? { target: '_blank', rel: 'noopener noreferrer' }
						: {} ),
				},
				...children,
				external
					? createElement(
							'span',
							{ key: `${ k }-x`, className: 'zs-sr-only' },
							__( '(opens in a new tab)', 'zero-spam' )
						)
					: null
			);
		}
		return createElement( node.tag, { key: k }, ...children );
	} );
}

/**
 * Renders limited markup.
 *
 * @param {Object} props             Props.
 * @param {string} props.html        Markup (from the settings schema).
 * @param {string} [props.as]        Wrapper for inline content (default p); block content uses a div.
 * @param {string} [props.id]        ID.
 * @param {string} [props.className] Classes.
 */
export function RichText( { html, as = 'p', id, className } ) {
	const nodes = parseRich( html );
	const tag = hasBlocks( html ) ? 'div' : as;
	return createElement( tag, { id, className }, ...toElements( nodes ) );
}
