/**
 * A settings schema and protection/modules answer shaped like the routes'
 * (Settings_Schema::get(), Protection_Controller), small enough to read:
 * the built-in detection sections, the honeypot action, an integration with
 * its plugin active and one without, a third-party section, and the
 * reputation services.
 */

const entry = ( key, section, extra = {} ) => ( {
	key,
	option: `zero-spam-${ section }`,
	module: section,
	section,
	title: key,
	description: '',
	type: 'checkbox',
	multiple: false,
	options: [ { value: 'enabled', label: '' } ],
	min: null,
	max: null,
	step: null,
	default: null,
	recommended: null,
	placeholder: '',
	lockable: true,
	secret: false,
	depends_on: null,
	area: 'protection',
	group: 'detection',
	order: 0,
	locked: false,
	source: 'default',
	value: false,
	html: null,
	...extra,
} );

const section = ( key, title, group, area = 'protection' ) => ( {
	key,
	title,
	description: '',
	module: key,
	area,
	group,
	order: 0,
} );

export const schema = () => ( {
	sections: [
		section( 'general', 'General Settings', 'general', 'settings' ),
		section( 'security', 'Security', 'detection' ),
		section( 'comments', 'Comments', 'detection' ),
		section( 'login', 'Login', 'detection' ),
		section( 'davidwalsh', 'David Walsh', 'detection' ),
		section( 'wpforms', 'WPForms', 'integrations' ),
		section( 'zs_widgets', 'Widget Forms', 'integrations' ),
		section( 'zerospam', 'Zero Spam', 'intelligence' ),
		section( 'ipstack', 'ipstack', 'intelligence' ),
	],
	settings: [
		entry( 'regenerate_honeypot', 'general', {
			module: 'settings',
			type: 'html',
			options: null,
			title: 'Regenerate Honeypot ID',
			description: 'Current ID: <code>abc12</code>',
			html: '',
		} ),
		entry( 'max_logs', 'general', {
			module: 'settings',
			type: 'number',
			options: null,
			area: 'settings',
			group: 'general',
			value: 10000,
		} ),
		entry( 'verify_comments', 'comments', {
			title: 'Protect comments',
			recommended: 'enabled',
			value: 'enabled',
		} ),
		entry( 'comment_spam_message', 'comments', {
			title: 'Message',
			type: 'text',
			options: null,
			value: 'No spam.',
		} ),
		entry( 'verify_login', 'login', { title: 'Protect logins' } ),
		entry( 'davidwalsh', 'davidwalsh', { title: 'Enable Protection' } ),
		entry( 'davidwalsh_form_selectors', 'davidwalsh', {
			title: 'Custom Form Selectors',
			type: 'text',
			options: null,
			placeholder: '.my-custom-form',
			value: '',
		} ),
		entry( 'davidwalsh_form_selectors_help', 'davidwalsh', {
			title: "How to Find Your Form's Selector",
			type: 'html',
			options: null,
			lockable: false,
			html: '<ol><li>Right-click on your form.</li></ol>',
		} ),
		entry( 'disable_xmlrpc', 'security', {
			title: 'Disable XML-RPC',
			default: 'enabled',
			recommended: 'enabled',
			value: 'enabled',
		} ),
		entry( 'hide_wp_version', 'security', {
			title: 'Hide the WordPress Version',
			default: 'enabled',
			value: 'disabled',
		} ),
		entry( 'disable_emojis', 'security', { title: 'Disable emojis' } ),
		entry( 'verify_wpforms', 'wpforms', {
			title: 'Protect WPForms',
			group: 'integrations',
		} ),
		entry( 'verify_zs_widgets', 'zs_widgets', {
			title: 'Protect widget forms',
			group: 'integrations',
		} ),
		entry( 'zs_widgets_mode', 'zs_widgets', {
			title: 'Mode',
			type: 'radio',
			group: 'integrations',
			options: [
				{ value: 'strict', label: 'Strict' },
				{ value: 'relaxed', label: 'Relaxed' },
			],
			value: 'strict',
		} ),
		entry( 'zerospam', 'zerospam', {
			title: 'Enhanced Protection',
			group: 'intelligence',
		} ),
		entry( 'zerospam_license', 'zerospam', {
			title: 'License Key',
			type: 'text',
			options: null,
			secret: true,
			group: 'intelligence',
			value: { set: false, source: 'default' },
		} ),
		entry( 'zerospam_timeout', 'zerospam', {
			title: 'API Timeout',
			type: 'number',
			options: null,
			group: 'intelligence',
			min: 1,
			max: 30,
			value: 5,
		} ),
		entry( 'ipstack_api', 'ipstack', {
			title: 'API Key',
			type: 'text',
			options: null,
			secret: true,
			group: 'intelligence',
			value: { set: true, hint: '••••a1b2', source: 'site' },
		} ),
		entry( 'ipstack_cache', 'ipstack', {
			title: 'Cache Expiration',
			type: 'number',
			options: null,
			group: 'intelligence',
			value: 14,
		} ),
	],
	meta: {},
} );

const mod = ( key, group, extra = {} ) => ( {
	key,
	title: key,
	area: 'protection',
	group,
	available: true,
	enabled: false,
	switches: [],
	needs_key: false,
	key_setting: null,
	plugin: null,
	locked: false,
	locked_keys: [],
	last_detection: null,
	detections_7d: 0,
	...extra,
} );

export const modules = () => ( {
	modules: [
		mod( 'security', 'detection', {
			switches: [ 'disable_xmlrpc', 'hide_wp_version', 'disable_emojis' ],
			enabled: true,
		} ),
		mod( 'comments', 'detection', {
			switches: [ 'verify_comments' ],
			enabled: true,
			last_detection: '2026-10-25T09:00:00',
			detections_7d: 14,
		} ),
		mod( 'login', 'detection', { switches: [ 'verify_login' ] } ),
		mod( 'davidwalsh', 'detection', { switches: [ 'davidwalsh' ] } ),
		mod( 'wpforms', 'integrations', {
			switches: [ 'verify_wpforms' ],
			plugin: { name: 'WPForms', active: true },
		} ),
		mod( 'zs_widgets', 'integrations', {
			switches: [ 'verify_zs_widgets' ],
		} ),
		mod( 'zerospam', 'intelligence', {
			switches: [ 'zerospam' ],
			needs_key: true,
			key_setting: 'zerospam_license',
		} ),
		mod( 'ipstack', 'intelligence', {
			enabled: true,
			key_setting: 'ipstack_api',
		} ),
		mod( 'contactform7', 'integrations', {
			title: 'Contact Form 7',
			available: false,
			plugin: { name: 'Contact Form 7', active: false },
		} ),
	],
	attention: [],
} );

export const basicLicense = () => ( {
	key: { set: false, source: 'default' },
	status: 'none',
	enabled: false,
	plan: null,
	queries: null,
	reset_date: null,
	renewal: { status: null, date: null },
	checked_at: null,
} );

export const enhancedLicense = () => ( {
	key: { set: true, hint: '••••7Q4K', source: 'site' },
	status: 'valid',
	enabled: true,
	plan: { id: 'ZEROSPAMBUSINESS', name: 'Business' },
	queries: { limit: 50000, used: 31200, remaining: 18800 },
	reset_date: null,
	renewal: { status: 'active', date: null },
	checked_at: Math.floor( Date.now() / 1000 ) - 300,
} );
