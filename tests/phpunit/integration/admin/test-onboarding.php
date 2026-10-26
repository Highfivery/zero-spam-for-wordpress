<?php
/**
 * Tests for first-run setup and "What's new in 6.0".
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin_App;
use ZeroSpam\Core\Admin\Onboarding;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\TestCase;

/**
 * First-run setup is flagged by the activation hook only on a fresh install
 * activated on its own (not network-wide, not in a bulk activation); the
 * Overview then shows the setup (and preloads what it needs), the legacy
 * welcome notice stays away, and the ui/onboarding route ends it. The
 * v6_whats_new migration flags upgraded sites only, and can run again.
 *
 * @covers \ZeroSpam\Core\Admin\Onboarding
 * @covers \ZeroSpam\Includes\Rest\Onboarding_Controller
 * @covers \ZeroSpam\Core\Admin\Admin_App::view_for
 * @covers \ZeroSpam\Core\Admin\Admin_App::preload_paths
 */
class Test_Onboarding extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/ui/onboarding';

	/**
	 * A fresh site: no settings, no flags.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'zero-spam-settings', 'zero-spam-comments', 'zero-spam-last-update', Onboarding::OPTION, Onboarding::STEPS_OPTION, Onboarding::WHATS_NEW_OPTION, 'zerospam_configured' ) as $option ) {
			delete_option( $option );
		}
		unset( $_REQUEST['action'], $_REQUEST['action2'], $_SERVER[ Onboarding::SETUP_HEADER ] );
	}

	/**
	 * Removes request and flag state.
	 */
	public function tear_down(): void {
		unset( $_REQUEST['action'], $_REQUEST['action2'], $_GET['page'] );
		remove_all_filters( 'zerospam_admin_v6' );
		remove_all_filters( 'zerospam_news_feed_enabled' );

		parent::tear_down();
	}

	/**
	 * A fresh install activated on its own gets the setup (through the real
	 * activation function).
	 */
	public function test_fresh_activation_flags_the_setup(): void {
		$this->as_role( 'administrator' );

		zerospam_plugin_activation( false );

		$this->assertTrue( Onboarding::is_pending() );
		$this->assertSame( 'pending', get_option( Onboarding::OPTION ) );
	}

	/**
	 * A site that has Zero Spam settings keeps them and gets no setup.
	 */
	public function test_activation_with_settings_does_not_flag(): void {
		update_option( 'zero-spam-settings', array( 'log_blocked_ips' => 'enabled' ) );

		Onboarding::on_activation( false );

		$this->assertFalse( Onboarding::is_pending() );
	}

	/**
	 * A network activation never flags the setup.
	 */
	public function test_network_activation_does_not_flag(): void {
		Onboarding::on_activation( true );

		$this->assertFalse( Onboarding::is_pending() );
	}

	/**
	 * A bulk activation (Bulk actions → Activate, either selector) does not
	 * flag the setup.
	 */
	public function test_bulk_activation_does_not_flag(): void {
		$_REQUEST['action'] = 'activate-selected';
		Onboarding::on_activation( false );
		$this->assertFalse( Onboarding::is_pending() );

		unset( $_REQUEST['action'] );
		$_REQUEST['action']  = '-1';
		$_REQUEST['action2'] = 'activate-selected';
		Onboarding::on_activation( false );
		$this->assertFalse( Onboarding::is_pending() );

		unset( $_REQUEST['action2'] );
		$_REQUEST['action'] = 'activate';
		Onboarding::on_activation( false );
		$this->assertTrue( Onboarding::is_pending(), 'A single activation from the Plugins screen does.' );
	}

	/**
	 * On a network, activating on one site flags that site only; a network
	 * activation flags none.
	 *
	 * @group ms-required
	 */
	public function test_site_activation_on_a_network_flags_that_site_only(): void {
		$site = self::factory()->blog->create();

		switch_to_blog( $site );
		delete_option( 'zero-spam-settings' );
		Onboarding::on_activation( false );
		$this->assertTrue( Onboarding::is_pending() );
		restore_current_blog();

		$this->assertFalse( Onboarding::is_pending(), 'The main site is not flagged.' );

		switch_to_blog( $site );
		delete_option( Onboarding::OPTION );
		Onboarding::on_activation( true );
		$this->assertFalse( Onboarding::is_pending() );
		restore_current_blog();
	}

	/**
	 * Upgraded sites show "What's new"; fresh installs and sites waiting
	 * for the setup do not; the migration can run again and keeps a value.
	 */
	public function test_whats_new_migration(): void {
		// Fresh install.
		$this->assertTrue( Onboarding::migrate_whats_new() );
		$this->assertSame( '', Onboarding::whats_new() );

		// Waiting for first-run setup.
		update_option( 'zero-spam-last-update', '5.7.14' );
		update_option( Onboarding::OPTION, Onboarding::PENDING );
		Onboarding::migrate_whats_new();
		$this->assertSame( '', Onboarding::whats_new() );

		// Upgraded from 5.x.
		delete_option( Onboarding::OPTION );
		Onboarding::migrate_whats_new();
		$this->assertSame( '6.0.0', Onboarding::whats_new() );

		// Again: nothing changes.
		Onboarding::migrate_whats_new();
		$this->assertSame( '6.0.0', get_option( Onboarding::WHATS_NEW_OPTION ) );

		// An upgraded site with settings but no version marker counts too.
		delete_option( Onboarding::WHATS_NEW_OPTION );
		delete_option( 'zero-spam-last-update' );
		update_option( 'zero-spam-settings', array( 'max_logs' => 5000 ) );
		Onboarding::migrate_whats_new();
		$this->assertSame( '6.0.0', Onboarding::whats_new() );
	}

	/**
	 * The migration is registered as v6_whats_new and runs through the
	 * migration runner once.
	 */
	public function test_whats_new_migration_runs_once_from_the_runner(): void {
		$migrations = new Migrations();
		$keys       = array_keys( ( new ReflectionMethod( Migrations::class, 'get_migrations' ) )->invoke( $migrations ) );
		$this->assertContains( 'v6_whats_new', $keys );

		update_option( Migrations::COMPLETED_OPTION, array_values( array_diff( $keys, array( 'v6_whats_new' ) ) ) );
		update_option( 'zero-spam-last-update', '5.7.14' );

		$migrations->run();

		$this->assertSame( '6.0.0', Onboarding::whats_new() );
		$this->assertContains( 'v6_whats_new', get_option( Migrations::COMPLETED_OPTION ) );
	}

	/**
	 * A stored value that is not a version is ignored.
	 */
	public function test_whats_new_ignores_a_malformed_value(): void {
		update_option( Onboarding::WHATS_NEW_OPTION, '<b>6</b>' );

		$this->assertSame( '', Onboarding::whats_new() );
	}

	/**
	 * The route: administrators only; GET gives the state, POST done ends
	 * the setup, anything else is refused.
	 */
	public function test_route(): void {
		update_option( Onboarding::OPTION, Onboarding::PENDING );
		update_option( Onboarding::WHATS_NEW_OPTION, '6.0.0' );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', self::ROUTE )->get_status() );
		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( 'POST', self::ROUTE, array( 'status' => 'done' ) )->get_status() );
		$this->assertTrue( Onboarding::is_pending(), 'An editor cannot end it.' );

		$this->as_role( 'administrator' );
		$this->assertSame(
			array(
				'pending'    => true,
				'configured' => false,
				'step'       => 0,
				'whats_new'  => '6.0.0',
			),
			$this->rest( 'GET', self::ROUTE )->get_data()
		);
		$this->assertSame( 400, $this->rest( 'POST', self::ROUTE, array( 'status' => 'later' ) )->get_status() );

		$response = $this->rest( 'POST', self::ROUTE, array( 'status' => 'done' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['pending'] );
		$this->assertFalse( Onboarding::is_pending() );
	}

	/**
	 * While the setup is pending the Overview shows it, preloads the shell
	 * and the settings schema (not the Overview's data), and tells the app;
	 * other areas are unchanged.
	 */
	public function test_overview_shows_the_setup_while_pending(): void {
		add_filter( 'zerospam_news_feed_enabled', '__return_false' );
		$this->as_role( 'administrator' );

		$this->assertSame( 'overview', Admin_App::view_for( 'overview' ) );

		update_option( Onboarding::OPTION, Onboarding::PENDING );
		$this->assertSame( 'onboarding', Admin_App::view_for( 'overview' ) );
		$this->assertSame( 'help', Admin_App::view_for( 'help' ) );

		$paths = Admin_App::preload_paths( 'onboarding' );
		$this->assertContains( '/zero-spam/v1/settings/schema', $paths );
		$this->assertContains( '/zero-spam/v1/protection/modules', $paths );
		$this->assertNotContains( '/zero-spam/v1/stats/timeseries', $paths );

		$boot = ( new Admin_App() )->bootstrap( 'overview' );
		$this->assertTrue( $boot['onboarding'] );
		$this->assertFalse( ( new Admin_App() )->bootstrap( 'help' )['onboarding'] );
	}

	/**
	 * The page shell prints the setup's own skeleton while it is pending.
	 */
	public function test_shell_prints_the_setup_skeleton(): void {
		add_filter( 'zerospam_admin_v6', '__return_true' );
		$this->as_role( 'administrator' );
		update_option( Onboarding::OPTION, Onboarding::PENDING );
		$_GET['page'] = 'zero-spam';

		ob_start();
		( new Admin_App() )->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'zs-skel-onb', $html );
		$this->assertStringNotContainsString( 'zs-skel-side', $html );
	}

	/**
	 * The bootstrap names the "What's new" release on upgraded sites only.
	 */
	public function test_bootstrap_names_the_whats_new_release(): void {
		$this->as_role( 'administrator' );
		$app = new Admin_App();

		$this->assertSame( '', $app->bootstrap( 'overview' )['whatsNew'] );

		update_option( Onboarding::WHATS_NEW_OPTION, '6.0.0' );
		$this->assertSame( '6.0.0', $app->bootstrap( 'overview' )['whatsNew'] );
		$this->assertSame( '', $app->bootstrap( 'network' )['whatsNew'] );
	}

	/**
	 * The legacy welcome notice is replaced by the setup when the 6.0 admin
	 * is on; with the 6.0 admin off it still shows.
	 */
	public function test_legacy_welcome_notice_gives_way_to_the_setup(): void {
		$this->admin_screen();
		$this->as_role( 'administrator' );
		update_option( Onboarding::OPTION, Onboarding::PENDING );
		$admin = new \ZeroSpam\Core\Admin\Admin();

		ob_start();
		$admin->admin_notices();
		$off = (string) ob_get_clean();
		$this->assertStringContainsString( 'Thanks for installing Zero Spam for WordPress!', $off );

		add_filter( 'zerospam_admin_v6', '__return_true' );
		ob_start();
		$admin->admin_notices();
		$on = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'Thanks for installing Zero Spam for WordPress!', $on );
	}

	/**
	 * A site is configured once settings were saved through any path, or
	 * when core settings are stored some other way (provisioning).
	 */
	public function test_configured_follows_the_site_settings(): void {
		$this->assertFalse( Onboarding::is_configured() );

		update_option( 'zero-spam-settings', array( 'log_blocked_ips' => 'enabled' ) );
		$this->assertTrue( Onboarding::is_configured(), 'Stored core settings count (provisioning).' );

		delete_option( 'zero-spam-settings' );
		update_option( 'zerospam_configured', 1 );
		$this->assertTrue( Onboarding::is_configured() );
	}

	/**
	 * Settings saved outside the setup (the Settings screen's PATCH)
	 * configure the site and end a pending setup; the setup's own saves
	 * (X-ZeroSpam-Setup) configure it and keep the setup going.
	 */
	public function test_saving_settings_outside_the_setup_ends_it(): void {
		$this->as_role( 'administrator' );
		update_option( Onboarding::OPTION, Onboarding::PENDING );

		$_SERVER[ Onboarding::SETUP_HEADER ] = '1';
		$this->assertSame( 200, $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'verify_comments' => true ) )->get_status() );
		$this->assertTrue( Onboarding::is_pending(), 'The setup\'s own save keeps it pending.' );
		$this->assertTrue( Onboarding::is_configured() );

		unset( $_SERVER[ Onboarding::SETUP_HEADER ] );
		$this->assertSame( 200, $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'verify_comments' => false ) )->get_status() );
		$this->assertFalse( Onboarding::is_pending(), 'A save from the Settings screen ends it.' );
	}

	/**
	 * WP-CLI (`wp zerospam set`, through Utilities::update_setting()) and the
	 * recommended settings (Settings::auto_configure(), also behind
	 * `wp zerospam autoconfigure`) end a pending setup too, unless the setup
	 * applied them.
	 */
	public function test_cli_and_autoconfigure_end_a_pending_setup(): void {
		$this->as_role( 'administrator' );
		update_option( Onboarding::OPTION, Onboarding::PENDING );

		$_SERVER[ Onboarding::SETUP_HEADER ] = '1';
		\ZeroSpam\Core\Settings::auto_configure();
		$this->assertTrue( Onboarding::is_pending() );
		unset( $_SERVER[ Onboarding::SETUP_HEADER ] );

		\ZeroSpam\Core\Utilities::update_setting( 'verify_comments', false );
		$this->assertFalse( Onboarding::is_pending() );

		update_option( Onboarding::OPTION, Onboarding::PENDING );
		\ZeroSpam\Core\Settings::auto_configure();
		$this->assertFalse( Onboarding::is_pending() );
	}

	/**
	 * Each administrator's step is kept per site, and the setup's end
	 * forgets them; the route saves and reports the step and whether the
	 * site is configured, and the page boot data carries both.
	 */
	public function test_steps_are_kept_per_user_and_end_with_the_setup(): void {
		update_option( Onboarding::OPTION, Onboarding::PENDING );
		$first = $this->as_role( 'administrator' );
		$this->assertSame( 200, $this->rest( 'POST', self::ROUTE, array( 'step' => 3 ) )->get_status() );
		$this->assertSame( 3, Onboarding::step() );
		$this->assertSame( 3, $this->rest( 'GET', self::ROUTE )->get_data()['step'] );

		$this->as_role( 'administrator' );
		$this->assertSame( 0, Onboarding::step(), 'Another administrator starts at the beginning.' );
		$boot = ( new Admin_App() )->bootstrap( 'overview' );
		$this->assertSame( 0, $boot['onboardingStep'] );
		$this->assertFalse( $boot['configured'] );

		wp_set_current_user( $first );
		$this->assertSame( 3, ( new Admin_App() )->bootstrap( 'overview' )['onboardingStep'] );

		$this->assertSame( 400, $this->rest( 'POST', self::ROUTE, array() )->get_status() );
		$this->rest( 'POST', self::ROUTE, array( 'status' => 'done' ) );
		$this->assertFalse( get_option( Onboarding::STEPS_OPTION ) );
		$this->assertSame( 0, Onboarding::step() );
	}

	/**
	 * On a network the step belongs to the site: an administrator at step 3
	 * on one site starts another site's setup at step 1.
	 *
	 * @group ms-required
	 */
	public function test_steps_are_kept_per_site_on_a_network(): void {
		$user = $this->as_role( 'administrator' );
		grant_super_admin( $user );
		$site = self::factory()->blog->create();

		update_option( Onboarding::OPTION, Onboarding::PENDING );
		Onboarding::save_step( 3 );

		switch_to_blog( $site );
		update_option( Onboarding::OPTION, Onboarding::PENDING );
		$this->assertSame( 0, Onboarding::step(), 'The other site starts at step 1.' );
		$this->assertFalse( Onboarding::is_configured(), 'Its own (absent) settings decide, not the other site\'s.' );
		Onboarding::complete();
		restore_current_blog();

		$this->assertSame( 3, Onboarding::step() );
		$this->assertTrue( Onboarding::is_pending(), 'Ending one site\'s setup leaves the other\'s.' );
		revoke_super_admin( $user );
	}
}
