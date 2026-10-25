/**
 * The app shell (prototype "Evolving Grid"): the in-app sidebar (brand,
 * area navigation linking to the real pages, the lower block), the sticky
 * top bar (breadcrumb, status, level chip, theme), the area content, and
 * on phones the bottom navigation with the sidebar as a "More" drawer.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp, useMedia } from '../context';
import { Button, Icon, SkipLink, cx } from '../primitives';
import { Mark } from '../mark';
import { LevelChip } from './level';
import { SideExtra } from './side-extra';
import { focusables } from '../primitives/drawer';
import { boot } from '../../data/api';
import { num } from '../../data/format';
import { toggledTheme } from '../../data/theme';
import { areaUrl } from '../../data/routes';

const ICONS = {
	overview: 'wall',
	activity: 'activity',
	rules: 'rules',
	protection: 'shield',
	settings: 'sliders',
	help: 'help',
};

/** @return {Object<string, string>} Area names. */
export const areaTitles = () => ( {
	overview: __( 'Overview', 'zero-spam' ),
	activity: __( 'Activity', 'zero-spam' ),
	rules: __( 'Rules', 'zero-spam' ),
	protection: __( 'Protection', 'zero-spam' ),
	settings: __( 'Settings', 'zero-spam' ),
	help: __( 'Help', 'zero-spam' ),
} );

/** @return {Array<[string, string, string]>} Network tabs: key, label, icon. */
export const networkTabs = () => [
	[ 'overview', __( 'Overview', 'zero-spam' ), 'wall' ],
	[ 'settings', __( 'Settings', 'zero-spam' ), 'sliders' ],
	[ 'templates', __( 'Templates', 'zero-spam' ), 'form' ],
	[ 'audit', __( 'Audit log', 'zero-spam' ), 'book' ],
	[ 'comparison', __( 'Comparison', 'zero-spam' ), 'window' ],
	[ 'io', __( 'Import/Export', 'zero-spam' ), 'down' ],
	[ 'notifications', __( 'Notifications', 'zero-spam' ), 'mail' ],
	[ 'stats', __( 'Network stats', 'zero-spam' ), 'activity' ],
];

/**
 * The site's protection status from protection/modules.
 *
 * @param {Object|undefined} modules Route answer.
 * @return {{tone: string, label: string, count: number}} Status.
 */
export function protectionStatus( modules ) {
	const warnings = ( modules?.attention || [] ).filter(
		( a ) => 'warning' === a.severity
	);
	if ( warnings.length ) {
		return {
			tone: 'warn',
			label: sprintf(
				/* translators: %d: number of items */
				__( '%d to review', 'zero-spam' ),
				warnings.length
			),
			count: warnings.length,
		};
	}
	return {
		tone: 'ok',
		label: __( 'All checks active', 'zero-spam' ),
		count: 0,
	};
}

/**
 * The theme switch (sun / moon).
 *
 * @param {Object} props             Props.
 * @param {string} [props.className] Extra classes.
 */
function ThemeButton( { className } ) {
	const { theme, themePref, savePrefs } = useApp();
	const osDark = useMedia( '(prefers-color-scheme: dark)' );
	const dark = 'dark' === theme;
	return (
		<Button
			variant="ghost"
			iconOnly
			icon={ dark ? 'sun' : 'moon' }
			className={ cx( 'zs-theme-btn', className ) }
			aria-label={
				dark
					? __( 'Switch to light theme', 'zero-spam' )
					: __( 'Switch to dark theme', 'zero-spam' )
			}
			onClick={ () =>
				savePrefs( { theme: toggledTheme( themePref, osDark ) } )
			}
		/>
	);
}

/**
 * @param {Object}   props             Props.
 * @param {string}   props.area        Current area.
 * @param {string}   [props.netTab]    Network tab.
 * @param {Function} [props.setNetTab] Changes the network tab.
 * @param {string}   [props.crumb]     Breadcrumb's last part.
 * @param {*}        props.children    Area content.
 */
export function Shell( { area, netTab, setNetTab, crumb, children } ) {
	const { network, modules, day } = useApp();
	const titles = areaTitles();
	const [ menuOpen, setMenuOpen ] = useState( false );
	const side = useRef( null );
	const menuBtn = useRef( null );
	const moreBtn = useRef( null );
	const lastOpener = useRef( null );
	const drawerMode = useMedia( '(max-width: 959px)' );
	const [ fade, setFade ] = useState( false );

	const status = protectionStatus( modules );
	const count = day?.total;

	const openMenu = ( from ) => {
		lastOpener.current = from;
		setMenuOpen( true );
	};
	const closeMenu = useCallback( ( restore = true ) => {
		setMenuOpen( false );
		if ( restore && lastOpener.current ) {
			lastOpener.current.focus();
		}
	}, [] );

	// The sidebar as a modal drawer on narrow screens.
	useEffect( () => {
		if ( ! menuOpen ) {
			return undefined;
		}
		side.current?.querySelector( 'a[aria-current="page"], a' )?.focus();
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				closeMenu();
			} else if ( 'Tab' === event.key && side.current ) {
				const list = focusables( side.current );
				const first = list[ 0 ];
				const last = list[ list.length - 1 ];
				if (
					event.shiftKey &&
					side.current.ownerDocument.activeElement === first
				) {
					event.preventDefault();
					last.focus();
				} else if (
					! event.shiftKey &&
					side.current.ownerDocument.activeElement === last
				) {
					event.preventDefault();
					first.focus();
				}
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ menuOpen, closeMenu ] );

	useEffect( () => {
		if ( ! drawerMode && menuOpen ) {
			setMenuOpen( false );
		}
	}, [ drawerMode, menuOpen ] );

	// A fade at the sidebar's bottom edge while there is more to scroll.
	useEffect( () => {
		const el = side.current;
		if ( ! el ) {
			return undefined;
		}
		const sync = () =>
			setFade( el.scrollHeight - el.scrollTop - el.clientHeight > 2 );
		sync();
		el.addEventListener( 'scroll', sync, { passive: true } );
		window.addEventListener( 'resize', sync );
		const observer =
			'undefined' !== typeof window.ResizeObserver
				? new window.ResizeObserver( sync )
				: null;
		observer?.observe( el );
		return () => {
			el.removeEventListener( 'scroll', sync );
			window.removeEventListener( 'resize', sync );
			observer?.disconnect();
		};
	}, [] );

	const siteNav = (
		<ul className="zs-nav">
			{ Object.keys( ICONS ).map( ( key ) => (
				<li key={ key }>
					<a
						href={ areaUrl( key ) }
						aria-current={ key === area ? 'page' : undefined }
					>
						<Icon name={ ICONS[ key ] } />
						{ titles[ key ] }
						{ 'activity' === key && count > 0 && (
							<span className="zs-count">
								{ num( count ) }
								<span className="zs-sr-only">
									{ __(
										'blocked in the last 24 hours',
										'zero-spam'
									) }
								</span>
							</span>
						) }
					</a>
				</li>
			) ) }
		</ul>
	);

	const netNav = (
		<ul className="zs-nav">
			{ networkTabs().map( ( [ key, label, icon ] ) => (
				<li key={ key }>
					<a
						href={ `${ boot.urls.network || '' }${ 'overview' === key ? '' : `&tab=${ key }` }` }
						aria-current={ key === netTab ? 'page' : undefined }
						onClick={ ( event ) => {
							if (
								setNetTab &&
								! event.metaKey &&
								! event.ctrlKey &&
								! event.shiftKey
							) {
								event.preventDefault();
								setNetTab( key );
								if ( menuOpen ) {
									closeMenu( false );
								}
								document
									.getElementById( 'zs-content' )
									?.focus();
							}
						} }
					>
						<Icon name={ icon } />
						{ label }
					</a>
				</li>
			) ) }
			<li className="zs-nav-back">
				<a href={ boot.urls.siteAdmin }>
					<Icon name="chevl" />
					{ __( 'Site admin', 'zero-spam' ) }
				</a>
			</li>
		</ul>
	);

	return (
		<>
			<SkipLink target="zs-content">
				{ __( 'Skip to Zero Spam content', 'zero-spam' ) }
			</SkipLink>
			<div className={ cx( 'zs-frame', menuOpen && 'zs-menu-open' ) }>
				{ /* The sidebar: a column on wide screens, a modal drawer below 960px. */ }
				<div
					ref={ side }
					className={ cx(
						'zs-side',
						menuOpen && 'zs-open',
						fade && 'zs-fade'
					) }
					id="zs-side"
					role={ drawerMode ? 'dialog' : undefined }
					aria-modal={ drawerMode && menuOpen ? 'true' : undefined }
					aria-label={
						drawerMode
							? __( 'Zero Spam menu', 'zero-spam' )
							: undefined
					}
					hidden={ drawerMode && ! menuOpen ? true : undefined }
				>
					<a
						className="zs-brand"
						href={
							network ? boot.urls.network : areaUrl( 'overview' )
						}
					>
						<Mark size={ 32 } live />
						<span>
							<b>{ __( 'Zero Spam', 'zero-spam' ) }</b>
							<small>
								{ network
									? __( 'Network Admin', 'zero-spam' )
									: __( 'for WordPress', 'zero-spam' ) }
							</small>
						</span>
					</a>
					{ ! network && (
						<div
							className="zs-side-m"
							role="group"
							aria-label={ __(
								'Protection level and display',
								'zero-spam'
							) }
						>
							<LevelChip where="sheet" />
							<ThemeButton className="zs-sheet-theme" />
						</div>
					) }
					<nav
						aria-label={
							network
								? __( 'Zero Spam network admin', 'zero-spam' )
								: __( 'Zero Spam', 'zero-spam' )
						}
					>
						{ network ? netNav : siteNav }
					</nav>
					<SideExtra />
				</div>
				{ menuOpen && (
					<div
						className="zs-scrim zs-scrim-menu"
						aria-hidden="true"
						onClick={ () => closeMenu() }
					/>
				) }

				<div className="zs-main" inert={ menuOpen ? '' : undefined }>
					<header className="zs-topbar">
						<Button
							ref={ menuBtn }
							variant="ghost"
							iconOnly
							icon="menu"
							className="zs-menu-btn"
							aria-label={ __(
								'Open Zero Spam menu',
								'zero-spam'
							) }
							aria-expanded={ menuOpen ? 'true' : 'false' }
							aria-controls="zs-side"
							onClick={ ( event ) =>
								openMenu( event.currentTarget )
							}
						/>
						<div className="zs-crumb">
							{ __( 'Zero Spam', 'zero-spam' ) } /{ ' ' }
							<b>{ crumb || titles[ area ] }</b>
						</div>
						<span className="zs-grow" />
						{ ! network && 'overview' !== area && modules && (
							<>
								<span
									className={ cx(
										'zs-tdot',
										'warn' === status.tone && 'zs-tdot-warn'
									) }
									role="img"
									aria-label={ sprintf(
										/* translators: %s: status, such as All checks active */
										__( 'Status: %s', 'zero-spam' ),
										status.label
									) }
								/>
								<span className="zs-pill zs-top-status">
									<span
										className={ cx(
											'zs-dot',
											'warn' === status.tone &&
												'zs-dot-warn'
										) }
										aria-hidden="true"
									/>
									<span className="zs-pill-k">
										{ __( 'Status:', 'zero-spam' ) }
									</span>
									<span
										className={
											'ok' === status.tone
												? 'zs-ok'
												: 'zs-warn-t'
										}
									>
										{ status.label }
									</span>
								</span>
							</>
						) }
						{ ! network && (
							<span className="zs-lvl-slot">
								<LevelChip />
							</span>
						) }
						<ThemeButton className="zs-top-theme" />
					</header>
					<div className="zs-content" id="zs-content" tabIndex={ -1 }>
						{ children }
					</div>
				</div>

				{ ! network && (
					<nav
						className="zs-bnav"
						aria-label={ __( 'Zero Spam sections', 'zero-spam' ) }
					>
						{ [ 'overview', 'activity', 'rules', 'protection' ].map(
							( key ) => (
								<a
									key={ key }
									href={ areaUrl( key ) }
									aria-current={
										key === area ? 'page' : undefined
									}
								>
									<Icon name={ ICONS[ key ] } />
									{ titles[ key ] }
								</a>
							)
						) }
						<button
							ref={ moreBtn }
							type="button"
							aria-controls="zs-side"
							aria-expanded={ menuOpen ? 'true' : 'false' }
							onClick={ ( event ) =>
								openMenu( event.currentTarget )
							}
						>
							<Icon name="more" />
							{ __( 'More', 'zero-spam' ) }
						</button>
					</nav>
				) }
			</div>
		</>
	);
}
