<?php
/**
 * Stub for ZeroSpam\Modules\Login: setcookie() in Login::set_intent_token().
 *
 * See stubs/core.php for why namespaced stubs work.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Modules\Login;

use ZeroSpam\Tests\Stubs;

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
