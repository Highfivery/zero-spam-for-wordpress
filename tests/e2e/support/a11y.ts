/**
 * Accessibility scans with axe-core and the project's policy (AGENTS.md §5).
 *
 * Fails the test:
 * - a critical or serious violation of a WCAG 2.0–2.2 A/AA rule that is not
 *   in tests/e2e/a11y/allowlist.json;
 * - an allowlist entry for this screen that no longer matches anything
 *   (the list can only shrink, like the PHPStan baseline);
 * - an allowlist entry with an empty or TODO reason, or a "debt" entry
 *   without an issue.
 *
 * Reported only: moderate and minor violations, AAA-only rules,
 * best-practice rules and axe "incomplete" results (manual review).
 *
 * With E2E_A11Y_BASELINE=1 nothing fails; proposed allowlist entries for the
 * gating violations are written to <artifacts>/a11y-baseline/<screen>.json so
 * the first baseline can be reviewed and given real reasons.
 */

import AxeBuilder from '@axe-core/playwright';
import type { Page, TestInfo } from '@playwright/test';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import type { Screen } from './screens';
import { E2E_DIR, type Target } from './targets';

export const AXE_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'wcag2aaa', 'best-practice' ];
const AA_TAGS = new Set( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa' ] );

/** Core and other-plugin chrome around the plugin's content. */
const ADMIN_EXCLUDES = [ '#wpadminbar', '#adminmenumain', '#wpfooter', '#query-monitor-main', '#screen-meta', '#screen-meta-links' ];

export interface AllowlistEntry {
	screen: string;
	rule: string;
	/** axe target selector of the node (node.target joined with " "), or "*" for every node of the rule on the screen. */
	target: string;
	kind: 'external' | 'debt';
	reason: string;
	issue?: string;
	added: string;
	/** Optional: only on these targets. */
	targets?: string[];
}

export interface ScreenA11yResult {
	screen: string;
	title: string;
	counts: { critical: number; serious: number; moderate: number; minor: number; aaa: number; bestPractice: number; incomplete: number; allowlisted: number };
	gating: Array< { rule: string; impact: string; target: string; help: string } >;
	reported: Array< { rule: string; impact: string; target: string; help: string; kind: 'aa' | 'aaa' | 'best-practice' } >;
	incomplete: Array< { rule: string; target: string; help: string } >;
	staleAllowlist: AllowlistEntry[];
}

/**
 * Loads and validates the allowlist.
 */
export function loadAllowlist(): AllowlistEntry[] {
	const entries = JSON.parse( readFileSync( join( E2E_DIR, 'a11y/allowlist.json' ), 'utf8' ) ) as AllowlistEntry[];
	const problems: string[] = [];
	entries.forEach( ( e, i ) => {
		const where = `allowlist.json entry ${ i } (${ e.screen } / ${ e.rule })`;
		if ( ! e.reason || /\bTODO\b/i.test( e.reason ) ) {
			problems.push( `${ where }: needs a real reason (empty or TODO).` );
		}
		if ( 'debt' === e.kind && ( ! e.issue || /\bTODO\b/i.test( e.issue ) ) ) {
			problems.push( `${ where }: a "debt" entry needs an issue.` );
		}
		if ( 'debt' !== e.kind && 'external' !== e.kind ) {
			problems.push( `${ where }: kind must be "debt" or "external".` );
		}
		if ( ! e.screen || ! e.rule || ! e.target ) {
			problems.push( `${ where }: screen, rule and target are required.` );
		}
	} );
	if ( problems.length ) {
		throw new Error( problems.join( '\n' ) );
	}
	return entries;
}

/**
 * Scans the current page and applies the policy.
 *
 * @param page     Page showing the screen.
 * @param screen   Screen.
 * @param target   Target.
 * @param testInfo Test info (results are attached for the summary).
 */
export async function scanScreen( page: Page, screen: Screen, target: Target, testInfo: TestInfo ): Promise< ScreenA11yResult > {
	const allowlist = loadAllowlist().filter( ( e ) => e.screen === screen.id && ( ! e.targets || e.targets.includes( target.name ) ) );

	let builder = new AxeBuilder( { page } ).withTags( AXE_TAGS );
	if ( screen.include?.length ) {
		for ( const sel of screen.include ) {
			builder = builder.include( sel );
		}
	} else if ( 'front' !== screen.area ) {
		builder = builder.include( '#wpbody-content' );
	}
	if ( 'front' !== screen.area ) {
		for ( const sel of ADMIN_EXCLUDES ) {
			builder = builder.exclude( sel );
		}
		if ( 'local' === target.kind ) {
			// Other plugins' notices on the developer's Local sites.
			builder = builder.exclude( '.notice:not([id^="zerospam"]):not(.zerospam-network-notice)' );
		}
	}

	const results = await builder.analyze();
	const result: ScreenA11yResult = {
		screen: screen.id,
		title: screen.title,
		counts: { critical: 0, serious: 0, moderate: 0, minor: 0, aaa: 0, bestPractice: 0, incomplete: 0, allowlisted: 0 },
		gating: [],
		reported: [],
		incomplete: [],
		staleAllowlist: [],
	};
	const used = new Set< AllowlistEntry >();
	const proposals: AllowlistEntry[] = [];

	for ( const v of results.violations ) {
		const isAA = v.tags.some( ( t ) => AA_TAGS.has( t ) );
		const isAAA = ! isAA && v.tags.includes( 'wcag2aaa' );
		for ( const node of v.nodes ) {
			const nodeTarget = node.target.map( String ).join( ' ' );
			const impact = node.impact || v.impact || 'minor';
			const item = { rule: v.id, impact, target: nodeTarget, help: v.help };

			if ( isAAA ) {
				result.counts.aaa++;
				result.reported.push( { ...item, kind: 'aaa' } );
				continue;
			}
			if ( ! isAA ) {
				result.counts.bestPractice++;
				result.reported.push( { ...item, kind: 'best-practice' } );
				continue;
			}

			const gatingImpact = 'critical' === impact || 'serious' === impact;
			const match = allowlist.find( ( e ) => e.rule === v.id && ( '*' === e.target || e.target === nodeTarget ) );
			if ( match ) {
				used.add( match );
				result.counts.allowlisted++;
				continue;
			}
			result.counts[ impact as 'critical' | 'serious' | 'moderate' | 'minor' ]++;
			if ( gatingImpact ) {
				result.gating.push( item );
				proposals.push( {
					screen: screen.id,
					rule: v.id,
					target: nodeTarget,
					kind: 'debt',
					reason: `TODO: ${ v.help }`,
					issue: 'TODO',
					added: new Date().toISOString().slice( 0, 10 ),
				} );
			} else {
				result.reported.push( { ...item, kind: 'aa' } );
			}
		}
	}

	for ( const inc of results.incomplete ) {
		for ( const node of inc.nodes ) {
			result.counts.incomplete++;
			result.incomplete.push( { rule: inc.id, target: node.target.map( String ).join( ' ' ), help: inc.help } );
		}
	}

	result.staleAllowlist = allowlist.filter( ( e ) => ! used.has( e ) );

	await testInfo.attach( 'zs-a11y', { body: JSON.stringify( result ), contentType: 'application/json' } );

	if ( '1' === process.env.E2E_A11Y_BASELINE && proposals.length ) {
		const dir = join( target.artifacts, 'a11y-baseline' );
		mkdirSync( dir, { recursive: true } );
		writeFileSync( join( dir, `${ screen.id }.json` ), JSON.stringify( proposals, null, '\t' ) );
	}

	return result;
}

/**
 * Human-readable failure text for a result.
 *
 * @param r Result.
 */
export function describeFailures( r: ScreenA11yResult ): string {
	const lines = [
		...r.gating.map( ( g ) => `${ g.impact } ${ g.rule } at ${ g.target }: ${ g.help }` ),
		...r.staleAllowlist.map( ( e ) => `allowlist entry no longer matches (remove it): ${ e.rule } at ${ e.target }` ),
	];
	return lines.join( '\n' );
}
