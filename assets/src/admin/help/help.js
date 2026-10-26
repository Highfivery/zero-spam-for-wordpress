/**
 * Help (prototype v18): the guides, support and the review ask, news and
 * security notices from zerospam.org, the Follow links, the protection
 * level (the member card on Enhanced) and the developer reference.
 *
 * Everything on it is preloaded (Admin_App::HELP_PRELOADS and the shell's
 * news), so the page makes no request of its own on load.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import {
	Badge,
	Button,
	Chip,
	ExtLink,
	Icon,
	Tile,
	TileHead,
} from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { FOLLOW, SocialIcon } from '../app/shell/side-extra';
import { roadmap } from '../app/compare';
import { boot } from '../data/api';
import { ago, day, parseDate } from '../data/format';
import { areaUrl, utm } from '../data/routes';
import { copyText } from './copy';
import { DocsTile } from './docs';
import { LINKS, cliCommands, commandParts, hooks, restRoutes } from './lists';
import {
	filterNews,
	displayCategory,
	newsCategories,
	newsView,
} from './news-model';

const NEW_TAB = () => __( '(opens in a new tab)', 'zero-spam' );

/**
 * The ?section= of the address (news, or a guide's ID).
 *
 * @return {string} Section.
 */
const readSection = () => {
	const value = new URLSearchParams( window.location.search ).get(
		'section'
	);
	return value && /^[a-z0-9_-]{1,64}$/.test( value ) ? value : '';
};

/**
 * Talk to a person, and Rate Zero Spam.
 */
function SupportTile() {
	const { enhanced } = useApp();
	const { toast } = useFeedback();
	const report = [
		sprintf(
			/* translators: 1: Zero Spam version, 2: WordPress version, 3: PHP version */
			__( 'Zero Spam %1$s · WordPress %2$s · PHP %3$s', 'zero-spam' ),
			boot.version,
			boot.environment.wordpress,
			boot.environment.php
		),
		enhanced ? __( 'Enhanced', 'zero-spam' ) : __( 'Basic', 'zero-spam' ),
		sprintf(
			/* translators: %s: where visitor IP addresses come from, such as cloudflare */
			__( 'IP source: %s', 'zero-spam' ),
			boot.environment.ipSource
		),
	].join( ' · ' );

	return (
		<Tile span={ 5 } labelledBy="zs-hs-h" className="zs-support">
			<TileHead
				eyebrow={ __( 'Support', 'zero-spam' ) }
				id="zs-hs-h"
				title={ __( 'Talk to a person', 'zero-spam' ) }
			/>
			<p className="zs-hint zs-help-p">
				{ __(
					'Include your system report so we can help faster. It lists versions and settings, never visitor data or keys.',
					'zero-spam'
				) }
			</p>
			<div className="zs-inline-row">
				<Button href={ LINKS.forum } external iconAfter="ext">
					{ __( 'Open the support forum', 'zero-spam' ) }
					<span className="zs-sr-only">{ NEW_TAB() }</span>
				</Button>
				<Button
					variant="secondary"
					icon="copy"
					onClick={ () => copyText( report, toast ) }
				>
					{ __( 'Copy system report', 'zero-spam' ) }
				</Button>
			</div>
			<p className="zs-hint">
				<ExtLink
					href={ utm( LINKS.contact, 'help-support' ) }
					newTab={ NEW_TAB() }
				>
					{ __(
						'Or write to the team on zerospam.org',
						'zero-spam'
					) }
				</ExtLink>
			</p>
			<div className="zs-sect zs-help-rate">
				<h3 className="zs-h3">
					{ __( 'Rate Zero Spam', 'zero-spam' ) }
				</h3>
				<p className="zs-hint zs-help-p">
					{ __(
						'A review on WordPress.org helps other site owners find free spam protection.',
						'zero-spam'
					) }
				</p>
				<div>
					<Button variant="secondary" href={ LINKS.review } external>
						{ __( 'Rate Zero Spam', 'zero-spam' ) }{ ' ' }
						<span aria-hidden="true">★</span>
						<span className="zs-sr-only">{ NEW_TAB() }</span>
					</Button>
				</div>
			</div>
		</Tile>
	);
}

/**
 * News and security notices (eight articles, filterable by category).
 */
function NewsTile() {
	const { newsState } = useApp();
	const { toast } = useFeedback();
	const [ cat, setCat ] = useState( '' );
	const view = newsView( { enabled: boot.newsEnabled, state: newsState } );
	const items = Array.isArray( newsState.data ) ? newsState.data : [];
	const categories = newsCategories( items );
	const list = filterNews( items, cat );
	const fetched = parseDate(
		newsState.headers?.[ 'x-zerospam-news-fetched' ]
	);
	const updated = fetched
		? ago(
				Math.max(
					0,
					Math.floor( ( Date.now() - fetched.getTime() ) / 60000 )
				)
			)
		: '';

	return (
		<Tile
			span={ 12 }
			labelledBy="zs-hn-h"
			className="zs-help-news"
			id="zs-help-news"
		>
			<TileHead
				eyebrow={ __( 'From zerospam.org', 'zero-spam' ) }
				id="zs-hn-h"
				title={ __( 'News and security notices', 'zero-spam' ) }
				focusable
				aside={
					'disabled' !== view.state && (
						<span className="zs-news-upd">
							{ updated && (
								<span className="zs-hint" aria-live="polite">
									{ sprintf(
										/* translators: %s: relative time, such as 2 h ago */
										__( 'Updated %s', 'zero-spam' ),
										updated.toLowerCase()
									) }
								</span>
							) }
							<Button
								variant="ghost"
								size="sm"
								icon="refresh"
								disabled={ newsState.loading }
								onClick={ () =>
									newsState
										.reload()
										.then( () =>
											toast(
												__(
													'News is up to date',
													'zero-spam'
												)
											)
										)
								}
							>
								{ __( 'Refresh', 'zero-spam' ) }
							</Button>
						</span>
					)
				}
			/>
			{ 'disabled' === view.state && (
				<div className="zs-empty">
					<b>{ __( 'Zero Spam news is off', 'zero-spam' ) }</b>
					<span className="zs-hint">
						{ __(
							'Nothing is downloaded from zerospam.org while it is off.',
							'zero-spam'
						) }{ ' ' }
						<a
							href={ areaUrl( 'settings', {
								tab: 'general',
								section: 'news',
							} ) }
						>
							{ __(
								'Turn it on in Settings › General',
								'zero-spam'
							) }
						</a>
					</span>
				</div>
			) }
			{ 'unavailable' === view.state && (
				<div className="zs-empty" role="status">
					<b>
						{ __(
							"The news isn't available right now",
							'zero-spam'
						) }
					</b>
					<span className="zs-hint">
						{ __(
							'zerospam.org could not be reached. Try Refresh in a little while.',
							'zero-spam'
						) }
					</span>
				</div>
			) }
			{ 'loading' === view.state && (
				<p className="zs-hint" role="status">
					{ __( 'Loading the news…', 'zero-spam' ) }
				</p>
			) }
			{ 'empty' === view.state && (
				<p className="zs-hint">
					{ __( 'No articles yet.', 'zero-spam' ) }
				</p>
			) }
			{ 'ready' === view.state && (
				<>
					<p className="zs-hint">
						{ __(
							'Daily research from the Threat Intelligence Log. Posts marked "Security notice" are incidents that may affect WordPress sites.',
							'zero-spam'
						) }
						{ view.stale &&
							' ' +
								__(
									'zerospam.org could not be reached, so this is the last copy.',
									'zero-spam'
								) }
					</p>
					{ categories.length > 1 && (
						<div
							className="zs-chips zs-newschips"
							role="group"
							aria-label={ __(
								'Filter news by category',
								'zero-spam'
							) }
						>
							<Chip
								pressed={ '' === cat }
								onClick={ () => setCat( '' ) }
							>
								{ __( 'All', 'zero-spam' ) }
							</Chip>
							{ categories.map( ( c ) => (
								<Chip
									key={ c }
									pressed={ c === cat }
									onClick={ () => setCat( c ) }
								>
									{ c }
								</Chip>
							) ) }
						</div>
					) }
					<ul className="zs-news">
						{ list.map( ( item ) => (
							<li key={ item.link } className="zs-news-i">
								<div className="zs-news-m">
									{ displayCategory( item ) && (
										<Badge>
											{ displayCategory( item ) }
										</Badge>
									) }
									{ item.security_notice && (
										<Badge tone="warn" icon="warn">
											{ __(
												'Security notice',
												'zero-spam'
											) }
										</Badge>
									) }
									{ item.date && (
										<time
											className="zs-hint"
											dateTime={ item.date }
										>
											{ day( item.date ) }
										</time>
									) }
								</div>
								<a
									className="zs-news-t"
									href={ utm( item.link, 'help-news' ) }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ item.title }
									<span className="zs-sr-only">
										{ __(
											'(opens zerospam.org)',
											'zero-spam'
										) }
									</span>
								</a>
								{ item.excerpt && (
									<p className="zs-hint zs-news-x">
										{ item.excerpt }
									</p>
								) }
							</li>
						) ) }
					</ul>
				</>
			) }
			<div className="zs-inline-row zs-tile-foot">
				<Button
					variant="secondary"
					icon="mail"
					href={ utm( LINKS.articles, 'help-alerts' ) }
					external
				>
					{ __( 'Get alerts in your inbox', 'zero-spam' ) }
					<span className="zs-sr-only">{ NEW_TAB() }</span>
				</Button>
				<ExtLink
					href={ utm( LINKS.articles, 'help-news' ) }
					newTab={ NEW_TAB() }
				>
					{ __( 'All articles', 'zero-spam' ) }
				</ExtLink>
			</div>
		</Tile>
	);
}

/**
 * Follow Zero Spam.
 */
function FollowTile() {
	return (
		<Tile span={ 7 } labelledBy="zs-hf-h" className="zs-follow-tile">
			<TileHead
				eyebrow={ __( 'Social', 'zero-spam' ) }
				id="zs-hf-h"
				title={ __( 'Follow Zero Spam', 'zero-spam' ) }
			/>
			<p className="zs-hint zs-help-p">
				{ __(
					'Plain links, no tracking scripts. New research and plugin releases are posted here first.',
					'zero-spam'
				) }
			</p>
			<ul className="zs-follow">
				{ FOLLOW.map( ( f ) => (
					<li key={ f.key }>
						<a
							className="zs-soc zs-soc-big"
							href={ f.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							<SocialIcon name={ f.key } />
							<span className="zs-soc-txt">
								<b>{ f.name }</b>
								<span className="zs-hint">{ f.handle }</span>
							</span>
							<Icon name="ext" size="sm" />
							<span className="zs-sr-only">{ NEW_TAB() }</span>
						</a>
					</li>
				) ) }
			</ul>
		</Tile>
	);
}

/**
 * The protection level: the member card (Enhanced) or Compare levels.
 */
function LevelTile() {
	const { enhanced, licenseLoaded, openCompare } = useApp();

	if ( ! licenseLoaded ) {
		return <Tile span={ 5 } className="zs-help-level" aria-busy="true" />;
	}

	if ( enhanced ) {
		return (
			<Tile span={ 5 } labelledBy="zs-hl-h" className="zs-help-level">
				<div className="zs-member-card zs-dark-block">
					<p className="zs-zc-eyebrow">
						<Icon name="shield" size="sm" />
						{ __( 'Enhanced member', 'zero-spam' ) }
					</p>
					<h2 id="zs-hl-h" className="zs-tile-title">
						{ __( 'Thank you for being a member', 'zero-spam' ) }
					</h2>
					<p>
						{ __(
							'Your subscription funds the free plugin, and you get new features first.',
							'zero-spam'
						) }
					</p>
					<ul
						className="zs-mc-list"
						aria-label={ __( 'Early access', 'zero-spam' ) }
					>
						{ roadmap().map( ( [ icon, title ] ) => (
							<li key={ title }>
								<Icon name={ icon } size="sm" />
								<span>{ title }</span>
								<span className="zs-rm-badge zs-rm-early">
									{ __( 'Early access', 'zero-spam' ) }
								</span>
							</li>
						) ) }
					</ul>
					<div className="zs-inline-row">
						<a
							className="zs-zc-ghost zs-mc-btn"
							href={ utm( LINKS.contact, 'help' ) }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Contact support', 'zero-spam' ) }
							<span className="zs-sr-only">{ NEW_TAB() }</span>
						</a>
						<button
							className="zs-zc-chip"
							type="button"
							onClick={ () => {
								openCompare( 'help' );
								window.setTimeout( () => {
									const h =
										document.getElementById( 'zs-rm-h' );
									if ( h ) {
										h.tabIndex = -1;
										h.scrollIntoView( { block: 'start' } );
										h.focus( { preventScroll: true } );
									}
								}, 80 );
							} }
						>
							{ __( "What's next", 'zero-spam' ) }{ ' ' }
							<span aria-hidden="true">→</span>
						</button>
					</div>
				</div>
			</Tile>
		);
	}

	return (
		<Tile span={ 5 } labelledBy="zs-hl-h" className="zs-help-level">
			<TileHead
				eyebrow={ __( 'Protection level', 'zero-spam' ) }
				id="zs-hl-h"
				title={ __( "You're on Basic", 'zero-spam' ) }
			/>
			<p className="zs-help-p">
				{ __(
					"Enhanced adds Zero Spam's own IP and email reputation from 30,000+ sites, and its subscriptions fund the free plugin.",
					'zero-spam'
				) }
			</p>
			<div className="zs-tile-foot">
				<Button
					variant="secondary"
					onClick={ () => openCompare( 'help' ) }
				>
					{ __( 'Compare levels', 'zero-spam' ) }
				</Button>
			</div>
		</Tile>
	);
}

/**
 * A reference list: code, one line, and a copy button.
 *
 * @param {Object}                 props       Props.
 * @param {Array<[string,string]>} props.items Code and description.
 * @param {boolean}                [props.cli] Highlight flags.
 * @param {string}                 props.label List name.
 */
function RefList( { items, cli = false, label } ) {
	const { toast } = useFeedback();
	return (
		<ul className="zs-hooks" aria-label={ label }>
			{ items.map( ( [ code, text ] ) => (
				<li key={ code }>
					<code>
						{ cli
							? commandParts( code ).map( ( part, i ) =>
									part.flag ? (
										<span key={ i } className="zs-cli-flag">
											{ part.text }
										</span>
									) : (
										part.text
									)
								)
							: code }
					</code>
					<span className="zs-hooks-d">{ text }</span>
					<Button
						variant="ghost"
						size="sm"
						iconOnly
						icon="copy"
						aria-label={ sprintf(
							/* translators: %s: hook, route or command */
							__( 'Copy %s', 'zero-spam' ),
							code
						) }
						onClick={ () => copyText( code, toast ) }
					/>
				</li>
			) ) }
		</ul>
	);
}

/**
 * Help.
 */
export function Help() {
	const [ section ] = useState( readSection );

	// ?section=news (the sidebar's "All news") or a guide: bring it into view.
	useEffect( () => {
		if ( ! section ) {
			return;
		}
		const target =
			'news' === section
				? document.getElementById( 'zs-hn-h' )
				: document.getElementById( `zs-doc-${ section }` );
		if ( target ) {
			target.scrollIntoView( { block: 'start' } );
			if ( 'news' === section ) {
				target.focus( { preventScroll: true } );
			}
		}
	}, [ section ] );

	return (
		<div className="zs-view zs-help" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ __( 'Help.', 'zero-spam' ) }
						<span>
							{ __( 'Answers, hooks and humans.', 'zero-spam' ) }
						</span>
					</h1>
					<p>
						{ __(
							"Guides written for site owners, with detail for developers. If you're stuck, the people who build Zero Spam answer the support forum.",
							'zero-spam'
						) }
					</p>
				</div>
			</div>
			<div className="zs-areabody" id="zs-help-body">
				<div className="zs-wall">
					<DocsTile open={ 'news' === section ? '' : section } />
					<SupportTile />
					<NewsTile />
					<FollowTile />
					<LevelTile />
					<Tile
						span={ 6 }
						labelledBy="zs-hh-h"
						className="zs-help-ref"
					>
						<TileHead
							eyebrow={ __( 'For developers', 'zero-spam' ) }
							id="zs-hh-h"
							title={ __( 'Hooks and REST', 'zero-spam' ) }
						/>
						<RefList
							items={ hooks() }
							label={ __( 'Hooks', 'zero-spam' ) }
						/>
						<h3 className="zs-h3 zs-help-sub">
							{ __( 'REST routes', 'zero-spam' ) }
						</h3>
						<p className="zs-hint">
							{ __(
								'Under /wp-json/. Administrators only (manage_options); application passwords work.',
								'zero-spam'
							) }
						</p>
						<RefList
							items={ restRoutes() }
							label={ __( 'REST routes', 'zero-spam' ) }
						/>
						<div className="zs-tile-foot">
							<ExtLink
								href={ `${ LINKS.wiki }/Hooks` }
								newTab={ NEW_TAB() }
							>
								{ __(
									'Every hook on the GitHub wiki',
									'zero-spam'
								) }
							</ExtLink>
						</div>
					</Tile>
					<Tile
						span={ 6 }
						labelledBy="zs-hc-h"
						className="zs-help-ref"
					>
						<TileHead
							eyebrow={ __( 'For developers', 'zero-spam' ) }
							id="zs-hc-h"
							title={ __( 'WP-CLI', 'zero-spam' ) }
						/>
						<RefList
							items={ cliCommands() }
							cli
							label={ __( 'WP-CLI commands', 'zero-spam' ) }
						/>
					</Tile>
				</div>
			</div>
		</div>
	);
}
