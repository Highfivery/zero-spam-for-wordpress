/**
 * Visual baselines (element screenshots) for the screens in support/screens.ts.
 *
 * Baselines are compared only on wp-env with the latest WordPress
 * (ignoreSnapshots elsewhere); every target still writes the gallery.
 * Screens shared by single site and network are recorded on the single site;
 * the network target records the network-only screens.
 * Update with `npm run test:visual:update`, in a commit of its own.
 */

import { expect, test } from '../support/fixtures';
import { openScreen, prepareState } from '../support/screen-runner';
import { screensFor, type ScreenState } from '../support/screens';
import { E2E_DIR, resolveTarget } from '../support/targets';

const target = resolveTarget();
const screens = screensFor( target ).filter( ( s ) => s.visual && ( target.multisite ? 'multisite' === s.sites : true ) );
const STYLE = `${ E2E_DIR }/support/visual.css`;

for ( const state of [ 'empty', 'seeded' ] as ScreenState[] ) {
	test.describe( `Visual (${ state })`, () => {
		test.beforeAll( async ( { zs } ) => {
			await prepareState( zs, state );
		} );

		for ( const screen of screens.filter( ( s ) => s.state === state ) ) {
			const options = 'object' === typeof screen.visual ? screen.visual : {};
			const viewports: Array< { name: string; size?: { width: number; height: number } } > = [ { name: '' } ];
			if ( options.mobile ) {
				viewports.push( { name: '-782', size: { width: 782, height: 900 } } );
			}

			for ( const vp of viewports ) {
				test( `${ screen.title }${ vp.size ? ' at 782px' : '' } matches its baseline`, async ( { page, zs, newRoleContext, gallery }, testInfo ) => {
					const shown = await openScreen( screen, { page, zs, target, newRoleContext }, testInfo, vp.size );
					const subject = shown.locator( options.selector ?? '#wpbody-content' );
					await gallery.capture( subject, `${ screen.title }${ vp.name }` );
					await expect( subject ).toHaveScreenshot( `${ screen.id }${ vp.name }.png`, {
						mask: ( options.mask ?? [] ).map( ( sel ) => shown.locator( sel ) ),
						stylePath: STYLE,
					} );
					if ( screen.before ) {
						await prepareState( zs, state );
					}
				} );
			}
		}
	} );
}
