<?php
/**
 * Tests for the in-admin documentation (Zero Spam → Help).
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * The Help guides list the developer hooks added in 5.7.14.
 *
 * @covers \ZeroSpam\Core\Admin\Documentation::sections
 * @covers \ZeroSpam\Core\Admin\Documentation::render
 */
class Test_Documentation extends TestCase {

	/**
	 * Renders the Developer Hooks guide as Help gets it.
	 *
	 * @return string Guide HTML.
	 */
	private function render(): string {
		$this->as_role( 'administrator' );

		$sections = \ZeroSpam\Core\Admin\Documentation::sections();
		$this->assertArrayHasKey( 'developer-hooks', $sections );

		return \ZeroSpam\Core\Admin\Documentation::render( $sections['developer-hooks']['template'] );
	}

	/**
	 * The Developer Hooks section is registered and rendered.
	 */
	public function test_developer_hooks_section_is_rendered(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'id="developer-hooks-docs"', $html );
		$this->assertStringContainsString( 'https://github.com/Highfivery/zero-spam-for-wordpress/wiki/Hooks', $html );
	}

	/**
	 * Every hook added in 5.7.14 is documented, and every hook name in the
	 * Developer Hooks section is one the plugin really calls.
	 */
	public function test_every_new_hook_is_documented(): void {
		$html = $this->render();

		$hooks = array(
			'zerospam_login_skip_check',
			'zerospam_login_intent_ttl',
			'zerospam_login_checks_waived',
			'zerospam_trusted_proxies',
			'zerospam_allow_private_forwarded_ip',
			'zerospam_shared_ip_warning',
			'zerospam_rescue_form_enabled',
			'zerospam_rescue_email_via_cron',
			'zerospam_admin_safe_ip_enabled',
			'zerospam_htaccess_file',
		);

		foreach ( $hooks as $hook ) {
			$this->assertStringContainsString( '<code>' . $hook . '</code>', $html, "{$hook} is not documented." );
		}

		// Only the Developer Hooks section: other sections also name setting keys in <code>.
		$section = (string) strstr( $html, 'id="developer-hooks-docs"' );
		preg_match_all( '/<code>(zerospam_[a-z0-9_]+)<\/code>/', $section, $matches );
		$documented = array_unique( $matches[1] );
		$this->assertNotEmpty( $documented );

		$source = '';
		foreach ( array( 'core', 'includes', 'modules' ) as $dir ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ZEROSPAM_PATH . $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				if ( 'php' === $file->getExtension() && false === strpos( $file->getPathname(), '/templates/docs/' ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own source files.
					$source .= (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		foreach ( $documented as $hook ) {
			if ( isset( \ZeroSpam\Core\Deprecated_Hooks::HOOKS[ $hook ] ) ) {
				continue; // Deprecated in 6.0.0: documented as such, never fired.
			}

			$this->assertMatchesRegularExpression(
				'/(apply_filters|do_action)\(\s*\'' . preg_quote( $hook, '/' ) . '\'/',
				$source,
				"{$hook} is documented but never called."
			);
		}
	}
}
