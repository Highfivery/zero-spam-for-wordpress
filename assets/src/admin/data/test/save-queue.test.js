/**
 * Preference saves go out one at a time, only the last answer of a run is
 * applied, and a failure reloads the server's state instead of rolling back
 * over a later change.
 */

/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';

/**
 * Internal dependencies
 */
import { createSaveQueue } from '../save-queue';

const deferred = () => {
	let resolve, reject;
	const promise = new Promise( ( res, rej ) => {
		resolve = res;
		reject = rej;
	} );
	return { promise, resolve, reject };
};

const flush = () => new Promise( ( r ) => setTimeout( r, 0 ) );

describe( 'createSaveQueue', () => {
	it( 'sends one save at a time, in order', async () => {
		const calls = [];
		const pending = [];
		const queue = createSaveQueue( {
			send: ( changes ) => {
				calls.push( changes );
				const d = deferred();
				pending.push( d );
				return d.promise;
			},
			onSaved: vi.fn(),
			onFailed: vi.fn(),
		} );
		queue.save( { theme: 'dark' } );
		queue.save( { theme: 'light' } );
		await flush();
		expect( calls ).toEqual( [ { theme: 'dark' } ] );
		pending[ 0 ].resolve( { theme: 'dark' } );
		await flush();
		expect( calls ).toEqual( [ { theme: 'dark' }, { theme: 'light' } ] );
		expect( queue.pending() ).toBe( 1 );
	} );

	it( 'applies only the last answer of a run', async () => {
		const onSaved = vi.fn();
		const queue = createSaveQueue( {
			send: ( changes ) => Promise.resolve( { ...changes, saved: 1 } ),
			onSaved,
			onFailed: vi.fn(),
		} );
		queue.save( { theme: 'dark' } );
		queue.save( { theme: 'light' } );
		await flush();
		expect( onSaved.mock.calls ).toEqual( [
			[ { theme: 'light', saved: 1 } ],
		] );
	} );

	it( 'reloads once after a run with a failure, never rolls back', async () => {
		const onSaved = vi.fn();
		const onFailed = vi.fn();
		const queue = createSaveQueue( {
			send: ( changes ) =>
				changes.fail
					? Promise.reject( new Error( 'offline' ) )
					: Promise.resolve( changes ),
			onSaved,
			onFailed,
		} );
		const first = queue.save( { fail: true } );
		queue.save( { theme: 'light' } );
		await expect( first ).rejects.toThrow( 'offline' );
		await flush();
		expect( onFailed ).toHaveBeenCalledTimes( 1 );
		expect( onSaved ).not.toHaveBeenCalled();

		// The next run starts clean.
		queue.save( { theme: 'dark' } );
		await flush();
		expect( onSaved ).toHaveBeenCalledWith( { theme: 'dark' } );
		expect( onFailed ).toHaveBeenCalledTimes( 1 );
	} );
} );
