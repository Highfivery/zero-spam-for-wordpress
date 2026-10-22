<?php
/**
 * Tests for the warning about visitors sharing one IP address.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Shared_IP_Warning;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\TestCase;

/**
 * Administrators are warned on the Zero Spam screens when recent detections
 * point to a proxy or CDN Zero Spam does not know about.
 *
 * @covers \ZeroSpam\Core\Admin\Shared_IP_Warning
 */
class Test_Shared_IP_Warning extends TestCase {

	/**
	 * The address the detections share.
	 */
	const SHARED_IP = '198.51.100.50';

	/**
	 * The settings screen, as an administrator, with no cached verdict.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		delete_transient( Shared_IP_Warning::TRANSIENT );
		delete_option( Shared_IP_Warning::DISMISSED_OPTION );

		// Installed a day ago: every row insert_log_rows() adds is newer.
		update_option( Migrations::PROXY_DETECTION_SINCE_OPTION, wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
	}

	/**
	 * The admin notices the warning prints.
	 */
	private function notices(): string {
		ob_start();
		( new Shared_IP_Warning() )->render();

		return (string) ob_get_clean();
	}

	/**
	 * The current request arrived with an X-Forwarded-For header from an
	 * address that is not a trusted proxy.
	 */
	private function forwarded_request(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.77';
	}

	/**
	 * The last detections all share one address.
	 *
	 * @param int $count Number of detections.
	 */
	private function shared_detections( int $count = Shared_IP_Warning::SAMPLE_SIZE ): void {
		$this->insert_log_rows( 5 );
		$this->insert_log_rows( $count, array( 'user_ip' => self::SHARED_IP ) );
	}

	/**
	 * Runs the dismiss handler and returns how it ended.
	 *
	 * @return Throwable|null WPDieException, RedirectException or null.
	 */
	private function run_dismiss(): ?Throwable {
		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			},
			1,
			2
		);

		try {
			( new Shared_IP_Warning() )->dismiss();
		} catch ( WPDieException | RedirectException $e ) {
			return $e;
		}

		return null;
	}

	/**
	 * No detections, no warning.
	 */
	public function test_no_warning_without_detections(): void {
		$this->forwarded_request();

		$this->assertSame( '', $this->notices() );
	}

	/**
	 * The last detections share one address and this request was forwarded
	 * by an unknown proxy: the warning explains what to do.
	 */
	public function test_warns_when_detections_share_one_ip_behind_an_unknown_proxy(): void {
		$this->shared_detections();
		$this->forwarded_request();

		$html = $this->notices();

		$this->assertStringContainsString( 'Many different visitors appear to share one IP address', $html );
		$this->assertStringContainsString( 'Settings → Zero Spam → Settings → Trusted Proxy IPs', $html );
		$this->assertStringContainsString( 'The last 20 detections all came from', $html );
		$this->assertStringContainsString( 'aria-label="Dismiss the shared IP address warning"', $html );
		$this->assertStringNotContainsString( 'network administrator', $html );
		$this->assertStringContainsString( self::SHARED_IP, $html );
		$this->assertStringContainsString( 'subview=settings#trusted_proxies', $html );
		$this->assertStringContainsString( 'action=' . Shared_IP_Warning::ACTION, $html );
	}

	/**
	 * Without a forwarded header, one busy spammer is just one busy spammer.
	 */
	public function test_no_warning_when_the_request_is_not_forwarded(): void {
		$this->shared_detections();

		$this->assertSame( '', $this->notices() );
	}

	/**
	 * A forwarded header from a trusted proxy is already handled.
	 */
	public function test_no_warning_when_the_request_comes_from_a_trusted_proxy(): void {
		$this->set_setting( 'trusted_proxies', '192.0.2.0/24' );
		$this->set_request( '/', '192.0.2.10' );
		$this->shared_detections();
		$this->forwarded_request();

		$this->assertSame( '', $this->notices() );
	}

	/**
	 * Fewer detections than the sample size, or different addresses, are not enough.
	 */
	public function test_no_warning_for_a_short_or_mixed_sample(): void {
		$this->forwarded_request();

		$this->shared_detections( Shared_IP_Warning::SAMPLE_SIZE - 1 );
		$this->assertSame( '', $this->notices() );

		delete_transient( Shared_IP_Warning::TRANSIENT );
		$this->insert_log_rows( Shared_IP_Warning::SAMPLE_SIZE );
		$this->assertSame( '', $this->notices() );
	}

	/**
	 * Detections recorded with a trusted proxy's address mean the proxy's
	 * address, not the visitor's, is being recorded.
	 */
	public function test_warns_when_detections_come_from_a_trusted_proxy(): void {
		$this->insert_log_rows( 3 );
		$this->insert_log_rows( 2, array( 'user_ip' => '173.245.48.5' ) );

		$verdict = Shared_IP_Warning::get_verdict();

		$this->assertSame(
			array(
				'ip'     => '173.245.48.5',
				'reason' => 'trusted_proxy',
			),
			$verdict
		);

		$html = $this->notices();
		$this->assertStringContainsString( '173.245.48.5', $html );
		$this->assertStringContainsString( 'which belongs to a proxy Zero Spam already trusts, was logged as a visitor', $html );
		$this->assertStringContainsString( 'check its X-Forwarded-For setup', $html );
		$this->assertStringNotContainsString( 'Many different visitors', $html, 'Not the unknown-proxy text.' );
		$this->assertStringContainsString( 'Review trusted proxies', $html );
	}

	/**
	 * One detection from a proxy address is not enough.
	 */
	public function test_single_trusted_proxy_detection_is_ignored(): void {
		$this->insert_log_rows( 3 );
		$this->insert_log_rows( 1, array( 'user_ip' => '173.245.48.5' ) );

		$this->assertNull( Shared_IP_Warning::get_verdict() );
	}

	/**
	 * Detections logged before this version was installed are ignored: they
	 * hold proxy addresses (for example Cloudflare edges) by design, so a
	 * Cloudflare site gets no warning from its old rows.
	 */
	public function test_detections_from_before_the_update_are_ignored(): void {
		$this->insert_log_rows( 3, array( 'user_ip' => '173.245.48.5' ) );
		$this->shared_detections();
		$this->forwarded_request();

		update_option( Migrations::PROXY_DETECTION_SINCE_OPTION, wp_date( 'Y-m-d H:i:s', time() + MINUTE_IN_SECONDS ) );

		$this->assertNull( Shared_IP_Warning::get_verdict() );
		$this->assertSame( '', $this->notices() );
	}

	/**
	 * Only rows from the update on are counted when old and new rows mix.
	 */
	public function test_only_rows_since_the_update_are_sampled(): void {
		global $wpdb;

		$this->forwarded_request();
		$old_ids = $this->insert_log_rows( 2, array( 'user_ip' => '173.245.48.5' ) );
		$this->insert_log_rows( 3 );

		$since = wp_date( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );
		update_option( Migrations::PROXY_DETECTION_SINCE_OPTION, $since );
		foreach ( $old_ids as $id ) {
			$wpdb->update( $this->log_table(), array( 'date_recorded' => wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ), array( 'log_id' => $id ) );
		}

		$this->assertNull( Shared_IP_Warning::get_verdict(), 'The Cloudflare rows are older than the update.' );
	}

	/**
	 * Before the migration has recorded the start time, nothing is reported.
	 */
	public function test_no_warning_before_the_start_time_is_recorded(): void {
		delete_option( Migrations::PROXY_DETECTION_SINCE_OPTION );
		$this->shared_detections();
		$this->forwarded_request();

		$this->assertNull( Shared_IP_Warning::get_verdict() );
	}

	/**
	 * Only administrators see it.
	 */
	public function test_only_administrators_see_it(): void {
		$this->shared_detections();
		$this->forwarded_request();
		$this->as_role( 'editor' );

		$this->assertSame( '', $this->notices() );
	}

	/**
	 * It is only checked on the Zero Spam settings and dashboard screens.
	 */
	public function test_only_on_zero_spam_screens(): void {
		$this->shared_detections();
		$this->forwarded_request();

		set_current_screen( 'dashboard' );
		$this->assertSame( '', $this->notices() );
		$this->assertFalse( get_transient( Shared_IP_Warning::TRANSIENT ), 'Nothing is computed elsewhere.' );

		set_current_screen( 'dashboard_page_wordpress-zero-spam-dashboard' );
		$this->assertStringContainsString( self::SHARED_IP, $this->notices() );
	}

	/**
	 * The verdict is cached for an hour, so the log is read at most once an hour.
	 */
	public function test_verdict_is_cached_for_an_hour(): void {
		$this->shared_detections();
		$this->forwarded_request();

		$this->assertNotNull( Shared_IP_Warning::get_verdict() );
		$expires = (int) get_option( '_transient_timeout_' . Shared_IP_Warning::TRANSIENT );
		$this->assertGreaterThan( time() + HOUR_IN_SECONDS - MINUTE_IN_SECONDS, $expires, 'Cached for about an hour.' );
		$this->assertLessThanOrEqual( time() + HOUR_IN_SECONDS, $expires );

		$log_table = $this->log_table();
		$queries   = $this->capture_queries(
			static function () {
				Shared_IP_Warning::get_verdict();
			}
		);

		$this->assertSame( array(), preg_grep( '/' . preg_quote( $log_table, '/' ) . '/', $queries ) );
	}

	/**
	 * A cached "no warning" is cached too.
	 */
	public function test_no_warning_verdict_is_cached(): void {
		$this->assertNull( Shared_IP_Warning::get_verdict() );

		$this->shared_detections();
		$this->forwarded_request();

		$this->assertNull( Shared_IP_Warning::get_verdict() );
	}

	/**
	 * The zerospam_shared_ip_warning filter can hide or add the warning.
	 */
	public function test_filter_can_hide_or_force_the_warning(): void {
		$this->shared_detections();
		$this->forwarded_request();

		add_filter( 'zerospam_shared_ip_warning', '__return_false' );
		$this->assertSame( '', $this->notices() );
		remove_filter( 'zerospam_shared_ip_warning', '__return_false' );

		delete_transient( Shared_IP_Warning::TRANSIENT );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		add_filter(
			'zerospam_shared_ip_warning',
			static function () {
				return array(
					'ip'     => '198.51.100.99',
					'reason' => 'shared_ip',
				);
			}
		);
		$this->assertStringContainsString( '198.51.100.99', $this->notices() );
	}

	/**
	 * Dismissing hides the warning for that address only.
	 */
	public function test_dismiss_hides_the_warning_for_that_address(): void {
		$this->shared_detections();
		$this->forwarded_request();
		$_REQUEST['_wpnonce'] = wp_create_nonce( Shared_IP_Warning::ACTION );

		$result = $this->run_dismiss();

		$this->assertInstanceOf( RedirectException::class, $result );
		$this->assertSame( '', $this->notices() );

		delete_transient( Shared_IP_Warning::TRANSIENT );
		$this->insert_log_rows( Shared_IP_Warning::SAMPLE_SIZE, array( 'user_ip' => '198.51.100.51' ) );

		$this->assertStringContainsString( '198.51.100.51', $this->notices(), 'Another shared address is reported again.' );
	}

	/**
	 * Dismissing needs a valid nonce and an administrator.
	 */
	public function test_dismiss_requires_nonce_and_capability(): void {
		$this->shared_detections();
		$this->forwarded_request();

		$this->assertInstanceOf( WPDieException::class, $this->run_dismiss(), 'No nonce.' );

		$this->as_role( 'editor' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( Shared_IP_Warning::ACTION );
		$this->assertInstanceOf( WPDieException::class, $this->run_dismiss(), 'Not an administrator.' );

		$this->assertFalse( get_option( Shared_IP_Warning::DISMISSED_OPTION ) );
	}

	/**
	 * Saving the settings (for example adding a trusted proxy) clears the cached verdict.
	 */
	public function test_saving_settings_clears_the_cached_verdict(): void {
		new Shared_IP_Warning();
		set_transient( Shared_IP_Warning::TRANSIENT, array( 'ip' => self::SHARED_IP ), HOUR_IN_SECONDS );

		update_option( 'zero-spam-settings', array( 'trusted_proxies' => '192.0.2.10' ) );

		$this->assertFalse( get_transient( Shared_IP_Warning::TRANSIENT ) );
	}
}
