<?php
/**
 * Tests for the settings page on a network site: lock UI and what a save stores.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Settings as Settings_Page;
use ZeroSpam\Core\Settings;
use ZeroSpam\Includes\Admin\Site_Admin_Overrides;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The settings page fires the hooks Site_Admin_Overrides listens to, and a
 * site save neither stores locked settings nor turns untouched ones into
 * overrides.
 *
 * Saves run on the main site without switch_to_blog(): writes made while
 * switched are network operations, which the save filter leaves alone.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Admin\Settings::settings_field
 * @covers \ZeroSpam\Core\Admin\Settings::settings_page
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
	 * Settings page under test.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $page;

	/**
	 * Sets up a site administrator on the settings screen with the network UI loaded.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		delete_option( self::OPTION );

		$overrides = new Site_Admin_Overrides();
		$overrides->register_save_filters();

		$this->page = new Settings_Page();
		$this->page->register_settings();
	}

	/**
	 * Renders the Comments view of the settings page.
	 *
	 * @return string HTML.
	 */
	private function render_comments_view(): string {
		// Fields capture their resolved value when registered, as on admin_init.
		self::reset_static_caches();
		$this->page->register_settings();
		$this->set_request( '/wp-admin/options-general.php?page=wordpress-zero-spam-settings&subview=comments' );

		ob_start();
		$this->page->settings_page();

		return (string) ob_get_clean();
	}

	/**
	 * Parses rendered HTML.
	 *
	 * @param string $html HTML.
	 */
	private function xpath( string $html ): DOMXPath {
		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new DOMXPath( $dom );
	}

	/**
	 * Text content of a node, whitespace trimmed.
	 *
	 * @param DOMNode|null $node Node.
	 */
	private static function text_of( ?DOMNode $node ): string {
		return null === $node ? '' : trim( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
	}

	/**
	 * Saves the Comments view the way options.php does.
	 *
	 * @param array<string, mixed> $submitted Submitted fields (unticked checkboxes are absent).
	 * @return array<string, mixed> What was stored.
	 */
	private function save( array $submitted ): array {
		update_option( self::OPTION, $submitted );

		$stored = get_option( self::OPTION );

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
	 * Every hook fires with the setting key and the field arguments.
	 */
	public function test_hooks_fire_while_rendering(): void {
		$this->set_network_setting( self::TEXT_KEY, 'Network locked message.', true );

		$calls = array(
			'top'      => array(),
			'before'   => array(),
			'after'    => array(),
			'disabled' => array(),
		);
		add_action(
			'zerospam_settings_page_top',
			static function ( $subview ) use ( &$calls ) {
				$calls['top'][] = $subview;
			}
		);
		add_action(
			'zerospam_setting_field_before',
			static function ( $key, $args ) use ( &$calls ) {
				$calls['before'][ $key ] = $args;
			},
			10,
			2
		);
		add_action(
			'zerospam_setting_field_after',
			static function ( $key, $args ) use ( &$calls ) {
				$calls['after'][ $key ] = $args;
			},
			10,
			2
		);
		add_filter(
			'zerospam_setting_field_disabled',
			static function ( $disabled, $key, $args ) use ( &$calls ) {
				$calls['disabled'][ $key ] = array( $disabled, $args );

				return $disabled;
			},
			5,
			3
		);

		$this->render_comments_view();

		$this->assertSame( array( 'comments' ), $calls['top'], 'zerospam_settings_page_top fires once with the view.' );

		foreach ( array( self::TEXT_KEY, self::CHECKBOX_KEY, 'verify_comments' ) as $key ) {
			foreach ( array( 'before', 'after' ) as $hook ) {
				$this->assertArrayHasKey( $key, $calls[ $hook ], "zerospam_setting_field_{$hook} fires for {$key}." );
				$this->assertSame( $key, $calls[ $hook ][ $key ]['label_for'] );
				$this->assertSame( 'comments', $calls[ $hook ][ $key ]['module'] );
			}
			$this->assertArrayHasKey( $key, $calls['disabled'], "zerospam_setting_field_disabled runs for {$key}." );
			$this->assertFalse( $calls['disabled'][ $key ][0], 'The filter starts from false.' );
		}

		$this->assertSame( 'Network locked message.', $calls['before'][ self::TEXT_KEY ]['value'], 'The field arguments carry the resolved value.' );
	}

	/**
	 * A locked setting is shown as locked and disabled, with an accessible explanation.
	 */
	public function test_locked_field_renders_locked_and_disabled(): void {
		$this->set_network_setting( self::TEXT_KEY, 'Network locked message.', true );

		$xpath = $this->xpath( $this->render_comments_view() );

		$notice = $xpath->query( '//div[contains(@class, "zerospam-network-notice")]' );
		$this->assertSame( 1, $notice->length, 'The network notice is printed at the top.' );
		$this->assertStringContainsString( 'inline', (string) $notice->item( 0 )->getAttribute( 'class' ), 'The notice stays where it is printed.' );

		$input = $xpath->query( '//input[@id="' . self::TEXT_KEY . '"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertTrue( $input->hasAttribute( 'disabled' ), 'The locked control is disabled.' );
		$this->assertSame( 'Network locked message.', $input->getAttribute( 'value' ) );

		$description_id = $input->getAttribute( 'aria-describedby' );
		$this->assertNotSame( '', $description_id );
		$description = $xpath->query( '//*[@id="' . $description_id . '"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $description );
		$this->assertStringContainsString( 'Locked by the network administrator', self::text_of( $description ) );

		$badge = $xpath->query( '//tr[.//input[@id="' . self::TEXT_KEY . '"]]//*[contains(@class, "zerospam-locked-badge")]' );
		$this->assertSame( 1, $badge->length, 'The row shows the lock badge.' );
		$this->assertStringContainsString( 'Locked by Network Admin', self::text_of( $badge->item( 0 ) ) );

		$unlocked = $xpath->query( '//input[@id="' . self::CHECKBOX_KEY . 'enabled"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $unlocked );
		$this->assertFalse( $unlocked->hasAttribute( 'disabled' ), 'Settings that are not locked stay editable.' );
		$this->assertFalse( $unlocked->hasAttribute( 'aria-describedby' ) );
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

		// A disabled control is not submitted at all; the stored value survives that too.
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
			'unticked checkbox, network disabled' => array( self::CHECKBOX_KEY, 'disabled', array(), false, null ),
			'unticked checkbox, network empty'    => array( self::CHECKBOX_KEY, '', array(), false, null ),
			'ticked checkbox, network enabled'    => array( self::CHECKBOX_KEY, 'enabled', array( self::CHECKBOX_KEY => 'enabled' ), false, null ),
			'unchanged text'                      => array( self::TEXT_KEY, 'Network message.', array( self::TEXT_KEY => 'Network message.' ), false, null ),
			'unticked checkbox, network enabled'  => array( self::CHECKBOX_KEY, 'enabled', array(), true, false ),
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
		$this->save( array() );

		$this->set_network_setting( self::CHECKBOX_KEY, 'enabled' );

		$this->assertSame( 'enabled', $this->resolved( self::CHECKBOX_KEY ) );
	}

	/**
	 * An earlier override that equals the network value is dropped on save.
	 */
	public function test_override_equal_to_network_is_dropped(): void {
		$this->set_setting( self::CHECKBOX_KEY, false, 'comments' );
		$this->set_network_setting( self::CHECKBOX_KEY, 'disabled' );

		$stored = $this->save( array() );

		$this->assertArrayNotHasKey( self::CHECKBOX_KEY, $stored );
	}

	/**
	 * Settings the network has not configured are stored as on a single site.
	 */
	public function test_settings_without_network_value_are_stored_as_before(): void {
		$this->set_network_setting( self::TEXT_KEY, 'Network message.' );

		$stored = $this->save( array() );

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
}
