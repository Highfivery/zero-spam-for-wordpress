import { expect, test } from '../support/fixtures';
import { LoginPage, RegisterPage, SignupPage, honeypot } from '../support/pages/front';

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

	test.describe( 'A network\'s sign-up page', { tag: '@multisite' }, () => {
		let registration: { status: number; stdout: string };

		/**
		 * How many pending sign-ups WordPress stored for a username.
		 *
		 * @param wpcli      WP-CLI for the target.
		 * @param wpcli.exec Runs a command and returns its output.
		 * @param username   Username.
		 */
		const pendingSignups = ( wpcli: { exec: ( args: string[] ) => string }, username: string ): number =>
			Number( wpcli.exec( [ 'eval', `global $wpdb; echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->signups} WHERE user_login = %s", '${ username }' ) );` ] ) );

		test.beforeEach( async ( { wpcli } ) => {
			registration = wpcli.run( [ 'network', 'meta', 'get', '1', 'registration' ] );
			wpcli.exec( [ 'network', 'meta', 'update', '1', 'registration', 'all' ] );
		} );

		test.afterEach( async ( { wpcli } ) => {
			wpcli.exec( [ 'eval', `global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->signups} WHERE user_login LIKE 'e2esignup%'" );` ] );
			if ( 0 === registration.status && '' !== registration.stdout.trim() ) {
				wpcli.exec( [ 'network', 'meta', 'update', '1', 'registration', registration.stdout.trim() ] );
			} else {
				wpcli.run( [ 'network', 'meta', 'delete', '1', 'registration' ] );
			}
		} );

		test( 'people sign up, for a username or a site; bots are refused and logged', async ( { visitor, newRoleContext, zs, wpcli, gallery }, testInfo ) => {
			const suffix = `${ Date.now().toString( 36 ) }${ testInfo.retry }`;
			const message = 'Sign-up e2e: not today.';
			await zs.set( { registration_spam_message: message } );

			await test.step( 'a person signs up for a username', async () => {
				const username = `e2esignupa${ suffix }`;
				const signup = new SignupPage( visitor.page );
				await signup.goto();
				await expect( honeypot( signup.form ) ).toHaveCount( 1 );
				await signup.signUp( username, `${ username }@example.com` );
				await expect( visitor.page.locator( 'body' ) ).toContainText( `${ username } is your new username` );
				await gallery.capture( visitor.page, 'network sign-up complete' );
				expect( pendingSignups( wpcli, username ) ).toBe( 1 );
			} );

			await test.step( 'a person signs up for a site: the second step carries the checks too', async () => {
				const username = `e2esignupb${ suffix }`;
				const page = await ( await newRoleContext( null, { ip: '203.0.113.70' } ) ).newPage();
				const signup = new SignupPage( page );
				await signup.goto();
				await signup.signUp( username, `${ username }@example.com`, 'blog' );
				await expect( signup.form.locator( '#blogname' ) ).toBeVisible();
				await expect( honeypot( signup.form ) ).toHaveCount( 1 );
				await signup.form.locator( '#blogname' ).click();
				await signup.form.locator( '#blogname' ).fill( username );
				await signup.form.locator( '#blog_title' ).fill( 'E2E sign-up' );
				await signup.submit();
				await expect( page.locator( 'body' ) ).toContainText( 'is almost ready' );
				expect( pendingSignups( wpcli, username ) ).toBe( 1 );
				expect( await zs.logRows( { ip: '203.0.113.70' } ) ).toEqual( [] );
			} );

			await test.step( 'a bot filling the honeypot is refused', async () => {
				const ip = '198.51.100.70';
				const username = `e2esignupc${ suffix }`;
				const page = await ( await newRoleContext( null, { ip } ) ).newPage();
				const signup = new SignupPage( page );
				await signup.goto();
				await honeypot( signup.form ).evaluate( ( el: HTMLInputElement ) => ( el.value = 'filled-by-bot' ) );
				await signup.signUp( username, `${ username }@example.com` );
				await expect( signup.error ).toHaveText( message );
				await gallery.capture( page, 'network sign-up refused' );
				const rows = await zs.logRows( { ip, type: 'registration' } );
				expect( rows.map( ( r ) => r.submission_data?.failed ) ).toEqual( [ 'honeypot' ] );
				expect( pendingSignups( wpcli, username ) ).toBe( 0 );
			} );

			await test.step( 'a browser without the David Walsh script is refused', async () => {
				const ip = '198.51.100.71';
				const username = `e2esignupd${ suffix }`;
				const page = await ( await newRoleContext( null, { ip } ) ).newPage();
				await page.route( '**/davidwalsh.js*', ( route ) => route.abort() );
				const signup = new SignupPage( page );
				await signup.goto();
				await signup.signUp( username, `${ username }@example.com` );
				await expect( signup.error ).toHaveText( message );
				const rows = await zs.logRows( { ip, type: 'registration' } );
				expect( rows.map( ( r ) => r.submission_data?.failed ) ).toEqual( [ 'david_walsh' ] );
				expect( pendingSignups( wpcli, username ) ).toBe( 0 );
			} );

			await test.step( 'a blocked email domain is refused', async () => {
				const ip = '198.51.100.72';
				const username = `e2esignupe${ suffix }`;
				await zs.set( { blocked_email_domains: 'example.org' } );
				const page = await ( await newRoleContext( null, { ip } ) ).newPage();
				const signup = new SignupPage( page );
				await signup.goto();
				await signup.signUp( username, `${ username }@example.org` );
				await expect( signup.error ).toHaveText( message );
				const rows = await zs.logRows( { ip, type: 'registration' } );
				expect( rows.map( ( r ) => r.submission_data?.failed ) ).toEqual( [ 'blocked_email_domain' ] );
				expect( pendingSignups( wpcli, username ) ).toBe( 0 );
			} );

			await test.step( 'an address on the block list never sees the form', async () => {
				const ip = '198.51.100.73';
				await zs.block( ip );
				const page = await ( await newRoleContext( null, { ip } ) ).newPage();
				const response = await page.goto( 'wp-signup.php' );
				expect( response?.status() ).toBe( 403 );
				await expect( page.locator( '#setupform' ) ).toHaveCount( 0 );
			} );
		} );

		test( 'Stop Forum Spam: a listed email or IP is refused; a clean one, or a listed username, signs up', async ( { newRoleContext, zs, wpcli }, testInfo ) => {
			const suffix = `${ Date.now().toString( 36 ) }${ testInfo.retry }`;
			const message = 'Stop Forum Spam sign-up e2e: not today.';
			await zs.set( { stop_forum_spam: 'enabled', registration_spam_message: message } );

			const attempt = async ( ip: string, username: string, email: string ) => {
				const page = await ( await newRoleContext( null, { ip } ) ).newPage();
				const signup = new SignupPage( page );
				await signup.goto();
				await signup.signUp( username, email );
				return { page, signup };
			};

			await test.step( 'a clean sign-up goes through', async () => {
				const ip = '203.0.113.71';
				const username = `e2esignupf${ suffix }`;
				const { page } = await attempt( ip, username, `${ username }@example.com` );
				await expect( page.locator( 'body' ) ).toContainText( 'is your new username' );
				expect( await zs.logRows( { ip } ) ).toEqual( [] );
			} );

			await test.step( 'a listed email is refused', async () => {
				const ip = '203.0.113.72';
				const username = `e2esignupg${ suffix }`;
				const { signup } = await attempt( ip, username, `e2e-sfs-listed-${ suffix }@example.com` );
				await expect( signup.error ).toHaveText( message );
				const rows = await zs.logRows( { ip, type: 'registration' } );
				expect( rows.map( ( r ) => r.submission_data?.failed ) ).toEqual( [ 'stop_forum_spam_email' ] );
				expect( pendingSignups( wpcli, username ) ).toBe( 0 );
			} );

			await test.step( 'a listed username with a clean email and IP signs up: usernames are not looked up', async () => {
				const ip = '203.0.113.73';
				const username = `e2esignupe2esfslisted${ suffix }`.slice( 0, 60 );
				const { page } = await attempt( ip, username, `e2esignuph${ suffix }@example.com` );
				await expect( page.locator( 'body' ) ).toContainText( 'is your new username' );
				expect( await zs.logRows( { ip } ) ).toEqual( [] );
			} );

			await test.step( 'a listed IP is stopped by the access check before the form', async () => {
				const ip = '198.51.100.66';
				const page = await ( await newRoleContext( null, { ip } ) ).newPage();
				const response = await page.goto( 'wp-signup.php' );
				expect( response?.status() ).toBe( 403 );
				await expect( page.locator( '#setupform' ) ).toHaveCount( 0 );
				const rows = await zs.logRows( { ip } );
				expect( rows.map( ( r ) => r.submission_data?.failed ) ).toContain( 'stop_forum_spam' );
			} );
		} );
	} );
} );
