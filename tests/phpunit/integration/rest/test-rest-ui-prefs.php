<?php
/**
 * Tests for zero-spam/v1/ui/prefs.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\UI_Prefs_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * Every logged-in user reads and changes their own admin preferences only,
 * and values are checked strictly.
 *
 * @covers \ZeroSpam\Includes\Rest\UI_Prefs_Controller
 */
class Test_Rest_UI_Prefs extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/ui/prefs';

	/**
	 * Defaults for a user who never saved anything.
	 */
	public function test_defaults(): void {
		$this->as_role( 'subscriber' );

		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( UI_Prefs_Controller::DEFAULTS, $response->get_data() );
	}

	/**
	 * A user changes some preferences; the others keep their value.
	 */
	public function test_patch_merges_and_stores_in_user_meta(): void {
		$user_id = $this->as_role( 'editor' );

		$response = $this->rest(
			'PATCH',
			self::ROUTE,
			array(
				'theme'     => 'dark',
				'dismissed' => array( 'whats-new:6.0.0', 'tip_live', 'tip_live' ),
			)
		);
		$this->rest( 'PATCH', self::ROUTE, array( 'onboarding_step' => 2 ) );

		$this->assertSame( 200, $response->get_status() );
		$stored = get_user_meta( $user_id, UI_Prefs_Controller::META_KEY, true );
		$this->assertSame( 'dark', $stored['theme'] );
		$this->assertSame( array( 'whats-new:6.0.0', 'tip_live' ), $stored['dismissed'] );
		$this->assertSame( 2, $stored['onboarding_step'] );
		$this->assertFalse( $stored['reduce_motion'] );
	}

	/**
	 * Invalid names and values.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function data_invalid(): array {
		return array(
			'unknown preference'   => array( 'colour', 'red' ),
			'unknown theme'        => array( 'theme', 'sepia' ),
			'bool as string'       => array( 'reduce_motion', 'true' ),
			'bool as int'          => array( 'live_paused', 1 ),
			'dismissed not a list' => array( 'dismissed', array( 'a' => 'b' ) ),
			'dismissed bad id'     => array( 'dismissed', array( '<script>' ) ),
			'dismissed too long'   => array( 'dismissed', array_map( 'strval', range( 1, 201 ) ) ),
			'version not semver'   => array( 'whats_new_seen', '6.0' ),
			'step as string'       => array( 'onboarding_step', '2' ),
			'step out of range'    => array( 'onboarding_step', 5 ),
		);
	}

	/**
	 * Invalid input is refused in the WordPress parameter error shape, and nothing is saved.
	 *
	 * @dataProvider data_invalid
	 *
	 * @param string $name  Preference.
	 * @param mixed  $value Value.
	 */
	public function test_invalid_input_is_refused( string $name, $value ): void {
		$user_id = $this->as_role( 'administrator' );

		$response = $this->rest(
			'PATCH',
			self::ROUTE,
			array(
				'theme' => 'light',
				$name   => $value,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( $name, $response->get_data()['data']['params'] );
		$this->assertSame( '', get_user_meta( $user_id, UI_Prefs_Controller::META_KEY, true ) );
	}

	/**
	 * An empty body is refused.
	 */
	public function test_empty_body_is_refused(): void {
		$this->as_role( 'administrator' );

		$this->assertSame( 400, $this->rest( 'PATCH', self::ROUTE )->get_status() );
	}

	/**
	 * Junk stored by hand is ignored on read.
	 */
	public function test_invalid_stored_values_are_ignored(): void {
		$user_id = $this->as_role( 'author' );
		update_user_meta(
			$user_id,
			UI_Prefs_Controller::META_KEY,
			array(
				'theme'       => 'neon',
				'live_paused' => true,
				'extra'       => 'x',
			)
		);

		$data = $this->rest( 'GET', self::ROUTE )->get_data();

		$this->assertSame( 'auto', $data['theme'] );
		$this->assertTrue( $data['live_paused'] );
		$this->assertArrayNotHasKey( 'extra', $data );
	}

	/**
	 * Logged-out visitors are refused.
	 */
	public function test_anonymous_is_refused(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->rest( 'GET', self::ROUTE )->get_status() );
		$this->assertSame( 401, $this->rest( 'PATCH', self::ROUTE, array( 'theme' => 'dark' ) )->get_status() );
	}

	/**
	 * One user's preferences never reach another user.
	 */
	public function test_preferences_are_per_user(): void {
		$this->as_role( 'administrator' );
		$this->rest( 'PATCH', self::ROUTE, array( 'theme' => 'dark' ) );

		$this->as_role( 'administrator' );

		$this->assertSame( 'auto', $this->rest( 'GET', self::ROUTE )->get_data()['theme'] );
	}

	/**
	 * `_fields` limits the response.
	 */
	public function test_fields(): void {
		$this->as_role( 'subscriber' );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'theme,live_paused' ) )->get_data();

		$this->assertSame( array( 'theme', 'live_paused' ), array_keys( $data ) );
	}
}
