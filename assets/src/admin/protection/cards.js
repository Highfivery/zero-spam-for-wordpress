/**
 * The cards of the Protection area (pure; unit tested): one per settings
 * section of a tab, built from the settings schema and protection/modules.
 *
 * Built-in sections get the prototype's icon, title, blurb and supported
 * signals; any other section (an extension's) gets its own title and the
 * same treatment otherwise, so third-party sections render generically.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ACTIONS, isOn } from './settings-model';
import { ago, minutesAgo, num } from '../data/format';

/** The Protection tabs, in order. */
export const TABS = [ 'detection', 'integrations', 'intelligence' ];

/** @return {Object<string, string>} Tab names. */
export const tabNames = () => ( {
	detection: __( 'Detection', 'zero-spam' ),
	integrations: __( 'Integrations', 'zero-spam' ),
	intelligence: __( 'Intelligence', 'zero-spam' ),
} );

/** @return {Object<string, string>} Names of the supported signals. */
export const signalNames = () => ( {
	honeypot: __( 'Honeypot', 'zero-spam' ),
	email: __( 'Email protection', 'zero-spam' ),
	davidwalsh: __( 'David Walsh', 'zero-spam' ),
	words: __( 'Disallowed words', 'zero-spam' ),
} );

const ALL_SIGNALS = [ 'honeypot', 'davidwalsh', 'email', 'words' ];

/**
 * What the built-in sections look like.
 *
 * @return {Object<string, {icon: string, title?: string, blurb?: string, signals?: string[]}>} Card details by section.
 */
export const cardMeta = () => ( {
	comments: {
		icon: 'comment',
		title: __( 'Comments', 'zero-spam' ),
		blurb: __( 'Protect WordPress comments from spam.', 'zero-spam' ),
		signals: [ 'honeypot', 'email', 'davidwalsh', 'words' ],
	},
	registration: {
		icon: 'userplus',
		title: __( 'Registration', 'zero-spam' ),
		blurb: __( 'Protect user registrations from spam.', 'zero-spam' ),
		signals: [ 'honeypot', 'email', 'davidwalsh' ],
	},
	login: {
		icon: 'key',
		title: __( 'User login', 'zero-spam' ),
		blurb: __( 'Protect the WordPress login from spam bots.', 'zero-spam' ),
		signals: [ 'honeypot', 'davidwalsh' ],
	},
	davidwalsh: {
		icon: 'code',
		title: __( 'David Walsh browser check', 'zero-spam' ),
		blurb: __(
			'JavaScript-based spam protection. No CAPTCHAs for real visitors.',
			'zero-spam'
		),
	},
	honeypot: {
		icon: 'shield',
		title: __( 'Honeypot', 'zero-spam' ),
		blurb: __(
			'A hidden field people never see. Bots fill it in and get caught.',
			'zero-spam'
		),
	},
	security: {
		icon: 'lock',
		title: __( 'Security', 'zero-spam' ),
		blurb: __(
			'Hide details that scanners use to find out your WordPress version, and close doors bots use to guess passwords.',
			'zero-spam'
		),
	},
	contactform7: { icon: 'form', signals: ALL_SIGNALS },
	wpforms: { icon: 'form', signals: ALL_SIGNALS },
	woocommerce: {
		icon: 'cart',
		signals: [ 'honeypot', 'email', 'davidwalsh' ],
	},
	fluent_form: { icon: 'form', signals: [ 'honeypot', 'davidwalsh' ] },
	mailchimp4wp: { icon: 'mail', signals: [ 'honeypot', 'davidwalsh' ] },
	givewp: { icon: 'form', signals: [ 'honeypot', 'email' ] },
	formidable: { icon: 'form', signals: ALL_SIGNALS },
	gravityforms: { icon: 'form', signals: ALL_SIGNALS },
	'wp-user-avatar': { icon: 'userplus', signals: [ 'honeypot', 'email' ] },
	elementor: {
		icon: 'form',
		title: __( 'Elementor Pro forms', 'zero-spam' ),
		signals: [ 'davidwalsh', 'email', 'words' ],
	},
	zerospam: {
		icon: 'hub',
		title: __( 'Enhanced Protection', 'zero-spam' ),
		blurb: __(
			"Zero Spam's global IP and email reputation service. Needs a license key.",
			'zero-spam'
		),
	},
	stop_forum_spam: {
		icon: 'globe',
		title: __( 'Stop Forum Spam', 'zero-spam' ),
		blurb: __(
			'Check IPs against the Stop Forum Spam database. Free, no key needed.',
			'zero-spam'
		),
	},
	project_honeypot: {
		icon: 'shield',
		title: __( 'Project Honeypot', 'zero-spam' ),
		blurb: __(
			'Check IPs against Project Honeypot. Needs a free access key.',
			'zero-spam'
		),
	},
	ipinfo: {
		icon: 'globe',
		title: __( 'IPinfo (geolocation)', 'zero-spam' ),
		blurb: __(
			'Adds country, region and city to detections and powers location blocking.',
			'zero-spam'
		),
	},
	ipstack: {
		icon: 'globe',
		title: __( 'ipstack (geolocation)', 'zero-spam' ),
		blurb: __(
			'Alternative geolocation service. Also powers location blocking.',
			'zero-spam'
		),
	},
	ipbase: {
		icon: 'globe',
		title: __( 'ipbase (geolocation)', 'zero-spam' ),
		blurb: __(
			'IP geolocation and details. Does not enable location blocking.',
			'zero-spam'
		),
	},
	google: {
		icon: 'globe',
		title: __( 'Google Maps', 'zero-spam' ),
		blurb: __( 'Shows a map in detection details.', 'zero-spam' ),
	},
} );

/** Card order per tab; other sections follow in schema order. */
export const ORDER = {
	detection: [
		'comments',
		'registration',
		'login',
		'davidwalsh',
		'honeypot',
		'security',
	],
	integrations: [
		'contactform7',
		'wpforms',
		'woocommerce',
		'fluent_form',
		'mailchimp4wp',
		'givewp',
		'formidable',
		'gravityforms',
		'wp-user-avatar',
		'elementor',
	],
	intelligence: [
		'zerospam',
		'stop_forum_spam',
		'project_honeypot',
		'ipinfo',
		'ipstack',
		'ipbase',
		'google',
	],
};

/** Settings shown on a card other than their section's. */
export const CARD_OF = { regenerate_honeypot: 'honeypot' };

/** Settings the cards handle themselves (the license key has its own panel). */
export const OWN_UI = [ 'zerospam_license' ];

/**
 * Whether a schema entry is help (an html setting with markup).
 *
 * @param {Object} entry Schema entry.
 * @return {boolean} Help.
 */
const isHelp = ( entry ) => 'html' === entry.type && ! ACTIONS[ entry.key ];

/**
 * The cards of a tab.
 *
 * Each card has its settings split in two: `visible` (the switch, or the
 * switches, and any key field; everything when there is no switch) and
 * `fine` (the rest, behind "Fine-tune"). Integrations whose plugin is not
 * active come last, flagged `inactive`.
 *
 * @param {Object|undefined} schema  settings/schema answer.
 * @param {Object|undefined} modules protection/modules answer.
 * @param {string}           tab     Tab.
 * @return {Array<Object>} Cards.
 */
export function buildCards( schema, modules, tab ) {
	const meta = cardMeta();
	const moduleList = Array.isArray( modules?.modules ) ? modules.modules : [];
	const moduleOf = {};
	moduleList.forEach( ( m ) => {
		moduleOf[ m.key ] = m;
	} );

	const cards = new Map();
	( schema?.sections || [] )
		.filter( ( s ) => 'protection' === s.area && tab === s.group )
		.forEach( ( s ) => {
			cards.set( s.key, {
				key: s.key,
				sectionTitle: s.title,
				settings: [],
			} );
		} );

	( schema?.settings || [] )
		.filter(
			( e ) =>
				'protection' === e.area &&
				tab === e.group &&
				! OWN_UI.includes( e.key )
		)
		.forEach( ( entry ) => {
			const key = CARD_OF[ entry.key ] || entry.section;
			if ( ! cards.has( key ) ) {
				cards.set( key, { key, sectionTitle: '', settings: [] } );
			}
			cards.get( key ).settings.push( entry );
		} );

	if ( 'integrations' === tab ) {
		moduleList
			.filter(
				( m ) =>
					'integrations' === m.group &&
					false === m.available &&
					! cards.has( m.key )
			)
			.forEach( ( m ) => {
				cards.set( m.key, {
					key: m.key,
					sectionTitle: m.title,
					settings: [],
					notInstalled: true,
				} );
			} );
	}

	const order = ORDER[ tab ] || [];
	const list = Array.from( cards.values() ).filter(
		( card ) =>
			card.notInstalled ||
			card.settings.length ||
			// The license panel lives on this card even without other settings.
			'zerospam' === card.key
	);

	return list
		.map( ( card, index ) => {
			const module = moduleOf[ card.key ] || null;
			const details = meta[ card.key ] || {};
			const keys = card.settings.map( ( e ) => e.key );
			const main = ( module?.switches || [] ).filter( ( k ) =>
				keys.includes( k )
			);
			const secrets = card.settings.filter( ( e ) => e.secret );
			let visible;
			if ( main.length ) {
				visible = card.settings.filter(
					( e ) => main.includes( e.key ) || e.secret
				);
			} else if ( secrets.length ) {
				visible = secrets;
			} else {
				visible = card.settings.filter( ( e ) => ! isHelp( e ) );
			}
			const fine = card.settings.filter(
				( e ) => ! visible.includes( e )
			);
			const plugin = module?.plugin || null;

			return {
				key: card.key,
				title:
					details.title ||
					card.sectionTitle ||
					module?.title ||
					card.key,
				blurb: details.blurb || '',
				icon:
					details.icon ||
					( 'integrations' === tab ? 'form' : 'shield' ),
				signals: details.signals || [],
				module,
				plugin,
				settings: card.settings,
				main,
				visible,
				fine,
				inactive:
					Boolean( card.notInstalled ) ||
					Boolean( plugin && false === plugin.active ),
				notInstalled: Boolean( card.notInstalled ),
				locked: Boolean( module?.locked ),
				position: order.includes( card.key )
					? order.indexOf( card.key )
					: 1000 + index,
			};
		} )
		.sort( ( a, b ) => {
			if ( a.notInstalled !== b.notInstalled ) {
				return a.notInstalled ? 1 : -1;
			}
			return a.position - b.position;
		} );
}

/**
 * A card's status badge.
 *
 * @param {Object}                          card      Card.
 * @param {(...args: unknown[]) => unknown} valueOf   Current (draft or saved) value of a setting.
 * @param {Object}                          [license] license route answer.
 * @return {{tone: string, label: string}|null} Badge.
 */
export function cardStatus( card, valueOf, license ) {
	if ( card.inactive ) {
		return { tone: '', label: __( 'Plugin not active', 'zero-spam' ) };
	}

	if ( 'zerospam' === card.key ) {
		if ( ! license || 'none' === license.status ) {
			return { tone: 'warn', label: __( 'No license key', 'zero-spam' ) };
		}
		if ( 'invalid' === license.status ) {
			return {
				tone: 'danger',
				label: __( 'Key not valid', 'zero-spam' ),
			};
		}
		return license.enabled
			? { tone: 'ok', label: __( 'Active', 'zero-spam' ) }
			: { tone: 'warn', label: __( 'Off', 'zero-spam' ) };
	}

	const byKey = {};
	card.settings.forEach( ( e ) => {
		byKey[ e.key ] = e;
	} );
	const secret = card.settings.find( ( e ) => e.secret );
	const hasKey = () => {
		const value = valueOf( secret.key );
		return 'string' === typeof value ? '' !== value : Boolean( value?.set );
	};

	if ( card.main.length ) {
		const on = card.main.filter( ( k ) =>
			isOn( byKey[ k ], valueOf( k ) )
		).length;
		if ( on && secret && ! hasKey() ) {
			return { tone: 'warn', label: __( 'Needs a key', 'zero-spam' ) };
		}
		if ( on === card.main.length ) {
			return { tone: 'ok', label: __( 'On', 'zero-spam' ) };
		}
		if ( 0 === on ) {
			return { tone: 'warn', label: __( 'Off', 'zero-spam' ) };
		}
		return {
			tone: 'ok',
			label: sprintf(
				/* translators: 1: switches that are on, 2: switches on the card */
				__( '%1$d of %2$d on', 'zero-spam' ),
				on,
				card.main.length
			),
		};
	}

	if ( secret ) {
		return hasKey()
			? { tone: 'ok', label: __( 'Connected', 'zero-spam' ) }
			: { tone: '', label: __( 'Not set up', 'zero-spam' ) };
	}

	return null;
}

/**
 * The card's detections in words, or '' (cards that do not detect).
 *
 * @param {Object} card     Card.
 * @param {number} nowLocal Site-local now (ms, read as UTC).
 * @return {string} Line.
 */
export function detectionLine( card, nowLocal ) {
	const module = card.module;
	if ( ! module || card.inactive || 'intelligence' === module.group ) {
		return '';
	}
	const week = Number( module.detections_7d ) || 0;
	if ( ! module.last_detection ) {
		return __( 'No detections in the last 7 days.', 'zero-spam' );
	}
	const when = ago( minutesAgo( module.last_detection, nowLocal ) );
	if ( ! week ) {
		return sprintf(
			/* translators: %s: relative time, such as 3 days ago */
			__( 'Last detection %s. None in the last 7 days.', 'zero-spam' ),
			when
		);
	}
	return sprintf(
		/* translators: 1: relative time, such as 2 h ago, 2: number of detections */
		_n(
			'Last detection %1$s · %2$s in the last 7 days.',
			'Last detection %1$s · %2$s in the last 7 days.',
			week,
			'zero-spam'
		),
		when,
		num( week )
	);
}
