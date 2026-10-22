<?php
/**
 * Stubs for ZeroSpam\Core: checkdnsrr() in Utilities::is_email() and
 * setcookie() in Access rescue mode.
 *
 * DNS lookups and cookies do not go through the WordPress HTTP API, so the
 * HTTP mock cannot catch them. PHP resolves an unqualified function call to
 * the function of the same name in the caller's namespace first, so these
 * run instead of the real ones. State lives in ZeroSpam\Tests\Stubs.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

use ZeroSpam\Tests\Stubs;

/**
 * Stub for checkdnsrr(): answers from Stubs::$dns_checks, true by default.
 *
 * @param string $hostname Host name.
 * @param string $type     Record type.
 */
function checkdnsrr( string $hostname, string $type = 'MX' ): bool {
	Stubs::$dns_lookups[] = 'checkdnsrr ' . $hostname . ' ' . $type;

	return Stubs::$dns_checks[ $hostname . '|' . strtoupper( $type ) ] ?? true;
}

/**
 * Stub for setcookie(): records the cookie instead of sending a header.
 *
 * @param string    $name               Cookie name.
 * @param string    $value              Cookie value.
 * @param array|int $expires_or_options Expiry or options array.
 * @param mixed     ...$rest            Remaining positional arguments (path, domain, ...).
 */
function setcookie( string $name, string $value = '', $expires_or_options = 0, ...$rest ): bool {
	return Stubs::record_cookie( $name, $value, $expires_or_options, $rest );
}
