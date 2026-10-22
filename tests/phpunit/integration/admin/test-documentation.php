<?php
/**
 * Tests for the in-admin documentation page.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * The Documentation tab lists the developer hooks added in 5.7.14.
 *
 * @coversNothing
 */
class Test_Documentation extends TestCase {

	/**
	 * Renders the documentation template.
	 *
	 * @return string Page HTML.
	 */
	private function render(): string {
		$this->as_role( 'administrator' );

		ob_start();
		require ZEROSPAM_PATH . 'includes/templates/admin-documentation.php';

		return (string) ob_get_clean();
	}

	/**
	 * The Developer Hooks section is registered, linked from the table of
	 * contents and rendered.
	 */
	public function test_developer_hooks_section_is_rendered(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'href="#section-developer-hooks"', $html );
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
			$this->assertMatchesRegularExpression(
				'/(apply_filters|do_action)\(\s*\'' . preg_quote( $hook, '/' ) . '\'/',
				$source,
				"{$hook} is documented but never called."
			);
		}
	}
}
