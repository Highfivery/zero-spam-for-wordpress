<?php
/**
 * Tests for the one-time API monitoring notice.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Tests\TestCase;

/**
 * On Zero Spam's own screens the notice's call to action is a secondary
 * button, so each screen keeps one primary action (Save Settings, Add
 * Blocked IP Address, …); on the WordPress dashboard it stays primary.
 *
 * @covers \ZeroSpam\Core\Admin\Admin::admin_notices
 */
class Test_Api_Monitoring_Notice extends TestCase {

	/**
	 * Renders the admin notices on a screen, as an administrator with
	 * Enhanced Protection and a license key.
	 *
	 * @param string $screen Screen ID.
	 * @return string The API monitoring notice's HTML.
	 */
	private function render_notice( string $screen ): string {
		$this->as_role( 'administrator' );
		$this->set_setting( 'zerospam', 'enabled' );
		$this->set_setting( 'zerospam_license', 'test-license-key' );
		update_option( 'zerospam_configured', true );
		$this->admin_screen( $screen );

		ob_start();
		( new Admin() )->admin_notices();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '/<div[^>]+id="zerospam-api-monitoring-notice".*?<\/div>/s', $html, $matches ), 'The API monitoring notice is not shown.' );

		return $matches[0];
	}

	/**
	 * Zero Spam's settings screen: the call to action is not a primary button.
	 */
	public function test_cta_is_secondary_on_plugin_screens(): void {
		$notice = $this->render_notice( 'settings_page_wordpress-zero-spam-settings' );

		$this->assertStringContainsString( 'Enable API Monitoring', $notice );
		$this->assertStringNotContainsString( 'button-primary', $notice );
	}

	/**
	 * The WordPress dashboard keeps WordPress's primary button.
	 */
	public function test_cta_stays_primary_on_the_wordpress_dashboard(): void {
		$notice = $this->render_notice( 'dashboard' );

		$this->assertMatchesRegularExpression( '/class="button button-primary"[^>]*>\s*Enable API Monitoring/', $notice );
	}
}
