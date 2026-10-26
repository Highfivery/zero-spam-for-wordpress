/**
 * Network notifications (prototype §9): the weekly summary for the
 * network administrator and the emails site administrators get when
 * network settings change (`network/notifications`). Keys and webhook
 * addresses never appear in those emails.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, Switch } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { request, useResource } from '../data/api';
import { PATHS } from './model';
import { ActionResult, LoadState, errorText } from './parts';

/**
 * The Notifications tab.
 */
export function Notifications() {
	const { toast } = useFeedback();
	const saved = useResource( PATHS.notifications );
	const fromAnswer = ( data ) =>
		data
			? {
					weekly: Boolean( data.weekly ),
					changes: Boolean( data.changes ),
				}
			: null;
	// From the preloaded answer on the first render, so the switches paint at once.
	const [ values, setValues ] = useState( () => fromAnswer( saved.data ) );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );

	useEffect( () => {
		if ( saved.data && null === values ) {
			setValues( fromAnswer( saved.data ) );
		}
	}, [ saved.data, values ] );

	if ( ! saved.data || ! values ) {
		return (
			<LoadState
				res={ saved }
				what={ __(
					'The notification settings could not be loaded.',
					'zero-spam'
				) }
			/>
		);
	}

	const save = () => {
		setBusy( true );
		setResult( null );
		request( { path: PATHS.notifications, method: 'PUT', data: values } )
			.then( ( answer ) => {
				saved.setData( answer );
				toast( __( 'Notification settings saved', 'zero-spam' ) );
			} )
			.catch( ( error ) =>
				setResult( {
					ok: false,
					text: errorText(
						error,
						__(
							'The notification settings could not be saved. Please try again.',
							'zero-spam'
						)
					),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const rows = [
		[
			'weekly',
			__( 'Weekly summary emails', 'zero-spam' ),
			__(
				'Network administrators get a weekly summary with network statistics, locked settings, site changes and recent changes.',
				'zero-spam'
			),
			__( 'Sent to the first super admin', 'zero-spam' ),
		],
		[
			'changes',
			__( 'Settings change notifications', 'zero-spam' ),
			__(
				'Site administrators get an email when network-level settings change. License and API keys are never shown in it. In large networks, turning this off prevents thousands of emails.',
				'zero-spam'
			),
			__( 'Sent to each affected site’s admin email', 'zero-spam' ),
		],
	];

	return (
		<section className="zs-tile" aria-labelledby="zs-nn-h">
			<h2 className="zs-tile-title" id="zs-nn-h">
				{ __( 'Email notifications', 'zero-spam' ) }
			</h2>
			<p className="zs-hint">
				{ __(
					'Control the emails sent to network and site administrators.',
					'zero-spam'
				) }
			</p>
			{ rows.map( ( [ key, title, desc, status ] ) => (
				<div className="zs-setrow" key={ key }>
					<div className="zs-desc">
						<b className="zs-st-t" id={ `zs-nn-${ key }` }>
							{ title }
						</b>
						<p id={ `zs-nn-${ key }-d` }>{ desc }</p>
					</div>
					<div className="zs-ctl">
						<span className="zs-sw-row">
							<Switch
								id={ `zs-nn-${ key }-sw` }
								checked={ values[ key ] }
								labelledBy={ `zs-nn-${ key }` }
								describedBy={ `zs-nn-${ key }-s zs-nn-${ key }-d` }
								onChange={ ( on ) =>
									setValues( { ...values, [ key ]: on } )
								}
							/>
							<span
								className="zs-hint zs-sw-status"
								id={ `zs-nn-${ key }-s` }
							>
								{ values[ key ]
									? status
									: __( 'Off', 'zero-spam' ) }
							</span>
						</span>
					</div>
				</div>
			) ) }
			<ActionResult result={ result } />
			<div>
				<Button disabled={ busy } onClick={ save }>
					{ __( 'Save notification settings', 'zero-spam' ) }
				</Button>
			</div>
		</section>
	);
}
