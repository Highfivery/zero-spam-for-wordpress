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
 * WordPress outputs rather than by which callbacks are hooked.
 *
 * @covers \ZeroSpam\Modules\Security\Security::init
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
	 *
	 * @group known-defect
	 */
	public function test_disable_emojis_only_disables_emojis(): void {
		$this->init_with( 'disable_emojis' );

		$this->assertFalse( $this->head_prints_emoji_script(), 'The emoji detection script is still printed.' );
		$this->assertSame( array( 'lists' ), array_values( $this->filtered_editor_plugins() ), 'The editor still loads the emoji plugin.' );
		$this->assertStringContainsString( '?ver=', $this->filtered_style_url(), 'Disabling emojis stripped ?ver= from asset URLs.' );
	}

	/**
	 * "Remove resource query parameters" strips ?ver= and leaves emojis alone.
	 *
	 * @group known-defect
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
}
