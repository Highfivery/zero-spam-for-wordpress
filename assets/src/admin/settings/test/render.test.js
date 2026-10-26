/**
 * The Settings area's own controls, rendered to markup: the date control's
 * next reset, rescue mode in both states, the import controls and the
 * alert tests are labelled and announced.
 */

/**
 * External dependencies
 */
import { afterEach, describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

/**
 * Internal dependencies
 */
import { boot } from '../../data/api';
import { Field } from '../../app/fields/fields';
import { byKey } from '../../app/fields/settings-model';
import { ImportExport } from '../io';
import { QuotaHint, RescuePanel, TestAlerts } from '../parts';
import { schema } from './fixture';

const entries = byKey( schema() );

describe( 'the quota reset date', () => {
	it( 'is a native date control, labelled and described', () => {
		const html = renderToStaticMarkup(
			<Field
				entry={ entries.quota_reset_date }
				value="2099-01-15"
				onChange={ () => {} }
				entries={ entries }
			/>
		);
		expect( html ).toContain( 'type="date"' );
		expect( html ).toContain( 'value="2099-01-15"' );
		expect( html ).toContain(
			'<label class="zs-st-t" id="zs-t-quota_reset_date" for="zs-f-quota_reset_date">'
		);
	} );

	it( 'shows the next reset and a way to clear it', () => {
		const html = renderToStaticMarkup(
			<QuotaHint value="2099-01-15" onClear={ () => {} } />
		);
		// Not a live region: the settled value is announced once, not every keystroke.
		expect( html ).not.toContain( 'aria-live' );
		expect( html ).toMatch(
			/Next reset: Jan 15, 2099\. Quota alerts count from Dec 15, 2098\./
		);
		expect( html ).toContain( 'Clear date' );
	} );

	it( 'says what happens without a date, and has nothing to clear', () => {
		const html = renderToStaticMarkup(
			<QuotaHint value="" onClear={ () => {} } />
		);
		expect( html ).toContain( 'No reset date set' );
		expect( html ).not.toContain( 'Clear date' );
	} );

	it( 'cannot be cleared while the network locked it', () => {
		const html = renderToStaticMarkup(
			<QuotaHint value="2099-01-15" onClear={ () => {} } disabled />
		);
		expect( html ).not.toContain( 'Clear date' );
	} );
} );

describe( 'units', () => {
	it( 'shows a number setting’s unit and describes the field with it', () => {
		const html = renderToStaticMarkup(
			<Field
				entry={ entries.threshold_slow_response }
				value={ 5000 }
				onChange={ () => {} }
				entries={ entries }
			/>
		);
		expect( html ).toContain(
			'<span class="zs-sfx" id="zs-u-threshold_slow_response">%</span>'
		);
		expect( html ).toMatch(
			/aria-describedby="zs-u-threshold_slow_response"/
		);
		expect( html ).toContain( 'min="1"' );
		expect( html ).toContain( 'max="100"' );
	} );

	it( 'leaves an input without a unit as it was', () => {
		const html = renderToStaticMarkup(
			<Field
				entry={ entries.max_logs }
				value={ 10000 }
				onChange={ () => {} }
				entries={ entries }
			/>
		);
		expect( html ).not.toContain( 'zs-inwrap' );
		expect( html ).not.toContain( 'zs-sfx' );
	} );
} );

describe( 'rescue mode', () => {
	afterEach( () => {
		delete boot.settings;
	} );

	it( 'says the key is not set, and that the emailed link still works', () => {
		const html = renderToStaticMarkup( <RescuePanel /> );
		expect( html ).toContain( 'Emailed rescue links are on' );
		expect( html ).toContain( 'Rescue key not set' );
		expect( html ).toContain(
			'Rescue key not set — the emailed rescue link still works. Define ZEROSPAM_RESCUE_KEY in wp-config.php for an always-on key.'
		);
	} );

	it( 'says Active when wp-config.php defines the key', () => {
		boot.settings = { rescueKey: true };
		const html = renderToStaticMarkup( <RescuePanel /> );
		expect( html ).toContain( '<b>Always-on rescue key</b>' );
		expect( html ).toContain( 'Key defined in wp-config.php' );
		expect( html ).not.toContain( 'Rescue key not set' );
	} );
} );

describe( 'import and export', () => {
	const html = renderToStaticMarkup(
		<ImportExport
			entries={ entries }
			onApplied={ () => {} }
			unsaved={ 0 }
		/>
	);

	it( 'labels the file input and the keys opt-in', () => {
		// The input sits inside its button-styled label and is named by the field and the button text.
		expect( html ).toMatch(
			/<label class="zs-btn zs-btn-secondary zs-btn-sm zs-filebtn" for="zs-imp-file"><input[^>]*id="zs-imp-file"[^>]*type="file"/
		);
		expect( html ).toContain( 'aria-labelledby="zs-imp-l zs-imp-t"' );
		expect( html ).toContain( 'aria-describedby="zs-imp-n zs-imp-d"' );
		expect( html ).toContain( 'id="zs-imp-t">Choose a file…</span>' );
		expect( html ).toContain( 'id="zs-imp-n">No file chosen</span>' );
		expect( html ).toContain( 'for="zs-imp-secrets"' );
		expect( html ).toMatch( /id="zs-imp-secrets" type="checkbox"/ );
	} );

	it( 'offers the download, and keeps import off until a file is chosen', () => {
		expect( html ).toContain( 'Download settings' );
		expect( html ).toMatch(
			/<button[^>]*disabled=""[^>]*>.*Import settings/
		);
		expect( html ).toContain( 'hidden=""' );
	} );
} );

describe( 'alert tests', () => {
	it( 'groups the three buttons under the setting’s title, with a status line', () => {
		const html = renderToStaticMarkup(
			<TestAlerts entry={ entries.test_alerts } disabled={ false } />
		);
		expect( html ).toContain(
			'role="group" aria-labelledby="zs-t-test_alerts"'
		);
		expect( html.match( /<button/g ) ).toHaveLength( 3 );
		expect( html ).toContain( 'role="status"' );
	} );
} );
