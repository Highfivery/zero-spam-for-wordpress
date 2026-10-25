/**
 * Actions with Undo: the toast's Undo sends the token back, refreshes and
 * confirms; failures are shown, never thrown.
 */

/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';

/**
 * Internal dependencies
 */
import { errorMessage, runUndoable } from '../undo';

const messages = {
	done: ( a ) => `Deleted ${ a.deleted }`,
	undone: ( r ) => `Restored ${ r.restored }`,
	failed: ( e ) => errorMessage( e, 'Could not delete' ),
	undoFailed: ( e ) => errorMessage( e, 'Could not restore' ),
};

/**
 * Collects toasts.
 *
 * @return {{toast: (message: string, options?: Object) => number, toasts: Array}} Spy.
 */
const toaster = () => {
	const toasts = [];
	return {
		toasts,
		toast: ( message, options = {} ) =>
			toasts.push( { message, ...options } ),
	};
};

describe( 'runUndoable', () => {
	it( 'deletes, refreshes and offers Undo with the token', async () => {
		const { toast, toasts } = toaster();
		const refresh = vi.fn();
		const request = vi.fn( () =>
			Promise.resolve( { kind: 'activity', restored: 3, skipped: 0 } )
		);

		const answer = await runUndoable( {
			run: () =>
				Promise.resolve( { deleted: 3, undo_token: 'a'.repeat( 32 ) } ),
			request,
			toast,
			refresh,
			messages,
		} );

		expect( answer.deleted ).toBe( 3 );
		expect( refresh ).toHaveBeenCalledTimes( 1 );
		expect( toasts[ 0 ].message ).toBe( 'Deleted 3' );
		expect( typeof toasts[ 0 ].undo ).toBe( 'function' );

		await toasts[ 0 ].undo();

		expect( request ).toHaveBeenCalledWith( {
			path: `/zero-spam/v1/undo/${ 'a'.repeat( 32 ) }`,
			method: 'POST',
		} );
		expect( refresh ).toHaveBeenCalledTimes( 2 );
		expect( toasts[ 1 ].message ).toBe( 'Restored 3' );
	} );

	it( 'offers no Undo without a token', async () => {
		const { toast, toasts } = toaster();
		await runUndoable( {
			run: () => Promise.resolve( { deleted: 0, undo_token: null } ),
			request: vi.fn(),
			toast,
			refresh: vi.fn(),
			messages,
		} );
		expect( toasts[ 0 ].undo ).toBeUndefined();
	} );

	it( 'uses the action’s own undo (an allowlist entry, a new block)', async () => {
		const { toast, toasts } = toaster();
		const undo = vi.fn( () => Promise.resolve( { restored: 1 } ) );
		const request = vi.fn();
		await runUndoable( {
			run: () => Promise.resolve( { deleted: 1 } ),
			undo,
			request,
			toast,
			refresh: vi.fn(),
			messages,
		} );
		await toasts[ 0 ].undo();
		expect( undo ).toHaveBeenCalledWith( { deleted: 1 } );
		expect( request ).not.toHaveBeenCalled();
	} );

	it( 'shows a failed action and an expired undo', async () => {
		const { toast, toasts } = toaster();
		const refresh = vi.fn();
		const failed = await runUndoable( {
			run: () =>
				Promise.reject( {
					message: 'Sorry, you are not allowed to do that.',
				} ),
			request: vi.fn(),
			toast,
			refresh,
			messages,
		} );
		expect( failed ).toBeNull();
		expect( refresh ).not.toHaveBeenCalled();
		expect( toasts[ 0 ].message ).toBe(
			'Sorry, you are not allowed to do that.'
		);

		await runUndoable( {
			run: () =>
				Promise.resolve( { deleted: 1, undo_token: 'b'.repeat( 32 ) } ),
			request: () => Promise.reject( {} ),
			toast,
			refresh,
			messages,
		} );
		await toasts[ 1 ].undo();
		expect( toasts[ 2 ].message ).toBe( 'Could not restore' );
	} );
} );
