<?php
/**
 * Tests for Utilities::sanitize_array().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * Logged submission data passes through sanitize_array() before it is stored.
 *
 * @covers \ZeroSpam\Core\Utilities::sanitize_array
 */
class Test_Utilities_Sanitize_Array extends TestCase {

	/**
	 * A scalar is sanitized like a text field.
	 */
	public function test_sanitizes_a_scalar(): void {
		$this->assertSame( 'hello', Utilities::sanitize_array( '<b>hello</b>' ) );
	}

	/**
	 * Values at every depth are sanitized.
	 */
	public function test_sanitizes_nested_values(): void {
		$result = Utilities::sanitize_array(
			array(
				'name'  => '<script>alert(1)</script>Jane',
				'extra' => array(
					'bio' => "<img src=x onerror=alert(1)>Line\nbreak",
				),
			)
		);

		$this->assertSame(
			array(
				'name'  => 'Jane',
				'extra' => array(
					'bio' => 'Line break',
				),
			),
			$result
		);
	}

	/**
	 * Array keys are attacker-controlled (`field[<img …>]`) and are sanitized too.
	 */
	public function test_sanitizes_string_keys(): void {
		$result = Utilities::sanitize_array(
			array(
				'f' => array(
					'<img src=x onerror=alert(1)>' => 'value',
					'<b>label</b>'                 => 'value',
				),
			)
		);

		$this->assertSame(
			array(
				'f' => array(
					''      => 'value',
					'label' => 'value',
				),
			),
			$result
		);
		$this->assertStringNotContainsString( '<', (string) wp_json_encode( $result ) );
	}

	/**
	 * Integer keys are kept as they are.
	 */
	public function test_keeps_integer_keys(): void {
		$this->assertSame(
			array(
				3 => 'a',
				7 => 'b',
			),
			Utilities::sanitize_array(
				array(
					3 => 'a',
					7 => '<i>b</i>',
				)
			)
		);
	}

	/**
	 * The esc_html type escapes values instead of stripping tags.
	 */
	public function test_esc_html_type_escapes_values(): void {
		$this->assertSame(
			array( 'k' => '&lt;b&gt;x&lt;/b&gt;' ),
			Utilities::sanitize_array( array( 'k' => '<b>x</b>' ), 'esc_html' )
		);
	}

	/**
	 * Unknown types fall back to text field sanitization.
	 */
	public function test_unknown_type_falls_back_to_text_field(): void {
		$this->assertSame( array( 'k' => 'x' ), Utilities::sanitize_array( array( 'k' => '<b>x</b>' ), 'unknown' ) );
	}
}
