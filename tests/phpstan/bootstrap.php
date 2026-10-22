<?php
/**
 * PHPStan bootstrap: declares the constants the plugin defines at runtime
 * so static analysis can resolve them without loading WordPress.
 *
 * @package ZeroSpam
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', '/' );
defined( 'WP_CLI' ) || define( 'WP_CLI', false );
defined( 'ZEROSPAM' ) || define( 'ZEROSPAM', __DIR__ . '/../../wordpress-zero-spam.php' );
defined( 'ZEROSPAM_PATH' ) || define( 'ZEROSPAM_PATH', __DIR__ . '/../../' );
defined( 'ZEROSPAM_PLUGIN_BASE' ) || define( 'ZEROSPAM_PLUGIN_BASE', 'zero-spam/wordpress-zero-spam.php' );
defined( 'ZEROSPAM_VERSION' ) || define( 'ZEROSPAM_VERSION', '0.0.0' );
defined( 'ZEROSPAM_URL' ) || define( 'ZEROSPAM_URL', 'https://www.zerospam.org/' );
defined( 'ZEROSPAM_DEVELOPMENT_URL' ) || define( 'ZEROSPAM_DEVELOPMENT_URL', 'https://zerospam.local/' );
