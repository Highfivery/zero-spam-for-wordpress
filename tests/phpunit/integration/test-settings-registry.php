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

	/**
	 * Calls the private Rescue Mode status renderer.
	 *
	 * @param bool $key_defined Whether ZEROSPAM_RESCUE_KEY is defined.
	 * @return string HTML.
	 */
	private function rescue_mode_status( bool $key_defined ): string {
		$method = new ReflectionMethod( Settings::class, 'rescue_mode_status' );

		return (string) $method->invoke( null, $key_defined );
	}

	/**
	 * Without a rescue key the card says the emailed link still works, in a
	 * colour that meets 4.5:1 on white (pure red did not).
	 */
	public function test_inactive_state_is_readable_and_mentions_the_rescue_link(): void {
		$html = $this->rescue_mode_status( false );

		$this->assertStringContainsString( 'Rescue key not set — the emailed rescue link still works.', $html );
		$this->assertStringContainsString( 'color: #b32d2e;', $html );
		$this->assertStringNotContainsString( 'color: red;', $html );
	}

	/**
	 * With a rescue key the card says Active, and the registry uses it (the
	 * test bootstrap defines ZEROSPAM_RESCUE_KEY).
	 */
	public function test_active_state(): void {
		$html = $this->rescue_mode_status( true );

		$this->assertStringContainsString( 'Active', $html );
		$this->assertSame( $html, Settings::get_settings()['rescue_mode']['html'] );
	}
}
