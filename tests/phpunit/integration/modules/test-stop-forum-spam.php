<?php
/**
 * Tests for the Stop Forum Spam module.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Modules\StopForumSpam;
use ZeroSpam\Tests\TestCase;

/**
 * Stop Forum Spam lookups and caching.
 *
 * @covers \ZeroSpam\Modules\StopForumSpam
 */
class Test_Stop_Forum_Spam extends TestCase {

	const API = '#^https://api\.stopforumspam\.org/api\?#';

	/**
	 * Enables the module.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_setting( 'stop_forum_spam', 'enabled', 'stop_forum_spam' );
	}

	/**
	 * A lookup is cached, so repeating it makes no second API request.
	 *
	 * Regression: query() read wp_cache_get() from the default group but wrote
	 * wp_cache_set() to the `zerospam` group, so the cache never hit.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_repeated_query_is_served_from_cache(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'ip'      => array( 'appears' => 0 ),
			)
		);

		$module = new StopForumSpam();
		$module->query( array( 'ip' => self::DEFAULT_IP ) );
		$module->query( array( 'ip' => self::DEFAULT_IP ) );

		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'The second lookup went back to the API.' );
	}

	/**
	 * Different lookups never share a cache entry.
	 *
	 * The key used to be built from the values alone through sanitize_title(),
	 * which strips the colons from IPv6 addresses, so these two IPs collided.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_distinct_lookups_do_not_share_a_cache_entry(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'ip'      => array( 'appears' => 0 ),
			)
		);

		$module = new StopForumSpam();
		$module->query( array( 'ip' => '2001:db8::1:23' ) );
		$module->query( array( 'ip' => '2001:db8::12:3' ) );

		$this->assertSame( 2, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'The second IP was answered from the first IP\'s cache entry.' );
	}

	/**
	 * An error answer is not cached, so the next lookup asks the API again.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_failed_lookup_is_not_cached(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 0,
				'error'   => 'rate limit exceeded',
			)
		);

		$module = new StopForumSpam();
		$module->query( array( 'ip' => self::DEFAULT_IP ) );
		$module->query( array( 'ip' => self::DEFAULT_IP ) );

		$this->assertSame( 2, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'An error answer was served from the cache.' );
	}

	/**
	 * The request carries only the lookup parameters and the JSON flag.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::query
	 */
	public function test_request_url_carries_only_the_lookup_parameters(): void {
		$this->mock_http( self::API, array( 'success' => 1 ) );

		( new StopForumSpam() )->query( array( 'ip' => self::DEFAULT_IP ) );

		$requests = \ZeroSpam\Tests\HttpMock::requests();
		$this->assertCount( 1, $requests );
		$this->assertSame( 'https://api.stopforumspam.org/api?ip=' . rawurlencode( self::DEFAULT_IP ) . '&json=', $requests[0]['url'] );
	}

	/**
	 * The module's comment check runs through the
	 * zerospam_preprocess_comment_submission filter, called the way the
	 * comments module calls it (three arguments, the callback accepts two).
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::init
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_comments
	 */
	public function test_comment_filter_reports_a_listed_email(): void {
		$this->mock_http(
			self::API,
			array(
				'success' => 1,
				'email'   => array(
					'appears'    => 1,
					'confidence' => 90,
				),
			)
		);

		( new StopForumSpam() )->init();

		$errors = apply_filters(
			'zerospam_preprocess_comment_submission',
			array(),
			array( 'comment_author_email' => 'spammer@example.com' ),
			'comment_spam_message'
		);

		$this->assertContains( 'stop_forum_spam_email', $errors );
		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( self::API ) );
	}

	/**
	 * A registration whose username Stop Forum Spam reports is rejected.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_registration_reported_as_spam_is_rejected(): void {
		$this->mock_http(
			self::API,
			array(
				'success'  => 1,
				'username' => array(
					'appears'    => 1,
					'confidence' => 90.5,
				),
				'email'    => array(
					'appears'    => 1,
					'confidence' => 75,
				),
			)
		);

		$errors = ( new StopForumSpam() )->preprocess_registrations( new WP_Error(), 'spammer', 'spammer@example.com' );

		$this->assertWPError( $errors );
		$this->assertContains( 'zerospam_error_stopformspam_username', $errors->get_error_codes() );
		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( self::API ) );
	}

	/**
	 * A clean registration passes.
	 *
	 * @covers \ZeroSpam\Modules\StopForumSpam::preprocess_registrations
	 */
	public function test_clean_registration_passes(): void {
		$this->mock_http( self::API, array( 'success' => 1 ) );

		$errors = ( new StopForumSpam() )->preprocess_registrations( new WP_Error(), 'jane', 'jane@example.com' );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( self::API ), 'The registration was not checked against the API.' );
	}
}
