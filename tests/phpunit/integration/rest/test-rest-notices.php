<?php
/**
 * Tests for dismissing the admin notices.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Notices;
use ZeroSpam\Core\Admin\Shared_IP_Warning;
use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\TestCase;

/**
 * `POST zero-spam/v1/ui/notices/{id}/dismiss` dismisses a notice as its own
 * Dismiss button does, into one store (Core\Admin\Notices).
 *
 * @covers \ZeroSpam\Includes\Rest\Notices_Controller
 * @covers \ZeroSpam\Core\Admin\Notices
 */
class Test_Rest_Notices extends TestCase {

	/**
	 * Clean store.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Notices::OPTION );
		delete_option( Admin_Safe_IPs::NOTICE_OPTION );
		delete_transient( Shared_IP_Warning::TRANSIENT );
	}

	/**
	 * Dismisses a notice over REST.
	 *
	 * @param string $id Notice ID.
	 */
	private function dismiss( string $id ): WP_REST_Response {
		return $this->rest( 'POST', '/zero-spam/v1/ui/notices/' . $id . '/dismiss' );
	}

	/**
	 * Only administrators of the site may dismiss.
	 */
	public function test_permissions(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dismiss( 'shared-ip' )->get_status() );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->dismiss( 'shared-ip' )->get_status() );

		$this->as_role( 'administrator' );
		$this->assertSame( 200, $this->dismiss( 'shared-ip' )->get_status() );
	}

	/**
	 * An unknown notice is a 404.
	 */
	public function test_unknown_notice_is_not_found(): void {
		$this->as_role( 'administrator' );

		$response = $this->dismiss( 'promo' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'zerospam_notice_not_found', $response->get_data()['code'] );
	}

	/**
	 * Nothing shown, nothing dismissed.
	 */
	public function test_nothing_to_dismiss(): void {
		$this->as_role( 'administrator' );

		$this->assertSame(
			array(
				'id'        => 'shared-ip',
				'dismissed' => false,
			),
			$this->dismiss( 'shared-ip' )->get_data()
		);
		$this->assertNull( Notices::dismissed( 'shared-ip' ) );
	}

	/**
	 * The shared-IP warning stores what its dismissal covers, so the same
	 * warning stays hidden and another address is reported again.
	 */
	public function test_shared_ip_stores_the_dismissal_key(): void {
		$this->as_role( 'administrator' );
		update_option( Migrations::PROXY_DETECTION_SINCE_OPTION, wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$this->insert_log_rows( Shared_IP_Warning::SAMPLE_SIZE, array( 'user_ip' => '198.51.100.50' ) );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.77';

		$response = $this->dismiss( 'shared-ip' );

		$this->assertTrue( $response->get_data()['dismissed'] );
		$this->assertSame( 'shared_ip:198.51.100.50', Notices::dismissed( 'shared-ip' ) );
		$this->assertTrue( Shared_IP_Warning::is_dismissed( Shared_IP_Warning::get_verdict() ) );
		$other = array(
			'ip'     => '198.51.100.51',
			'reason' => 'shared_ip',
		);
		$this->assertFalse( Shared_IP_Warning::is_dismissed( $other ) );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * The safe-IP notice is removed only for the administrator it was shown to.
	 */
	public function test_safe_ip_only_for_its_administrator(): void {
		$owner = $this->as_role( 'administrator' );
		update_option(
			Admin_Safe_IPs::NOTICE_OPTION,
			array(
				'ip'      => '8.8.8.8',
				'user_id' => $owner,
			),
			false
		);

		$this->as_role( 'administrator' );
		$this->assertFalse( $this->dismiss( 'safe-ip' )->get_data()['dismissed'] );
		$this->assertIsArray( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );

		wp_set_current_user( $owner );
		$this->assertTrue( $this->dismiss( 'safe-ip' )->get_data()['dismissed'] );
		$this->assertFalse( get_option( Admin_Safe_IPs::NOTICE_OPTION ) );
	}

	/**
	 * The store is not autoloaded and keeps other notices' dismissals.
	 */
	public function test_store_is_one_option(): void {
		global $wpdb;

		Notices::remember( 'shared-ip', 'trusted_proxy' );
		Notices::remember( 'other', 'x' );

		$this->assertSame( 'trusted_proxy', Notices::dismissed( 'shared-ip' ) );
		$this->assertSame( 'x', Notices::dismissed( 'other' ) );
		$this->assertNull( Notices::dismiss( 'nope' ) );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Notices::OPTION ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}
}
