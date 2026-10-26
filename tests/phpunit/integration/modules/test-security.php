<?php
/**
 * Tests for the Security module.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Modules\Security\Security;
use ZeroSpam\Tests\TestCase;

/**
 * Each hardening setting changes only its own behaviour, judged by what
 * WordPress outputs rather than by which callbacks are hooked. "Disable
 * XML-RPC" and "Hide the WordPress Version" (6.0) are on unless turned off.
 *
 * @covers \ZeroSpam\Modules\Security\Security::init
 * @covers \ZeroSpam\Modules\Security\Security::settings
 * @covers \ZeroSpam\Modules\Security\Security::default_on_value
 */
class Test_Security extends TestCase {

	/**
	 * Runs the module's init, with one setting enabled when given.
	 *
	 * @param string|null $setting Setting key to enable.
	 */
	private function init_with( ?string $setting ): void {
		if ( null !== $setting ) {
			$this->set_setting( $setting, 'enabled', 'security' );
		}

		( new Security() )->init();
	}

	/**
	 * Whether the front-end head will print the emoji detection script.
	 *
	 * Rendering wp_head cannot answer this: core prints the script at most
	 * once per PHP process (a function-level static), so it checks for core's
	 * own callback instead.
	 */
	private function head_prints_emoji_script(): bool {
		return false !== has_action( 'wp_head', 'print_emoji_detection_script' );
	}

	/**
	 * A stylesheet URL as WordPress hands it to style_loader_src.
	 */
	private function filtered_style_url(): string {
		$url = 'https://example.org/wp-includes/css/dashicons.css?ver=' . get_bloginfo( 'version' );

		return (string) apply_filters( 'style_loader_src', $url, 'dashicons' );
	}

	/**
	 * Editor plugins after the tiny_mce_plugins filter.
	 *
	 * @return string[]
	 */
	private function filtered_editor_plugins(): array {
		return (array) apply_filters( 'tiny_mce_plugins', array( 'wpemoji', 'lists' ) );
	}

	/**
	 * "Disable emojis" removes the emoji script and leaves asset URLs alone.
	 */
	public function test_disable_emojis_only_disables_emojis(): void {
		$this->init_with( 'disable_emojis' );

		$this->assertFalse( $this->head_prints_emoji_script(), 'The emoji detection script is still printed.' );
		$this->assertSame( array( 'lists' ), array_values( $this->filtered_editor_plugins() ), 'The editor still loads the emoji plugin.' );
		$this->assertStringContainsString( '?ver=', $this->filtered_style_url(), 'Disabling emojis stripped ?ver= from asset URLs.' );
	}

	/**
	 * "Remove resource query parameters" strips ?ver= and leaves emojis alone.
	 */
	public function test_remove_resource_query_parameters_only_strips_versions(): void {
		$this->init_with( 'remove_resource_query_parameters' );

		$this->assertSame( 'https://example.org/wp-includes/css/dashicons.css', $this->filtered_style_url(), 'Asset URLs keep ?ver=.' );
		$this->assertTrue( $this->head_prints_emoji_script(), 'Stripping ?ver= disabled emojis.' );
	}

	/**
	 * With neither setting enabled, nothing changes.
	 */
	public function test_defaults_change_neither(): void {
		$this->init_with( null );

		$this->assertTrue( $this->head_prints_emoji_script() );
		$this->assertStringContainsString( '?ver=', $this->filtered_style_url() );
		$this->assertSame( array( 'wpemoji', 'lists' ), $this->filtered_editor_plugins() );
	}

	/**
	 * The version stripper removes only the WordPress version argument.
	 */
	public function test_remove_resource_query_params_strips_the_wordpress_version(): void {
		$version = get_bloginfo( 'version' );
		$plugin  = new Security();

		$this->assertSame( 'https://example.org/a.css', $plugin->remove_resource_query_params( "https://example.org/a.css?ver={$version}" ) );
		$this->assertSame( 'https://example.org/a.css?ver=1.2.3', $plugin->remove_resource_query_params( 'https://example.org/a.css?ver=1.2.3' ) );
	}

	/**
	 * Runs the module's init with the security option set to exactly this.
	 *
	 * @param array<string, mixed>|null $option Option value, or null to delete it.
	 * @return Security The initialized module.
	 */
	private function init_with_option( ?array $option ): Security {
		self::restore_core_hooks();

		if ( null === $option ) {
			delete_option( 'zero-spam-security' );
		} else {
			update_option( 'zero-spam-security', $option );
		}
		self::reset_static_caches();

		$security = new Security();
		$security->init();

		return $security;
	}

	/**
	 * Undoes what the module did while WordPress loaded for the suite, so
	 * each test sees WordPress's own hooks (they are restored after it).
	 */
	public static function restore_core_hooks(): void {
		remove_all_filters( 'xmlrpc_enabled' );
		add_action( 'wp_head', 'wp_generator' );
		add_action( 'opml_head', 'the_generator' );
	}

	/**
	 * Whether XML-RPC methods that need a login are enabled.
	 */
	private function xmlrpc_enabled(): bool {
		return (bool) apply_filters( 'xmlrpc_enabled', true );
	}

	/**
	 * Whether the front-end head prints the generator tag.
	 */
	private function head_prints_generator(): bool {
		return false !== has_action( 'wp_head', 'wp_generator' );
	}

	/**
	 * With nothing stored both switches are on: XML-RPC is disabled and the
	 * generator tag removed, as every version before 6.0 always did.
	 */
	public function test_new_switches_are_on_when_nothing_is_stored(): void {
		$security = $this->init_with_option( null );

		$this->assertFalse( $this->xmlrpc_enabled(), 'XML-RPC is still enabled.' );
		$this->assertNotFalse( has_action( 'init', array( $security, 'block_xmlrpc' ) ), 'xmlrpc.php is not blocked.' );
		$this->assertFalse( $this->head_prints_generator(), 'The generator tag is still printed.' );
		$this->assertFalse( has_action( 'opml_head', 'the_generator' ) );
	}

	/**
	 * Turning "Disable XML-RPC" off enables XML-RPC again and leaves the version hidden.
	 */
	public function test_disable_xmlrpc_off_enables_xmlrpc(): void {
		$security = $this->init_with_option( array( 'disable_xmlrpc' => 'disabled' ) );

		$this->assertTrue( $this->xmlrpc_enabled(), 'XML-RPC stays disabled.' );
		$this->assertFalse( has_action( 'init', array( $security, 'block_xmlrpc' ) ), 'xmlrpc.php is still blocked.' );
		$this->assertFalse( $this->head_prints_generator(), 'Turning XML-RPC on showed the version.' );
	}

	/**
	 * Turning "Hide the WordPress Version" off keeps the generator tag and XML-RPC disabled.
	 */
	public function test_hide_wp_version_off_keeps_the_generator(): void {
		$this->init_with_option( array( 'hide_wp_version' => 'disabled' ) );

		$this->assertTrue( $this->head_prints_generator(), 'The generator tag was removed.' );
		$this->assertNotFalse( has_action( 'opml_head', 'the_generator' ) );
		$this->assertFalse( $this->xmlrpc_enabled(), 'Showing the version enabled XML-RPC.' );
	}

	/**
	 * A stored false (an older save of the module) also means off; `enabled` means on.
	 */
	public function test_stored_values_win_over_the_default(): void {
		$this->init_with_option(
			array(
				'disable_xmlrpc'  => false,
				'hide_wp_version' => 'enabled',
			)
		);

		$this->assertTrue( $this->xmlrpc_enabled() );
		$this->assertFalse( $this->head_prints_generator() );
	}

	/**
	 * Both switches are registered as on-by-default, recommended checkboxes
	 * in Protection › Detection, and a network can lock them.
	 */
	public function test_switches_are_registered(): void {
		delete_option( 'zero-spam-security' );
		self::reset_static_caches();

		foreach ( Security::DEFAULT_ON as $key ) {
			$entry = \ZeroSpam\Core\Settings_Schema::setting( $key );

			$this->assertNotNull( $entry, $key );
			$this->assertSame( 'checkbox', $entry['type'], $key );
			$this->assertSame( 'security', $entry['module'], $key );
			$this->assertSame( 'enabled', $entry['default'], $key );
			$this->assertSame( 'enabled', $entry['recommended'], $key );
			$this->assertSame( 'enabled', $entry['value'], $key );
			$this->assertTrue( $entry['lockable'], $key );
			$this->assertSame( array( 'protection', 'detection' ), array( $entry['area'], $entry['group'] ), $key );
			$this->assertTrue( \ZeroSpam\Core\Settings_Schema::is_default_on( \ZeroSpam\Core\Settings_Schema::registry()[ $key ] ), $key );
		}

		$this->assertFalse( \ZeroSpam\Core\Settings_Schema::is_default_on( \ZeroSpam\Core\Settings_Schema::registry()['disable_emojis'] ), 'An off-by-default switch is not default-on.' );
	}

	/**
	 * On a network a locked network value turns a switch off for every site.
	 *
	 * @group ms-required
	 */
	public function test_locked_network_value_turns_a_switch_off(): void {
		$this->set_network_setting( 'disable_xmlrpc', 'disabled', true );

		$this->init_with_option( array( 'disable_xmlrpc' => 'enabled' ) );

		$this->assertTrue( $this->xmlrpc_enabled(), 'The locked network value did not apply.' );
	}
}
