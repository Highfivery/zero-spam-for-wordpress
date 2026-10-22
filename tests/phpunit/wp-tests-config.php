<?php
/**
 * WordPress test configuration for Zero Spam for WordPress.
 *
 * Loaded by the WordPress test bootstrap and, in a separate PHP process, by
 * the WordPress test installer, which drops and recreates the core tables.
 * That is why the database name is checked here as well as in the bootstrap.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

$zerospam_tests_config = require __DIR__ . '/includes/env.php';

if ( ! preg_match( $zerospam_tests_config['db_name_pattern'], $zerospam_tests_config['db_name'] ) ) {
	fwrite( STDERR, 'wp-tests-config.php: refusing to use database "' . $zerospam_tests_config['db_name'] . '". The test database name must match ' . $zerospam_tests_config['db_name_pattern'] . ' because installing WordPress drops tables.' . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI message to STDERR.
	exit( 1 );
}

define( 'ABSPATH', $zerospam_tests_config['wp_core_dir'] . '/' );

// A plugins directory outside any real site, holding only a zero-spam symlink
// to this checkout, so plugin_basename() returns
// zero-spam/wordpress-zero-spam.php as in production.
define( 'WP_PLUGIN_DIR', $zerospam_tests_config['wp_plugin_dir'] );

define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );

define( 'DB_NAME', $zerospam_tests_config['db_name'] );
define( 'DB_USER', $zerospam_tests_config['db_user'] );
define( 'DB_PASSWORD', $zerospam_tests_config['db_password'] );
define( 'DB_HOST', $zerospam_tests_config['db_host'] );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Test-only salts; nothing here protects real data.
define( 'AUTH_KEY', 'zerospam-tests-auth-key' );
define( 'SECURE_AUTH_KEY', 'zerospam-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'zerospam-tests-logged-in-key' );
define( 'NONCE_KEY', 'zerospam-tests-nonce-key' );
define( 'AUTH_SALT', 'zerospam-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'zerospam-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'zerospam-tests-logged-in-salt' );
define( 'NONCE_SALT', 'zerospam-tests-nonce-salt' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress reads the table prefix from this variable.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Zero Spam Tests' );

// WordPress concatenates this into a shell command unquoted, and the LocalWP
// PHP path contains a space.
define( 'WP_PHP_BINARY', escapeshellarg( PHP_BINARY ) );

define( 'WPLANG', '' );

unset( $zerospam_tests_config );
