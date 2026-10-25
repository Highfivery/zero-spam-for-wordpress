/**
 * Theme and motion resolution (pure; unit tested).
 *
 * The user's preferences live in `ui/prefs`: `theme` is light, dark or auto
 * (follow the operating system) and `reduce_motion` turns animations off for
 * that user. The operating system's reduced-motion setting always wins.
 */

export const THEMES = [ 'light', 'dark', 'auto' ];

/**
 * A valid theme preference.
 *
 * @param {*} pref Stored value.
 * @return {'light'|'dark'|'auto'} Theme preference.
 */
export function themePref( pref ) {
	return THEMES.includes( pref ) ? pref : 'auto';
}

/**
 * The theme shown.
 *
 * @param {*}       pref   Theme preference.
 * @param {boolean} osDark The operating system prefers dark.
 * @return {'light'|'dark'} Theme shown.
 */
export function resolveTheme( pref, osDark ) {
	const theme = themePref( pref );
	if ( 'auto' === theme ) {
		return osDark ? 'dark' : 'light';
	}
	return theme;
}

/**
 * The preference the top bar's theme button switches to: the opposite of
 * what is shown.
 *
 * @param {*}       pref   Theme preference.
 * @param {boolean} osDark The operating system prefers dark.
 * @return {'light'|'dark'} New preference.
 */
export function toggledTheme( pref, osDark ) {
	return 'dark' === resolveTheme( pref, osDark ) ? 'light' : 'dark';
}

/**
 * Whether animations are off.
 *
 * @param {*}       reducePref The user's reduce_motion preference.
 * @param {boolean} osReduce   The operating system asks for reduced motion.
 * @return {boolean} Animations are off.
 */
export function motionReduced( reducePref, osReduce ) {
	return Boolean( osReduce ) || true === reducePref;
}

/**
 * Attributes for the app root.
 *
 * @param {Object} prefs ui/prefs.
 * @return {{ 'data-zs-theme': string, 'data-zs-motion': string|undefined }} Attributes.
 */
export function rootAttributes( prefs = {} ) {
	return {
		'data-zs-theme': themePref( prefs.theme ),
		'data-zs-motion': true === prefs.reduce_motion ? 'reduce' : undefined,
	};
}
