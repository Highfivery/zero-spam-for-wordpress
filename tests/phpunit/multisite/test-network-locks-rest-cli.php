<?php
/**
 * Tests for network locks on the REST settings route and `wp zerospam set`.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * A site cannot change a locked setting through REST or WP-CLI, gets a clear
 * answer when it tries, and a value equal to the network default is a
 * success that keeps the site on the network default.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\Settings_Controller::update_settings
 * @covers \ZeroSpam\Includes\Settings_Resolver::update_settings
 */
class Test_Network_Locks_Rest_Cli extends TestCase {

	/**
	 * Setting exposed by the REST route.
	 */
	const KEY = 'zerospam_confidence_min';

	/**
	 * Option the REST route writes on a site.
	 */
	const OPTION = 'zero-spam-zerospam';

	/**
	 * Logs in an administrator of the main site.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->as_role( 'administrator' );
		delete_option( self::OPTION );
		delete_option( 'zerospam_settings_audit' );
	}

	/**
	 * Drops the REST server so routes are registered afresh.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * Sends a site-scope PATCH to zero-spam/v1/settings.
	 *
	 * @param array<string, mixed> $settings Body.
	 */
	private function patch( array $settings ): WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', '/zero-spam/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $settings ) );

		return rest_do_request( $request );
	}

	/**
	 * What the site stored.
	 *
	 * @return array<string, mixed>
	 */
	private function stored(): array {
		$stored = get_option( self::OPTION );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * A locked key is refused with 403 and listed; nothing is written.
	 */
	public function test_rest_locked_key_is_refused(): void {
		update_option( self::OPTION, array( self::KEY => 20 ) );
		$this->set_network_setting( self::KEY, 40, true );

		$response = $this->patch( array( self::KEY => 50 ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_setting_locked', $response->get_data()['code'] );
		$this->assertSame( array( self::KEY ), $response->get_data()['data']['locked_keys'] );
		$this->assertSame( array( self::KEY => 20 ), $this->stored() );
	}

	/**
	 * Without an existing option, a locked key is refused the same way (it used to answer 200).
	 */
	public function test_rest_locked_key_without_option_is_refused(): void {
		$this->set_network_setting( self::KEY, 40, true );

		$response = $this->patch( array( self::KEY => 50 ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( get_option( self::OPTION ) );
	}

	/**
	 * A mixed request is refused as a whole: the unlocked key is not written either.
	 */
	public function test_rest_mixed_request_is_refused_and_writes_nothing(): void {
		update_option( self::OPTION, array( 'zerospam' => false ) );
		$this->set_network_setting( self::KEY, 40, true );

		$response = $this->patch(
			array(
				'zerospam' => 'enabled',
				self::KEY  => 50,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( array( self::KEY ), $response->get_data()['data']['locked_keys'] );
		$this->assertSame( array( 'zerospam' => false ), $this->stored() );
		$this->assertFalse( get_option( 'zerospam_settings_audit' ), 'No audit entry claims a change.' );
	}

	/**
	 * A value equal to the unlocked network default succeeds and keeps the site on the default.
	 */
	public function test_rest_value_equal_to_network_default_follows_the_network(): void {
		update_option( self::OPTION, array( 'zerospam' => false ) );
		$this->set_network_setting( self::KEY, 40 );

		$response = $this->patch( array( self::KEY => 40 ) );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'network_default', $response->get_data()['changes'][ self::KEY ]['source'] );
		$this->assertArrayNotHasKey( self::KEY, $this->stored(), 'No site override is stored.' );
	}

	/**
	 * A different value is stored as a site override and reported as such.
	 */
	public function test_rest_different_value_is_a_site_override(): void {
		$this->set_network_setting( self::KEY, 40 );

		$response = $this->patch( array( self::KEY => 55 ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'site', $response->get_data()['changes'][ self::KEY ]['source'] );
		$this->assertEquals( 55, $this->stored()[ self::KEY ] );
	}

	/**
	 * Runs a `wp zerospam` command method with the stand-in WP_CLI.
	 *
	 * WP_CLI::halt() and WP_CLI::error() throw in the stand-in; the exit code
	 * is in WP_CLI::$exit_code.
	 *
	 * @param string                $method     ZeroSpamCLI method.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	private function cli( string $method, array $assoc_args ): void {
		require_once dirname( __DIR__ ) . '/includes/stubs/wp-cli.php';
		require_once ZEROSPAM_PATH . 'includes/class-cli.php';

		WP_CLI::reset();
		try {
			( new ZeroSpamCLI() )->$method( array(), $assoc_args );
		} catch ( RuntimeException $e ) {
			// The command stopped; WP_CLI::$exit_code says how.
			unset( $e );
		}
	}

	/**
	 * `wp zerospam set` warns and exits non-zero, instead of claiming success, when the key is locked.
	 */
	public function test_cli_set_warns_on_a_locked_key(): void {
		$this->set_network_setting( 'comment_spam_message', 'Network locked message.', true );

		$this->cli( 'set', array( 'comment_spam_message' => 'CLI message.' ) );

		$this->assertSame( array(), WP_CLI::of( 'success' ) );
		$this->assertCount( 1, WP_CLI::of( 'warning' ) );
		$this->assertStringContainsString( 'locked by the network administrator', WP_CLI::of( 'warning' )[0] );
		$this->assertSame( 1, WP_CLI::$exit_code );
		$stored = get_option( 'zero-spam-comments' );
		$this->assertFalse( is_array( $stored ) && array_key_exists( 'comment_spam_message', $stored ) );
	}

	/**
	 * With a locked and an unlocked key, the unlocked one is still set, and the command exits non-zero.
	 */
	public function test_cli_set_mixed_keys_applies_the_unlocked_one_and_exits_non_zero(): void {
		$this->set_network_setting( 'comment_spam_message', 'Network locked message.', true );

		$this->cli(
			'set',
			array(
				'comment_spam_message' => 'CLI message.',
				'log_blocked_comments' => 'enabled',
			)
		);

		$this->assertCount( 1, WP_CLI::of( 'success' ) );
		$this->assertCount( 1, WP_CLI::of( 'warning' ) );
		$this->assertSame( 1, WP_CLI::$exit_code );
		$this->assertSame( 'enabled', get_option( 'zero-spam-comments' )['log_blocked_comments'] );
	}

	/**
	 * Locked blocked email domains: both CLI paths warn, exit non-zero and change nothing.
	 */
	public function test_cli_warns_on_locked_blocked_email_domains(): void {
		update_option( 'zerospam_blocked_email_domains', 'site.example' );
		$this->set_network_setting( 'blocked_email_domains', 'network.example', true );

		$this->cli( 'set', array( 'blocked_email_domains' => 'cli.example' ) );
		$this->assertCount( 1, WP_CLI::of( 'warning' ) );
		$this->assertSame( 1, WP_CLI::$exit_code );

		$this->cli( 'update_blocked_domains', array( 'domains' => 'cli.example' ) );
		$this->assertSame( array(), WP_CLI::of( 'success' ) );
		$this->assertCount( 1, WP_CLI::of( 'warning' ) );
		$this->assertSame( 1, WP_CLI::$exit_code );

		$this->assertSame( 'site.example', get_option( 'zerospam_blocked_email_domains' ) );
	}

	/**
	 * An unlocked key is still set, with a success message and no non-zero exit.
	 */
	public function test_cli_set_succeeds_on_an_unlocked_key(): void {
		$this->cli( 'set', array( 'comment_spam_message' => 'CLI message.' ) );

		$this->assertCount( 1, WP_CLI::of( 'success' ) );
		$this->assertSame( array(), WP_CLI::of( 'warning' ) );
		$this->assertNull( WP_CLI::$exit_code );
		$this->assertSame( 'CLI message.', get_option( 'zero-spam-comments' )['comment_spam_message'] );
	}
}
