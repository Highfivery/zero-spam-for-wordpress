<?php
/**
 * Tests for Utilities::admin_link() and Utilities::activity_url().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * One helper for links into the Zero Spam admin (the 6.0 pages; the 5.x
 * addresses redirect, see Legacy_Redirects).
 *
 * @covers \ZeroSpam\Core\Utilities::admin_link
 * @covers \ZeroSpam\Core\Utilities::activity_url
 */
class Test_Utilities_Admin_Links extends TestCase {

	/**
	 * View, network, path.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: string}>
	 */
	public static function data_links(): array {
		return array(
			'activity'         => array( 'activity', false, 'admin.php?page=zero-spam-activity' ),
			'api-monitoring'   => array( 'api-monitoring', false, 'admin.php?page=zero-spam-settings&tab=api' ),
			'license'          => array( 'license', false, 'admin.php?page=zero-spam-protection&tab=intelligence&card=zerospam' ),
			'no-lockout'       => array( 'no-lockout', false, 'admin.php?page=zero-spam-rules&tab=no-lockout' ),
			'allowlist'        => array( 'allowlist', false, 'admin.php?page=zero-spam-rules&tab=allowlist' ),
			'proxies'          => array( 'proxies', false, 'admin.php?page=zero-spam-settings&section=proxies' ),
			'network-activity' => array( 'network-activity', true, 'admin.php?page=zero-spam-network&tab=stats' ),
			'network-license'  => array( 'network-license', true, 'admin.php?page=zero-spam-network&tab=settings' ),
			'settings'         => array( 'settings', false, 'admin.php?page=zero-spam-settings' ),
			'network-settings' => array( 'network-settings', true, 'admin.php?page=zero-spam-network&tab=settings' ),
		);
	}

	/**
	 * Each view's address.
	 *
	 * @dataProvider data_links
	 *
	 * @param string $view    View.
	 * @param bool   $network Whether it is a network admin page.
	 * @param string $path    Path.
	 */
	public function test_links( string $view, bool $network, string $path ): void {
		$this->assertSame( $network ? network_admin_url( $path ) : admin_url( $path ), Utilities::admin_link( $view ) );
	}

	/**
	 * The activity URL is the activity view, site or network (activity_url()).
	 */
	public function test_activity_url(): void {
		$this->assertSame( Utilities::admin_link( 'activity' ), Utilities::activity_url() );
		$this->assertSame( Utilities::admin_link( 'network-activity' ), Utilities::activity_url( true ) );
	}

	/**
	 * An unknown view has no address.
	 */
	public function test_unknown_view(): void {
		$this->assertSame( '', Utilities::admin_link( 'nowhere' ) );
	}
}
