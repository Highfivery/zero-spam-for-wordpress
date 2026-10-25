/**
 * The sidebar's lower block on every screen: the Enhanced Protection card
 * (Basic or member), the two latest posts from zerospam.org, and the
 * Follow row.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useApp } from '../context';
import { Icon } from '../primitives';
import { num, day } from '../../data/format';
import { areaUrl, utm } from '../../data/routes';

export const FOLLOW = [
	{
		key: 'facebook',
		name: 'Facebook',
		handle: '@zerospamorg',
		url: 'https://www.facebook.com/zerospamorg',
	},
	{
		key: 'x',
		name: 'X',
		handle: '@ZeroSpamOrg',
		url: 'https://x.com/zerospamorg',
	},
	{
		key: 'wp',
		name: 'WordPress.org',
		handle: 'zero-spam',
		url: 'https://wordpress.org/plugins/zero-spam/',
	},
	{
		key: 'gh',
		name: 'GitHub',
		handle: 'Highfivery/zero-spam-for-wordpress',
		url: 'https://github.com/Highfivery/zero-spam-for-wordpress',
	},
];

/**
 * Brand icons for the Follow row (not in the UI sprite).
 *
 * @param {Object} props      Props.
 * @param {string} props.name facebook, x, wp or gh.
 */
export function SocialIcon( { name } ) {
	const paths = {
		facebook: (
			<path
				d="M14 8h3V4h-3c-2.8 0-4 1.8-4 4v2H8v4h2v8h4v-8h3l1-4h-4V8.6c0-.4.2-.6.6-.6z"
				className="zs-fill"
			/>
		),
		x: (
			<path
				d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.8-6.3L5.5 21H2.4l7.3-8.3L2 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"
				className="zs-fill"
			/>
		),
		wp: (
			<>
				<circle cx="12" cy="12" r="9" />
				<path d="M4.5 9l4 11M9 6l5.5 14M14 9l3.5 10M19.5 9.5l-2 9" />
			</>
		),
		gh: (
			<path d="M9 19c-4 1.3-4-2-6-2.5M15 21v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12 12 0 0 0-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 9.5c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21" />
		),
	};
	return (
		<svg
			className="zs-icon zs-icon-sm"
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			{ paths[ name ] }
		</svg>
	);
}

/**
 * The Enhanced Protection card (Basic) or the member card (Enhanced).
 */
function LevelCard() {
	const { enhanced, license, licenseLoaded, openCompare } = useApp();

	if ( ! licenseLoaded ) {
		return null;
	}

	if ( enhanced ) {
		const q = license?.queries;
		const left = q ? Math.max( 0, q.remaining ) : null;
		const pct =
			q && q.limit > 0 ? Math.round( ( left / q.limit ) * 100 ) : null;
		return (
			<section
				className="zs-zc zs-zc-member zs-dark-block"
				aria-labelledby="zs-zc-t"
			>
				<p className="zs-zc-eyebrow">
					<Icon name="shield" size="sm" />
					{ __( 'Enhanced member', 'zero-spam' ) }
				</p>
				<h2 className="zs-zc-title zs-zc-one" id="zs-zc-t">
					{ __( 'Enhanced · Active', 'zero-spam' ) }
				</h2>
				<p className="zs-zc-status">
					<span className="zs-pdot" aria-hidden="true" />
					{ 'unreachable' === license?.status
						? __( 'Network not reachable right now', 'zero-spam' )
						: __( 'Protected by the network', 'zero-spam' ) }
				</p>
				{ null !== pct && (
					<>
						<div
							className="zs-zc-bar"
							role="img"
							aria-label={ sprintf(
								/* translators: 1: queries left, 2: queries in the plan */
								__( '%1$s of %2$s queries left', 'zero-spam' ),
								num( left ),
								num( q.limit )
							) }
						>
							<i style={ { width: `${ pct }%` } } />
						</div>
						<p className="zs-zc-mono">
							{ sprintf(
								/* translators: %s: number of queries */
								__( '%s queries left', 'zero-spam' ),
								num( left )
							) }
						</p>
					</>
				) }
				<p className="zs-zc-quiet zs-zc-left">
					{ license?.plan?.name
						? sprintf(
								/* translators: %s: plan name, such as Business */
								__( '%s plan', 'zero-spam' ),
								license.plan.name
							)
						: __( 'Thank you for being a member', 'zero-spam' ) }
				</p>
			</section>
		);
	}

	return (
		<section className="zs-zc zs-dark-block" aria-labelledby="zs-zc-t">
			<p className="zs-zc-eyebrow">
				{ __( 'Enhanced Protection', 'zero-spam' ) }
			</p>
			<h2 className="zs-zc-title" id="zs-zc-t">
				{ __( 'Protect smarter.', 'zero-spam' ) }
			</h2>
			<p className="zs-zc-text">
				{ __(
					'Real-time checks against known bad IPs and emails.',
					'zero-spam'
				) }
			</p>
			<ul
				className="zs-zc-chips"
				aria-label={ __( 'Zero Spam network', 'zero-spam' ) }
			>
				<li>{ __( '120M+ records', 'zero-spam' ) }</li>
				<li>{ __( '<10 ms', 'zero-spam' ) }</li>
			</ul>
			<button
				className="zs-zc-cta"
				type="button"
				onClick={ () => openCompare( 'sidebar' ) }
			>
				{ __( 'Compare levels', 'zero-spam' ) }{ ' ' }
				<span aria-hidden="true">→</span>
			</button>
			<p className="zs-zc-quiet">
				{ __( 'Plans for every site size', 'zero-spam' ) } ·{ ' ' }
				<a
					className="zs-zc-link"
					href={ areaUrl( 'protection', {
						tab: 'intelligence',
						card: 'zerospam',
					} ) }
				>
					{ __( 'Have a key?', 'zero-spam' ) }
				</a>
			</p>
		</section>
	);
}

/**
 * The lower block.
 */
export function SideExtra() {
	const { news, network } = useApp();
	const latest = news.slice( 0, 2 );

	return (
		<div className="zs-side-extra">
			{ ! network && <LevelCard /> }
			{ latest.length > 0 && (
				<section className="zs-sn" aria-labelledby="zs-sn-t">
					<div className="zs-sx-head">
						<h2 className="zs-sx-label" id="zs-sn-t">
							{ __( 'Latest news', 'zero-spam' ) }
						</h2>
						<a
							className="zs-sx-all"
							href={ areaUrl( 'help', { section: 'news' } ) }
						>
							{ __( 'All news', 'zero-spam' ) }{ ' ' }
							<span aria-hidden="true">→</span>
						</a>
					</div>
					<ul>
						{ latest.map( ( item ) => (
							<li key={ item.link }>
								<a
									href={ utm( item.link, 'sidebar-news' ) }
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
								<span className="zs-sn-meta">
									<span
										className={
											item.security_notice
												? 'zs-sn-dot zs-sn-alert'
												: 'zs-sn-dot'
										}
										aria-hidden="true"
									/>
									{ [
										day( item.date ),
										item.categories?.[ 0 ],
									]
										.filter( Boolean )
										.join( ' · ' ) }
									{ item.security_notice && (
										<span className="zs-sn-pill">
											{ __(
												'Security notice',
												'zero-spam'
											) }
										</span>
									) }
								</span>
							</li>
						) ) }
					</ul>
				</section>
			) }
			<section className="zs-sf" aria-labelledby="zs-sf-t">
				<h2 className="zs-sx-label" id="zs-sf-t">
					{ __( 'Follow', 'zero-spam' ) }
				</h2>
				<div className="zs-sf-row">
					{ FOLLOW.map( ( f ) => (
						<a
							key={ f.key }
							className="zs-sf-btn"
							href={ f.url }
							target="_blank"
							rel="noopener noreferrer"
							aria-label={ sprintf(
								/* translators: 1: network name, such as GitHub, 2: account name */
								__(
									'Zero Spam on %1$s, %2$s (opens in a new tab)',
									'zero-spam'
								),
								f.name,
								f.handle
							) }
							data-handle={
								f.handle.length > 18 ? f.name : f.handle
							}
						>
							<SocialIcon name={ f.key } />
						</a>
					) ) }
				</div>
			</section>
		</div>
	);
}
