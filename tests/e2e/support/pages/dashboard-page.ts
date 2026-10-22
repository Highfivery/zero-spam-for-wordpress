/**
 * Dashboard › Zero Spam (index.php?page=wordpress-zero-spam-dashboard) and
 * its add/update block modal.
 */

import { expect, type Locator, type Page } from '@playwright/test';

export const DASHBOARD_PATH = 'wp-admin/index.php?page=wordpress-zero-spam-dashboard';

export class DashboardPage {
	constructor( readonly page: Page ) {}

	async goto( subview: 'reports' | 'log' | 'blocked-ips' | 'blocked-locations' = 'reports', query = '' ): Promise< void > {
		await this.page.goto( `${ DASHBOARD_PATH }&subview=${ subview }${ query }` );
		await expect( this.page.locator( '.zerospam-dashboard' ) ).toBeVisible();
	}

	/** Rows of the list table on the current subview (empty-state row excluded). */
	get rows(): Locator {
		return this.page.locator( 'table.wp-list-table tbody tr:not(.no-items)' );
	}

	get notice(): Locator {
		return this.page.locator( '.zerospam-block--notice' );
	}

	async logIps(): Promise< string[] > {
		return this.page.locator( 'table.wp-list-table tbody td.user_ip a' ).allInnerTexts();
	}
}

export class BlockIpModal {
	readonly root: Locator;

	constructor( readonly page: Page ) {
		this.root = page.locator( '#zerospam-block-ip' );
	}

	async openFromToolbar(): Promise< void > {
		await this.page.getByRole( 'button', { name: /Add Blocked IP Address/ } ).click();
		await expect( this.root ).toHaveClass( /is-active/ );
	}

	async fill( values: { ip?: string; type?: 'temporary' | 'permanent'; reason?: string; end?: string } ): Promise< void > {
		if ( undefined !== values.ip ) {
			await this.root.locator( 'input[name="blocked_ip"]' ).fill( values.ip );
		}
		if ( values.type ) {
			await this.root.locator( 'select[name="blocked_type"]' ).selectOption( values.type );
		}
		if ( undefined !== values.reason ) {
			await this.root.locator( 'input[name="blocked_reason"]' ).fill( values.reason );
		}
		if ( undefined !== values.end ) {
			await this.root.locator( 'input[name="blocked_end_date"]' ).fill( values.end );
		}
	}

	async submit(): Promise< void > {
		// The redirect lands on a URL that may equal the current one, so wait for the new document.
		await Promise.all( [ this.page.waitForEvent( 'load' ), this.root.getByRole( 'button', { name: /Add\/Update Blocked IP/ } ).click() ] );
	}
}
