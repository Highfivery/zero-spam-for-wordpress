<?php
/**
 * Resolves the test environment from environment variables and versions.env.
 *
 * Side-effect free: reads files and environment variables and returns an
 * array, without defining anything. Loaded by the PHPUnit bootstrap and by
 * wp-tests-config.php, which WordPress also loads in a separate PHP process
 * to install the test site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

return ( static function (): array {
	$root = dirname( __DIR__, 3 );

	$env = static function ( string $name, string $fallback ): string {
		$value = getenv( $name );

		return ( false === $value || '' === $value ) ? $fallback : $value;
	};

	$versions = array();
	$lines    = file( __DIR__ . '/../versions.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	foreach ( false === $lines ? array() : $lines as $line ) {
		if ( preg_match( '/^([A-Z0-9_]+)=(.*)$/', trim( $line ), $match ) ) {
			$versions[ $match[1] ] = trim( $match[2], "\"'" );
		}
	}

	$home  = $env( 'HOME', sys_get_temp_dir() );
	$cache = $env(
		'ZEROSPAM_TESTS_CACHE',
		'Darwin' === PHP_OS_FAMILY
			? $home . '/Library/Caches/zero-spam-tests'
			: $env( 'XDG_CACHE_HOME', $home . '/.cache' ) . '/zero-spam-tests'
	);
	$wp    = $env( 'ZEROSPAM_WP_VERSION', $versions['WP_LATEST'] ?? '' );

	return array(
		'root'            => $root,
		'versions'        => $versions,
		'cache_dir'       => $cache,
		'wp_version'      => $wp,
		'wp_core_dir'     => rtrim( $env( 'ZEROSPAM_WP_CORE_DIR', $cache . '/wordpress-' . $wp ), '/' ),
		'wp_tests_dir'    => rtrim( $env( 'ZEROSPAM_WP_TESTS_DIR', $root . '/vendor/wp-phpunit/wp-phpunit' ), '/' ),
		'db_host'         => $env( 'WP_TESTS_DB_HOST', '127.0.0.1' ),
		'db_user'         => $env( 'WP_TESTS_DB_USER', 'root' ),
		'db_password'     => (string) getenv( 'WP_TESTS_DB_PASSWORD' ),
		'db_name'         => $env( 'WP_TESTS_DB_NAME', 'zerospam_tests' ),
		// Installing WordPress for the tests drops tables, so only these names are accepted.
		'db_name_pattern' => '/^zerospam_tests(_\w+)?$/',
		'multisite'       => '1' === getenv( 'WP_MULTISITE' ),
	);
} )();
