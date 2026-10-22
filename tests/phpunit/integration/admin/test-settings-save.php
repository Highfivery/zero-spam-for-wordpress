<?php
/**
 * Tests for saving the settings page on a single site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Settings as Settings_Page;
use ZeroSpam\Core\Settings;
use ZeroSpam\Tests\TestCase;

/**
 * A single-site save stores what the form submits, with unticked checkboxes
 * as false and empty multi-selects as empty arrays; the network lock handling
 * does not apply.
 *
 * @group ms-excluded
 * @covers \ZeroSpam\Core\Admin\Settings::settings_validation
 */
class Test_Settings_Save extends TestCase {

	/**
	 * Registers the settings as admin_init does.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		delete_option( 'zero-spam-comments' );
		delete_option( 'zero-spam-settings' );

		( new Settings_Page() )->register_settings();
	}

	/**
	 * Submitted values are stored and every unticked checkbox becomes false.
	 */
	public function test_save_stores_submitted_values_and_unticked_checkboxes(): void {
		update_option(
			'zero-spam-comments',
			array(
				'comment_spam_message' => 'Site message.',
				'verify_comments'      => 'enabled',
			)
		);

		$stored = get_option( 'zero-spam-comments' );

		$this->assertIsArray( $stored );
		$this->assertSame( 'Site message.', $stored['comment_spam_message'] );
		$this->assertSame( 'enabled', $stored['verify_comments'] );
		$this->assertArrayHasKey( 'log_blocked_comments', $stored );
		$this->assertFalse( $stored['log_blocked_comments'] );

		foreach ( Settings::get_settings() as $key => $setting ) {
			if ( ! isset( $setting['type'], $setting['module'] ) || 'comments' !== $setting['module'] || 'verify_comments' === $key ) {
				continue;
			}
			if ( 'checkbox' === $setting['type'] ) {
				$this->assertArrayHasKey( $key, $stored, "Unticked checkbox {$key} is stored." );
				$this->assertFalse( $stored[ $key ] );
			}
		}
	}

	/**
	 * An empty multi-select is stored as an empty array.
	 */
	public function test_empty_multiselect_is_stored_as_empty_array(): void {
		update_option( 'zero-spam-settings', array( 'block_handler' => 'redirect' ) );

		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( 'widget_visibility', $stored );
		$this->assertSame( array(), $stored['widget_visibility'] );
	}

	/**
	 * The same value saved again is stored again (no network rule on a single site).
	 */
	public function test_value_equal_to_default_is_still_stored(): void {
		$default = Settings::get_settings( 'comment_spam_message' );

		update_option( 'zero-spam-comments', array( 'comment_spam_message' => $default ) );

		$stored = get_option( 'zero-spam-comments' );
		$this->assertIsArray( $stored );
		$this->assertSame( $default, $stored['comment_spam_message'] );
	}

	/**
	 * Blocked email domains go to their own option, which is not autoloaded.
	 */
	public function test_blocked_email_domains_are_stored_separately_without_autoload(): void {
		global $wpdb;

		delete_option( 'zerospam_blocked_email_domains' );

		update_option( 'zero-spam-settings', array( 'blocked_email_domains' => "example.com\nexample.net" ) );

		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'blocked_email_domains', $stored );
		$this->assertSame( "example.com\nexample.net", get_option( 'zerospam_blocked_email_domains' ) );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'zerospam_blocked_email_domains' ) );
		$this->assertContains( $autoload, array( 'no', 'off' ), 'The large domain list is not autoloaded.' );
	}
}
