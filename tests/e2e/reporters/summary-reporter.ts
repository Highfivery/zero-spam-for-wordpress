/**
 * Writes <artifacts>/summary.json (for agents) and summary.md (for the PR) for
 * one target: environment, counts, accessibility per screen, known defects
 * still reproducing, blocked external requests and the gallery.
 */

import type { FullConfig, FullResult, Reporter, Suite, TestCase, TestResult } from '@playwright/test/reporter';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { basename, join } from 'node:path';

import type { ScreenA11yResult } from '../support/a11y';
import { ROOT, resolveTarget, type Target } from '../support/targets';

interface Row {
	title: string;
	project: string;
	status: 'passed' | 'failed' | 'expected-fail' | 'flaky' | 'skipped' | 'fixed';
	reason?: string;
	error?: string;
	/** First line of the last attempt's error. */
	errorLine?: string;
	/** Error of the first attempt (what made a flaky test retry). */
	firstError?: string;
	durationMs: number;
}

/**
 * An error message as one line of plain text.
 *
 * @param message Error message.
 * @param lines   How many lines to keep.
 */
function oneLine( message: string | undefined, lines: number ): string | undefined {
	return message
		?.replace( /\u001b\[[0-9;]*m/g, '' )
		.split( '\n' )
		.map( ( l ) => l.trim() )
		.filter( Boolean )
		.slice( 0, lines )
		.join( ' ' );
}

export default class SummaryReporter implements Reporter {
	private target: Target;
	private rows: Row[] = [];
	private a11y: ScreenA11yResult[] = [];
	private blocked = new Map< string, number >();
	private gallery: Array< { file: string; caption: string; test: string } > = [];
	private started = Date.now();
	private env: Record< string, unknown > = {};
	private root: Suite | null = null;

	constructor() {
		this.target = resolveTarget();
	}

	onBegin( config: FullConfig, suite: Suite ): void {
		this.root = suite;
		this.started = Date.now();
		this.env.playwright = config.version;
	}

	onTestEnd( test: TestCase, result: TestResult ): void {
		for ( const a of result.attachments ) {
			if ( 'zs-a11y' === a.name && a.body ) {
				const r = JSON.parse( a.body.toString() ) as ScreenA11yResult;
				this.a11y = this.a11y.filter( ( x ) => x.screen !== r.screen );
				this.a11y.push( r );
			}
			if ( 'zs-blocked-requests' === a.name && a.body ) {
				for ( const b of JSON.parse( a.body.toString() ) as Array< { host: string; served: string } > ) {
					const key = `${ b.host } (${ b.served })`;
					this.blocked.set( key, ( this.blocked.get( key ) ?? 0 ) + 1 );
				}
			}
			if ( 'zs-env' === a.name && a.body ) {
				Object.assign( this.env, JSON.parse( a.body.toString() ) );
			}
			if ( a.name.startsWith( 'gallery:' ) && a.path ) {
				this.gallery.push( { file: basename( a.path ), caption: a.name.slice( 'gallery:'.length ), test: test.title } );
			}
		}
	}

	onEnd( result: FullResult ): void {
		// Final outcome per test (last attempt), using Playwright's own verdict.
		if ( this.root ) {
			this.collect( this.root );
		}
		this.writeFiles( result );
	}

	printsToStdio(): boolean {
		return false;
	}

	private collect( suite: Suite ): void {
		for ( const test of suite.allTests() ) {
			const project = test.parent.project()?.name ?? '';
			const last = test.results[ test.results.length - 1 ];
			const outcome = test.outcome();
			const failAnnotation = test.annotations.find( ( a ) => 'fail' === a.type );
			let status: Row[ 'status' ];
			if ( 'skipped' === outcome ) {
				status = 'skipped';
			} else if ( 'flaky' === outcome ) {
				status = 'flaky';
			} else if ( 'expected' === outcome ) {
				status = 'failed' === test.expectedStatus ? 'expected-fail' : 'passed';
			} else {
				status = 'failed' === test.expectedStatus ? 'fixed' : 'failed';
			}
			this.rows.push( {
				title: test.titlePath().slice( 2 ).join( ' › ' ),
				project,
				status,
				reason: failAnnotation?.description,
				error: oneLine( last?.error?.message, 4 ),
				errorLine: oneLine( last?.error?.message, 1 ),
				firstError: oneLine( test.results[ 0 ]?.error?.message, 2 ),
				durationMs: test.results.reduce( ( s, r ) => s + r.duration, 0 ),
			} );
		}
	}

	private writeFiles( result: FullResult ): void {
		const t = this.target;
		const counts = { passed: 0, failed: 0, 'expected-fail': 0, flaky: 0, skipped: 0, fixed: 0 } as Record< Row[ 'status' ], number >;
		for ( const r of this.rows ) {
			counts[ r.status ]++;
		}
		let commit = '';
		try {
			commit = execFileSync( 'git', [ 'rev-parse', '--short', 'HEAD' ], { cwd: ROOT, encoding: 'utf8' } ).trim();
		} catch {
			commit = 'unknown';
		}
		const summary = {
			target: t.name,
			baseURL: t.baseURL,
			wpLine: t.wp,
			commit,
			date: new Date().toISOString(),
			status: result.status,
			durationMs: Date.now() - this.started,
			env: this.env,
			counts,
			tests: this.rows,
			a11y: this.a11y.sort( ( a, b ) => a.screen.localeCompare( b.screen ) ),
			blockedRequests: Object.fromEntries( this.blocked ),
			gallery: this.gallery,
		};
		mkdirSync( t.artifacts, { recursive: true } );
		writeFileSync( join( t.artifacts, 'summary.json' ), JSON.stringify( summary, null, 2 ) );
		writeFileSync( join( t.artifacts, 'summary.md' ), renderTargetMarkdown( summary ) );
		writeGalleryIndex( t.artifacts, this.gallery );
	}

}

/**
 * Markdown for one target.
 *
 * @param s Summary object.
 */
export function renderTargetMarkdown( s: Record< string, any > ): string {
	const env = s.env || {};
	const c = s.counts;
	const lines: string[] = [];
	lines.push( `### ${ s.target } — ${ 'passed' === s.status ? 'PASS' : 'FAIL' }` );
	lines.push( '' );
	lines.push( '| WordPress | PHP | Passed | Failed | Expected fail (known bugs) | Flaky | Skipped | Fixed (remove test.fail) | Duration |' );
	lines.push( '|---|---|---|---|---|---|---|---|---|' );
	lines.push( `| ${ env.wp ?? '?' } | ${ env.php ?? '?' } | ${ c.passed } | ${ c.failed } | ${ c[ 'expected-fail' ] } | ${ c.flaky } | ${ c.skipped } | ${ c.fixed } | ${ Math.round( s.durationMs / 1000 ) } s |` );
	lines.push( '' );

	const failed = s.tests.filter( ( r: Row ) => 'failed' === r.status || 'fixed' === r.status );
	if ( failed.length ) {
		lines.push( '**Failures**' );
		lines.push( '' );
		for ( const r of failed ) {
			lines.push( `- [${ r.project }] ${ r.title }: ${ 'fixed' === r.status ? 'passed although marked test.fail — the bug looks fixed; remove the marker' : r.error ?? '' }` );
		}
		lines.push( '' );
	}

	const flaky = s.tests.filter( ( r: Row ) => 'flaky' === r.status );
	if ( flaky.length ) {
		lines.push( '**Flaky** (failed, then passed on retry; first attempt\'s error)' );
		lines.push( '' );
		for ( const r of flaky ) {
			lines.push( `- [${ r.project }] ${ r.title }: ${ r.firstError ?? '(no error recorded)' }` );
		}
		lines.push( '' );
	}

	const known = s.tests.filter( ( r: Row ) => 'expected-fail' === r.status );
	if ( known.length ) {
		lines.push( '**Known defects still reproducing** (reason — how it failed)' );
		lines.push( '' );
		for ( const r of known ) {
			lines.push( `- ${ r.title } — ${ r.reason ?? '' }${ r.errorLine ? ` — \`${ r.errorLine.replace( /`/g, "'" ) }\`` : '' }` );
		}
		lines.push( '' );
	}

	if ( s.a11y.length ) {
		lines.push( '**Accessibility per screen** (gating = critical/serious WCAG A/AA not allowlisted)' );
		lines.push( '' );
		lines.push( '| Screen | Critical | Serious | Moderate | Minor | AAA | Best practice | Allowlisted | Manual review |' );
		lines.push( '|---|---|---|---|---|---|---|---|---|' );
		for ( const a of s.a11y as ScreenA11yResult[] ) {
			const k = a.counts;
			lines.push( `| ${ a.screen } | ${ k.critical } | ${ k.serious } | ${ k.moderate } | ${ k.minor } | ${ k.aaa } | ${ k.bestPractice } | ${ k.allowlisted } | ${ k.incomplete } |` );
		}
		lines.push( '' );
		const lower = ( s.a11y as ScreenA11yResult[] ).flatMap( ( a ) => ( a.lowerCount ?? [] ).map( ( u ) => ( { screen: a.screen, ...u } ) ) );
		if ( lower.length ) {
			lines.push( '**Allowlist counts to lower** (fewer nodes than recorded; lower `count` in tests/e2e/a11y/allowlist.json)' );
			lines.push( '' );
			for ( const u of lower ) {
				lines.push( `- ${ u.screen } / ${ u.rule }: ${ u.matched } (count ${ u.count })` );
			}
			lines.push( '' );
		}
	}

	const blocked = Object.entries( s.blockedRequests || {} );
	if ( blocked.length ) {
		lines.push( '**External requests blocked in the browser** (no external CDN assets in admin, AGENTS.md §4)' );
		lines.push( '' );
		for ( const [ host, n ] of blocked ) {
			lines.push( `- ${ host }: ${ n }` );
		}
		lines.push( '' );
	}

	if ( s.gallery?.length ) {
		lines.push( `Gallery: ${ s.gallery.length } screenshots in \`artifacts/e2e/…/${ s.target }/gallery/index.html\`` );
		lines.push( '' );
	}

	return lines.join( '\n' );
}

/**
 * gallery/index.html for a target.
 *
 * @param dir   Artifacts directory.
 * @param items Gallery items.
 */
function writeGalleryIndex( dir: string, items: Array< { file: string; caption: string; test: string } > ): void {
	const galleryDir = join( dir, 'gallery' );
	if ( ! items.length || ! existsSync( galleryDir ) ) {
		return;
	}
	const esc = ( v: string ) => v.replace( /[&<>"]/g, ( ch ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' } )[ ch ]! );
	const figures = items
		.map( ( g, i ) => `<figure><img src="${ esc( g.file ) }" alt="${ esc( g.caption ) }" loading="lazy"><figcaption>${ String( i + 1 ).padStart( 2, '0' ) }. ${ esc( g.test ) } — ${ esc( g.caption ) }</figcaption></figure>` )
		.join( '\n' );
	writeFileSync(
		join( galleryDir, 'index.html' ),
		`<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Zero Spam e2e gallery</title><style>body{font:14px system-ui;margin:2rem;max-width:1400px}figure{margin:0 0 2rem}img{max-width:100%;border:1px solid #ccc}</style></head><body><h1>Zero Spam e2e gallery</h1>${ figures }</body></html>`
	);
}
