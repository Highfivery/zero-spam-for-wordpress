/**
 * An action with Undo (pure; unit tested with stubs): run the request,
 * refresh, show a toast whose Undo button sends the token back to
 * `zero-spam/v1/undo/{token}` (two minutes, once, this user and site), then
 * refresh again and confirm. Failures are shown, never thrown.
 */

/**
 * @param {Object}                          options           Options.
 * @param {(...args: unknown[]) => unknown} options.run       Sends the request; resolves to an answer with `undo_token` (or the action's own undo, see `undo`).
 * @param {(...args: unknown[]) => unknown} [options.undo]    ( answer ) => Promise: undoes without a token (an added allowlist entry, a new block).
 * @param {(...args: unknown[]) => unknown} [options.canUndo] ( answer ) => boolean: whether this answer can be undone (default: yes).
 * @param {(...args: unknown[]) => unknown} options.request   apiFetch-like ( { path, method } ) => Promise.
 * @param {(...args: unknown[]) => unknown} options.toast     ( message, { undo } ) => void.
 * @param {(...args: unknown[]) => unknown} options.refresh   Reloads what changed.
 * @param {Object}                          options.messages  { done( answer ), undone( answer ), failed( error ), undoFailed( error ) }.
 * @return {Promise<Object|null>} The answer, or null when the action failed.
 */
export function runUndoable( {
	run,
	undo,
	canUndo = () => true,
	request,
	toast,
	refresh,
	messages,
} ) {
	return Promise.resolve()
		.then( run )
		.then(
			( answer ) => {
				refresh();
				const token = answer && answer.undo_token;
				let back = null;
				if ( ! canUndo( answer ) ) {
					back = null;
				} else if ( undo ) {
					back = () => undo( answer );
				} else if ( token ) {
					back = () =>
						request( {
							path: `/zero-spam/v1/undo/${ token }`,
							method: 'POST',
						} );
				}
				toast(
					messages.done( answer ),
					back
						? {
								undo: () =>
									Promise.resolve()
										.then( back )
										.then(
											( result ) => {
												refresh();
												toast(
													messages.undone( result )
												);
											},
											( error ) =>
												toast(
													messages.undoFailed( error )
												)
										),
							}
						: {}
				);
				return answer;
			},
			( error ) => {
				toast( messages.failed( error ) );
				return null;
			}
		);
}

/**
 * The message of a REST error, or a fallback.
 *
 * @param {unknown} error    apiFetch error.
 * @param {string}  fallback Message when the error has none.
 * @return {string} Message.
 */
export const errorMessage = ( error, fallback ) =>
	error && 'string' === typeof error.message && error.message
		? error.message
		: fallback;
