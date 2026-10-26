/**
 * The network page's model (pure; unit tested): the REST paths it reads
 * (exactly as Admin_App::network_preloads() preloads them), the settings
 * cards built from the settings schema and the network values, what the
 * lock switch and the Sites column say, the comparison rows, template
 * chips, audit filters, statistics paging and the CSV export.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatValue, holdsValue } from '../app/fields/settings-model';

const NS = '/zero-spam/v1';

/** Rows per page, as Admin_App preloads them. */
export const AUDIT_PER_PAGE = 25;
export const COMPARISON_PER_PAGE = 5;
export const STATS_PER_PAGE = 20;
export const BLOCKS_PER_PAGE = 10;

/** Periods of the statistics, as the route takes them. */
export const PERIODS = [ 'today', 'yesterday', '7', '30' ];

/**
 * Builds a query string from defined arguments, in the given order.
 *
 * @param {Array<[string, unknown]>} args Arguments.
 * @return {string} `?a=1&b=2`, or ''.
 */
const query = ( args ) => {
	const parts = args
		.filter(
			( [ , v ] ) =>
				undefined !== v && null !== v && '' !== v && false !== v
		)
		.map(
			( [ k, v ] ) =>
				`${ encodeURIComponent( k ) }=${ encodeURIComponent( v ) }`
		);
	return parts.length ? `?${ parts.join( '&' ) }` : '';
};

/** REST paths of the network page (the first of each is preloaded). */
export const PATHS = {
	schema: `${ NS }/settings/schema`,
	values: `${ NS }/settings?scope=network`,
	overview: `${ NS }/network/overview`,
	templates: `${ NS }/network-settings/templates`,
	exported: `${ NS }/network-settings/export`,
	notifications: `${ NS }/network/notifications`,
	audit: ( filters = {}, page = 1 ) =>
		`${ NS }/network-settings/audit${ query( [
			[ 'page', page ],
			[ 'per_page', AUDIT_PER_PAGE ],
			[ 'action_type', filters.action ],
			[ 'setting_key', filters.key ],
			[ 'user_id', filters.user ],
			[ 'site_id', filters.site ],
			[ 'after', filters.after ],
			[ 'before', filters.before ],
		] ) }`,
	comparison: ( page = 1 ) =>
		`${ NS }/network/comparison${ query( [
			[ 'page', page ],
			[ 'per_page', COMPARISON_PER_PAGE ],
		] ) }`,
	stats: ( period = '30', page = 1, sort = 'blocked:desc' ) => {
		const [ orderby, order ] = String( sort ).split( ':' );
		return `${ NS }/network/stats${ query( [
			[ 'period', period ],
			[ 'page', page ],
			[ 'per_page', STATS_PER_PAGE ],
			[ 'orderby', orderby || 'blocked' ],
			[ 'order', order || 'desc' ],
		] ) }`;
	},
	blocks: ( page = 1, search = '' ) =>
		`${ NS }/network/blocks${ query( [
			[ 'page', page ],
			[ 'per_page', BLOCKS_PER_PAGE ],
			[ 'search', search ],
		] ) }`,
	sites: ( search = '', page = 1 ) =>
		`${ NS }/network/sites${ query( [
			[ 'page', page ],
			[ 'per_page', 50 ],
			[ 'search', search ],
		] ) }`,
};

/**
 * The groups of the Settings tab (`&group=`): where each setting lives in
 * the site admin.
 *
 * @return {Array<[string, string]>} Keys and names, in order.
 */
export const settingGroups = () => [
	[ 'detection', __( 'Detection', 'zero-spam' ) ],
	[ 'integrations', __( 'Integrations', 'zero-spam' ) ],
	[ 'intelligence', __( 'Intelligence', 'zero-spam' ) ],
	[ 'settings', __( 'Settings', 'zero-spam' ) ],
	[ 'rules', __( 'Rules', 'zero-spam' ) ],
];

/**
 * The Settings tab group of a schema entry (the Protection groups by name,
 * Settings and Rules each as one group).
 *
 * @param {Object} entry Schema entry or section.
 * @return {string} Group key.
 */
export function groupOf( entry ) {
	if ( 'protection' === entry?.area ) {
		return [ 'detection', 'integrations', 'intelligence' ].includes(
			entry.group
		)
			? entry.group
			: 'integrations';
	}
	return 'rules' === entry?.area ? 'rules' : 'settings';
}

/**
 * Names of the Settings and Rules groups, used as card titles there.
 *
 * @return {Object<string, string>} Names by schema group.
 */
const subgroupNames = () => ( {
	general: __( 'General', 'zero-spam' ),
	data: __( 'Logging & data', 'zero-spam' ),
	api: __( 'API monitoring', 'zero-spam' ),
	advanced: __( 'Advanced', 'zero-spam' ),
	proxies: __( 'Trusted proxies', 'zero-spam' ),
	debug: __( 'Debug', 'zero-spam' ),
	'allowed-ips': __( 'Allowed IPs', 'zero-spam' ),
	'email-domains': __( 'Email domains', 'zero-spam' ),
	'allowed-words': __( 'Words', 'zero-spam' ),
} );

/**
 * Whether a schema entry can be set for the network: it holds a value and
 * can be locked (help text and buttons stay on each site).
 *
 * @param {Object} entry Schema entry.
 * @return {boolean} Settable.
 */
export const networkSettable = ( entry ) =>
	holdsValue( entry ) && false !== entry.lockable;

/**
 * The network layer as the settings route returns it with scope=network.
 *
 * @param {Object|undefined} layer `{ settings, locked }`.
 * @return {{values: Object<string, unknown>, locked: Set<string>}} Values and locked keys.
 */
export function readLayer( layer ) {
	return {
		values:
			layer?.settings && 'object' === typeof layer.settings
				? layer.settings
				: {},
		locked: new Set( Array.isArray( layer?.locked ) ? layer.locked : [] ),
	};
}

/**
 * A schema entry as the network page edits it: the network value (or the
 * plugin default when the network sets none), never locked or "from the
 * network" here (this is where those are decided).
 *
 * @param {Object}                        entry Schema entry (main site).
 * @param {{values: Object, locked: Set}} layer Network layer (readLayer()).
 * @return {Object} Entry with `has` (the network sets a value) and `netLocked`.
 */
export function networkEntry( entry, layer ) {
	const has = Object.prototype.hasOwnProperty.call( layer.values, entry.key );
	let value = has ? layer.values[ entry.key ] : entry.default;
	if ( entry.secret ) {
		const masked =
			has && value && 'object' === typeof value ? value : { set: false };
		value = { ...masked, source: 'site' };
	}
	return {
		...entry,
		value,
		locked: false,
		source: 'default',
		has,
		netLocked: layer.locked.has( entry.key ),
	};
}

/**
 * The cards of the Settings tab: per settings section (Protection) or
 * group (Settings, Rules), the settings a network can set, in schema order.
 *
 * @param {Object|undefined} schema settings/schema answer.
 * @param {Object|undefined} layer  settings?scope=network answer.
 * @return {Array<Object>} Cards: { key, group, title, blurb, entries }.
 */
export function networkCards( schema, layer ) {
	if ( ! schema?.settings ) {
		return [];
	}
	const read = readLayer( layer );
	const sections = {};
	( schema.sections || [] ).forEach( ( s ) => {
		sections[ s.key ] = s;
	} );
	const names = subgroupNames();
	const cards = new Map();

	schema.settings
		.filter( networkSettable )
		.slice()
		.sort( ( a, b ) => ( a.order || 0 ) - ( b.order || 0 ) )
		.forEach( ( entry ) => {
			const group = groupOf( entry );
			const protection = 'protection' === entry.area;
			const key = protection
				? `${ group }-${ entry.section }`
				: `${ group }-${ entry.group }`;
			if ( ! cards.has( key ) ) {
				const section = sections[ entry.section ] || {};
				cards.set( key, {
					key,
					group,
					title: protection
						? section.title || entry.section
						: names[ entry.group ] || section.title || entry.group,
					blurb: protection ? section.description || '' : '',
					order: section.order || 0,
					entries: [],
				} );
			}
			cards.get( key ).entries.push( networkEntry( entry, read ) );
		} );

	const order = settingGroups().map( ( [ k ] ) => k );
	return [ ...cards.values() ].sort(
		( a, b ) =>
			order.indexOf( a.group ) - order.indexOf( b.group ) ||
			a.order - b.order
	);
}

/**
 * How many sites follow a network value, as the Sites column says it.
 *
 * @param {Object}           entry    Network entry (networkEntry()).
 * @param {Object|undefined} overview network/overview answer.
 * @return {{label: string, tone: string, custom: number, sentence: string}} What to show.
 */
export function sitesState( entry, overview ) {
	const sites = Number( overview?.sites ) || 0;
	const custom = Number( overview?.custom?.[ entry.key ] ) || 0;

	if ( entry.netLocked ) {
		return {
			label: __( 'Locked on every site', 'zero-spam' ),
			tone: 'lock',
			custom: 0,
			sentence: __(
				'Every site uses the network value and cannot change it.',
				'zero-spam'
			),
		};
	}
	if ( ! entry.has ) {
		return {
			label: __( 'Not set for the network', 'zero-spam' ),
			tone: '',
			custom: 0,
			sentence: __(
				'Each site uses its own value. Saving a value here makes it the default for every site.',
				'zero-spam'
			),
		};
	}
	const following = Math.max( 0, sites - custom );
	return {
		label: sprintf(
			/* translators: 1: sites using the network value, 2: sites in the network */
			__( '%1$s of %2$s sites use it', 'zero-spam' ),
			following.toLocaleString(),
			sites.toLocaleString()
		),
		tone: custom > 0 ? 'warn' : '',
		custom,
		sentence:
			custom > 0
				? sprintf(
						/* translators: %s: number of sites */
						_n(
							'%s site changed it for itself.',
							'%s sites changed it for themselves.',
							custom,
							'zero-spam'
						),
						custom.toLocaleString()
					)
				: __(
						'Sites that have not changed it use the network value.',
						'zero-spam'
					),
	};
}

/**
 * A network value as text: "(none)" when it is empty, never an empty box;
 * secrets as "Set" or "(none)".
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} value Value.
 * @return {string} Text.
 */
export function valueText( entry, value ) {
	if ( entry?.secret ) {
		const set =
			value && 'object' === typeof value ? Boolean( value.set ) : false;
		return set ? __( 'Set', 'zero-spam' ) : __( '(none)', 'zero-spam' );
	}
	const text = formatValue( entry, value );
	return '' === text ? __( '(none)', 'zero-spam' ) : text;
}

/**
 * What the lock switch says and does.
 *
 * @param {Object}  entry  Network entry.
 * @param {boolean} locked Locked now.
 * @return {{label: string, aria: string}} Visible text and accessible name.
 */
export function lockText( entry, locked ) {
	return {
		label: locked
			? __( 'Locked', 'zero-spam' )
			: __( 'Unlocked', 'zero-spam' ),
		aria: sprintf(
			/* translators: %s: setting name */
			__( 'Lock “%s” for every site', 'zero-spam' ),
			entry.title
		),
	};
}

/**
 * The toast after the lock switch was used.
 *
 * @param {Object}  entry  Network entry.
 * @param {boolean} locked Locked now.
 * @return {string} Message.
 */
export const lockToast = ( entry, locked ) =>
	locked
		? sprintf(
				/* translators: %s: setting name */
				__( '%s: locked for every site', 'zero-spam' ),
				entry.title
			)
		: sprintf(
				/* translators: %s: setting name */
				__( '%s: sites can change it', 'zero-spam' ),
				entry.title
			);

/**
 * Comparison rows with their override count, sorted.
 *
 * @param {Object|undefined} data network/comparison answer.
 * @param {string}           sort name or overrides.
 * @return {Array<Object>} Rows.
 */
export function comparisonRows( data, sort = 'name' ) {
	const rows = Array.isArray( data?.settings ) ? data.settings.slice() : [];
	return rows.sort( ( a, b ) =>
		'overrides' === sort
			? ( b.custom || 0 ) - ( a.custom || 0 ) ||
				String( a.title ).localeCompare( String( b.title ) )
			: String( a.title ).localeCompare( String( b.title ) )
	);
}

/**
 * A comparison cell: what it shows and its badge.
 *
 * @param {Object} row     Comparison row.
 * @param {Object} cell    `{ source, value }` of one site.
 * @param {Object} [entry] Schema entry of the setting (choices, units).
 * @return {{text: string, badge: string, tone: string}} Cell.
 */
export function comparisonCell( row, cell, entry ) {
	const setting = entry || { type: row.type, secret: row.secret };
	const source = cell?.source || 'plugin';
	const value =
		'override' === source || 'site' === source ? cell.value : row.network;
	const names = {
		locked: [ __( 'Locked', 'zero-spam' ), 'lock' ],
		override: [ __( 'Changed', 'zero-spam' ), 'warn' ],
		default: [ __( 'Network', 'zero-spam' ), '' ],
		site: [ __( 'Own value', 'zero-spam' ), '' ],
		plugin: [ __( 'Default', 'zero-spam' ), '' ],
	};
	const [ badge, tone ] = names[ source ] || names.plugin;
	return {
		text:
			'plugin' === source
				? __( 'Plugin default', 'zero-spam' )
				: valueText( setting, value ),
		badge,
		tone,
	};
}

/**
 * The chips of a template card: the first settings with their values,
 * then "+N more".
 *
 * @param {Object}                 template Template.
 * @param {Object<string, Object>} entries  Schema entries by key.
 * @param {number}                 [max]    Chips before "+N more".
 * @return {{chips: Array<{key: string, title: string, value: string}>, more: number}} Chips.
 */
export function templateChips( template, entries, max = 7 ) {
	const keys = Object.keys( template?.settings || {} );
	return {
		chips: keys.slice( 0, max ).map( ( key ) => {
			const entry = entries[ key ];
			return {
				key,
				title: entry?.title || key,
				value: entry
					? valueText( entry, template.settings[ key ] )
					: String( template.settings[ key ] ?? '' ),
			};
		} ),
		more: Math.max( 0, keys.length - max ),
	};
}

/**
 * Whether a template slug is acceptable (as the route checks it).
 *
 * @param {string} slug Slug.
 * @return {boolean} Valid.
 */
export const validSlug = ( slug ) =>
	/^[a-z0-9][a-z0-9_-]{0,62}$/.test( String( slug || '' ) );

/**
 * A slug made from a name.
 *
 * @param {string} name Name.
 * @return {string} Slug.
 */
export const slugify = ( name ) =>
	String( name || '' )
		.toLowerCase()
		.normalize( 'NFKD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 63 );

/**
 * Page numbers and the "Showing 1–25 of 230" range, for the shared Pager.
 *
 * @param {number} total   Entries.
 * @param {number} page    Page.
 * @param {number} perPage Entries per page.
 * @param {number} shown   Entries on this page.
 * @return {{page: number, pages: number, from: number, to: number}} Paging.
 */
export function pageInfo( total, page, perPage, shown ) {
	const count = Number( total ) || 0;
	const pages = Math.max( 1, Math.ceil( count / perPage ) );
	const current = Math.min( Math.max( 1, Number( page ) || 1 ), pages );
	const from = count > 0 ? ( current - 1 ) * perPage + 1 : 0;
	return {
		page: current,
		pages,
		from,
		to: count > 0 ? Math.min( count, from + Math.max( 0, shown ) - 1 ) : 0,
	};
}

/**
 * Names of the statistics periods.
 *
 * @return {Object<string, string>} Names.
 */
export const periodNames = () => ( {
	today: __( 'Today', 'zero-spam' ),
	yesterday: __( 'Yesterday', 'zero-spam' ),
	7: __( 'Last 7 days', 'zero-spam' ),
	30: __( 'Last 30 days', 'zero-spam' ),
} );

/**
 * A site's change from the period before, as a badge.
 *
 * @param {number|null} trend Percent, or null (new: nothing before).
 * @return {{text: string, sr: string, tone: string}} Badge.
 */
export function trendBadge( trend ) {
	if ( null === trend || undefined === trend ) {
		return {
			text: __( 'New', 'zero-spam' ),
			sr: __( 'none in the previous period', 'zero-spam' ),
			tone: 'warn',
		};
	}
	const n = Number( trend ) || 0;
	let arrow = '';
	if ( n > 0 ) {
		arrow = '↑ ';
	} else if ( n < 0 ) {
		arrow = '↓ ';
	}
	let sr = __( 'the same as the previous period', 'zero-spam' );
	if ( n > 0 ) {
		sr = __( 'more than the previous period', 'zero-spam' );
	} else if ( n < 0 ) {
		sr = __( 'fewer than the previous period', 'zero-spam' );
	}
	let tone = '';
	if ( n > 0 ) {
		tone = 'warn';
	} else if ( n < 0 ) {
		tone = 'ok';
	}
	return {
		text: `${ arrow }${ Math.abs( n ) }%`,
		sr,
		tone,
	};
}

/**
 * The protection level badge of a site.
 *
 * @param {string} level enhanced, basic, invalid or unverified.
 * @return {{text: string, tone: string}} Badge.
 */
export function levelBadge( level ) {
	switch ( level ) {
		case 'enhanced':
			return { text: __( 'Enhanced', 'zero-spam' ), tone: 'enh' };
		case 'invalid':
			return {
				text: __( 'License not valid', 'zero-spam' ),
				tone: 'warn',
			};
		case 'unverified':
			return { text: __( 'Not verified yet', 'zero-spam' ), tone: '' };
		default:
			return { text: __( 'Basic', 'zero-spam' ), tone: '' };
	}
}

/**
 * Quotes a CSV field.
 *
 * @param {unknown} value Value.
 * @return {string} Field.
 */
const csvField = ( value ) => {
	let text = String( value ?? '' );
	// A leading =, +, - or @ would be read as a formula by spreadsheets.
	if ( /^[=+\-@]/.test( text ) ) {
		text = `'${ text }`;
	}
	return /[",\n\r]/.test( text ) ? `"${ text.replace( /"/g, '""' ) }"` : text;
};

/**
 * The per-site statistics as CSV (the rows shown).
 *
 * @param {Array<Object>} sites Site rows.
 * @return {string} CSV.
 */
export function statsCsv( sites ) {
	const head = [
		__( 'Site', 'zero-spam' ),
		__( 'Address', 'zero-spam' ),
		__( 'Spam blocked', 'zero-spam' ),
		__( 'Unique IPs', 'zero-spam' ),
		__( 'Top type', 'zero-spam' ),
		__( 'Top country', 'zero-spam' ),
		__( 'Change from the previous period (%)', 'zero-spam' ),
		__( 'Level', 'zero-spam' ),
	];
	const rows = ( sites || [] ).map( ( s ) => [
		s.name,
		s.url,
		s.blocked,
		s.unique_ips,
		s.top_type_label || s.top_type,
		s.country_name || s.top_country,
		null === s.trend ? '' : s.trend,
		s.level ? levelBadge( s.level ).text : '',
	] );
	return [ head, ...rows ]
		.map( ( r ) => r.map( csvField ).join( ',' ) )
		.join( '\r\n' );
}

/**
 * The audit actions, with names, for the filter.
 *
 * @return {Array<[string, string]>} Values and names.
 */
export const auditActions = () => [
	[ 'set', __( 'Change', 'zero-spam' ) ],
	[ 'lock', __( 'Lock', 'zero-spam' ) ],
	[ 'unlock', __( 'Unlock', 'zero-spam' ) ],
	[ 'apply', __( 'Apply', 'zero-spam' ) ],
	[ 'template', __( 'Template', 'zero-spam' ) ],
	[ 'import', __( 'Import', 'zero-spam' ) ],
	[ 'reset', __( 'Reset', 'zero-spam' ) ],
	[ 'block', __( 'Block', 'zero-spam' ) ],
	[ 'unblock', __( 'Unblock', 'zero-spam' ) ],
	[ 'migrate', __( 'Update', 'zero-spam' ) ],
];

/**
 * Whether any audit filter is set.
 *
 * @param {Object} filters Filters.
 * @return {boolean} Filtered.
 */
export const auditFiltered = ( filters ) =>
	Object.values( filters || {} ).some(
		( v ) => undefined !== v && null !== v && '' !== v
	);

/**
 * The JSON of an export, pretty-printed.
 *
 * @param {Object|undefined} data network-settings/export answer.
 * @return {string} JSON.
 */
export const exportJson = ( data ) =>
	JSON.stringify( data && 'object' === typeof data ? data : {}, null, 2 );

/**
 * Reads an import file's settings: `{ key: { value, locked } }` or
 * `{ key: value }`. Throws on anything else.
 *
 * @param {string} text File contents.
 * @return {Object} Settings.
 */
export function parseImport( text ) {
	const data = JSON.parse( text );
	if ( ! data || 'object' !== typeof data || Array.isArray( data ) ) {
		throw new Error( 'not an object' );
	}
	const settings =
		data.settings &&
		'object' === typeof data.settings &&
		! Array.isArray( data.settings )
			? data.settings
			: data;
	if ( ! Object.keys( settings ).length ) {
		throw new Error( 'empty' );
	}
	return settings;
}
