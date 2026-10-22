/**
 * Merges the per-target summaries of a run into one Markdown summary for the
 * PR description.
 *
 * Usage: node tests/e2e/bin/summarize.mjs [artifacts/e2e/latest]
 */

import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { ROOT } from './lib.mjs';

/** Total size allowed for the committed visual baselines (AGENTS.md §5). */
export const BASELINE_BUDGET_MB = 15;

/**
 * Builds the combined summary of a run directory.
 *
 * @param {string} runDir Run directory.
 * @return {{markdown: string, totals: string, targets: Object[], overBudget: boolean, baselineMB: string, budgetMB: number}}
 */
export function summarizeRun( runDir ) {
	const targets = readdirSync( runDir )
		.filter( ( d ) => statSync( join( runDir, d ) ).isDirectory() && existsSync( join( runDir, d, 'summary.json' ) ) )
		.sort()
		.map( ( d ) => ( { dir: d, ...JSON.parse( readFileSync( join( runDir, d, 'summary.json' ), 'utf8' ) ) } ) );

	const sum = { passed: 0, failed: 0, 'expected-fail': 0, flaky: 0, skipped: 0, fixed: 0 };
	for ( const t of targets ) {
		for ( const k of Object.keys( sum ) ) {
			sum[ k ] += t.counts[ k ] || 0;
		}
	}
	const totals = `${ sum.passed } passed, ${ sum.failed } failed, ${ sum[ 'expected-fail' ] } expected-fail, ${ sum.flaky } flaky, ${ sum.skipped } skipped${ sum.fixed ? `, ${ sum.fixed } fixed` : '' }`;

	const first = targets[ 0 ] || {};
	const lines = [];
	lines.push( '## E2E summary' );
	lines.push( '' );
	lines.push( `Commit \`${ first.commit ?? '?' }\` · ${ first.date ?? new Date().toISOString() } · Playwright ${ first.env?.playwright ?? '?' }` );
	lines.push( '' );
	lines.push( '| Target | WordPress | PHP | Passed | Failed | Expected fail | Flaky | Skipped | Duration |' );
	lines.push( '|---|---|---|---|---|---|---|---|---|' );
	for ( const t of targets ) {
		const c = t.counts;
		lines.push( `| ${ t.dir } | ${ t.env?.wp ?? '?' } | ${ t.env?.php ?? '?' } | ${ c.passed } | ${ c.failed + ( c.fixed || 0 ) } | ${ c[ 'expected-fail' ] } | ${ c.flaky } | ${ c.skipped } | ${ Math.round( t.durationMs / 1000 ) } s |` );
	}
	lines.push( '' );
	// Visual baselines: the total must stay within the budget (the run fails
	// otherwise); images over 300 KB are only a warning.
	const shots = join( ROOT, 'tests/e2e/__screenshots__' );
	let overBudget = false;
	let baselineMB = '0.0';
	if ( existsSync( shots ) ) {
		const files = readdirSync( shots, { recursive: true } )
			.map( ( f ) => join( shots, String( f ) ) )
			.filter( ( f ) => f.endsWith( '.png' ) );
		const sizes = files.map( ( f ) => ( { f, size: statSync( f ).size } ) );
		const total = sizes.reduce( ( s, x ) => s + x.size, 0 );
		baselineMB = ( total / 1048576 ).toFixed( 1 );
		overBudget = total > BASELINE_BUDGET_MB * 1048576;
		lines.push( `Visual baselines: ${ files.length } PNGs, ${ baselineMB } MB (budget ${ BASELINE_BUDGET_MB } MB)${ overBudget ? ' — **over budget: the run fails**' : '' }.` );
		const big = sizes.filter( ( x ) => x.size > 300 * 1024 );
		if ( big.length ) {
			lines.push( `Warning: ${ big.length } baseline(s) over 300 KB: ${ big.map( ( x ) => `${ x.f.slice( shots.length + 1 ) } (${ Math.round( x.size / 1024 ) } KB)` ).join( ', ' ) }.` );
		}
		lines.push( '' );
	}

	for ( const t of targets ) {
		const md = join( runDir, t.dir, 'summary.md' );
		if ( existsSync( md ) ) {
			lines.push( readFileSync( md, 'utf8' ).trim() );
			lines.push( '' );
		}
	}

	return { markdown: lines.join( '\n' ), totals, targets, overBudget, baselineMB, budgetMB: BASELINE_BUDGET_MB };
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const dir = resolve( ROOT, process.argv[ 2 ] || 'artifacts/e2e/latest' );
	if ( ! existsSync( dir ) ) {
		process.stderr.write( `No run at ${ dir }; run npm run test:e2e:wp-env first.\n` );
		process.exit( 1 );
	}
	process.stdout.write( summarizeRun( dir ).markdown + '\n' );
}
