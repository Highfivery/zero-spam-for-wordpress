/**
 * Copies text to the clipboard and says whether it worked.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * @param {string}                          text  Text.
 * @param {(...args: unknown[]) => unknown} toast toast() from useFeedback().
 */
export function copyText( text, toast ) {
	const done = () => toast( __( 'Copied to clipboard', 'zero-spam' ) );
	const fail = () =>
		toast(
			__(
				'Copy did not work. Select the text and press Ctrl+C or Cmd+C.',
				'zero-spam'
			)
		);
	try {
		window.navigator.clipboard.writeText( text ).then( done, fail );
	} catch {
		fail();
	}
}
