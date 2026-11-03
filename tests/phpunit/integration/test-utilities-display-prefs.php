<?php
/**
 * Tests for Utilities::display_prefs().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Rest\UI_Prefs_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * The user's display preferences for a Zero Spam root: an explicit light or
 * dark theme everywhere; "auto" follows the operating system on the plugin's
 * own screens and is light elsewhere in wp-admin (the dashboard widget).
 *
 * The first test is the one in feat/admin-shell-overview (test-admin-app.php),
 * which defines the helper; the others cover the dashboard widget's use.
 *
 * @covers \ZeroSpam\Core\Utilities::display_prefs
 */
class Test_Utilities_Display_Prefs extends TestCase {

	/**
	 * "auto" follows the device on the plugin's own screens only; elsewhere
	 * (the dashboard widget) it renders light.
	 */
	public function test_display_prefs_auto_is_light_outside_plugin_screens(): void {
		$user = $this->as_role( 'administrator' );

		$this->assertSame( 'auto', Utilities::display_prefs( true )['theme'] );
		$this->assertSame( 'light', Utilities::display_prefs( false )['theme'] );

		update_user_meta( $user, 'zerospam_ui', array( 'theme' => 'dark' ) );
		$this->assertSame( 'dark', Utilities::display_prefs( false )['theme'] );

		update_user_meta( $user, 'zerospam_ui', array( 'theme' => '<script>' ) );
		$this->assertSame( 'auto', Utilities::display_prefs( true )['theme'] );
		$this->assertFalse( Utilities::display_prefs( true )['reduce_motion'] );
	}

	/**
	 * Stored preference, plugin screen or not, expected theme.
	 *
	 * @return array<string, array{0: mixed, 1: bool, 2: string}>
	 */
	public static function data_themes(): array {
		return array(
			'no preference, plugin screen' => array( null, true, 'auto' ),
			'no preference, widget'        => array( null, false, 'light' ),
			'auto, plugin screen'          => array( 'auto', true, 'auto' ),
			'auto, widget'                 => array( 'auto', false, 'light' ),
			'light, plugin screen'         => array( 'light', true, 'light' ),
			'light, widget'                => array( 'light', false, 'light' ),
			'dark, plugin screen'          => array( 'dark', true, 'dark' ),
			'dark, widget'                 => array( 'dark', false, 'dark' ),
			'invalid stored value, widget' => array( 'purple', false, 'light' ),
		);
	}

	/**
	 * The theme for each preference, on and off the plugin's screens.
	 *
	 * @dataProvider data_themes
	 *
	 * @param mixed  $stored        Stored theme preference, or null for none.
	 * @param bool   $plugin_screen Whether the markup is on a plugin screen.
	 * @param string $expected      Expected data-zs-theme value.
	 */
	public function test_theme( $stored, bool $plugin_screen, string $expected ): void {
		$user_id = $this->as_role( 'administrator' );

		if ( null !== $stored ) {
			update_user_meta( $user_id, UI_Prefs_Controller::META_KEY, array( 'theme' => $stored ) );
		}

		$this->assertSame( $expected, Utilities::display_prefs( $plugin_screen )['theme'] );
	}

	/**
	 * Reduce motion only when stored as true; another user can be read.
	 */
	public function test_reduce_motion_and_other_user(): void {
		$other = self::factory()->user->create();
		update_user_meta(
			$other,
			UI_Prefs_Controller::META_KEY,
			array(
				'theme'         => 'dark',
				'reduce_motion' => true,
			)
		);
		$me = $this->as_role( 'administrator' );
		update_user_meta( $me, UI_Prefs_Controller::META_KEY, array( 'reduce_motion' => 'yes' ) );

		$this->assertSame(
			array(
				'theme'         => 'dark',
				'reduce_motion' => true,
			),
			Utilities::display_prefs( false, $other )
		);
		$this->assertSame(
			array(
				'theme'         => 'light',
				'reduce_motion' => false,
			),
			Utilities::display_prefs( false )
		);
	}
}
