/**
 * Network admin › Settings › Zero Spam Network, and the dashboard widget.
 */

import { expect, type Locator, type Page } from '@playwright/test';

export const NETWORK_SETTINGS_PATH = 'wp-admin/network/settings.php?page=zerospam-network-settings';

export class NetworkSettingsPage {
	constructor( readonly page: Page ) {}

	async goto( tab = 'settings' ): Promise< void > {
		await this.page.goto( `${ NETWORK_SETTINGS_PATH }&tab=${ tab }` );
	}

	row( key: string ): NetworkSettingRow {
		return new NetworkSettingRow( this.page, this.page.locator( `tr[data-setting-key="${ key }"]` ) );
	}
}

export class NetworkSettingRow {
	constructor(
		readonly page: Page,
		readonly root: Locator
	) {}

	/** Expands the group the row is in. */
	async reveal(): Promise< void > {
		if ( await this.root.isVisible() ) {
			return;
		}
		const groupId = await this.root.evaluate( ( tr ) => tr.closest( '.settings-group-content' )!.id.replace( /^group-/, '' ) );
		await this.page.locator( `.settings-group-toggle[data-group="${ groupId }"]` ).click();
		await expect( this.root ).toBeVisible();
	}

	async setChecked( on: boolean ): Promise< void > {
		await this.reveal();
		await this.root.locator( 'input[type="checkbox"]' ).first().setChecked( on );
	}

	async save(): Promise< void > {
		const response = this.page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() || '' ).includes( 'zerospam_network_set_setting' ) );
		await this.root.getByRole( 'button', { name: 'Save' } ).click();
		expect( ( await ( await response ).json() ).success ).toBe( true );
		await expect( this.root.locator( '.inline-save-message.success' ) ).toBeVisible();
	}

	async toggleLock(): Promise< void > {
		await this.reveal();
		const response = this.page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && /zerospam_network_(un)?lock_setting/.test( r.request().postData() || '' ) );
		await this.root.locator( '.toggle-lock' ).click();
		expect( ( await ( await response ).json() ).success ).toBe( true );
	}
}

export class DashboardWidget {
	readonly root: Locator;

	constructor( readonly page: Page ) {
		this.root = page.locator( '#zerospam_unified_widget' );
	}

	get refresh(): Locator {
		return this.root.locator( '[data-zs-refresh]' );
	}

	/** The headline figure (detections in the last 30 days). */
	get total(): Locator {
		return this.root.locator( '[data-zs-field="total"]' );
	}

	/** The polite live region that announces the refresh result. */
	get status(): Locator {
		return this.root.locator( '[role="status"][aria-live="polite"]' );
	}
}
