/**
 * The Zero Spam dashboard widget (site and network dashboards).
 */

import type { Locator, Page } from '@playwright/test';

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
