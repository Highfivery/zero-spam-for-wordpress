/**
 * The system report: what Zero Spam checks on this site, as rows (the
 * Overview's System tile) and as one line of text (Copy system report, on
 * the Overview and in Settings › Debug). Versions and settings only, never
 * visitor data or keys.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { boot } from '../data/api';

/**
 * The rows: [ label, value, ok ].
 *
 * @param {Object}  options          Options.
 * @param {Object}  options.modules  protection/modules answer.
 * @param {boolean} options.enhanced Enhanced Protection is working.
 * @param {Object}  options.schema   settings/schema answer.
 * @return {Array<[string, string, boolean]>} Rows.
 */
export function systemRows( { modules, enhanced, schema } ) {
	const list = modules?.modules || [];
	const on = ( key ) => true === list.find( ( m ) => m.key === key )?.enabled;
	const forms = list.filter(
		( m ) =>
			'integrations' === m.group && m.plugin?.active && true === m.enabled
	).length;
	const method = ( schema?.settings || [] ).find(
		( s ) => 'block_method' === s.key
	)?.value;
	const methodLabel = String( method || 'php' ).startsWith( 'htaccess' )
		? '.htaccess'
		: 'PHP';
	const ipLabels = {
		cloudflare: __( 'Cloudflare', 'zero-spam' ),
		trusted_proxy: __( 'Trusted proxy', 'zero-spam' ),
		untrusted_proxy: __( 'Untrusted proxy', 'zero-spam' ),
		direct: __( 'Direct connection', 'zero-spam' ),
		none: __( 'Not available', 'zero-spam' ),
	};
	const ip = ipLabels[ boot.environment.ipSource ] || ipLabels.direct;
	const checking = __( 'Checking', 'zero-spam' );
	const off = __( 'Off', 'zero-spam' );

	return [
		[
			__( 'Comments', 'zero-spam' ),
			on( 'comments' ) ? checking : off,
			on( 'comments' ),
		],
		[
			__( 'Login', 'zero-spam' ),
			on( 'login' ) ? checking : off,
			on( 'login' ),
		],
		[
			__( 'Registration', 'zero-spam' ),
			on( 'registration' ) ? checking : off,
			on( 'registration' ),
		],
		[
			__( 'Forms', 'zero-spam' ),
			forms
				? sprintf(
						/* translators: %d: number of plugins */
						_n( '%d plugin', '%d plugins', forms, 'zero-spam' ),
						forms
					)
				: __( 'None active', 'zero-spam' ),
			forms > 0,
		],
		[ __( 'Block method', 'zero-spam' ), methodLabel, true ],
		[
			__( 'IP source', 'zero-spam' ),
			ip,
			'untrusted_proxy' !== boot.environment.ipSource,
		],
		[
			__( 'Stop Forum Spam', 'zero-spam' ),
			on( 'stop_forum_spam' ) ? checking : off,
			on( 'stop_forum_spam' ),
		],
		[
			__( 'Zero Spam network', 'zero-spam' ),
			enhanced ? checking : __( 'Not used (Basic)', 'zero-spam' ),
			enhanced,
		],
	];
}

/**
 * The versions line: Zero Spam, WordPress and PHP.
 *
 * @return {string} Text.
 */
export const environmentLine = () =>
	sprintf(
		/* translators: 1: Zero Spam version, 2: WordPress version, 3: PHP version */
		__( 'Zero Spam %1$s · WordPress %2$s · PHP %3$s', 'zero-spam' ),
		boot.version,
		boot.environment.wordpress,
		boot.environment.php
	);

/**
 * The report as one line of text.
 *
 * @param {Array<[string, string, boolean]>} rows     systemRows().
 * @param {boolean}                          enhanced Enhanced Protection is working.
 * @return {string} Report.
 */
export const systemReport = ( rows, enhanced ) =>
	[
		environmentLine(),
		enhanced ? __( 'Enhanced', 'zero-spam' ) : __( 'Basic', 'zero-spam' ),
		...rows.map( ( [ k, v ] ) => `${ k }: ${ v }` ),
	].join( ' · ' );

/**
 * Copies text to the clipboard and says how it went.
 *
 * @param {string}                    text  Text.
 * @param {(message: string) => void} toast Shows a message.
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
