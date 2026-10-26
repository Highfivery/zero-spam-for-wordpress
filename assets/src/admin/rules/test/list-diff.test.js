/**
 * Unsaved list changes: staged additions and removals cancel each other,
 * repeats are skipped (without case), the request body holds only what
 * changed, and the rows show staged entries with their state.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	changeCount,
	diffLists,
	emptyDraft,
	listRows,
	requestBody,
	stageAdd,
	stageRemove,
	unstageRemove,
} from '../list-diff';

describe( 'staging', () => {
	it( 'adds entries once, without case', () => {
		const { draft, skipped } = stageAdd( emptyDraft(), [
			'a.com',
			'A.COM',
			'b.com',
		] );
		expect( draft ).toEqual( { add: [ 'a.com', 'b.com' ], remove: [] } );
		expect( skipped ).toEqual( [ 'A.COM' ] );
	} );

	it( 'skips entries known to be on the list', () => {
		const { draft, skipped } = stageAdd(
			emptyDraft(),
			[ 'a.com', 'c.com' ],
			( e ) => 'a.com' === e
		);
		expect( draft.add ).toEqual( [ 'c.com' ] );
		expect( skipped ).toEqual( [ 'a.com' ] );
	} );

	it( 'adding an entry staged for removal keeps it instead', () => {
		let draft = stageRemove( emptyDraft(), 'a.com' );
		expect( draft.remove ).toEqual( [ 'a.com' ] );
		draft = stageAdd( draft, [ 'A.com' ] ).draft;
		expect( draft ).toEqual( { add: [], remove: [] } );
		expect( changeCount( draft ) ).toBe( 0 );
	} );

	it( 'removing an entry only staged to be added drops it', () => {
		let draft = stageAdd( emptyDraft(), [ 'a.com', 'b.com' ] ).draft;
		draft = stageRemove( draft, 'A.COM' );
		expect( draft ).toEqual( { add: [ 'b.com' ], remove: [] } );
	} );

	it( 'removes once, and Keep undoes a removal', () => {
		let draft = stageRemove( emptyDraft(), 'a.com' );
		draft = stageRemove( draft, 'a.com' );
		expect( draft.remove ).toEqual( [ 'a.com' ] );
		draft = unstageRemove( draft, 'A.com' );
		expect( changeCount( draft ) ).toBe( 0 );
	} );
} );

describe( 'request body', () => {
	it( 'sends only what changed', () => {
		expect( requestBody( emptyDraft() ) ).toEqual( {} );
		expect( requestBody( { add: [ 'a' ], remove: [] } ) ).toEqual( {
			add: [ 'a' ],
		} );
		expect( requestBody( { add: [], remove: [ 'b' ] }, 4 ) ).toEqual( {
			remove: [ 'b' ],
			min_length: 4,
		} );
		expect( requestBody( emptyDraft(), 0 ) ).toEqual( { min_length: 0 } );
	} );
} );

describe( 'rows', () => {
	it( 'shows staged additions first, then the page with removals marked', () => {
		const draft = { add: [ 'new.com' ], remove: [ 'b.com' ] };
		expect( listRows( [ 'a.com', 'B.com' ], draft ) ).toEqual( [
			{ entry: 'new.com', state: 'added' },
			{ entry: 'a.com', state: 'saved' },
			{ entry: 'B.com', state: 'removed' },
		] );
	} );

	it( 'filters staged additions by the search; the page is already searched', () => {
		const draft = { add: [ 'new.com', 'other.org' ], remove: [] };
		expect( listRows( [ 'other.net' ], draft, 'OTHER' ) ).toEqual( [
			{ entry: 'other.org', state: 'added' },
			{ entry: 'other.net', state: 'saved' },
		] );
	} );

	it( 'does not show an entry twice', () => {
		const draft = { add: [ 'a.com' ], remove: [] };
		expect( listRows( [ 'A.com' ], draft ) ).toEqual( [
			{ entry: 'a.com', state: 'added' },
		] );
	} );
} );

describe( 'diffLists', () => {
	it( 'finds additions and removals without case or repeats', () => {
		expect(
			diffLists( [ 'a.com', 'b.com' ], [ 'B.com', 'c.com', 'C.com' ] )
		).toEqual( { add: [ 'c.com' ], remove: [ 'a.com' ] } );
		expect( diffLists( [], [] ) ).toEqual( { add: [], remove: [] } );
	} );
} );
