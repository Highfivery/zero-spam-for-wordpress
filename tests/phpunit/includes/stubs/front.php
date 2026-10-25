<?php
/**
 * Stubs for ZeroSpam\Core\Front: headers_sent() and header() in
 * Block_Page::die_handler(), so tests can read the headers a blocked, rescue
 * or expired-link page sends (under the CLI, header() sends nothing and
 * headers_sent() is true once PHPUnit prints).
 *
 * See stubs/core.php for why namespaced stubs work.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Front;

use ZeroSpam\Tests\Stubs;

/**
 * Stub for headers_sent(): answers Stubs::$headers_sent, false by default.
 */
function headers_sent(): bool {
	return Stubs::$headers_sent;
}

/**
 * Stub for header(): records the header line instead of sending it.
 *
 * @param string $header             Header line.
 * @param bool   $replace            Whether to replace an earlier header of the same name.
 * @param int    $response_code      HTTP status code.
 */
function header( string $header, bool $replace = true, int $response_code = 0 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature of header().
	Stubs::$headers[] = $header;
}

/**
 * Stub for header_remove(): does nothing (under the CLI, output has started).
 *
 * @param string|null $name Header name.
 */
function header_remove( ?string $name = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature of header_remove().
}
