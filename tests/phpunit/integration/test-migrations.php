<?php
/**
 * Tests for the run-once migrations.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\TestCase;

/**
 * Migrations run once per site, in order, and never overwrite user settings.
 *
 * @covers \ZeroSpam\Includes\Migrations
 */
class Test_Migrations extends TestCase {

	/**
	 * Every registered migration, in order.
	 */
	const KEYS = array(
		'legacy_options_to_modules_v5',
		'share_detection_cron_cleanup',
		'davidwalsh_signed_tokens',
		'geolocation_header_cache',
	);

	/**
	 * The first run records every migration and fires zerospam_plugin_updated once.
	 */
	public function test_first_run_records_every_migration(): void {
		delete_option( Migrations::COMPLETED_OPTION );
		$updated = new MockAction();
		add_action( 'zerospam_plugin_updated', array( $updated, 'action' ) );

		( new Migrations() )->run();

		$this->assertSame( self::KEYS, array_values( get_option( Migrations::COMPLETED_OPTION ) ) );
		$this->assertSame( 1, $updated->get_call_count() );
		$this->assertSame( array( ZEROSPAM_VERSION ), $updated->get_args()[0] );
		$this->assertSame( ZEROSPAM_VERSION, get_option( 'zero-spam-last-update' ) );
	}

	/**
	 * A second run does nothing and fires nothing.
	 */
	public function test_second_run_is_a_no_op(): void {
		delete_option( Migrations::COMPLETED_OPTION );
		( new Migrations() )->run();

		$updated = new MockAction();
		add_action( 'zerospam_plugin_updated', array( $updated, 'action' ) );
		update_option( 'wpzerospam', array( 'max_logs' => 1 ) );

		( new Migrations() )->run();

		$this->assertSame( self::KEYS, array_values( get_option( Migrations::COMPLETED_OPTION ) ) );
		$this->assertSame( 0, $updated->get_call_count() );
		$this->assertSame( array( 'max_logs' => 1 ), get_option( 'wpzerospam' ), 'A completed migration ran again.' );
	}

	/**
	 * The legacy option only fills keys the user has not set.
	 */
	public function test_migrate_legacy_options_never_overwrites(): void {
		update_option( 'zero-spam-settings', array( 'max_logs' => 50 ) );
		update_option(
			'wpzerospam',
			array(
				'max_logs'        => 999,
				'log_blocked_ips' => 'enabled',
				'stop_forum_spam' => 'enabled',
			)
		);

		$this->assertTrue( ( new Migrations() )->migrate_legacy_options() );

		$settings = get_option( 'zero-spam-settings' );
		$this->assertSame( 50, $settings['max_logs'], 'A user setting was overwritten by the legacy value.' );
		$this->assertSame( 'enabled', $settings['log_blocked_ips'] );
		$this->assertSame( array( 'stop_forum_spam' => 'enabled' ), get_option( 'zero-spam-stop_forum_spam' ) );
		$this->assertFalse( get_option( 'wpzerospam' ) );
		$this->assertTrue( (bool) get_option( Migrations::SETTINGS_REVIEW_NOTICE_OPTION ) );
	}

	/**
	 * Without a legacy option the migration completes without touching settings.
	 */
	public function test_migrate_legacy_options_without_legacy_data(): void {
		delete_option( 'wpzerospam' );
		update_option( 'zero-spam-settings', array( 'max_logs' => 50 ) );

		$this->assertTrue( ( new Migrations() )->migrate_legacy_options() );
		$this->assertSame( array( 'max_logs' => 50 ), get_option( 'zero-spam-settings' ) );
	}
}
