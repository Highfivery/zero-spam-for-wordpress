<?php
/**
 * PHPUnit bootstrap for Zero Spam for WordPress.
 *
 * Run the suite through bin/test.sh, which downloads and verifies WordPress
 * core and the test library and exports where they are.
 *
 * Order matters:
 * 1. Refuse any database that is not a test database, create it, and drop
 *    plugin tables left by an earlier run (the WordPress installer only drops
 *    core tables).
 * 2. Block all outbound HTTP and define the plugin's test constants before
 *    WordPress loads.
 * 3. Load the plugin as a must-use plugin, after the DNS/cookie stubs.
 * 4. Install and load WordPress; DB::update() creates the plugin tables on
 *    `init`, outside any test transaction, so they are real tables.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\Database;
use ZeroSpam\Tests\HttpMock;

$zerospam_tests_root = dirname( __DIR__, 2 );

require_once $zerospam_tests_root . '/vendor/autoload.php';

$zerospam_tests_env = require __DIR__ . '/includes/env.php';

$zerospam_tests_abort = static function ( string $message ): void {
	fwrite( STDERR, 'Zero Spam tests: ' . $message . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
};

try {
	Database::prepare( $zerospam_tests_env );
} catch ( RuntimeException $zerospam_tests_error ) {
	$zerospam_tests_abort( $zerospam_tests_error->getMessage() );
}

if ( ! is_file( $zerospam_tests_env['wp_core_dir'] . '/wp-includes/version.php' ) ) {
	$zerospam_tests_abort( 'WordPress core not found in ' . $zerospam_tests_env['wp_core_dir'] . '. Run the suite with bin/test.sh, which downloads it.' );
}

if ( ! is_file( $zerospam_tests_env['wp_tests_dir'] . '/includes/bootstrap.php' ) ) {
	$zerospam_tests_abort( 'WordPress test library not found in ' . $zerospam_tests_env['wp_tests_dir'] . '. Run composer install, then bin/test.sh.' );
}

// The environment test compares this with the loaded $wp_version.
putenv( 'ZEROSPAM_WP_VERSION=' . $zerospam_tests_env['wp_version'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Test process only.

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

// Plugin constants read when the plugin loads.
define( 'ZEROSPAM_DEVELOPMENT_URL', 'https://zerospam.invalid/' );
define( 'ZEROSPAM_RESCUE_KEY', 'zerospam-tests-rescue-key' );

require_once $zerospam_tests_env['wp_tests_dir'] . '/includes/functions.php';

HttpMock::register();

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $zerospam_tests_root ): void {
		require_once __DIR__ . '/includes/stubs/core.php';
		require_once __DIR__ . '/includes/stubs/modules.php';
		require_once __DIR__ . '/includes/stubs/login.php';
		require_once $zerospam_tests_root . '/wordpress-zero-spam.php';
	}
);

require $zerospam_tests_env['wp_tests_dir'] . '/includes/bootstrap.php';

try {
	Database::assert_plugin_tables_exist();
} catch ( RuntimeException $zerospam_tests_error ) {
	$zerospam_tests_abort( $zerospam_tests_error->getMessage() );
}
