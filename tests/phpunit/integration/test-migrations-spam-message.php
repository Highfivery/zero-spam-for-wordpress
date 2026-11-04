<?php
/**
 * Tests for the wording of the message a blocked submission gets.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * "Your IP has been flagged" was shown for every failed check, also a form's
 * hidden trap, disallowed words and blocked email domains, where no address
 * was flagged. The default now says what happened, and sites that kept the
 * old default get the new one; a message the owner wrote stays.
 *
 * @covers \ZeroSpam\Includes\Migrations::spam_message_wording
 * @covers \ZeroSpam\Core\Utilities::spam_message
 * @covers \ZeroSpam\Core\Utilities::detection_message
 */
class Test_Migrations_Spam_Message extends TestCase {

	private const OLD = 'Your IP has been flagged as spam/malicious.';

	/**
	 * Starts each test without the network guards.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::cache_key() );
	}

	/**
	 * The default no longer blames an IP address, for any message setting.
	 */
	public function test_the_defaults_do_not_mention_an_ip(): void {
		delete_option( 'zero-spam-comments' );
		delete_option( 'zero-spam-login' );
		self::reset_static_caches();

		$this->assertStringNotContainsString( 'IP', Utilities::spam_message() );
		$this->assertSame( Utilities::spam_message(), Utilities::detection_message() );
		$this->assertSame( Utilities::spam_message(), Utilities::detection_message( 'comment_spam_message' ) );

		$settings = Settings::get_settings();
		foreach ( array( 'comment_spam_message', 'login_spam_message', 'registration_spam_message' ) as $key ) {
			$this->assertSame( Utilities::spam_message(), $settings[ $key ]['value'], $key );
			$this->assertSame( Utilities::spam_message(), $settings[ $key ]['recommended'], $key );
		}
	}

	/**
	 * A stored old default is removed; a message of the owner's stays.
	 */
	public function test_a_stored_old_default_follows_the_new_one(): void {
		update_option(
			'zero-spam-comments',
			array(
				'comment_spam_message' => self::OLD,
				'verify_comments'      => 'enabled',
			)
		);
		update_option( 'zero-spam-login', array( 'login_spam_message' => 'Go away, robot.' ) );
		update_option( 'zero-spam-givewp', array( 'givewp_spam_message' => "We're sorry, but we're unable to process the transaction. Your IP has been flagged as possible spam." ) );

		$this->assertTrue( ( new Migrations() )->spam_message_wording() );
		self::reset_static_caches();

		$this->assertSame( array( 'verify_comments' => 'enabled' ), get_option( 'zero-spam-comments' ) );
		$this->assertSame( 'Go away, robot.', get_option( 'zero-spam-login' )['login_spam_message'] );
		$this->assertSame( array(), get_option( 'zero-spam-givewp' ) );
		$this->assertSame( Utilities::spam_message(), Settings_Schema::value( 'comment_spam_message' ) );

		$this->assertTrue( ( new Migrations() )->spam_message_wording(), 'It can run again.' );
		$this->assertSame( 'Go away, robot.', get_option( 'zero-spam-login' )['login_spam_message'] );
	}

	/**
	 * A network value with the old default gets the new wording, lock kept.
	 *
	 * @group ms-required
	 */
	public function test_a_network_value_gets_the_new_wording(): void {
		$this->set_network_setting( 'comment_spam_message', self::OLD, true );
		$this->set_network_setting( 'login_spam_message', 'Go away, robot.' );

		$this->assertTrue( ( new Migrations() )->spam_message_wording() );

		$store = Settings_Schema::normalize_network_store( get_site_option( Network_Settings::META_KEY, array() ) );

		$this->assertSame( Utilities::spam_message(), $store['settings']['comment_spam_message']['value'] );
		$this->assertTrue( (bool) $store['settings']['comment_spam_message']['locked'] );
		$this->assertSame( 'Go away, robot.', $store['settings']['login_spam_message']['value'] );
	}
}
