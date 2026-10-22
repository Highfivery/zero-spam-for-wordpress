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
			await expect( login.error ).toContainText( 'Your IP has been flagged as spam/malicious.' );
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
} );
