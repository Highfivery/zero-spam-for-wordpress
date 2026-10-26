/**
 * Answers of the network page's routes, shaped like the PHP that sends
 * them: a settings schema with settings of every group, the network layer
 * (settings?scope=network), the overview, templates, audit, comparison,
 * statistics and blocks.
 */

const entry = ( key, extra = {} ) => ( {
	key,
	option: `zero-spam-${ extra.module || 'settings' }`,
	module: 'settings',
	section: 'settings',
	title: key,
	description: '',
	type: 'checkbox',
	multiple: false,
	options: [ { value: 'enabled', label: '' } ],
	min: null,
	max: null,
	step: null,
	suffix: '',
	default: false,
	recommended: null,
	placeholder: '',
	lockable: true,
	secret: false,
	depends_on: null,
	area: 'settings',
	group: 'general',
	order: 0,
	locked: false,
	source: 'default',
	value: false,
	html: null,
	...extra,
} );

/** @return {Object} settings/schema answer. */
export const schema = () => ( {
	sections: [
		{
			key: 'comments',
			title: 'Comments',
			description: 'Protect comments',
			module: 'comments',
			area: 'protection',
			group: 'detection',
			order: 10,
		},
		{
			key: 'wpforms',
			title: 'WPForms',
			description: '',
			module: 'wpforms',
			area: 'protection',
			group: 'integrations',
			order: 20,
		},
		{
			key: 'ipinfo',
			title: 'IPinfo',
			description: '',
			module: 'ipinfo',
			area: 'protection',
			group: 'intelligence',
			order: 30,
		},
		{
			key: 'settings',
			title: 'General Settings',
			description: '',
			module: 'settings',
			area: 'settings',
			group: 'general',
			order: 40,
		},
	],
	settings: [
		entry( 'verify_comments', {
			title: 'Protect comments',
			module: 'comments',
			section: 'comments',
			area: 'protection',
			group: 'detection',
			order: 10,
			default: 'enabled',
		} ),
		entry( 'comment_spam_message', {
			title: 'Comment message',
			type: 'text',
			options: null,
			module: 'comments',
			section: 'comments',
			area: 'protection',
			group: 'detection',
			order: 20,
			default: '',
		} ),
		entry( 'verify_wpforms', {
			title: 'Protect WPForms',
			module: 'wpforms',
			section: 'wpforms',
			area: 'protection',
			group: 'integrations',
			order: 30,
		} ),
		entry( 'ipinfo_access_token', {
			title: 'IPinfo access token',
			type: 'text',
			options: null,
			secret: true,
			module: 'ipinfo',
			section: 'ipinfo',
			area: 'protection',
			group: 'intelligence',
			order: 40,
			value: { set: false, source: 'default' },
		} ),
		entry( 'share_data', {
			title: 'Share data',
			area: 'settings',
			group: 'data',
			order: 50,
			default: 'enabled',
		} ),
		entry( 'max_logs', {
			title: 'Maximum logs',
			type: 'number',
			options: null,
			area: 'settings',
			group: 'data',
			order: 60,
			default: 10000,
			suffix: 'entries',
		} ),
		entry( 'block_method', {
			title: 'Block method',
			type: 'select',
			options: [
				{ value: 'php', label: 'PHP' },
				{ value: 'htaccess_modern', label: '.htaccess' },
			],
			area: 'settings',
			group: 'general',
			order: 70,
			default: 'php',
		} ),
		entry( 'blocked_message', {
			title: 'Blocked message',
			type: 'textarea',
			options: null,
			area: 'settings',
			group: 'general',
			order: 80,
			default: '',
		} ),
		entry( 'ip_whitelist', {
			title: 'IP Allowlist',
			type: 'textarea',
			options: null,
			area: 'rules',
			group: 'allowed-ips',
			order: 90,
			default: '',
		} ),
		entry( 'use_recommended_settings', {
			title: 'Use recommended',
			type: 'html',
			options: null,
			lockable: false,
			order: 100,
		} ),
		entry( 'not_lockable', {
			title: 'Not lockable',
			lockable: false,
			order: 110,
		} ),
	],
	meta: {},
} );

/** @return {Object} settings?scope=network answer. */
export const layer = () => ( {
	settings: {
		verify_comments: 'enabled',
		share_data: 'enabled',
		max_logs: 20000,
		blocked_message: '',
		ip_whitelist: '',
		ipinfo_access_token: { set: true, source: 'network', hint: '••••a1b2' },
	},
	locked: [ 'share_data' ],
	meta: {},
} );

/** @return {Object} network/overview answer. */
export const overview = () => ( {
	sites: 12,
	checked: 12,
	capped: false,
	custom: { verify_comments: 0, max_logs: 2, blocked_message: 1 },
	overrides: 3,
	sites_with_overrides: 2,
	network_settings: 5,
	locked: 1,
	blocks: 1,
} );

/** @return {Object} network-settings/templates answer. */
export const templates = () => ( {
	strict_protection: {
		slug: 'strict_protection',
		name: 'Strict Protection',
		description: 'Maximum security.',
		type: 'built_in',
		settings: {
			verify_comments: 'enabled',
			max_logs: 50000,
			share_data: 'enabled',
			block_method: 'php',
			blocked_message: '',
			verify_wpforms: 'enabled',
			ip_whitelist: '',
			verify_gravityforms: 'enabled',
		},
	},
	'clinic-sites': {
		slug: 'clinic-sites',
		name: 'Clinic sites',
		description: 'Our standard.',
		type: 'custom',
		created_at: '2026-10-13 10:00:00',
		settings: { max_logs: 10000 },
	},
} );

/** @return {Array<Object>} network-settings/audit answer. */
export const audit = () => [
	{
		id: 2,
		date: '2026-10-26T09:20:00',
		user: 'bmarshall',
		user_id: 1,
		action: 'lock',
		action_label: 'Lock',
		key: 'share_data',
		key_title: 'Share data',
		site_name: null,
		old: null,
		new: null,
		summary: 'Locked “Share data” on every site.',
	},
	{
		id: 1,
		date: '2026-10-25T09:20:00',
		user: 'bmarshall',
		user_id: 1,
		action: 'set',
		action_label: 'Change',
		key: 'max_logs',
		key_title: 'Maximum logs',
		site_name: null,
		old: '5000',
		new: '20000',
		summary: 'Changed “Maximum logs” from 5000 to 20000.',
	},
];

/** @return {Object} network/comparison answer. */
export const comparison = () => ( {
	sites: [
		{ id: 1, name: 'Lakeside Dental', url: 'lakeside.example' },
		{ id: 2, name: 'Riverside Ortho', url: 'riverside.example' },
	],
	settings: [
		{
			key: 'share_data',
			title: 'Share data',
			type: 'checkbox',
			secret: false,
			locked: true,
			has_value: true,
			network: 'enabled',
			custom: 0,
			sites: {
				1: { source: 'locked', value: 'enabled' },
				2: { source: 'locked', value: 'enabled' },
			},
		},
		{
			key: 'max_logs',
			title: 'Maximum logs',
			type: 'number',
			secret: false,
			locked: false,
			has_value: true,
			network: 20000,
			custom: 2,
			sites: {
				1: { source: 'default', value: 20000 },
				2: { source: 'override', value: 25000 },
			},
		},
		{
			key: 'blocked_message',
			title: 'Blocked message',
			type: 'textarea',
			secret: false,
			locked: false,
			has_value: true,
			network: '',
			custom: 1,
			sites: {
				1: { source: 'default', value: '' },
				2: { source: 'override', value: 'Go away' },
			},
		},
	],
	checked: 12,
	capped: false,
} );

/** @return {Object} network/stats answer. */
export const stats = () => ( {
	period: '30',
	totals: {
		blocked: 5340,
		unique_ips: 3716,
		sites: 12,
		types: 9,
		previous: 4800,
	},
	checked: 12,
	capped: false,
	attacker_days: 7,
	sites: [
		{
			id: 1,
			name: 'Lakeside Dental',
			url: 'lakeside.example',
			blocked: 1284,
			previous: 1088,
			unique_ips: 903,
			top_type: 'comment',
			top_type_label: 'Comment',
			top_country: 'DE',
			country_name: 'Germany',
			trend: 18,
			level: 'basic',
		},
		{
			id: 2,
			name: 'Riverside Ortho',
			url: 'riverside.example',
			blocked: 2210,
			previous: 1687,
			unique_ips: 1544,
			top_type: 'login',
			top_type_label: 'Login',
			top_country: 'IN',
			country_name: 'India',
			trend: 31,
			level: 'enhanced',
		},
	],
	attackers: [
		{
			ip: '203.0.113.9',
			sites: 6,
			country: 'DE',
			country_name: 'Germany',
			blocked: false,
			block_id: null,
		},
		{
			ip: '198.51.100.24',
			sites: 4,
			country: 'US',
			country_name: 'United States',
			blocked: true,
			block_id: 3,
		},
	],
} );

/** @return {Array<Object>} network/blocks answer. */
export const blocks = () => [
	{
		id: 3,
		ip: '198.51.100.24',
		type: 'permanent',
		start: '2026-10-20T10:00:00Z',
		end: null,
		reason: 'Seen on several sites',
		date_added: '2026-10-20T10:00:00',
		active: true,
		added_by: 'bmarshall',
	},
];
