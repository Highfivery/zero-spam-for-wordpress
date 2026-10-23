<?php
/**
 * Tests for the Trusted Proxy IPs setting.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Settings as Settings_Page;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * The setting is registered next to the IP Whitelist, keeps only valid IP
 * addresses and CIDR ranges, and reports the lines it dropped.
 *
 * @covers \ZeroSpam\Core\Settings::get_settings
 * @covers \ZeroSpam\Core\Admin\Settings::settings_validation
 * @covers \ZeroSpam\Core\Utilities::parse_ip_ranges
 */
class Test_Trusted_Proxies_Setting extends TestCase {

	/**
	 * Registers the settings as admin_init does.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		delete_option( 'zero-spam-settings' );

		( new Settings_Page() )->register_settings();
	}

	/**
	 * Forgets the simulated form post and the settings errors.
	 */
	public function tear_down(): void {
		global $wp_settings_errors;

		unset( $_POST['option_page'] );
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset between tests.

		parent::tear_down();
	}

	/**
	 * The setting is a textarea on the Settings tab, right after the IP Whitelist.
	 */
	public function test_setting_is_registered_after_the_ip_whitelist(): void {
		$settings = Settings::get_settings();

		$this->assertArrayHasKey( 'trusted_proxies', $settings );
		$this->assertSame( 'textarea', $settings['trusted_proxies']['type'] );
		$this->assertSame( 'settings', $settings['trusted_proxies']['module'] );
		$this->assertSame( 'general', $settings['trusted_proxies']['section'] );
		$this->assertSame( 'Trusted Proxy IPs', $settings['trusted_proxies']['title'] );
		$this->assertStringContainsString( 'Cloudflare is recognised automatically', $settings['trusted_proxies']['desc'] );

		$keys = array_keys( $settings );
		$this->assertSame( array_search( 'ip_whitelist', $keys, true ) + 1, array_search( 'trusted_proxies', $keys, true ) );
	}

	/**
	 * The setting is part of the settings export (and so of `wp zerospam settings`).
	 */
	public function test_setting_is_exported_with_the_settings_module(): void {
		$this->set_setting( 'trusted_proxies', '192.0.2.10' );

		$modules = Settings::get_settings_by_module();

		$this->assertSame( '192.0.2.10', $modules['settings']['trusted_proxies']['value'] );
	}

	/**
	 * Saving keeps valid lines, drops the others and lists them in a notice.
	 */
	public function test_save_drops_invalid_lines_and_reports_them(): void {
		$_POST['option_page'] = 'zero-spam-settings';
		update_option(
			'zero-spam-settings',
			array( 'trusted_proxies' => " 192.0.2.10 \r\nnot-an-ip\n\n203.0.113.0/24\n2001:db8::/32\n10.0.0.0/33\n<b>x</b>" )
		);
		unset( $_POST['option_page'] );

		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertSame( "192.0.2.10\n203.0.113.0/24\n2001:db8::/32", $stored['trusted_proxies'] );

		$errors = wp_list_filter( get_settings_errors( 'zero-spam' ), array( 'code' => 'zerospam_trusted_proxies_invalid' ) );
		$this->assertCount( 1, $errors );
		$error = array_shift( $errors );
		$this->assertSame( 'warning', $error['type'] );
		$this->assertStringContainsString( 'not-an-ip', $error['message'] );
		$this->assertStringContainsString( '10.0.0.0/33', $error['message'] );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $error['message'], 'Dropped lines are escaped.' );
	}

	/**
	 * A clean list saves without a notice.
	 */
	public function test_valid_list_saves_without_notice(): void {
		update_option( 'zero-spam-settings', array( 'trusted_proxies' => "192.0.2.10\n2001:db8::1" ) );

		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertSame( "192.0.2.10\n2001:db8::1", $stored['trusted_proxies'] );
		$this->assertSame( array(), wp_list_filter( get_settings_errors( 'zero-spam' ), array( 'code' => 'zerospam_trusted_proxies_invalid' ) ) );
	}

	/**
	 * Lines and whether they are valid IP addresses or CIDR ranges.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_ranges(): array {
		return array(
			'IPv4'                 => array( '192.0.2.1', true ),
			'IPv4 CIDR'            => array( '192.0.2.0/24', true ),
			'IPv4 /32'             => array( '192.0.2.1/32', true ),
			'IPv4 prefix too long' => array( '192.0.2.0/33', false ),
			'IPv6'                 => array( '2001:db8::1', true ),
			'IPv6 CIDR'            => array( '2001:db8::/32', true ),
			'IPv6 /128'            => array( '2001:db8::1/128', true ),
			'IPv6 prefix too long' => array( '2001:db8::/129', false ),
			'negative prefix'      => array( '192.0.2.0/-1', false ),
			'empty prefix'         => array( '192.0.2.0/', false ),
			'hostname'             => array( 'cdn.example.com', false ),
			'out of range octet'   => array( '256.0.0.1', false ),
			'two slashes'          => array( '192.0.2.0/24/8', false ),
		);
	}

	/**
	 * Each line is accepted or rejected.
	 *
	 * @dataProvider data_ranges
	 *
	 * @param string $line  Line.
	 * @param bool   $valid Whether it is kept.
	 */
	public function test_parse_ip_ranges( string $line, bool $valid ): void {
		$parsed = Utilities::parse_ip_ranges( $line );

		$this->assertSame( $valid ? array( $line ) : array(), $parsed['valid'] );
		$this->assertSame( $valid ? array() : array( $line ), $parsed['invalid'] );
	}

	/**
	 * Lists may be separated by new lines, commas or spaces; duplicates are dropped.
	 */
	public function test_parse_ip_ranges_accepts_lists(): void {
		$parsed = Utilities::parse_ip_ranges( "192.0.2.1, 192.0.2.2\n192.0.2.1 2001:db8::/32" );

		$this->assertSame( array( '192.0.2.1', '192.0.2.2', '2001:db8::/32' ), $parsed['valid'] );
		$this->assertSame( array(), $parsed['invalid'] );
		$this->assertSame( array( '192.0.2.1' ), Utilities::parse_ip_ranges( array( '192.0.2.1', 42 ) )['valid'] );
		$this->assertSame( array(), Utilities::parse_ip_ranges( false )['valid'] );
	}

	/**
	 * Ranges and whether they are too broad to trust (shorter than /8 for
	 * IPv4, /32 for IPv6).
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_broad_ranges(): array {
		return array(
			'IPv4 everything' => array( '0.0.0.0/0', true ),
			'IPv4 /4'         => array( '10.0.0.0/4', true ),
			'IPv4 /7'         => array( '10.0.0.0/7', true ),
			'IPv4 /8'         => array( '10.0.0.0/8', false ),
			'IPv6 everything' => array( '::/0', true ),
			'IPv6 /31'        => array( '2001:db8::/31', true ),
			'IPv6 /32'        => array( '2001:db8::/32', false ),
			'single address'  => array( '192.0.2.1', false ),
		);
	}

	/**
	 * Too-broad ranges are neither valid nor invalid: they are reported apart.
	 *
	 * @dataProvider data_broad_ranges
	 *
	 * @param string $range     Range.
	 * @param bool   $too_broad Whether it is rejected as too broad.
	 */
	public function test_too_broad_ranges_are_rejected( string $range, bool $too_broad ): void {
		$parsed = Utilities::parse_ip_ranges( $range );

		$this->assertSame( $too_broad ? array( $range ) : array(), $parsed['too_broad'] );
		$this->assertSame( $too_broad ? array() : array( $range ), $parsed['valid'] );
		$this->assertSame( array(), $parsed['invalid'] );
	}

	/**
	 * Saving drops too-broad ranges and says why.
	 */
	public function test_save_drops_too_broad_ranges_with_the_reason(): void {
		update_option( 'zero-spam-settings', array( 'trusted_proxies' => "0.0.0.0/0\n::/0\n10.0.0.0/4\n10.0.0.0/8\nnope" ) );

		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertSame( '10.0.0.0/8', $stored['trusted_proxies'] );

		$errors = wp_list_filter( get_settings_errors( 'zero-spam' ), array( 'code' => 'zerospam_trusted_proxies_invalid' ) );
		$this->assertCount( 1, $errors );
		$message = array_shift( $errors )['message'];
		foreach ( array( '0.0.0.0/0', '::/0', '10.0.0.0/4' ) as $range ) {
			$this->assertStringContainsString( $range . ' (too broad: would trust every visitor&#039;s X-Forwarded-For)', $message );
		}
		$this->assertStringContainsString( 'nope', $message );
	}

	/**
	 * IPv4-mapped entries are stored as IPv4; a mapped range wider than the
	 * IPv4 space is rejected.
	 */
	public function test_ipv4_mapped_entries_are_rewritten_or_rejected(): void {
		$parsed = Utilities::parse_ip_ranges( "::ffff:192.0.2.0/120\n::FFFF:192.0.2.7\n192.0.2.7\n::ffff:0.0.0.0/64\n::ffff:0.0.0.0/96" );

		$this->assertSame( array( '192.0.2.0/24', '192.0.2.7' ), $parsed['valid'] );
		$this->assertSame( array( '::ffff:0.0.0.0/64' ), $parsed['invalid'] );
		$this->assertSame( array( '::ffff:0.0.0.0/96' ), $parsed['too_broad'], 'Rewritten to 0.0.0.0/0, which is too broad.' );

		update_option( 'zero-spam-settings', array( 'trusted_proxies' => "::ffff:192.0.2.0/120\n::ffff:0.0.0.0/64" ) );
		$stored = get_option( 'zero-spam-settings' );
		$this->assertIsArray( $stored );
		$this->assertSame( '192.0.2.0/24', $stored['trusted_proxies'] );
		$errors = wp_list_filter( get_settings_errors( 'zero-spam' ), array( 'code' => 'zerospam_trusted_proxies_invalid' ) );
		$this->assertStringContainsString( '::ffff:0.0.0.0/64', array_shift( $errors )['message'] );
	}
}
