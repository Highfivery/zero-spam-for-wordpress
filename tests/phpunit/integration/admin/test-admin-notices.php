<?php
/**
 * Tests for the admin notices and Plugins screen links left after 6.0.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Tests\TestCase;

/**
 * The 5.x welcome, settings review, API monitoring and Enhanced Protection
 * promo notices are gone (first-run setup and "What's new" replace them);
 * the license error notice and the Plugins screen's Settings link point at
 * the 6.0 pages.
 *
 * @covers \ZeroSpam\Core\Admin\Admin
 */
class Test_Admin_Notices extends TestCase {

	/**
	 * An administrator on the WordPress dashboard, in every state that used to show a notice.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'dashboard' );
		$this->as_role( 'administrator' );
		delete_option( 'zerospam_configured' );
		delete_option( 'zerospam_api_monitoring_notice_dismissed' );
		update_option( Migrations::SETTINGS_REVIEW_NOTICE_OPTION, true );
		update_option( 'zerospam_activation_time', time() - 30 * DAY_IN_SECONDS );
		delete_option( 'zero-spam-zerospam' );
		self::reset_static_caches();
	}

	/**
	 * What the admin notices print.
	 */
	private function notices(): string {
		ob_start();
		( new Admin() )->admin_notices();

		return (string) ob_get_clean();
	}

	/**
	 * No welcome, settings review, API monitoring or promo notice, in any state.
	 */
	public function test_removed_notices_never_render(): void {
		$this->assertSame( '', $this->notices() );

		update_option(
			'zero-spam-zerospam',
			array(
				'zerospam'         => 'enabled',
				'zerospam_license' => 'notices-license-key-1234',
			)
		);
		self::reset_static_caches();
		$html = $this->notices();

		foreach ( array( 'Thanks for installing', 'verify your settings', 'API Usage Monitoring', 'WELCOME15', 'Enhanced Protection: Disabled' ) as $text ) {
			$this->assertStringNotContainsString( $text, $html );
		}
		$this->assertSame( '', $html );
		$this->assertFalse( has_action( 'wp_ajax_zerospam_dismiss_promo_notice' ) );
	}

	/**
	 * Enhanced Protection on without a key: the error links to the license card.
	 */
	public function test_license_error_links_to_the_license_card(): void {
		update_option( 'zero-spam-zerospam', array( 'zerospam' => 'enabled' ) );
		self::reset_static_caches();

		$html = $this->notices();

		$this->assertStringContainsString( 'missing a valid license key', $html );
		$this->assertStringContainsString( 'href="' . esc_url( Utilities::admin_link( 'license' ) ) . '"', $html );
		$this->assertStringNotContainsString( 'wordpress-zero-spam', $html );
	}

	/**
	 * Other roles see nothing.
	 */
	public function test_editors_see_nothing(): void {
		update_option( 'zero-spam-zerospam', array( 'zerospam' => 'enabled' ) );
		$this->as_role( 'editor' );

		$this->assertSame( '', $this->notices() );
	}

	/**
	 * The Plugins screen's Settings link opens Zero Spam → Settings.
	 */
	public function test_settings_link(): void {
		$links = ( new Admin() )->plugin_action_links( array( 'deactivate' => 'x' ) );

		$this->assertStringContainsString( 'href="' . esc_url( admin_url( 'admin.php?page=zero-spam-settings' ) ) . '"', $links[0] );
		$this->assertSame( 'x', $links['deactivate'] );
	}

	/**
	 * No review ask in the footer (product rule: only the milestone tile and Help ask).
	 */
	public function test_no_footer_text(): void {
		$admin = new Admin();
		$admin->init();

		$this->assertFalse( has_filter( 'admin_footer_text', array( $admin, 'admin_footer_text' ) ) );
	}
}
