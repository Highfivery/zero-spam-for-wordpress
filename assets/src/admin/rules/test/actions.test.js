/**
 * Removals with Undo: each sends its own request, says what it did, and
 * offers Undo only with a token; Undo redeems the token, reloads and
 * confirms. Failures are shown, never thrown.
 */

/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';

/**
 * Internal dependencies
 */
import {
	blockName,
	removeBlock,
	removeBlocks,
	removeNoLockout,
} from '../actions';

const TOKEN = 'a'.repeat( 32 );

/** @return {{toast: (message: string, options?: Object) => number, toasts: Array}} Toast spy. */
const toaster = () => {
	const toasts = [];
	return {
		toasts,
		toast: ( message, options = {} ) =>
			toasts.push( { message, ...options } ),
	};
};

describe( 'blockName', () => {
	it( 'names an address or a location', () => {
		expect(
			blockName( { kind: 'ip', ip: '203.0.113.9', key: '203.0.113.9' } )
		).toBe( '203.0.113.9' );
		expect(
			blockName( { kind: 'location', key: 'DE', label: 'Germany' } )
		).toBe( 'Germany' );
		expect( blockName( { kind: 'location', key: '10115' } ) ).toBe(
			'10115'
		);
	} );
} );

describe( 'removeBlock', () => {
	it( 'deletes, reloads, and Undo restores the block', async () => {
		const { toast, toasts } = toaster();
		const refresh = vi.fn();
		const request = vi.fn( ( { method } ) =>
			Promise.resolve(
				'DELETE' === method
					? { deleted: 1, undo_token: TOKEN }
					: { kind: 'blocks', restored: 1, skipped: 0 }
			)
		);

		await removeBlock( {
			block: { id: 7, kind: 'ip', ip: '203.0.113.9' },
			request,
			toast,
			refresh,
		} );
		expect( request ).toHaveBeenCalledWith( {
			path: '/zero-spam/v1/rules/blocks/7',
			method: 'DELETE',
		} );
		expect( refresh ).toHaveBeenCalledTimes( 1 );
		expect( toasts[ 0 ].message ).toBe(
			'Removed the block on 203.0.113.9'
		);

		await toasts[ 0 ].undo();
		expect( request ).toHaveBeenLastCalledWith( {
			path: `/zero-spam/v1/undo/${ TOKEN }`,
			method: 'POST',
		} );
		expect( refresh ).toHaveBeenCalledTimes( 2 );
		expect( toasts[ 1 ].message ).toBe( 'Block restored' );
	} );

	it( 'shows the server’s message when the removal fails', async () => {
		const { toast, toasts } = toaster();
		const answer = await removeBlock( {
			block: { id: 9, kind: 'ip', ip: '203.0.113.9' },
			request: () =>
				Promise.reject( { message: 'No block has that ID.' } ),
			toast,
			refresh: vi.fn(),
		} );
		expect( answer ).toBeNull();
		expect( toasts ).toEqual( [ { message: 'No block has that ID.' } ] );
	} );
} );

describe( 'removeBlocks', () => {
	it( 'posts the IDs and counts what was removed', async () => {
		const { toast, toasts } = toaster();
		const request = vi.fn( () =>
			Promise.resolve( { deleted: 2, undo_token: TOKEN } )
		);
		await removeBlocks( {
			ids: [ 1, 2, 3 ],
			request,
			toast,
			refresh: vi.fn(),
		} );
		expect( request ).toHaveBeenCalledWith( {
			path: '/zero-spam/v1/rules/blocks/bulk-delete',
			method: 'POST',
			data: { ids: [ 1, 2, 3 ] },
		} );
		expect( toasts[ 0 ].message ).toBe( 'Removed 2 blocks' );
		expect( typeof toasts[ 0 ].undo ).toBe( 'function' );
	} );

	it( 'offers no Undo without a token', async () => {
		const { toast, toasts } = toaster();
		await removeBlocks( {
			ids: [ 4 ],
			request: () => Promise.resolve( { deleted: 0, undo_token: null } ),
			toast,
			refresh: vi.fn(),
		} );
		expect( toasts[ 0 ].message ).toBe( 'Removed 0 blocks' );
		expect( toasts[ 0 ].undo ).toBeUndefined();
	} );

	it( 'says so when Undo comes too late', async () => {
		const { toast, toasts } = toaster();
		const request = vi
			.fn()
			.mockResolvedValueOnce( { deleted: 1, undo_token: TOKEN } )
			.mockRejectedValueOnce( {
				message: 'This can no longer be undone.',
			} );
		await removeBlocks( { ids: [ 4 ], request, toast, refresh: vi.fn() } );
		await toasts[ 0 ].undo();
		expect( toasts[ 1 ].message ).toBe( 'This can no longer be undone.' );
	} );
} );

describe( 'removeNoLockout', () => {
	it( 'deletes the address (only address characters in the path) and offers Undo', async () => {
		const { toast, toasts } = toaster();
		const request = vi.fn( () =>
			Promise.resolve( { items: [], undo_token: TOKEN } )
		);
		await removeNoLockout( {
			ip: '2001:db8::1/../x',
			request,
			toast,
			refresh: vi.fn(),
		} );
		expect( request ).toHaveBeenCalledWith( {
			path: '/zero-spam/v1/rules/no-lockout/2001:db8::1..',
			method: 'DELETE',
		} );
		expect( toasts[ 0 ].message ).toBe(
			'Removed 2001:db8::1/../x. It can be blocked again.'
		);
		expect( typeof toasts[ 0 ].undo ).toBe( 'function' );
	} );
} );
