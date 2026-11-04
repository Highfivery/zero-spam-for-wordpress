/**
 * Front-end forms: the comment form, wp-login.php, the registration form and a network's sign-up page.
 */

import { expect, type Locator, type Page } from '@playwright/test';

/** The comment-test post, by slug (the main site of a subdirectory network adds /blog/ to permalinks). */
export const COMMENTS_POST_PATH = '?name=e2e-comments';

/** The Zero Spam honeypot input inside a form. */
export function honeypot( form: Locator ): Locator {
	return form.locator( 'input[type="text"][style*="display: none"]' );
}

export class CommentForm {
	readonly form: Locator;

	constructor( readonly page: Page ) {
		this.form = page.locator( '#commentform' );
	}

	async goto( root = '' ): Promise< void > {
		await this.page.goto( root + COMMENTS_POST_PATH );
		await expect( this.form ).toBeVisible();
	}

	/** Fills the form like a person (focus fetches a fresh David Walsh key). */
	async fill( values: { author: string; email: string; comment: string } ): Promise< void > {
		const keyResponse = this.page.waitForResponse( ( r ) => /davidwalsh-key|zerospam_davidwalsh_token/.test( r.url() ), { timeout: 10_000 } ).catch( () => null );
		await this.form.locator( '#comment' ).click();
		await this.form.locator( '#comment' ).fill( values.comment );
		await this.form.locator( '#author' ).fill( values.author );
		await this.form.locator( '#email' ).fill( values.email );
		await keyResponse;
	}

	async submit(): Promise< number > {
		const response = this.page.waitForResponse( ( r ) => r.url().includes( 'wp-comments-post.php' ) && 'POST' === r.request().method() );
		await this.form.locator( '#submit, [type="submit"]' ).first().click();
		const status = ( await response ).status();
		await this.page.waitForLoadState();
		return status;
	}
}

export class LoginPage {
	readonly form: Locator;

	constructor( readonly page: Page ) {
		this.form = page.locator( '#loginform' );
	}

	async goto(): Promise< void > {
		await this.page.goto( 'wp-login.php' );
		await expect( this.form ).toBeVisible();
		// wp-login.php focuses and selects the username 200 ms after load; typing
		// before that lands the password in the username field.
		await expect( this.form.locator( '#user_login' ) ).toBeFocused();
	}

	async login( username: string, password: string ): Promise< void > {
		await this.form.locator( '#user_login' ).fill( username );
		await this.form.locator( '#user_pass' ).fill( password );
		await Promise.all( [ this.page.waitForLoadState(), this.form.locator( '#wp-submit' ).click() ] );
	}

	get error(): Locator {
		return this.page.locator( '#login_error' );
	}
}

export class RegisterPage {
	readonly form: Locator;

	constructor( readonly page: Page ) {
		this.form = page.locator( '#registerform' );
	}

	async goto(): Promise< void > {
		await this.page.goto( 'wp-login.php?action=register' );
		await expect( this.form ).toBeVisible();
	}

	async register( username: string, email: string ): Promise< void > {
		await this.form.locator( '#user_login' ).click();
		await this.form.locator( '#user_login' ).fill( username );
		await this.form.locator( '#user_email' ).fill( email );
		await Promise.all( [ this.page.waitForLoadState(), this.form.locator( '#wp-submit' ).click() ] );
	}
}

/** A network's sign-up page: wp-signup.php, the user step and the site step share the form's id. */
export class SignupPage {
	readonly form: Locator;

	constructor( readonly page: Page ) {
		this.form = page.locator( '#setupform' );
	}

	async goto(): Promise< void > {
		await this.page.goto( 'wp-signup.php' );
		await expect( this.form ).toBeVisible();
	}

	/**
	 * Fills the user step like a person (focus fetches a fresh David Walsh key) and sends it.
	 *
	 * @param username Username.
	 * @param email    Email address.
	 * @param signupFor Whether to ask for just a username or a site as well.
	 */
	async signUp( username: string, email: string, signupFor: 'user' | 'blog' = 'user' ): Promise< void > {
		await this.form.locator( '#user_name' ).click();
		await this.form.locator( '#user_name' ).fill( username );
		await this.form.locator( '#user_email' ).fill( email );
		await this.form.locator( 'user' === signupFor ? '#signupuser' : '#signupblog' ).check();
		await this.submit();
	}

	async submit(): Promise< void > {
		await Promise.all( [ this.page.waitForLoadState(), this.form.locator( 'input[type="submit"]' ).click() ] );
	}

	get error(): Locator {
		return this.page.locator( '#wp-signup-generic-error' );
	}
}
