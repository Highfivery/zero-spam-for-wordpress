<?php
/**
 * Tests for what a network site stores when its settings are saved.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Admin\Site_Admin_Overrides;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Tests\TestCase;

/**
 * A site save (REST, WP-CLI, imports) neither stores locked settings nor
 * turns values equal to the network's into overrides (Site_Admin_Overrides'
 * save filters). The 5.x settings page and its lock UI were removed in
 * 6.0.0.
 *
 * Saves run on the main site without switch_to_blog(): writes made while
 * switched are network operations, which the save filter leaves alone.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Admin\Site_Admin_Overrides
 */
class Test_Site_Admin_Overrides extends TestCase {

	/**
	 * Text setting on the Comments view.
	 */
	const TEXT_KEY = 'comment_spam_message';

	/**
	 * Checkbox setting on the Comments view.
	 */
	const CHECKBOX_KEY = 'log_blocked_comments';

	/**
	 * Option that stores the Comments view.
	 */
	const OPTION = 'zero-spam-comments';

	/**
	 * A second network site, for "Apply to All Sites".
	 *
	 * @var int
	 */
	private static int $other_site = 0;

	/**
	 * Creates the second site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$other_site = self::create_site_with_tables( $factory );
	}

	/**
	 * Sets up a site administrator.
	 *
	 * The save filters are not registered here: the plugin registers them on
	 * `init` for every request on a network site (not only in the admin), and
	 * that registration is what these tests exercise.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->as_role( 'administrator' );
		delete_option( self::OPTION );
		delete_option( 'zerospam_blocked_email_domains' );
	}

	/**
	 * Saves a module option as a site writer does (not switched).
	 *
	 * @param array<string, mixed> $submitted Values written.
	 * @param string               $option    Option saved.
	 * @return array<string, mixed> What was stored.
	 */
	private function save( array $submitted, string $option = self::OPTION ): array {
		update_option( $option, $submitted );

		$stored = get_option( $option );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Reads the value the site resolves for a setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	private function resolved( string $key ) {
		self::reset_static_caches();

		return Settings::get_settings( $key );
	}

	/**
	 * A tampered save cannot store a locked setting, and keeps the override the site had before the lock.
	 */
	public function test_locked_key_is_not_persisted(): void {
		$this->set_setting( self::TEXT_KEY, 'Site message before the lock.', 'comments' );
		$this->set_network_setting( self::TEXT_KEY, 'Network locked message.', true );

		$stored = $this->save( array( self::TEXT_KEY => 'Tampered message.' ) );

		$this->assertSame( 'Site message before the lock.', $stored[ self::TEXT_KEY ], 'The stored value is unchanged.' );
		$this->assertSame( 'Network locked message.', $this->resolved( self::TEXT_KEY ) );

		// A write without the key keeps the stored value too.
		$stored = $this->save( array() );
		$this->assertSame( 'Site message before the lock.', $stored[ self::TEXT_KEY ] );
	}

	/**
	 * Without an earlier override, a locked key is not stored at all.
	 */
	public function test_locked_key_without_override_is_not_stored(): void {
		$this->set_network_setting( self::TEXT_KEY, 'Network locked message.', true );

		$stored = $this->save( array( self::TEXT_KEY => 'Tampered message.' ) );

		$this->assertArrayNotHasKey( self::TEXT_KEY, $stored );
		$this->assertSame( 'Network locked message.', $this->resolved( self::TEXT_KEY ) );
	}

	/**
	 * Key, network value, submitted fields, whether an override is stored, value
	 * stored, and whether the option exists before the save.
	 *
	 * A missing option is sanitized twice by WordPress (update_option() then
	 * add_option()), an existing one once; both paths are covered.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: bool, 4: mixed, 5: bool}>
	 */
	public static function data_unlocked_saves(): array {
		$cases = array(
			'unticked checkbox, network disabled' => array( self::CHECKBOX_KEY, 'disabled', array( self::CHECKBOX_KEY => false ), false, null ),
			'unticked checkbox, network empty'    => array( self::CHECKBOX_KEY, '', array( self::CHECKBOX_KEY => false ), false, null ),
			'ticked checkbox, network enabled'    => array( self::CHECKBOX_KEY, 'enabled', array( self::CHECKBOX_KEY => 'enabled' ), false, null ),
			'unchanged text'                      => array( self::TEXT_KEY, 'Network message.', array( self::TEXT_KEY => 'Network message.' ), false, null ),
			'unticked checkbox, network enabled'  => array( self::CHECKBOX_KEY, 'enabled', array( self::CHECKBOX_KEY => false ), true, false ),
			'ticked checkbox, network disabled'   => array( self::CHECKBOX_KEY, 'disabled', array( self::CHECKBOX_KEY => 'enabled' ), true, 'enabled' ),
			'changed text'                        => array( self::TEXT_KEY, 'Network message.', array( self::TEXT_KEY => 'Site message.' ), true, 'Site message.' ),
		);

		$data = array();
		foreach ( $cases as $name => $case ) {
			$data[ "{$name}, first save" ]      = array_merge( $case, array( false ) );
			$data[ "{$name}, existing option" ] = array_merge( $case, array( true ) );
		}

		return $data;
	}

	/**
	 * An unlocked setting is stored only when it differs from the network value.
	 *
	 * @dataProvider data_unlocked_saves
	 *
	 * @param string               $key       Setting key.
	 * @param string               $network   Network default (not locked).
	 * @param array<string, mixed> $submitted Submitted fields.
	 * @param bool                 $override  Whether the save creates a site override.
	 * @param mixed                $expected  Stored value when it does.
	 * @param bool                 $existing  Whether the option exists before the save.
	 */
	public function test_unlocked_save_stores_only_real_changes( string $key, string $network, array $submitted, bool $override, $expected, bool $existing ): void {
		if ( $existing ) {
			add_option( self::OPTION, array( 'verify_comments' => 'enabled' ) );
		}
		$this->set_network_setting( $key, $network );

		$stored = $this->save( $submitted );

		if ( $override ) {
			$this->assertArrayHasKey( $key, $stored );
			$this->assertSame( $expected, $stored[ $key ] );
			$this->assertFalse( ( new Network_Settings() )->is_using_default( $key ) );
		} else {
			$this->assertArrayNotHasKey( $key, $stored, 'An unchanged setting does not become a site override.' );
			$this->assertTrue( ( new Network_Settings() )->is_using_default( $key ) );
		}
	}

	/**
	 * After a save that changed nothing, a new network default still reaches the site.
	 */
	public function test_network_default_still_applies_after_an_unchanged_save(): void {
		$this->set_network_setting( self::CHECKBOX_KEY, 'disabled' );
		$this->save( array( self::CHECKBOX_KEY => false ) );

		$this->set_network_setting( self::CHECKBOX_KEY, 'enabled' );

		$this->assertSame( 'enabled', $this->resolved( self::CHECKBOX_KEY ) );
	}

	/**
	 * An earlier override that equals the network value is dropped on save.
	 */
	public function test_override_equal_to_network_is_dropped(): void {
		$this->set_setting( self::CHECKBOX_KEY, false, 'comments' );
		$this->set_network_setting( self::CHECKBOX_KEY, 'disabled' );

		$stored = $this->save( array( self::CHECKBOX_KEY => false ) );

		$this->assertArrayNotHasKey( self::CHECKBOX_KEY, $stored );
	}

	/**
	 * Settings the network has not configured are stored as on a single site.
	 */
	public function test_settings_without_network_value_are_stored_as_before(): void {
		$this->set_network_setting( self::TEXT_KEY, 'Network message.' );

		$stored = $this->save(
			array(
				self::CHECKBOX_KEY => false,
				'verify_comments'  => false,
			)
		);

		$this->assertArrayHasKey( self::CHECKBOX_KEY, $stored );
		$this->assertFalse( $stored[ self::CHECKBOX_KEY ] );
		$this->assertArrayHasKey( 'verify_comments', $stored );
		$this->assertFalse( $stored['verify_comments'] );
	}

	/**
	 * Network operations write inside switch_to_blog(); the save filter leaves them alone.
	 */
	public function test_writes_while_switched_are_not_filtered(): void {
		$this->set_network_setting( self::TEXT_KEY, 'Network locked message.', true );

		switch_to_blog( get_current_blog_id() );
		update_option( self::OPTION, array( self::TEXT_KEY => 'Network locked message.' ) );
		$stored = get_option( self::OPTION );
		restore_current_blog();

		$this->assertIsArray( $stored );
		$this->assertSame( 'Network locked message.', $stored[ self::TEXT_KEY ] );
	}

	/**
	 * "Apply to All Sites" (defaults only) leaves a site on the network defaults.
	 *
	 * It writes one key at a time inside switch_to_blog(). In 5.x, through
	 * admin-ajax.php, the settings page's sanitize callback filled every
	 * unticked checkbox on each write, which stored verify_comments = false
	 * before verify_comments itself was applied.
	 */
	public function test_apply_defaults_to_a_site_keeps_its_network_defaults(): void {
		$this->set_network_setting( self::CHECKBOX_KEY, 'enabled' );
		$this->set_network_setting( 'verify_comments', 'enabled' );
		grant_super_admin( get_current_user_id() );

		$result = ( new Network_Settings_Manager() )->apply_to_sites( false, array( self::$other_site ), 'defaults_only' );

		switch_to_blog( self::$other_site );
		self::reset_static_caches();
		$verify   = Settings::get_settings( 'verify_comments' );
		$comments = get_option( self::OPTION );
		$general  = get_option( 'zero-spam-settings' );
		restore_current_blog();

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'enabled', $verify, 'Comment protection stays on.' );
		$this->assertEqualsCanonicalizing(
			array(
				self::CHECKBOX_KEY => 'enabled',
				'verify_comments'  => 'enabled',
			),
			$comments,
			'Only the network settings are written; no other key becomes an override.'
		);
		$this->assertFalse( is_array( $general ) && array_key_exists( 'widget_visibility', $general ), 'Other modules are not touched.' );
	}

	/**
	 * A write outside the settings form stores only what it writes.
	 */
	public function test_writes_outside_the_settings_form_are_not_filled_in(): void {
		update_option( self::OPTION, array( self::TEXT_KEY => 'Site message.' ) );

		$this->assertSame( array( self::TEXT_KEY => 'Site message.' ), get_option( self::OPTION ) );
	}

	/**
	 * A tampered save cannot change a locked blocked_email_domains, which has its own option.
	 */
	public function test_locked_blocked_email_domains_survive_a_tampered_save(): void {
		update_option( 'zerospam_blocked_email_domains', 'site.example' );
		$this->set_network_setting( 'blocked_email_domains', 'network.example', true );

		$this->save( array( 'blocked_email_domains' => 'tampered.example' ), 'zero-spam-settings' );

		$this->assertSame( 'site.example', get_option( 'zerospam_blocked_email_domains' ) );
	}

	/**
	 * The "Override & Update Blocked Email Domains" action cannot change it while locked either.
	 */
	public function test_locked_blocked_email_domains_survive_the_update_action(): void {
		update_option( 'zerospam_blocked_email_domains', 'site.example' );
		$this->set_network_setting( 'blocked_email_domains', 'network.example', true );

		Settings::update_blocked_email_domains();

		$this->assertSame( 'site.example', get_option( 'zerospam_blocked_email_domains' ) );
	}

	/**
	 * The recommended list replaces only the domains option: every network
	 * default stays followed (AGENTS.md §8; before 6.0.0 it rewrote
	 * `zero-spam-settings` with resolved values, turning network defaults
	 * into site overrides).
	 */
	public function test_update_action_leaves_the_core_settings_alone(): void {
		$this->set_network_setting( 'max_logs', 4321 );
		$this->set_network_setting( 'log_blocked_ips', 'enabled' );
		delete_option( 'zero-spam-settings' );

		$this->assertTrue( Settings::update_blocked_email_domains() );

		$this->assertFalse( get_option( 'zero-spam-settings' ), 'Nothing becomes a site override.' );
		$this->assertNotSame( '', (string) get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertTrue( ( new Network_Settings() )->is_using_default( 'max_logs' ) );
		$this->assertSame( 4321, (int) $this->resolved( 'max_logs' ) );
	}

	/**
	 * Unlocked, the action updates the list as before.
	 */
	public function test_unlocked_blocked_email_domains_can_be_updated(): void {
		update_option( 'zerospam_blocked_email_domains', 'site.example' );
		$this->set_network_setting( 'blocked_email_domains', 'network.example' );

		Settings::update_blocked_email_domains();

		$this->assertNotSame( 'site.example', get_option( 'zerospam_blocked_email_domains' ) );
	}

	/**
	 * WP-CLI (`wp zerospam set`) cannot store a value for a locked setting.
	 */
	public function test_update_setting_cannot_store_a_locked_value(): void {
		update_option( 'zerospam_blocked_email_domains', 'site.example' );
		$this->set_network_setting( self::TEXT_KEY, 'Network locked message.', true );
		$this->set_network_setting( 'blocked_email_domains', 'network.example', true );

		Utilities::update_setting( self::TEXT_KEY, 'CLI message.' );
		Utilities::update_setting( 'blocked_email_domains', 'cli.example' );

		$stored = get_option( self::OPTION );
		$this->assertFalse( is_array( $stored ) && array_key_exists( self::TEXT_KEY, $stored ) );
		$this->assertSame( 'site.example', get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertSame( 'Network locked message.', $this->resolved( self::TEXT_KEY ) );
	}
}
