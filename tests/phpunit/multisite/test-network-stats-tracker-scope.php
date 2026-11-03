<?php
/**
 * Tests that the network statistics of WP-CLI read only the current network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Stats_Tracker;
use ZeroSpam\Tests\TestCase;

/**
 * Network_Stats_Tracker (`wp zerospam network-stats`, `site-breakdown`,
 * `attackers`) reads the daily totals of the current network's sites only;
 * the shared `wpzerospam_stats_daily` table holds every network's rows. The
 * cache is per network too.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Stats_Tracker::get_network_stats
 */
class Test_Network_Stats_Tracker_Scope extends TestCase {

	/**
	 * The other network and its site.
	 *
	 * @var array{0: int, 1: int}
	 */
	private static array $other = array( 0, 0 );

	/**
	 * Creates the other network.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$other = self::create_network_with_site( $factory );
	}

	/**
	 * Daily totals: 5 on this network's main site, 999 on the other network's site.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$table = $wpdb->base_prefix . DB::$tables['stats_daily'];
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );

		foreach ( array(
			get_main_site_id() => 5,
			self::$other[1]    => 999,
		) as $site_id => $count ) {
			$wpdb->insert(
				$table,
				array(
					'site_id'            => $site_id,
					'stat_date'          => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ),
					'total_spam_blocked' => $count,
					'unique_ips'         => 1,
					'spam_by_type'       => wp_json_encode( array( array( 'log_type' => 'comment', 'count' => $count ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- One row.
					'top_countries'      => wp_json_encode( array() ),
					'top_ips'            => wp_json_encode( array() ),
				)
			);
		}

		foreach ( array( get_current_network_id(), self::$other[0] ) as $network ) {
			delete_transient( 'zerospam_network_stats_' . $network . '_week' );
		}
	}

	/**
	 * Each network counts its own sites only, even with a warm cache from the other.
	 */
	public function test_totals_are_per_network(): void {
		$this->assertSame( 5, Network_Stats_Tracker::get_network_stats( 'week' )['total_spam'] );

		$other = $this->on_network(
			self::$other[0],
			static function () {
				return Network_Stats_Tracker::get_network_stats( 'week' )['total_spam'];
			}
		);
		$this->assertSame( 999, $other );

		$this->assertSame( 5, Network_Stats_Tracker::get_network_stats( 'week' )['total_spam'] );
	}
}
