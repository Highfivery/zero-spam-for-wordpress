<?php
/**
 * A stand-in for WP_CLI\Utils\format_items(), for tests of the plugin's
 * command classes. Load it with stubs/wp-cli.php.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace WP_CLI\Utils;

if ( ! function_exists( __NAMESPACE__ . '\format_items' ) ) {
	/**
	 * Records the rows as one JSON line.
	 *
	 * @param string                           $format Format.
	 * @param array<int, array<string, mixed>> $items  Rows.
	 * @param array<int, string>|string        $fields Columns.
	 */
	function format_items( $format, $items, $fields ): void {
		unset( $format, $fields );
		\WP_CLI::line( (string) wp_json_encode( $items ) );
	}
}
