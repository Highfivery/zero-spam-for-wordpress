<?php
/**
 * Tests for the hooks of the 5.x admin screens, deprecated in 6.0.0.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Deprecated_Hooks;
use ZeroSpam\Tests\TestCase;

/**
 * The 5.x screens that fired these hooks are gone. A plugin that still
 * hooks one gets the deprecation notice do_action_deprecated() gives; with
 * nothing hooked nothing is raised.
 *
 * @covers \ZeroSpam\Core\Deprecated_Hooks
 */
class Test_Deprecated_Hooks extends TestCase {

	/**
	 * The legacy-only hooks (build plan C4).
	 */
	public function test_lists_the_legacy_only_hooks(): void {
		$this->assertEqualsCanonicalizing(
			array(
				'zerospam_settings_page_top',
				'zerospam_setting_field_before',
				'zerospam_setting_field_after',
				'zerospam_setting_field_disabled',
				'zerospam_google_map',
				'zerospam_display_promo_notice',
				'zerospam_promo_notice_displayed',
				'zerospam_promo_notice_dismissed',
				'zerospam_promo_notice_clicked',
			),
			array_keys( Deprecated_Hooks::HOOKS )
		);
	}

	/**
	 * Nothing hooked, nothing raised.
	 */
	public function test_nothing_hooked(): void {
		foreach ( array_keys( Deprecated_Hooks::HOOKS ) as $hook ) {
			remove_all_filters( $hook );
		}

		$this->assertSame( array(), Deprecated_Hooks::check() );
	}

	/**
	 * Each hooked one raises the deprecation notice.
	 */
	public function test_each_hooked_one_is_deprecated(): void {
		foreach ( array_keys( Deprecated_Hooks::HOOKS ) as $hook ) {
			add_filter( $hook, '__return_false' );
			$this->setExpectedDeprecated( $hook );
		}

		$this->assertEqualsCanonicalizing( array_keys( Deprecated_Hooks::HOOKS ), Deprecated_Hooks::check() );
	}

	/**
	 * It checks once the plugins are loaded, in the admin.
	 */
	public function test_checks_on_admin_init(): void {
		new Deprecated_Hooks();

		$this->assertNotFalse( has_action( 'admin_init', array( Deprecated_Hooks::class, 'warn' ) ) );
	}

	/**
	 * Nothing in the plugin fires them any more.
	 */
	public function test_nothing_fires_them(): void {
		$source = '';
		foreach ( array( 'core', 'includes', 'modules' ) as $dir ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ZEROSPAM_PATH . $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				if ( 'php' === $file->getExtension() ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own source files.
					$source .= (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		foreach ( array_keys( Deprecated_Hooks::HOOKS ) as $hook ) {
			$this->assertDoesNotMatchRegularExpression( '/(apply_filters|do_action)(_deprecated)?\(\s*\'' . preg_quote( $hook, '/' ) . '\'/', $source, $hook );
		}
	}
}
