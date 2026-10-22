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
 * Each hardening setting changes only its own behaviour.
 *
 * @covers \ZeroSpam\Modules\Security\Security::init
 */
class Test_Security extends TestCase {

	/**
	 * Runs the module's init with only the given setting enabled.
	 *
	 * @param string $setting Setting key to enable.
	 */
	private function init_with( string $setting ): Security {
		$this->set_setting( $setting, 'enabled', 'security' );

		$security = new Security();
		$security->init();

		return $security;
	}

	/**
	 * "Disable emojis" removes the emoji scripts and leaves asset URLs alone.
	 *
	 * @group known-defect
	 */
	public function test_disable_emojis_only_disables_emojis(): void {
		$security = $this->init_with( 'disable_emojis' );

		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ), 'Emoji detection script is still printed.' );
		$this->assertSame( 10, has_filter( 'tiny_mce_plugins', array( $security, 'disable_emojis' ) ), 'The editor still loads the emoji plugin.' );
		$this->assertFalse( has_filter( 'style_loader_src', array( $security, 'remove_resource_query_params' ) ), 'Disabling emojis changed stylesheet URLs.' );
		$this->assertFalse( has_filter( 'script_loader_src', array( $security, 'remove_resource_query_params' ) ), 'Disabling emojis changed script URLs.' );
	}

	/**
	 * "Remove resource query parameters" strips ?ver= and leaves emojis alone.
	 *
	 * @group known-defect
	 */
	public function test_remove_resource_query_parameters_only_strips_versions(): void {
		$security = $this->init_with( 'remove_resource_query_parameters' );

		$this->assertSame( 10, has_filter( 'style_loader_src', array( $security, 'remove_resource_query_params' ) ), 'Stylesheet URLs keep ?ver=.' );
		$this->assertSame( 10, has_filter( 'script_loader_src', array( $security, 'remove_resource_query_params' ) ), 'Script URLs keep ?ver=.' );
		$this->assertSame( 7, has_action( 'wp_head', 'print_emoji_detection_script' ), 'Stripping ?ver= disabled emojis.' );
	}

	/**
	 * With neither setting enabled, nothing changes.
	 */
	public function test_defaults_change_neither(): void {
		$security = new Security();
		$security->init();

		$this->assertSame( 7, has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_filter( 'style_loader_src', array( $security, 'remove_resource_query_params' ) ) );
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
}
