/**
 * The Compare levels drawer (prototype v10–v17), without prices: plans are
 * described by who they are for, and "View plans on zerospam.org" opens the
 * plans page. Members see their plan and what comes next instead.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from './context';
import { Button, Icon } from './primitives';
import { Drawer } from './primitives/drawer';
import { num, day } from '../data/format';
import { areaUrl, utm } from '../data/routes';

const plans = () => [
	{
		id: 'essentials',
		name: __( 'Essentials', 'zero-spam' ),
		who: __( 'For a single site', 'zero-spam' ),
		points: [
			__( 'Real-time IP and email reputation', 'zero-spam' ),
			__( 'Threat confidence and source intelligence', 'zero-spam' ),
			__( 'Activity history', 'zero-spam' ),
		],
	},
	{
		id: 'business',
		name: __( 'Business', 'zero-spam' ),
		who: __( 'For growing sites', 'zero-spam' ),
		points: [
			__( 'Higher query allowance', 'zero-spam' ),
			__( 'Everything in Essentials', 'zero-spam' ),
			__( 'IP and email abuse reports', 'zero-spam' ),
		],
		popular: true,
	},
	{
		id: 'platform',
		name: __( 'Platform', 'zero-spam' ),
		who: __( 'For agencies and networks', 'zero-spam' ),
		points: [
			__( 'Multiple sites', 'zero-spam' ),
			__( 'Highest query allowance', 'zero-spam' ),
			__( 'Priority support', 'zero-spam' ),
		],
	},
];

const adds = () => [
	[
		'hub',
		__( 'Threat network reputation for IPs and emails', 'zero-spam' ),
		__(
			'Every visitor and email checked in real time against the network.',
			'zero-spam'
		),
	],
	[
		'mail',
		__( 'Blocks reported spam emails', 'zero-spam' ),
		__(
			'Addresses reported by other sites are stopped at the form.',
			'zero-spam'
		),
	],
	[
		'shield',
		__( 'Stops credential stuffing and card testing', 'zero-spam' ),
		__(
			'Known attackers never reach your login or checkout.',
			'zero-spam'
		),
	],
	[
		'form',
		__( 'Detailed IP and email reports', 'zero-spam' ),
		__( "See an address's history across the network.", 'zero-spam' ),
	],
];

const comparison = () => [
	[
		__( 'Hidden traps and browser checks on every form', 'zero-spam' ),
		true,
		true,
	],
	[
		__( 'Comment, login, registration and form protection', 'zero-spam' ),
		true,
		true,
	],
	[
		__( 'Stop Forum Spam and Project Honeypot lookups', 'zero-spam' ),
		true,
		true,
	],
	[
		__( 'Your own IP, location, email and word rules', 'zero-spam' ),
		true,
		true,
	],
	[
		__(
			'Zero Spam IP and email reputation from 30,000+ sites',
			'zero-spam'
		),
		false,
		true,
	],
	[
		__( 'Blocks email addresses reported for spam', 'zero-spam' ),
		false,
		true,
	],
	[
		__(
			'Helps stop DDoS attempts and stolen credit card testing',
			'zero-spam'
		),
		false,
		true,
	],
	[ __( 'Detailed IP and email reports', 'zero-spam' ), false, true ],
	[ __( 'API usage monitoring and alerts', 'zero-spam' ), false, true ],
];

const roadmap = () => [
	[
		'hub',
		__( 'MCP integration', 'zero-spam' ),
		__(
			"Connect Zero Spam's threat data to your AI tools and agents.",
			'zero-spam'
		),
		true,
	],
	[
		'activity',
		__( 'Deeper AI analysis', 'zero-spam' ),
		__(
			'Pattern detection across submissions, sign-up bursts and bot behaviour.',
			'zero-spam'
		),
		false,
	],
	[
		'form',
		__( 'Threat analysis reports', 'zero-spam' ),
		__(
			"Weekly intelligence on what's hitting your site and why.",
			'zero-spam'
		),
		true,
	],
	[
		'network',
		__( 'More integrations', 'zero-spam' ),
		__(
			'New form, commerce and membership plugins as they ship.',
			'zero-spam'
		),
		false,
	],
];

/**
 * @param {Object}  props          Props.
 * @param {boolean} props.enhanced The site is on Enhanced.
 */
function Roadmap( { enhanced } ) {
	return (
		<section className="zs-cd-sec" aria-labelledby="zs-rm-h">
			<div className="zs-cd-sh">
				<h3 id="zs-rm-h">
					{ enhanced
						? __( "What's next for you", 'zero-spam' )
						: __( 'Coming to Enhanced', 'zero-spam' ) }
				</h3>
				<span className="zs-rm-note">
					{ __( 'Roadmap · subject to change', 'zero-spam' ) }
				</span>
			</div>
			<div className="zs-rm-grid">
				{ roadmap().map( ( [ icon, title, text, early ] ) => (
					<article className="zs-rm-card" key={ title }>
						<div className="zs-rm-top">
							<span className="zs-rm-ico">
								<Icon name={ icon } />
							</span>
							<span
								className={
									enhanced || early
										? 'zs-rm-badge zs-rm-early'
										: 'zs-rm-badge'
								}
							>
								{ enhanced || early
									? __( 'Early access', 'zero-spam' )
									: __( 'Coming soon', 'zero-spam' ) }
							</span>
						</div>
						<b>{ title }</b>
						<p>{ text }</p>
					</article>
				) ) }
			</div>
			<p className="zs-rm-foot">
				{ enhanced
					? __(
							'You get these first, as part of your subscription.',
							'zero-spam'
						)
					: __( 'Subscribers get new features first.', 'zero-spam' ) }
			</p>
		</section>
	);
}

/**
 * The drawer.
 */
export function CompareDrawer() {
	const { compare, closeCompare, enhanced, license } = useApp();
	const open = null !== compare;
	const source = compare || 'app';
	const q = license?.queries;

	const head = enhanced ? (
		<div className="zs-cd-hero zs-dark-block">
			<p className="zs-zc-eyebrow">
				{ __( 'Protection levels', 'zero-spam' ) }
			</p>
			<h2 id="zs-cd-title" className="zs-cd-h">
				{ license?.plan?.name
					? sprintf(
							/* translators: %s: plan name, such as Business */
							__( "You're on %s.", 'zero-spam' ),
							license.plan.name
						)
					: __( "You're on Enhanced.", 'zero-spam' ) }
				<span>{ __( 'Thank you.', 'zero-spam' ) }</span>
			</h2>
			<p className="zs-cd-lead">
				{ __(
					"Every visitor is checked against Zero Spam's network, and your subscription funds the free plugin.",
					'zero-spam'
				) }
			</p>
			<ul className="zs-cd-proof">
				{ q && (
					<li>
						{ createInterpolateElement(
							sprintf(
								/* translators: %s: number of queries */
								__( '<b>%s</b> queries left', 'zero-spam' ),
								num( q.remaining )
							),
							{ b: <b /> }
						) }
					</li>
				) }
				{ license?.reset_date && (
					<li>
						{ createInterpolateElement(
							sprintf(
								/* translators: %s: date */
								__( 'Resets <b>%s</b>', 'zero-spam' ),
								day( license.reset_date )
							),
							{ b: <b /> }
						) }
					</li>
				) }
			</ul>
		</div>
	) : (
		<div className="zs-cd-hero zs-dark-block">
			<p className="zs-zc-eyebrow">
				{ __( 'Protection levels', 'zero-spam' ) }
			</p>
			<h2 id="zs-cd-title" className="zs-cd-h">
				{ __( 'Protect smarter.', 'zero-spam' ) }
				<span>{ __( 'Join the network.', 'zero-spam' ) }</span>
			</h2>
			<p className="zs-cd-lead">
				{ __(
					"Enhanced checks every visitor against Zero Spam's own reputation network, built from detections on 30,000+ sites, on top of everything Basic already does.",
					'zero-spam'
				) }
			</p>
			<ul
				className="zs-cd-proof"
				aria-label={ __( 'Network at a glance', 'zero-spam' ) }
			>
				<li>
					<span className="zs-pdot" aria-hidden="true" />
					{ createInterpolateElement(
						__( '<b>120M+</b> records', 'zero-spam' ),
						{ b: <b /> }
					) }
				</li>
				<li>
					<span className="zs-pdot" aria-hidden="true" />
					{ createInterpolateElement(
						__( '<b>30,000+</b> sites reporting', 'zero-spam' ),
						{ b: <b /> }
					) }
				</li>
				<li>
					<span className="zs-pdot" aria-hidden="true" />
					{ createInterpolateElement(
						__( '<b>&lt;10 ms</b> per check', 'zero-spam' ),
						{ b: <b /> }
					) }
				</li>
			</ul>
		</div>
	);

	const footer = enhanced ? (
		<div className="zs-inline-row">
			<Button variant="secondary" onClick={ closeCompare }>
				{ __( 'Close', 'zero-spam' ) }
			</Button>
		</div>
	) : (
		<>
			<div className="zs-inline-row">
				<Button
					className="zs-cd-primary"
					href={ utm(
						'https://www.zerospam.org/pricing/',
						`compare-${ source }`
					) }
					external
				>
					{ __( 'View plans on zerospam.org', 'zero-spam' ) }{ ' ' }
					<span aria-hidden="true">↗</span>
					<span className="zs-sr-only">
						{ __( '(opens in a new tab)', 'zero-spam' ) }
					</span>
				</Button>
				<Button
					variant="secondary"
					className="zs-cd-ghost"
					href={ areaUrl( 'protection', {
						tab: 'intelligence',
						card: 'zerospam',
					} ) }
				>
					{ __( 'I already have a license key', 'zero-spam' ) }
				</Button>
			</div>
			<p className="zs-hint">
				{ __(
					'Your subscription funds the free plugin.',
					'zero-spam'
				) }
			</p>
		</>
	);

	return (
		<Drawer
			open={ open }
			onClose={ closeCompare }
			labelledBy="zs-cd-title"
			wide
			className="zs-compare"
			footer={ footer }
		>
			{ head }
			{ ! enhanced && (
				<>
					<section className="zs-cd-sec" aria-labelledby="zs-pl-h">
						<div className="zs-cd-sh">
							<h3 id="zs-pl-h">
								{ __(
									'Plans for every site size',
									'zero-spam'
								) }
							</h3>
						</div>
						<div className="zs-pl-row">
							{ plans().map( ( plan ) => (
								<article
									className={
										plan.popular
											? 'zs-plan zs-plan-pop'
											: 'zs-plan'
									}
									key={ plan.id }
									aria-labelledby={ `zs-pl-${ plan.id }` }
								>
									{ plan.popular && (
										<span className="zs-pl-badge">
											{ __(
												'Most popular',
												'zero-spam'
											) }
										</span>
									) }
									<h4 id={ `zs-pl-${ plan.id }` }>
										{ plan.name }
									</h4>
									<p className="zs-pl-q">{ plan.who }</p>
									<ul>
										{ plan.points.map( ( point ) => (
											<li key={ point }>
												<Icon name="check" size="sm" />
												<span>{ point }</span>
											</li>
										) ) }
									</ul>
								</article>
							) ) }
						</div>
						<p className="zs-hint">
							{ __(
								'Compare plans on zerospam.org. A free Explorer key is available for trying it out.',
								'zero-spam'
							) }
						</p>
					</section>
					<section className="zs-cd-sec" aria-labelledby="zs-ad-h">
						<div className="zs-cd-sh">
							<h3 id="zs-ad-h">
								{ __( 'What Enhanced adds', 'zero-spam' ) }
							</h3>
						</div>
						<ul className="zs-ad-list">
							{ adds().map( ( [ icon, title, text ] ) => (
								<li key={ title }>
									<span className="zs-ad-ico">
										<Icon name={ icon } />
									</span>
									<span>
										<b>{ title }</b>
										<span>{ text }</span>
									</span>
								</li>
							) ) }
						</ul>
						<p className="zs-ad-free">
							<Icon name="checkc" size="sm" />
							<span>
								{ __(
									'Included at every level: honeypot and browser checks, Stop Forum Spam, and your own IP, email, word and location rules (country, region, city, postal code).',
									'zero-spam'
								) }
							</span>
						</p>
						<details className="zs-cd-full">
							<summary>
								{ __( 'Full comparison', 'zero-spam' ) }
							</summary>
							<div className="zs-cd-tbl">
								<table className="zs-t zs-cmpt">
									<caption className="zs-sr-only">
										{ __(
											'Basic compared with Enhanced',
											'zero-spam'
										) }
									</caption>
									<thead>
										<tr>
											<th scope="col">
												{ __( 'Feature', 'zero-spam' ) }
											</th>
											<th scope="col">
												{ __( 'Basic', 'zero-spam' ) }
											</th>
											<th scope="col">
												{ __(
													'Enhanced',
													'zero-spam'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ comparison().map(
											( [ feature, basic, enh ] ) => (
												<tr key={ feature }>
													<th scope="row">
														{ feature }
													</th>
													{ [ basic, enh ].map(
														( included, i ) => (
															<td key={ i }>
																{ included ? (
																	<>
																		<Icon
																			name="check"
																			size="sm"
																		/>
																		<span className="zs-sr-only">
																			{ __(
																				'Included',
																				'zero-spam'
																			) }
																		</span>
																	</>
																) : (
																	<>
																		<span aria-hidden="true">
																			—
																		</span>
																		<span className="zs-sr-only">
																			{ __(
																				'Not included',
																				'zero-spam'
																			) }
																		</span>
																	</>
																) }
															</td>
														)
													) }
												</tr>
											)
										) }
									</tbody>
								</table>
							</div>
						</details>
					</section>
				</>
			) }
			<Roadmap enhanced={ enhanced } />
		</Drawer>
	);
}
