/**
 * The site's protection state, from protection/modules (pure; unit tested).
 *
 * The core entry points (comments, login, registration) decide whether the
 * site can be called protected; other warnings make it "to review".
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/** The checks every site relies on (Protection_Controller::ENTRY_POINTS). */
export const ENTRY_POINTS = [ 'comments', 'login', 'registration' ];

/**
 * @param {Object|undefined} modules protection/modules answer.
 * @return {{state: 'loading'|'off'|'partial'|'review'|'ok', tone: 'ok'|'warn', label: string, coreOn: number, coreTotal: number, count: number}} Status.
 */
export function protectionStatus( modules ) {
	const list = modules?.modules;
	if ( ! Array.isArray( list ) ) {
		return {
			state: 'loading',
			tone: 'ok',
			label: '',
			coreOn: 0,
			coreTotal: ENTRY_POINTS.length,
			count: 0,
		};
	}
	const core = list.filter( ( m ) => ENTRY_POINTS.includes( m.key ) );
	const coreOn = core.filter( ( m ) => true === m.enabled ).length;
	const coreTotal = core.length || ENTRY_POINTS.length;
	const warnings = ( modules.attention || [] ).filter(
		( a ) => 'warning' === a.severity
	);
	const base = { coreOn, coreTotal, count: warnings.length };

	if ( 0 === coreOn ) {
		return {
			...base,
			state: 'off',
			tone: 'warn',
			label: __( 'No checks active', 'zero-spam' ),
		};
	}
	if ( coreOn < coreTotal ) {
		return {
			...base,
			state: 'partial',
			tone: 'warn',
			label: __( 'Protection not fully on', 'zero-spam' ),
		};
	}
	if ( warnings.length ) {
		return {
			...base,
			state: 'review',
			tone: 'warn',
			label: sprintf(
				/* translators: %d: number of items */
				_n(
					'%d to review',
					'%d to review',
					warnings.length,
					'zero-spam'
				),
				warnings.length
			),
		};
	}
	return {
		...base,
		state: 'ok',
		tone: 'ok',
		label: __( 'All checks active', 'zero-spam' ),
	};
}
