/**
 * Small parts of the network page: the lock switch, number tiles, the
 * tab panels' loading and error states, and a confirmation row.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, Icon, cx } from '../app/primitives';
import { num } from '../data/format';
import { lockText } from './model';

/**
 * The lock switch of a network setting (prototype `.lockbtn`): a toggle
 * button, pressed when the setting is locked on every site.
 *
 * @param {Object}                          props               Props.
 * @param {Object}                          props.entry         Network entry.
 * @param {boolean}                         props.locked        Locked now.
 * @param {boolean}                         [props.busy]        Saving.
 * @param {(...args: unknown[]) => unknown} props.onToggle      Toggles the lock.
 * @param {string}                          [props.describedBy] IDs describing it.
 */
export function LockButton( {
	entry,
	locked,
	busy = false,
	onToggle,
	describedBy,
} ) {
	const text = lockText( entry, locked );
	return (
		<button
			type="button"
			className="zs-lockbtn"
			data-zs-lock={ entry.key }
			aria-pressed={ locked ? 'true' : 'false' }
			aria-label={ text.aria }
			aria-describedby={ describedBy }
			aria-disabled={ busy ? 'true' : undefined }
			onClick={ busy ? undefined : onToggle }
		>
			<Icon name={ locked ? 'lock' : 'unlock' } size="sm" />
			<span aria-hidden="true">{ text.label }</span>
		</button>
	);
}

/**
 * A number tile (prototype `.bignum`).
 *
 * @param {Object}  props        Props.
 * @param {string}  props.label  Eyebrow.
 * @param {unknown} props.value  Number (null while loading).
 * @param {string}  [props.hint] Line under the number.
 */
export function NumberTile( { label, value, hint } ) {
	return (
		<div className="zs-tile zs-s3 zs-numtile">
			<div className="zs-eyebrow">{ label }</div>
			<b className="zs-bignum">
				{ null === value || undefined === value ? '—' : num( value ) }
			</b>
			{ hint && <span className="zs-hint">{ hint }</span> }
		</div>
	);
}

/**
 * What a tab shows while its data loads, or when it could not be read.
 *
 * @param {Object}                          props         Props.
 * @param {Object}                          props.res     useResource() state.
 * @param {string}                          props.what    What could not be read, as a sentence.
 * @param {(...args: unknown[]) => unknown} [props.retry] Reloads.
 */
export function LoadState( { res, what, retry } ) {
	if ( res.error && undefined === res.data ) {
		return (
			<div className="zs-tile zs-empty" role="alert">
				<b>{ what }</b>
				<span className="zs-hint">
					{ __(
						'Your sites are still protected. Check your connection and try again.',
						'zero-spam'
					) }
				</span>
				<div>
					<Button
						variant="secondary"
						icon="refresh"
						onClick={ retry || res.reload }
					>
						{ __( 'Try again', 'zero-spam' ) }
					</Button>
				</div>
			</div>
		);
	}
	return (
		<div className="zs-cardcol" aria-hidden="true">
			<div className="zs-tile zs-pcard zs-pcard-skel" />
		</div>
	);
}

/**
 * A question with Yes and Cancel, shown in place of the button that asked
 * it (prototype `.confirm-q`); the Yes button takes focus.
 *
 * @param {Object}                          props          Props.
 * @param {string}                          props.question Question.
 * @param {string}                          props.yes      Label of the Yes button.
 * @param {(...args: unknown[]) => unknown} props.onYes    Yes.
 * @param {(...args: unknown[]) => unknown} props.onCancel Cancel.
 * @param {boolean}                         [props.busy]   Working.
 * @param {string}                          [props.id]     ID of the question.
 */
export function Confirm( {
	question,
	yes,
	onYes,
	onCancel,
	busy = false,
	id = 'zs-nconfirm',
} ) {
	const button = useRef( null );
	useEffect( () => {
		button.current?.focus();
	}, [] );
	return (
		<div
			className={ cx( 'zs-confirm-q', 'zs-inline-row' ) }
			role="group"
			aria-labelledby={ id }
		>
			<span id={ id }>{ question }</span>
			<Button
				ref={ button }
				size="sm"
				disabled={ busy }
				onClick={ onYes }
			>
				{ yes }
			</Button>
			<Button variant="ghost" size="sm" onClick={ onCancel }>
				{ __( 'Cancel', 'zero-spam' ) }
			</Button>
		</div>
	);
}

/**
 * The result line under an action (polite live region).
 *
 * @param {Object} props        Props.
 * @param {Object} props.result `{ ok, text }` or null.
 * @param {string} [props.id]   ID.
 */
export function ActionResult( { result, id } ) {
	return (
		<p
			id={ id }
			className={ cx(
				'zs-actres',
				result && ( result.ok ? 'zs-actres-ok' : 'zs-actres-err' )
			) }
			role="status"
			aria-live="polite"
		>
			{ result?.text || '' }
		</p>
	);
}

/**
 * Reads an apiFetch error's message.
 *
 * @param {unknown} error    Error.
 * @param {string}  fallback Message when it has none.
 * @return {string} Message.
 */
export const errorText = ( error, fallback ) =>
	( error && 'object' === typeof error && error.message ) || fallback;
