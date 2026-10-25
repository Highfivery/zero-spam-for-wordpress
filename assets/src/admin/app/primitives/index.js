/**
 * Small building blocks shared by every area: icons, buttons, badges,
 * chips, the switch, tiles and the skip link. Styles: styles/primitives.css.
 */

/**
 * WordPress dependencies
 */
import { forwardRef } from '@wordpress/element';

/**
 * Joins class names, skipping empty ones.
 *
 * @param {...(string|false|null|undefined)} names Class names.
 * @return {string} Class attribute.
 */
export const cx = ( ...names ) => names.filter( Boolean ).join( ' ' );

/**
 * A UI icon from the sprite (#zs-i-{name}); always decorative.
 *
 * @param {Object} props             Props.
 * @param {string} props.name        Icon name.
 * @param {string} [props.size]      sm or lg.
 * @param {string} [props.className] Extra classes.
 */
export function Icon( { name, size, className } ) {
	return (
		<svg
			className={ cx(
				'zs-icon',
				size && `zs-icon-${ size }`,
				className
			) }
			aria-hidden="true"
			focusable="false"
		>
			<use href={ `#zs-i-${ name }` } />
		</svg>
	);
}

/**
 * Button: primary (40px), secondary and ghost (36px), sm (28px), icon-only.
 * Renders a link when `href` is given.
 */
export const Button = forwardRef( function ButtonRender(
	{
		variant = 'primary',
		size,
		icon,
		iconAfter,
		iconOnly = false,
		href,
		className,
		children,
		type = 'button',
		external = false,
		...rest
	},
	ref
) {
	const classes = cx(
		'zs-btn',
		'primary' !== variant && `zs-btn-${ variant }`,
		size && `zs-btn-${ size }`,
		iconOnly && 'zs-btn-icon',
		className
	);
	const content = (
		<>
			{ icon && <Icon name={ icon } size="sm" /> }
			{ children }
			{ iconAfter && <Icon name={ iconAfter } size="sm" /> }
		</>
	);

	if ( href ) {
		return (
			<a
				ref={ ref }
				className={ classes }
				href={ href }
				{ ...( external
					? { target: '_blank', rel: 'noopener noreferrer' }
					: {} ) }
				{ ...rest }
			>
				{ content }
			</a>
		);
	}

	return (
		<button ref={ ref } className={ classes } type={ type } { ...rest }>
			{ content }
		</button>
	);
} );

/**
 * Badge (20px pill): neutral, ok, warn, danger, info, enh.
 *
 * @param {Object}  props             Props.
 * @param {string}  [props.tone]      Tone.
 * @param {string}  [props.icon]      Icon.
 * @param {unknown} props.children    Label.
 * @param {string}  [props.className] Extra classes.
 */
export function Badge( { tone, icon, children, className } ) {
	return (
		<span
			className={ cx(
				'zs-badge',
				tone && `zs-badge-${ tone }`,
				className
			) }
		>
			{ icon && <Icon name={ icon } /> }
			{ children }
		</span>
	);
}

/**
 * Toggle chip (28px; 44px on phones) with aria-pressed.
 *
 * @param {Object}                          props          Props.
 * @param {boolean}                         props.pressed  Selected.
 * @param {(...args: unknown[]) => unknown} props.onClick  Click.
 * @param {unknown}                         props.children Label.
 */
export function Chip( { pressed, onClick, children, ...rest } ) {
	return (
		<button
			type="button"
			className="zs-chip"
			aria-pressed={ pressed ? 'true' : 'false' }
			onClick={ onClick }
			{ ...rest }
		>
			{ children }
		</button>
	);
}

/**
 * The switch: a 44×26 pill with an invisible 44px hit band (role="switch").
 *
 * @param {Object}                          props               Props.
 * @param {boolean}                         props.checked       On.
 * @param {(...args: unknown[]) => unknown} props.onChange      Receives the new value.
 * @param {string}                          [props.labelledBy]  ID of the visible label.
 * @param {string}                          [props.describedBy] ID of the status text.
 * @param {string}                          [props.label]       Accessible name when there is no visible label.
 * @param {boolean}                         [props.disabled]    Disabled.
 * @param {string}                          [props.id]          ID.
 */
export function Switch( {
	checked,
	onChange,
	labelledBy,
	describedBy,
	label,
	disabled = false,
	id,
} ) {
	return (
		<button
			type="button"
			role="switch"
			id={ id }
			className="zs-switch"
			aria-checked={ checked ? 'true' : 'false' }
			aria-labelledby={ labelledBy }
			aria-describedby={ describedBy }
			aria-label={ labelledBy ? undefined : label }
			disabled={ disabled }
			onClick={ () => onChange( ! checked ) }
		/>
	);
}

/**
 * A tile (card) on the wall. `span` is its width in the 12-column grid.
 *
 * @param {Object}  props              Props.
 * @param {number}  [props.span]       Columns (12, 8, 7, 6, 5, 4).
 * @param {string}  [props.className]  Extra classes.
 * @param {string}  [props.labelledBy] Heading ID (makes it a region).
 * @param {string}  [props.as]         Element.
 * @param {unknown} props.children     Content.
 */
export function Tile( {
	span = 12,
	className,
	labelledBy,
	as: Tag = 'section',
	children,
	...rest
} ) {
	return (
		<Tag
			className={ cx( 'zs-tile', `zs-s${ span }`, className ) }
			aria-labelledby={ labelledBy }
			{ ...rest }
		>
			{ children }
		</Tag>
	);
}

/**
 * A tile's header: eyebrow, heading and an optional action on the right.
 *
 * @param {Object}  props             Props.
 * @param {string}  props.eyebrow     Eyebrow.
 * @param {string}  props.id          Heading ID.
 * @param {unknown} props.title       Heading.
 * @param {unknown} [props.aside]     Right-hand content.
 * @param {boolean} [props.focusable] The heading can take focus (after a dismiss).
 */
export function TileHead( { eyebrow, id, title, aside, focusable = false } ) {
	return (
		<div className="zs-tile-h">
			<div>
				{ eyebrow && <div className="zs-eyebrow">{ eyebrow }</div> }
				<h2
					id={ id }
					className="zs-tile-title"
					tabIndex={ focusable ? -1 : undefined }
				>
					{ title }
				</h2>
			</div>
			{ aside }
		</div>
	);
}

/**
 * Skip link to the area's content.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.target   ID to jump to.
 * @param {unknown} props.children Label.
 */
export function SkipLink( { target, children } ) {
	return (
		<a
			className="zs-skip"
			href={ `#${ target }` }
			onClick={ ( event ) => {
				const el = document.getElementById( target );
				if ( el ) {
					event.preventDefault();
					el.focus();
					el.scrollIntoView( { block: 'start' } );
				}
			} }
		>
			{ children }
		</a>
	);
}

/**
 * An external link with an icon and a hidden "(opens in a new tab)".
 *
 * @param {Object}  props             Props.
 * @param {string}  props.href        Address.
 * @param {unknown} props.children    Label.
 * @param {string}  [props.className] Extra classes.
 * @param {string}  [props.newTab]    Hidden suffix.
 */
export function ExtLink( { href, children, className, newTab } ) {
	return (
		<a
			className={ className }
			href={ href }
			target="_blank"
			rel="noopener noreferrer"
		>
			{ children }
			<Icon name="ext" size="sm" />
			{ newTab && <span className="zs-sr-only">{ newTab }</span> }
		</a>
	);
}
