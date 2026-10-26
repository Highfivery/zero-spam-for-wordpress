/**
 * The tabs and cards of the Settings area (pure; unit tested).
 *
 * Six tabs (prototype v18): General, Logging & data, API monitoring,
 * Advanced, Debug, and Import & errors. Each card lists the settings it
 * shows, by key, from the settings schema; some cards are made of the
 * area's own controls (display preferences, rescue mode, no-lockout
 * addresses, system report, import and export, the error log). A setting
 * of the Settings area that no card lists (an extension's) is shown on its
 * group's tab, in a card of its own, so nothing goes missing.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { holdsValue, isOn } from '../app/fields/settings-model';

/** The Settings tabs, in order (?tab=). */
export const TABS = [ 'general', 'data', 'api', 'advanced', 'debug', 'io' ];

/** @return {Object<string, string>} Tab names. */
export const tabNames = () => ( {
	general: __( 'General', 'zero-spam' ),
	data: __( 'Logging & data', 'zero-spam' ),
	api: __( 'API monitoring', 'zero-spam' ),
	advanced: __( 'Advanced', 'zero-spam' ),
	debug: __( 'Debug', 'zero-spam' ),
	io: __( 'Import & errors', 'zero-spam' ),
} );

/**
 * Settings of the schema's Settings area that other areas show (the
 * prototype puts them in Protection › Detection and Rules), so the
 * catch-all card leaves them out.
 */
export const SHOWN_ELSEWHERE = [
	'regenerate_honeypot',
	'update_blocked_email_domains',
	'update_disallowed_words',
];

/** The five API alerts, in the order the rules card shows them. */
export const ALERTS = [
	'quota_warning',
	'quota_critical',
	'usage_spike',
	'high_error_rate',
	'slow_response',
];

/**
 * The cards, in order. `keys` are the settings a card shows; `custom`
 * names the area's own content; `main` is the switch its status follows.
 *
 * @return {Object[]} Cards: { key, tab, icon, title, blurb?, keys, custom?, main?, mine? }.
 */
export const cardList = () => [
	{
		key: 'quick',
		tab: 'general',
		icon: 'flag',
		title: __( 'Quick setup', 'zero-spam' ),
		keys: [ 'use_recommended_settings' ],
	},
	{
		key: 'blocking',
		tab: 'general',
		icon: 'ban',
		title: __( 'Blocking', 'zero-spam' ),
		blurb: __(
			'What happens when Zero Spam blocks a visitor.',
			'zero-spam'
		),
		keys: [
			'block_handler',
			'block_method',
			'blocked_message',
			'blocked_redirect_url',
		],
	},
	{
		key: 'widget',
		tab: 'general',
		icon: 'wall',
		title: __( 'Dashboard widget', 'zero-spam' ),
		keys: [ 'widget_enabled', 'widget_visibility' ],
		main: 'widget_enabled',
	},
	{
		key: 'news',
		tab: 'general',
		icon: 'book',
		title: __( 'Zero Spam news', 'zero-spam' ),
		keys: [ 'show_news' ],
		main: 'show_news',
	},
	{
		key: 'display',
		tab: 'general',
		icon: 'palette',
		title: __( 'Display', 'zero-spam' ),
		blurb: __( 'How Zero Spam looks for you.', 'zero-spam' ),
		keys: [],
		custom: 'display',
		mine: true,
	},
	{
		key: 'logging',
		tab: 'data',
		icon: 'db',
		title: __( 'Activity log', 'zero-spam' ),
		keys: [ 'log_blocked_ips', 'max_logs' ],
		main: 'log_blocked_ips',
	},
	{
		key: 'sharing',
		tab: 'data',
		icon: 'hub',
		title: __( 'Data sharing', 'zero-spam' ),
		keys: [ 'share_data' ],
		main: 'share_data',
	},
	{
		key: 'apimon',
		tab: 'api',
		icon: 'activity',
		title: __( 'API monitoring', 'zero-spam' ),
		blurb: __(
			'Track Enhanced Protection API calls, quota and response times.',
			'zero-spam'
		),
		keys: [
			'api_monitoring',
			'api_retention',
			'quota_reset_date',
			'delete_data_on_uninstall',
		],
		main: 'api_monitoring',
	},
	{
		key: 'alertch',
		tab: 'api',
		icon: 'mail',
		title: __( 'Alert channels', 'zero-spam' ),
		keys: [
			'alert_email',
			'alert_admin_notice',
			'alert_webhook_url',
			'alert_network',
			'test_alerts',
		],
	},
	{
		key: 'alertrules',
		tab: 'api',
		icon: 'warn',
		title: __( 'Alert rules', 'zero-spam' ),
		keys: ALERTS.flatMap( ( type ) => [
			`alert_${ type }`,
			`threshold_${ type }`,
		] ),
	},
	{
		key: 'proxies',
		tab: 'advanced',
		icon: 'server',
		title: __( 'Trusted proxies', 'zero-spam' ),
		keys: [ 'trusted_proxies' ],
	},
	{
		key: 'rescue',
		tab: 'advanced',
		icon: 'buoy',
		title: __( 'Rescue mode', 'zero-spam' ),
		keys: [ 'rescue_mode' ],
		custom: 'rescue',
	},
	{
		key: 'nolockout',
		tab: 'advanced',
		icon: 'lock',
		title: __( 'No-lockout addresses', 'zero-spam' ),
		keys: [],
		custom: 'nolockout',
	},
	{
		key: 'debug',
		tab: 'debug',
		icon: 'code',
		title: __( 'Debug', 'zero-spam' ),
		keys: [ 'debug', 'debug_ip' ],
		main: 'debug',
	},
	{
		key: 'system',
		tab: 'debug',
		icon: 'server',
		title: __( 'System report', 'zero-spam' ),
		blurb: __(
			'Versions and settings for a support request. Never visitor data or keys.',
			'zero-spam'
		),
		keys: [],
		custom: 'system',
	},
	{
		key: 'io',
		tab: 'io',
		icon: 'down',
		title: __( 'Import / export settings', 'zero-spam' ),
		keys: [],
		custom: 'io',
	},
	{
		key: 'errlog',
		tab: 'io',
		icon: 'warn',
		title: __( 'Error log', 'zero-spam' ),
		keys: [],
		custom: 'errlog',
	},
];

/** Tabs a schema group belongs to (the schema's groups of the Settings area). */
const GROUP_TABS = {
	general: 'general',
	api: 'api',
	debug: 'debug',
	proxies: 'advanced',
};

/**
 * The cards of the Settings area with their schema entries.
 *
 * @param {Object|undefined} schema settings/schema answer.
 * @return {Object[]} Cards with `entries` (schema entries, in card order; missing ones left out).
 */
export function buildCards( schema ) {
	const all = Array.isArray( schema?.settings ) ? schema.settings : [];
	const byKey = {};
	all.forEach( ( entry ) => {
		byKey[ entry.key ] = entry;
	} );

	const cards = cardList().map( ( card ) => ( {
		...card,
		entries: card.keys.map( ( key ) => byKey[ key ] ).filter( Boolean ),
	} ) );

	// Settings of this area no card lists: one card per tab, after the others.
	const placed = new Set( cards.flatMap( ( card ) => card.keys ) );
	const extra = {};
	all.forEach( ( entry ) => {
		if (
			'settings' !== entry.area ||
			placed.has( entry.key ) ||
			SHOWN_ELSEWHERE.includes( entry.key )
		) {
			return;
		}
		const tab = GROUP_TABS[ entry.group ] || 'general';
		( extra[ tab ] = extra[ tab ] || [] ).push( entry );
	} );

	Object.keys( extra ).forEach( ( tab ) => {
		const last = cards.map( ( c ) => c.tab ).lastIndexOf( tab );
		cards.splice( last + 1, 0, {
			key: `more-${ tab }`,
			tab,
			icon: 'sliders',
			title: __( 'More settings', 'zero-spam' ),
			blurb: __( 'Added by other plugins or extensions.', 'zero-spam' ),
			keys: extra[ tab ].map( ( e ) => e.key ),
			entries: extra[ tab ],
		} );
	} );

	// A card made of settings that are all missing (an inactive module) is left out.
	return cards.filter( ( card ) => card.custom || card.entries.length > 0 );
}

/**
 * The tab and card a ?section= or ?card= argument points to: a card key
 * (`proxies`) or a setting key (`max_logs`).
 *
 * @param {Object[]} cards   buildCards().
 * @param {string}   section Card or setting key.
 * @return {{tab: string, card: string, key: string}|null} Where it is, or null.
 */
export function locate( cards, section ) {
	if ( ! section ) {
		return null;
	}
	const card =
		cards.find( ( c ) => c.key === section ) ||
		cards.find( ( c ) => c.keys.includes( section ) );
	if ( ! card ) {
		return null;
	}
	return {
		tab: card.tab,
		card: card.key,
		key: card.key === section ? '' : section,
	};
}

/**
 * Unsaved changes per tab, for the tab labels.
 *
 * @param {Object[]}               cards   buildCards().
 * @param {Object<string,unknown>} payload useSettings().payload.
 * @return {Object<string, number>} Count by tab.
 */
export function tabCounts( cards, payload ) {
	const counts = Object.fromEntries( TABS.map( ( t ) => [ t, 0 ] ) );
	Object.keys( payload || {} ).forEach( ( key ) => {
		const card = cards.find( ( c ) => c.keys.includes( key ) );
		if ( card ) {
			counts[ card.tab ] += 1;
		}
	} );
	return counts;
}

/**
 * A card's status badge, or null.
 *
 * @param {Object}                   card    Card.
 * @param {(key: string) => unknown} valueOf Current value of a setting.
 * @param {Object<string, Object>}   entries Schema entries by key.
 * @return {{label: string, tone: string}|null} Badge.
 */
export function cardStatus( card, valueOf, entries ) {
	if ( ! card.main || ! entries[ card.main ] ) {
		return null;
	}
	return isOn( entries[ card.main ], valueOf( card.main ) )
		? { label: __( 'On', 'zero-spam' ), tone: 'ok' }
		: { label: __( 'Off', 'zero-spam' ), tone: '' };
}

/**
 * How many settings of a card can be changed (for a locked card's badge).
 *
 * @param {Object} card Card.
 * @return {{locked: number, total: number}} Locked and value-holding settings.
 */
export function lockCount( card ) {
	const values = card.entries.filter( holdsValue );
	return {
		locked: values.filter( ( e ) => e.locked ).length,
		total: values.length,
	};
}
