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
		$this->assertStringContainsString( esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ), $html );

		foreach ( array( 'Not set', 'Locked by the network: sites can\'t change it.', 'View your Zero Spam settings' ) as $string ) {
			$this->assertContains( $string, $this->translated, "\"{$string}\" is not translated." );
		}
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
		$this->assertStringContainsString( esc_url( network_admin_url( 'settings.php?page=zerospam-network-settings' ) ), $parts['text/html'] );

		foreach ( array( 'Weekly summary', 'Total sites', 'Locked settings', 'Site overrides', 'View network settings' ) as $string ) {
			$this->assertContains( $string, $this->translated, "\"{$string}\" is not translated." );
		}
	}
}
