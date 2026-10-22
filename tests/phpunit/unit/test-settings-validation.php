<?php
/**
 * Tests for Settings::is_valid_setting().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Settings added through the zerospam_settings filter need `type` and `module`.
 *
 * @covers \ZeroSpam\Core\Settings::is_valid_setting
 * @covers \ZeroSpam\Core\Settings::get_settings_by_module
 */
class Test_Settings_Validation extends TestCase {

	/**
	 * Setting definitions and whether they are valid.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function data_setting_definitions(): array {
		return array(
			'type and module'       => array(
				array(
					'type'   => 'text',
					'module' => 'settings',
				),
				true,
			),
			'extra keys are fine'   => array(
				array(
					'type'    => 'checkbox',
					'module'  => 'security',
					'title'   => 'Title',
					'section' => 'security',
				),
				true,
			),
			'missing module'        => array( array( 'type' => 'text' ), false ),
			'missing type'          => array( array( 'module' => 'settings' ), false ),
			'empty type'            => array(
				array(
					'type'   => '',
					'module' => 'settings',
				),
				false,
			),
			'empty array'           => array( array(), false ),
			'string instead of map' => array( 'text', false ),
			'null'                  => array( null, false ),
		);
	}

	/**
	 * Each definition is accepted or rejected.
	 *
	 * @dataProvider data_setting_definitions
	 *
	 * @param mixed $setting  Setting definition.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_is_valid_setting( $setting, bool $expected ): void {
		$this->assertSame( $expected, Settings::is_valid_setting( 'example', $setting ) );
	}

	/**
	 * A third-party setting without `type`/`module` is skipped, without a PHP warning.
	 *
	 * PHPUnit turns warnings into failures, so reaching the assertions proves
	 * there was none.
	 */
	public function test_invalid_filtered_setting_is_skipped_without_warning(): void {
		add_filter(
			'zerospam_settings',
			static function ( $settings ) {
				$settings['third_party_broken'] = array( 'title' => 'No type or module' );

				return $settings;
			}
		);

		$modules = Settings::get_settings_by_module();

		$this->assertArrayHasKey( 'settings', $modules );
		foreach ( $modules as $module => $settings ) {
			$this->assertArrayNotHasKey( 'third_party_broken', $settings, "Skipped setting ended up in module {$module}." );
		}
	}
}
