<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Icons to this file name.
/**
 * Inline SVG icons from the plugin's sprite.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Prints symbols of assets/dist/sprite.svg (built from
 * assets/src/admin/icons/sprite.svg) as standalone inline <svg> elements, for
 * screens rendered in PHP outside the admin app, such as the dashboard
 * widget: one icon costs only its own markup, and no sprite or <use> is
 * needed.
 *
 * Icons are decorative (aria-hidden, not focusable); put the meaning in text
 * next to them.
 *
 * @since 6.0.0
 */
final class Icons {

	/**
	 * Symbols read from the sprite in this request, by ID: viewBox, extra
	 * attributes and inner markup.
	 *
	 * @var array<string, array{viewBox: string, attributes: string, body: string}>|null
	 */
	private static ?array $symbols = null;

	/**
	 * One symbol as an inline SVG, or an empty string for an unknown ID.
	 *
	 * @param string $id      Symbol ID, for example zs-i-refresh or zs-mark-flat.
	 * @param string $classes Classes of the <svg> (zs-icon for the stroked UI icons).
	 * @param int    $width   Width in pixels, or 0 to leave the size to CSS.
	 * @param int    $height  Height in pixels, or 0 to leave the size to CSS.
	 * @return string Markup, already escaped.
	 */
	public static function svg( string $id, string $classes = '', int $width = 0, int $height = 0 ): string {
		$symbols = self::symbols();

		if ( ! isset( $symbols[ $id ] ) ) {
			return '';
		}

		$symbol = $symbols[ $id ];
		$svg    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . esc_attr( $symbol['viewBox'] ) . '"';

		if ( '' !== $classes ) {
			$svg .= ' class="' . esc_attr( $classes ) . '"';
		}

		if ( $width > 0 && $height > 0 ) {
			$svg .= ' width="' . $width . '" height="' . $height . '"';
		}

		$svg .= $symbol['attributes'] . ' aria-hidden="true" focusable="false">' . $symbol['body'] . '</svg>';

		return wp_kses( $svg, self::allowed_html() );
	}

	/**
	 * Forgets the symbols read in this request (tests).
	 */
	public static function reset_cache(): void {
		self::$symbols = null;
	}

	/**
	 * The sprite's symbols, read once per request.
	 *
	 * @return array<string, array{viewBox: string, attributes: string, body: string}>
	 */
	private static function symbols(): array {
		if ( null !== self::$symbols ) {
			return self::$symbols;
		}

		self::$symbols = array();
		$file          = ZEROSPAM_PATH . 'assets/dist/sprite.svg';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own built sprite from disk.
		$sprite = is_readable( $file ) ? (string) file_get_contents( $file ) : '';

		if ( preg_match_all( '#<symbol id="([\w-]+)" viewBox="([\d. ]+)"([^>]*)>(.*?)</symbol>#s', $sprite, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				self::$symbols[ $match[1] ] = array(
					'viewBox'    => $match[2],
					'attributes' => $match[3],
					'body'       => trim( (string) preg_replace( '/\s+/', ' ', $match[4] ) ),
				);
			}
		}

		return self::$symbols;
	}

	/**
	 * Elements and attributes an icon may contain.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_html(): array {
		$shape = array(
			'class'          => true,
			'd'              => true,
			'x'              => true,
			'y'              => true,
			'cx'             => true,
			'cy'             => true,
			'r'              => true,
			'rx'             => true,
			'ry'             => true,
			'width'          => true,
			'height'         => true,
			'fill'           => true,
			'fill-opacity'   => true,
			'opacity'        => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'stroke-linecap' => true,
			'transform'      => true,
			'points'         => true,
			'x1'             => true,
			'x2'             => true,
			'y1'             => true,
			'y2'             => true,
			'data-i'         => true,
			'style'          => true,
		);

		return array(
			'svg'      => array(
				'xmlns'           => true,
				'viewbox'         => true,
				'class'           => true,
				'width'           => true,
				'height'          => true,
				'aria-hidden'     => true,
				'focusable'       => true,
				'shape-rendering' => true,
				'fill'            => true,
			),
			'g'        => $shape,
			'path'     => $shape,
			'rect'     => $shape,
			'circle'   => $shape,
			'ellipse'  => $shape,
			'line'     => $shape,
			'polyline' => $shape,
			'polygon'  => $shape,
		);
	}
}
