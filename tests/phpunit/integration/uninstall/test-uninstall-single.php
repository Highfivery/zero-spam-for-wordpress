<?php
/**
 * Tests for uninstall.php on a single site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * Uninstalling removes everything the plugin stored.
 *
 * @group ms-excluded
 * @group known-defect
 * @coversNothing
 */
class Test_Uninstall_Single extends TestCase {

	/**
	 * Every module option key used in the source (`'module' => '…'`).
	 */
	const MODULES = array(
		'comments',
		'contactform7',
		'davidwalsh',
		'debug',
		'elementor',
		'fluent_form',
		'formidable',
		'givewp',
		'google',
		'gravityforms',
		'ipbase',
		'ipinfo',
		'ipstack',
		'login',
		'mailchimp4wp',
		'project_honeypot',
		'registration',
		'security',
		'settings',
		'stop_forum_spam',
		'woocommerce',
		'wpforms',
		'zerospam',
	);

	/**
	 * Site options the plugin writes besides the module options.
	 */
	const OPTIONS = array(
		'wpzerospam',
		'wpzerospam_honeypot',
		'zerospam_db_version',
		'zerospam_configured',
		'zerospam_davidwalsh',
		'zerospam_davidwalsh_data',
		'zerospam_davidwalsh_secret',
		'zero_spam_last_api_report',
		'zero-spam-last-update',
		'zerospam_completed_migrations',
		'zerospam_show_settings_review_notice',
		'zerospam_activation_time',
		'zerospam_api_monitoring_notice_dismissed',
		'zerospam_share_queue',
		'zerospam_blocked_email_domains',
		'zerospam_api_notices',
		'zerospam_settings_audit',
	);

	/**
	 * Transients the plugin sets.
	 */
	const TRANSIENTS = array(
		'zerospam_geo_test',
		'zerospam_login_intent_test',
		'zero_spam_circuit_open',
		'zero_spam_failure_count',
	);

	/**
	 * Cron hooks the plugin schedules.
	 */
	const CRON_HOOKS = array(
		'zerospam_davidwalsh_rotate_key',
		'zerospam_aggregate_daily_stats',
		'zerospam_check_api_anomalies',
		'zerospam_aggregate_api_data',
		'zerospam_async_share_detection',
	);

	/**
	 * Runs uninstall.php as WordPress does.
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'zero-spam/wordpress-zero-spam.php' );
		}

		require dirname( __DIR__, 4 ) . '/uninstall.php';
	}

	/**
	 * Options, transients and cron events are all removed.
	 */
	public function test_removes_options_transients_and_cron_events(): void {
		global $wpdb;

		foreach ( self::MODULES as $module ) {
			update_option( "zero-spam-{$module}", array( 'seeded' => 'yes' ) );
		}
		foreach ( self::OPTIONS as $option ) {
			update_option( $option, 'seeded' );
		}
		foreach ( self::TRANSIENTS as $transient ) {
			set_transient( $transient, 'seeded', HOUR_IN_SECONDS );
		}
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
		}

		$this->uninstall();
		wp_cache_flush();

		$options = array_merge(
			array_map(
				static function ( string $module ): string {
					return "zero-spam-{$module}";
				},
				self::MODULES
			),
			self::OPTIONS
		);

		$left = array();
		foreach ( $options as $option ) {
			if ( false !== get_option( $option ) ) {
				$left[] = "option {$option}";
			}
		}
		foreach ( self::TRANSIENTS as $transient ) {
			if ( false !== get_transient( $transient ) ) {
				$left[] = "transient {$transient}";
			}
		}
		foreach ( self::CRON_HOOKS as $hook ) {
			if ( false !== wp_next_scheduled( $hook ) ) {
				$left[] = "cron {$hook}";
			}
		}

		$this->assertSame( array(), $left, 'uninstall.php left these behind.' );
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'zero-spam-%'" ) );
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
			$drop = "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}";
			if ( ! in_array( $drop, $queries, true ) ) {
				$missing[] = $drop;
			}
		}

		$this->assertSame( array(), $missing, 'uninstall.php does not drop these tables.' );
	}
}
