<?php
/**
 * Tests for the Utilities helpers deprecated in 6.0.1.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * Unused since 6.0.0, they still answer as before and say they are deprecated.
 */
class Test_Utilities_Deprecated extends TestCase {

	/**
	 * The detection title is deprecated, and so is its filter.
	 *
	 * @covers \ZeroSpam\Core\Utilities::detection_title
	 */
	public function test_detection_title_is_deprecated(): void {
		$this->setExpectedDeprecated( 'ZeroSpam\Core\Utilities::detection_title' );
		$this->setExpectedDeprecated( 'zerospam_detection_title' );
		add_filter( 'zerospam_detection_title', static fn(): string => 'Filtered' );

		$this->assertSame( 'Filtered', Utilities::detection_title() );
	}

	/**
	 * The flag URL helper is deprecated and still returns the URL.
	 *
	 * @covers \ZeroSpam\Core\Utilities::country_flag_url
	 */
	public function test_country_flag_url_is_deprecated(): void {
		$this->setExpectedDeprecated( 'ZeroSpam\Core\Utilities::country_flag_url' );

		$this->assertStringEndsWith( 'assets/img/flags/nl.svg', Utilities::country_flag_url( 'NL' ) );
	}
}
