/**
 * Static checks of the icon sprite and the exported marks.
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = join( __dirname, '..', '..', '..', '..' );
const read = ( file ) => readFileSync( join( root, file ), 'utf8' );

const sprite = read( 'assets/src/admin/icons/sprite.svg' );
const symbols = [
	...sprite.matchAll( /<symbol id="([^"]+)"[^>]*>([\s\S]*?)<\/symbol>/g ),
].map( ( [ , id, body ] ) => ( { id, body } ) );
const ids = ( svg ) =>
	[ ...svg.matchAll( /\sid="([^"]+)"/g ) ].map( ( match ) => match[ 1 ] );

describe( 'sprite.svg', () => {
	it( 'has the three marks and the UI icons', () => {
		const names = symbols.map( ( symbol ) => symbol.id );
		expect( names ).toEqual(
			expect.arrayContaining( [
				'zs-mark',
				'zs-mark-flat',
				'zs-mark-mono',
			] )
		);
		expect(
			names.filter( ( name ) => name.startsWith( 'zs-i-' ) )
		).toHaveLength( 48 );
	} );

	it( 'has no ids inside symbols, so inline copies never clash', () => {
		expect( symbols.flatMap( ( symbol ) => ids( symbol.body ) ) ).toEqual(
			[]
		);
	} );

	it( 'hides the animation layers with attributes, which reach into <use>', () => {
		const mark = symbols.find( ( symbol ) => symbol.id === 'zs-mark' ).body;
		const layers = [
			...mark.matchAll( /<\w+[^>]*class="zs-(sh|pulse|ring)"[^>]*>/g ),
		].map( ( match ) => match[ 0 ] );
		expect( layers ).toHaveLength( 15 );
		expect(
			layers.filter( ( tag ) => ! tag.includes( 'opacity="0"' ) )
		).toEqual( [] );
	} );

	it( 'draws the one-colour mark at full and 70% opacity only', () => {
		const mono = symbols.find(
			( symbol ) => symbol.id === 'zs-mark-mono'
		).body;
		expect(
			new Set(
				[ ...mono.matchAll( /fill-opacity="([^"]+)"/g ) ].map(
					( m ) => m[ 1 ]
				)
			)
		).toEqual( new Set( [ '.7' ] ) );
		expect( mono ).toMatch( /fill="currentColor"/ );
	} );
} );

describe( 'exported marks', () => {
	it( 'prefixes the standalone mark ids so they never collide with the sprite', () => {
		const mark = read( 'assets/img/zero-spam-mark.svg' );
		const markIds = ids( mark );
		expect( markIds.length ).toBeGreaterThan( 0 );
		expect(
			markIds.filter( ( id ) => ! id.startsWith( 'zs-mark-' ) )
		).toEqual( [] );
		expect(
			markIds.filter( ( id ) => ids( sprite ).includes( id ) )
		).toEqual( [] );
	} );

	it( 'gives the admin menu icon explicit fills for WordPress svg-painter', () => {
		const icon = read( 'assets/img/zero-spam-menu-icon.svg' );
		// svg-painter rewrites fill="…" attributes; currentColor, style
		// attributes and <style> blocks would not follow the colour scheme.
		expect( icon ).not.toMatch( /currentColor|style=|<style/ );
		const shapes = [ ...icon.matchAll( /<rect [^>]*>/g ) ].map(
			( match ) => match[ 0 ]
		);
		expect( shapes ).toHaveLength( 13 );
		expect(
			shapes.filter( ( tag ) => ! /\sfill="#[0-9a-f]{6}"/i.test( tag ) )
		).toEqual( [] );
		expect( icon ).toMatch( /viewBox="0 0 20 20"/ );
	} );
} );
