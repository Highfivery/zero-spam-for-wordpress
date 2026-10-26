/**
 * The Rules forms' checks mirror the server's (Rules_Controller): canonical
 * IP addresses as User::normalize_ip() writes them, no ranges for blocks
 * (the route's message), locations, list entries and the minimum length.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	canonicalIp,
	checkBlockIp,
	checkEntries,
	checkLocation,
	checkMinLength,
	isCidr,
	listEntry,
	splitEntries,
} from '../validate';

describe( 'canonicalIp', () => {
	it.each( [
		[ '203.0.113.9', '203.0.113.9' ],
		[ ' 198.51.100.24 ', '198.51.100.24' ],
		[ '0.0.0.0', '0.0.0.0' ],
		[ '255.255.255.255', '255.255.255.255' ],
		[ '2001:DB8:0:0::1', '2001:db8::1' ],
		[ '2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1' ],
		[ '2001:db8:0:0:1:0:0:1', '2001:db8::1:0:0:1' ],
		[ '2001:db8:0:1:0:0:0:1', '2001:db8:0:1::1' ],
		[ '::1', '::1' ],
		[ '::', '::' ],
		[ '1::', '1::' ],
		[ '::ffff:192.0.2.1', '192.0.2.1' ],
		[ '::FFFF:C000:0201', '192.0.2.1' ],
		[ 'fe80::1:2', 'fe80::1:2' ],
	] )( '%s → %s', ( input, out ) => {
		expect( canonicalIp( input ) ).toBe( out );
	} );

	it.each( [
		'',
		'not-an-ip',
		'256.1.1.1',
		'01.2.3.4',
		'1.2.3',
		'1.2.3.4.5',
		'2001:db8::1::2',
		'2001:db8:0:0:0:0:0:0:1',
		'12345::1',
		'gggg::1',
		'203.0.113.0/24',
	] )( 'refuses %s', ( input ) => {
		expect( canonicalIp( input ) ).toBe( '' );
	} );
} );

describe( 'blocks', () => {
	it( 'recognises ranges', () => {
		expect( isCidr( '203.0.113.0/24' ) ).toBe( true );
		expect( isCidr( '2001:db8::/32' ) ).toBe( true );
		expect( isCidr( '203.0.113.9' ) ).toBe( false );
	} );

	it( 'refuses a range with the route’s message, and stores addresses canonically', () => {
		expect( checkBlockIp( '203.0.113.0/24' ).error ).toBe(
			'Blocks are for single addresses; ranges (CIDR) are not supported yet.'
		);
		expect( checkBlockIp( '' ).error ).toMatch( /Enter the IP address/ );
		expect( checkBlockIp( 'nope' ).error ).toBe(
			'Must be an IPv4 or IPv6 address.'
		);
		expect( checkBlockIp( ' 2001:DB8::0:1 ' ) ).toEqual( {
			value: '2001:db8::1',
		} );
	} );

	it( 'checks locations by type', () => {
		expect( checkLocation( 'country_code', 'de' ) ).toEqual( {
			value: 'DE',
		} );
		expect( checkLocation( 'country_code', 'Germany' ).error ).toMatch(
			/two-letter country code/
		);
		expect( checkLocation( 'zip', '90210' ) ).toEqual( { value: '90210' } );
		expect( checkLocation( 'zip', 'SW1A 1AA-XYZ' ).error ).toMatch(
			/postal code/
		);
		expect( checkLocation( 'city', 'Berlin' ) ).toEqual( {
			value: 'Berlin',
		} );
		expect(
			checkLocation( 'region_code', 'x'.repeat( 101 ) ).error
		).toMatch( /at most 100/ );
		expect( checkLocation( 'city', '  ' ).error ).toBe(
			'Enter the location to block.'
		);
	} );
} );

describe( 'list entries', () => {
	it( 'stores entries as the server does', () => {
		expect( listEntry( 'allowed-ips', '2001:DB8::1' ) ).toBe(
			'2001:db8::1'
		);
		expect( listEntry( 'allowed-ips', '203.0.113.0/24' ) ).toBe( '' );
		expect( listEntry( 'email-domains', '@Mailinator.COM' ) ).toBe(
			'mailinator.com'
		);
		expect( listEntry( 'email-domains', 'localhost' ) ).toBe( '' );
		expect( listEntry( 'email-domains', 'spam.example' ) ).toBe(
			'spam.example'
		);
		expect( listEntry( 'allowed-words', 'ugg' ) ).toBe( 'ugg' );
		expect( listEntry( 'allowed-words', 'x'.repeat( 101 ) ) ).toBe( '' );
		expect( listEntry( 'allowed-words', '<b>' ) ).toBe( '' );
	} );

	it( 'splits addresses and domains on spaces, words only on commas and lines', () => {
		expect(
			splitEntries(
				'allowed-ips',
				'203.0.113.5, 198.51.100.7\n192.0.2.1'
			)
		).toEqual( [ '203.0.113.5', '198.51.100.7', '192.0.2.1' ] );
		expect( splitEntries( 'allowed-words', 'van gogh, ugg' ) ).toEqual( [
			'van gogh',
			'ugg',
		] );
	} );

	it( 'explains what is wrong and adds nothing then', () => {
		expect( checkEntries( 'allowed-ips', '' ).error ).toMatch(
			/Enter an IP address/
		);
		expect( checkEntries( 'allowed-ips', '203.0.113.5 nope' ) ).toEqual( {
			entries: [],
			error: 'These are not IP addresses: nope',
		} );
		expect( checkEntries( 'allowed-ips', '203.0.113.0/24' ).error ).toMatch(
			/Ranges \(CIDR\) are not supported in the IP Allowlist/
		);
		expect( checkEntries( 'email-domains', 'a.com bad' ).error ).toBe(
			'These are not domain names (example.com): bad'
		);
		expect( checkEntries( 'email-domains', 'A.com @b.org' ) ).toEqual( {
			entries: [ 'a.com', 'b.org' ],
			error: '',
		} );
		const many = Array.from( { length: 12 }, ( _, i ) => `x${ i }` ).join(
			' '
		);
		expect( checkEntries( 'allowed-ips', many ).error ).toMatch( /, …$/ );
	} );

	it( 'checks the minimum word length', () => {
		expect( checkMinLength( '4' ) ).toEqual( { value: 4 } );
		expect( checkMinLength( 0 ) ).toEqual( { value: 0 } );
		expect( checkMinLength( '101' ).error ).toBe(
			'Must be a whole number from 0 to 100.'
		);
		expect( checkMinLength( '-1' ).error ).toBeTruthy();
		expect( checkMinLength( '2.5' ).error ).toBeTruthy();
		expect( checkMinLength( '' ).error ).toBeTruthy();
	} );
} );
