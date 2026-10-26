/**
 * Guards the design tokens (styles/tokens.css, blocked/tokens.css) and the
 * scoping of the shared stylesheets:
 *
 * - every text colour meets 4.5:1 and every UI colour 3:1 against the surfaces
 *   it sits on, in the light and the dark theme and inside dark blocks
 *   (WCAG 2.2 1.4.3 and 1.4.11). Tokens are resolved the way the browser
 *   does: a var() in a custom property is resolved on the element that
 *   declares it, so an alias set on the root keeps the root's value inside a
 *   dark block unless the block declares it again;
 * - the explicit dark theme and the "auto" dark theme declare the same values;
 * - the blocked pages' token subset matches the admin tokens and covers
 *   base.css;
 * - no selector reaches outside the Zero Spam roots.
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const stylesDir = join( __dirname, '..', 'styles' );
const srcDir = join( __dirname, '..', '..' );

const read = ( file ) => readFileSync( file, 'utf8' );

/**
 * Splits a stylesheet into its rules, one level of at-rule nesting deep.
 *
 * @param {string} css Stylesheet source.
 * @return {Array<{atRule: string|null, selector: string, body: string}>} Rules.
 */
function parseRules( css ) {
	const source = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const rules = [];

	const walk = ( text, atRule ) => {
		let depth = 0;
		let start = 0;
		let head = '';
		for ( let i = 0; i < text.length; i++ ) {
			const char = text[ i ];
			if ( char === '{' ) {
				if ( depth === 0 ) {
					head = text.slice( start, i ).trim();
					start = i + 1;
				}
				depth++;
			} else if ( char === '}' ) {
				depth--;
				if ( depth === 0 ) {
					const body = text.slice( start, i );
					if ( head.startsWith( '@' ) ) {
						walk( body, head );
					} else {
						rules.push( {
							atRule,
							selector: head.replace( /\s+/g, ' ' ),
							body,
						} );
					}
					start = i + 1;
				}
			} else if ( char === ';' && depth === 0 ) {
				// Statements such as @import.
				start = i + 1;
			}
		}
	};

	walk( source, null );
	return rules;
}

/**
 * @param {string} body Declaration block.
 * @return {Object<string, string>} Custom properties declared in it.
 */
function customProperties( body ) {
	const props = {};
	for ( const match of body.matchAll( /(--[\w-]+)\s*:\s*([^;]+);?/g ) ) {
		props[ match[ 1 ] ] = match[ 2 ].trim();
	}
	return props;
}

/**
 * Resolves the custom properties one element declares, given the resolved
 * values it inherits. var() references are resolved on that element, as in
 * the browser.
 *
 * @param {Object<string, string>} inherited    Resolved values of the parent.
 * @param {Object<string, string>} declarations This element's declarations.
 * @return {Object<string, string>} Resolved values on this element.
 */
function resolveScope( inherited, declarations ) {
	const raw = { ...inherited, ...declarations };
	const lookup = ( name, depth = 0 ) => {
		if ( depth > 10 ) {
			throw new Error( `${ name } has a var() cycle` );
		}
		const value = raw[ name ];
		if ( value === undefined ) {
			throw new Error( `${ name } is not defined` );
		}
		if ( ! ( name in declarations ) ) {
			return value; // Inherited: already resolved on the parent.
		}
		return value.replace( /var\(\s*(--[\w-]+)\s*\)/g, ( match, ref ) =>
			lookup( ref, depth + 1 )
		);
	};
	const resolved = { ...inherited };
	for ( const name of Object.keys( declarations ) ) {
		resolved[ name ] = lookup( name );
	}
	return resolved;
}

/**
 * Relative luminance of a #rgb or #rrggbb colour (WCAG 2.2 definition).
 *
 * @param {string} hex Colour.
 * @return {number} Luminance, 0 to 1.
 */
function luminance( hex ) {
	let digits = hex.replace( '#', '' );
	if ( digits.length === 3 ) {
		digits = [ ...digits ].map( ( d ) => d + d ).join( '' );
	}
	if ( ! /^[0-9a-f]{6}$/i.test( digits ) ) {
		throw new Error( `${ hex } is not a solid hex colour` );
	}
	const [ r, g, b ] = [ 0, 2, 4 ].map( ( i ) => {
		const channel = parseInt( digits.slice( i, i + 2 ), 16 ) / 255;
		return channel <= 0.04045
			? channel / 12.92
			: ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * @param {string} a Colour.
 * @param {string} b Colour.
 * @return {number} WCAG contrast ratio, 1 to 21.
 */
function contrast( a, b ) {
	const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort(
		( x, y ) => y - x
	);
	return ( hi + 0.05 ) / ( lo + 0.05 );
}

/**
 * The solid colours a gradient shows: its hex stops, and each rgba() stop
 * painted over each hex stop.
 *
 * @param {string} gradient CSS background value.
 * @return {string[]} Hex colours.
 */
function gradientStops( gradient ) {
	const hexes = [ ...gradient.matchAll( /#[0-9a-f]{6}\b/gi ) ].map(
		( match ) => match[ 0 ]
	);
	const overlays = [
		...gradient.matchAll(
			/rgba\(\s*(\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\s*\)/g
		),
	]
		.map( ( match ) => match.slice( 1 ).map( Number ) )
		.filter( ( [ , , , alpha ] ) => alpha > 0 );
	const composite = overlays.flatMap( ( [ r, g, b, alpha ] ) =>
		hexes.map(
			( hex ) =>
				'#' +
				[ r, g, b ]
					.map( ( channel, i ) => {
						const under = parseInt(
							hex.slice( 1 + i * 2, 3 + i * 2 ),
							16
						);
						return Math.round(
							channel * alpha + under * ( 1 - alpha )
						)
							.toString( 16 )
							.padStart( 2, '0' );
					} )
					.join( '' )
		)
	);
	return [ ...hexes, ...composite ];
}

const rules = parseRules( read( join( stylesDir, 'tokens.css' ) ) );
const findRule = ( test ) => rules.find( test );

const lightRule = findRule(
	( rule ) => rule.atRule === null && rule.selector === '.zs-app, .zs-widget'
);
const darkRule = findRule(
	( rule ) =>
		rule.atRule === null &&
		rule.selector.includes( '[data-zs-theme="dark"]' )
);
const autoRule = findRule(
	( rule ) =>
		/prefers-color-scheme:\s*dark/.test( rule.atRule || '' ) &&
		rule.selector.includes( '[data-zs-theme="auto"]' )
);
const darkBlockRule = findRule(
	( rule ) => rule.atRule === null && rule.selector === '.zs-dark-block'
);

const lightDecls = customProperties( lightRule?.body || '' );
const darkDecls = {
	...lightDecls,
	...customProperties( darkRule?.body || '' ),
};
const themes = {
	light: resolveScope( {}, lightDecls ),
	dark: resolveScope( {}, darkDecls ),
};
const darkBlocks = Object.fromEntries(
	Object.entries( themes ).map( ( [ theme, tokens ] ) => [
		theme,
		resolveScope( tokens, customProperties( darkBlockRule?.body || '' ) ),
	] )
);

const token = ( tokens, name ) => {
	if ( ! ( `--zs-${ name }` in tokens ) ) {
		throw new Error( `--zs-${ name } is not defined` );
	}
	return tokens[ `--zs-${ name }` ];
};

const cross = ( fgs, bgs ) =>
	fgs.flatMap( ( fg ) => bgs.map( ( bg ) => [ fg, bg ] ) );

const surfaces = [
	'bg',
	'surface',
	'surface-2',
	'surface-3',
	'sunken',
	'nav-hover',
	'ni-tint',
	'band',
];
const softs = [ 'ok-soft', 'warn-soft', 'red-soft', 'info-soft' ];

// Text: 4.5:1 (WCAG 1.4.3).
const textPairs = [
	...cross(
		[ 'ink', 'ink-2', 'ink-3', 'eyebrow', 'sx-label', 'red' ],
		surfaces
	),
	...cross( [ 'ink', 'ink-2', 'ink-3' ], softs ),
	...cross(
		[ 'ok-ink', 'warn-ink', 'red-soft-ink', 'info' ],
		[ 'surface', 'surface-3', 'band' ]
	),
	[ 'on-action', 'action' ],
	[ 'on-action', 'action-hover' ],
	[ 'red-soft-ink', 'red-soft' ],
	[ 'ok-ink', 'ok-soft' ],
	[ 'warn-ink', 'warn-soft' ],
	[ 'info', 'info-soft' ],
	[ 'enh-ink', 'enh-bg' ],
];

// Controls, focus ring, status icons, sparklines and chart series: 3:1
// (WCAG 1.4.11).
const uiPairs = [
	...cross(
		[ 'control', 'focus', 'action', 'red', 'ok', 'warn', 'spark' ],
		[
			'bg',
			'surface',
			'surface-2',
			'sunken',
			'surface-3',
			'band',
			'nav-hover',
		]
	),
	...cross( [ 'ch0', 'ch1', 'ch2', 'ch3', 'ch4' ], [ 'surface', 'bg' ] ),
];

// Inside a dark block (hero, Enhanced Protection), with the block's own
// tokens, against the block's surfaces and every colour of the hero gradient.
const darkBlockBackgrounds = ( tokens ) => [
	[ '--zs-enh-bg', token( tokens, 'enh-bg' ) ],
	[ '--zs-surface', token( tokens, 'surface' ) ],
	...gradientStops( token( tokens, 'hero-bg' ) ).map( ( hex ) => [
		`hero ${ hex }`,
		hex,
	] ),
];
const darkBlockText = [
	'ink',
	'ink-2',
	'ink-3',
	'eyebrow',
	'sx-label',
	'red',
	'ok-ink',
	'enh-ink',
];
const darkBlockUi = [ 'focus', 'control', 'action', 'ok' ];

describe( 'design tokens', () => {
	it( 'has a light, a dark, an auto dark and a dark-block rule', () => {
		expect( lightRule ).toBeDefined();
		expect( darkRule ).toBeDefined();
		expect( autoRule ).toBeDefined();
		expect( darkBlockRule ).toBeDefined();
	} );

	it( 'declares the same values for dark and for auto in a dark OS', () => {
		expect( customProperties( autoRule.body ) ).toEqual(
			customProperties( darkRule.body )
		);
	} );

	it( 'only overrides tokens in dark that the light theme defines', () => {
		const unknown = Object.keys( customProperties( darkRule.body ) ).filter(
			( name ) => ! ( name in lightDecls )
		);
		expect( unknown ).toEqual( [] );
	} );

	it( 'never stores a value composed from --zs-focus (it would not follow a surface)', () => {
		const composed = Object.entries( lightDecls ).filter( ( [ , value ] ) =>
			/var\(\s*--zs-focus\s*\)/.test( value )
		);
		expect( composed ).toEqual( [] );
		expect( read( join( stylesDir, 'base.css' ) ) ).toMatch(
			/outline:\s*var\(--zs-focus-width\)\s+solid\s+var\(--zs-focus\)/
		);
	} );

	describe.each( Object.keys( themes ) )( '%s theme', ( theme ) => {
		const tokens = themes[ theme ];

		it.each( textPairs )(
			'text --zs-%s on --zs-%s is at least 4.5:1',
			( fg, bg ) => {
				expect(
					contrast( token( tokens, fg ), token( tokens, bg ) )
				).toBeGreaterThanOrEqual( 4.5 );
			}
		);

		it.each( uiPairs )(
			'UI --zs-%s on --zs-%s is at least 3:1',
			( fg, bg ) => {
				expect(
					contrast( token( tokens, fg ), token( tokens, bg ) )
				).toBeGreaterThanOrEqual( 3 );
			}
		);

		const block = darkBlocks[ theme ];
		const backgrounds = darkBlockBackgrounds( block );

		it.each( cross( darkBlockText, backgrounds ) )(
			'dark block: text --zs-%s on %j is at least 4.5:1',
			( fg, [ , bg ] ) => {
				expect(
					contrast( token( block, fg ), bg )
				).toBeGreaterThanOrEqual( 4.5 );
			}
		);

		it.each( cross( darkBlockUi, backgrounds ) )(
			'dark block: UI --zs-%s on %j is at least 3:1',
			( fg, [ , bg ] ) => {
				expect(
					contrast( token( block, fg ), bg )
				).toBeGreaterThanOrEqual( 3 );
			}
		);
	} );
} );

describe( 'blocked page tokens', () => {
	const blockedRules = parseRules(
		read( join( srcDir, 'blocked', 'tokens.css' ) )
	);
	const blockedLight = customProperties(
		blockedRules.find(
			( rule ) => rule.atRule === null && rule.selector === '.zs-blocked'
		)?.body || ''
	);
	const blockedDark = {
		...blockedLight,
		...customProperties(
			blockedRules.find( ( rule ) =>
				/prefers-color-scheme:\s*dark/.test( rule.atRule || '' )
			)?.body || ''
		),
	};

	it.each( [
		[ 'light', blockedLight, lightDecls ],
		[ 'dark', blockedDark, darkDecls ],
	] )(
		'%s values equal the admin tokens of the same name',
		( theme, blocked, admin ) => {
			const differing = Object.entries( blocked ).filter(
				( [ name, value ] ) => admin[ name ] !== value
			);
			expect( differing ).toEqual( [] );
		}
	);

	it( 'defines every token base.css uses', () => {
		const used = new Set(
			[
				...read( join( stylesDir, 'base.css' ) ).matchAll(
					/var\(\s*(--zs-[\w-]+)\s*\)/g
				),
			].map( ( match ) => match[ 1 ] )
		);
		expect(
			[ ...used ].filter( ( name ) => ! ( name in blockedLight ) )
		).toEqual( [] );
	} );
} );

describe( 'stylesheet scoping', () => {
	const files = [
		join( stylesDir, 'tokens.css' ),
		join( stylesDir, 'base.css' ),
		join( stylesDir, 'logo.css' ),
		join( stylesDir, 'primitives.css' ),
		join( stylesDir, 'shell.css' ),
		join( stylesDir, 'overview.css' ),
		join( stylesDir, 'rules.css' ),
		join( stylesDir, 'areas.css' ),
		join( stylesDir, 'activity.css' ),
		join( stylesDir, 'settings.css' ),
		join( srcDir, 'admin', 'app', 'fields', 'fields.css' ),
		join( srcDir, 'widget-refresh', 'index.css' ),
		join( srcDir, 'widget-refresh', 'widget.css' ),
		join( srcDir, 'blocked', 'tokens.css' ),
		join( srcDir, 'blocked', 'index.css' ),
		join( srcDir, 'blocked', 'page.css' ),
	];

	it.each( files )(
		'every selector in %s stays inside Zero Spam markup',
		( file ) => {
			const leaking = parseRules( read( file ) )
				.filter( ( rule ) => ! /@keyframes/.test( rule.atRule || '' ) )
				.flatMap( ( rule ) =>
					rule.selector
						.split( /,(?![^(]*\))/ )
						.map( ( selector ) => selector.trim() )
				)
				.filter(
					( selector ) =>
						! /\.zs-[\w-]+|\[data-zs-[\w-]+/.test( selector ) ||
						/(^|[\s>+~(,])(:root|html|body)\b/.test( selector )
				);
			expect( leaking ).toEqual( [] );
		}
	);

	it( 'prefixes every keyframes name with zs-', () => {
		const names = files.flatMap( ( file ) =>
			[ ...read( file ).matchAll( /@keyframes\s+([\w-]+)/g ) ].map(
				( match ) => match[ 1 ]
			)
		);
		expect(
			names.filter( ( name ) => ! name.startsWith( 'zs-' ) )
		).toEqual( [] );
	} );
} );
