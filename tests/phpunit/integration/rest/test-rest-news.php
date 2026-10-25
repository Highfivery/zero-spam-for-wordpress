<?php
/**
 * Tests for zero-spam/v1/news.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\News_Feed;
use ZeroSpam\Tests\HttpMock;
use ZeroSpam\Tests\TestCase;

/**
 * The news route downloads the zerospam.org feed once per six hours with a
 * generic user agent, serves plain text and zerospam.org links only, backs
 * off for an hour after a failure while serving the last good copy, and
 * downloads nothing when the news is turned off.
 *
 * @covers \ZeroSpam\Includes\Rest\News_Controller
 * @covers \ZeroSpam\Includes\News_Feed
 */
class Test_Rest_News extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/news';

	/**
	 * Pattern matching the feed address.
	 */
	const FEED = '#^https://www\.zerospam\.org/articles/feed/#';

	/**
	 * Clears the news cache and the settings, and logs in an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_transient( News_Feed::TRANSIENT );
		delete_site_transient( News_Feed::BACKOFF_TRANSIENT );
		delete_option( 'zero-spam-settings' );
		self::reset_static_caches();

		$this->as_role( 'administrator' );
	}

	/**
	 * An RSS feed.
	 *
	 * @param array<int, array<string, mixed>> $items Items: title, link, description, categories, date.
	 */
	private static function feed( array $items ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Zero Spam</title><link>https://www.zerospam.org/</link>';

		foreach ( $items as $item ) {
			$xml .= '<item><title>' . $item['title'] . '</title><link>' . $item['link'] . '</link>';
			$xml .= '<pubDate>' . ( $item['date'] ?? 'Fri, 23 Oct 2026 14:30:00 +0000' ) . '</pubDate>';
			foreach ( $item['categories'] ?? array( 'Site Security' ) as $category ) {
				$xml .= '<category><![CDATA[' . $category . ']]></category>';
			}
			$xml .= '<description><![CDATA[' . ( $item['description'] ?? '<p>An article.</p>' ) . ']]></description></item>';
		}

		return $xml . '</channel></rss>';
	}

	/**
	 * A feed of plain articles on zerospam.org.
	 *
	 * @param int $count Number of articles.
	 */
	private static function simple_feed( int $count ): string {
		$items = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$items[] = array(
				'title' => "Article {$i}",
				'link'  => "https://www.zerospam.org/article-{$i}/",
			);
		}

		return self::feed( $items );
	}

	/**
	 * Who may read the news.
	 */
	public function test_permission_matrix(): void {
		$this->mock_http( self::FEED, self::simple_feed( 2 ) );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', self::ROUTE )->get_status() );

		foreach ( array( 'subscriber', 'editor' ) as $role ) {
			$this->as_role( $role );
			$this->assertSame( 403, $this->rest( 'GET', self::ROUTE )->get_status(), "A user with the {$role} role reads the news." );
		}

		$this->assertSame( 0, HttpMock::hits( self::FEED ), 'A refused request downloaded the feed.' );

		$this->as_role( 'administrator' );
		$this->assertSame( 200, $this->rest( 'GET', self::ROUTE )->get_status() );
	}

	/**
	 * The limit is 1 to 8.
	 *
	 * @dataProvider provide_invalid_limits
	 *
	 * @param int $limit Limit.
	 */
	public function test_limit_out_of_range_is_refused( int $limit ): void {
		$response = $this->rest( 'GET', self::ROUTE, array(), array( 'limit' => $limit ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( 'limit', $response->get_data()['data']['params'] );
	}

	/**
	 * Limits outside 1 to 8.
	 *
	 * @return array<string, array{0: int}>
	 */
	public static function provide_invalid_limits(): array {
		return array(
			'zero' => array( 0 ),
			'nine' => array( 9 ),
		);
	}

	/**
	 * A limit returns that many articles; at most 8 are kept.
	 */
	public function test_limit_and_maximum(): void {
		$this->mock_http( self::FEED, self::simple_feed( 10 ) );

		$this->assertCount( 3, $this->rest( 'GET', self::ROUTE, array(), array( 'limit' => 3 ) )->get_data() );
		$this->assertCount( 8, $this->rest( 'GET', self::ROUTE )->get_data() );
		$this->assertCount( 8, get_site_transient( News_Feed::TRANSIENT )['items'] );
	}

	/**
	 * `_fields` keeps only the fields asked for, in every article.
	 */
	public function test_fields(): void {
		$this->mock_http( self::FEED, self::simple_feed( 2 ) );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'title,link' ) )->get_data();

		$this->assertSame(
			array(
				array(
					'title' => 'Article 1',
					'link'  => 'https://www.zerospam.org/article-1/',
				),
				array(
					'title' => 'Article 2',
					'link'  => 'https://www.zerospam.org/article-2/',
				),
			),
			$data
		);
	}

	/**
	 * Articles are plain text with zerospam.org links only; incident reports are security notices.
	 */
	public function test_articles_are_plain_text_with_safe_links(): void {
		$long = str_repeat( 'Spammers keep trying new tricks every week. ', 10 );

		$this->mock_http(
			self::FEED,
			self::feed(
				array(
					array(
						'title'       => 'PHP2Shell &amp; you',
						'link'        => 'https://www.zerospam.org/php2shell/',
						'categories'  => array( 'Site Security', 'Incident Response' ),
						'description' => '<p>A pair of <strong>critical</strong> flaws.</p><script>alert(1)</script>',
					),
					array(
						'title' => 'Off-site',
						'link'  => 'https://evil.example.com/zerospam.org/',
					),
					array(
						'title' => 'Script link',
						'link'  => 'javascript:alert(1)',
					),
					array(
						'title' => 'Plain http',
						'link'  => 'http://www.zerospam.org/plain/',
					),
					array(
						'title'       => '&lt;script&gt;alert(2)&lt;/script&gt;Injected title',
						'link'        => 'https://zerospam.org/injected/',
						'categories'  => array( '<b>Spam Protection</b>' ),
						'description' => $long,
						'date'        => 'not a date',
					),
				)
			)
		);

		$data = $this->rest( 'GET', self::ROUTE )->get_data();

		$this->assertCount( 2, $data, 'Only the articles linking to https://zerospam.org are kept.' );
		$this->assertSame(
			array(
				'title'           => 'PHP2Shell & you',
				'excerpt'         => 'A pair of critical flaws.',
				'date'            => '2026-10-23T14:30:00Z',
				'link'            => 'https://www.zerospam.org/php2shell/',
				'categories'      => array( 'Site Security', 'Incident Response' ),
				'security_notice' => true,
			),
			$data[0]
		);

		$this->assertSame( 'Injected title', $data[1]['title'] );
		$this->assertSame( array( 'Spam Protection' ), $data[1]['categories'] );
		$this->assertFalse( $data[1]['security_notice'] );
		$this->assertNull( $data[1]['date'] );
		$this->assertLessThanOrEqual( 201, mb_strlen( $data[1]['excerpt'] ) );
		$this->assertStringEndsWith( "\u{2026}", $data[1]['excerpt'] );
		$this->assertStringStartsWith( mb_substr( $data[1]['excerpt'], 0, -1 ) . ' ', trim( $long ), 'The excerpt ends on a word boundary.' );
		$this->assertStringNotContainsString( '<', (string) wp_json_encode( $data ) );
		$this->assertStringNotContainsString( 'alert', (string) wp_json_encode( $data ) );
	}

	/**
	 * The feed is downloaded once, with a generic user agent, and the copy is served afterwards.
	 */
	public function test_download_is_cached_with_a_generic_user_agent(): void {
		$this->mock_http( self::FEED, self::simple_feed( 3 ) );

		$args = array();
		$spy  = static function ( $preempt, $request_args ) use ( &$args ) {
			$args = $request_args;

			return $preempt;
		};
		add_filter( 'pre_http_request', $spy, 0, 2 );

		$first  = $this->rest( 'GET', self::ROUTE );
		$second = $this->rest( 'GET', self::ROUTE );

		remove_filter( 'pre_http_request', $spy, 0 );

		$this->assertSame( 1, HttpMock::hits( self::FEED ), 'The second request downloaded the feed again.' );
		$this->assertSame( $first->get_data(), $second->get_data() );
		$this->assertSame( '0', $second->get_headers()['X-ZeroSpam-News-Stale'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $second->get_headers()['X-ZeroSpam-News-Fetched'] );

		$this->assertSame( 'Zero Spam for WordPress/' . ZEROSPAM_VERSION, $args['user-agent'] );
		$this->assertTrue( $args['reject_unsafe_urls'], 'The download goes through wp_safe_remote_get().' );
		$this->assertSame( 5, $args['timeout'] );
		$this->assertStringNotContainsString( wp_parse_url( home_url(), PHP_URL_HOST ), (string) wp_json_encode( $args ), 'The request names the site.' );
	}

	/**
	 * A failed download with no copy answers 503 and backs off for an hour.
	 */
	public function test_server_error_backs_off(): void {
		$this->mock_http( self::FEED, 'Oops', 500 );

		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 503, $response->get_status() );
		$this->assertSame( 'zerospam_news_unavailable', $response->get_data()['code'] );
		$this->assertNotFalse( get_site_transient( News_Feed::BACKOFF_TRANSIENT ) );

		$this->assertSame( 503, $this->rest( 'GET', self::ROUTE )->get_status() );
		$this->assertSame( 1, HttpMock::hits( self::FEED ), 'A download was tried during the back-off.' );
	}

	/**
	 * Malformed XML, an empty feed and a feed with a DOCTYPE are failures.
	 *
	 * @dataProvider provide_unusable_feeds
	 *
	 * @param string $body Feed body.
	 */
	public function test_unusable_feed_backs_off( string $body ): void {
		$this->mock_http( self::FEED, $body );

		$this->assertSame( 503, $this->rest( 'GET', self::ROUTE )->get_status() );
		$this->assertNotFalse( get_site_transient( News_Feed::BACKOFF_TRANSIENT ) );
		$this->assertFalse( get_site_transient( News_Feed::TRANSIENT ) );
	}

	/**
	 * Feeds that cannot be used.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_unusable_feeds(): array {
		return array(
			'malformed'       => array( '<rss><channel><item><title>Broken' ),
			'not xml'         => array( '<html><body>Maintenance</body></html>' ),
			'no usable items' => array( self::feed( array( array( 'title' => 'Elsewhere', 'link' => 'https://example.com/' ) ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- One-line fixture.
			'entities'        => array( '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY x "boom">]><rss><channel><item><title>&x;</title><link>https://www.zerospam.org/x/</link></item></channel></rss>' ),
			'empty'           => array( '' ),
		);
	}

	/**
	 * When a download fails, the last good copy is served and marked stale.
	 */
	public function test_stale_copy_served_on_error(): void {
		set_site_transient(
			News_Feed::TRANSIENT,
			array(
				'items'      => array(
					array(
						'title'           => 'Old article',
						'excerpt'         => '',
						'date'            => null,
						'link'            => 'https://www.zerospam.org/old/',
						'categories'      => array(),
						'security_notice' => false,
					),
				),
				'fetched_at' => time() - 7 * HOUR_IN_SECONDS,
			),
			WEEK_IN_SECONDS
		);
		$this->mock_http( self::FEED, 'Oops', 500 );

		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Old article', $response->get_data()[0]['title'] );
		$this->assertSame( '1', $response->get_headers()['X-ZeroSpam-News-Stale'] );
		$this->assertSame( 1, HttpMock::hits( self::FEED ) );

		$again = $this->rest( 'GET', self::ROUTE );
		$this->assertSame( 'Old article', $again->get_data()[0]['title'] );
		$this->assertSame( 1, HttpMock::hits( self::FEED ), 'A download was tried during the back-off.' );
	}

	/**
	 * After six hours the feed is downloaded again.
	 */
	public function test_copy_older_than_six_hours_is_refreshed(): void {
		set_site_transient(
			News_Feed::TRANSIENT,
			array(
				'items'      => array(),
				'fetched_at' => time() - 7 * HOUR_IN_SECONDS,
			),
			WEEK_IN_SECONDS
		);
		$this->mock_http( self::FEED, self::simple_feed( 1 ) );

		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 'Article 1', $response->get_data()[0]['title'] );
		$this->assertSame( '0', $response->get_headers()['X-ZeroSpam-News-Stale'] );
		$this->assertSame( 1, HttpMock::hits( self::FEED ) );
	}

	/**
	 * With the setting off the route answers 404 and downloads nothing.
	 */
	public function test_setting_off_downloads_nothing(): void {
		$this->set_setting( 'show_news', false );
		$this->mock_http( self::FEED, self::simple_feed( 1 ) );

		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'zerospam_news_disabled', $response->get_data()['code'] );
		$this->assertSame( array(), HttpMock::requests() );
	}

	/**
	 * The zerospam_news_feed_enabled filter turns the news off.
	 */
	public function test_filter_off_downloads_nothing(): void {
		add_filter( 'zerospam_news_feed_enabled', '__return_false' );
		$this->mock_http( self::FEED, self::simple_feed( 1 ) );

		$response = $this->rest( 'GET', self::ROUTE );

		remove_filter( 'zerospam_news_feed_enabled', '__return_false' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( array(), HttpMock::requests() );
	}

	/**
	 * The setting is on unless turned off, and a stored false turns it off.
	 */
	public function test_setting_defaults_to_enabled(): void {
		$entry = Settings_Schema::setting( 'show_news' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'checkbox', $entry['type'] );
		$this->assertSame( 'enabled', $entry['default'] );
		$this->assertSame( 'enabled', $entry['value'] );
		$this->assertSame( 'settings', $entry['area'] );
		$this->assertSame( 'general', $entry['group'] );
		$this->assertTrue( $entry['lockable'] );
		$this->assertTrue( News_Feed::is_enabled() );

		$this->set_setting( 'show_news', false );

		$this->assertFalse( Settings_Schema::resolve( 'show_news' )['value'] );
		$this->assertFalse( News_Feed::is_enabled() );

		$this->set_setting( 'show_news', 'enabled' );
		$this->assertTrue( News_Feed::is_enabled() );
	}

	/**
	 * The settings route turns the news off with false.
	 */
	public function test_settings_route_turns_it_off(): void {
		$response = $this->rest( 'PATCH', '/zero-spam/v1/settings', array( 'show_news' => false ) );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertFalse( $response->get_data()['settings']['show_news'] );
		self::reset_static_caches();
		$this->assertFalse( News_Feed::is_enabled() );
	}
}
