<?php
/**
 * Tests for Utilities::asset_version().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * The plugin's own assets are versioned by release, or by file time on
 * development sites (SCRIPT_DEBUG), so edited CSS and JS reload at once.
 *
 * @covers \ZeroSpam\Core\Utilities::asset_version
 */
class Test_Utilities_Asset_Version extends TestCase {

	/**
	 * Without SCRIPT_DEBUG the plugin version is used.
	 */
	public function test_uses_plugin_version_without_script_debug(): void {
		self::set_static( Utilities::class, 'script_debug', false );

		$this->assertSame( ZEROSPAM_VERSION, Utilities::asset_version( 'assets/css/admin.css' ) );
	}

	/**
	 * The test environment does not define SCRIPT_DEBUG, so the constant
	 * read (not only the cached flag) gives the plugin version.
	 */
	public function test_reads_script_debug_constant(): void {
		if ( defined( 'SCRIPT_DEBUG' ) && true === constant( 'SCRIPT_DEBUG' ) ) {
			$this->markTestSkipped( 'SCRIPT_DEBUG is on in this environment.' );
		}

		$this->assertSame( ZEROSPAM_VERSION, Utilities::asset_version( 'assets/js/admin.js' ) );
	}

	/**
	 * With SCRIPT_DEBUG the file's modification time is used.
	 */
	public function test_uses_file_time_with_script_debug(): void {
		self::set_static( Utilities::class, 'script_debug', true );

		$this->assertSame(
			(string) filemtime( ZEROSPAM_PATH . 'assets/css/admin.css' ),
			Utilities::asset_version( 'assets/css/admin.css' )
		);
		$this->assertSame(
			(string) filemtime( ZEROSPAM_PATH . 'modules/davidwalsh/assets/js/davidwalsh.js' ),
			Utilities::asset_version( '/modules/davidwalsh/assets/js/davidwalsh.js' ),
			'A leading slash is ignored.'
		);
	}

	/**
	 * A missing file falls back to the plugin version, even with SCRIPT_DEBUG.
	 */
	public function test_missing_file_falls_back_to_plugin_version(): void {
		self::set_static( Utilities::class, 'script_debug', true );

		$this->assertSame( ZEROSPAM_VERSION, Utilities::asset_version( 'assets/css/does-not-exist.css' ) );
		$this->assertSame( ZEROSPAM_VERSION, Utilities::asset_version( 'assets/css' ), 'A directory is not a file.' );
	}

	/**
	 * Every enqueue of the plugin's own CSS and JS uses asset_version(), and
	 * every path it is given exists.
	 */
	public function test_own_assets_are_enqueued_with_asset_version(): void {
		$files = array(
			'core/admin/class-admin.php',
			'includes/admin/class-networkstatspage.php',
			'includes/admin/class-networksettingspage.php',
			'includes/admin/class-siteadminoverrides.php',
			'modules/davidwalsh/class-davidwalsh.php',
		);

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own source files.
			$source = (string) file_get_contents( ZEROSPAM_PATH . $file );

			$this->assertDoesNotMatchRegularExpression( '/^\s*ZEROSPAM_VERSION,?\s*$/m', $source, "{$file} still versions an asset with ZEROSPAM_VERSION." );
			$this->assertGreaterThan( 0, (int) preg_match_all( "/asset_version\\( '([^']+)' \\)/", $source, $matches ), "{$file} has no asset_version() call." );

			foreach ( $matches[1] as $path ) {
				$this->assertFileExists( ZEROSPAM_PATH . $path, "{$file} versions a file that does not exist." );
			}
		}
	}
}
