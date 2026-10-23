<?php
/**
 * User class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * User class.
 *
 * Works out the visitor's IP address, including behind CDNs, load balancers
 * and reverse proxies.
 *
 * The address the connection comes from (REMOTE_ADDR) is the only value a
 * visitor cannot choose. Forwarded headers are read only when that address
 * is a trusted proxy: one of Cloudflare's published ranges, an entry of the
 * "Trusted Proxy IPs" setting or of the `zerospam_trusted_proxies` filter.
 * Otherwise anyone could pick their IP (and dodge blocks or get someone else
 * blocked) by sending an `X-Forwarded-For` header.
 */
class User {

	/**
	 * Single-address headers tried after X-Forwarded-For, in order ($_SERVER keys).
	 *
	 * @var string[]
	 */
	const FALLBACK_HEADERS = array( 'HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP' );

	/**
	 * Headers the detected IP depends on ($_SERVER keys).
	 *
	 * @var string[]
	 */
	const FORWARDED_HEADERS = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP' );

	/**
	 * Every header a proxy, load balancer or CDN may use to pass on the
	 * visitor's address ($_SERVER keys), whether or not get_ip() reads it.
	 *
	 * A request that carries one of them came through a proxy. Admin_Safe_IPs
	 * uses the list to recognise a proxy that is not trusted.
	 *
	 * @var string[]
	 */
	const PROXY_HEADERS = array(
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_FASTLY_CLIENT_IP',
		'HTTP_X_SUCURI_CLIENTIP',
		'HTTP_INCAP_CLIENT_IP',
		'HTTP_X_AZURE_CLIENTIP',
	);

	/**
	 * Trusted proxies for this request: Cloudflare's ranges and the configured
	 * ones (setting and filter). Built on first use.
	 *
	 * @var array{cloudflare: string[], configured: string[]}|null
	 */
	private static $trusted_proxies = null;

	/**
	 * Whether the trusted-proxy list is being built, so a settings filter that
	 * asks for the visitor IP does not recurse.
	 *
	 * @var bool
	 */
	private static $building = false;

	/**
	 * The IP detected for this request (before the `zerospam_get_ip` filter),
	 * with the request data it was detected from.
	 *
	 * @var array{key: string, ip: string|false}|null
	 */
	private static $detected = null;

	/**
	 * Gets the current visitor's IP address.
	 *
	 * 1. REMOTE_ADDR must be a valid IP address, or there is no IP (false).
	 * 2. If REMOTE_ADDR is not a trusted proxy, it is the visitor: no header
	 *    is read.
	 * 3. If it is a trusted proxy, the first usable value of:
	 *    - `CF-Connecting-IP`, only when REMOTE_ADDR is a Cloudflare address;
	 *    - `X-Forwarded-For`, walked from the right (see from_forwarded_for());
	 *      a Cloudflare hop in it ends the walk with CF-Connecting-IP or,
	 *      without a usable one, with REMOTE_ADDR;
	 *    - `X-Real-IP`, then `True-Client-IP`, when X-Forwarded-For is missing
	 *      or lists trusted proxies only.
	 *    Forwarded values must be valid, public addresses (see
	 *    `zerospam_allow_private_forwarded_ip`). When none is usable,
	 *    REMOTE_ADDR is used.
	 *
	 * IPv4-mapped IPv6 addresses (`::ffff:192.0.2.1`) are treated as the IPv4
	 * address they carry, for REMOTE_ADDR and forwarded values alike.
	 * 4. The `zerospam_get_ip` filter has the last word (the Debug module's IP
	 *    mock uses it).
	 *
	 * The detection is cached for the request; the filter runs on every call.
	 *
	 * @return string|false IP address, or false when the request has none.
	 */
	public static function get_ip() {
		$ip = self::detect_ip();

		if ( false === $ip ) {
			return false;
		}

		/**
		 * Filters the visitor's IP address.
		 *
		 * @param string $ip IP address detected from the request.
		 */
		return apply_filters( 'zerospam_get_ip', $ip );
	}

	/**
	 * Clears the per-request caches (the trusted-proxy list and the detected IP).
	 *
	 * For tests, and for code that changes the trusted proxies or the request
	 * mid-request.
	 */
	public static function reset_cache(): void {
		self::$trusted_proxies = null;
		self::$detected        = null;
	}

	/**
	 * The address the connection comes from, if it is a valid IP address.
	 *
	 * @return string IP address, or '' when REMOTE_ADDR is missing or invalid.
	 */
	public static function get_remote_addr(): string {
		return self::normalize_ip( self::server_value( 'REMOTE_ADDR' ) );
	}

	/**
	 * Validates an IP address and returns it in one canonical form: an
	 * IPv4-mapped IPv6 address (`::ffff:192.0.2.1`) becomes the IPv4 address
	 * it carries, and other IPv6 addresses are lowercased and compressed
	 * (`2001:DB8:0:0::1` becomes `2001:db8::1`), so range checks, validation
	 * and exact matches (whitelist) see one form.
	 *
	 * @param string $value Possible IP address.
	 * @return string The address, or '' when it is not a valid IP address.
	 */
	public static function normalize_ip( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || false === filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		if ( false !== strpos( $value, ':' ) ) {
			$binary = inet_pton( $value );
			if ( false === $binary || 16 !== strlen( $binary ) ) {
				return '';
			}

			// IPv4-mapped: keep the IPv4 address. Otherwise the canonical
			// (lowercase, compressed) form, so `2001:DB8:0:0::1` and
			// `2001:db8::1` compare equal.
			$mapped    = str_repeat( "\0", 10 ) . "\xff\xff" === substr( $binary, 0, 12 );
			$canonical = inet_ntop( $mapped ? substr( $binary, 12 ) : $binary );

			return false !== $canonical ? $canonical : '';
		}

		return rest_is_ip_address( $value ) ? $value : '';
	}

	/**
	 * Whether an address belongs to Cloudflare's published ranges.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_cloudflare_ip( string $ip ): bool {
		return self::in_ranges( $ip, self::trusted_proxies()['cloudflare'] );
	}

	/**
	 * Whether an address is a proxy from the "Trusted Proxy IPs" setting or
	 * the `zerospam_trusted_proxies` filter.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_configured_proxy( string $ip ): bool {
		return self::in_ranges( $ip, self::trusted_proxies()['configured'] );
	}

	/**
	 * Whether an address is a trusted proxy (Cloudflare or configured).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_trusted_proxy( string $ip ): bool {
		return self::is_cloudflare_ip( $ip ) || self::is_configured_proxy( $ip );
	}

	/**
	 * Detects the visitor IP, once per request.
	 *
	 * @return string|false
	 */
	private static function detect_ip() {
		$remote = self::get_remote_addr();
		if ( '' === $remote ) {
			return false;
		}

		$key = $remote;
		foreach ( self::FORWARDED_HEADERS as $header ) {
			$key .= '|' . self::server_value( $header );
		}

		if ( null !== self::$detected && self::$detected['key'] === $key ) {
			return self::$detected['ip'];
		}

		$forwarded = self::is_trusted_proxy( $remote ) ? self::forwarded_ip( $remote ) : '';
		$ip        = '' !== $forwarded ? $forwarded : $remote;

		self::$detected = array(
			'key' => $key,
			'ip'  => $ip,
		);

		return $ip;
	}

	/**
	 * The visitor address a trusted proxy forwarded.
	 *
	 * @param string $remote The proxy's address (REMOTE_ADDR).
	 * @return string IP address, or '' when the proxy forwarded none that is usable.
	 */
	private static function forwarded_ip( string $remote ): string {
		$allow_private = self::allow_private_forwarded_ip();

		// Cloudflare sets CF-Connecting-IP itself, replacing any sent by the visitor.
		if ( self::is_cloudflare_ip( $remote ) ) {
			$ip = self::usable_forwarded_ip( self::server_value( 'HTTP_CF_CONNECTING_IP' ), $allow_private );
			if ( '' !== $ip ) {
				return $ip;
			}
		}

		$ip = self::from_forwarded_for( self::server_value( 'HTTP_X_FORWARDED_FOR' ), $allow_private );
		if ( false === $ip ) {
			// The hop the last trusted proxy saw is unusable: no other header can be believed over it.
			return '';
		}

		if ( '' !== $ip ) {
			return $ip;
		}

		foreach ( self::FALLBACK_HEADERS as $header ) {
			$ip = self::usable_forwarded_ip( self::server_value( $header ), $allow_private );
			if ( '' !== $ip ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * The visitor address in an X-Forwarded-For header ("rightmost untrusted").
	 *
	 * Each proxy appends the address it received the request from, so the
	 * header reads "what the visitor sent, …, visitor, proxy 1, proxy 2". The
	 * leftmost entries are whatever the visitor put in its own header and are
	 * spoofable: only the entries added by trusted proxies can be believed.
	 * Walking from the right, configured proxies are skipped; the first other
	 * entry is the address the last trusted proxy received the request from,
	 * so it is the visitor. The walk stops there whatever that entry is: if it
	 * is not a usable public address (not an IP, private, reserved, CGNAT),
	 * the header is not believed at all. Skipping it instead would let a
	 * forged entry to its left win, for example behind a load balancer that
	 * does not add its own entry. Internal hops (an ingress or load balancer
	 * on 10.0.0.0/8, …) must therefore be listed as trusted proxies.
	 *
	 * A Cloudflare hop also ends the walk. Everything to its left is what
	 * reached Cloudflare, and anyone in Cloudflare's address space (a
	 * Cloudflare Worker, for example) can send a request with any
	 * X-Forwarded-For straight to a configured proxy. So behind a Cloudflare
	 * hop only CF-Connecting-IP is believed (Cloudflare sets it itself; the
	 * configured proxy passes it on). If it is missing or unusable, the
	 * header is not believed and REMOTE_ADDR is used.
	 *
	 * @param string $header        Header value.
	 * @param bool   $allow_private Whether private and reserved addresses are visitor addresses.
	 * @return string|false IP address; '' when the header is missing or lists
	 *                      trusted proxies only; false when the visitor entry
	 *                      is unusable.
	 */
	private static function from_forwarded_for( string $header, bool $allow_private ) {
		if ( '' === $header ) {
			return '';
		}

		foreach ( array_reverse( explode( ',', $header ) ) as $hop ) {
			$hop = self::normalize_ip( self::strip_port( trim( $hop ) ) );

			if ( '' === $hop ) {
				return false;
			}

			if ( self::is_cloudflare_ip( $hop ) ) {
				$cloudflare_visitor = self::usable_forwarded_ip( self::server_value( 'HTTP_CF_CONNECTING_IP' ), $allow_private );

				return '' !== $cloudflare_visitor ? $cloudflare_visitor : false;
			}

			if ( self::is_configured_proxy( $hop ) ) {
				continue;
			}

			return $allow_private || ! self::is_private_ip( $hop ) ? $hop : false;
		}

		return '';
	}

	/**
	 * A single forwarded address, if it is usable as the visitor IP.
	 *
	 * @param string $value         Header value.
	 * @param bool   $allow_private Whether private and reserved addresses are accepted.
	 * @return string IP address, or ''.
	 */
	private static function usable_forwarded_ip( string $value, bool $allow_private ): string {
		$ip = self::normalize_ip( self::strip_port( trim( $value ) ) );

		if ( '' === $ip || ( ! $allow_private && self::is_private_ip( $ip ) ) ) {
			return '';
		}

		return $ip;
	}

	/**
	 * Removes the port some proxies add to forwarded addresses
	 * (`203.0.113.9:4711`, `[2001:db8::9]:443`).
	 *
	 * @param string $value Address, possibly with a port.
	 * @return string
	 */
	private static function strip_port( string $value ): string {
		if ( preg_match( '/^\[([0-9a-fA-F:.]+)\](?::\d+)?$/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $value, $matches ) ) {
			return $matches[1];
		}

		return $value;
	}

	/**
	 * Whether an address is not public: private or reserved (10.0.0.0/8,
	 * 127.0.0.0/8, 192.168.0.0/16, fc00::/7, ::1, …) as PHP's filter defines
	 * them (recent PHP versions also count 2001:db8::/32, the IPv6
	 * documentation range), or carrier-grade NAT (100.64.0.0/10).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private static function is_private_ip( string $ip ): bool {
		return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )
			|| Utilities::ip_in_range( $ip, '100.64.0.0/10' );
	}

	/**
	 * Whether private and reserved forwarded addresses may be the visitor IP.
	 *
	 * @return bool
	 */
	private static function allow_private_forwarded_ip(): bool {
		/**
		 * Filters whether a private or reserved address (10.0.0.0/8,
		 * 192.168.0.0/16, fc00::/7, 127.0.0.1, …) forwarded by a trusted proxy
		 * can be the visitor IP. Only enable this for sites whose visitors
		 * really reach the proxy from a private network, such as an intranet.
		 *
		 * @since 5.7.14
		 *
		 * @param bool $allow Whether to accept private forwarded addresses. Default false.
		 */
		return (bool) apply_filters( 'zerospam_allow_private_forwarded_ip', false );
	}

	/**
	 * The trusted proxies, built once per request.
	 *
	 * @return array{cloudflare: string[], configured: string[]}
	 */
	private static function trusted_proxies(): array {
		if ( null !== self::$trusted_proxies ) {
			return self::$trusted_proxies;
		}

		if ( self::$building ) {
			return array(
				'cloudflare' => array(),
				'configured' => array(),
			);
		}

		self::$building = true;

		try {
			$configured = Utilities::parse_ip_ranges( Settings::get_settings( 'trusted_proxies' ) )['valid'];

			/**
			 * Filters the proxies whose forwarded headers are trusted, in
			 * addition to Cloudflare's ranges (see `zerospam_cloudflare_ip_ranges`).
			 *
			 * Add to the array rather than replacing it, so the entries of the
			 * "Trusted Proxy IPs" setting are kept. Entries may be IP addresses
			 * or CIDR ranges, IPv4 or IPv6; invalid entries and ranges broader
			 * than /8 (IPv4) or /32 (IPv6) are ignored.
			 *
			 * @since 5.7.12
			 * @since 5.7.14 Applied to the visitor IP as well as country headers;
			 *               accepts CIDR ranges; receives the Trusted Proxy IPs setting.
			 *
			 * @param string[] $proxies IP addresses and CIDR ranges from the Trusted Proxy IPs setting.
			 */
			$filtered = apply_filters( 'zerospam_trusted_proxies', $configured );

			// Cloudflare's published list is used as is: it includes 2a06:98c0::/29,
			// which the /32 minimum for configured ranges would reject.
			$cloudflare = array_map(
				'trim',
				array_filter(
					Utilities::cloudflare_ip_ranges(),
					static function ( $range ) {
						return is_string( $range ) && Utilities::is_ip_range( trim( $range ) );
					}
				)
			);

			self::$trusted_proxies = array(
				'cloudflare' => $cloudflare,
				'configured' => Utilities::parse_ip_ranges( (array) $filtered )['valid'],
			);
		} finally {
			self::$building = false;
		}

		return self::$trusted_proxies;
	}

	/**
	 * Whether an address is inside any of the given addresses or ranges.
	 *
	 * @param string   $ip     IP address.
	 * @param string[] $ranges IP addresses and CIDR ranges.
	 * @return bool
	 */
	private static function in_ranges( string $ip, array $ranges ): bool {
		$ip = self::normalize_ip( $ip );
		if ( '' === $ip ) {
			return false;
		}

		foreach ( $ranges as $range ) {
			if ( Utilities::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A $_SERVER value as a sanitized string.
	 *
	 * @param string $key $_SERVER key.
	 * @return string Value, or '' when missing.
	 */
	private static function server_value( string $key ): string {
		if ( ! isset( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
			return '';
		}

		return trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
	}
}
