<?php
/**
 * Tests for .htaccess writes on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * Every site of a network shares one .htaccess, at the network's root. A
 * subsite in .htaccess mode wrote it after every block change, with only
 * its own addresses, replacing the main site's (AGENTS.md section 8). Only
 * the main site writes it now; every site still blocks in PHP.
 *
 * @group ms-required
 *
 * @covers \ZeroSpam\Core\Utilities::refresh_htaccess
 */
class Test_Htaccess_Subsite extends TestCase {

	/**
	 * A subsite.
	 *
	 * @var int
	 */
	private static int $subsite = 0;

	/**
	 * Creates the subsite.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$subsite = self::create_site_with_tables( $factory );
	}

	/**
	 * The file the test writes instead of the real .htaccess.
	 */
	private function file(): string {
		$file = get_temp_dir() . 'zerospam-htaccess-test';
		file_put_contents( $file, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in the temp directory.
		add_filter( 'zerospam_htaccess_file', static fn(): string => $file );

		return $file;
	}

	/**
	 * A subsite in .htaccess mode leaves the shared file alone.
	 */
	public function test_subsite_does_not_write_the_network_htaccess(): void {
		$file   = $this->file();
		$before = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the test fixture.

		switch_to_blog( self::$subsite );
		$this->set_setting( 'block_method', 'htaccess_modern' );
		$this->block( '198.51.100.40' );
		$written = Utilities::refresh_htaccess();
		restore_current_blog();

		$this->assertFalse( $written );
		$this->assertSame( $before, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the test fixture.
	}

	/**
	 * The main site still writes it.
	 */
	public function test_main_site_writes_the_htaccess(): void {
		$file = $this->file();
		$this->set_setting( 'block_method', 'htaccess_modern' );
		$this->block( '198.51.100.41' );

		$this->assertTrue( Utilities::refresh_htaccess() );
		$this->assertStringContainsString( '198.51.100.41', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the test fixture.
	}
}
