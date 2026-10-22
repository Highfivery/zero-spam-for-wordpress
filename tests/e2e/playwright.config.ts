/**
 * Playwright configuration for the Zero Spam e2e, accessibility and visual
 * suite. One configuration for every target; E2E_TARGET picks the site
 * (tests/e2e/support/targets.ts). Run it through tests/e2e/bin/run.mjs
 * (`npm run test:e2e:wp-env`, `npm run test:e2e:local`, …), which checks the
 * environment first and prints the summary.
 */

import { defineConfig } from '@playwright/test';
import { join } from 'node:path';

import { resolveTarget } from './support/targets';

const target = resolveTarget();

// @wordpress/e2e-test-utils-playwright reads WP_BASE_URL when it is loaded.
process.env.WP_BASE_URL = target.baseURL;

// Admin screens: the single-site screens on single site, the network screens on a network.
const grepInvert = target.multisite ? /@single-only/ : /@multisite/;

// Baselines are recorded on macOS (-darwin); other platforms render fonts
// differently, so they only write the gallery there.
const compareSnapshots = target.compareSnapshots && 'darwin' === process.platform;

export default defineConfig( {
	testDir: './specs',
	outputDir: join( target.artifacts, 'test-results' ),
	snapshotPathTemplate: '{testDir}/../__screenshots__/{projectName}/{arg}-{platform}{ext}',
	fullyParallel: false,
	workers: 1,
	retries: 'local' === target.kind ? 0 : 1,
	forbidOnly: true,
	timeout: 60_000,
	expect: {
		timeout: 10_000,
		toHaveScreenshot: { maxDiffPixelRatio: 0.002, animations: 'disabled', caret: 'hide', scale: 'css' },
	},
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: join( target.artifacts, 'report' ), open: 'never' } ],
		[ 'json', { outputFile: join( target.artifacts, 'results.json' ) } ],
		[ './reporters/summary-reporter.ts', { target: target.name } ],
	],
	use: {
		baseURL: target.baseURL,
		viewport: { width: 1280, height: 900 },
		deviceScaleFactor: 1,
		locale: 'en-US',
		timezoneId: 'UTC',
		colorScheme: 'light',
		contextOptions: { reducedMotion: 'reduce' },
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
		extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': target.token },
	},
	projects: [
		{ name: 'setup', testMatch: /auth\.setup\.ts/, teardown: 'restore' },
		{ name: 'restore', testMatch: /restore\.teardown\.ts/ },
		{
			name: 'functional',
			dependencies: [ 'setup' ],
			testIgnore: [ /a11y\.spec/, /visual\.spec/, /uninstall\.spec/ ],
			grepInvert,
		},
		{ name: 'a11y', dependencies: [ 'setup' ], testMatch: /a11y\.spec/, grepInvert },
		{
			name: 'visual',
			dependencies: [ 'setup' ],
			testMatch: /visual\.spec/,
			grepInvert,
			ignoreSnapshots: ! compareSnapshots,
		},
		{ name: 'destructive', dependencies: [ 'functional', 'a11y', 'visual' ], testMatch: /uninstall\.spec/, grepInvert },
	],
} );
