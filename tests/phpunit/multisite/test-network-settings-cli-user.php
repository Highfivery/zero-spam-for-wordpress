<?php
/**
 * Tests for `wp zerospam network-settings` run without a user.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\CLI\Network_Settings_CLI;
use ZeroSpam\Tests\TestCase;

/**
 * WP-CLI runs as no user unless `--user` is given, so the manager's
 * capability check refused every write and the command said only
 * "Failed to set setting". It now says how to run it, and still writes
 * nothing without a super admin.
 *
 * @group ms-required
 *
 * @covers \ZeroSpam\Includes\CLI\Network_Settings_CLI
 */
class Test_Network_Settings_CLI_User extends TestCase {

	/**
	 * Runs a command method with the stand-in WP_CLI.
	 *
	 * @param string                $method     Method.
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return string|null The error message, if the command stopped with one.
	 */
	private function cli( string $method, array $args, array $assoc_args = array() ): ?string {
		require_once dirname( __DIR__ ) . '/includes/stubs/wp-cli.php';

		WP_CLI::reset();
		try {
			( new Network_Settings_CLI() )->$method( $args, $assoc_args );
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}

		return null;
	}

	/**
	 * Without a user, a write says to run it as a super admin and changes nothing.
	 */
	public function test_set_without_a_user_says_how_to_run_it(): void {
		wp_set_current_user( 0 );

		$error = $this->cli( 'set', array( 'comment_spam_message', 'From CLI.' ) );

		$this->assertNotNull( $error );
		$this->assertStringContainsString( '--user=', $error );
		$this->assertArrayNotHasKey( 'comment_spam_message', get_site_option( 'zerospam_network_settings', array() )['settings'] ?? array() );
	}

	/**
	 * Lock and unlock say the same.
	 */
	public function test_lock_without_a_user_says_how_to_run_it(): void {
		wp_set_current_user( 0 );

		$this->assertStringContainsString( '--user=', (string) $this->cli( 'lock', array( 'comment_spam_message' ) ) );
		$this->assertStringContainsString( '--user=', (string) $this->cli( 'unlock', array( 'comment_spam_message' ) ) );
	}

	/**
	 * As a super admin the command writes the value.
	 */
	public function test_set_as_a_super_admin_writes(): void {
		$user = self::factory()->user->create();
		grant_super_admin( $user );
		wp_set_current_user( $user );

		$this->assertNull( $this->cli( 'set', array( 'comment_spam_message', 'From CLI.' ) ) );
		$this->assertSame( 'From CLI.', get_site_option( 'zerospam_network_settings' )['settings']['comment_spam_message']['value'] );
	}

	/**
	 * Read commands say so too, instead of printing an empty export.
	 */
	public function test_export_without_a_user_says_how_to_run_it(): void {
		wp_set_current_user( 0 );

		$this->assertStringContainsString( '--user=', (string) $this->cli( 'export', array() ) );
		$this->assertStringContainsString( '--user=', (string) $this->cli( 'list_settings', array() ) );
	}
}
