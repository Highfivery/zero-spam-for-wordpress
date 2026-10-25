<?php
/**
 * Tests for the 6.0 admin app's network page.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin_App;
use ZeroSpam\Tests\TestCase;

/**
 * The network admin gets one page (`zero-spam-network`) for super admins
 * (manage_network_options); a subsite administrator can neither open it nor
 * see a link to it, and the network shell preloads only the user's own
 * preferences.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Admin\Admin_App::register_network_menu
 * @covers \ZeroSpam\Core\Admin\Admin_App::render_page
 * @covers \ZeroSpam\Core\Admin\Admin_App::bootstrap
 */
class Test_Admin_App_Network extends TestCase {

	/**
	 * Admin screen, empty menus, flag on.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$GLOBALS['menu']              = array();
		$GLOBALS['submenu']           = array();
		$GLOBALS['admin_page_hooks']  = array();
		$GLOBALS['_registered_pages'] = array();
		add_filter( 'zerospam_admin_v6', '__return_true' );
		add_filter( 'zerospam_news_feed_enabled', '__return_false' );
	}

	/**
	 * Removes the flag.
	 */
	public function tear_down(): void {
		remove_all_filters( 'zerospam_admin_v6' );
		$GLOBALS['menu']    = array();
		$GLOBALS['submenu'] = array();

		parent::tear_down();
	}

	/**
	 * A subsite administrator: administrator of a site, not a super admin.
	 */
	private function as_site_admin(): int {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		revoke_super_admin( $user );

		return $user;
	}

	/**
	 * One page in the network admin, for manage_network_options.
	 */
	public function test_network_page_needs_manage_network_options(): void {
		$user = $this->as_role( 'administrator' );
		grant_super_admin( $user );

		$app = new Admin_App();
		$app->register_network_menu();

		$this->assertArrayHasKey( Admin_App::MENU_POSITION, $GLOBALS['menu'] );
		$item = $GLOBALS['menu'][ Admin_App::MENU_POSITION ];
		$this->assertSame( 'manage_network_options', $item[1] );
		$this->assertSame( Admin_App::NETWORK_SLUG, $item[2] );
		$this->assertSame( 'network', $app->area_for_hook( 'toplevel_page_zero-spam-network' ) );

		$_GET['page'] = Admin_App::NETWORK_SLUG;
		ob_start();
		$app->render_page();
		$html = (string) ob_get_clean();
		unset( $_GET['page'] );

		$this->assertStringContainsString( 'data-area="network"', $html );
	}

	/**
	 * A subsite administrator cannot open the network page and gets no link to it.
	 */
	public function test_subsite_admin_cannot_open_the_network_page(): void {
		$this->as_site_admin();
		$this->assertFalse( current_user_can( 'manage_network_options' ) );

		$app = new Admin_App();
		$this->assertSame( '', $app->bootstrap( 'overview' )['urls']['network'] );

		$_GET['page'] = Admin_App::NETWORK_SLUG;
		$this->expectException( WPDieException::class );
		try {
			$app->render_page();
		} finally {
			unset( $_GET['page'] );
		}
	}

	/**
	 * A super admin gets the link to the network page from site pages.
	 */
	public function test_super_admin_gets_the_network_link(): void {
		$user = $this->as_role( 'administrator' );
		grant_super_admin( $user );

		$this->assertStringContainsString( 'network/admin.php?page=zero-spam-network', ( new Admin_App() )->bootstrap( 'overview' )['urls']['network'] );
	}

	/**
	 * The network shell preloads the user's preferences only (no site data).
	 */
	public function test_network_shell_preloads_no_site_data(): void {
		$this->assertSame( array( '/zero-spam/v1/ui/prefs' ), Admin_App::preload_paths( 'network' ) );
	}
}
