<?php
/**
 * Tests for the administrator addresses that are never locked out.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Core\User;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\TestCase;

/**
 * Activation and the update migration save the administrator's public IP in
 * a separate list that only the blocking checks read: that IP is never
 * locked out, but everything sent from it is still checked and logged. The
 * IP Whitelist setting is never changed.
 *
 * @covers \ZeroSpam\Core\Admin_Safe_IPs
 * @covers \ZeroSpam\Core\Access::get_access
 */
class Test_Admin_Safe_IPs extends TestCase {

	/**
	 * A public address (no network request is made for it).
	 */
	const PUBLIC_IP = '8.8.8.8';

	/**
	 * Migrations completed before this feature.
	 */
	const EARLIER_MIGRATIONS = array( 'legacy_options_to_modules_v5', 'share_detection_cron_cleanup', 'davidwalsh_signed_tokens', 'geolocation_header_cache' );

	/**
	 * The stored IP Whitelist setting of the current site.
	 */
	private function whitelist(): string {
		$options = get_option( 'zero-spam-settings', array() );

		return is_array( $options ) && isset( $options['ip_whitelist'] ) ? (string) $options['ip_whitelist'] : '';
	}

	/**
	 * Simulates an administrator request from an IP.
	 *
	 * @param string $ip Administrator IP.
	 * @return int User ID.
	 */
	private function admin_from( string $ip ): int {
		$user_id = $this->as_role( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		$this->set_request( '/wp-admin/plugins.php', $ip );

		return $user_id;
	}

	/**
	 * Activation saves a public IP (not in the IP Whitelist) and flags the notice for that administrator.
	 */
	public function test_activation_saves_a_public_ip(): void {
		$user_id = $this->admin_from( self::PUBLIC_IP );

		zerospam_plugin_activation( false );

		$this->assertSame( array( self::PUBLIC_IP ), Admin_Safe_IPs::get_ips() );
		$this->assertSame( '', $this->whitelist(), 'The IP Whitelist setting was changed.' );
		$this->assertSame(
			array(
				'ip'      => self::PUBLIC_IP,
				'user_id' => $user_id,
			),
			get_option( Admin_Safe_IPs::NOTICE_OPTION )
		);
		$this->assertFalse( wp_load_alloptions()[ Admin_Safe_IPs::OPTION ] ?? false, 'The list is autoloaded.' );
	}

	/**
	 * Existing entries are kept; an IP already listed is not added twice and shows no notice.
	 */
	public function test_activation_keeps_entries_and_skips_duplicates(): void {
		update_option( Admin_Safe_IPs::OPTION, array( '1.1.1.1' ), false );
		$this->admin_from( self::PUBLIC_IP );

		zerospam_plugin_activation( false );
		delete_option( Admin_Safe_IPs::NOTICE_OPTION );
		zerospam_plugin_activation( false );

		$this->assertSame( array( '1.1.1.1', self::PUBLIC_IP ), Admin_Safe_IPs::get_ips() );
		$this->assertFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );
	}

	/**
	 * The list keeps the 10 most recent addresses, dropping the oldest.
	 */
	public function test_list_keeps_the_ten_most_recent(): void {
		$seed = array();
		for ( $i = 1; $i <= Admin_Safe_IPs::MAX_IPS; $i++ ) {
			$seed[] = "8.8.4.{$i}";
		}
		update_option( Admin_Safe_IPs::OPTION, $seed, false );
		$this->admin_from( self::PUBLIC_IP );

		zerospam_plugin_activation( false );

		$expected   = array_slice( $seed, 1 );
		$expected[] = self::PUBLIC_IP;
		$this->assertSame( 10, Admin_Safe_IPs::MAX_IPS );
		$this->assertSame( $expected, Admin_Safe_IPs::get_ips() );
	}

	/**
	 * Non-public addresses are never saved.
	 *
	 * @dataProvider data_non_public_ips
	 *
	 * @param string $ip Administrator IP.
	 */
	public function test_activation_skips_non_public_ips( string $ip ): void {
		$this->admin_from( $ip );

		zerospam_plugin_activation( false );

		$this->assertSame( array(), Admin_Safe_IPs::get_ips() );
		$this->assertFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );
	}

	/**
	 * Addresses that are not public (RFC 6890 non-global ranges included).
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_non_public_ips(): array {
		return array(
			'IPv4 loopback'      => array( '127.0.0.1' ),
			'10/8'               => array( '10.1.2.3' ),
			'172.16/12'          => array( '172.16.5.4' ),
			'192.168/16'         => array( '192.168.1.20' ),
			'link-local'         => array( '169.254.10.10' ),
			'CGNAT 100.64/10'    => array( '100.64.0.1' ),
			'TEST-NET-1'         => array( '192.0.2.10' ),
			'TEST-NET-3'         => array( '203.0.113.10' ),
			'IPv6 loopback'      => array( '::1' ),
			'IPv6 unique'        => array( 'fd00::1' ),
			'IPv6 link-local'    => array( 'fe80::1' ),
			'IPv6 documentation' => array( '2001:db8::1' ),
			'6to4 relay anycast' => array( '192.88.99.1' ),
			'IPv6 6to4'          => array( '2002:c058:6301::1' ),
		);
	}

	/**
	 * Behind a proxy Zero Spam does not trust yet, the IP it sees is the proxy's: never save it.
	 *
	 * @dataProvider data_proxy_headers
	 *
	 * @param string $header $_SERVER key of the forwarding header.
	 */
	public function test_activation_skips_an_untrusted_proxy( string $header ): void {
		$this->admin_from( self::PUBLIC_IP );
		$_SERVER[ $header ] = '1.1.1.1';

		zerospam_plugin_activation( false );

		$this->assertSame( array(), Admin_Safe_IPs::get_ips(), 'The proxy address was saved, which would exempt every visitor.' );
		$this->assertFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );
	}

	/**
	 * Forwarding headers that mean the request came through a proxy or CDN.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_proxy_headers(): array {
		return array(
			'X-Forwarded-For'     => array( 'HTTP_X_FORWARDED_FOR' ),
			'X-Real-IP'           => array( 'HTTP_X_REAL_IP' ),
			'True-Client-IP'      => array( 'HTTP_TRUE_CLIENT_IP' ),
			'CF-Connecting-IP'    => array( 'HTTP_CF_CONNECTING_IP' ),
			'Client-IP'           => array( 'HTTP_CLIENT_IP' ),
			'X-Forwarded'         => array( 'HTTP_X_FORWARDED' ),
			'X-Cluster-Client-IP' => array( 'HTTP_X_CLUSTER_CLIENT_IP' ),
			'Forwarded-For'       => array( 'HTTP_FORWARDED_FOR' ),
			'Forwarded'           => array( 'HTTP_FORWARDED' ),
			'Fastly-Client-IP'    => array( 'HTTP_FASTLY_CLIENT_IP' ),
			'X-Sucuri-ClientIP'   => array( 'HTTP_X_SUCURI_CLIENTIP' ),
			'Incap-Client-IP'     => array( 'HTTP_INCAP_CLIENT_IP' ),
			'X-Azure-ClientIP'    => array( 'HTTP_X_AZURE_CLIENTIP' ),
		);
	}

	/**
	 * The guard and User share one list of forwarding headers, and the tests cover all of it.
	 */
	public function test_proxy_headers_are_defined_once(): void {
		$expected = array_column( self::data_proxy_headers(), 0 );
		$actual   = User::PROXY_HEADERS;
		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Behind a trusted proxy (the IP comes from the header, not REMOTE_ADDR), the visitor IP is saved.
	 */
	public function test_activation_saves_the_client_behind_a_trusted_proxy(): void {
		$this->admin_from( '1.1.1.1' );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = self::PUBLIC_IP;
		add_filter(
			'zerospam_get_ip',
			static function () {
				return self::PUBLIC_IP;
			}
		);

		zerospam_plugin_activation( false );

		$this->assertSame( array( self::PUBLIC_IP ), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * The zerospam_admin_safe_ip_enabled filter turns it off.
	 */
	public function test_filter_turns_it_off(): void {
		$this->admin_from( self::PUBLIC_IP );
		add_filter( 'zerospam_admin_safe_ip_enabled', '__return_false' );

		zerospam_plugin_activation( false );

		$this->assertSame( array(), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * Only users who can manage options are saved.
	 */
	public function test_non_admins_are_not_saved(): void {
		$this->as_role( 'editor' );
		$this->set_request( '/wp-admin/', self::PUBLIC_IP );

		zerospam_plugin_activation( false );

		$this->assertSame( array(), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * The update migration waits for an administrator with a visitor IP, then runs once.
	 */
	public function test_update_migration_runs_once(): void {
		update_option( Migrations::COMPLETED_OPTION, self::EARLIER_MIGRATIONS );

		// A visitor's request leaves it pending.
		( new Migrations() )->run();
		$this->assertNotContains( 'admin_safe_ip', get_option( Migrations::COMPLETED_OPTION ) );

		// An administrator without a visitor IP (WP-CLI with --user) leaves it pending.
		$this->as_role( 'administrator' );
		unset( $_SERVER['REMOTE_ADDR'] );
		( new Migrations() )->run();
		$this->assertNotContains( 'admin_safe_ip', get_option( Migrations::COMPLETED_OPTION ) );

		// The first administrator request runs it.
		$this->admin_from( self::PUBLIC_IP );
		( new Migrations() )->run();
		$this->assertContains( 'admin_safe_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( array( self::PUBLIC_IP ), Admin_Safe_IPs::get_ips() );
		$this->assertSame( '', $this->whitelist() );

		// Removing the IP afterwards sticks.
		delete_option( Admin_Safe_IPs::OPTION );
		( new Migrations() )->run();
		$this->assertSame( array(), Admin_Safe_IPs::get_ips(), 'The migration ran a second time.' );
	}

	/**
	 * The update migration stays pending behind an untrusted proxy, and runs on a later plain request.
	 */
	public function test_update_migration_waits_behind_an_untrusted_proxy(): void {
		update_option( Migrations::COMPLETED_OPTION, self::EARLIER_MIGRATIONS );
		$this->admin_from( self::PUBLIC_IP );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

		( new Migrations() )->run();

		$this->assertNotContains( 'admin_safe_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( array(), Admin_Safe_IPs::get_ips() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		( new Migrations() )->run();

		$this->assertContains( 'admin_safe_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( array( self::PUBLIC_IP ), Admin_Safe_IPs::get_ips() );
	}

	/**
	 * A blocked IP on the list is not locked out; the same block still applies to other IPs.
	 */
	public function test_saved_ip_is_never_locked_out(): void {
		update_option( Admin_Safe_IPs::OPTION, array( self::PUBLIC_IP ), false );
		$this->set_setting( 'log_blocked_ips', 'enabled', 'settings' );
		$this->block( self::PUBLIC_IP );
		$this->block( '1.1.1.1' );

		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		$this->set_request( '/', self::PUBLIC_IP );
		$this->assertTrue( Access::process(), 'The saved IP skips every check, as if it were whitelisted.' );
		$access->run_block_check();
		$this->assertSame( 0, $this->count_log_rows(), 'A lockout was logged for the saved IP.' );

		$this->set_request( '/', '1.1.1.1' );
		$this->expectException( WPDieException::class );
		$access->run_block_check();
	}

	/**
	 * The list is only read when a block applies, not on every request.
	 */
	public function test_list_is_read_only_when_a_block_applies(): void {
		global $wpdb;

		update_option( Admin_Safe_IPs::OPTION, array( self::PUBLIC_IP ), false );
		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		$reads = function () use ( $access, $wpdb ): int {
			wp_cache_delete( Admin_Safe_IPs::OPTION, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$queries = $this->capture_queries(
				static function () use ( $access ) {
					$access->get_access();
				}
			);

			return count(
				array_filter(
					$queries,
					static fn ( string $query ): bool => false !== strpos( $query, $wpdb->options ) && false !== strpos( $query, Admin_Safe_IPs::OPTION )
				)
			);
		};

		$this->set_request( '/', self::PUBLIC_IP );
		$this->assertSame( 0, $reads(), 'The list was read on a request that is not blocked.' );

		$this->block( self::PUBLIC_IP );
		$this->assertSame( 1, $reads(), 'The list was not read when a block applied.' );
	}

	/**
	 * In .htaccess mode the generated deny rules leave saved addresses out, and are refreshed when the list changes.
	 */
	public function test_htaccess_rules_leave_saved_addresses_out(): void {
		$file = get_temp_dir() . 'zerospam-htaccess-' . wp_generate_password( 8, false ) . '.txt';
		file_put_contents( $file, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Throwaway test file.
		add_filter(
			'zerospam_htaccess_file',
			static function () use ( $file ) {
				return $file;
			}
		);
		$this->set_setting( 'block_method', 'htaccess_modern', 'settings' );
		$this->block( self::PUBLIC_IP );
		$this->block( '1.1.1.1' );

		try {
			// Saving the address rewrites the rules without it.
			$this->admin_from( self::PUBLIC_IP );
			zerospam_plugin_activation( false );
			$rules = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Throwaway test file.
			$this->assertStringContainsString( 'Require not ip 1.1.1.1', $rules );
			$this->assertStringNotContainsString( self::PUBLIC_IP, $rules, 'A saved administrator address is denied by the web server.' );
			$this->assertSame( array( 'Require not ip 1.1.1.1' ), array_values( preg_grep( '/^Require not ip/', \ZeroSpam\Core\Utilities::htaccess_rules( 'htaccess_modern' ) ) ) );

			// Removing it from the list puts it back.
			$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
			$_GET     = array(
				'ip'       => self::PUBLIC_IP,
				'_wpnonce' => wp_create_nonce( Admin_Safe_IPs::REMOVE_ACTION . '|' . self::PUBLIC_IP ),
			);
			$_REQUEST = $_GET;
			$this->catch_redirect(
				static function () {
					( new Admin_Safe_IPs() )->remove_ip();
				}
			);
			$rules = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Throwaway test file.
			$this->assertStringContainsString( self::PUBLIC_IP, $rules, 'Removing the address did not refresh the rules.' );
		} finally {
			wp_delete_file( $file );
			$_GET     = array();
			$_REQUEST = array();
		}
	}

	/**
	 * The Settings list escapes what is stored.
	 */
	public function test_settings_list_escapes_stored_values(): void {
		update_option( Admin_Safe_IPs::OPTION, array( '<script>alert(1)</script>' ), false );
		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );

		ob_start();
		( new Admin_Safe_IPs() )->settings_list( 'ip_whitelist' );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A spam comment from a saved IP is still detected and logged.
	 */
	public function test_spam_from_a_saved_ip_is_still_detected(): void {
		update_option( Admin_Safe_IPs::OPTION, array( self::PUBLIC_IP ), false );
		$this->set_setting( 'verify_comments', 'enabled', 'comments' );
		$this->set_setting( 'log_blocked_comments', 'enabled', 'comments' );
		$this->set_request( '/wp-comments-post.php', self::PUBLIC_IP );
		$_POST[ \ZeroSpam\Core\Utilities::get_honeypot() ] = 'i-am-a-bot';

		try {
			( new Comments() )->preprocess_comments(
				array(
					'comment_author'       => 'Bot',
					'comment_author_email' => 'bot@example.com',
					'comment_author_url'   => '',
					'comment_content'      => 'Spam from a saved address',
					'comment_agent'        => 'test',
				)
			);
			$this->fail( 'The spam comment was accepted.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'comment' ) ) );
		} finally {
			$_POST = array();
		}
	}

	/**
	 * The notice names the IP, says visitors are still checked, is shown to its administrator only, and can be dismissed.
	 */
	public function test_notice_is_shown_once_and_dismissible(): void {
		$user_id = $this->admin_from( self::PUBLIC_IP );
		zerospam_plugin_activation( false );
		$safe_ips = new Admin_Safe_IPs();

		ob_start();
		$safe_ips->admin_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( self::PUBLIC_IP, $html );
		$this->assertStringContainsString( "can't be locked out by Zero Spam from this address. Visitors from it are still checked.", $html );
		$this->assertStringContainsString( 'options-general.php?page=wordpress-zero-spam-settings', $html );
		$this->assertStringNotContainsString( 'is-dismissible', $html, 'The X only hides the notice until the next page; the Dismiss button is the one that lasts.' );

		// Another administrator does not see someone else's IP.
		$this->as_role( 'administrator' );
		ob_start();
		$safe_ips->admin_notice();
		$this->assertSame( '', ob_get_clean() );

		// Dismissing needs the nonce.
		wp_set_current_user( $user_id );
		$_REQUEST['_wpnonce'] = 'bad';
		try {
			$safe_ips->dismiss_notice();
			$this->fail( 'Dismissed without a valid nonce.' );
		} catch ( WPDieException $e ) {
			$this->assertNotFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );
		}

		$_REQUEST['_wpnonce'] = wp_create_nonce( Admin_Safe_IPs::DISMISS_ACTION );
		$this->catch_redirect(
			static function () use ( $safe_ips ) {
				$safe_ips->dismiss_notice();
			}
		);
		$this->assertFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );
		$this->assertSame( array( self::PUBLIC_IP ), Admin_Safe_IPs::get_ips(), 'Dismissing the notice removed the address.' );
	}

	/**
	 * The Settings page lists the addresses under the IP Whitelist, each with a Remove control that needs the capability and a nonce.
	 */
	public function test_settings_list_and_remove(): void {
		update_option( Admin_Safe_IPs::OPTION, array( self::PUBLIC_IP, '1.1.1.1' ), false );
		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		$safe_ips = new Admin_Safe_IPs();

		ob_start();
		do_action( 'zerospam_setting_field_after', 'ip_whitelist', array() );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Administrator addresses that can never be locked out', $html );
		$this->assertStringContainsString( '<code>' . self::PUBLIC_IP . '</code>', $html );
		$this->assertStringContainsString( 'aria-label="Remove ' . self::PUBLIC_IP, $html );

		ob_start();
		do_action( 'zerospam_setting_field_after', 'max_logs', array() );
		$this->assertSame( '', ob_get_clean(), 'The list is shown under another setting.' );

		// Without a valid nonce nothing is removed.
		$_REQUEST = array(
			'ip'       => self::PUBLIC_IP,
			'_wpnonce' => 'bad',
		);
		$_GET     = $_REQUEST;
		try {
			$safe_ips->remove_ip();
			$this->fail( 'Removed without a valid nonce.' );
		} catch ( WPDieException $e ) {
			$this->assertCount( 2, Admin_Safe_IPs::get_ips() );
		}

		// Without the capability nothing is removed.
		$this->as_role( 'editor' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( Admin_Safe_IPs::REMOVE_ACTION . '|' . self::PUBLIC_IP );
		$_GET                 = $_REQUEST;
		try {
			$safe_ips->remove_ip();
			$this->fail( 'An editor removed an address.' );
		} catch ( WPDieException $e ) {
			$this->assertCount( 2, Admin_Safe_IPs::get_ips() );
		}

		$this->as_role( 'administrator' );

		// A nonce made for another address does not remove this one.
		$_REQUEST['_wpnonce'] = wp_create_nonce( Admin_Safe_IPs::REMOVE_ACTION . '|1.1.1.1' );
		$_GET                 = $_REQUEST;
		try {
			$safe_ips->remove_ip();
			$this->fail( 'Removed with a nonce for another address.' );
		} catch ( WPDieException $e ) {
			$this->assertCount( 2, Admin_Safe_IPs::get_ips() );
		}

		$_REQUEST['_wpnonce'] = wp_create_nonce( Admin_Safe_IPs::REMOVE_ACTION . '|' . self::PUBLIC_IP );
		$_GET                 = $_REQUEST;
		$this->catch_redirect(
			static function () use ( $safe_ips ) {
				$safe_ips->remove_ip();
			}
		);
		$this->assertSame( array( '1.1.1.1' ), Admin_Safe_IPs::get_ips() );

		$_GET     = array();
		$_REQUEST = array();
	}

	/**
	 * Runs a callback that must end with a redirect.
	 *
	 * @param callable $callback Code to run.
	 */
	private function catch_redirect( callable $callback ): void {
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RedirectException( (string) $location, 302 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			}
		);

		try {
			$callback();
			$this->fail( 'No redirect.' );
		} catch ( RedirectException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
	}
}
