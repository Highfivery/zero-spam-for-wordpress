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
	 * The query() method reads wp_cache_get() from the default group but
	 * writes wp_cache_set() to the `zerospam` group, so the cache never hits.
	 *
	 * @group known-defect
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
	}
}
