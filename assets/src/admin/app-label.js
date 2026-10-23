/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Accessible name for the app root.
 *
 * A placeholder until the 6.0 admin lands: it exists so the build pipeline
 * proves that translated strings, their text domain and their translator
 * comments survive minification.
 *
 * @param {string} version Plugin version, or an empty string when unknown.
 * @return {string} The label, for example "Zero Spam 6.0.0".
 */
export function getAppLabel(version) {
	if (!version) {
		return __('Zero Spam', 'zero-spam');
	}

	return sprintf(
		/* translators: %s: plugin version number, for example 6.0.0. */
		__('Zero Spam %s', 'zero-spam'),
		version
	);
}
