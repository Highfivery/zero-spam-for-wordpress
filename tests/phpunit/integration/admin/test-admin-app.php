<?php
/**
 * Tests for the 6.0 admin app: flag, menu, pages, assets and preloading.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin_App;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * The 6.0 admin is registered only behind its flag (ZEROSPAM_ADMIN_V6 /
 * `zerospam_admin_v6`); then a top-level "Zero Spam" menu below Comments
 * holds six pages for administrators, each printing the app shell and
 * loading the admin bundle with the first REST responses preloaded.
 *
 * @covers \ZeroSpam\Core\Admin\Admin_App
 * @covers \ZeroSpam\Core\Utilities::admin_v6
 * @covers \ZeroSpam\Core\Utilities::display_prefs
 */
class Test_Admin_App extends TestCase {

	/**
	 * Clean menu and script globals, the admin screen, and an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$this->reset_menus();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		wp_default_scripts( $GLOBALS['wp_scripts'] );
		wp_default_packages( $GLOBALS['wp_scripts'] );

		// The news would be downloaded while the page preloads it.
		add_filter( 'zerospam_news_feed_enabled', '__return_false' );
	}

	/**
	 * Restores the admin menu globals.
	 */
	public function tear_down(): void {
		$this->reset_menus();
		remove_all_filters( 'zerospam_admin_v6' );
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
	 * Turns the flag on with the filter.
	 */
	private function flag_on(): void {
		add_filter( 'zerospam_admin_v6', '__return_true' );
	}

	/**
	 * An app with its site pages registered.
	 */
	private function registered_app(): Admin_App {
		$app = new Admin_App();
		$app->register_menu();

		return $app;
	}

	/**
	 * The inline scripts printed after a handle.
	 *
	 * @param string $handle   Script handle.
	 * @param string $position before or after.
	 */
	private function inline( string $handle, string $position ): string {
		$data = wp_scripts()->get_data( $handle, $position );

		return is_array( $data ) ? implode( "\n", $data ) : '';
	}

	/**
	 * Paths in the page's preloading middleware.
	 *
	 * @return string[]
	 */
	private function preloaded_paths(): array {
		$inline = $this->inline( 'wp-api-fetch', 'after' );
		$this->assertSame( 1, preg_match( '/var zeroSpamPreload = (\{.*\}|\[\]); wp\.apiFetch\.use\( wp\.apiFetch\.createPreloadingMiddleware\( zeroSpamPreload \) \);/s', $inline, $match ), 'The preloading middleware is added after wp-api-fetch.' );
		$data = json_decode( $match[1], true );

		return is_array( $data ) ? array_keys( $data ) : array();
	}

	/**
	 * Off by default; the filter switches it on.
	 */
	public function test_flag_is_off_by_default_and_the_filter_turns_it_on(): void {
		$this->assertFalse( Utilities::admin_v6() );

		$this->flag_on();
		$this->assertTrue( Utilities::admin_v6() );
	}

	/**
	 * With the flag off nothing is registered or enqueued.
	 */
	public function test_flag_off_registers_nothing(): void {
		$this->as_role( 'administrator' );
		$app = $this->registered_app();
		$app->register_network_menu();

		$this->assertSame( array(), $app->hooks() );
		$this->assertArrayNotHasKey( 'zero-spam', $GLOBALS['admin_page_hooks'] );
		$this->assertSame( array(), $GLOBALS['menu'] );

		$app->enqueue( 'toplevel_page_zero-spam' );
		$this->assertFalse( wp_script_is( Admin_App::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( Admin_App::FONTS_HANDLE, 'enqueued' ) );
	}

	/**
	 * The legacy screens keep their own menu entries in this release.
	 */
	public function test_flag_on_keeps_the_legacy_screens(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );

		( new \ZeroSpam\Core\Admin\Settings() )->admin_menu();
		$this->registered_app();

		$legacy = preg_grep( '/_page_wordpress-zero-spam-settings$/', array_keys( $GLOBALS['_registered_pages'] ) );
		$this->assertNotEmpty( $legacy, 'The legacy settings page is still registered.' );
		$this->assertArrayHasKey( 'toplevel_page_zero-spam', $GLOBALS['_registered_pages'] );
	}

	/**
	 * A top-level menu at position 26 (below Comments) with the mark as its icon.
	 */
	public function test_top_level_menu_below_comments(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$this->registered_app();

		$this->assertArrayHasKey( Admin_App::MENU_POSITION, $GLOBALS['menu'] );
		$item = $GLOBALS['menu'][ Admin_App::MENU_POSITION ];
		$this->assertSame( 'Zero Spam', $item[0] );
		$this->assertSame( 'manage_options', $item[1] );
		$this->assertSame( 'zero-spam', $item[2] );
		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $item[6] );
		$this->assertStringContainsString( '#a7aaad', (string) base64_decode( substr( $item[6], strlen( 'data:image/svg+xml;base64,' ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads back the menu icon.
	}

	/**
	 * Six pages, in order, for administrators only.
	 */
	public function test_six_area_pages_need_manage_options(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$app = $this->registered_app();

		$pages = $GLOBALS['submenu']['zero-spam'];
		$this->assertSame( array_values( Admin_App::AREAS ), array_column( $pages, 2 ) );
		$this->assertSame( array( 'Overview', 'Activity', 'Rules', 'Protection', 'Settings', 'Help' ), array_column( $pages, 0 ) );
		$this->assertSame( array_fill( 0, 6, 'manage_options' ), array_column( $pages, 1 ) );

		$hooks = $app->hooks();
		$this->assertSame( array_keys( Admin_App::AREAS ), array_keys( $hooks ) );
		$this->assertSame( 'toplevel_page_zero-spam', $hooks['overview'] );
		$this->assertSame( 'zero-spam_page_zero-spam-activity', $hooks['activity'] );
		$this->assertSame( 'help', $app->area_for_hook( 'zero-spam_page_zero-spam-help' ) );
		$this->assertNull( $app->area_for_hook( 'settings_page_wordpress-zero-spam-settings' ) );
	}

	/**
	 * Users without manage_options cannot open the pages.
	 */
	public function test_editor_cannot_open_a_page(): void {
		$this->flag_on();
		$this->as_role( 'editor' );
		$_GET['page'] = 'zero-spam';

		$this->expectException( WPDieException::class );
		try {
			$this->registered_app()->render_page();
		} finally {
			unset( $_GET['page'] );
		}
	}

	/**
	 * The shell: the root with its area, theme and skeleton, the sprite and a
	 * no-JavaScript message.
	 */
	public function test_page_prints_the_shell(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$_GET['page'] = 'zero-spam-rules';

		ob_start();
		$this->registered_app()->render_page();
		$html = (string) ob_get_clean();
		unset( $_GET['page'] );

		$this->assertMatchesRegularExpression( '/<div\s+id="zerospam-app"\s+class="zs-app"\s+data-area="rules"\s+data-zs-theme="auto"/', $html );
		$this->assertStringContainsString( '<symbol id="zs-mark"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
		$this->assertStringContainsString( 'options-general.php?page=wordpress-zero-spam-settings', $html );
		$this->assertStringContainsString( 'class="zs-skel"', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringNotContainsString( 'data-zs-motion', $html );
	}

	/**
	 * The root carries the user's saved theme and motion preference.
	 */
	public function test_shell_uses_the_saved_display_preferences(): void {
		$this->flag_on();
		$user = $this->as_role( 'administrator' );
		update_user_meta(
			$user,
			'zerospam_ui',
			array(
				'theme'         => 'dark',
				'reduce_motion' => true,
			)
		);
		$_GET['page'] = 'zero-spam';

		ob_start();
		$this->registered_app()->render_page();
		$html = (string) ob_get_clean();
		unset( $_GET['page'] );

		$this->assertStringContainsString( 'data-zs-theme="dark"', $html );
		$this->assertStringContainsString( 'data-zs-motion="reduce"', $html );
	}

	/**
	 * "auto" follows the device on the plugin's own screens only; elsewhere
	 * (the dashboard widget) it renders light.
	 */
	public function test_display_prefs_auto_is_light_outside_plugin_screens(): void {
		$user = $this->as_role( 'administrator' );

		$this->assertSame( 'auto', Utilities::display_prefs( true )['theme'] );
		$this->assertSame( 'light', Utilities::display_prefs( false )['theme'] );

		update_user_meta( $user, 'zerospam_ui', array( 'theme' => 'dark' ) );
		$this->assertSame( 'dark', Utilities::display_prefs( false )['theme'] );

		update_user_meta( $user, 'zerospam_ui', array( 'theme' => '<script>' ) );
		$this->assertSame( 'auto', Utilities::display_prefs( true )['theme'] );
		$this->assertFalse( Utilities::display_prefs( true )['reduce_motion'] );
	}

	/**
	 * The bundle, its stylesheet and the fonts, with the dependencies and
	 * version from admin.asset.php and the zero-spam translations.
	 */
	public function test_assets_come_from_the_asset_file_with_translations(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$this->registered_app()->enqueue( 'zero-spam_page_zero-spam-help' );

		$asset  = require ZEROSPAM_PATH . 'assets/dist/admin.asset.php';
		$script = wp_scripts()->registered[ Admin_App::HANDLE ];

		$this->assertTrue( wp_script_is( Admin_App::HANDLE, 'enqueued' ) );
		$this->assertStringEndsWith( 'assets/dist/admin.js', $script->src );
		$this->assertSame( $asset['dependencies'], $script->deps );
		$this->assertSame( $asset['version'], $script->ver );
		$this->assertSame( 'zero-spam', $script->textdomain );
		$this->assertTrue( wp_style_is( Admin_App::HANDLE, 'enqueued' ) );
		$this->assertStringEndsWith( 'assets/dist/admin.css', wp_styles()->registered[ Admin_App::HANDLE ]->src );
		$this->assertSame( $asset['version'], wp_styles()->registered[ Admin_App::HANDLE ]->ver );
		$this->assertTrue( wp_style_is( Admin_App::FONTS_HANDLE, 'enqueued' ) );

		$boot = $this->inline( Admin_App::HANDLE, 'before' );
		$this->assertStringContainsString( 'var zeroSpamAdmin = ', $boot );
		$this->assertStringContainsString( '"area":"help"', $boot );
	}

	/**
	 * Nothing is loaded on other admin screens.
	 */
	public function test_no_assets_on_other_screens(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$app = $this->registered_app();

		foreach ( array( 'index.php', 'edit.php', 'settings_page_wordpress-zero-spam-settings', 'dashboard_page_wordpress-zero-spam-dashboard' ) as $hook ) {
			$app->enqueue( $hook );
		}

		$this->assertFalse( wp_script_is( Admin_App::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( Admin_App::FONTS_HANDLE, 'enqueued' ) );
	}

	/**
	 * The Overview preloads everything it reads on load, as the logged-in
	 * administrator, with real answers.
	 */
	public function test_overview_preloads_its_first_requests(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$this->insert_log_rows( 3 );
		$this->registered_app()->enqueue( 'toplevel_page_zero-spam' );

		$paths = $this->preloaded_paths();
		foreach ( array_merge( Admin_App::SHELL_PRELOADS, Admin_App::OVERVIEW_PRELOADS ) as $path ) {
			$this->assertContains( $path, $paths, "{$path} is preloaded on the Overview." );
		}
		$this->assertNotContains( Admin_App::NEWS_PRELOAD, $paths, 'The news is not preloaded while it is turned off.' );

		$inline = $this->inline( 'wp-api-fetch', 'after' );
		$this->assertStringContainsString( '"theme":"auto"', $inline, 'The answers are real: ui/prefs.' );
	}

	/**
	 * Other areas preload only what the shell needs.
	 */
	public function test_other_areas_preload_only_the_shell(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$this->registered_app()->enqueue( 'zero-spam_page_zero-spam-activity' );

		$paths = $this->preloaded_paths();
		$this->assertSame( Admin_App::SHELL_PRELOADS, array_values( array_intersect( $paths, Admin_App::SHELL_PRELOADS ) ) );
		foreach ( Admin_App::OVERVIEW_PRELOADS as $path ) {
			$this->assertNotContains( $path, $paths );
		}
	}

	/**
	 * The news is preloaded when it is on.
	 */
	public function test_news_is_preloaded_when_on(): void {
		remove_all_filters( 'zerospam_news_feed_enabled' );
		add_filter( 'zerospam_news_feed_enabled', '__return_true' );
		$this->mock_http( '#^https://www\.zerospam\.org/articles/feed/#', '<?xml version="1.0"?><rss version="2.0"><channel><title>Z</title><item><title>One</title><link>https://www.zerospam.org/one/</link></item></channel></rss>' );

		$this->assertContains( Admin_App::NEWS_PRELOAD, Admin_App::preload_paths( 'help' ) );
	}

	/**
	 * A user who cannot read a route gets no preloaded answer for it.
	 */
	public function test_preloads_are_scoped_to_the_user(): void {
		$this->as_role( 'editor' );

		$preloaded = array_reduce( Admin_App::preload_paths( 'overview' ), 'rest_preload_api_request', array() );

		$this->assertArrayNotHasKey( '/zero-spam/v1/license', $preloaded );
		$this->assertArrayNotHasKey( '/zero-spam/v1/stats/summary?range=7', $preloaded );
		// Their own preferences only.
		$this->assertArrayHasKey( '/zero-spam/v1/ui/prefs', $preloaded );
	}

	/**
	 * A saved license key never reaches the page: not in the bootstrap, not
	 * in the preloaded REST answers (they carry a masked hint only).
	 */
	public function test_license_key_never_appears_in_the_page(): void {
		$this->flag_on();
		$this->as_role( 'administrator' );
		$key = 'ZS-SECRET-LICENSE-9f3c7a';
		$this->set_setting( 'zerospam_license', $key, 'zerospam' );
		$this->mock_http( '#wp-json/v2/get-license#', array( 'license_key' => $key, 'queries_remaining' => 5 ) );

		$app = $this->registered_app();
		$app->enqueue( 'toplevel_page_zero-spam' );
		$_GET['page'] = 'zero-spam';
		ob_start();
		$app->render_page();
		$html = (string) ob_get_clean();
		unset( $_GET['page'] );

		$page = $html . $this->inline( Admin_App::HANDLE, 'before' ) . $this->inline( 'wp-api-fetch', 'after' );
		$this->assertStringContainsString( 'v1\\/license', $page, 'The license answer is preloaded (JSON-escaped path).' );
		$this->assertStringNotContainsString( $key, $page );
		$this->assertStringNotContainsString( 'SECRET-LICENSE', $page );
	}
}
