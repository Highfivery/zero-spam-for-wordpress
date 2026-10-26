/**
 * What the details drawer shows from stored details: text only.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { confidenceOf, detailPairs } from '../details-data';

describe( 'detailPairs', () => {
	it( 'turns every value into text and leaves out what is shown above', () => {
		expect(
			detailPairs( {
				type: 'comment',
				failed: 'honeypot',
				comment_content: '<img src=x onerror=alert(1)>',
				nested: { a: [ 1, '<b>' ] },
				empty: null,
				n: 4,
			} )
		).toEqual( [
			[ 'comment_content', '<img src=x onerror=alert(1)>' ],
			[ 'nested', '{"a":[1,"<b>"]}' ],
			[ 'empty', '' ],
			[ 'n', '4' ],
		] );
	} );

	it( 'is empty without details', () => {
		expect( detailPairs( null ) ).toEqual( [] );
		expect( detailPairs( 'text' ) ).toEqual( [] );
	} );
} );

describe( 'confidenceOf', () => {
	it( 'reads the network score in percent', () => {
		expect( confidenceOf( { confidence_score: 87.4 } ) ).toBe( 87 );
		expect( confidenceOf( { confidence: 0.91 } ) ).toBe( 91 );
		expect( confidenceOf( { failed: 'honeypot' } ) ).toBeNull();
		expect( confidenceOf( null ) ).toBeNull();
	} );
} );
