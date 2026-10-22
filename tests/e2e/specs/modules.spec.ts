import { expect, test } from '../support/fixtures';
import { CommentForm, honeypot } from '../support/pages/front';

test.describe( 'Modules', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'turning comment protection on adds the honeypot and David Walsh key to the comment form; turning it off removes them', async ( { visitor, zs, gallery } ) => {
		const form = new CommentForm( visitor.page );

		await test.step( 'protection on: honeypot field and David Walsh key are present', async () => {
			await form.goto();
			await expect( honeypot( form.form ) ).toHaveCount( 1 );
			await expect( form.form ).toHaveAttribute( 'data-zerospam-davidwalsh', 'protected' );
			await expect( form.form.locator( 'input[name="zerospam_david_walsh_key"]' ) ).toHaveValue( /\S{10,}/ );
			await gallery.capture( form.form, 'protected comment form' );
		} );

		await test.step( 'protection off: neither is added', async () => {
			await zs.set( { verify_comments: false } );
			await form.goto();
			await expect( honeypot( form.form ) ).toHaveCount( 0 );
			await expect( form.form.locator( 'input[name="zerospam_david_walsh_key"]' ) ).toHaveCount( 0 );
		} );
	} );
} );
