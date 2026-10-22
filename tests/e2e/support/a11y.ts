/**
 * Accessibility scans with axe-core and the project's policy (AGENTS.md §5).
 *
 * Fails the test:
 * - a critical or serious violation of a WCAG 2.0–2.2 A/AA rule that is not
 *   in tests/e2e/a11y/allowlist.json;
 * - an allowlist entry that matches more nodes than its `count` (the number
 *   of nodes it covered when it was recorded), so a new violation of an
 *   allowlisted rule on the same screen still fails;
 * - an allowlist entry for this screen that no longer matches anything
 *   (the list can only shrink, like the PHPStan baseline);
 * - an allowlist entry with an empty or TODO reason, a "debt" entry without
 *   an issue, a `target: "*"` entry without a `count` (only `kind:
 *   "external"` may leave it out), or a `targets` field (screens are already
 *   limited to single site or network by their `sites`).
 *
 * Reported only: moderate and minor violations, AAA-only rules,
 * best-practice rules, axe "incomplete" results (manual review) and entries
 * that match fewer nodes than their count ("lower the count": counts can
 * differ between targets, so this does not fail).
 *
 * With E2E_A11Y_BASELINE=1 nothing fails and entries may lack a count;
 * proposed allowlist entries for the gating violations are written to
 * <artifacts>/a11y-baseline/<screen>.json so they can be reviewed and given
 * real reasons, and every entry's matched node count is in summary.json
 * (a11y[].allowlistUsage) for setting `count`.
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
	/**
	 * Nodes the entry covered when it was recorded. More matching nodes fail
	 * the scan; fewer print "lower the count". Required for target "*"
	 * unless kind is "external".
	 */
	count?: number;
}

export interface AllowlistUsage {
	rule: string;
	target: string;
	count: number | null;
	matched: number;
}

export interface ScreenA11yResult {
	screen: string;
	title: string;
	counts: { critical: number; serious: number; moderate: number; minor: number; aaa: number; bestPractice: number; incomplete: number; allowlisted: number };
	gating: Array< { rule: string; impact: string; target: string; help: string } >;
	reported: Array< { rule: string; impact: string; target: string; help: string; kind: 'aa' | 'aaa' | 'best-practice' } >;
	incomplete: Array< { rule: string; target: string; help: string } >;
	staleAllowlist: AllowlistEntry[];
	/** Every allowlist entry of the screen with the nodes it matched. */
	allowlistUsage: AllowlistUsage[];
	/** Entries that matched fewer nodes than their count. */
	lowerCount: AllowlistUsage[];
}

/**
 * Loads and validates the allowlist.
 */
export function loadAllowlist(): AllowlistEntry[] {
	const entries = JSON.parse( readFileSync( join( E2E_DIR, 'a11y/allowlist.json' ), 'utf8' ) ) as Array< AllowlistEntry & { targets?: unknown } >;
	const baseline = '1' === process.env.E2E_A11Y_BASELINE;
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
		if ( undefined !== e.targets ) {
			problems.push( `${ where }: "targets" is not supported; a screen already runs only where its "sites" allow.` );
		}
		if ( undefined !== e.count && ! ( Number.isInteger( e.count ) && e.count > 0 ) ) {
			problems.push( `${ where }: count must be a positive integer.` );
		}
		if ( '*' === e.target && 'external' !== e.kind && undefined === e.count && ! baseline ) {
			problems.push( `${ where }: a target "*" entry needs a count (the number of nodes it covers; see summary.json a11y[].allowlistUsage).` );
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
	const allowlist = loadAllowlist().filter( ( e ) => e.screen === screen.id );

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
		allowlistUsage: [],
		lowerCount: [],
	};
	const matched = new Map< AllowlistEntry, Array< { impact: string; target: string; help: string } > >();
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
			// An exact-target entry wins over a "*" entry of the same rule.
			const match =
				allowlist.find( ( e ) => e.rule === v.id && e.target === nodeTarget ) ?? allowlist.find( ( e ) => e.rule === v.id && '*' === e.target );
			if ( match ) {
				matched.set( match, [ ...( matched.get( match ) ?? [] ), { impact, target: nodeTarget, help: v.help } ] );
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

	for ( const entry of allowlist ) {
		const nodes = matched.get( entry ) ?? [];
		const usage: AllowlistUsage = { rule: entry.rule, target: entry.target, count: entry.count ?? null, matched: nodes.length };
		result.allowlistUsage.push( usage );
		if ( ! nodes.length ) {
			result.staleAllowlist.push( entry );
			continue;
		}
		const allowed = undefined === entry.count ? nodes.length : Math.min( entry.count, nodes.length );
		result.counts.allowlisted += allowed;
		if ( nodes.length > allowed ) {
			// More nodes than were recorded: a new violation of an allowlisted rule.
			for ( const extra of nodes.slice( allowed ) ) {
				result.counts[ extra.impact as 'critical' | 'serious' | 'moderate' | 'minor' ]++;
			}
			result.gating.push( {
				rule: entry.rule,
				impact: nodes.some( ( n ) => 'critical' === n.impact ) ? 'critical' : 'serious',
				target: `${ nodes.length } nodes where the allowlist entry (target ${ entry.target }) allows ${ entry.count }: ${ nodes.map( ( n ) => n.target ).join( ', ' ) }`,
				help: nodes[ 0 ].help,
			} );
		} else if ( undefined !== entry.count && nodes.length < entry.count ) {
			result.lowerCount.push( usage );
			const note = `${ screen.id } / ${ entry.rule }: ${ nodes.length } node(s) match, the allowlist count is ${ entry.count }: lower the count to ${ nodes.length } in tests/e2e/a11y/allowlist.json`;
			testInfo.annotations.push( { type: 'a11y-lower-count', description: note } );
			process.stdout.write( `${ note }\n` );
		}
	}

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
