<?php
/**
 * Checks the test environment itself.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\Database;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\TestCase;

/**
 * The harness loads the requested WordPress, real plugin tables, and no network.
 */
class Test_Environment extends TestCase {

	/**
	 * The WordPress version bin/test.sh asked for is the one loaded.
	 */
	public function test_loads_the_requested_wordpress_version(): void {
		global $wp_version;

		$this->assertNotFalse( getenv( 'ZEROSPAM_WP_VERSION' ) );
		$this->assertSame( getenv( 'ZEROSPAM_WP_VERSION' ), $wp_version );
	}

	/**
	 * The plugin loads from its directory name, as on a real site.
	 */
	public function test_plugin_basename_matches_production(): void {
		$this->assertSame( 'zero-spam/wordpress-zero-spam.php', ZEROSPAM_PLUGIN_BASE );
	}

	/**
	 * The plugin tables exist as real tables, visible to SHOW TABLES.
	 */
	public function test_plugin_tables_are_real_tables(): void {
		global $wpdb;

		foreach ( Database::plugin_tables() as $table ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SHOW FULL TABLES LIKE %s', $table ), ARRAY_N );

			$this->assertIsArray( $row, "{$table} does not exist." );
			$this->assertSame( 'BASE TABLE', $row[1], "{$table} is not a real table." );
		}
	}

	/**
	 * Outbound HTTP is blocked and recorded.
	 */
	public function test_outbound_http_is_blocked_and_recorded(): void {
		$response = wp_remote_get( 'https://www.zerospam.org/' );

		$this->assertWPError( $response );
		$this->assertSame( array( 'GET https://www.zerospam.org/' ), HttpMock::take_unmocked() );
	}

	/**
	 * A mocked URL gets its canned response.
	 */
	public function test_mocked_http_returns_the_canned_response(): void {
		$this->mock_http( '#^https://api\.example\.com/#', array( 'ok' => true ), 201 );

		$response = wp_remote_get( 'https://api.example.com/ping' );

		$this->assertSame( 201, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( '{"ok":true}', wp_remote_retrieve_body( $response ) );
		$this->assertSame( 1, HttpMock::hits( '#^https://api\.example\.com/#' ) );
	}

	/**
	 * The plugin talks to a non-resolvable service URL.
	 */
	public function test_service_url_is_not_production(): void {
		$this->assertSame( 'https://zerospam.invalid/', ZEROSPAM_URL );
	}

	/**
	 * The install type is the one the PHPUnit configuration declares.
	 */
	public function test_multisite_matches_the_configuration(): void {
		$this->assertTrue( defined( 'ZEROSPAM_TESTS_EXPECT_MULTISITE' ) );
		$this->assertSame( ZEROSPAM_TESTS_EXPECT_MULTISITE, is_multisite() );
	}

	/**
	 * WordPress sees the plugin as zero-spam/, whatever the checkout is called.
	 */
	public function test_plugin_loads_from_the_test_plugins_directory(): void {
		$this->assertSame( WP_PLUGIN_DIR . '/zero-spam/', plugin_dir_path( WP_PLUGIN_DIR . '/zero-spam/wordpress-zero-spam.php' ) );
		$this->assertSame( realpath( dirname( __DIR__, 3 ) ), realpath( WP_PLUGIN_DIR . '/zero-spam' ) );
		$this->assertSame( 'zero-spam/wordpress-zero-spam.php', plugin_basename( ZEROSPAM ) );
	}
}
