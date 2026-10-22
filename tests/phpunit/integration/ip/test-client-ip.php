<?php
/**
 * Tests for the visitor IP behind CDNs and proxies.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\User;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * User::get_ip() only reads forwarded headers when the connection comes from
 * a trusted proxy (Cloudflare, the Trusted Proxy IPs setting or the
 * zerospam_trusted_proxies filter), and then takes the address the proxy
 * forwarded, never one the visitor could have written.
 *
 * @covers \ZeroSpam\Core\User
 * @covers \ZeroSpam\Core\Utilities::get_proxy_country_code
 */
class Test_Client_IP extends TestCase {

	/**
	 * A Cloudflare edge address (173.245.48.0/20).
	 */
	const CLOUDFLARE_EDGE = '173.245.48.5';

	/**
	 * The proxy configured in most tests, inside the trusted 192.0.2.0/24.
	 */
	const PROXY = '192.0.2.10';

	/**
	 * The real visitor behind the proxy.
	 */
	const VISITOR = '203.0.113.9';

	/**
	 * An IPv6 visitor. It must be a global address: 2001:db8::/32 is reserved
	 * for documentation, and recent PHP versions treat it as reserved.
	 */
	const VISITOR_V6 = '2a01:4f8:1:2::9';

	/**
	 * Simulates a request from an address with request headers.
	 *
	 * @param string                $remote  REMOTE_ADDR.
	 * @param array<string, string> $headers Header name => value.
	 */
	private function request_from( string $remote, array $headers = array() ): void {
		$this->set_request( '/', $remote );

		foreach ( array( 'CF_CONNECTING_IP', 'CF_IPCOUNTRY', 'CLIENT_IP', 'TRUE_CLIENT_IP', 'X_FORWARDED_COUNTRY', 'X_FORWARDED_FOR', 'X_REAL_IP' ) as $header ) {
			unset( $_SERVER[ 'HTTP_' . $header ] );
		}

		foreach ( $headers as $name => $value ) {
			$_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) ) ] = $value;
		}
	}

	/**
	 * Trusts 192.0.2.0/24 through the setting.
	 */
	private function trust_test_proxy_range(): void {
		$this->set_setting( 'trusted_proxies', "192.0.2.0/24\n", 'settings' );
	}

	/**
	 * A visitor that is not a trusted proxy cannot choose its IP with headers.
	 */
	public function test_untrusted_remote_ignores_spoofed_forwarded_headers(): void {
		$this->request_from(
			'198.51.100.7',
			array(
				'X-Forwarded-For'  => '203.0.113.66',
				'X-Real-IP'        => '203.0.113.67',
				'True-Client-IP'   => '203.0.113.68',
				'CF-Connecting-IP' => '203.0.113.69',
				'Client-IP'        => '203.0.113.70',
			)
		);

		$this->assertSame( '198.51.100.7', User::get_ip() );
	}

	/**
	 * Behind a proxy in a trusted range, the rightmost address that is not a
	 * trusted proxy (or an internal hop) is the visitor.
	 */
	public function test_trusted_cidr_uses_rightmost_untrusted_forwarded_address(): void {
		$this->trust_test_proxy_range();
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => self::VISITOR . ', 10.0.0.1, 192.0.2.20' ) );

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * The leftmost X-Forwarded-For entry is whatever the visitor sent, so it is
	 * not used when the proxy appended the real address after it.
	 */
	public function test_leftmost_forwarded_address_is_not_trusted(): void {
		$this->trust_test_proxy_range();
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => '198.51.100.66, ' . self::VISITOR ) );

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * Cloudflare is recognised without any configuration.
	 */
	public function test_cloudflare_edge_uses_cf_connecting_ip(): void {
		$this->request_from(
			self::CLOUDFLARE_EDGE,
			array(
				'CF-Connecting-IP' => self::VISITOR,
				'X-Forwarded-For'  => '198.51.100.66',
			)
		);

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * Cloudflare's IPv6 edges are recognised too.
	 */
	public function test_cloudflare_ipv6_edge_uses_cf_connecting_ip(): void {
		$this->request_from( '2606:4700::6810:84e5', array( 'CF-Connecting-IP' => self::VISITOR_V6 ) );

		$this->assertSame( self::VISITOR_V6, User::get_ip() );
	}

	/**
	 * CF-Connecting-IP is only Cloudflare's header: from another trusted proxy
	 * it is ignored and X-Forwarded-For is used.
	 */
	public function test_cf_connecting_ip_is_ignored_from_other_proxies(): void {
		$this->trust_test_proxy_range();
		$this->request_from(
			self::PROXY,
			array(
				'CF-Connecting-IP' => '198.51.100.66',
				'X-Forwarded-For'  => self::VISITOR,
			)
		);

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * Behind Cloudflare and a trusted load balancer, Cloudflare's edge in
	 * X-Forwarded-For is skipped as a trusted hop.
	 */
	public function test_cloudflare_hop_in_forwarded_chain_is_skipped(): void {
		$this->trust_test_proxy_range();
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => self::VISITOR . ', ' . self::CLOUDFLARE_EDGE ) );

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * X-Real-IP and True-Client-IP are used when X-Forwarded-For is missing.
	 */
	public function test_single_value_headers_are_fallbacks(): void {
		$this->trust_test_proxy_range();

		$this->request_from( self::PROXY, array( 'X-Real-IP' => self::VISITOR ) );
		$this->assertSame( self::VISITOR, User::get_ip() );

		$this->request_from( self::PROXY, array( 'True-Client-IP' => '198.51.100.20' ) );
		$this->assertSame( '198.51.100.20', User::get_ip() );
	}

	/**
	 * Ports some proxies add to X-Forwarded-For entries are ignored.
	 */
	public function test_forwarded_entries_with_ports(): void {
		$this->trust_test_proxy_range();

		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => self::VISITOR . ':4711' ) );
		$this->assertSame( self::VISITOR, User::get_ip() );

		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => '[' . self::VISITOR_V6 . ']:443' ) );
		$this->assertSame( self::VISITOR_V6, User::get_ip() );
	}

	/**
	 * A private or reserved forwarded address is not used, so the proxy's own
	 * address is kept, unless a site allows private forwarded addresses.
	 */
	public function test_private_forwarded_address_is_rejected_unless_allowed(): void {
		$this->trust_test_proxy_range();
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => '10.0.0.5' ) );

		$this->assertSame( self::PROXY, User::get_ip() );

		add_filter( 'zerospam_allow_private_forwarded_ip', '__return_true' );
		User::reset_cache();

		$this->assertSame( '10.0.0.5', User::get_ip() );
	}

	/**
	 * Garbage in the forwarded headers falls back to the connecting address.
	 */
	public function test_invalid_forwarded_values_fall_back_to_remote_addr(): void {
		$this->trust_test_proxy_range();
		$this->request_from(
			self::PROXY,
			array(
				'X-Forwarded-For' => 'unknown, <script>',
				'X-Real-IP'       => '999.1.1.1',
			)
		);

		$this->assertSame( self::PROXY, User::get_ip() );
	}

	/**
	 * IPv6 proxies can be trusted with a CIDR range.
	 */
	public function test_ipv6_cidr_proxy(): void {
		$this->set_setting( 'trusted_proxies', '2001:db8:100::/48', 'settings' );
		$this->request_from( '2001:db8:100::5', array( 'X-Forwarded-For' => self::VISITOR_V6 ) );

		$this->assertSame( self::VISITOR_V6, User::get_ip() );

		$this->request_from( '2001:db8:101::5', array( 'X-Forwarded-For' => self::VISITOR_V6 ) );
		$this->assertSame( '2001:db8:101::5', User::get_ip(), 'An address outside the range is not trusted.' );
	}

	/**
	 * Invalid lines saved by other means than the settings page (WP-CLI,
	 * imports) are ignored instead of breaking detection.
	 */
	public function test_invalid_stored_entries_are_ignored(): void {
		$this->set_setting( 'trusted_proxies', "not-an-ip\n192.0.2.0/24\n0.0.0.0/99", 'settings' );
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => self::VISITOR ) );

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * Ranges that would trust (almost) every visitor are ignored when read,
	 * whether stored without the settings page or added by the filter.
	 */
	public function test_too_broad_ranges_are_ignored_on_read(): void {
		$this->set_setting( 'trusted_proxies', "64.0.0.0/3\n10.0.0.0/4", 'settings' );
		add_filter(
			'zerospam_trusted_proxies',
			static function ( $proxies ) {
				$proxies[] = '2a00::/12';

				return $proxies;
			}
		);

		$this->request_from( '64.0.0.5', array( 'X-Forwarded-For' => self::VISITOR ) );
		$this->assertSame( '64.0.0.5', User::get_ip() );

		$this->request_from( '2a01:4f8:9::1', array( 'X-Forwarded-For' => self::VISITOR ) );
		$this->assertSame( '2a01:4f8:9::1', User::get_ip() );
	}

	/**
	 * Cloudflare's own list is not subject to the /32 minimum: its
	 * 2a06:98c0::/29 range is still recognised.
	 */
	public function test_cloudflare_wide_ipv6_range_is_still_trusted(): void {
		$this->request_from( '2a06:98c7::5', array( 'CF-Connecting-IP' => self::VISITOR ) );

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * The zerospam_trusted_proxies filter still adds proxies, now also as ranges.
	 */
	public function test_trusted_proxies_filter_is_honoured(): void {
		add_filter(
			'zerospam_trusted_proxies',
			static function ( $proxies ) {
				$proxies[] = '198.51.100.0/24';

				return $proxies;
			}
		);
		$this->request_from( '198.51.100.3', array( 'X-Forwarded-For' => self::VISITOR ) );

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * The trusted-proxy list is built once per request; reset_cache() starts over.
	 */
	public function test_trusted_proxy_list_is_cached_per_request(): void {
		$calls = 0;
		add_filter(
			'zerospam_trusted_proxies',
			static function ( $proxies ) use ( &$calls ) {
				++$calls;

				return $proxies;
			}
		);

		$this->request_from( '198.51.100.3', array( 'X-Forwarded-For' => self::VISITOR ) );
		User::get_ip();
		User::get_ip();
		$this->request_from( '198.51.100.4' );
		User::get_ip();

		$this->assertSame( 1, $calls, 'The list is read once per request.' );

		User::reset_cache();
		User::get_ip();

		$this->assertSame( 2, $calls, 'reset_cache() clears it.' );
	}

	/**
	 * A setting change is picked up after the per-request cache is reset.
	 */
	public function test_setting_change_applies_after_cache_reset(): void {
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => self::VISITOR ) );
		$this->assertSame( self::PROXY, User::get_ip() );

		$this->trust_test_proxy_range();

		$this->assertSame( self::VISITOR, User::get_ip() );
	}

	/**
	 * The Debug module's IP mock replaces the detected address.
	 */
	public function test_debug_ip_mock_still_wins(): void {
		$this->trust_test_proxy_range();
		update_option(
			'zero-spam-debug',
			array(
				'debug'    => 'enabled',
				'debug_ip' => '198.51.100.99',
			)
		);
		$this->request_from( self::PROXY, array( 'X-Forwarded-For' => self::VISITOR ) );

		$this->assertSame( '198.51.100.99', User::get_ip() );
	}

	/**
	 * Without a valid connecting address there is no IP.
	 */
	public function test_invalid_remote_addr_returns_false(): void {
		$this->request_from( 'not-an-ip', array( 'X-Forwarded-For' => self::VISITOR ) );
		$this->assertFalse( User::get_ip() );

		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertFalse( User::get_ip() );
	}

	/**
	 * Country headers are read from proxies in the Trusted Proxy IPs setting,
	 * the same list the IP detection uses.
	 */
	public function test_country_headers_use_the_trusted_proxy_setting(): void {
		$this->request_from( self::PROXY, array( 'X-Forwarded-Country' => 'de' ) );
		$this->assertFalse( Utilities::get_proxy_country_code(), 'Not trusted before the proxy is configured.' );

		$this->trust_test_proxy_range();

		$this->assertSame( 'DE', Utilities::get_proxy_country_code() );

		$this->request_from( self::PROXY, array( 'CF-IPCountry' => 'FR' ) );
		$this->assertSame( 'FR', Utilities::get_proxy_country_code() );
	}

	/**
	 * Cloudflare's country header is trusted from Cloudflare; the
	 * X-Forwarded-Country header, which Cloudflare passes through from the
	 * visitor, is not.
	 */
	public function test_country_headers_from_cloudflare(): void {
		$this->request_from( self::CLOUDFLARE_EDGE, array( 'CF-IPCountry' => 'NL' ) );
		$this->assertSame( 'NL', Utilities::get_proxy_country_code() );

		$this->request_from( self::CLOUDFLARE_EDGE, array( 'X-Forwarded-Country' => 'NL' ) );
		$this->assertFalse( Utilities::get_proxy_country_code() );

		$this->request_from( '198.51.100.7', array( 'CF-IPCountry' => 'NL' ) );
		$this->assertFalse( Utilities::get_proxy_country_code(), 'A visitor cannot pick a country.' );
	}
}
