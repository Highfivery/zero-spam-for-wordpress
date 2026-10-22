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
 * A single-site settings form save stores what the form submits, with the
 * module's unticked checkboxes as false and empty multi-selects as empty
 * arrays; the network lock handling does not apply. Writes from outside the
 * form store only what they write.
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
	 * Forgets the simulated form post.
	 */
	public function tear_down(): void {
		unset( $_POST['option_page'] );

		parent::tear_down();
	}

	/**
	 * Saves a settings view the way options.php does for the settings form.
	 *
	 * @param string               $option    Option (and option page) saved.
	 * @param array<string, mixed> $submitted Submitted fields (unticked checkboxes are absent).
	 */
	private function submit_form( string $option, array $submitted ): void {
		$_POST['option_page'] = $option;
		update_option( $option, $submitted );
		unset( $_POST['option_page'] );
	}

	/**
	 * Submitted values are stored and every unticked checkbox becomes false.
	 */
	public function test_save_stores_submitted_values_and_unticked_checkboxes(): void {
		$this->submit_form(
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

		foreach ( array_keys( $stored ) as $key ) {
			$this->assertSame( 'comments', Settings::get_settings()[ $key ]['module'] ?? null, "{$key} belongs to another module." );
		}
	}

	/**
	 * A write from outside the form (import, CLI, REST) stores only what it writes.
	 */
	public function test_write_outside_the_form_is_not_filled_in(): void {
		update_option( 'zero-spam-comments', array( 'comment_spam_message' => 'Site message.' ) );

		$this->assertSame( array( 'comment_spam_message' => 'Site message.' ), get_option( 'zero-spam-comments' ) );
	}

	/**
	 * A post for another option page does not fill in this option.
	 */
	public function test_other_option_page_is_not_filled_in(): void {
		$_POST['option_page'] = 'zero-spam-settings';
		update_option( 'zero-spam-comments', array( 'comment_spam_message' => 'Site message.' ) );

		$this->assertSame( array( 'comment_spam_message' => 'Site message.' ), get_option( 'zero-spam-comments' ) );
	}

	/**
	 * An empty multi-select is stored as an empty array.
	 */
	public function test_empty_multiselect_is_stored_as_empty_array(): void {
		$this->submit_form( 'zero-spam-settings', array( 'block_handler' => 'redirect' ) );

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

		$this->submit_form( 'zero-spam-comments', array( 'comment_spam_message' => $default ) );

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

		$this->submit_form( 'zero-spam-settings', array( 'blocked_email_domains' => "example.com\nexample.net" ) );

		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'blocked_email_domains', $stored );
		$this->assertSame( "example.com\nexample.net", get_option( 'zerospam_blocked_email_domains' ) );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'zerospam_blocked_email_domains' ) );
		$this->assertContains( $autoload, array( 'no', 'off' ), 'The large domain list is not autoloaded.' );
	}
}
