<?php
/**
 * Tests for what the API usage table keeps.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\API_Usage_Tracker;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Modules\Zero_Spam;
use ZeroSpam\Tests\TestCase;

/**
 * The usage table records that a call was made, not what it carried: the
 * request URLs hold the license key and, for lookups and reports, the visitor's
 * address and what they typed.
 *
 * @covers \ZeroSpam\Includes\API_Usage_Tracker::track_api_call
 * @covers \ZeroSpam\Includes\API_Usage_Tracker::track_cache_hit
 * @covers \ZeroSpam\Includes\Migrations::usage_endpoint_privacy
 */
class Test_API_Usage_Privacy extends TestCase {

	private const KEY = 'secret-license-key-1234';

	/**
	 * The usage table's name.
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->base_prefix . DB::$tables['api_usage'];
	}

	/**
	 * Inserts rows as earlier versions stored them.
	 *
	 * @param int $count Rows.
	 */
	private function old_rows( int $count ): void {
		global $wpdb;

		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$this->table(),
				array(
					'site_id'       => get_current_blog_id(),
					'event_type'    => 'api_call',
					'endpoint'      => 'https://www.zerospam.org/wp-json/v3/query?license_key=' . self::KEY . '&ip=203.0.113.5',
					'date_recorded' => current_time( 'mysql' ),
					'hour_bucket'   => gmdate( 'Y-m-d H:00:00' ),
					'day_bucket'    => gmdate( 'Y-m-d' ),
				)
			);
		}
	}

	/**
	 * Every endpoint in the table.
	 *
	 * @return string[]
	 */
	private function endpoints(): array {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT endpoint FROM %i', $this->table() ) );
	}

	/**
	 * Empties the table and turns monitoring on.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->table() ) );
		delete_site_option( Migrations::NETWORK_COMPLETED_OPTION );
		$this->set_setting( 'api_monitoring', 'enabled' );
	}

	/**
	 * A real lookup leaves no key, address or email behind.
	 */
	public function test_a_lookup_stores_only_the_address_of_the_endpoint(): void {
		$this->set_setting( 'zerospam', 'enabled' );
		$this->set_setting( 'zerospam_license', self::KEY );
		$this->mock_http(
			'#/wp-json/v3/query#',
			array(
				'status'        => 200,
				'body_response' => array( 'ip_addresses' => array() ),
			)
		);

		Zero_Spam::query(
			array(
				'ip'    => '203.0.113.5',
				'email' => 'jane@example.com',
			)
		);

		$this->assertSame( array( ZEROSPAM_URL . 'wp-json/v3/query' ), $this->endpoints() );
	}

	/**
	 * The same for a cache hit.
	 */
	public function test_a_cache_hit_stores_only_the_address_of_the_endpoint(): void {
		API_Usage_Tracker::track_cache_hit( 'https://www.zerospam.org/wp-json/v3/query?license_key=' . self::KEY );

		$this->assertSame( array( 'https://www.zerospam.org/wp-json/v3/query' ), $this->endpoints() );
	}

	/**
	 * Rows stored before are cleaned, a batch at a time; the counts stay.
	 */
	public function test_old_rows_are_cleaned_in_batches(): void {
		global $wpdb;

		$this->old_rows( 3 );
		$migrations = new Migrations();

		$this->set_static( Migrations::class, 'usage_batch', 2 );
		$this->assertFalse( $migrations->usage_endpoint_privacy(), 'More rows are left: it runs again.' );
		$this->assertTrue( $migrations->usage_endpoint_privacy() );

		$this->assertSame( array( 'https://www.zerospam.org/wp-json/v3/query' ), $this->endpoints() );
		$this->assertSame( '3', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table() ) ) );
	}
}
