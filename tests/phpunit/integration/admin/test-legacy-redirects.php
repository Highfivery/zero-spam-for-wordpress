<?php
/**
 * Tests for the redirects from the 5.x admin screens.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Legacy_Redirects;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\TestCase;

/**
 * Every row of the build plan's redirect map (§3) and the inventory's
 * corrections: the 5.x screens' addresses go to the 6.0 page with their
 * arguments mapped, as a 302, for users who can open the target.
 *
 * @covers \ZeroSpam\Core\Admin\Legacy_Redirects
 */
class Test_Legacy_Redirects extends TestCase {

	/**
	 * The plugin's own module sections are registered (they hook `init`).
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$this->as_role( 'administrator' );
	}

	/**
	 * Forgets the simulated request.
	 */
	public function tear_down(): void {
		$_GET     = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Site rows: page, arguments, expected path below admin_url().
	 *
	 * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
	 */
	public static function data_site_rows(): array {
		$dashboard = Legacy_Redirects::DASHBOARD;
		$settings  = Legacy_Redirects::SETTINGS;

		return array(
			'dashboard'                  => array( $dashboard, array(), 'admin.php?page=zero-spam-activity&tab=reports' ),
			'dashboard reports'          => array( $dashboard, array( 'subview' => 'reports' ), 'admin.php?page=zero-spam-activity&tab=reports' ),
			'dashboard unknown subview'  => array( $dashboard, array( 'subview' => 'nope' ), 'admin.php?page=zero-spam-activity&tab=reports' ),
			'log'                        => array( $dashboard, array( 'subview' => 'log' ), 'admin.php?page=zero-spam-activity' ),
			'log filters'                => array(
				$dashboard,
				array(
					'subview' => 'log',
					'type'    => 'comment',
					'country' => 'de',
					's'       => '2001:DB8::1',
					'paged'   => '3',
				),
				'admin.php?page=zero-spam-activity&type=comment&country=DE&ip=2001%3ADB8%3A%3A1&paged=3',
			),
			'log first page'             => array(
				$dashboard,
				array(
					'subview' => 'log',
					'paged'   => '1',
				),
				'admin.php?page=zero-spam-activity',
			),
			'log bad country'            => array(
				$dashboard,
				array(
					'subview' => 'log',
					'country' => 'Germany',
				),
				'admin.php?page=zero-spam-activity',
			),
			'log sort by ip asc'         => array(
				$dashboard,
				array(
					'subview' => 'log',
					'orderby' => 'user_ip',
					'order'   => 'asc',
				),
				'admin.php?page=zero-spam-activity&sort=ip%3Aasc',
			),
			'log sort by type desc'      => array(
				$dashboard,
				array(
					'subview' => 'log',
					'orderby' => 'log_type',
				),
				'admin.php?page=zero-spam-activity&sort=type%3Adesc',
			),
			'log oldest first'           => array(
				$dashboard,
				array(
					'subview' => 'log',
					'order'   => 'asc',
				),
				'admin.php?page=zero-spam-activity&sort=date%3Aasc',
			),
			'log default sort'           => array(
				$dashboard,
				array(
					'subview' => 'log',
					'orderby' => 'date_recorded',
					'order'   => 'desc',
				),
				'admin.php?page=zero-spam-activity',
			),
			'log country and region'     => array(
				$dashboard,
				array(
					'subview' => 'log',
					'orderby' => 'region',
				),
				'admin.php?page=zero-spam-activity&sort=region%3Adesc',
			),
			'log unknown sort'           => array(
				$dashboard,
				array(
					'subview' => 'log',
					'orderby' => 'evil` --',
				),
				'admin.php?page=zero-spam-activity',
			),
			'blocked ips'                => array( $dashboard, array( 'subview' => 'blocked-ips' ), 'admin.php?page=zero-spam-rules&tab=ips' ),
			'blocked ips search dropped' => array(
				$dashboard,
				array(
					'subview' => 'blocked-ips',
					's'       => '192.0.2.1',
				),
				'admin.php?page=zero-spam-rules&tab=ips',
			),
			'blocked locations'          => array( $dashboard, array( 'subview' => 'blocked-locations' ), 'admin.php?page=zero-spam-rules&tab=locations' ),
			'settings'                   => array( $settings, array(), 'admin.php?page=zero-spam-settings' ),
			'settings settings'          => array( $settings, array( 'subview' => 'settings' ), 'admin.php?page=zero-spam-settings' ),
			'documentation'              => array( $settings, array( 'subview' => 'documentation' ), 'admin.php?page=zero-spam-help' ),
			'api monitoring'             => array( $settings, array( 'subview' => 'api-monitoring' ), 'admin.php?page=zero-spam-settings&tab=api' ),
			'debug'                      => array( $settings, array( 'subview' => 'debug' ), 'admin.php?page=zero-spam-settings&tab=debug' ),
			'export'                     => array( $settings, array( 'subview' => 'export' ), 'admin.php?page=zero-spam-settings&tab=io' ),
			'errors'                     => array( $settings, array( 'subview' => 'errors' ), 'admin.php?page=zero-spam-settings&tab=io' ),
			'google (removed)'           => array( $settings, array( 'subview' => 'google' ), 'admin.php?page=zero-spam-protection&tab=intelligence' ),
			'comments'                   => array( $settings, array( 'subview' => 'comments' ), 'admin.php?page=zero-spam-protection&tab=detection&card=comments' ),
			'registration'               => array( $settings, array( 'subview' => 'registration' ), 'admin.php?page=zero-spam-protection&tab=detection&card=registration' ),
			'login'                      => array( $settings, array( 'subview' => 'login' ), 'admin.php?page=zero-spam-protection&tab=detection&card=login' ),
			'davidwalsh'                 => array( $settings, array( 'subview' => 'davidwalsh' ), 'admin.php?page=zero-spam-protection&tab=detection&card=davidwalsh' ),
			'security'                   => array( $settings, array( 'subview' => 'security' ), 'admin.php?page=zero-spam-protection&tab=detection&card=security' ),
			'zerospam'                   => array( $settings, array( 'subview' => 'zerospam' ), 'admin.php?page=zero-spam-protection&tab=intelligence&card=zerospam' ),
			'stop forum spam'            => array( $settings, array( 'subview' => 'stop_forum_spam' ), 'admin.php?page=zero-spam-protection&tab=intelligence&card=stop_forum_spam' ),
			'project honeypot'           => array( $settings, array( 'subview' => 'project_honeypot' ), 'admin.php?page=zero-spam-protection&tab=intelligence&card=project_honeypot' ),
			'ipinfo'                     => array( $settings, array( 'subview' => 'ipinfo' ), 'admin.php?page=zero-spam-protection&tab=intelligence&card=ipinfo' ),
			'ipstack'                    => array( $settings, array( 'subview' => 'ipstack' ), 'admin.php?page=zero-spam-protection&tab=intelligence&card=ipstack' ),
			'ipbase'                     => array( $settings, array( 'subview' => 'ipbase' ), 'admin.php?page=zero-spam-protection&tab=intelligence&card=ipbase' ),
			'unknown subview'            => array( $settings, array( 'subview' => 'nothing-here' ), 'admin.php?page=zero-spam-settings' ),
			'broken ip link'             => array( $settings, array( 'ip' => '198.51.100.7' ), 'admin.php?page=zero-spam-activity&ip=198.51.100.7' ),
			'one-shot args dropped'      => array(
				$settings,
				array(
					'subview'       => 'debug',
					'zerospam-msg'  => 'Done',
					'zerospam-type' => 'success',
					'zerospam-id'   => '4',
				),
				'admin.php?page=zero-spam-settings&tab=debug',
			),
		);
	}

	/**
	 * Each site row and its argument mapping.
	 *
	 * @dataProvider data_site_rows
	 *
	 * @param string                $page     Page slug.
	 * @param array<string, string> $args     Query arguments.
	 * @param string                $expected Path below admin_url().
	 */
	public function test_site_rows( string $page, array $args, string $expected ): void {
		$this->assertSame(
			array(
				'path'    => $expected,
				'network' => false,
			),
			Legacy_Redirects::target( $page, array( 'page' => $page ) + $args, false )
		);
	}

	/**
	 * A section of another plugin opens its card on Protection › Integrations.
	 */
	public function test_third_party_section_goes_to_integrations(): void {
		add_filter(
			'zerospam_setting_sections',
			static function ( $sections ) {
				$sections['acme_forms'] = array( 'title' => 'Acme Forms' );

				return $sections;
			}
		);
		add_filter(
			'zerospam_settings',
			static function ( $settings ) {
				$settings['acme_forms_enabled'] = array(
					'title'   => 'Protect Acme Forms',
					'section' => 'acme_forms',
					'module'  => 'acme_forms',
					'type'    => 'checkbox',
					'options' => array( 'enabled' => false ),
					'value'   => false,
				);

				return $settings;
			}
		);
		self::reset_static_caches();

		$this->assertSame( 'admin.php?page=zero-spam-protection&tab=integrations&card=acme_forms', Legacy_Redirects::target( Legacy_Redirects::SETTINGS, array( 'subview' => 'acme_forms' ), false )['path'] );
	}

	/**
	 * Pages that are not 5.x screens, and network screens asked for outside the network admin, are left alone.
	 */
	public function test_other_pages_are_left_alone(): void {
		$this->assertNull( Legacy_Redirects::target( 'zero-spam-settings', array(), false ) );
		$this->assertNull( Legacy_Redirects::target( '', array(), false ) );
		$this->assertNull( Legacy_Redirects::target( Legacy_Redirects::NETWORK_SETTINGS, array(), false ) );
		$this->assertNull( Legacy_Redirects::target( Legacy_Redirects::NETWORK_STATS, array(), false ) );
		$this->assertNull( Legacy_Redirects::target( Legacy_Redirects::SETTINGS, array(), true ), 'Site screens are not network screens.' );
	}

	/**
	 * Network rows: page, arguments, expected path below network_admin_url().
	 *
	 * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
	 */
	public static function data_network_rows(): array {
		$settings = Legacy_Redirects::NETWORK_SETTINGS;
		$stats    = Legacy_Redirects::NETWORK_STATS;
		$rows     = array(
			'network settings'           => array( $settings, array(), 'admin.php?page=zero-spam-network' ),
			'network unknown tab'        => array( $settings, array( 'tab' => 'nope' ), 'admin.php?page=zero-spam-network' ),
			'network import-export'      => array( $settings, array( 'tab' => 'import-export' ), 'admin.php?page=zero-spam-network&tab=io' ),
			'stats'                      => array( $stats, array(), 'admin.php?page=zero-spam-network&tab=stats' ),
			'stats today'                => array( $stats, array( 'period' => 'today' ), 'admin.php?page=zero-spam-network&tab=stats&period=today' ),
			'stats yesterday'            => array( $stats, array( 'period' => 'yesterday' ), 'admin.php?page=zero-spam-network&tab=stats&period=yesterday' ),
			'stats week'                 => array( $stats, array( 'period' => 'week' ), 'admin.php?page=zero-spam-network&tab=stats&period=7' ),
			'stats month (the default)'  => array( $stats, array( 'period' => 'month' ), 'admin.php?page=zero-spam-network&tab=stats' ),
			'stats custom dates dropped' => array(
				$stats,
				array(
					'period'     => 'custom',
					'start_date' => '2026-01-01',
					'end_date'   => '2026-01-31',
				),
				'admin.php?page=zero-spam-network&tab=stats',
			),
		);

		foreach ( array( 'overview', 'settings', 'templates', 'audit', 'comparison', 'notifications' ) as $tab ) {
			$rows[ "network {$tab}" ] = array( $settings, array( 'tab' => $tab ), 'admin.php?page=zero-spam-network&tab=' . $tab );
		}

		return $rows;
	}

	/**
	 * Each network row and its argument mapping (in the network admin).
	 *
	 * @dataProvider data_network_rows
	 *
	 * @param string                $page     Page slug.
	 * @param array<string, string> $args     Query arguments.
	 * @param string                $expected Path below network_admin_url().
	 */
	public function test_network_rows( string $page, array $args, string $expected ): void {
		$this->assertSame(
			array(
				'path'    => $expected,
				'network' => true,
			),
			Legacy_Redirects::target( $page, $args, true )
		);
	}

	/**
	 * Runs maybe_redirect() for a request and returns where it went, or null.
	 *
	 * @param array<string, string> $get Query arguments.
	 * @return RedirectException|null
	 */
	private function redirect( array $get ): ?RedirectException {
		$_GET     = $get;
		$_REQUEST = $get;

		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			},
			1,
			2
		);

		try {
			( new Legacy_Redirects() )->maybe_redirect();
		} catch ( RedirectException $e ) {
			return $e;
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		return null;
	}

	/**
	 * An administrator is sent on with a 302.
	 */
	public function test_redirects_an_administrator_with_a_302(): void {
		$redirect = $this->redirect(
			array(
				'page'    => Legacy_Redirects::DASHBOARD,
				'subview' => 'blocked-locations',
			)
		);

		$this->assertInstanceOf( RedirectException::class, $redirect );
		$this->assertSame( 302, $redirect->status );
		$this->assertSame( admin_url( 'admin.php?page=zero-spam-rules&tab=locations' ), $redirect->location );
	}

	/**
	 * A user who cannot open the target is not redirected (WordPress refuses the old address).
	 */
	public function test_editor_is_not_redirected(): void {
		$this->as_role( 'editor' );

		$this->assertNull( $this->redirect( array( 'page' => Legacy_Redirects::SETTINGS ) ) );
	}

	/**
	 * Other pages are not redirected.
	 */
	public function test_other_pages_are_not_redirected(): void {
		$this->assertNull( $this->redirect( array( 'page' => 'zero-spam-settings' ) ) );
		$this->assertNull( $this->redirect( array() ) );
	}

	/**
	 * Both menu hooks run it first.
	 */
	public function test_runs_first_on_both_menu_hooks(): void {
		$redirects = new Legacy_Redirects();

		$this->assertSame( 0, has_action( 'admin_menu', array( $redirects, 'maybe_redirect' ) ) );
		$this->assertSame( 0, has_action( 'network_admin_menu', array( $redirects, 'maybe_redirect' ) ) );
	}

	/**
	 * The 5.x "click here to configure recommended settings" link still
	 * applies them with a valid nonce, then redirects.
	 */
	public function test_autoconfigure_link_with_a_valid_nonce(): void {
		$this->setExpectedDeprecated( 'ZeroSpam\Core\Admin\Legacy_Redirects::run_legacy_action' );
		delete_option( 'zerospam_configured' );
		delete_option( 'zero-spam-comments' );

		$redirect = $this->redirect(
			array(
				'page'            => Legacy_Redirects::SETTINGS,
				'zerospam-action' => 'autoconfigure',
				'zero-spam'       => wp_create_nonce( 'autoconfigure' ),
			)
		);

		$this->assertInstanceOf( RedirectException::class, $redirect );
		$this->assertSame( admin_url( 'admin.php?page=zero-spam-settings' ), $redirect->location );
		$this->assertSame( '1', (string) get_option( 'zerospam_configured' ) );
		$this->assertIsArray( get_option( 'zero-spam-comments' ) );
	}

	/**
	 * Without a valid nonce nothing is configured; the redirect still happens.
	 */
	public function test_autoconfigure_link_with_a_bad_nonce(): void {
		delete_option( 'zerospam_configured' );
		delete_option( 'zero-spam-comments' );

		$redirect = $this->redirect(
			array(
				'page'            => Legacy_Redirects::SETTINGS,
				'zerospam-action' => 'autoconfigure',
				'zero-spam'       => 'bad',
			)
		);

		$this->assertInstanceOf( RedirectException::class, $redirect );
		$this->assertFalse( get_option( 'zerospam_configured' ) );
		$this->assertFalse( get_option( 'zero-spam-comments' ) );
	}

	/**
	 * The two 5.x notice dismissals still work, then redirect.
	 */
	public function test_notice_dismissal_links(): void {
		$this->setExpectedDeprecated( 'ZeroSpam\Core\Admin\Legacy_Redirects::run_legacy_action' );
		update_option( Migrations::SETTINGS_REVIEW_NOTICE_OPTION, true );
		delete_option( 'zerospam_api_monitoring_notice_dismissed' );

		$this->redirect(
			array(
				'page'            => Legacy_Redirects::SETTINGS,
				'zerospam-action' => 'dismiss-settings-review-notice',
				'zero-spam'       => wp_create_nonce( 'dismiss-settings-review-notice' ),
			)
		);
		$this->assertFalse( get_option( Migrations::SETTINGS_REVIEW_NOTICE_OPTION ) );

		$this->redirect(
			array(
				'page'            => Legacy_Redirects::SETTINGS,
				'zerospam-action' => 'dismiss-api-monitoring-notice',
				'zero-spam'       => wp_create_nonce( 'dismiss-api-monitoring-notice' ),
			)
		);
		$this->assertTrue( (bool) get_option( 'zerospam_api_monitoring_notice_dismissed' ) );
	}

	/**
	 * Other 5.x actions only redirect: their links lived on the removed screens.
	 */
	public function test_other_actions_only_redirect(): void {
		$before = get_option( 'disallowed_keys' );

		$redirect = $this->redirect(
			array(
				'page'            => Legacy_Redirects::SETTINGS,
				'zerospam-action' => 'update-disallowed-words',
				'zero-spam'       => wp_create_nonce( 'update-disallowed-words' ),
			)
		);

		$this->assertInstanceOf( RedirectException::class, $redirect );
		$this->assertSame( $before, get_option( 'disallowed_keys' ) );
	}

	/**
	 * In the network admin, a super admin is sent to the network page.
	 *
	 * @group ms-required
	 */
	public function test_network_redirect_for_a_super_admin(): void {
		grant_super_admin( get_current_user_id() );
		set_current_screen( 'dashboard-network' );

		$redirect = $this->redirect(
			array(
				'page' => Legacy_Redirects::NETWORK_SETTINGS,
				'tab'  => 'import-export',
			)
		);

		$this->assertInstanceOf( RedirectException::class, $redirect );
		$this->assertSame( network_admin_url( 'admin.php?page=zero-spam-network&tab=io' ), $redirect->location );
	}

	/**
	 * A site administrator on a network address is not redirected: WordPress
	 * keeps refusing the page.
	 *
	 * @group ms-required
	 */
	public function test_site_admin_is_not_redirected_from_network_pages(): void {
		set_current_screen( 'dashboard-network' );

		$this->assertFalse( is_super_admin() );
		$this->assertNull( $this->redirect( array( 'page' => Legacy_Redirects::NETWORK_STATS ) ) );

		set_current_screen( 'dashboard' );
		$this->assertNull( $this->redirect( array( 'page' => Legacy_Redirects::NETWORK_SETTINGS ) ), 'Not from the site admin either.' );
	}
}
