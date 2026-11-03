<?php
/**
 * Tests for the cap on the sites the network statistics read.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Tests\TestCase;

/**
 * Network_Report::log_tables() reads at most $max sites (lowest IDs first)
 * and says when the network has more (`capped`), so a large network is
 * never read in one go and the screen can say the numbers are partial.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Report::log_tables
 */
class Test_Network_Report_Capped extends TestCase {

	/**
	 * Two more sites with their tables.
	 *
	 * @var int[]
	 */
	private static array $sites = array();

	/**
	 * Creates the sites.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$sites = array( self::create_site_with_tables( $factory ), self::create_site_with_tables( $factory ) );
	}

	/**
	 * Below the cap every site is read and nothing is capped.
	 */
	public function test_not_capped_below_the_limit(): void {
		$all = Network_Report::log_tables();

		$this->assertFalse( $all['capped'] );
		$this->assertGreaterThanOrEqual( 3, count( $all['tables'] ) );
		foreach ( self::$sites as $site ) {
			$this->assertArrayHasKey( $site, $all['tables'] );
		}
	}

	/**
	 * Above the cap only the first sites are read, and it says so.
	 */
	public function test_capped_above_the_limit(): void {
		$count  = Network_Report::active_sites_count();
		$capped = Network_Report::log_tables( 1 );

		$this->assertGreaterThan( 1, $count );
		$this->assertTrue( $capped['capped'] );
		$this->assertCount( 1, $capped['tables'] );
		$this->assertSame( array( min( array_keys( Network_Report::log_tables()['tables'] ) ) ), array_keys( $capped['tables'] ), 'The lowest ID first.' );

		$this->assertFalse( Network_Report::log_tables( $count )['capped'], 'Exactly at the limit is not capped.' );
	}
}
