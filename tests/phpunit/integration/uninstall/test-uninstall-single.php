<?php
/**
 * Tests for uninstall.php on a single site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * Uninstalling removes everything the plugin stored.
 *
 * @group ms-excluded
 * @coversNothing
 */
class Test_Uninstall_Single extends TestCase {

	/**
	 * License key seeded into the zerospam module settings.
	 */
	const LICENSE = 'test-license-key';

	/**
	 * Path to uninstall.php.
	 */
	private function uninstall_file(): string {
		return dirname( __DIR__, 4 ) . '/uninstall.php';
	}

	/**
	 * Runs uninstall.php as WordPress does.
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'zero-spam/wordpress-zero-spam.php' );
		}

		require $this->uninstall_file();
	}

	/**
	 * Transient key of the cached Zero Spam API query for the default IP.
	 */
	private static function query_cache_transient(): string {
		return Utilities::cache_key( array( 'zero_spam', self::DEFAULT_IP ) );
	}

	/**
	 * Options, network options, transients, cron events and user meta are all removed.
	 */
	public function test_removes_options_transients_and_cron_events(): void {
		global $wpdb;

		foreach ( self::PLUGIN_MODULES as $module ) {
			update_option( "zero-spam-{$module}", array( 'seeded' => 'yes' ) );
			update_site_option( "zero-spam-network-{$module}", array( 'seeded' => 'yes' ) );
		}
		update_option( 'zero-spam-zerospam', array( 'zerospam_license' => self::LICENSE ) );
		foreach ( self::PLUGIN_SITE_OPTIONS as $option ) {
			update_option( $option, 'seeded' );
		}
		foreach ( self::PLUGIN_NETWORK_OPTIONS as $option ) {
			update_site_option( $option, 'seeded' );
		}

		$transients = array_merge(
			self::PLUGIN_TRANSIENTS,
			array( self::query_cache_transient(), sanitize_title( 'license_' . self::LICENSE ) )
		);
		foreach ( $transients as $transient ) {
			set_transient( $transient, 'seeded', HOUR_IN_SECONDS );
		}
		set_site_transient( 'zerospam_network_settings', 'seeded', HOUR_IN_SECONDS );

		foreach ( self::PLUGIN_CRON_HOOKS as $hook ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
		}
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'zerospam_promo_dismissed', time() );

		$this->uninstall();
		wp_cache_flush();

		$options = array_merge(
			array_map(
				static function ( string $module ): string {
					return "zero-spam-{$module}";
				},
				self::PLUGIN_MODULES
			),
			self::PLUGIN_SITE_OPTIONS
		);

		$network_options = array_merge(
			array_map(
				static function ( string $module ): string {
					return "zero-spam-network-{$module}";
				},
				self::PLUGIN_MODULES
			),
			self::PLUGIN_NETWORK_OPTIONS
		);

		$left = array();
		foreach ( $options as $option ) {
			if ( false !== get_option( $option ) ) {
				$left[] = "option {$option}";
			}
		}
		foreach ( $network_options as $option ) {
			if ( false !== get_site_option( $option ) ) {
				$left[] = "network option {$option}";
			}
		}
		foreach ( $transients as $transient ) {
			if ( false !== get_transient( $transient ) ) {
				$left[] = "transient {$transient}";
			}
		}
		if ( false !== get_site_transient( 'zerospam_network_settings' ) ) {
			$left[] = 'site transient zerospam_network_settings';
		}
		foreach ( self::PLUGIN_CRON_HOOKS as $hook ) {
			if ( false !== wp_next_scheduled( $hook ) ) {
				$left[] = "cron {$hook}";
			}
		}
		if ( '' !== get_user_meta( $user_id, 'zerospam_promo_dismissed', true ) ) {
			$left[] = 'user meta zerospam_promo_dismissed';
		}

		$this->assertSame( array(), $left, 'uninstall.php left these behind.' );
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'zero-spam-%'" ) );
	}

	/**
	 * Keys nobody listed are still swept by prefix, and the prefixes are
	 * matched literally (an underscore is not a LIKE wildcard).
	 */
	public function test_sweeps_unlisted_keys_by_prefix_only(): void {
		$swept = array(
			'zero-spam-unlisted',
			'zerospam_unlisted',
			'zero_spam_unlisted',
			'wpzerospam_unlisted',
			'_transient_zerospam_unlisted',
			'_transient_timeout_zerospam_unlisted',
			'_transient_zero_spam_unlisted',
			'_transient_timeout_zero_spam_unlisted',
			'_site_transient_zerospam_unlisted',
			'_site_transient_timeout_zerospam_unlisted',
		);
		$kept  = array(
			'zerospamXunlisted',
			'zero_spamXunlisted',
			'_transient_zerospamXunlisted',
			'unrelated_option',
		);
		foreach ( array_merge( $swept, $kept ) as $option ) {
			add_option( $option, 'seeded', '', false );
		}

		$this->uninstall();
		wp_cache_flush();

		$left = array_values(
			array_filter(
				$swept,
				static function ( string $option ): bool {
					return false !== get_option( $option );
				}
			)
		);
		$gone = array_values(
			array_filter(
				$kept,
				static function ( string $option ): bool {
					return false === get_option( $option );
				}
			)
		);

		$this->assertSame( array(), $left, 'The prefix sweep missed these.' );
		$this->assertSame( array(), $gone, 'The prefix sweep deleted options that are not the plugin\'s.' );
	}

	/**
	 * Every plugin table is dropped.
	 */
	public function test_drops_every_plugin_table(): void {
		global $wpdb;

		$queries = $this->capture_queries(
			function () {
				$this->uninstall();
			}
		);

		$missing = array();
		foreach ( DB::$tables as $table ) {
			$name    = $wpdb->prefix . $table;
			$pattern = '/^DROP TABLE IF EXISTS\s+`?' . preg_quote( $name, '/' ) . '`?\s*;?$/i';
			if ( ! preg_grep( $pattern, array_map( 'trim', $queries ) ) ) {
				$missing[] = $name;
			}
		}

		$this->assertSame( array(), $missing, 'uninstall.php does not drop these tables.' );
	}

	/**
	 * Loaded outside WordPress's uninstall routine, the file does nothing.
	 */
	public function test_does_nothing_without_wp_uninstall_plugin(): void {
		$code = sprintf( 'define( "ABSPATH", "/" ); require %s; echo "ran";', var_export( $this->uninstall_file(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Builds a PHP string literal for the child process.

		$output = array();
		$status = null;
		exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Runs the file in a separate PHP process without WordPress.

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array(), $output, 'uninstall.php ran without WP_UNINSTALL_PLUGIN.' );
	}
}
