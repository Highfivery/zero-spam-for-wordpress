<?php
/**
 * Tests for the network dashboard widget.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard_Widget;
use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\TestCase;

/**
 * The "Zero Spam network overview" widget sums every site's log (each site
 * logs to its own table; 5.x read only the main site's), lists the sites with
 * the most spam, has no Refresh button and links to the network activity.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Admin\Dashboard_Widget
 * @covers \ZeroSpam\Core\Utilities::activity_url
 */
class Test_Dashboard_Widget_Network extends TestCase {

	/**
	 * Another site of the network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * Creates the second site with its plugin tables.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );
		update_blog_option( self::$site_b, 'blogname', 'Site B <i>shop</i>' );
	}

	/**
	 * A super admin on the network dashboard, widget on, no cached data.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		$this->admin_screen( 'dashboard-network' );
		grant_super_admin( $this->as_role( 'administrator' ) );
		$this->set_setting( 'widget_enabled', 'enabled' );
		self::reset_static_caches();
		delete_transient( Dashboard_Widget::NETWORK_TRANSIENT );
		unset( $GLOBALS['wp_meta_boxes'], $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
	}

	/**
	 * Restores globals.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['wp_meta_boxes'], $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );

		parent::tear_down();
	}

	/**
	 * Inserts detections into site B's log table, dated now.
	 *
	 * @param int    $count Rows.
	 * @param string $type  Log type.
	 */
	private function insert_site_b_rows( int $count, string $type = 'comment' ): void {
		global $wpdb;

		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->get_blog_prefix( self::$site_b ) . DB::$tables['log'],
				array(
					'log_type'        => $type,
					'user_ip'         => '203.0.113.' . ( $i + 1 ),
					'date_recorded'   => current_time( 'mysql' ),
					'page_url'        => 'http://example.org/',
					'submission_data' => '{}',
				)
			);
		}
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
	 * Totals cover every site; the sites are ranked; rescue rows are left out.
	 */
	public function test_network_widget_sums_every_site(): void {
		$this->insert_log_rows( 3 );
		$this->insert_site_b_rows( 5 );
		$this->insert_site_b_rows( 2, 'rescue' );

		$html = $this->render();

		$this->assertStringContainsString( 'data-zs-widget="network"', $html );
		$this->assertMatchesRegularExpression( '#data-zs-field="total">8<#', $html );
		$this->assertStringContainsString( 'across ' . get_blog_count() . ' sites', $html );
		$this->assertStringContainsString( 'Site B &lt;i&gt;shop&lt;/i&gt;', $html, 'Site names are escaped.' );
		$this->assertLessThan( strpos( $html, get_blog_details( get_main_site_id() )->blogname ), strpos( $html, 'Site B' ), 'The site with the most spam comes first.' );
		$this->assertStringContainsString( '>5<span class="zs-sr-only"> spam blocked</span>', $html );
	}

	/**
	 * No Refresh button (the REST stats are per site); the link opens the network activity.
	 */
	public function test_network_widget_has_no_refresh(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( 'data-zs-refresh', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( esc_url( network_admin_url( 'settings.php?page=wordpress-zero-spam-network-stats' ) ), $html );
		$this->assertStringContainsString( 'View network activity', $html );

		( new Dashboard_Widget() )->enqueue_assets( 'index.php' );
		$this->assertTrue( wp_style_is( Dashboard_Widget::HANDLE ) );
		$this->assertFalse( wp_script_is( Dashboard_Widget::HANDLE, 'registered' ) );
	}

	/**
	 * The network dashboard registers the network title.
	 */
	public function test_network_title(): void {
		( new Dashboard_Widget() )->register_widget();

		$box = $GLOBALS['wp_meta_boxes']['dashboard-network']['normal']['core'][ Dashboard_Widget::WIDGET_ID ] ?? null;
		$this->assertNotNull( $box );
		$this->assertStringContainsString( '<span>Zero Spam network overview</span>', $box['args']['__widget_basename'] );
	}

	/**
	 * The data is cached for five minutes in the 5.x transient.
	 */
	public function test_network_data_is_cached(): void {
		$this->insert_log_rows( 1 );
		$this->render();

		$this->insert_site_b_rows( 4 );
		$this->assertMatchesRegularExpression( '#data-zs-field="total">1<#', $this->render() );

		delete_transient( Dashboard_Widget::NETWORK_TRANSIENT );
		$this->assertMatchesRegularExpression( '#data-zs-field="total">5<#', $this->render() );
	}
}
