/**
 * The Rules removals with Undo (pure apart from the injected request and
 * toast; unit tested): one block, the selected blocks, and a no-lockout
 * address. Each answer carries a token that `zero-spam/v1/undo/{token}`
 * redeems within two minutes (activity/undo.js runs the toast).
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NS } from '../data/api';
import { num } from '../data/format';
import { errorMessage, runUndoable } from '../activity/undo';

/**
 * The name of what a block blocks: the address, or the location's name.
 *
 * @param {Object} block Block (rules/blocks shape).
 * @return {string} Name.
 */
export const blockName = ( block ) =>
	'ip' === block.kind ? block.ip || block.key : block.label || block.key;

/**
 * Removes one block, with Undo.
 *
 * @param {Object}                          options         Options.
 * @param {Object}                          options.block   Block.
 * @param {(...args: unknown[]) => unknown} options.request apiFetch-like.
 * @param {(...args: unknown[]) => unknown} options.toast   ( message, { undo } ) => void.
 * @param {(...args: unknown[]) => unknown} options.refresh Reloads the list.
 * @return {Promise<Object|null>} The answer, or null.
 */
export const removeBlock = ( { block, request, toast, refresh } ) =>
	runUndoable( {
		run: () =>
			request( {
				path: `${ NS }/rules/blocks/${ Number( block.id ) }`,
				method: 'DELETE',
			} ),
		canUndo: ( answer ) => Boolean( answer?.undo_token ),
		request,
		toast,
		refresh,
		messages: {
			done: () =>
				sprintf(
					/* translators: %s: IP address or location */
					__( 'Removed the block on %s', 'zero-spam' ),
					blockName( block )
				),
			undone: () => __( 'Block restored', 'zero-spam' ),
			failed: ( error ) =>
				errorMessage(
					error,
					__(
						'The block could not be removed. Please try again.',
						'zero-spam'
					)
				),
			undoFailed: ( error ) =>
				errorMessage(
					error,
					__( 'The block could not be restored.', 'zero-spam' )
				),
		},
	} );

/**
 * Removes several blocks, with Undo.
 *
 * @param {Object}                          options         Options.
 * @param {number[]}                        options.ids     Block IDs.
 * @param {(...args: unknown[]) => unknown} options.request apiFetch-like.
 * @param {(...args: unknown[]) => unknown} options.toast   ( message, { undo } ) => void.
 * @param {(...args: unknown[]) => unknown} options.refresh Reloads the list.
 * @return {Promise<Object|null>} The answer, or null.
 */
export const removeBlocks = ( { ids, request, toast, refresh } ) =>
	runUndoable( {
		run: () =>
			request( {
				path: `${ NS }/rules/blocks/bulk-delete`,
				method: 'POST',
				data: { ids },
			} ),
		canUndo: ( answer ) => Boolean( answer?.undo_token ),
		request,
		toast,
		refresh,
		messages: {
			done: ( answer ) =>
				sprintf(
					/* translators: %s: number of blocks */
					_n(
						'Removed %s block',
						'Removed %s blocks',
						answer?.deleted ?? ids.length,
						'zero-spam'
					),
					num( answer?.deleted ?? ids.length )
				),
			undone: () => __( 'Blocks restored', 'zero-spam' ),
			failed: ( error ) =>
				errorMessage(
					error,
					__(
						'The blocks could not be removed. Please try again.',
						'zero-spam'
					)
				),
			undoFailed: ( error ) =>
				errorMessage(
					error,
					__( 'The blocks could not be restored.', 'zero-spam' )
				),
		},
	} );

/**
 * What removing a no-lockout address would do to you: your own address
 * while it is blocked locks you out at once (the server asks for the same
 * confirmation, 409 rest_would_lock_out).
 *
 * @param {{ip: string, current: boolean, blocked: boolean}} item Address.
 * @return {string} The warning, or '' when removing it is safe.
 */
export const lockoutWarning = ( item ) =>
	item?.current && item?.blocked
		? sprintf(
				/* translators: %s: IP address */
				__(
					'Your address %s is blocked; removing it here locks you out of this site.',
					'zero-spam'
				),
				item.ip
			)
		: '';

/**
 * Removes a no-lockout address, with Undo.
 *
 * @param {Object}                          options             Options.
 * @param {string}                          options.ip          Address.
 * @param {boolean}                         [options.confirm]   Remove your own blocked address anyway.
 * @param {(...args: unknown[]) => unknown} options.request     apiFetch-like.
 * @param {(...args: unknown[]) => unknown} options.toast       ( message, { undo } ) => void.
 * @param {(...args: unknown[]) => unknown} options.refresh     Reloads the list.
 * @param {(...args: unknown[]) => unknown} [options.onLockout] Receives the server's warning when it asks for confirmation.
 * @return {Promise<Object|null>} The answer, or null.
 */
export const removeNoLockout = ( {
	ip,
	confirm = false,
	request,
	toast,
	refresh,
	onLockout,
} ) =>
	runUndoable( {
		run: () =>
			request( {
				// Addresses hold only URL-safe characters (the route allows [0-9A-Fa-f:.]).
				path: `${ NS }/rules/no-lockout/${ String( ip ).replace(
					/[^0-9A-Fa-f:.]/g,
					''
				) }${ confirm ? '?confirm=1' : '' }`,
				method: 'DELETE',
			} ).catch( ( error ) => {
				if ( 'rest_would_lock_out' === error?.code && onLockout ) {
					onLockout( error.message );
					throw { lockout: true };
				}
				throw error;
			} ),
		canUndo: ( answer ) => Boolean( answer?.undo_token ),
		request,
		// The confirmation replaces the error message.
		toast: ( message, options ) => message && toast( message, options ),
		refresh,
		messages: {
			done: () =>
				sprintf(
					/* translators: %s: IP address */
					__( 'Removed %s. It can be blocked again.', 'zero-spam' ),
					ip
				),
			undone: () => __( 'Address restored', 'zero-spam' ),
			failed: ( error ) =>
				error?.lockout
					? ''
					: errorMessage(
							error,
							__(
								'The address could not be removed. Please try again.',
								'zero-spam'
							)
						),
			undoFailed: ( error ) =>
				errorMessage(
					error,
					__( 'The address could not be restored.', 'zero-spam' )
				),
		},
	} );
