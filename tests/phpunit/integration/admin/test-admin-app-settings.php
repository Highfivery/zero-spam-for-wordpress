<?php
/**
 * Tests for the 6.0 Settings page: capabilities, preloads and bootstrap.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin_App;
use ZeroSpam\Tests\TestCase;

/**
 * The Settings page (admin.php?page=zero-spam-settings) needs
 * manage_options, preloads the settings schema and the data of the tab the
 * address opens (no request of its own on load), and tells the app whether
 * the rescue key is set. The legacy settings page stays registered.
 *
 * @covers \ZeroSpam\Core\Admin\Admin_App
 */
class Test_Admin_App_Settings extends TestCase {

	/**
	 * Hook suffix of the Settings page.
	 */
	const HOOK = 'zero-spam_page_zero-spam-settings';

	/**
	 * Fresh menus and script registries, the 6.0 admin on.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$this->reset_menus();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		wp_default_scripts( $GLOBALS['wp_scripts'] );
		wp_default_packages( $GLOBALS['wp_scripts'] );

		add_filter( 'zerospam_news_feed_enabled', '__return_false' );
		add_filter( 'zerospam_admin_v6', '__return_true' );
	}

	/**
	 * Undoes set_up().
	 */
	public function tear_down(): void {
		$this->reset_menus();
		remove_all_filters( 'zerospam_admin_v6' );
		remove_all_filters( 'zerospam_news_feed_enabled' );
		unset( $_GET['tab'], $_GET['section'], $_GET['page'], $_GET['card'] );
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;

		parent::tear_down();
	}

	/**
	 * Empties the admin menu globals.
	 */
	private function reset_menus(): void {
		$GLOBALS['menu']              = array();
		$GLOBALS['submenu']           = array();
		$GLOBALS['admin_page_hooks']  = array();
		$GLOBALS['_registered_pages'] = array();
		$GLOBALS['_parent_pages']     = array();
	}

	/**
	 * The app with its menu registered.
	 */
	private function registered_app(): Admin_App {
		$app = new Admin_App();
		$app->register_menu();

		return $app;
	}

	/**
	 * The preloaded answers printed after wp-api-fetch.
	 *
	 * @return array<string, mixed> Answers by path.
	 */
	private function preloaded(): array {
		$data   = wp_scripts()->get_data( 'wp-api-fetch', 'after' );
		$inline = is_array( $data ) ? implode( "\n", $data ) : '';
		$this->assertSame( 1, preg_match( '/var zeroSpamPreload = (\{.*\}|\[\]);/s', $inline, $match ) );
		$answers = json_decode( $match[1], true );

		return is_array( $answers ) ? $answers : array();
	}

	/**
	 * The bootstrap printed before the bundle.
	 *
	 * @return array<string, mixed>
	 */
	private function bootstrap_data(): array {
		$data   = wp_scripts()->get_data( Admin_App::HANDLE, 'before' );
		$inline = is_array( $data ) ? implode( "\n", $data ) : '';
		$this->assertSame( 1, preg_match( '/var zeroSpamAdmin = (\{.*\});/s', $inline, $match ) );

		return json_decode( $match[1], true );
	}

	/**
	 * The page needs manage_options (the legacy settings page staying
	 * registered is Test_Admin_App::test_flag_on_keeps_the_legacy_screens()).
	 */
	public function test_page_needs_manage_options(): void {
		$this->as_role( 'administrator' );
		$app = $this->registered_app();

		$this->assertSame( self::HOOK, $app->hooks()['settings'] );
		$settings = wp_list_filter( $GLOBALS['submenu']['zero-spam'], array( 2 => 'zero-spam-settings' ) );
		$this->assertSame( 'manage_options', array_values( $settings )[0][1] );

		$this->as_role( 'editor' );
		$_GET['page'] = 'zero-spam-settings';
		$this->expectException( WPDieException::class );
		$app->render_page();
	}

	/**
	 * On load the page has the shell's answers, the schema and the first
	 * tab's data: General needs nothing more.
	 */
	public function test_general_preloads_the_schema(): void {
		$this->as_role( 'administrator' );
		$this->registered_app()->enqueue( self::HOOK );

		$answers = $this->preloaded();

		foreach ( array_merge( Admin_App::SHELL_PRELOADS, array( '/zero-spam/v1/settings/schema' ) ) as $path ) {
			$this->assertArrayHasKey( $path, $answers, "{$path} is preloaded." );
		}
		$this->assertArrayNotHasKey( '/zero-spam/v1/rules/no-lockout', $answers );
		$this->assertArrayNotHasKey( '/zero-spam/v1/settings/error-log', $answers );

		$keys = array_column( $answers['/zero-spam/v1/settings/schema']['body']['settings'], 'key' );
		$this->assertContains( 'delete_data_on_uninstall', $keys, 'The answer is real.' );
	}

	/**
	 * Advanced preloads the no-lockout addresses; Import & errors the error
	 * log; a ?section= without a tab picks its card's tab.
	 */
	public function test_tabs_preload_their_own_data(): void {
		$this->as_role( 'administrator' );

		$this->assertSame( array( '/zero-spam/v1/settings/schema', '/zero-spam/v1/rules/no-lockout' ), Admin_App::settings_preloads( 'advanced' ) );
		$this->assertSame( array( '/zero-spam/v1/settings/schema', '/zero-spam/v1/settings/error-log' ), Admin_App::settings_preloads( 'io' ) );
		$this->assertSame( array( '/zero-spam/v1/settings/schema' ), Admin_App::settings_preloads( 'api' ) );
		$this->assertSame( array( '/zero-spam/v1/settings/schema' ), Admin_App::settings_preloads( 'nope' ) );
		$this->assertSame( Admin_App::settings_preloads( 'advanced' ), Admin_App::settings_preloads( '', 'proxies' ) );
		$this->assertSame( Admin_App::settings_preloads( 'api' ), Admin_App::settings_preloads( 'api', 'proxies' ), 'An explicit tab wins.' );

		// A setting key opens its group's tab (the schema), so its data is preloaded too.
		$this->assertSame( Admin_App::settings_preloads( 'advanced' ), Admin_App::settings_preloads( '', 'rescue_mode' ) );
		$this->assertSame( Admin_App::settings_preloads( 'advanced' ), Admin_App::settings_preloads( '', 'trusted_proxies' ) );
		$this->assertSame( 'data', Admin_App::settings_tab_of( 'max_logs' ) );
		$this->assertSame( 'data', Admin_App::settings_tab_of( 'delete_data_on_uninstall' ) );
		$this->assertSame( 'api', Admin_App::settings_tab_of( 'threshold_slow_response' ) );
		$this->assertSame( 'debug', Admin_App::settings_tab_of( 'debug_ip' ) );
		$this->assertSame( 'general', Admin_App::settings_tab_of( 'verify_comments' ), 'Another area\'s setting: General.' );
		$this->assertSame( 'general', Admin_App::settings_tab_of( 'nope' ) );

		$_GET['tab'] = 'io';
		$this->registered_app()->enqueue( self::HOOK );
		$answers = $this->preloaded();
		$this->assertArrayHasKey( '/zero-spam/v1/settings/error-log', $answers );
		$this->assertSame( 200, $answers['/zero-spam/v1/settings/error-log']['headers']['status'] ?? 200 );
	}

	/**
	 * A ?section= from the Overview (Trusted proxies) preloads the Advanced tab.
	 */
	public function test_section_link_preloads_its_tab(): void {
		$this->as_role( 'administrator' );
		$_GET['section'] = 'proxies';
		$this->registered_app()->enqueue( self::HOOK );

		$this->assertArrayHasKey( '/zero-spam/v1/rules/no-lockout', $this->preloaded() );
	}

	/**
	 * Every preloaded route answers the administrator without an error.
	 */
	public function test_every_preload_answers(): void {
		$this->as_role( 'administrator' );

		foreach ( array( 'general', 'advanced', 'io' ) as $tab ) {
			$answers = array_reduce( Admin_App::preload_paths( 'settings', array( 'tab' => $tab ) ), 'rest_preload_api_request', array() );
			foreach ( $answers as $path => $answer ) {
				$this->assertArrayNotHasKey( 'code', (array) $answer['body'], "{$path} answered with an error on {$tab}." );
			}
		}
	}

	/**
	 * The bootstrap says whether the rescue key is set, on this page only.
	 */
	public function test_bootstrap_has_the_rescue_key_state(): void {
		$this->as_role( 'administrator' );
		$this->registered_app()->enqueue( self::HOOK );

		// The test bootstrap defines ZEROSPAM_RESCUE_KEY.
		$this->assertSame( array( 'rescueKey' => true ), $this->bootstrap_data()['settings'] );
		$this->assertNull( ( new Admin_App() )->bootstrap( 'overview' )['settings'] );
	}

	/**
	 * The key counts as set only when it holds text.
	 */
	public function test_rescue_key_state(): void {
		$this->assertTrue( Admin_App::rescue_key_set() );
		$this->assertTrue( Admin_App::rescue_key_set( 'a-key' ) );
		$this->assertFalse( Admin_App::rescue_key_set( '' ) );
		$this->assertFalse( Admin_App::rescue_key_set( '   ' ) );
		$this->assertFalse( Admin_App::rescue_key_set( 123 ) );
	}
}
