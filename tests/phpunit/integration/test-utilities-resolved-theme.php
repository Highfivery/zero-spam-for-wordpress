<?php
/**
 * Tests for Utilities::resolved_theme().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Rest\UI_Prefs_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * The data-zs-theme value for a Zero Spam root: an explicit light or dark
 * preference everywhere; "auto" follows the operating system in the app
 * (Zero Spam owns the page) and is light in the dashboard widget (the
 * surrounding wp-admin stays light).
 *
 * @covers \ZeroSpam\Core\Utilities::resolved_theme
 */
class Test_Utilities_Resolved_Theme extends TestCase {

	/**
	 * Stored preference, context, expected value.
	 *
	 * @return array<string, array{0: mixed, 1: string, 2: string}>
	 */
	public static function data_themes(): array {
		return array(
			'no preference, app'        => array( null, 'app', 'auto' ),
			'no preference, widget'     => array( null, 'widget', 'light' ),
			'auto, app'                 => array( 'auto', 'app', 'auto' ),
			'auto, widget'              => array( 'auto', 'widget', 'light' ),
			'light, app'                => array( 'light', 'app', 'light' ),
			'light, widget'             => array( 'light', 'widget', 'light' ),
			'dark, app'                 => array( 'dark', 'app', 'dark' ),
			'dark, widget'              => array( 'dark', 'widget', 'dark' ),
			'invalid stored value'      => array( 'purple', 'widget', 'light' ),
			'unknown context is app'    => array( 'auto', 'email', 'auto' ),
			'unknown context, explicit' => array( 'dark', 'email', 'dark' ),
		);
	}

	/**
	 * The resolved theme for each preference and context.
	 *
	 * @dataProvider data_themes
	 *
	 * @param mixed  $stored   Stored theme preference, or null for none.
	 * @param string $context  Context.
	 * @param string $expected Expected data-zs-theme value.
	 */
	public function test_resolved_theme( $stored, string $context, string $expected ): void {
		$user_id = $this->as_role( 'administrator' );

		if ( null !== $stored ) {
			update_user_meta( $user_id, UI_Prefs_Controller::META_KEY, array( 'theme' => $stored ) );
		}

		$this->assertSame( $expected, Utilities::resolved_theme( $context ) );
	}

	/**
	 * Another user's preference can be resolved; the default context is the app.
	 */
	public function test_other_user_and_default_context(): void {
		$other = self::factory()->user->create();
		update_user_meta( $other, UI_Prefs_Controller::META_KEY, array( 'theme' => 'dark' ) );
		$this->as_role( 'administrator' );

		$this->assertSame( 'dark', Utilities::resolved_theme( 'widget', $other ) );
		$this->assertSame( 'light', Utilities::resolved_theme( 'widget' ) );
		$this->assertSame( 'auto', Utilities::resolved_theme() );
	}
}
