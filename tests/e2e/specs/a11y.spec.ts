/**
 * Accessibility scan of every screen in support/screens.ts (policy in
 * support/a11y.ts), plus a 320px reflow check (WCAG 1.4.10).
 */

import { describeFailures, scanScreen } from '../support/a11y';
import { expect, test } from '../support/fixtures';
import { changesState, openScreen, prepareState } from '../support/screen-runner';
import { screensFor, type ScreenState } from '../support/screens';
import { resolveTarget } from '../support/targets';

const target = resolveTarget();
const screens = screensFor( target );

for ( const state of [ 'empty', 'seeded' ] as ScreenState[] ) {
	test.describe( `Accessibility (${ state })`, () => {
		test.beforeAll( async ( { zs } ) => {
			await prepareState( zs, state );
		} );

		for ( const screen of screens.filter( ( s ) => s.state === state ) ) {
			test( `${ screen.title } has no critical or serious WCAG A/AA violations`, async ( { page, zs, newRoleContext, gallery }, testInfo ) => {
				const shown = await openScreen( screen, { page, zs, target, newRoleContext }, testInfo );
				const result = await scanScreen( shown, screen, target, testInfo );
				await gallery.capture( shown, screen.title );
				if ( changesState( screen ) ) {
					await prepareState( zs, state );
				}
				if ( '1' !== process.env.E2E_A11Y_BASELINE ) {
					expect( result.gating.length + result.staleAllowlist.length, describeFailures( result ) ).toBe( 0 );
				}
			} );
		}
	} );
}

test.describe( 'Reflow at 320px (WCAG 1.4.10)', () => {
	test.beforeAll( async ( { zs } ) => {
		await prepareState( zs, 'seeded' );
	} );

	// The plugin header's 250px logo (includes/templates/admin-header.php) makes every
	// screen that includes it 378px wide at 320px; WordPress's own screens fit.
	const HEADER_OVERFLOW = 'AGENTS.md §8: the admin header logo (<img width="250"> in includes/templates/admin-header.php) overflows a 320px viewport (page 378px wide); core screens fit';
	// The Dashboard has more than the logo: without it the page is still 329px wide.
	const REASONS: Record< string, string > = {
		'dashboard-reports-seeded': `${ HEADER_OVERFLOW }; with the logo fixed the Dashboard still overflows (329px) through .zerospam-block--review, the pie and line chart blocks and canvas#zerospam-bar-chart`,
	};
	const reflowScreens = screens.filter( ( s ) => [ 'settings-comments', 'dashboard-reports-seeded', 'dashboard-log-seeded', 'network-settings' ].includes( s.id ) );
	for ( const screen of reflowScreens ) {
		test( `${ screen.title } fits a 320px-wide viewport without horizontal scrolling`, async ( { page, zs, newRoleContext, gallery }, testInfo ) => {
			test.fail( 'admin' === screen.area, REASONS[ screen.id ] ?? HEADER_OVERFLOW );
			const shown = await openScreen( screen, { page, zs, target, newRoleContext }, testInfo, { width: 320, height: 800 } );
			await gallery.capture( shown, `${ screen.title } at 320px` );
			const overflow = await shown.evaluate( () => {
				const vw = window.innerWidth;
				const culprits = Array.from( document.querySelectorAll( '#wpbody-content *' ) )
					.filter( ( el ) => {
						const r = el.getBoundingClientRect();
						return r.width > 0 && r.right > vw + 1;
					} )
					.slice( 0, 5 )
					.map( ( el ) => `${ el.tagName.toLowerCase() }${ el.id ? '#' + el.id : '' }${ el.getAttribute( 'class' ) ? '.' + el.getAttribute( 'class' )!.trim().split( /\s+/ ).join( '.' ) : '' }${ el.getAttribute( 'width' ) ? `[width=${ el.getAttribute( 'width' ) }]` : '' }` );
				return { scroll: document.documentElement.scrollWidth, viewport: vw, culprits };
			} );
			testInfo.annotations.push( { type: 'reflow', description: JSON.stringify( overflow ) } );
			expect( overflow.scroll, `page width ${ overflow.scroll }px at a ${ overflow.viewport }px viewport; wider than the viewport: ${ overflow.culprits.join( ', ' ) }` ).toBeLessThanOrEqual( overflow.viewport + 1 );
		} );
	}
} );
