<?php
/**
 * Tests for zero-spam/v1/news on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\News_Feed;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\TestCase;

/**
 * One download serves every site of the network, any site administrator can
 * read the news of their own site, and a locked "off" from the network
 * administrator wins over a site's own setting.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Rest\News_Controller
 * @covers \ZeroSpam\Includes\News_Feed
 */
class Test_Rest_News_Network extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/news';

	/**
	 * Pattern matching the feed address.
	 */
	const FEED = '#^https://www\.zerospam\.org/articles/feed/#';

	/**
	 * Another site of the network.
	 *
	 * @var int
	 */
	private static int $site_b = 0;

	/**
	 * Creates the second site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site_b = self::create_site_with_tables( $factory );
	}

	/**
	 * Clears the news cache, the network store and both sites' settings.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_transient( News_Feed::TRANSIENT );
		delete_site_transient( News_Feed::BACKOFF_TRANSIENT );
		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::CACHE_KEY );

		foreach ( array( get_main_site_id(), self::$site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			delete_option( 'zero-spam-settings' );
			restore_current_blog();
		}
		self::reset_static_caches();

		$this->mock_http( self::FEED, '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Network news</title><link>https://www.zerospam.org/network-news/</link></item></channel></rss>' );
	}

	/**
	 * A user who administers only site B.
	 */
	private function site_b_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		remove_user_from_blog( $user_id, get_main_site_id() );
		add_user_to_blog( self::$site_b, $user_id, 'administrator' );

		return $user_id;
	}

	/**
	 * Sends a request on a given site.
	 *
	 * @param int $blog_id Site.
	 */
	private function news_on( int $blog_id ): WP_REST_Response {
		switch_to_blog( $blog_id );
		self::reset_static_caches();

		try {
			return $this->rest( 'GET', self::ROUTE );
		} finally {
			restore_current_blog();
			self::reset_static_caches();
		}
	}

	/**
	 * Every site reads the same download.
	 */
	public function test_one_download_per_network(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$main = $this->news_on( get_main_site_id() );
		$b    = $this->news_on( self::$site_b );

		$this->assertSame( 200, $main->get_status() );
		$this->assertSame( 200, $b->get_status() );
		$this->assertSame( 'Network news', $b->get_data()[0]['title'] );
		$this->assertSame( 1, HttpMock::hits( self::FEED ), 'Each site downloaded the feed.' );
	}

	/**
	 * A site administrator reads the news of their own site only.
	 */
	public function test_site_admin_permissions(): void {
		wp_set_current_user( $this->site_b_admin() );

		$this->assertSame( 200, $this->news_on( self::$site_b )->get_status() );
		$this->assertSame( 403, $this->news_on( get_main_site_id() )->get_status(), 'An administrator of another site reads the news.' );
	}

	/**
	 * A locked network "off" wins over the site's own "on" and downloads nothing.
	 */
	public function test_locked_network_off_wins(): void {
		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'show_news' => 'enabled' ) );
		restore_current_blog();
		$this->set_network_setting( 'show_news', false, true );
		wp_set_current_user( $this->site_b_admin() );

		$response = $this->news_on( self::$site_b );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'zerospam_news_disabled', $response->get_data()['code'] );
		$this->assertSame( array(), HttpMock::requests() );
	}

	/**
	 * An unlocked network "off" is the default a site may override.
	 */
	public function test_unlocked_network_off_can_be_overridden(): void {
		$this->set_network_setting( 'show_news', false );
		wp_set_current_user( $this->site_b_admin() );

		$this->assertSame( 404, $this->news_on( self::$site_b )->get_status(), 'The network default is not followed.' );

		switch_to_blog( self::$site_b );
		update_option( 'zero-spam-settings', array( 'show_news' => 'enabled' ) );
		restore_current_blog();

		$this->assertSame( 200, $this->news_on( self::$site_b )->get_status(), 'The site override is not followed.' );
	}
}
