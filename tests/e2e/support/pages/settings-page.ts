/**
 * Settings › Zero Spam (options-general.php?page=wordpress-zero-spam-settings).
 */

import { expect, type Locator, type Page } from '@playwright/test';

export const SETTINGS_PATH = 'wp-admin/options-general.php?page=wordpress-zero-spam-settings';

export class SettingsPage {
	constructor(
		readonly page: Page,
		private readonly root = ''
	) {}

	async goto( subview = 'settings' ): Promise< void > {
		await this.page.goto( `${ this.root }${ SETTINGS_PATH }&subview=${ subview }` );
		await expect( this.page.locator( '.zerospam-dashboard' ) ).toBeVisible();
	}

	/** Links in the left-hand menu, as subview keys. */
	async menuSubviews(): Promise< string[] > {
		const hrefs = await this.page.locator( '.zerospam-dashboard__sections a' ).evaluateAll( ( links ) => links.map( ( a ) => ( a as HTMLAnchorElement ).href ) );
		return hrefs.map( ( h ) => new URL( h ).searchParams.get( 'subview' ) || '' );
	}

	get form(): Locator {
		return this.page.locator( 'form.zerospam-form' );
	}

	get saveButton(): Locator {
		return this.page.getByRole( 'button', { name: /Save Settings/ } );
	}

	/** The control of a setting key (checkboxes and radios carry the option key as suffix). */
	field( key: string, option?: string ): Locator {
		return option ? this.page.locator( `[id="${ key }${ option }"]` ) : this.page.locator( `[id="${ key }"]` );
	}

	/**
	 * Switches a checkbox or radio the way a mouse user does: the input itself
	 * is display:none and the styled label is the control.
	 *
	 * @param key    Setting key.
	 * @param option Option key (enabled for single checkboxes).
	 * @param on     Desired state.
	 */
	async toggle( key: string, option: string, on: boolean ): Promise< void > {
		const input = this.field( key, option );
		if ( ( await input.isChecked() ) !== on ) {
			await this.page.locator( `label[for="${ key }${ option }"]` ).click();
		}
		await expect( input ).toBeChecked( { checked: on } );
	}

	async save(): Promise< void > {
		await Promise.all( [ this.page.waitForURL( /settings-updated=true/ ), this.saveButton.click() ] );
		await expect( this.page.getByText( 'Settings saved.' ) ).toBeVisible();
	}
}
