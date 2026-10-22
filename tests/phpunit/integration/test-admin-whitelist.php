<?php
/**
 * Tests for whitelisting the administrator's IP on activation and update.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin_Whitelist;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\TestCase;

/**
 * Activation and the update migration add the administrator's public IP to
 * the IP Whitelist, never remove anything, and say so once.
 *
 * @covers \ZeroSpam\Core\Admin_Whitelist
 */
class Test_Admin_Whitelist extends TestCase {

	/**
	 * The stored IP Whitelist of the current site.
	 */
	private function whitelist(): string {
		$options = get_option( 'zero-spam-settings', array() );

		return is_array( $options ) && isset( $options['ip_whitelist'] ) ? (string) $options['ip_whitelist'] : '';
	}

	/**
	 * Simulates an administrator activating the plugin from an IP.
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
	 * Activation adds a public IP and flags the notice for that administrator.
	 */
	public function test_activation_whitelists_a_public_ip(): void {
		$user_id = $this->admin_from( self::DEFAULT_IP );

		zerospam_plugin_activation( false );

		$this->assertSame( self::DEFAULT_IP, $this->whitelist() );
		$this->assertSame(
			array(
				'ip'      => self::DEFAULT_IP,
				'user_id' => $user_id,
			),
			get_option( Admin_Whitelist::NOTICE_OPTION )
		);
	}

	/**
	 * Existing entries are kept, in order; the IP is appended.
	 */
	public function test_activation_never_removes_entries(): void {
		$this->set_setting( 'ip_whitelist', "192.0.2.1\r\n198.51.100.2\n", 'settings' );
		$this->admin_from( self::DEFAULT_IP );

		zerospam_plugin_activation( false );

		$this->assertSame( "192.0.2.1\r\n198.51.100.2\n" . self::DEFAULT_IP, $this->whitelist() );
	}

	/**
	 * An IP already on the list is not added twice and no notice is shown.
	 */
	public function test_activation_skips_an_ip_already_listed(): void {
		$this->set_setting( 'ip_whitelist', "192.0.2.1\n " . self::DEFAULT_IP . ' ', 'settings' );
		$this->admin_from( self::DEFAULT_IP );

		zerospam_plugin_activation( false );

		$this->assertSame( "192.0.2.1\n " . self::DEFAULT_IP . ' ', $this->whitelist() );
		$this->assertFalse( get_option( Admin_Whitelist::NOTICE_OPTION ) );
	}

	/**
	 * Private, loopback and link-local addresses are never added.
	 *
	 * @dataProvider data_non_public_ips
	 *
	 * @param string $ip Administrator IP.
	 */
	public function test_activation_skips_non_public_ips( string $ip ): void {
		$this->admin_from( $ip );

		zerospam_plugin_activation( false );

		$this->assertSame( '', $this->whitelist() );
		$this->assertFalse( get_option( Admin_Whitelist::NOTICE_OPTION ) );
	}

	/**
	 * Addresses that are not public.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_non_public_ips(): array {
		return array(
			'IPv4 loopback'   => array( '127.0.0.1' ),
			'10/8'            => array( '10.1.2.3' ),
			'172.16/12'       => array( '172.16.5.4' ),
			'192.168/16'      => array( '192.168.1.20' ),
			'link-local'      => array( '169.254.10.10' ),
			'IPv6 loopback'   => array( '::1' ),
			'IPv6 unique'     => array( 'fd00::1' ),
			'IPv6 link-local' => array( 'fe80::1' ),
		);
	}

	/**
	 * Behind a proxy Zero Spam does not trust yet, the IP it sees is the proxy's: never whitelist it.
	 *
	 * @dataProvider data_proxy_headers
	 *
	 * @param string $header $_SERVER key of the forwarding header.
	 */
	public function test_activation_skips_an_untrusted_proxy( string $header ): void {
		$this->admin_from( self::DEFAULT_IP );
		$_SERVER[ $header ] = '198.51.100.23';

		zerospam_plugin_activation( false );

		$this->assertSame( '', $this->whitelist(), 'The proxy address was whitelisted, which would exempt every visitor.' );
		$this->assertFalse( get_option( Admin_Whitelist::NOTICE_OPTION ) );
	}

	/**
	 * Forwarding headers that mean the request came through a proxy.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_proxy_headers(): array {
		return array(
			'X-Forwarded-For'  => array( 'HTTP_X_FORWARDED_FOR' ),
			'X-Real-IP'        => array( 'HTTP_X_REAL_IP' ),
			'True-Client-IP'   => array( 'HTTP_TRUE_CLIENT_IP' ),
			'CF-Connecting-IP' => array( 'HTTP_CF_CONNECTING_IP' ),
		);
	}

	/**
	 * Once the proxy is trusted (the visitor IP comes from the header, not REMOTE_ADDR), the visitor IP is whitelisted.
	 */
	public function test_activation_whitelists_the_client_behind_a_trusted_proxy(): void {
		$this->admin_from( '198.51.100.1' );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = self::DEFAULT_IP;
		add_filter(
			'zerospam_get_ip',
			static function () {
				return self::DEFAULT_IP;
			}
		);

		zerospam_plugin_activation( false );

		$this->assertSame( self::DEFAULT_IP, $this->whitelist() );
	}

	/**
	 * The update migration stays pending behind an untrusted proxy, and runs on a later plain request.
	 */
	public function test_update_migration_waits_behind_an_untrusted_proxy(): void {
		update_option( Migrations::COMPLETED_OPTION, array( 'legacy_options_to_modules_v5', 'share_detection_cron_cleanup', 'davidwalsh_signed_tokens', 'geolocation_header_cache' ) );
		$this->admin_from( self::DEFAULT_IP );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.23';

		( new Migrations() )->run();

		$this->assertNotContains( 'whitelist_admin_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( '', $this->whitelist() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		( new Migrations() )->run();

		$this->assertContains( 'whitelist_admin_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( self::DEFAULT_IP, $this->whitelist() );
	}

	/**
	 * The zerospam_whitelist_activating_admin filter turns it off.
	 */
	public function test_filter_turns_it_off(): void {
		$this->admin_from( self::DEFAULT_IP );
		add_filter( 'zerospam_whitelist_activating_admin', '__return_false' );

		zerospam_plugin_activation( false );

		$this->assertSame( '', $this->whitelist() );
	}

	/**
	 * Only users who can manage options get whitelisted.
	 */
	public function test_non_admins_are_not_whitelisted(): void {
		$this->as_role( 'editor' );
		$this->set_request( '/wp-admin/', self::DEFAULT_IP );

		zerospam_plugin_activation( false );

		$this->assertSame( '', $this->whitelist() );
	}

	/**
	 * The update migration waits for an administrator, then runs once.
	 */
	public function test_update_migration_runs_once(): void {
		update_option( Migrations::COMPLETED_OPTION, array( 'legacy_options_to_modules_v5', 'share_detection_cron_cleanup', 'davidwalsh_signed_tokens', 'geolocation_header_cache' ) );

		// A visitor's request leaves it pending.
		( new Migrations() )->run();
		$this->assertNotContains( 'whitelist_admin_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( '', $this->whitelist() );

		// An administrator without a visitor IP (WP-CLI with --user) leaves it pending.
		$this->as_role( 'administrator' );
		unset( $_SERVER['REMOTE_ADDR'] );
		( new Migrations() )->run();
		$this->assertNotContains( 'whitelist_admin_ip', get_option( Migrations::COMPLETED_OPTION ) );

		// The first administrator request runs it.
		$this->admin_from( self::DEFAULT_IP );
		( new Migrations() )->run();
		$this->assertContains( 'whitelist_admin_ip', get_option( Migrations::COMPLETED_OPTION ) );
		$this->assertSame( self::DEFAULT_IP, $this->whitelist() );

		// Removing the IP afterwards sticks.
		$this->set_setting( 'ip_whitelist', '', 'settings' );
		( new Migrations() )->run();
		$this->assertSame( '', $this->whitelist(), 'The migration ran a second time.' );
	}

	/**
	 * The notice names the IP, is shown to the administrator it was added for, and can be dismissed.
	 */
	public function test_notice_is_shown_once_and_dismissible(): void {
		$user_id = $this->admin_from( self::DEFAULT_IP );
		zerospam_plugin_activation( false );
		$whitelist = new Admin_Whitelist();

		ob_start();
		$whitelist->admin_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Your IP address (' . self::DEFAULT_IP . ') was added to Zero Spam', $html );
		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( 'options-general.php?page=wordpress-zero-spam-settings', $html );

		// Another administrator does not see someone else's IP.
		$this->as_role( 'administrator' );
		ob_start();
		$whitelist->admin_notice();
		$this->assertSame( '', ob_get_clean() );

		// Dismissing needs the nonce.
		wp_set_current_user( $user_id );
		$_REQUEST['_wpnonce'] = 'bad';
		try {
			$whitelist->dismiss_notice();
			$this->fail( 'Dismissed without a valid nonce.' );
		} catch ( WPDieException $e ) {
			$this->assertNotFalse( get_option( Admin_Whitelist::NOTICE_OPTION ) );
		}

		$_REQUEST['_wpnonce'] = wp_create_nonce( Admin_Whitelist::DISMISS_ACTION );
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RedirectException( (string) $location, 302 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			}
		);
		try {
			$whitelist->dismiss_notice();
			$this->fail( 'The dismiss handler did not redirect.' );
		} catch ( RedirectException $e ) {
			$this->assertFalse( get_option( Admin_Whitelist::NOTICE_OPTION ) );
		}

		ob_start();
		$whitelist->admin_notice();
		$this->assertSame( '', ob_get_clean() );
	}
}
