/**
 * The Enhanced Protection license on the Protection page (pure; unit
 * tested): the panel's facts, the network line, and what entering or
 * removing a key leads to. No prices, quotas or billing links (6.0 product
 * rules); renewal and sites are shown only when the license answer has them.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ago, day, num } from '../data/format';

/** License statuses with which the key works (License_Status). */
export const WORKING = [ 'valid', 'quota_exceeded', 'unreachable' ];

/**
 * The facts of the license panel.
 *
 * @param {Object} license license route answer.
 * @return {Array<[string, string, boolean?]>} Label, value and whether it is monospaced.
 */
export function licenseRows( license ) {
	if ( ! license ) {
		return [];
	}
	const rows = [
		[
			__( 'License key', 'zero-spam' ),
			license.key?.hint || ( license.key?.set ? '••••' : '' ),
			true,
		],
	];
	const plan = license.plan?.name || license.plan?.id;
	if ( plan ) {
		rows.push( [ __( 'Plan', 'zero-spam' ), plan ] );
	}
	if ( license.renewal?.date ) {
		rows.push( [
			__( 'Renews', 'zero-spam' ),
			day( license.renewal.date ),
		] );
	}
	const sites = Number( license.sites?.active ?? license.sites );
	if ( Number.isFinite( sites ) && sites > 0 ) {
		rows.push( [
			__( 'Sites', 'zero-spam' ),
			sprintf(
				/* translators: %s: number of sites */
				_n( '%s activated', '%s activated', sites, 'zero-spam' ),
				num( sites )
			),
		] );
	}
	if ( 'site' !== license.key?.source && license.key?.set ) {
		rows.push( [
			__( 'Set in', 'zero-spam' ),
			'constant' === license.key.source
				? __( 'wp-config.php', 'zero-spam' )
				: __( 'Network settings', 'zero-spam' ),
		] );
	}
	return rows.filter( ( row ) => '' !== row[ 1 ] );
}

/**
 * The live network line: connected (with the last self-test's latency and
 * when the license was checked), or what is wrong.
 *
 * @param {Object}      license license route answer.
 * @param {number|null} latency Milliseconds of the last self-test lookup.
 * @param {number}      [nowMs] Now.
 * @return {{tone: string, text: string}} Line.
 */
export function networkLine( license, latency, nowMs = Date.now() ) {
	if ( 'unreachable' === license?.status ) {
		return {
			tone: 'warn',
			text: __(
				"Zero Spam's network can't be reached right now. Your site stays protected by everything else.",
				'zero-spam'
			),
		};
	}
	const parts = [ __( 'Network connected', 'zero-spam' ) ];
	if ( null !== latency && undefined !== latency ) {
		/* translators: %s: milliseconds */
		parts.push( sprintf( __( '%s ms', 'zero-spam' ), num( latency ) ) );
	}
	if ( license?.checked_at ) {
		parts.push(
			sprintf(
				/* translators: %s: relative time, such as 5 min ago */
				__( 'last checked %s', 'zero-spam' ),
				ago(
					Math.max(
						0,
						Math.floor(
							( nowMs - license.checked_at * 1000 ) / 60000
						)
					)
				).toLowerCase()
			)
		);
	}
	if ( 'quota_exceeded' === license?.status ) {
		return {
			tone: 'warn',
			text: __(
				"Your plan's lookups for this period are used up. Basic protection keeps working until they reset.",
				'zero-spam'
			),
		};
	}
	return { tone: 'ok', text: parts.join( ' · ' ) };
}

/**
 * What a saved key (PUT license) means for the page.
 *
 * @param {Object} answer license route answer.
 * @return {{working: boolean, enable: boolean, message: string}} Result.
 */
export function keySaved( answer ) {
	const working = WORKING.includes( answer?.status );
	const plan = answer?.plan?.name;
	let message;
	if ( 'unreachable' === answer?.status ) {
		message = __(
			"Your license key is saved. Zero Spam's network couldn't be reached to check it; it is checked again shortly.",
			'zero-spam'
		);
	} else if ( plan ) {
		message = sprintf(
			/* translators: %s: plan name, such as Business */
			__(
				'License verified · %s plan. Enhanced Protection is on.',
				'zero-spam'
			),
			plan
		);
	} else {
		message = __(
			'License verified. Enhanced Protection is on.',
			'zero-spam'
		);
	}
	return { working, enable: working && ! answer?.enabled, message };
}

/**
 * The message of a failed key change.
 *
 * @param {Object} error apiFetch error.
 * @return {string} Message.
 */
export function keyError( error ) {
	const params = error?.data?.params;
	if ( params?.license_key ) {
		return String( params.license_key );
	}
	if ( 'rest_setting_locked' === error?.code ) {
		return __(
			'The license key is managed by your network administrator.',
			'zero-spam'
		);
	}
	return (
		error?.message ||
		__(
			'The license key could not be saved. Please try again.',
			'zero-spam'
		)
	);
}

/**
 * Whether this site can remove the key itself (not one from wp-config.php
 * or the network).
 *
 * @param {Object} license license route answer.
 * @return {boolean} Removable.
 */
export const canRemoveKey = ( license ) =>
	Boolean( license?.key?.set ) && 'site' === license.key.source;
