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
 * One helper for links into the Zero Spam admin: the legacy page while the
 * 6.0 admin is off, the 6.0 page (build plan redirect map) when it is on.
 *
 * @covers \ZeroSpam\Core\Utilities::admin_link
 * @covers \ZeroSpam\Core\Utilities::activity_url
 * @covers \ZeroSpam\Core\Utilities::admin_v6
 */
class Test_Utilities_Admin_Links extends TestCase {

	/**
	 * View, network, legacy path, 6.0 path.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: string, 3: string}>
	 */
	public static function data_links(): array {
		return array(
			'activity'         => array( 'activity', false, 'index.php?page=wordpress-zero-spam-dashboard&subview=log', 'admin.php?page=zero-spam-activity' ),
			'api-monitoring'   => array( 'api-monitoring', false, 'options-general.php?page=wordpress-zero-spam-settings&subview=api-monitoring', 'admin.php?page=zero-spam-settings&tab=api' ),
			'license'          => array( 'license', false, 'options-general.php?page=wordpress-zero-spam-settings&subview=zerospam', 'admin.php?page=zero-spam-protection&tab=intelligence&card=zerospam' ),
			'network-activity' => array( 'network-activity', true, 'settings.php?page=wordpress-zero-spam-network-stats', 'admin.php?page=zero-spam-network&tab=stats' ),
			'network-license'  => array( 'network-license', true, 'settings.php?page=zerospam-network-settings', 'admin.php?page=zero-spam-network&tab=settings' ),
			'settings'         => array( 'settings', false, 'options-general.php?page=wordpress-zero-spam-settings', 'admin.php?page=zero-spam-settings' ),
			'network-settings' => array( 'network-settings', true, 'settings.php?page=zerospam-network-settings', 'admin.php?page=zero-spam-network&tab=settings' ),
		);
	}

	/**
	 * Each view's legacy and 6.0 address.
	 *
	 * @dataProvider data_links
	 *
	 * @param string $view    View.
	 * @param bool   $network Whether it is a network admin page.
	 * @param string $legacy  Legacy path.
	 * @param string $v6      6.0 path.
	 */
	public function test_links_follow_the_flag( string $view, bool $network, string $legacy, string $v6 ): void {
		$url = $network ? 'network_admin_url' : 'admin_url';

		$this->assertSame( $url( $legacy ), Utilities::admin_link( $view ) );

		add_filter( 'zerospam_admin_v6', '__return_true' );
		$this->assertSame( $url( $v6 ), Utilities::admin_link( $view ) );
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
