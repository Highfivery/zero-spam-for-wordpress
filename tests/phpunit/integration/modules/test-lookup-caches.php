<?php
/**
 * Tests for the caches and requests of the IP lookup modules.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Modules\ipbase\ipbase;
use ZeroSpam\Modules\IPinfoModule;
use ZeroSpam\Modules\ipstack;
use ZeroSpam\Modules\ProjectHoneypot;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * Project Honeypot, ipbase, IPinfo and ipstack read their object cache from
 * the default group but wrote it to `zerospam`, so a repeated lookup in the
 * same request went back to the service (AGENTS.md section 8). ipbase also
 * wrote under an undefined key and ignored its timeout and cache settings,
 * and ipstack sent the key and the visitor's address over plain HTTP.
 */
class Test_Lookup_Caches extends TestCase {

	const IP = '203.0.113.7';

	/**
	 * A repeated Project Honeypot lookup is served from the cache.
	 *
	 * @covers \ZeroSpam\Modules\ProjectHoneypot::query
	 */
	public function test_project_honeypot_is_cached(): void {
		$this->set_setting( 'project_honeypot_access_key', 'abcdefghijkl', 'project_honeypot' );
		Stubs::$dns_records['abcdefghijkl.7.113.0.203.dnsbl.httpbl.org'] = array( array( 'ip' => '127.1.40.4' ) );

		$module = new ProjectHoneypot();
		$first  = $module->query( self::IP );
		$second = $module->query( self::IP );

		$this->assertSame( '40', $first['threat_score'] );
		$this->assertSame( $first, $second );
		$this->assertCount( 1, Stubs::$dns_lookups, 'The second lookup asked DNS again.' );
	}

	/**
	 * A repeated ipbase lookup is served from the cache.
	 *
	 * @covers \ZeroSpam\Modules\ipbase\ipbase::query_ip_address
	 */
	public function test_ipbase_is_cached(): void {
		$this->set_setting( 'ipbase_api_key', 'key', 'ipbase' );
		$this->mock_http( '#^https://api\.ipbase\.com/#', array( 'ip' => self::IP, 'country_code' => 'NL' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short answer.

		$first = ipbase::query_ip_address( self::IP );
		ipbase::query_ip_address( self::IP );

		$this->assertSame( 'NL', $first['country_code'] );
		$this->assertSame( 1, HttpMock::hits( '#^https://api\.ipbase\.com/#' ), 'The second lookup went back to ipbase.' );
	}

	/**
	 * The ipbase lookup never reads IPinfo's cached answer (they shared a cache key).
	 *
	 * @covers \ZeroSpam\Modules\ipbase\ipbase::query_ip_address
	 */
	public function test_ipbase_does_not_share_ipinfo_cache(): void {
		$this->set_setting( 'ipbase_api_key', 'key', 'ipbase' );
		$this->set_setting( 'ipinfo_access_token', 'token', 'ipinfo' );
		$this->mock_http( '#^https://api\.ipinfo\.io/#', array( 'ip' => self::IP, 'country_code' => 'DE' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short answer.
		$this->mock_http( '#^https://api\.ipbase\.com/#', array( 'ip' => self::IP, 'country_code' => 'NL' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short answer.

		IPinfoModule::get_geolocation( self::IP );
		$answer = ipbase::query_ip_address( self::IP );

		$this->assertSame( 'NL', $answer['country_code'] );
	}

	/**
	 * The ipbase lookup sends its timeout setting.
	 *
	 * @covers \ZeroSpam\Modules\ipbase\ipbase::query_ip_address
	 */
	public function test_ipbase_uses_its_timeout(): void {
		$this->set_setting( 'ipbase_api_key', 'key', 'ipbase' );
		$this->set_setting( 'ipbase_api_timeout', 2, 'ipbase' );
		$this->mock_http( '#^https://api\.ipbase\.com/#', array( 'ip' => self::IP ) );

		$timeout = null;
		$watch   = static function ( $preempt, $args, $url ) use ( &$timeout ) {
			if ( str_contains( (string) $url, 'ipbase' ) ) {
				$timeout = $args['timeout'] ?? null;
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $watch, 1, 3 );
		ipbase::query_ip_address( self::IP );
		remove_filter( 'pre_http_request', $watch, 1 );

		$this->assertEquals( 2, $timeout );
	}

	/**
	 * An IPinfo answer is kept in the object cache for the request.
	 *
	 * @covers \ZeroSpam\Modules\IPinfoModule::get_geolocation
	 */
	public function test_ipinfo_object_cache_is_read(): void {
		$this->set_setting( 'ipinfo_access_token', 'token', 'ipinfo' );
		$this->mock_http( '#^https://api\.ipinfo\.io/#', array( 'ip' => self::IP, 'country_code' => 'DE' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short answer.

		IPinfoModule::get_geolocation( self::IP );
		delete_transient( 'zerospam_ipinfo_' . md5( self::IP ) );
		IPinfoModule::get_geolocation( self::IP );

		$this->assertSame( 1, HttpMock::hits( '#^https://api\.ipinfo\.io/#' ), 'The object cache was not read.' );
	}

	/**
	 * The ipstack lookup is made over HTTPS, once per address.
	 *
	 * @covers \ZeroSpam\Modules\ipstack::get_geolocation
	 */
	public function test_ipstack_uses_https_and_is_cached(): void {
		$this->set_setting( 'ipstack_api', 'key', 'ipstack' );
		$this->mock_http( '#^https://api\.ipstack\.com/#', array( 'ip' => self::IP, 'country_code' => 'FR' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short answer.

		$first = ipstack::get_geolocation( self::IP );
		ipstack::get_geolocation( self::IP );

		$this->assertSame( 'FR', $first['country_code'] );
		$this->assertSame( 1, HttpMock::hits( '#^https://api\.ipstack\.com/#' ) );
		foreach ( HttpMock::requests() as $request ) {
			$this->assertStringStartsNotWith( 'http://', $request['url'], 'A request went out over plain HTTP.' );
		}
	}

	/**
	 * An ipstack refusal of the key over HTTPS gives no location, and no retry over HTTP.
	 *
	 * @covers \ZeroSpam\Modules\ipstack::get_geolocation
	 */
	public function test_ipstack_https_refusal_gives_no_location(): void {
		$this->set_setting( 'ipstack_api', 'key', 'ipstack' );
		$this->mock_http(
			'#^https://api\.ipstack\.com/#',
			array(
				'success' => false,
				'error'   => array(
					'code' => 105,
					'type' => 'https_access_restricted',
				),
			)
		);

		$this->assertFalse( ipstack::get_geolocation( self::IP ) );
		$this->assertCount( 1, HttpMock::requests() );
	}

	/**
	 * Two IPv6 addresses that differ only in their colons never share a cache entry.
	 *
	 * @covers \ZeroSpam\Modules\ipstack::get_geolocation
	 */
	public function test_ipv6_addresses_do_not_share_a_cache_entry(): void {
		$this->set_setting( 'ipstack_api', 'key', 'ipstack' );
		$this->mock_http( '#^https://api\.ipstack\.com/2001:db8::1\?#', array( 'country_code' => 'FR' ) );
		$this->mock_http( '#^https://api\.ipstack\.com/2001:db8:1::\?#', array( 'country_code' => 'JP' ) );

		$this->assertSame( 'FR', ipstack::get_geolocation( '2001:db8::1' )['country_code'] );
		$this->assertSame( 'JP', ipstack::get_geolocation( '2001:db8:1::' )['country_code'] );
	}

	/**
	 * Something that is not an IP address is never sent to a service.
	 *
	 * @covers \ZeroSpam\Modules\ipstack::get_geolocation
	 * @covers \ZeroSpam\Modules\ipbase\ipbase::query_ip_address
	 * @covers \ZeroSpam\Modules\IPinfoModule::get_geolocation
	 */
	public function test_a_value_that_is_not_an_ip_is_not_sent(): void {
		$this->set_setting( 'ipstack_api', 'key', 'ipstack' );
		$this->set_setting( 'ipbase_api_key', 'key', 'ipbase' );
		$this->set_setting( 'ipinfo_access_token', 'token', 'ipinfo' );

		$this->assertFalse( ipstack::get_geolocation( '203.0.113.7/../x' ) );
		$this->assertFalse( ipbase::query_ip_address( '203.0.113.7/../x' ) );
		$this->assertFalse( IPinfoModule::get_geolocation( '203.0.113.7/../x' ) );
		$this->assertSame( array(), HttpMock::requests() );
	}

	/**
	 * An ipbase error status is not cached as an answer.
	 *
	 * @covers \ZeroSpam\Modules\ipbase\ipbase::query_ip_address
	 */
	public function test_ipbase_error_status_is_not_cached(): void {
		$this->set_setting( 'ipbase_api_key', 'key', 'ipbase' );
		$this->mock_http( '#^https://api\.ipbase\.com/#', array( 'error' => 'quota' ), 429 );

		$this->assertFalse( ipbase::query_ip_address( self::IP ) );
		$this->assertFalse( ipbase::query_ip_address( self::IP ) );
		$this->assertSame( 2, HttpMock::hits( '#^https://api\.ipbase\.com/#' ) );
	}

	/**
	 * An address http:BL does not list is remembered, and the access key is never logged.
	 *
	 * @covers \ZeroSpam\Modules\ProjectHoneypot::query
	 */
	public function test_project_honeypot_unlisted_address(): void {
		$this->set_setting( 'project_honeypot_access_key', 'secretkeyabc', 'project_honeypot' );
		$log    = wp_upload_dir()['basedir'] . '/zerospam.log';
		$before = file_exists( $log ) ? (string) file_get_contents( $log ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's local log.

		$module = new ProjectHoneypot();
		$this->assertFalse( $module->query( self::IP ) );
		$this->assertFalse( $module->query( self::IP ) );
		$this->assertFalse( $module->query( '2001:db8::1' ), 'IPv6 is not asked.' );

		$this->assertCount( 1, Stubs::$dns_lookups );
		$after = file_exists( $log ) ? (string) file_get_contents( $log ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- As above.
		$this->assertStringNotContainsString( 'secretkeyabc', substr( $after, strlen( $before ) ) );
	}

	/**
	 * A lookup no provider answered is kept for minutes, not a week.
	 *
	 * @covers \ZeroSpam\Core\Utilities::geolocation
	 */
	public function test_geolocation_without_an_answer_is_cached_briefly(): void {
		$this->set_setting( 'ipstack_api', 'key', 'ipstack' );
		$this->mock_http( '#^https://api\.ipstack\.com/#', array( 'error' => array( 'type' => 'https_access_restricted' ) ) );

		Utilities::geolocation( self::IP );
		$expires = (int) get_option( '_transient_timeout_zerospam_geo_' . md5( self::IP ) );

		$this->assertLessThanOrEqual( time() + 15 * MINUTE_IN_SECONDS, $expires );
		$this->assertGreaterThan( time(), $expires );
	}

	/**
	 * A lookup a provider answered is kept for a week.
	 *
	 * @covers \ZeroSpam\Core\Utilities::geolocation
	 */
	public function test_geolocation_with_an_answer_is_cached_for_a_week(): void {
		$this->set_setting( 'ipstack_api', 'key', 'ipstack' );
		$this->mock_http( '#^https://api\.ipstack\.com/#', array( 'country_code' => 'FR' ) );

		$this->assertSame( 'FR', Utilities::geolocation( self::IP )['country_code'] );
		$expires = (int) get_option( '_transient_timeout_zerospam_geo_' . md5( self::IP ) );

		$this->assertGreaterThan( time() + DAY_IN_SECONDS, $expires );
	}
}
