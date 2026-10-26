/**
 * Small parts of the Rules screens: the search box, the pager, empty
 * states, the network lock badge and notes, a country with its flag, and
 * dates of blocks in the site's time.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Badge, Button, Icon } from '../app/primitives';
import { boot, nowLocal } from '../data/api';
import { day, num, parseDate } from '../data/format';

/**
 * A UTC date from the API (ends in Z) shown as its day in the site's time;
 * a date without a zone is already site time (date_added).
 *
 * @param {string|null} iso Date.
 * @return {string} "Thu, Oct 22", or ''.
 */
export function siteDay( iso ) {
	if ( ! iso ) {
		return '';
	}
	const text = String( iso );
	if ( ! /(Z|[+-]\d{2}:?\d{2})$/.test( text ) ) {
		return day( text, new Date( nowLocal() ) );
	}
	const date = parseDate( text );
	if ( ! date ) {
		return '';
	}
	const shift = nowLocal() - Date.now();
	return day( new Date( date.getTime() + shift ), new Date( nowLocal() ) );
}

/**
 * A search box that waits for a pause in typing (300 ms) before it
 * searches; Enter searches at once.
 *
 * @param {Object}                          props            Props.
 * @param {string}                          props.id         Input ID.
 * @param {string}                          props.label      Label (also the placeholder).
 * @param {string}                          props.value      Search applied.
 * @param {(...args: unknown[]) => unknown} props.onSearch   Receives the text.
 * @param {Object}                          [props.inputRef] Ref of the input.
 */
export function SearchBox( { id, label, value, onSearch, inputRef } ) {
	const [ text, setText ] = useState( value );
	const timer = useRef( 0 );
	const latest = useRef( onSearch );
	latest.current = onSearch;

	// Cleared from outside ("Clear search").
	useEffect( () => {
		setText( value );
	}, [ value ] );

	useEffect( () => () => window.clearTimeout( timer.current ), [] );

	const change = ( next ) => {
		setText( next );
		window.clearTimeout( timer.current );
		timer.current = window.setTimeout(
			() => latest.current( next.trim() ),
			300
		);
	};

	return (
		<div className="zs-field zs-search" role="search">
			<label htmlFor={ id } className="zs-sr-only">
				{ label }
			</label>
			<Icon name="search" size="sm" />
			<input
				ref={ inputRef }
				id={ id }
				className="zs-input"
				type="search"
				placeholder={ label }
				value={ text }
				maxLength={ 100 }
				autoComplete="off"
				spellCheck="false"
				onChange={ ( e ) => change( e.target.value ) }
				onKeyDown={ ( e ) => {
					if ( 'Enter' === e.key ) {
						e.preventDefault();
						window.clearTimeout( timer.current );
						latest.current( text.trim() );
					}
				} }
			/>
		</div>
	);
}

/**
 * Previous / Next with "Showing 1–50 of 230".
 *
 * @param {Object}                          props        Props.
 * @param {Object}                          props.pages  paging() result.
 * @param {number}                          props.total  Entries.
 * @param {string}                          props.label  Name of the navigation.
 * @param {(...args: unknown[]) => unknown} props.onPage Receives a page.
 */
export function Pager( { pages, total, label, onPage } ) {
	if ( pages.pages < 2 ) {
		return null;
	}
	return (
		<nav className="zs-pager" aria-label={ label }>
			<span>
				{ sprintf(
					/* translators: 1: first entry shown, 2: last entry shown, 3: number of entries */
					__( 'Showing %1$s–%2$s of %3$s', 'zero-spam' ),
					num( pages.from ),
					num( pages.to ),
					num( total )
				) }
			</span>
			<span className="zs-pages">
				<Button
					variant="secondary"
					size="sm"
					disabled={ pages.page <= 1 }
					onClick={ () => onPage( pages.page - 1 ) }
				>
					{ __( 'Previous', 'zero-spam' ) }
				</Button>
				<Button
					variant="secondary"
					size="sm"
					disabled={ pages.page >= pages.pages }
					onClick={ () => onPage( pages.page + 1 ) }
				>
					{ __( 'Next', 'zero-spam' ) }
				</Button>
			</span>
		</nav>
	);
}

/**
 * One empty-state pattern: what is empty, then what to do.
 *
 * @param {Object}                          props           Props.
 * @param {unknown}                         props.title     What is empty.
 * @param {unknown}                         props.hint      What to do.
 * @param {(...args: unknown[]) => unknown} [props.onClear] Adds "Clear search".
 */
export function EmptyState( { title, hint, onClear } ) {
	return (
		<div className="zs-empty">
			<div className="zs-ghost-wall zs-ghost-sm" aria-hidden="true">
				<span />
				<span />
				<span />
			</div>
			<b>{ title }</b>
			<span className="zs-hint">{ hint }</span>
			{ onClear && (
				<Button variant="secondary" size="sm" onClick={ onClear }>
					{ __( 'Clear search', 'zero-spam' ) }
				</Button>
			) }
		</div>
	);
}

/**
 * The "Locked by network" badge, with the full sentence for screen readers.
 */
export function LockBadge() {
	return (
		<>
			<Badge className="zs-badge-lock" icon="lock">
				{ __( 'Locked by network', 'zero-spam' ) }
			</Badge>
			<span className="zs-sr-only">
				{ __(
					'Locked by the network administrator. This list cannot be changed on this site.',
					'zero-spam'
				) }
			</span>
		</>
	);
}

/**
 * A country: its flag (decorative, shipped with the plugin) and its name.
 *
 * @param {Object} props      Props.
 * @param {string} props.code Two-letter code.
 * @param {string} props.name Country name.
 */
export function Country( { code, name } ) {
	const [ broken, setBroken ] = useState( false );
	const cc = /^[A-Za-z]{2}$/.test( String( code || '' ) )
		? String( code ).toLowerCase()
		: '';
	return (
		<span className="zs-cty">
			{ cc && boot.urls.flags && ! broken && (
				<img
					className="zs-flag"
					src={ `${ boot.urls.flags }${ cc }.svg` }
					alt=""
					width="20"
					height="14"
					loading="lazy"
					decoding="async"
					onError={ () => setBroken( true ) }
				/>
			) }
			<span>{ name || code }</span>
		</span>
	);
}
