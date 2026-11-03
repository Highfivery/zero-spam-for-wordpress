/**
 * Serialised preference saves.
 *
 * Each save is applied optimistically by the caller and sent in order, one
 * request at a time, so an answer can never land on top of a later change.
 * Only the answer of the last save in the queue is applied (it already holds
 * every earlier change the server accepted). When any save in a run fails,
 * the queue asks for the server's state once it has drained instead of
 * rolling back to a snapshot that may undo a later change.
 */

/**
 * Creates a save queue.
 *
 * @param {Object}                               options          Options.
 * @param {(changes: Object) => Promise<Object>} options.send     Sends one save; resolves with the saved state.
 * @param {(saved: Object) => void}              options.onSaved  Gets the last answer of a run.
 * @param {() => void}                           options.onFailed Called once per run with a failure, after it drained.
 * @return {{save: (changes: Object) => Promise<Object>, pending: () => number}} Queue.
 */
export function createSaveQueue( { send, onSaved, onFailed } ) {
	let tail = Promise.resolve();
	let pending = 0;
	let failed = false;

	const settle = () => {
		pending -= 1;
		if ( pending > 0 || ! failed ) {
			return;
		}
		failed = false;
		onFailed();
	};

	return {
		/**
		 * Queues a save.
		 *
		 * @param {Object} changes Changes.
		 * @return {Promise} Resolves with this save's answer, rejects with its error.
		 */
		save( changes ) {
			pending += 1;
			const run = tail.then( () => send( changes ) );
			tail = run.then(
				( saved ) => {
					if ( 1 === pending && ! failed ) {
						onSaved( saved );
					}
					settle();
				},
				() => {
					failed = true;
					settle();
				}
			);
			return run;
		},

		/**
		 * @return {number} Saves queued or in flight.
		 */
		pending: () => pending,
	};
}
