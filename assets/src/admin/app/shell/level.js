/**
 * The protection level chip in the top bar: "Basic protection · Upgrade ›"
 * (opens Compare levels) or "Enhanced · Active ✓" with the license popover.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../context';
import { Icon } from '../primitives';
import { num, day, ago } from '../../data/format';

/**
 * The license facts shown in the popover and the sidebar card.
 *
 * @param {Object} license license route answer.
 * @return {Array<[string, string]>} Label and value pairs.
 */
export function licenseFacts( license ) {
	if ( ! license ) {
		return [];
	}
	const rows = [];
	if ( license.plan?.name ) {
		rows.push( [ __( 'Plan', 'zero-spam' ), license.plan.name ] );
	}
	if ( license.queries ) {
		rows.push( [
			__( 'Queries left', 'zero-spam' ),
			sprintf(
				/* translators: 1: queries left, 2: queries in the plan */
				__( '%1$s of %2$s', 'zero-spam' ),
				num( license.queries.remaining ),
				num( license.queries.limit )
			),
		] );
	}
	if ( license.reset_date ) {
		rows.push( [
			__( 'Quota resets', 'zero-spam' ),
			day( license.reset_date ),
		] );
	}
	const network = {
		valid: __( 'Connected', 'zero-spam' ),
		quota_exceeded: __( 'Connected · no queries left', 'zero-spam' ),
		unreachable: __( 'Not reachable right now', 'zero-spam' ),
	}[ license.status ];
	if ( network ) {
		rows.push( [ __( 'Network', 'zero-spam' ), network ] );
	}
	if ( license.checked_at ) {
		rows.push( [
			__( 'Last checked', 'zero-spam' ),
			ago(
				Math.max(
					0,
					Math.floor(
						( Date.now() - license.checked_at * 1000 ) / 60000
					)
				)
			),
		] );
	}
	return rows;
}

/**
 * @param {Object} props         Props.
 * @param {string} [props.where] "top" or "sheet" (IDs must differ).
 */
export function LevelChip( { where = 'top' } ) {
	const { enhanced, license, licenseLoaded, openCompare } = useApp();
	const [ open, setOpen ] = useState( false );
	const wrap = useRef( null );
	const button = useRef( null );
	const timer = useRef( null );
	// Opened by hovering (a click then keeps it open) or by a click (a second click closes it).
	const pinned = useRef( false );
	const popId = `zs-lvl-pop-${ where }`;
	const tipId = `zs-lvl-tip-${ where }`;

	useEffect( () => {
		if ( ! open ) {
			pinned.current = false;
			return undefined;
		}
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				event.stopPropagation();
				setOpen( false );
				button.current?.focus();
			}
		};
		const onDown = ( event ) => {
			if ( wrap.current && ! wrap.current.contains( event.target ) ) {
				setOpen( false );
			}
		};
		document.addEventListener( 'keydown', onKey, true );
		document.addEventListener( 'pointerdown', onDown );
		return () => {
			document.removeEventListener( 'keydown', onKey, true );
			document.removeEventListener( 'pointerdown', onDown );
		};
	}, [ open ] );

	if ( ! licenseLoaded ) {
		return <span className="zs-lvl zs-lvl-loading" aria-hidden="true" />;
	}

	if ( ! enhanced ) {
		return (
			<div className="zs-lvl">
				<span className="zs-lvl-chip">
					<span className="zs-lvl-long">
						{ __( 'Basic protection', 'zero-spam' ) }
					</span>
					<span className="zs-lvl-short" aria-hidden="true">
						{ __( 'Basic', 'zero-spam' ) }
					</span>
				</span>
				<button
					className="zs-lvl-up"
					type="button"
					aria-describedby={ tipId }
					onClick={ () => openCompare( 'header' ) }
				>
					{ __( 'Upgrade', 'zero-spam' ) }
					<Icon name="chev" size="sm" />
				</button>
				<span className="zs-lvl-tip" role="tooltip" id={ tipId }>
					{ __(
						'Enhanced adds the global threat network',
						'zero-spam'
					) }
				</span>
			</div>
		);
	}

	const facts = licenseFacts( license );
	const show = () => {
		window.clearTimeout( timer.current );
		setOpen( true );
	};
	const hideSoon = () => {
		window.clearTimeout( timer.current );
		timer.current = window.setTimeout( () => {
			if (
				! wrap.current?.contains(
					wrap.current.ownerDocument.activeElement
				)
			) {
				setOpen( false );
			}
		}, 250 );
	};

	return (
		<div
			className="zs-lvl"
			ref={ wrap }
			onMouseEnter={ show }
			onMouseLeave={ hideSoon }
			onBlur={ ( event ) => {
				if ( ! wrap.current?.contains( event.relatedTarget ) ) {
					setOpen( false );
				}
			} }
		>
			<button
				ref={ button }
				className="zs-lvl-chip zs-lvl-enh"
				type="button"
				aria-expanded={ open ? 'true' : 'false' }
				aria-controls={ popId }
				onClick={ () => {
					if ( open && pinned.current ) {
						pinned.current = false;
						setOpen( false );
					} else {
						pinned.current = true;
						setOpen( true );
					}
				} }
			>
				<Icon name="checkc" size="sm" />
				<span>
					{ 'quota_exceeded' === license?.status
						? __( 'Enhanced · No queries left', 'zero-spam' )
						: __( 'Enhanced · Active', 'zero-spam' ) }
				</span>
			</button>
			<div
				className="zs-lvl-pop"
				id={ popId }
				role="dialog"
				aria-label={ __( 'License health', 'zero-spam' ) }
				hidden={ ! open }
			>
				<b>{ __( 'License health', 'zero-spam' ) }</b>
				<dl className="zs-kv">
					{ facts.map( ( [ k, v ] ) => (
						<div key={ k } className="zs-kv-row">
							<dt>{ k }</dt>
							<dd>{ v }</dd>
						</div>
					) ) }
				</dl>
			</div>
		</div>
	);
}
