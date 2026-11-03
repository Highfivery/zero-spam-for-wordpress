/**
 * "What's new in 6.0", as data: when the tile shows, the menu, where each
 * 5.x screen moved (the redirect map, build plan §3) and the new switches.
 * No JSX, so the unit tests run in Node.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Compares two version numbers (1.2.3), numerically, part by part.
 *
 * @param {string} a Version.
 * @param {string} b Version.
 * @return {number} Negative, 0 or positive.
 */
export function compareVersions( a, b ) {
	const pa = String( a || '' )
		.split( '-' )[ 0 ]
		.split( '.' )
		.map( ( n ) => parseInt( n, 10 ) || 0 );
	const pb = String( b || '' )
		.split( '-' )[ 0 ]
		.split( '.' )
		.map( ( n ) => parseInt( n, 10 ) || 0 );
	for ( let i = 0; i < Math.max( pa.length, pb.length ); i++ ) {
		const d = ( pa[ i ] || 0 ) - ( pb[ i ] || 0 );
		if ( d ) {
			return d;
		}
	}
	return 0;
}

/**
 * Whether this administrator still has the tile: the site was upgraded
 * (`release`, boot.whatsNew) and they have not dismissed that release's
 * panel (`seen`, their whats_new_seen preference). Waits for the
 * preferences, so the tile never flashes.
 *
 * @param {Object}  args              Arguments.
 * @param {string}  args.release      The release the site shows (boot.whatsNew).
 * @param {string}  args.seen         The release they dismissed ('' for none).
 * @param {boolean} args.prefsLoaded  Their preferences have arrived.
 * @param {boolean} [args.onboarding] First-run setup is showing (a fresh install).
 * @return {boolean} Show it.
 */
export function shouldShowWhatsNew( {
	release,
	seen,
	prefsLoaded,
	onboarding,
} ) {
	if ( ! release || ! prefsLoaded || onboarding ) {
		return false;
	}
	return ! seen || compareVersions( seen, release ) < 0;
}

/**
 * The preferences to save when the panel is dismissed.
 *
 * @param {string} release The release the site shows.
 * @return {Object} ui/prefs changes.
 */
export const dismissChanges = ( release ) => ( { whats_new_seen: release } );

/** @return {Array<[string, string, string, string]>} Area, icon, name and what is there. */
export const menuAreas = () => [
	[
		'overview',
		'wall',
		__( 'Overview', 'zero-spam' ),
		__( 'Live blocks, the last 30 days and what needs you.', 'zero-spam' ),
	],
	[
		'activity',
		'activity',
		__( 'Activity', 'zero-spam' ),
		__( 'The log with filters, details and reports.', 'zero-spam' ),
	],
	[
		'rules',
		'rules',
		__( 'Rules', 'zero-spam' ),
		__(
			'Blocked IPs and locations, allow lists, email domains and words.',
			'zero-spam'
		),
	],
	[
		'protection',
		'shield',
		__( 'Protection', 'zero-spam' ),
		__(
			'What is checked: detection, form plugins and reputation services.',
			'zero-spam'
		),
	],
	[
		'settings',
		'sliders',
		__( 'Settings', 'zero-spam' ),
		__(
			'Blocking, logging, notifications, API monitoring and tools.',
			'zero-spam'
		),
	],
	[
		'help',
		'help',
		__( 'Help', 'zero-spam' ),
		__( 'Guides, developer reference, news and support.', 'zero-spam' ),
	],
];

/**
 * Where each 5.x screen is now: old place, new place, and the area and
 * view arguments to open it (the same map the old addresses redirect by).
 *
 * @param {boolean} multisite Include the network screens.
 * @return {Array<{from: string, to: string, area: string, args: Object}>} Rows.
 */
export function movedRows( multisite = false ) {
	const rows = [
		{
			from: __( 'Dashboard → Zero Spam (reports)', 'zero-spam' ),
			to: __( 'Activity › Reports', 'zero-spam' ),
			area: 'activity',
			args: { tab: 'reports' },
		},
		{
			from: __( 'Dashboard → Zero Spam › Log', 'zero-spam' ),
			to: __( 'Activity › Log', 'zero-spam' ),
			area: 'activity',
			args: {},
		},
		{
			from: __( 'Dashboard → Zero Spam › Blocked IPs', 'zero-spam' ),
			to: __( 'Rules › Blocked IPs', 'zero-spam' ),
			area: 'rules',
			args: { tab: 'ips' },
		},
		{
			from: __(
				'Dashboard → Zero Spam › Blocked Locations',
				'zero-spam'
			),
			to: __( 'Rules › Blocked locations', 'zero-spam' ),
			area: 'rules',
			args: { tab: 'locations' },
		},
		{
			from: __( 'Settings → Zero Spam › Settings', 'zero-spam' ),
			to: __( 'Settings › General', 'zero-spam' ),
			area: 'settings',
			args: { tab: 'general' },
		},
		{
			from: __(
				'Comments, Registration, User Login, David Walsh, Security',
				'zero-spam'
			),
			to: __( 'Protection › Detection', 'zero-spam' ),
			area: 'protection',
			args: { tab: 'detection' },
		},
		{
			from: __(
				'Enhanced Protection, Stop Forum Spam, Project Honeypot, ipbase, IPinfo, ipstack, Google',
				'zero-spam'
			),
			to: __( 'Protection › Intelligence', 'zero-spam' ),
			area: 'protection',
			args: { tab: 'intelligence' },
		},
		{
			from: __(
				'Form plugins (Contact Form 7, WPForms, WooCommerce and more)',
				'zero-spam'
			),
			to: __( 'Protection › Integrations', 'zero-spam' ),
			area: 'protection',
			args: { tab: 'integrations' },
		},
		{
			from: __( 'API Monitoring', 'zero-spam' ),
			to: __( 'Settings › API monitoring', 'zero-spam' ),
			area: 'settings',
			args: { tab: 'api' },
		},
		{
			from: __( 'Debug', 'zero-spam' ),
			to: __( 'Settings › Debug', 'zero-spam' ),
			area: 'settings',
			args: { tab: 'debug' },
		},
		{
			from: __( 'Import/Export Settings, Error Log', 'zero-spam' ),
			to: __( 'Settings › Import & errors', 'zero-spam' ),
			area: 'settings',
			args: { tab: 'io' },
		},
		{
			from: __( 'Settings → Zero Spam › Documentation', 'zero-spam' ),
			to: __( 'Help', 'zero-spam' ),
			area: 'help',
			args: {},
		},
	];

	if ( multisite ) {
		rows.push( {
			from: __(
				'Network Admin → Settings → Zero Spam Network and Zero Spam Stats',
				'zero-spam'
			),
			to: __( 'Network Admin → Zero Spam', 'zero-spam' ),
			area: 'network',
			args: {},
		} );
	}

	return rows;
}

/** @return {Array<{title: string, text: string, area: string, args: Object}>} The new switches. */
export const newSwitches = () => [
	{
		title: __( 'Disable XML-RPC', 'zero-spam' ),
		text: __(
			'Always on before 6.0; now a switch. On by default.',
			'zero-spam'
		),
		area: 'protection',
		args: { tab: 'detection', card: 'security' },
	},
	{
		title: __( 'Hide the WordPress version', 'zero-spam' ),
		text: __(
			'Always on before 6.0; now a switch. On by default.',
			'zero-spam'
		),
		area: 'protection',
		args: { tab: 'detection', card: 'security' },
	},
	{
		title: __( 'Zero Spam news', 'zero-spam' ),
		text: __(
			'Articles and security notices from zerospam.org. Turn it off to never download them.',
			'zero-spam'
		),
		area: 'settings',
		args: { tab: 'general', section: 'news' },
	},
	{
		title: __( 'Theme and animations', 'zero-spam' ),
		text: __(
			'Light, dark or your device’s, and fewer animations. Saved for you only.',
			'zero-spam'
		),
		area: 'settings',
		args: { tab: 'general', section: 'display' },
	},
];
