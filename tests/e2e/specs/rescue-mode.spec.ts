import { expect, test } from '../support/fixtures';

test.describe( 'Rescue mode', () => {
	test( 'the rescue URL lets a blocked visitor back in and sets a 1-hour HttpOnly, SameSite=Lax cookie; a wrong key stays blocked', async ( { visitor, zs, target } ) => {
		test.skip( ! target.rescueKey, 'ZEROSPAM_RESCUE_KEY is not defined for this target (set E2E_RESCUE_KEY on Local).' );
		await zs.reset( 'baseline' );
		await zs.block( visitor.ip, 'E2E rescue test' );

		await test.step( 'the visitor is blocked', async () => {
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 403 );
		} );

		await test.step( 'a wrong key does not help', async () => {
			expect( ( await visitor.page.goto( '?zerospam_rescue=wrong-key' ) )?.status() ).toBe( 403 );
			expect( ( await visitor.context.cookies() ).find( ( c ) => 'zerospam_rescue' === c.name ) ).toBeUndefined();
		} );

		await test.step( 'the right key lets them in and sets the cookie', async () => {
			const before = Date.now() / 1000;
			expect( ( await visitor.page.goto( `?zerospam_rescue=${ encodeURIComponent( target.rescueKey! ) }` ) )?.status() ).toBe( 200 );
			const cookie = ( await visitor.context.cookies() ).find( ( c ) => 'zerospam_rescue' === c.name );
			expect( cookie ).toBeDefined();
			expect( cookie!.httpOnly ).toBe( true );
			expect( cookie!.sameSite ).toBe( 'Lax' );
			expect( cookie!.expires - before ).toBeGreaterThan( 3600 - 120 );
			expect( cookie!.expires - before ).toBeLessThan( 3600 + 120 );
		} );

		await test.step( 'the cookie keeps them in without the key', async () => {
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 200 );
		} );
	} );
} );
