<?php
/**
 * Tests for the settings registry built by Settings::get_settings().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Core settings are registered before the zerospam_settings filter runs, so
 * every call returns them and filters can see them.
 *
 * @covers \ZeroSpam\Core\Settings::get_settings
 */
class Test_Settings_Registry extends TestCase {

	/**
	 * The first call in a request already includes Rescue Mode (it used to be
	 * added after the filter, so only later calls returned it).
	 */
	public function test_first_call_includes_rescue_mode(): void {
		Settings::$settings = array();

		$settings = Settings::get_settings();

		$this->assertArrayHasKey( 'rescue_mode', $settings );
		$this->assertSame( 'html', $settings['rescue_mode']['type'] );
		$this->assertSame( 'general', $settings['rescue_mode']['section'] );
	}

	/**
	 * A zerospam_settings callback receives Rescue Mode and can change it.
	 */
	public function test_zerospam_settings_filter_sees_rescue_mode(): void {
		Settings::$settings = array();

		$seen = null;
		add_filter(
			'zerospam_settings',
			static function ( array $settings ) use ( &$seen ): array {
				$seen                            = array_key_exists( 'rescue_mode', $settings );
				$settings['rescue_mode']['desc'] = 'Filtered.';

				return $settings;
			}
		);

		$settings = Settings::get_settings();

		$this->assertTrue( $seen, 'The zerospam_settings filter did not receive rescue_mode.' );
		$this->assertSame( 'Filtered.', $settings['rescue_mode']['desc'] );
	}

	/**
	 * Rescue Mode stays where the settings page showed it: after the other
	 * core settings and before the modules' settings.
	 */
	public function test_rescue_mode_follows_the_core_settings(): void {
		Settings::get_settings();
		$keys = array_keys( Settings::get_settings() );

		$this->assertSame(
			array_search( 'update_disallowed_words', $keys, true ) + 1,
			array_search( 'rescue_mode', $keys, true )
		);
	}
}
