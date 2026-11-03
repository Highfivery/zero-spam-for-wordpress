/**
 * A settings schema shaped like Settings_Schema::get(), with the settings
 * of the Settings area (core, API monitoring, debug), a few of other areas,
 * and one an extension adds to the Settings area.
 */

const entry = ( key, module, type, extra = {} ) => ( {
	key,
	option: `zero-spam-${ module }`,
	module,
	section: module,
	title: key,
	description: '',
	type,
	multiple: false,
	options: 'checkbox' === type ? [ { value: 'enabled', label: '' } ] : null,
	min: null,
	max: null,
	step: null,
	suffix: '',
	default: null,
	recommended: null,
	placeholder: '',
	lockable: 'html' !== type,
	secret: false,
	depends_on: null,
	area: 'settings',
	group: 'general',
	order: 0,
	locked: false,
	source: 'default',
	value: 'checkbox' === type ? false : '',
	...extra,
} );

const ALERTS = [
	'quota_warning',
	'quota_critical',
	'usage_spike',
	'high_error_rate',
	'slow_response',
];

/**
 * @param {Object} [overrides] Entries to change, by key.
 * @return {Object} settings/schema answer.
 */
export function schema( overrides = {} ) {
	const api = ( key, type, extra = {} ) =>
		entry( key, 'api-monitoring', type, { group: 'api', ...extra } );
	const settings = [
		entry( 'use_recommended_settings', 'settings', 'html' ),
		entry( 'share_data', 'settings', 'checkbox', { value: 'enabled' } ),
		entry( 'show_news', 'settings', 'checkbox', {
			title: 'Zero Spam News',
			value: 'enabled',
			default: 'enabled',
		} ),
		entry( 'widget_enabled', 'settings', 'checkbox' ),
		entry( 'widget_visibility', 'settings', 'select', {
			multiple: true,
			options: [ { value: 'administrator', label: 'Administrator' } ],
			value: [ 'administrator' ],
		} ),
		entry( 'block_handler', 'settings', 'radio', {
			options: [
				{ value: 'redirect', label: 'Redirect' },
				{ value: '403', label: 'Message' },
			],
			value: '403',
		} ),
		entry( 'block_method', 'settings', 'radio', {
			options: [ { value: 'php', label: 'PHP' } ],
			value: 'php',
		} ),
		entry( 'blocked_message', 'settings', 'text' ),
		entry( 'blocked_redirect_url', 'settings', 'url' ),
		entry( 'log_blocked_ips', 'settings', 'checkbox', {
			title: 'Log Blocked IPs',
			value: 'enabled',
		} ),
		entry( 'max_logs', 'settings', 'number', { value: 10000 } ),
		entry( 'ip_whitelist', 'settings', 'textarea', {
			area: 'rules',
			group: 'allowed-ips',
		} ),
		entry( 'trusted_proxies', 'settings', 'textarea', {
			title: 'Trusted Proxy IPs',
			group: 'proxies',
		} ),
		entry( 'blocked_email_domains', 'settings', 'textarea', {
			area: 'rules',
			group: 'email-domains',
		} ),
		entry( 'update_blocked_email_domains', 'settings', 'html' ),
		entry( 'regenerate_honeypot', 'settings', 'html' ),
		entry( 'update_disallowed_words', 'settings', 'html' ),
		entry( 'rescue_mode', 'settings', 'html', { title: 'Rescue Mode' } ),
		api( 'api_monitoring', 'checkbox' ),
		api( 'api_retention', 'number', {
			value: 90,
			min: 7,
			max: 365,
			suffix: 'days',
		} ),
		api( 'alert_email', 'checkbox' ),
		api( 'alert_admin_notice', 'checkbox' ),
		api( 'alert_webhook_url', 'url', {
			secret: true,
			value: { set: false, source: 'default' },
		} ),
		...ALERTS.flatMap( ( type ) => [
			api( `alert_${ type }`, 'checkbox' ),
			api( `threshold_${ type }`, 'number', {
				value: 80,
				min: 1,
				max: 100,
				suffix: '%',
			} ),
		] ),
		api( 'test_alerts', 'html', { title: 'Test Alerts' } ),
		api( 'quota_reset_date', 'date', { title: 'Quota Reset Date' } ),
		api( 'delete_data_on_uninstall', 'checkbox', {
			value: 'enabled',
			default: 'enabled',
		} ),
		entry( 'debug', 'debug', 'checkbox', { group: 'debug' } ),
		entry( 'debug_ip', 'debug', 'text', { group: 'debug' } ),
		entry( 'verify_comments', 'comments', 'checkbox', {
			area: 'protection',
			group: 'detection',
		} ),
		entry( 'acme_mode', 'acme', 'checkbox', {
			title: 'Acme mode',
			group: 'api',
		} ),
	].map( ( e ) =>
		overrides[ e.key ] ? { ...e, ...overrides[ e.key ] } : e
	);

	return { sections: [], settings };
}
