<?php
/**
 * Tests for Settings::auto_configure() on a single site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Tests\TestCase;

/**
 * Every setting with a recommended value is stored with it, as before; a
 * setting without one keeps the site's own value or stays unset (its
 * default), so the resolved values are what they were.
 *
 * @covers \ZeroSpam\Core\Settings::auto_configure
 */
class Test_Auto_Configure extends TestCase {

	/**
	 * Recommended values are stored; others keep their value or default.
	 */
	public function test_recommended_values_are_stored(): void {
		delete_option( 'zero-spam-settings' );
		update_option( 'zero-spam-settings', array( 'ip_whitelist' => '192.0.2.44' ) );
		self::reset_static_caches();

		Settings::auto_configure();
		self::reset_static_caches();

		foreach ( Settings::get_settings() as $key => $setting ) {
			if ( ! isset( $setting['recommended'], $setting['module'] ) || 'html' === ( $setting['type'] ?? '' ) || 'blocked_email_domains' === $key ) {
				continue;
			}

			$stored = get_option( 'zero-spam-' . $setting['module'] );
			$this->assertSame( $setting['recommended'], $stored[ $key ] ?? null, "{$key} gets its recommended value." );
		}

		$this->assertSame( '192.0.2.44', get_option( 'zero-spam-settings' )['ip_whitelist'] );
		$this->assertArrayNotHasKey( 'trusted_proxies', get_option( 'zero-spam-settings' ), 'A setting without a recommendation the site never stored stays unset.' );
		$this->assertSame( 10000, (int) Settings_Schema::value( 'max_logs' ) );
		$this->assertSame( 1, (int) get_option( 'zerospam_configured' ) );
	}

	/**
	 * Sending detections to zerospam.org needs the site owner's consent
	 * (WordPress.org plugin guideline 7): the recommended settings never turn
	 * Usage Data Sharing on, and keep it on where the owner turned it on.
	 */
	public function test_recommended_settings_never_turn_on_data_sharing(): void {
		delete_option( 'zero-spam-settings' );
		self::reset_static_caches();

		$this->assertArrayNotHasKey( 'recommended', Settings::get_settings()['share_data'] );

		Settings::auto_configure();
		self::reset_static_caches();

		$this->assertNotSame( 'enabled', get_option( 'zero-spam-settings' )['share_data'] ?? null );
		$this->assertNotSame( 'enabled', Settings_Schema::value( 'share_data' ) );

		update_option( 'zero-spam-settings', array( 'share_data' => 'enabled' ) );
		self::reset_static_caches();

		Settings::auto_configure();
		self::reset_static_caches();

		$this->assertSame( 'enabled', get_option( 'zero-spam-settings' )['share_data'] );
	}

	/**
	 * Stop Forum Spam sends visitors' addresses and emails to stopforumspam.com,
	 * so the recommended settings leave it as it is: first-run setup asks.
	 */
	public function test_recommended_settings_leave_stop_forum_spam_to_the_owner(): void {
		delete_option( 'zero-spam-stop_forum_spam' );
		self::reset_static_caches();

		$this->assertArrayNotHasKey( 'recommended', Settings::get_settings()['stop_forum_spam'] );

		Settings::auto_configure();
		self::reset_static_caches();

		$this->assertNotSame( 'enabled', Settings_Schema::value( 'stop_forum_spam' ) );

		update_option( 'zero-spam-stop_forum_spam', array( 'stop_forum_spam' => 'enabled' ) );
		self::reset_static_caches();

		Settings::auto_configure();
		self::reset_static_caches();

		$this->assertSame( 'enabled', get_option( 'zero-spam-stop_forum_spam' )['stop_forum_spam'] );
	}
}
