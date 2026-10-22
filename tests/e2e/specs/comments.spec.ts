import type { APIRequestContext } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { COMMENTS_POST_PATH, CommentForm } from '../support/pages/front';
import { DashboardPage } from '../support/pages/dashboard-page';

/**
 * Reads what a bot needs from the post page: the post ID and the honeypot name.
 *
 * @param request Bot request context.
 */
async function scrapeForm( request: APIRequestContext ): Promise< { postId: string; honeypot: string } > {
	const html = await ( await request.get( COMMENTS_POST_PATH ) ).text();
	const postId = html.match( /name=['"]comment_post_ID['"] value=['"](\d+)['"]/ )?.[ 1 ] ?? '';
	const honeypot = html.match( /<input type="text" name="([^"]+)" value="" style="display: none !important;"/ )?.[ 1 ] ?? '';
	expect( postId, 'the post has an open comment form' ).not.toBe( '' );
	return { postId, honeypot };
}

test.describe( 'Comment protection', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'a real visitor with JavaScript posts a comment and nothing is logged', async ( { visitor, zs, gallery } ) => {
		const form = new CommentForm( visitor.page );
		await form.goto();
		await form.fill( { author: 'Real Person', email: 'real.person@example.com', comment: `A genuine comment ${ Date.now() }` } );
		await gallery.capture( form.form, 'visitor fills in the comment form' );
		const status = await form.submit();
		expect( status, 'WordPress accepted the comment (redirect back to the post)' ).toBe( 302 );
		await expect( visitor.page ).toHaveURL( /e2e-comments/ );
		await gallery.capture( visitor.page, 'comment accepted' );
		expect( await zs.logRows( { ip: visitor.ip } ) ).toEqual( [] );
	} );

	test( 'a bot that fills the honeypot is blocked with the comment spam message and logged as comment/honeypot', async ( { bot, zs, page, gallery } ) => {
		const { postId, honeypot } = await scrapeForm( bot.request );
		expect( honeypot, 'the honeypot field is rendered' ).not.toBe( '' );

		const response = await bot.request.post( 'wp-comments-post.php', {
			form: { comment_post_ID: postId, author: 'Bot', email: 'bot@example.com', comment: `Buy now ${ Date.now() }`, [ honeypot ]: 'filled-by-bot' },
			maxRedirects: 0,
		} );
		expect( response.status() ).toBe( 403 );
		expect( await response.text() ).toContain( 'Your IP has been flagged as spam/malicious.' );

		const rows = await zs.logRows( { ip: bot.ip, type: 'comment' } );
		expect( rows.map( ( r ) => r.submission_data?.failed ) ).toContain( 'honeypot' );

		await test.step( 'the detection appears in the Log screen', async () => {
			const dashboard = new DashboardPage( page );
			await dashboard.goto( 'log', `&s=${ bot.ip }` );
			await expect( dashboard.rows.filter( { hasText: bot.ip } ).first() ).toContainText( 'Comment' );
			await gallery.capture( page, 'detection in the log' );
		} );
	} );

	test( 'a bot without JavaScript (no David Walsh key) is blocked and logged; the key still arrives through admin-ajax when REST is blocked', async ( { bot, zs, visitor } ) => {
		await test.step( 'a form post without the key is blocked', async () => {
			const { postId, honeypot } = await scrapeForm( bot.request );
			const response = await bot.request.post( 'wp-comments-post.php', {
				form: { comment_post_ID: postId, author: 'Bot', email: 'bot@example.com', comment: `No JS ${ Date.now() }`, ...( honeypot ? { [ honeypot ]: '' } : {} ) },
				maxRedirects: 0,
			} );
			expect( response.status() ).toBe( 403 );
			const rows = await zs.logRows( { ip: bot.ip, type: 'comment' } );
			expect( rows.map( ( r ) => r.submission_data?.failed ) ).toContain( 'david_walsh' );
		} );

		await test.step( 'with the REST API blocked, the browser gets the key from admin-ajax and the comment goes through', async () => {
			await visitor.context.route( /davidwalsh-key|rest_route=%2Fzero-spam%2Fv5/, ( route ) => route.fulfill( { status: 403, body: '{}' } ) );
			const ajax = visitor.page.waitForResponse( ( r ) => r.url().includes( 'action=zerospam_davidwalsh_token' ) );
			const form = new CommentForm( visitor.page );
			await form.goto();
			await form.form.locator( '#comment' ).click();
			const answer = await ( await ajax ).json();
			expect( answer.key ).toBeTruthy();
			await expect( form.form.locator( 'input[name="zerospam_david_walsh_key"]' ) ).toHaveValue( answer.key );
			await form.fill( { author: 'Real Person', email: 'real.person@example.com', comment: `Through admin-ajax ${ Date.now() }` } );
			expect( await form.submit() ).toBe( 302 );
			expect( await zs.logRows( { ip: visitor.ip } ) ).toEqual( [] );
		} );
	} );
} );
