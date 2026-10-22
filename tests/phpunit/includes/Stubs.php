<?php
/**
 * State behind the namespaced function stubs in stubs/.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

/**
 * Records what the plugin tried to do with DNS and cookies.
 *
 * DNS lookups and cookies do not go through the WordPress HTTP API, so the
 * `pre_http_request` mock cannot catch them. The files in stubs/ declare
 * functions with the same names in the plugin namespaces that call them
 * (ZeroSpam\Core, ZeroSpam\Modules, ZeroSpam\Modules\Login); PHP resolves an
 * unqualified call to the namespaced function first, so these run instead of
 * the real network and header functions. headers_sent() is stubbed in
 * ZeroSpam\Core and ZeroSpam\Modules\Login so Access and Login reach their
 * setcookie() calls under the CLI.
 */
final class Stubs {

	/**
	 * Cookies the plugin tried to set.
	 *
	 * @var array<int, array{name: string, value: string, options: mixed}>
	 */
	public static array $cookies = array();

	/**
	 * Answer for headers_sent() in ZeroSpam\Core and ZeroSpam\Modules\Login.
	 *
	 * @var bool
	 */
	public static bool $headers_sent = false;

	/**
	 * Answers for checkdnsrr(), keyed by "host|TYPE". Unlisted lookups succeed.
	 *
	 * @var array<string, bool>
	 */
	public static array $dns_checks = array();

	/**
	 * Answers for dns_get_record(), keyed by host. Unlisted hosts have no records.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	public static array $dns_records = array();

	/**
	 * DNS lookups the plugin made, as "function host TYPE".
	 *
	 * @var string[]
	 */
	public static array $dns_lookups = array();

	/**
	 * Records a cookie the plugin tried to set.
	 *
	 * @param string    $name               Cookie name.
	 * @param string    $value              Cookie value.
	 * @param array|int $expires_or_options Expiry or options array.
	 * @param array     $rest               Remaining positional setcookie() arguments.
	 */
	public static function record_cookie( string $name, string $value, $expires_or_options, array $rest ): bool {
		self::$cookies[] = array(
			'name'    => $name,
			'value'   => $value,
			'options' => is_array( $expires_or_options ) ? $expires_or_options : array_merge( array( $expires_or_options ), $rest ),
		);

		return true;
	}

	/**
	 * Clears all recorded calls and configured answers.
	 */
	public static function reset(): void {
		self::$headers_sent = false;
		self::$cookies      = array();
		self::$dns_checks   = array();
		self::$dns_records  = array();
		self::$dns_lookups  = array();
	}
}
