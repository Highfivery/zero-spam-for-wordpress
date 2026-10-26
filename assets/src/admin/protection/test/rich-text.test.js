/**
 * Setting descriptions and help render as React elements from an allowlist:
 * no script, no attributes but safe links, entities decoded as text.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

/**
 * Internal dependencies
 */
import {
	RichText,
	decodeEntities,
	hasBlocks,
	parseRich,
	plainText,
	safeHref,
} from '../rich-text';

describe( 'decodeEntities', () => {
	it( 'decodes named and numeric entities, and leaves unknown ones', () => {
		expect(
			decodeEntities(
				'&lt;form&gt; &amp; &#039;x&#039; &#x2192; &rsquo;'
			)
		).toBe( "<form> & 'x' → ’" );
		expect( decodeEntities( '&bogus; &#0;' ) ).toBe( '&bogus; &#0;' );
	} );
} );

describe( 'safeHref', () => {
	it.each( [
		[ 'https://www.zerospam.org/', 'https://www.zerospam.org/' ],
		[ 'mailto:a@example.com', 'mailto:a@example.com' ],
		[ '/wp-admin/plugins.php', '/wp-admin/plugins.php' ],
		[ '#anchor', '#anchor' ],
		[ 'javascript:alert(1)', null ],
		[ 'JaVaScRiPt&#58;alert(1)', null ],
		[ 'data:text/html,x', null ],
		[ '//evil.example', null ],
	] )( '%s → %s', ( href, expected ) => {
		expect( safeHref( href ) ).toBe( expected );
	} );
} );

describe( 'RichText', () => {
	const render = ( html, as ) =>
		renderToStaticMarkup( <RichText html={ html } as={ as } /> );

	it( 'keeps formatting and safe links', () => {
		expect(
			render(
				'Use <code>.my-form</code> or <strong>see</strong> <a href="https://example.com/" onclick="x()">docs</a>.'
			)
		).toBe(
			'<p>Use <code>.my-form</code> or <strong>see</strong> <a href="https://example.com/" target="_blank" rel="noopener noreferrer">docs<span class="zs-sr-only">(opens in a new tab)</span></a>.</p>'
		);
	} );

	it( 'drops scripts, unknown tags and unsafe links, keeping text as text', () => {
		const html = render(
			'<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a> &lt;b&gt;'
		);
		expect( html ).not.toMatch( /<script|<img|javascript:|onerror/ );
		expect( html ).toBe( '<p>alert(1)<span>x</span> &lt;b&gt;</p>' );
	} );

	it( 'uses a div for paragraphs and lists', () => {
		expect( render( '<p>One</p><ol><li>Two</li></ol>' ) ).toBe(
			'<div><p>One</p><ol><li>Two</li></ol></div>'
		);
		expect( hasBlocks( '<ul><li>x</li></ul>' ) ).toBe( true );
		expect( hasBlocks( 'plain <em>x</em>' ) ).toBe( false );
	} );

	it( 'closes unclosed tags and ignores stray closing ones', () => {
		expect( render( '<strong>bold <em>both</strong> after</em>' ) ).toBe(
			'<p><strong>bold <em>both</em></strong> after</p>'
		);
		expect( parseRich( 'a < b' ) ).toEqual( [ 'a ', '<', ' b' ] );
	} );

	it( 'gives plain text for length checks', () => {
		expect( plainText( '<p>Hello <b>you</b></p>\n<p>there</p>' ) ).toBe(
			'Hello you there'
		);
	} );
} );
