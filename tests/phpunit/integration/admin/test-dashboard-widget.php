<?php
/**
 * Tests for the site dashboard widget.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Core\Admin\Sparkline;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\API_Usage_Alerts;
use ZeroSpam\Includes\Rest\Stats_Controller;
use ZeroSpam\Includes\Rest\UI_Prefs_Controller;
use ZeroSpam\Tests\TestCase;

/**
 * The 6.0 widget: rendered in PHP with the 30-day figures, a server-rendered
 * sparkline and the latest detections; the built widget-refresh assets only
 * on the dashboard and only when the widget shows; the 5.x AJAX refresh kept
 * but deprecated.
 *
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget
 * @covers \ZeroSpam\Core\Admin\Icons
 * @covers \ZeroSpam\Core\Utilities::activity_url
 * @covers \ZeroSpam\Core\Utilities::admin_v6
 * @covers \ZeroSpam\Core\Utilities::display_prefs
 */
class Test_Dashboard_Widget extends TestCase {

	/**
	 * Widget on, an administrator on the dashboard, fresh caches and asset queues.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		$this->admin_screen( 'dashboard' );
		$this->as_role( 'administrator' );
		$this->set_setting( 'widget_enabled', 'enabled' );
		self::reset_static_caches();
		Stats_Controller::flush_cache();
		self::set_static( Dashboard_Widget::class, 'ajax_warned', false );

		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'], $GLOBALS['wp_meta_boxes'] );
	}

	/**
	 * Restores the asset queues.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'], $GLOBALS['wp_meta_boxes'] );

		parent::tear_down();
	}

	/**
	 * The widget body's HTML.
	 */
	private function render(): string {
		ob_start();
		( new Dashboard_Widget() )->render_widget();

		return (string) ob_get_clean();
	}

	/**
	 * Registers the widget for the current user.
	 *
	 * @return array<string, mixed>|null The dashboard meta box, or null when not registered.
	 */
	private function registered(): ?array {
		( new Dashboard_Widget() )->register_widget();

		return $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'][ Dashboard_Widget::WIDGET_ID ] ?? null;
	}

	/**
	 * Detections only: the count, the sparkline and the latest three, newest first.
	 */
	public function test_site_widget_shows_detections_only(): void {
		$this->insert_log_rows( 5, array( 'log_type' => 'comment' ) );
		$this->insert_log_rows(
			2,
			array(
				'log_type' => 'rescue',
				'user_ip'  => '192.0.2.200',
			)
		);
		$this->insert_log_rows(
			1,
			array(
				'log_type' => 'registration',
				'user_ip'  => '203.0.113.9',
			)
		);

		$html = $this->render();

		$this->assertMatchesRegularExpression( '#data-zs-field="total">6<#', $html );
		$this->assertStringNotContainsString( '192.0.2.200', $html, 'Rescue entries are not detections.' );
		$this->assertSame( 3, substr_count( $html, '<li class="zs-w-item">' ) );
		$this->assertLessThan( strpos( $html, '198.51.100.5' ), strpos( $html, '203.0.113.9' ), 'The newest detection comes first.' );
		$this->assertStringContainsString( 'Last 7 days: 6, up 6 from the 7 days before.', $html );
	}

	/**
	 * The sparkline is drawn on the server from the daily totals, hidden from
	 * assistive technology, with a text alternative, whatever the time of day
	 * (rows pinned to midday of the site's today; the site clock is moved to
	 * just before and just after midnight).
	 *
	 * @dataProvider data_site_clock
	 *
	 * @param string|null $clock Site time to run at (H:i), or null for the real time.
	 */
	public function test_sparkline_is_rendered_from_the_daily_totals( ?string $clock ): void {
		if ( null !== $clock ) {
			$this->site_clock_at( $clock );
		}

		$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log's clock.
		for ( $i = 0; $i < 4; $i++ ) {
			$this->insert_log_rows( 1, array( 'date_recorded' => "{$today} 12:00:0{$i}" ) );
		}

		$html  = $this->render();
		$paths = Sparkline::paths( array_merge( array_fill( 0, 29, 0 ), array( 4 ) ) );

		$this->assertStringContainsString( 'd="' . esc_attr( $paths['line'] ) . '"', $html );
		$this->assertStringContainsString( 'd="' . esc_attr( $paths['area'] ) . '"', $html );
		$this->assertMatchesRegularExpression( '#<svg class="zs-w-spark-svg"[^>]*aria-hidden="true"#', $html );
		$this->assertStringContainsString( 'at most 4 on one day, 4 today.', $html );
	}

	/**
	 * Site times the sparkline test runs at.
	 *
	 * @return array<string, array{0: string|null}>
	 */
	public function data_site_clock(): array {
		return array(
			'now'                  => array( null ),
			'just before midnight' => array( '23:59' ),
			'just after midnight'  => array( '00:01' ),
		);
	}

	/**
	 * Moves the site's clock to a time of day through its UTC offset (no
	 * time zone name), so current_time() and wp_date() read that time.
	 *
	 * @param string $hhmm Site time (H:i).
	 */
	private function site_clock_at( string $hhmm ): void {
		list( $hours, $minutes ) = array_map( 'intval', explode( ':', $hhmm ) );

		// Whole minutes (wp_timezone() turns a UTC offset into +HH:MM): the
		// site clock then reads HH:MM and the current second.
		$wanted = $hours * 60 + $minutes;
		$shift  = ( ( $wanted - intdiv( time() % DAY_IN_SECONDS, MINUTE_IN_SECONDS ) ) % 1440 + 1440 ) % 1440;
		$shift  = $shift > 720 ? $shift - 1440 : $shift;

		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', $shift / 60 );

		// The clock keeps running: a second may tick into the next minute.
		$now = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
		$this->assertContains( $now, array( $wanted, ( $wanted + 1 ) % 1440 ), 'The site clock reads the wanted time.' );
	}

	/**
	 * Visitor data (type, IP address) is escaped.
	 */
	public function test_detection_data_is_escaped(): void {
		$this->insert_log_rows(
			1,
			array(
				'log_type' => '<b>bold</b>',
				'user_ip'  => '"><img src=x onerror=alert(1)>',
			)
		);

		$html = $this->render();

		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsString( '<b>bold</b>', $html );
		$this->assertStringContainsString( '&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $html );
		$this->assertStringContainsString( '&lt;b&gt;bold&lt;/b&gt;', $html );
	}

	/**
	 * The widget prints no script: the refresh code is the enqueued handle.
	 */
	public function test_widget_prints_no_script(): void {
		$this->insert_log_rows( 2 );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( 'canvas', $html, 'No Chart.js canvas.' );
	}

	/**
	 * An empty log shows the empty state and a flat sparkline.
	 */
	public function test_empty_log(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '#data-zs-field="total">0<#', $html );
		$this->assertMatchesRegularExpression( '#<p class="zs-w-empty" data-zs-field="empty">#', $html );
		$this->assertMatchesRegularExpression( '#<ul class="zs-w-list" data-zs-field="latest"[^>]* hidden>#', $html );
	}

	/**
	 * Administrators get Refresh, a polite live region and the activity link.
	 */
	public function test_administrator_gets_refresh_and_live_region(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'data-zs-refresh', $html );
		$this->assertMatchesRegularExpression( '#<p class="zs-w-status" role="status" aria-live="polite" data-zs-field="status"></p>#', $html );
		$this->assertStringContainsString( 'href="' . esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&subview=log' ) ) . '"', $html );
	}

	/**
	 * Other roles allowed to see the widget get neither Refresh nor the link.
	 */
	public function test_editor_gets_no_refresh_or_activity_link(): void {
		$this->set_setting( 'widget_visibility', array( 'administrator', 'editor' ) );
		$this->as_role( 'editor' );

		$html = $this->render();

		$this->assertStringContainsString( 'class="zs-widget"', $html );
		$this->assertStringNotContainsString( 'data-zs-refresh', $html );
		$this->assertStringNotContainsString( 'zs-w-link', $html );
	}

	/**
	 * The activity link follows the 6.0 admin flag (one helper).
	 */
	public function test_activity_link_follows_the_v6_flag(): void {
		$this->assertFalse( Utilities::admin_v6() );
		$this->assertSame( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&subview=log' ), Utilities::activity_url() );

		add_filter( 'zerospam_admin_v6', '__return_true' );

		$this->assertSame( admin_url( 'admin.php?page=zero-spam-activity' ), Utilities::activity_url() );
		$this->assertStringContainsString( esc_url( admin_url( 'admin.php?page=zero-spam-activity' ) ), $this->render() );
	}

	/**
	 * Theme and motion come from the user's ui/prefs. On the WordPress
	 * dashboard "auto" (or no preference) is light; dark only when chosen.
	 */
	public function test_theme_and_motion_follow_ui_prefs(): void {
		$html = $this->render();
		$this->assertStringContainsString( 'data-zs-theme="light"', $html );
		$this->assertStringNotContainsString( 'data-zs-motion', $html );

		update_user_meta( get_current_user_id(), UI_Prefs_Controller::META_KEY, array( 'theme' => 'auto' ) );
		$this->assertStringContainsString( 'data-zs-theme="light"', $this->render() );

		update_user_meta(
			get_current_user_id(),
			UI_Prefs_Controller::META_KEY,
			array(
				'theme'         => 'dark',
				'reduce_motion' => true,
			)
		);

		$html = $this->render();
		$this->assertStringContainsString( 'data-zs-theme="dark"', $html );
		$this->assertStringContainsString( 'data-zs-motion="reduce"', $html );
	}

	/**
	 * Basic protection is a quiet line, with no promotion, price or billing link.
	 */
	public function test_basic_level_has_no_promotion(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'Protection level: <b>Basic</b>', $html );
		foreach ( array( 'pricing', 'View Pricing', 'Unlock', 'account', 'Upgrade', 'review' ) as $promo ) {
			$this->assertStringNotContainsStringIgnoringCase( $promo, $html, "The widget shows \"{$promo}\"." );
		}
	}

	/**
	 * The postbox title carries the flat mark (hidden from assistive technology).
	 */
	public function test_title_has_the_mark_and_a_plain_screen_options_name(): void {
		$box = $this->registered();

		$this->assertNotNull( $box );
		$this->assertStringContainsString( '<span>Zero Spam overview</span>', $box['title'] );
		$this->assertMatchesRegularExpression( '#<svg [^>]*viewBox="0 0 20 19"[^>]*class="zs-w-mark"[^>]*width="24" height="23"[^>]*aria-hidden="true"#i', $box['title'] ); // wp_kses() lowercases viewBox; HTML parsers restore SVG attribute case.
		$this->assertStringContainsString( '<span>Zero Spam overview</span>', $box['args']['__widget_basename'] );
	}

	/**
	 * Who sees the widget, unchanged from 5.x.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool|null, 3: bool}>
	 */
	public static function data_visibility(): array {
		return array(
			'administrator by default'           => array( 'administrator', array(), null, true ),
			'editor not by default'              => array( 'editor', array(), null, false ),
			'editor when listed'                 => array( 'editor', array( 'widget_visibility' => array( 'administrator', 'editor' ) ), null, true ),
			'no roles hides it'                  => array( 'administrator', array( 'widget_visibility' => array() ), null, false ),
			'switched off hides it'              => array( 'administrator', array( 'widget_enabled' => false ), null, false ),
			'the filter can show it when off'    => array( 'subscriber', array( 'widget_enabled' => false ), true, true ),
			'the filter can hide it from admins' => array( 'administrator', array(), false, false ),
		);
	}

	/**
	 * Visibility rules and the zerospam_dashboard_widget_visible filter.
	 *
	 * @dataProvider data_visibility
	 *
	 * @param string               $role     Role of the user.
	 * @param array<string, mixed> $settings Settings to store.
	 * @param bool|null            $filter   Value of zerospam_dashboard_widget_visible, or null for none.
	 * @param bool                 $visible  Expected visibility.
	 */
	public function test_visibility( string $role, array $settings, ?bool $filter, bool $visible ): void {
		foreach ( $settings as $key => $value ) {
			$this->set_setting( $key, $value );
		}
		self::reset_static_caches();

		if ( null !== $filter ) {
			add_filter( 'zerospam_dashboard_widget_visible', $filter ? '__return_true' : '__return_false' );
		}

		$this->as_role( $role );

		$this->assertSame( $visible, null !== $this->registered() );
	}

	/**
	 * The built assets load on the dashboard, versioned by their .asset.php.
	 */
	public function test_assets_on_the_dashboard_use_the_asset_file(): void {
		$asset = require ZEROSPAM_PATH . 'assets/dist/widget-refresh.asset.php';

		( new Dashboard_Widget() )->enqueue_assets( 'index.php' );

		$this->assertTrue( wp_style_is( Dashboard_Widget::HANDLE ) );
		$this->assertTrue( wp_script_is( Dashboard_Widget::HANDLE ) );
		$this->assertSame( $asset['version'], wp_styles()->registered[ Dashboard_Widget::HANDLE ]->ver );
		$this->assertSame( $asset['version'], wp_scripts()->registered[ Dashboard_Widget::HANDLE ]->ver );
		$this->assertSame( $asset['dependencies'], wp_scripts()->registered[ Dashboard_Widget::HANDLE ]->deps );
		$this->assertStringEndsWith( 'assets/dist/widget-refresh.js', wp_scripts()->registered[ Dashboard_Widget::HANDLE ]->src );
		$this->assertStringEndsWith( 'assets/dist/widget-refresh.css', wp_styles()->registered[ Dashboard_Widget::HANDLE ]->src );
		$this->assertSame( 'replace', wp_styles()->get_data( Dashboard_Widget::HANDLE, 'rtl' ) );

		$config = (string) wp_scripts()->get_data( Dashboard_Widget::HANDLE, 'data' );
		$this->assertStringContainsString( 'var zerospamWidget = ', $config );
		$this->assertStringContainsString( '"refreshed":"Data refreshed."', $config );
		$this->assertStringContainsString( '"skip":["rescue"]', $config );

		foreach ( array( 'zerospam-chartjs', 'zerospam-dashboard-widget' ) as $legacy ) {
			$this->assertFalse( wp_script_is( $legacy, 'registered' ), "{$legacy} is still registered." );
			$this->assertFalse( wp_style_is( $legacy, 'registered' ), "{$legacy} is still registered." );
		}
	}

	/**
	 * Nothing loads on other admin pages, or when the widget is hidden.
	 */
	public function test_no_assets_elsewhere_or_when_hidden(): void {
		( new Dashboard_Widget() )->enqueue_assets( 'edit.php' );
		$this->assertFalse( wp_style_is( Dashboard_Widget::HANDLE, 'registered' ) );
		$this->assertFalse( wp_script_is( Dashboard_Widget::HANDLE, 'registered' ) );

		$this->as_role( 'editor' );
		( new Dashboard_Widget() )->enqueue_assets( 'index.php' );
		$this->assertFalse( wp_style_is( Dashboard_Widget::HANDLE, 'registered' ) );
	}

	/**
	 * A role that sees the widget without Refresh gets the styles only.
	 */
	public function test_styles_only_without_refresh(): void {
		$this->set_setting( 'widget_visibility', array( 'administrator', 'editor' ) );
		$this->as_role( 'editor' );

		( new Dashboard_Widget() )->enqueue_assets( 'index.php' );

		$this->assertTrue( wp_style_is( Dashboard_Widget::HANDLE ) );
		$this->assertFalse( wp_script_is( Dashboard_Widget::HANDLE, 'registered' ) );
	}

	/**
	 * The 5.x AJAX refresh still answers, and reports its deprecation once per request.
	 */
	public function test_deprecated_ajax_refresh_warns_once(): void {
		$this->setExpectedIncorrectUsage( Dashboard_Widget::class . '::ajax_refresh_data' );
		$this->insert_log_rows( 2 );

		$warnings = 0;
		add_action(
			'doing_it_wrong_run',
			static function () use ( &$warnings ): void {
				++$warnings;
			}
		);

		$widget  = new Dashboard_Widget();
		$request = array( 'nonce' => wp_create_nonce( 'zerospam_dashboard_refresh' ) );
		$this->ajax( array( $widget, 'ajax_refresh_data' ), $request );
		$answer = $this->ajax( array( $widget, 'ajax_refresh_data' ), $request );

		$this->assertSame( 1, $warnings );
		$this->assertTrue( $answer['success'] );
		$this->assertSame( 2, array_sum( $answer['data']['data']['days'] ) );
		$this->assertArrayHasKey( 'latest', $answer['data']['data'] );
	}

	/**
	 * The AJAX refresh needs manage_options (a valid nonce is not enough).
	 */
	public function test_deprecated_ajax_refresh_needs_manage_options(): void {
		$this->setExpectedIncorrectUsage( Dashboard_Widget::class . '::ajax_refresh_data' );
		$this->insert_log_rows( 2 );
		$this->as_role( 'editor' );

		$answer = $this->ajax(
			array( new Dashboard_Widget(), 'ajax_refresh_data' ),
			array( 'nonce' => wp_create_nonce( 'zerospam_dashboard_refresh' ) )
		);

		$this->assertFalse( $answer['success'] );
		$this->assertSame( 'Insufficient permissions.', $answer['data']['message'] );
		$this->assertArrayNotHasKey( 'days', $answer['data'] );
	}

	/**
	 * Without a valid nonce the handler stops before it warns or answers.
	 */
	public function test_deprecated_ajax_refresh_checks_the_nonce_first(): void {
		$warnings = 0;
		add_action(
			'doing_it_wrong_run',
			static function () use ( &$warnings ): void {
				++$warnings;
			}
		);

		$answer = $this->ajax( array( new Dashboard_Widget(), 'ajax_refresh_data' ), array( 'nonce' => 'wrong' ) );

		$this->assertNull( $answer );
		$this->assertSame( 0, $warnings );
	}

	/**
	 * Detection times carry their UTC offset, so the browser never reads them as local time.
	 */
	public function test_detection_times_have_a_utc_offset(): void {
		update_option( 'timezone_string', 'America/Chicago' );
		$this->insert_log_rows( 1, array( 'date_recorded' => '2026-07-01 07:00:00' ) );

		$this->assertStringContainsString( '<time class="zs-w-when" datetime="2026-07-01T12:00:00+00:00">', $this->render() );
	}

	/**
	 * When the network has more log tables than are read, the label says so.
	 */
	public function test_capped_network_label(): void {
		ob_start();
		( new ReflectionMethod( Dashboard_Widget::class, 'load_template' ) )->invoke(
			( new ReflectionClass( Dashboard_Widget::class ) )->newInstanceWithoutConstructor(),
			ZEROSPAM_PATH . 'includes/templates/dashboard-widget.php',
			array(
				'scope' => 'network',
				'data'  => array(
					'table_missing' => false,
					'days'          => array_fill( 0, 30, 1 ),
					'sites'         => 1500,
					'checked'       => 1000,
					'capped'        => true,
					'top_sites'     => array(),
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'spam blocked across the 1,000 sites checked in the last 30 days', $html );
		$this->assertStringNotContainsString( '1,500', $html );
	}

	/**
	 * The API alert notice links to the API Monitoring settings, not the dashboard.
	 */
	public function test_api_alert_notice_links_to_api_monitoring(): void {
		$this->set_setting( 'alert_admin_notice', 'enabled' );
		self::reset_static_caches();
		update_option(
			'zerospam_api_notices',
			array(
				array(
					'site_id'   => get_current_blog_id(),
					'timestamp' => time(),
					'severity'  => 'warning',
					'message'   => 'Usage is high.',
				),
			)
		);

		ob_start();
		( new ReflectionClass( API_Usage_Alerts::class ) )->newInstanceWithoutConstructor()->display_admin_notices();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'href="' . esc_url( Utilities::admin_link( 'api-monitoring' ) ) . '"', $html );
		$this->assertStringContainsString( 'subview=api-monitoring', $html );
		$this->assertStringNotContainsString( 'API Usage Dashboard', $html );
	}
}
