// @vitest-environment happy-dom

/**
 * useResource keeps the newest answer: a request that answers after a newer
 * one (a slow Undo refresh, then a change of sort) must not replace its list.
 */

/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { useResource } from '../api';

vi.mock( '@wordpress/api-fetch', () => ( { default: vi.fn() } ) );

/** Requests in the order they were made, each answered by the test. */
let pending;

let container;
let root;
let seen;

function Probe( { path } ) {
	seen = useResource( path );
	return null;
}

const answer = async ( index, data ) => {
	await act( async () => {
		pending[ index ].resolve( data );
		await Promise.resolve();
	} );
};

beforeEach( () => {
	globalThis.IS_REACT_ACT_ENVIRONMENT = true;
	pending = [];
	apiFetch.mockImplementation(
		( { path } ) =>
			new Promise( ( resolve, reject ) =>
				pending.push( { path, resolve, reject } )
			)
	);
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	vi.clearAllMocks();
} );

describe( 'useResource', () => {
	it( 'keeps a reload’s answer when the first request answers last', async () => {
		await act( async () => root.render( <Probe path="/list?sort=a" /> ) );
		await act( async () => {
			seen.reload();
		} );
		expect( pending ).toHaveLength( 2 );

		await answer( 1, [ 'new' ] );
		expect( seen.data ).toEqual( [ 'new' ] );
		await answer( 0, [ 'old' ] );
		expect( seen.data ).toEqual( [ 'new' ] );
		expect( seen.loading ).toBe( false );
	} );

	it( 'keeps the new path’s answer when the old path answers last (a sort during a slow refresh)', async () => {
		await act( async () => root.render( <Probe path="/list?sort=a" /> ) );
		await act( async () => root.render( <Probe path="/list?sort=b" /> ) );
		expect( pending.map( ( p ) => p.path ) ).toEqual( [
			'/list?sort=a',
			'/list?sort=b',
		] );

		await answer( 1, [ 'sorted by b' ] );
		await answer( 0, [ 'sorted by a' ] );
		expect( seen.data ).toEqual( [ 'sorted by b' ] );
	} );

	it( 'stays loading until the newest request answers, and ignores an older failure', async () => {
		await act( async () => root.render( <Probe path="/list" /> ) );
		await act( async () => {
			seen.reload();
		} );
		await act( async () => {
			pending[ 0 ].reject( { code: 'old' } );
			await Promise.resolve();
		} );
		expect( seen.loading ).toBe( true );
		expect( seen.error ).toBeNull();
		await answer( 1, [ 'fresh' ] );
		expect( seen.data ).toEqual( [ 'fresh' ] );
		expect( seen.loading ).toBe( false );
	} );

	it( 'a reload kept from an older path (Undo’s refresh after a change of sort) loads the path shown now', async () => {
		await act( async () => root.render( <Probe path="/list?sort=a" /> ) );
		const keptReload = seen.reload;
		await act( async () => root.render( <Probe path="/list?sort=b" /> ) );
		await act( async () => {
			keptReload();
		} );
		expect( pending.map( ( p ) => p.path ) ).toEqual( [
			'/list?sort=a',
			'/list?sort=b',
			'/list?sort=b',
		] );

		await answer( 1, [ 'sorted by b' ] );
		await answer( 2, [ 'sorted by b, refreshed' ] );
		await answer( 0, [ 'sorted by a' ] );
		expect( seen.data ).toEqual( [ 'sorted by b, refreshed' ] );
	} );
} );
