<?php
/**
 * Tests for how the core settings option is stored, whoever writes it.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * Since 6.0.0 the 5.x settings screen and its sanitize callback are gone;
 * Settings_Schema::sanitize_settings_option() normalizes `zero-spam-settings`
 * for every writer: the blocked email domains move to their own option,
 * which is never autoloaded. Other module options are stored as written.
 *
 * @group ms-excluded
 * @covers \ZeroSpam\Core\Settings_Schema::sanitize_settings_option
 * @covers \ZeroSpam\Core\Settings_Schema::register_hooks
 */
class Test_Settings_Save extends TestCase {

	/**
	 * Starts without stored settings.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->as_role( 'administrator' );
		delete_option( 'zero-spam-comments' );
		delete_option( 'zero-spam-settings' );
		delete_option( 'zerospam_blocked_email_domains' );
	}

	/**
	 * Whether an option is autoloaded.
	 *
	 * @param string $option Option.
	 */
	private function autoloaded( string $option ): bool {
		global $wpdb;

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) );

		return ! in_array( $autoload, array( 'no', 'off' ), true );
	}

	/**
	 * The filter runs on every request, not only in the admin.
	 */
	public function test_filter_is_registered_on_every_request(): void {
		$this->assertSame( 5, has_filter( 'sanitize_option_zero-spam-settings', array( Settings_Schema::class, 'sanitize_settings_option' ) ) );
	}

	/**
	 * A write of another module's option stores only what it writes.
	 */
	public function test_other_module_is_stored_as_written(): void {
		update_option( 'zero-spam-comments', array( 'comment_spam_message' => 'Site message.' ) );

		$this->assertSame( array( 'comment_spam_message' => 'Site message.' ), get_option( 'zero-spam-comments' ) );
	}

	/**
	 * A direct write of the option (an import, network "Apply to sites")
	 * moves the blocked email domains to their own, non-autoloaded option.
	 */
	public function test_direct_write_moves_blocked_email_domains(): void {
		update_option(
			'zero-spam-settings',
			array(
				'blocked_email_domains' => "example.com\nexample.net",
				'max_logs'              => 5000,
			)
		);

		$stored = get_option( 'zero-spam-settings' );
		$this->assertSame( array( 'max_logs' => 5000 ), $stored );
		$this->assertSame( "example.com\nexample.net", get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertFalse( $this->autoloaded( 'zerospam_blocked_email_domains' ), 'The large domain list is not autoloaded.' );
	}

	/**
	 * Utilities::update_setting() (WP-CLI) writes the domains to their option.
	 */
	public function test_update_setting_writes_the_domains_option(): void {
		Utilities::update_setting( 'blocked_email_domains', 'example.org' );

		$this->assertSame( 'example.org', get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * PATCH /settings writes the domains to their option only.
	 */
	public function test_rest_writes_the_domains_option(): void {
		$response = $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'blocked_email_domains' => "example.com\nexample.io" ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( "example.com\nexample.io", get_option( 'zerospam_blocked_email_domains' ) );
		$stored = get_option( 'zero-spam-settings' );
		$this->assertTrue( false === $stored || ! array_key_exists( 'blocked_email_domains', (array) $stored ) );
	}

	/**
	 * A value that is not text is dropped from the module option and never stored.
	 */
	public function test_non_text_domains_are_dropped(): void {
		update_option( 'zero-spam-settings', array( 'blocked_email_domains' => array( 'x' ) ) );

		$this->assertSame( array(), get_option( 'zero-spam-settings' ) );
		$this->assertFalse( get_option( 'zerospam_blocked_email_domains' ) );
	}

	/**
	 * A value that is not an array is left alone.
	 */
	public function test_non_array_is_left_alone(): void {
		$this->assertSame( 'x', Settings_Schema::sanitize_settings_option( 'x' ) );
	}
}
