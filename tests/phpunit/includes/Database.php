<?php
/**
 * Test database guard and plugin table checks.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

use mysqli;
use RuntimeException;
use ZeroSpam\Includes\DB;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages go to the CLI before WordPress (and its escaping functions) is loaded.

/**
 * Prepares the throwaway test database before WordPress is installed into it.
 */
final class Database {

	/**
	 * Creates the test database and drops plugin tables left by a previous run.
	 *
	 * Refuses any database whose name does not match the test pattern: the
	 * WordPress test installer drops tables, so pointing it at a site database
	 * would destroy that site.
	 *
	 * @param array<string, mixed> $env Environment from includes/env.php.
	 * @throws RuntimeException When the name is not a test database or MySQL fails.
	 */
	public static function prepare( array $env ): void {
		$name = (string) $env['db_name'];

		if ( ! preg_match( (string) $env['db_name_pattern'], $name ) ) {
			throw new RuntimeException(
				sprintf(
					'Refusing to use database "%s". The test database name must match %s because installing WordPress for the tests drops tables.',
					$name,
					$env['db_name_pattern']
				)
			);
		}

		// WordPress is not loaded yet, so there is no $wpdb to use.
		mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- See above.

		$host = (string) $env['db_host'];
		$port = null;
		if ( preg_match( '/^(.+):(\d+)$/', $host, $match ) ) {
			$host = $match[1];
			$port = (int) $match[2];
		}

		$link = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- See above.
		if ( ! $link instanceof mysqli || ! @$link->real_connect( $host, (string) $env['db_user'], (string) $env['db_password'], '', $port ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is reported below.
			throw new RuntimeException(
				sprintf( 'Cannot connect to MySQL at %s as %s. Start it with: brew services start mysql', $env['db_host'], $env['db_user'] )
			);
		}

		// The name matched the pattern above, so it is safe to quote as an identifier.
		if ( ! $link->query( "CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4" ) || ! $link->select_db( $name ) ) {
			throw new RuntimeException( sprintf( 'Cannot create or select database %s: %s', $name, $link->error ) );
		}

		$result = $link->query( "SHOW TABLES LIKE '%wpzerospam%'" );
		if ( $result instanceof \mysqli_result ) {
			foreach ( $result->fetch_all() as $row ) {
				$link->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', (string) $row[0] ) . '`' );
			}
		}

		$link->close();
	}

	/**
	 * Full names of the plugin tables DB::update() creates for the current site.
	 *
	 * `wpzerospam_blacklist` is listed in DB::$tables but never created.
	 *
	 * @return array<string, string> Table key => full table name.
	 */
	public static function plugin_tables(): array {
		global $wpdb;

		$tables = array();
		foreach ( DB::$tables as $key => $table ) {
			if ( 'blacklist' === $key ) {
				continue;
			}

			$prefix         = in_array( $key, array( 'log', 'blocked' ), true ) ? $wpdb->prefix : $wpdb->base_prefix;
			$tables[ $key ] = $prefix . $table;
		}

		return $tables;
	}

	/**
	 * Checks that every plugin table exists as a real (non-temporary) table.
	 *
	 * Temporary tables are invisible to SHOW TABLES, which is what DB::log()
	 * and DB::query() use, so those methods can only be tested when the tables
	 * were created for real while WordPress loaded (DB::update() on `init`).
	 *
	 * @throws RuntimeException Listing the missing tables.
	 */
	public static function assert_plugin_tables_exist(): void {
		global $wpdb;

		$missing = array();
		foreach ( self::plugin_tables() as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				$missing[] = $table;
			}
		}

		if ( $missing ) {
			throw new RuntimeException( 'Plugin tables were not created while WordPress loaded: ' . implode( ', ', $missing ) );
		}
	}
}
