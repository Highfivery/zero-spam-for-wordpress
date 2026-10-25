<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Sparkline to this file name.
/**
 * Sparkline paths, rendered on the server.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The SVG paths of a sparkline: the approved prototype's spark() generator,
 * ported to PHP. assets/src/widget-refresh/model.js (sparkPaths()) is the same
 * generator in JavaScript; both are tested against the same values. They
 * agree to 0.1 unit: JavaScript's toFixed() and PHP's number_format() may
 * round a rare halfway value differently.
 *
 * Points are spread evenly across the width, the highest value touches the
 * top (2 units of padding) and zero sits on the bottom line; coordinates have
 * one decimal. Draw them in an <svg viewBox="0 0 {width} {height}"
 * preserveAspectRatio="none">.
 *
 * @since 6.0.0
 */
final class Sparkline {

	/**
	 * Default width of the viewBox.
	 *
	 * @var int
	 */
	const WIDTH = 240;

	/**
	 * Default height of the viewBox.
	 *
	 * @var int
	 */
	const HEIGHT = 48;

	/**
	 * The line and the area under it.
	 *
	 * @param array<int|float|string> $values Values, oldest first; negatives count as zero.
	 * @param int                     $width  Width of the viewBox.
	 * @param int                     $height Height of the viewBox.
	 * @return array{line: string, area: string}
	 */
	public static function paths( array $values, int $width = self::WIDTH, int $height = self::HEIGHT ): array {
		$values = array_map(
			static function ( $value ): float {
				return max( 0.0, (float) $value );
			},
			array_values( $values )
		);

		if ( ! $values ) {
			$values = array( 0.0 );
		}

		// One value is a flat line across the whole width.
		if ( 1 === count( $values ) ) {
			$values[] = $values[0];
		}

		// An empty series stays a flat line on the bottom instead of dividing by zero.
		$max   = max( 1.0, max( $values ) );
		$last  = count( $values ) - 1;
		$parts = array();

		foreach ( $values as $i => $value ) {
			$x       = ( $i / $last ) * $width;
			$y       = $height - 2 - ( $value / $max ) * ( $height - 4 );
			$parts[] = ( 0 === $i ? 'M' : 'L' ) . self::coordinate( $x ) . ' ' . self::coordinate( $y );
		}

		$line = implode( '', $parts );

		return array(
			'line' => $line,
			'area' => $line . 'L' . $width . ' ' . $height . 'L0 ' . $height . 'Z',
		);
	}

	/**
	 * A coordinate with one decimal, as JavaScript's toFixed( 1 ) writes it (to 0.1; rounding may differ).
	 *
	 * @param float $value Coordinate.
	 */
	private static function coordinate( float $value ): string {
		$text = number_format( $value, 1, '.', '' );

		return '-0.0' === $text ? '0.0' : $text;
	}
}
