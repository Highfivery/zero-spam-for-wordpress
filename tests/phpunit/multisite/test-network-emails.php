<?php
/**
 * Tests for the network notification emails.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Network_Notifications;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * The settings-change email and the weekly summary use the plugin's email
 * template (HTML with a plain-text alternative), escape what comes from data
 * and translate every word of their own.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Notifications
 */
class Test_Network_Emails extends TestCase {

	/**
	 * Strings looked up in the zero-spam text domain.
	 *
	 * @var string[]
	 */
	private array $translated = array();

	/**
	 * Clears the mail spy and records translations.
	 */
	public function set_up(): void {
		parent::set_up();

		reset_phpmailer_instance();
		$this->translated = array();
		add_filter( 'gettext', array( $this, 'record_translation' ), 10, 3 );
	}

	/**
	 * Clears the mail spy.
	 */
	public function tear_down(): void {
		remove_filter( 'gettext', array( $this, 'record_translation' ), 10 );
		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Records the zero-spam strings that were translated and marks them.
	 *
	 * @param string $translation Translation.
	 * @param string $text        Original.
	 * @param string $domain      Text domain.
	 */
	public function record_translation( $translation, $text, $domain ) {
		if ( 'zero-spam' === $domain ) {
			$this->translated[] = $text;
		}

		return $translation;
	}

	/**
	 * Changing and locking a network setting emails each site's administrator.
	 */
	public function test_settings_change_email(): void {
		$admin = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Net <b>Admin</b>',
			)
		);
		grant_super_admin( $admin );
		wp_set_current_user( $admin );
		update_option( 'blogname', 'Main & <Site>' );

		update_site_option( Network_Settings::META_KEY, array( 'settings' => array() ) );
		reset_phpmailer_instance();

		update_site_option(
			Network_Settings::META_KEY,
			array(
				'settings' => array(
					'verify_comments' => array(
						'value'  => '<script>enabled</script>',
						'locked' => true,
					),
				),
			)
		);

		$sent = $this->sent_mail();
		$this->assertNotEmpty( $sent, 'No site administrator was told about the change.' );
		$mail = $sent[0];
		$this->assertSame( '[Main & <Site>] Zero Spam Network Settings Updated', $mail->subject );

		$parts = $this->mail_parts( $mail );
		$this->assertArrayHasKey( 'text/plain', $parts );
		$this->assertArrayHasKey( 'text/html', $parts );
		$html = $parts['text/html'];
		$text = $parts['text/plain'];

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<strong>' . esc_html( get_userdata( $admin )->display_name ) . '</strong>', $html );
		$this->assertStringContainsString( 'Main &amp; &lt;Site&gt;', $html );
		$this->assertStringContainsString( '&lt;script&gt;enabled&lt;/script&gt;', $html );
		$this->assertStringContainsString( 'Network administrator ' . get_userdata( $admin )->display_name . ' changed', $text );
		$this->assertStringContainsString( esc_url( admin_url( 'admin.php?page=zero-spam-settings' ) ), $html );

		foreach ( array( 'Not set', 'Locked by the network: sites can\'t change it.', 'View your Zero Spam settings' ) as $string ) {
			$this->assertContains( $string, $this->translated, "\"{$string}\" is not translated." );
		}
	}

	/**
	 * The network API alert is HTML and links to the network admin.
	 */
	public function test_network_api_alert_links_to_the_network_admin(): void {
		$this->set_setting( 'alert_network', 'enabled', 'api-monitoring' );

		$send = new ReflectionMethod( \ZeroSpam\Includes\API_Usage_Alerts::class, 'send_network_alert' );
		$send->invoke(
			new \ZeroSpam\Includes\API_Usage_Alerts(),
			array(
				'type'     => 'quota_warning',
				'severity' => 'critical',
				'message'  => 'Network-wide API quota at 5.0% remaining',
				'value'    => 5,
			)
		);

		$sent = $this->sent_mail();
		$this->assertNotEmpty( $sent );
		$parts = $this->mail_parts( $sent[0] );
		$this->assertArrayHasKey( 'text/html', $parts );
		$this->assertStringContainsString( esc_url( network_admin_url( 'admin.php?page=zero-spam-network&tab=settings' ) ), $parts['text/html'] );
		$this->assertStringNotContainsString( esc_url( admin_url( 'admin.php?page=zero-spam-settings&tab=api' ) ), $parts['text/html'] );
	}

	/**
	 * The weekly summary goes to a super admin in the template, with the network's numbers and recent changes.
	 */
	public function test_weekly_summary_email(): void {
		$admin = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'netadmin',
				'user_email' => 'netadmin@example.com',
			)
		);
		grant_super_admin( $admin );

		( new Network_Notifications() )->send_weekly_summary();

		$sent = $this->sent_mail();
		$this->assertNotEmpty( $sent );
		$mail = $sent[0];
		$this->assertSame( 'Zero Spam Network - Weekly Summary', $mail->subject );

		$parts = $this->mail_parts( $mail );
		$this->assertArrayHasKey( 'text/plain', $parts );
		$this->assertArrayHasKey( 'text/html', $parts );
		$this->assertStringContainsString( 'Total sites', $parts['text/plain'] );
		$this->assertStringContainsString( esc_url( network_admin_url( 'admin.php?page=zero-spam-network&tab=settings' ) ), $parts['text/html'] );

		foreach ( array( 'Weekly summary', 'Total sites', 'Locked settings', 'Site overrides', 'View network settings' ) as $string ) {
			$this->assertContains( $string, $this->translated, "\"{$string}\" is not translated." );
		}
	}

	/**
	 * A changed key or webhook address is never written in the email: it reads "••••".
	 */
	public function test_settings_change_email_redacts_secrets(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin );
		wp_set_current_user( $admin );
		delete_option( 'zero-spam-ipinfo' );
		delete_option( 'zero-spam-api-monitoring' );
		update_site_option( Network_Settings::META_KEY, array( 'settings' => array() ) );
		reset_phpmailer_instance();

		update_site_option(
			Network_Settings::META_KEY,
			array(
				'settings' => array(
					'ipinfo_access_token' => array(
						'value'  => 'ipinfo-secret-9876',
						'locked' => false,
					),
					'alert_webhook_url'   => array(
						'value'  => 'https://hooks.example.com/abcdef-secret-hook',
						'locked' => false,
					),
				),
			)
		);

		$sent = $this->sent_mail();
		$this->assertNotEmpty( $sent, 'No site administrator was told about the change.' );

		foreach ( $sent as $mail ) {
			$parts = $this->mail_parts( $mail );
			foreach ( array( 'text/html', 'text/plain' ) as $type ) {
				$this->assertArrayHasKey( $type, $parts );
				$this->assertStringContainsString( "\u{2022}\u{2022}\u{2022}\u{2022}", $parts[ $type ], "{$type}: no mask." );
				$this->assertStringNotContainsString( 'ipinfo-secret', $parts[ $type ], "{$type}: the key is in the email." );
				$this->assertStringNotContainsString( 'secret-hook', $parts[ $type ], "{$type}: the webhook is in the email." );
			}
			$this->assertStringContainsString( esc_url( \ZeroSpam\Core\Utilities::admin_link( 'settings' ) ), $parts['text/html'] );
		}
	}

	/**
	 * Undoing a lock on a setting the network never set removes its value:
	 * every site was told "Locked by the network", so every site is told it is over.
	 */
	public function test_undoing_a_lock_on_an_unset_setting_emails_every_site(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin );
		wp_set_current_user( $admin );
		update_option( 'zero-spam-settings', array( 'max_logs' => 4321 ) );
		update_site_option( Network_Settings::META_KEY, array( 'settings' => array() ) );
		delete_site_transient( Network_Settings::cache_key() );
		reset_phpmailer_instance();

		$manager = new \ZeroSpam\Includes\Network_Settings_Manager();
		$manager->lock_setting( 'max_logs' );
		$this->assertStringContainsString( 'Locked by the network', $this->mail_parts( $this->sent_mail()[0] )['text/plain'] );

		reset_phpmailer_instance();
		$manager->unlock_setting( 'max_logs' );

		$sent = $this->sent_mail();
		$this->assertNotEmpty( $sent, 'The site that stores its own value was not told the lock is over.' );
		$this->assertStringContainsString( 'Unlocked: you can now change it', $this->mail_parts( $sent[0] )['text/plain'] );
	}
}
