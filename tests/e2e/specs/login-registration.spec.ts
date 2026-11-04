import { expect, test } from '../support/fixtures';
import { LoginPage, RegisterPage, honeypot } from '../support/pages/front';

test.describe( 'Login and registration protection', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'a human can log in; a bot that fills the honeypot is rejected and logged even with the right password', async ( { visitor, newRoleContext, target, zs, gallery }, testInfo ) => {
		const account = target.users.editor!;

		await test.step( 'a person logs in', async () => {
			const login = new LoginPage( visitor.page );
			await login.goto();
			await expect( honeypot( login.form ) ).toHaveCount( 1 );
			await login.login( account.username, account.password );
			await expect( visitor.page ).toHaveURL( /wp-admin/ );
			await expect( visitor.page.locator( '#wpadminbar' ) ).toBeVisible();
		} );

		await test.step( 'a bot filling the honeypot is rejected with the right password', async () => {
			const ip = `198.51.100.${ 10 + ( testInfo.retry % 10 ) }`;
			const botPage = await ( await newRoleContext( null, { ip } ) ).newPage();
			const login = new LoginPage( botPage );
			await login.goto();
			await honeypot( login.form ).evaluate( ( el: HTMLInputElement ) => ( el.value = 'filled-by-bot' ) );
			await login.login( account.username, account.password );
			await expect( login.error ).toContainText( 'This looks like spam, so it wasn' );
			await expect( botPage.locator( '#wpadminbar' ) ).toHaveCount( 0 );
			await gallery.capture( botPage, 'bot login rejected' );
			const rows = await zs.logRows( { ip, type: 'login' } );
			expect( rows.map( ( r ) => r.submission_data?.failed ) ).toContain( 'honeypot' );
		} );
	} );

	test( 'a human can register; a bot that fills the honeypot is rejected and logged', { tag: '@single-only' }, async ( { visitor, newRoleContext, zs, wpcli, gallery }, testInfo ) => {
		const suffix = `${ Date.now().toString( 36 ) }${ testInfo.retry }`;

		await test.step( 'a person registers', async () => {
			const register = new RegisterPage( visitor.page );
			await register.goto();
			await expect( honeypot( register.form ) ).toHaveCount( 1 );
			await register.register( `e2ehuman${ suffix }`, `e2ehuman${ suffix }@example.com` );
			await expect( visitor.page ).toHaveURL( /checkemail=registered/ );
			await gallery.capture( visitor.page, 'registration complete' );
			wpcli.exec( [ 'user', 'delete', `e2ehuman${ suffix }`, '--yes' ] );
		} );

		await test.step( 'a bot filling the honeypot is rejected', async () => {
			const ip = `198.51.100.${ 30 + ( testInfo.retry % 10 ) }`;
			const botPage = await ( await newRoleContext( null, { ip } ) ).newPage();
			const register = new RegisterPage( botPage );
			await register.goto();
			await honeypot( register.form ).evaluate( ( el: HTMLInputElement ) => ( el.value = 'filled-by-bot' ) );
			await register.register( `e2ebot${ suffix }`, `e2ebot${ suffix }@example.com` );
			await expect( botPage.locator( '#login_error' ) ).toBeVisible();
			await expect( botPage ).not.toHaveURL( /checkemail=registered/ );
			const rows = await zs.logRows( { ip, type: 'registration' } );
			expect( rows.map( ( r ) => r.submission_data?.failed ) ).toContain( 'honeypot' );
			expect( wpcli.run( [ 'user', 'get', `e2ebot${ suffix }` ] ).status ).not.toBe( 0 );
		} );
	} );

	test( 'Stop Forum Spam: a listed email is refused with the configured message and logged; a clean one, or a listed username, registers', { tag: '@single-only' }, async ( { newRoleContext, zs, wpcli, gallery }, testInfo ) => {
		const suffix = `${ Date.now().toString( 36 ) }${ testInfo.retry }`;
		const message = 'Stop Forum Spam e2e: not today.';
		await zs.set( { stop_forum_spam: 'enabled', registration_spam_message: message } );

		const attempt = async ( ip: string, username: string, email: string ) => {
			const page = await ( await newRoleContext( null, { ip } ) ).newPage();
			const register = new RegisterPage( page );
			await register.goto();
			await register.register( username, email );
			return page;
		};

		await test.step( 'a clean registration goes through', async () => {
			const page = await attempt( '203.0.113.60', `e2esfsclean${ suffix }`, `e2esfsclean${ suffix }@example.com` );
			await expect( page ).toHaveURL( /checkemail=registered/ );
			expect( await zs.logRows( { ip: '203.0.113.60' } ) ).toEqual( [] );
			wpcli.exec( [ 'user', 'delete', `e2esfsclean${ suffix }`, '--yes' ] );
		} );

		await test.step( 'a listed email is refused', async () => {
			const ip = '203.0.113.61';
			const username = `e2esfsmail${ suffix }`;
			const page = await attempt( ip, username, `e2e-sfs-listed-${ suffix }@example.com` );
			await expect( page.locator( '#login_error' ) ).toContainText( message );
			await expect( page ).not.toHaveURL( /checkemail=registered/ );
			await gallery.capture( page, 'stop forum spam refuses a listed email' );
			const rows = await zs.logRows( { ip, type: 'registration' } );
			expect( rows.map( ( r ) => r.submission_data?.failed ) ).toEqual( [ 'stop_forum_spam_email' ] );
			expect( wpcli.run( [ 'user', 'get', username ] ).status ).not.toBe( 0 );
		} );

		await test.step( 'a listed username with a clean email and IP registers: usernames are not looked up', async () => {
			const ip = '203.0.113.62';
			const username = `e2esfslisted${ suffix }`;
			const page = await attempt( ip, username, `e2esfsname${ suffix }@example.com` );
			await expect( page ).toHaveURL( /checkemail=registered/ );
			expect( await zs.logRows( { ip } ) ).toEqual( [] );
			wpcli.exec( [ 'user', 'delete', username, '--yes' ] );
		} );

		await test.step( 'a listed IP is stopped by the access check before the form', async () => {
			const ip = '198.51.100.66';
			const page = await ( await newRoleContext( null, { ip } ) ).newPage();
			const response = await page.goto( 'wp-login.php?action=register' );
			expect( response?.status() ).toBe( 403 );
			await expect( page.locator( '#registerform' ) ).toHaveCount( 0 );
			const rows = await zs.logRows( { ip } );
			expect( rows.map( ( r ) => r.submission_data?.failed ) ).toContain( 'stop_forum_spam' );
		} );
	} );
} );
