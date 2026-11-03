<?php
/**
 * Tests for the server-rendered sparkline and the widget's figures.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Core\Admin\Sparkline;
use ZeroSpam\Tests\TestCase;

/**
 * Sparkline::paths() is the prototype's spark() in PHP, and
 * Dashboard_Widget::figures() the widget's headline arithmetic. The same
 * values and expected strings are in assets/src/widget-refresh/test/model.test.js,
 * so the server and a refresh draw and say the same thing.
 *
 * @covers \ZeroSpam\Core\Admin\Sparkline
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget::figures
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget::delta_text
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget::spark_text
 */
class Test_Sparkline extends TestCase {

	/**
	 * Values, size and the expected line (shared with model.test.js).
	 *
	 * @return array<string, array{0: array<int|float>, 1: int, 2: int, 3: string}>
	 */
	public static function data_paths(): array {
		return array(
			'five days'          => array( array( 0, 2, 4, 1, 3 ), 240, 48, 'M0.0 46.0L60.0 24.0L120.0 2.0L180.0 35.0L240.0 13.0' ),
			'thirds round'       => array( array( 1, 2, 3 ), 100, 20, 'M0.0 12.7L50.0 7.3L100.0 2.0' ),
			'empty series'       => array( array(), 240, 48, 'M0.0 46.0L240.0 46.0' ),
			'one value'          => array( array( 5 ), 240, 48, 'M0.0 2.0L240.0 2.0' ),
			'only zeros'         => array( array( 0, 0, 0 ), 240, 48, 'M0.0 46.0L120.0 46.0L240.0 46.0' ),
			'negatives are zero' => array( array( -3, 2 ), 240, 48, 'M0.0 46.0L240.0 2.0' ),
		);
	}

	/**
	 * The line for known values, and the area closed along the bottom.
	 *
	 * @dataProvider data_paths
	 *
	 * @param array<int|float> $values Values.
	 * @param int              $width  Width.
	 * @param int              $height Height.
	 * @param string           $line   Expected line.
	 */
	public function test_paths( array $values, int $width, int $height, string $line ): void {
		$paths = Sparkline::paths( $values, $width, $height );

		$this->assertSame( $line, $paths['line'] );
		$this->assertSame( $line . "L{$width} {$height}L0 {$height}Z", $paths['area'] );
	}

	/**
	 * Thirty days of points spread evenly over 240 units.
	 */
	public function test_thirty_days_span_the_width(): void {
		$line = Sparkline::paths( array_fill( 0, 30, 1 ) )['line'];

		$this->assertStringStartsWith( 'M0.0 2.0L8.3 2.0L16.6 2.0', $line );
		$this->assertStringEndsWith( 'L240.0 2.0', $line );
		$this->assertSame( 29, substr_count( $line, 'L' ) );
	}

	/**
	 * Daily totals, the expected figures and the "Last 7 days" sentence.
	 *
	 * @return array<string, array{0: int[], 1: array<string, int|null>, 2: string, 3: string}>
	 */
	public static function data_figures(): array {
		return array(
			'up by half'              => array(
				array_merge( array_fill( 0, 16, 1 ), array_fill( 0, 7, 2 ), array_fill( 0, 7, 3 ) ),
				array(
					'total'    => 51,
					'week'     => 21,
					'previous' => 14,
					'delta'    => 7,
					'percent'  => 50,
				),
				'up',
				'Last 7 days: 21, up 50% from the 7 days before.',
			),
			'nothing the week before' => array(
				array_merge( array_fill( 0, 23, 0 ), array_fill( 0, 7, 1 ) ),
				array(
					'total'    => 7,
					'week'     => 7,
					'previous' => 0,
					'delta'    => 7,
					'percent'  => null,
				),
				'up',
				'Last 7 days: 7, up 7 from the 7 days before.',
			),
			'down'                    => array(
				array_merge( array_fill( 0, 16, 0 ), array_fill( 0, 7, 4 ), array_fill( 0, 7, 1 ) ),
				array(
					'total'    => 35,
					'week'     => 7,
					'previous' => 28,
					'delta'    => -21,
					'percent'  => 75,
				),
				'down',
				'Last 7 days: 7, down 75% from the 7 days before.',
			),
			'short series'            => array(
				array_fill( 0, 10, 1 ),
				array(
					'total'    => 10,
					'week'     => 7,
					'previous' => 3,
					'delta'    => 4,
					'percent'  => 133,
				),
				'up',
				'Last 7 days: 7, up 133% from the 7 days before.',
			),
			'no detections'           => array(
				array_fill( 0, 30, 0 ),
				array(
					'total'    => 0,
					'week'     => 0,
					'previous' => 0,
					'delta'    => 0,
					'percent'  => null,
				),
				'same',
				'Last 7 days: 0, the same as the 7 days before.',
			),
		);
	}

	/**
	 * The figures and the sentence for known daily totals.
	 *
	 * @dataProvider data_figures
	 *
	 * @param int[]                   $days      Daily totals.
	 * @param array<string, int|null> $expected  Figures.
	 * @param string                  $direction Direction.
	 * @param string                  $text      Sentence.
	 */
	public function test_figures( array $days, array $expected, string $direction, string $text ): void {
		$figures = Dashboard_Widget::figures( $days );

		$this->assertSame( $expected, $figures );
		$this->assertSame(
			array(
				'text'      => $text,
				'direction' => $direction,
			),
			Dashboard_Widget::delta_text( $figures )
		);
	}

	/**
	 * The sparkline's text alternative names the busiest day and today.
	 */
	public function test_spark_text(): void {
		$this->assertSame(
			'Spam blocked per day over the last 30 days: at most 4 on one day, 3 today.',
			Dashboard_Widget::spark_text( array( 0, 2, 4, 1, 3 ) )
		);
		$this->assertSame(
			'Spam blocked per day over the last 30 days: at most 0 on one day, 0 today.',
			Dashboard_Widget::spark_text( array() )
		);
	}
}
