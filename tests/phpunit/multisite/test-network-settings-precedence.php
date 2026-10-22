<?php
/**
 * Tests for network settings precedence.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Locked network value > site override > network default > plugin default.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Settings::resolve_all_settings
 */
class Test_Network_Settings_Precedence extends TestCase {

	/**
	 * Two sites besides the main site.
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
		self::$sites = array(
			self::create_site_with_tables( $factory ),
			self::create_site_with_tables( $factory ),
		);
	}

	/**
	 * Reads max_logs on a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return mixed
	 */
	private function max_logs_on( int $blog_id ) {
		switch_to_blog( $blog_id );
		self::reset_static_caches();
		$value = Settings::get_settings( 'max_logs' );
		restore_current_blog();

		return $value;
	}

	/**
	 * Network value, whether it is locked, site override, expected value.
	 *
	 * @return array<string, array{0: int|null, 1: bool, 2: int|null, 3: int}>
	 */
	public static function data_precedence(): array {
		return array(
			'plugin default'                  => array( null, false, null, 10000 ),
			'network default'                 => array( 500, false, null, 500 ),
			'site override beats network'     => array( 500, false, 250, 250 ),
			'locked network beats override'   => array( 500, true, 250, 500 ),
			'site override without network'   => array( null, false, 250, 250 ),
			'locked network without override' => array( 500, true, null, 500 ),
		);
	}

	/**
	 * The value a site sees.
	 *
	 * @dataProvider data_precedence
	 *
	 * @param int|null $network  Network value, or null for none.
	 * @param bool     $locked   Whether the network value is locked.
	 * @param int|null $override Site override, or null for none.
	 * @param int      $expected Value the site sees.
	 */
	public function test_precedence( ?int $network, bool $locked, ?int $override, int $expected ): void {
		$site = self::$sites[0];

		if ( null !== $network ) {
			$this->set_network_setting( 'max_logs', $network, $locked );
		}
		if ( null !== $override ) {
			switch_to_blog( $site );
			$this->set_setting( 'max_logs', $override, 'settings' );
			restore_current_blog();
		}

		$this->assertSame( $expected, $this->max_logs_on( $site ) );
	}

	/**
	 * One site's override does not leak to another site.
	 */
	public function test_site_override_does_not_leak(): void {
		list( $site, $other ) = self::$sites;

		$this->set_network_setting( 'max_logs', 500 );
		switch_to_blog( $site );
		$this->set_setting( 'max_logs', 250, 'settings' );
		restore_current_blog();

		$this->assertSame( 250, $this->max_logs_on( $site ) );
		$this->assertSame( 500, $this->max_logs_on( $other ) );
		$this->assertSame( 500, $this->max_logs_on( get_main_site_id() ) );
	}
}
