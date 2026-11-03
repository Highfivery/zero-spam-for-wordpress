/**
 * The developer lists on Help: hooks, REST routes and WP-CLI commands, each
 * with a one-line description. The full reference stays in the guides
 * (Developer hooks, REST API for settings) and on the GitHub wiki.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/** @return {Array<[string, string]>} Hook and what it does. */
export const hooks = () => [
	[
		'zerospam_settings',
		__( 'Filter. Add or change settings.', 'zero-spam' ),
	],
	[
		'zerospam_types',
		__(
			'Filter. Register a detection type shown in Activity.',
			'zero-spam'
		),
	],
	[
		'zerospam_failed_types',
		__( 'Filter. Name the reasons a check can fail.', 'zero-spam' ),
	],
	[
		'zerospam_access_checks',
		__(
			'Filter. Add your own checks before a visitor is let in.',
			'zero-spam'
		),
	],
	[
		'zerospam_preprocess_registration_submission',
		__(
			'Filter. Check a sign-up before the user is created.',
			'zero-spam'
		),
	],
	[
		'zerospam_share_detection',
		__(
			'Action. Runs when a detection is shared with the network.',
			'zero-spam'
		),
	],
	[
		'zerospam_rescue_form_enabled',
		__( 'Filter. Hide the rescue form on the blocked page.', 'zero-spam' ),
	],
	[
		'zerospam_documentation_sections',
		__( 'Filter. Add your own guide to this Help screen.', 'zero-spam' ),
	],
];

/** @return {Array<[string, string]>} Route and what it does. */
export const restRoutes = () => [
	[
		'GET PATCH /zero-spam/v1/settings',
		__( 'Read and update settings.', 'zero-spam' ),
	],
	[
		'GET /zero-spam/v1/settings/schema',
		__( 'Every setting with its type, options and value.', 'zero-spam' ),
	],
	[
		'GET PUT DELETE /zero-spam/v1/license',
		__( 'License status; save or remove the key.', 'zero-spam' ),
	],
	[
		'GET /zero-spam/v1/stats/summary',
		__( 'Detections in the last 1, 7 or 30 days.', 'zero-spam' ),
	],
	[
		'GET /zero-spam/v1/activity',
		__( 'The activity log, with filters and paging.', 'zero-spam' ),
	],
	[
		'GET POST /zero-spam/v1/rules/blocks',
		__( 'Blocked IP addresses and locations.', 'zero-spam' ),
	],
	[
		'POST /zero-spam/v1/self-test',
		__( 'Test the site’s protection.', 'zero-spam' ),
	],
	[
		'GET /zero-spam/v1/api-usage',
		__( 'Enhanced Protection API usage.', 'zero-spam' ),
	],
];

/** @return {Array<[string, string]>} Command and what it does. */
export const cliCommands = () => [
	[
		'wp zerospam autoconfigure',
		__( 'Apply the recommended settings.', 'zero-spam' ),
	],
	[
		'wp zerospam settings',
		__( 'List every setting and its value.', 'zero-spam' ),
	],
	[
		'wp zerospam set --<key>=<value>',
		__( 'Change one setting.', 'zero-spam' ),
	],
	[
		'wp zerospam regenerate-honeypot',
		__( 'Reset the honeypot field ID.', 'zero-spam' ),
	],
	[
		'wp zerospam update-blocked-domains',
		__( 'Use --recommended to replace, --append to add.', 'zero-spam' ),
	],
	[
		'wp zerospam api-usage [--period] [--format]',
		__( 'Show Enhanced Protection API usage.', 'zero-spam' ),
	],
	[
		'wp zerospam network-stats',
		__( 'Show network statistics (multisite).', 'zero-spam' ),
	],
	[
		'wp zerospam network-settings <command>',
		__( 'list, show, set, lock, unlock, audit and more.', 'zero-spam' ),
	],
];

/**
 * Splits a command into text and flag parts (flags and placeholders are
 * shown in their own colour and never wrap inside).
 *
 * @param {string} command Command.
 * @return {Array<{text: string, flag: boolean}>} Parts.
 */
export function commandParts( command ) {
	const parts = [];
	const pattern = /(\[?--[^\s\]]+\]?|<[^>]+>)/g;
	let last = 0;
	let match;
	while ( ( match = pattern.exec( command ) ) ) {
		if ( match.index > last ) {
			parts.push( {
				text: command.slice( last, match.index ),
				flag: false,
			} );
		}
		parts.push( { text: match[ 0 ], flag: true } );
		last = match.index + match[ 0 ].length;
	}
	if ( last < command.length ) {
		parts.push( { text: command.slice( last ), flag: false } );
	}
	return parts;
}

/** Links the Help screen and the setup use. */
export const LINKS = {
	forum: 'https://wordpress.org/support/plugin/zero-spam/',
	review: 'https://wordpress.org/support/plugin/zero-spam/reviews/#new-post',
	contact: 'https://www.zerospam.org/contact/',
	articles: 'https://www.zerospam.org/articles/',
	plans: 'https://www.zerospam.org/pricing/',
	wiki: 'https://github.com/Highfivery/zero-spam-for-wordpress/wiki',
};
