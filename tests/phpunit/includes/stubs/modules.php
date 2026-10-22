<?php
/**
 * Stub for ZeroSpam\Modules: dns_get_record() in ProjectHoneypot.
 *
 * See stubs/core.php for why namespaced stubs work.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Modules;

use ZeroSpam\Tests\Stubs;

/**
 * Stub for dns_get_record(): answers from Stubs::$dns_records, empty by default.
 *
 * @param string $hostname Host name.
 * @param int    $type     DNS_* constant.
 * @return array<int, array<string, mixed>>
 */
function dns_get_record( string $hostname, int $type = DNS_ANY ): array {
	Stubs::$dns_lookups[] = 'dns_get_record ' . $hostname . ' ' . $type;

	return Stubs::$dns_records[ $hostname ] ?? array();
}
