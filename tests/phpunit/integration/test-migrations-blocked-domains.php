<?php
/**
 * Tests for the migration that takes the blocked email domains out of the autoloaded settings.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\TestCase;

/**
 * Sites that saved the recommended list before 5.5.9 kept it inside the
 * autoloaded `zero-spam-settings` option (#404): only a later save moved it, so
 * upgrading alone left about 1.1 MB loaded on every request. Rows 5.x wrote can
 * also leave the list's own option autoloaded, which update_option() does not
 * correct while the value is unchanged.
 *
 * @covers \ZeroSpam\Includes\Migrations::blocked_domains_own_option
 * @covers \ZeroSpam\Core\Settings_Schema::sanitize_settings_option
 */
class Test_Migrations_Blocked_Domains extends TestCase {

	/**
	 * The list's own option.
	 *
	 * @var string
	 */
	const OWN = 'zerospam_blocked_email_domains';

	/**
	 * Starts each test without the settings or the list's own option.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( self::OWN );
		delete_option( 'zero-spam-settings' );
	}

	/**
	 * Stores an option row exactly as 5.x left it, without any filter.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $value    Option value.
	 * @param string $autoload Autoload value of the row.
	 */
	private function store_raw( string $name, $value, string $autoload = 'yes' ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes the row an old version left.
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes the row an old version left.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => $name,
				'option_value' => maybe_serialize( $value ),
				'autoload'     => $autoload,
			)
		);

		$this->forget_option_caches( $name );
	}

	/**
	 * Empties WordPress's caches of an option.
	 *
	 * @param string $name Option name.
	 */
	private function forget_option_caches( string $name ): void {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		Settings_Schema::reset_cache();
	}

	/**
	 * An option's row as the database has it.
	 *
	 * @param string $name Option name.
	 * @return object{autoload: string, size: string}|null
	 */
	private function row( string $name ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The test is about what the row holds.
		return $wpdb->get_row( $wpdb->prepare( "SELECT autoload, LENGTH(option_value) AS size FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	}

	/**
	 * Runs the migration.
	 */
	private function migrate(): bool {
		return ( new Migrations() )->blocked_domains_own_option();
	}

	/**
	 * The migration runs once per site, after the 6.0 backup that keeps the 5.x values.
	 */
	public function test_registered_after_the_backup(): void {
		$method = new ReflectionMethod( Migrations::class, 'get_migrations' );
		$keys   = array_keys( $method->invoke( new Migrations() ) );

		$this->assertContains( 'v6_blocked_domains_option', $keys );
		$this->assertGreaterThan( array_search( 'v6_backup_5x', $keys, true ), array_search( 'v6_blocked_domains_option', $keys, true ) );
	}

	/**
	 * The recommended list stored in the settings moves to its own option,
	 * unchanged and not autoloaded; the autoloaded row keeps only the settings.
	 */
	public function test_moves_the_recommended_list_out_of_the_autoloaded_settings(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own list.
		$list = trim( (string) file_get_contents( ZEROSPAM_PATH . 'assets/disposable-email-domains.txt' ) );

		$this->store_raw(
			'zero-spam-settings',
			array(
				'share_data'            => 'enabled',
				'blocked_email_domains' => $list,
			)
		);
		$this->assertGreaterThan( 1000000, (int) $this->row( 'zero-spam-settings' )->size );

		$this->assertTrue( $this->migrate() );
		$this->forget_option_caches( 'zero-spam-settings' );
		$this->forget_option_caches( self::OWN );

		$this->assertLessThan( 1024, (int) $this->row( 'zero-spam-settings' )->size );
		$this->assertSame( array( 'share_data' => 'enabled' ), get_option( 'zero-spam-settings' ) );
		$this->assertSame( $list, get_option( self::OWN ) );
		$this->assertSame( 'off', $this->row( self::OWN )->autoload );
		$this->assertSame( $list, Settings::get_settings( 'blocked_email_domains' ) );
	}

	/**
	 * When both exist the list's own option is the one sites read: it stays,
	 * and the older copy in the settings is dropped.
	 */
	public function test_own_option_wins_over_the_older_copy(): void {
		$this->store_raw( self::OWN, 'new.example', 'off' );
		$this->store_raw( 'zero-spam-settings', array( 'blocked_email_domains' => 'old.example' ) );

		$this->migrate();
		$this->forget_option_caches( self::OWN );

		$this->assertSame( 'new.example', get_option( self::OWN ) );
		$this->assertSame( array(), get_option( 'zero-spam-settings' ) );
	}

	/**
	 * An emptied list is a decision: an older copy does not bring it back.
	 */
	public function test_emptied_own_option_stays_empty(): void {
		$this->store_raw( self::OWN, '', 'off' );
		$this->store_raw( 'zero-spam-settings', array( 'blocked_email_domains' => 'old.example' ) );

		$this->migrate();
		$this->forget_option_caches( self::OWN );

		$this->assertSame( '', get_option( self::OWN ) );
		$this->assertSame( array(), get_option( 'zero-spam-settings' ) );
	}

	/**
	 * An own option 5.x left autoloaded stops being autoloaded, unchanged.
	 *
	 * @dataProvider provide_autoloaded_values
	 *
	 * @param string $autoload Autoload value of the row.
	 */
	public function test_stops_autoloading_the_own_option( string $autoload ): void {
		$this->store_raw( self::OWN, 'kept.example', $autoload );

		$this->migrate();
		$this->forget_option_caches( self::OWN );

		$this->assertSame( 'off', $this->row( self::OWN )->autoload );
		$this->assertSame( 'kept.example', get_option( self::OWN ) );
	}

	/**
	 * Autoload values WordPress loads on every request.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_autoloaded_values(): array {
		return array(
			'yes'     => array( 'yes' ),
			'on'      => array( 'on' ),
			'auto-on' => array( 'auto-on' ),
			'auto'    => array( 'auto' ),
		);
	}

	/**
	 * A stored value that is not a list is removed and creates no option.
	 *
	 * @dataProvider provide_empty_values
	 *
	 * @param mixed $value Stored value.
	 */
	public function test_empty_copy_is_removed_without_creating_the_option( $value ): void {
		$this->store_raw(
			'zero-spam-settings',
			array(
				'share_data'            => 'enabled',
				'blocked_email_domains' => $value,
			)
		);

		$this->migrate();
		$this->forget_option_caches( self::OWN );

		$this->assertFalse( get_option( self::OWN ) );
		$this->assertSame( array( 'share_data' => 'enabled' ), get_option( 'zero-spam-settings' ) );
	}

	/**
	 * Values of a list that was never filled in.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_empty_values(): array {
		return array(
			'false'       => array( false ),
			'empty'       => array( '' ),
			'white space' => array( " \n" ),
			'array'       => array( array( 'old.example' ) ),
		);
	}

	/**
	 * A fresh install gets no option: a settings row would mark the site configured.
	 */
	public function test_fresh_install_stays_untouched(): void {
		$this->assertTrue( $this->migrate() );

		$this->assertFalse( get_option( 'zero-spam-settings' ) );
		$this->assertFalse( get_option( self::OWN ) );
	}

	/**
	 * Running it again writes nothing.
	 */
	public function test_second_run_writes_nothing(): void {
		$this->store_raw( 'zero-spam-settings', array( 'blocked_email_domains' => 'old.example' ) );
		$this->migrate();

		$queries = $this->capture_queries(
			function (): void {
				$this->migrate();
			}
		);
		$writes  = preg_grep( '/^\s*(UPDATE|INSERT|DELETE|REPLACE)/i', $queries );

		$this->assertSame( array(), array_values( (array) $writes ) );
	}

	/**
	 * Saving another setting carries the older copy along: it must not
	 * replace the newer list (it did, on every save that merges stored values).
	 */
	public function test_saving_another_setting_keeps_the_newer_list(): void {
		$this->store_raw( self::OWN, 'new.example', 'off' );
		$this->store_raw( 'zero-spam-settings', array( 'blocked_email_domains' => 'old.example' ) );

		update_option( 'zero-spam-settings', array_merge( get_option( 'zero-spam-settings' ), array( 'share_data' => 'enabled' ) ) );

		$this->assertSame( 'new.example', get_option( self::OWN ) );
		$this->assertSame( array( 'share_data' => 'enabled' ), get_option( 'zero-spam-settings' ) );
	}

	/**
	 * A list really being written still moves to the own option.
	 */
	public function test_writing_a_different_list_still_replaces_it(): void {
		$this->store_raw( self::OWN, 'new.example', 'off' );
		$this->store_raw( 'zero-spam-settings', array( 'blocked_email_domains' => 'old.example' ) );

		update_option( 'zero-spam-settings', array( 'blocked_email_domains' => 'written.example' ) );

		$this->assertSame( 'written.example', get_option( self::OWN ) );
		$this->assertSame( array(), get_option( 'zero-spam-settings' ) );
	}

	/**
	 * Without an own option yet, a carried copy is still the only list: it moves.
	 */
	public function test_saving_moves_the_only_copy(): void {
		$this->store_raw( 'zero-spam-settings', array( 'blocked_email_domains' => 'only.example' ) );

		update_option( 'zero-spam-settings', array_merge( get_option( 'zero-spam-settings' ), array( 'share_data' => 'enabled' ) ) );

		$this->assertSame( 'only.example', get_option( self::OWN ) );
		$this->assertSame( array( 'share_data' => 'enabled' ), get_option( 'zero-spam-settings' ) );
	}
}
