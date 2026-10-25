<?php
/**
 * Tests for the plugin's email template and the API usage alert emails.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\API_Usage_Alerts;
use ZeroSpam\Includes\Email;
use ZeroSpam\Tests\TestCase;

/**
 * Email: one HTML template (tables, inline styles, the brick motif as
 * coloured cells, no images) with a plain-text alternative.
 *
 * @covers \ZeroSpam\Includes\Email
 * @covers \ZeroSpam\Includes\API_Usage_Alerts
 */
class Test_Emails extends TestCase {

	/**
	 * Clears the mail spy.
	 */
	public function set_up(): void {
		parent::set_up();

		reset_phpmailer_instance();
	}

	/**
	 * Clears the mail spy.
	 */
	public function tear_down(): void {
		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * A sample message with every kind of block.
	 *
	 * @return array<string, mixed>
	 */
	private function sample(): array {
		return array(
			'heading' => 'Heading <b>&</b>',
			'blocks'  => array(
				array(
					'type' => 'text',
					'text' => 'Hello %1$s from %2$s, see %3$s.',
					'args' => array(
						array(
							'text'  => 'Site <script>alert(1)</script> & Co',
							'style' => 'strong',
						),
						array(
							'text'  => '203.0.113.9',
							'style' => 'mono',
						),
						array(
							'text'  => 'the settings',
							'style' => 'link',
							'url'   => 'https://example.org/wp-admin/?a=1&b=2',
						),
					),
				),
				array(
					'type'  => 'button',
					'label' => 'Do <it>',
					'url'   => 'https://example.org/?token=abc&x=1',
					'paste' => true,
				),
				array(
					'type'  => 'rows',
					'title' => 'Numbers',
					'rows'  => array(
						array( 'Label <i>', 'Value "1" & <2>' ),
					),
				),
				array(
					'type' => 'text',
					'size' => 'small',
					'text' => 'Small print with 100%% of %s.',
					'args' => array( 'plain <u>' ),
				),
			),
			'footer'  => 'Footer <hr>',
		);
	}

	/**
	 * The HTML is a table layout no wider than 600px, with inline styles only, no images and no scripts.
	 */
	public function test_html_layout(): void {
		$html = Email::html( $this->sample() );

		$this->assertStringStartsWith( '<!DOCTYPE html>', $html );
		$this->assertMatchesRegularExpression( '/<html lang="en-US" dir="ltr"/', $html );
		$this->assertStringContainsString( 'max-width:600px', $html );
		$this->assertStringContainsString( 'role="presentation"', $html );
		$this->assertDoesNotMatchRegularExpression( '/<(img|script|link|style|iframe|svg)\b/i', $html, 'The email has an image, script, stylesheet or SVG.' );
		$this->assertDoesNotMatchRegularExpression( '/\sclass=/i', $html, 'The email relies on classes (stripped by many clients).' );
		$this->assertStringNotContainsString( 'url(', $html );

		// The brick motif: coloured table cells in the six reds.
		foreach ( array( '#63000D', '#840012', '#BE0000', '#FF2929', '#FF7038', '#FFA17C' ) as $red ) {
			$this->assertMatchesRegularExpression( '/<td[^>]+background(?:-color)?:' . $red . '/i', $html, "The brick motif has no {$red} brick." );
		}

		// No line long enough to force quoted-printable or break in mail servers.
		foreach ( explode( "\n", $html ) as $line ) {
			$this->assertLessThan( 998, strlen( $line ) );
		}
	}

	/**
	 * Everything that comes from data is escaped in the HTML and kept as plain text in the alternative.
	 */
	public function test_escaping(): void {
		$html = Email::html( $this->sample() );
		$text = Email::text( $this->sample() );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Site &lt;script&gt;alert(1)&lt;/script&gt; &amp; Co', $html );
		$this->assertStringContainsString( 'Heading &lt;b&gt;&amp;&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'Do &lt;it&gt;', $html );
		$this->assertStringContainsString( 'Value &quot;1&quot; &amp; &lt;2&gt;', $html );
		$this->assertStringContainsString( 'href="https://example.org/wp-admin/?a=1&#038;b=2"', $html );
		$this->assertStringContainsString( 'Small print with 100% of plain &lt;u&gt;.', $html );
		$this->assertStringContainsString( 'Footer &lt;hr&gt;', $html );

		$this->assertStringContainsString( 'Heading <b>&</b>', $text );
		$this->assertStringContainsString( 'Hello Site <script>alert(1)</script> & Co from 203.0.113.9, see the settings (https://example.org/wp-admin/?a=1&b=2).', $text );
		$this->assertStringContainsString( "\nhttps://example.org/?token=abc&x=1\n", $text );
		$this->assertStringContainsString( 'Label <i>: Value "1" & <2>', $text );
		$this->assertStringNotContainsString( '&amp;', $text );
		$this->assertStringNotContainsString( '<td', $text );
	}

	/**
	 * A button is a real link with its label, followed by the raw address to paste.
	 */
	public function test_button_has_link_text_and_raw_url(): void {
		$html = Email::html( $this->sample() );

		$this->assertMatchesRegularExpression( '#<a href="https://example\.org/\?token=abc&\#038;x=1"[^>]*>Do &lt;it&gt;</a>#', $html );
		$this->assertStringContainsString( 'Or paste this address into your browser:', $html );
		$this->assertStringContainsString( '>https://example.org/?token=abc&amp;x=1<', $html );
	}

	/**
	 * Email::send() sends HTML with a plain-text alternative, through a one-shot phpmailer_init callback.
	 */
	public function test_send_is_multipart_and_cleans_up(): void {
		$before = has_action( 'phpmailer_init' ) ? count( $GLOBALS['wp_filter']['phpmailer_init']->callbacks, COUNT_RECURSIVE ) : 0;

		$this->assertTrue( Email::send( 'owner@example.com', 'Subject', $this->sample() ) );

		$after = has_action( 'phpmailer_init' ) ? count( $GLOBALS['wp_filter']['phpmailer_init']->callbacks, COUNT_RECURSIVE ) : 0;
		$this->assertSame( $before, $after, 'The phpmailer_init callback was left behind.' );

		$mail = $this->sent_mail()[0];
		$this->assertStringContainsString( 'multipart/alternative', $mail->header );
		$parts = $this->mail_parts( $mail );
		$this->assertArrayHasKey( 'text/plain', $parts );
		$this->assertArrayHasKey( 'text/html', $parts );
		$this->assertStringContainsString( 'https://example.org/?token=abc&x=1', $parts['text/plain'] );
		$this->assertStringContainsString( '<table', $parts['text/html'] );

		// The next email, sent without the template, has no leftover alternative.
		wp_mail( 'owner@example.com', 'Plain', 'Just text.' );
		$plain = $this->sent_mail()[1];
		$this->assertStringNotContainsString( 'multipart', $plain->header );
		$this->assertStringNotContainsString( 'token=abc', $plain->body );
	}

	/**
	 * When another plugin sends the email itself (pre_wp_mail), the phpmailer_init callback is still removed.
	 */
	public function test_send_cleans_up_when_wp_mail_is_short_circuited(): void {
		add_filter( 'pre_wp_mail', '__return_true' );
		$before = has_action( 'phpmailer_init' ) ? count( $GLOBALS['wp_filter']['phpmailer_init']->callbacks, COUNT_RECURSIVE ) : 0;

		$this->assertTrue( Email::send( 'owner@example.com', 'Subject', $this->sample() ) );

		$after = has_action( 'phpmailer_init' ) ? count( $GLOBALS['wp_filter']['phpmailer_init']->callbacks, COUNT_RECURSIVE ) : 0;
		$this->assertSame( $before, $after, 'The phpmailer_init callback was left behind.' );
		$this->assertSame( array(), $this->sent_mail() );
	}

	/**
	 * Line breaks inside a paragraph survive in the HTML.
	 */
	public function test_line_breaks_in_a_paragraph(): void {
		$html = Email::html(
			array(
				'blocks' => array(
					array(
						'type' => 'text',
						'text' => "First line\nsecond <line>",
					),
				),
			)
		);

		$this->assertStringContainsString( "First line<br>\nsecond &lt;line&gt;", $html );
	}

	/**
	 * Rendering an email runs no database queries.
	 */
	public function test_rendering_runs_no_queries(): void {
		Email::html( $this->sample() );

		$this->assertSame(
			array(),
			$this->capture_queries(
				function (): void {
					Email::html( $this->sample() );
					Email::text( $this->sample() );
				}
			)
		);
	}

	/**
	 * The template's own words go through the zero-spam text domain.
	 */
	public function test_template_strings_are_translatable(): void {
		$domains = array();
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) use ( &$domains ) {
				$domains[ $text ] = $domain;

				return 'zero-spam' === $domain ? '[tr] ' . $translation : $translation;
			},
			10,
			3
		);

		$html = Email::html( $this->sample() );

		$this->assertStringContainsString( '[tr] Or paste this address into your browser:', $html );
		$this->assertSame( 'zero-spam', $domains['Or paste this address into your browser:'] ?? null );
	}

	/**
	 * The API usage alert: the template, translated words, the user's own usage, and no prices, plans or upgrade links.
	 */
	public function test_api_alert_email(): void {
		$this->set_setting( 'alert_email', 'enabled', 'api-monitoring' );
		update_option( 'blogname', 'Lakeside <Dental>' );
		$translated = array();
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) use ( &$translated ) {
				if ( 'zero-spam' === $domain ) {
					$translated[] = $text;
				}

				return $translation;
			},
			10,
			3
		);

		$this->assertTrue( API_Usage_Alerts::send_test_alert( 'quota_warning', 'email' ) );

		$mail = $this->sent_mail()[0];
		$this->assertSame( get_option( 'admin_email' ), $mail->to[0][0] );
		$this->assertSame( '[Zero Spam] Warning Alert: Lakeside <Dental>', $mail->subject );

		$parts = $this->mail_parts( $mail );
		$this->assertArrayHasKey( 'text/plain', $parts );
		$this->assertArrayHasKey( 'text/html', $parts );
		$html = $parts['text/html'];
		$text = $parts['text/plain'];

		$this->assertStringContainsString( 'This is a test alert from Zero Spam API Monitoring', $text );
		$this->assertStringContainsString( 'Lakeside &lt;Dental&gt;', $html );
		$this->assertStringNotContainsString( 'Lakeside <Dental>', $html );
		$this->assertStringContainsString( 'API calls', $text );
		$this->assertStringContainsString( esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=api-monitoring' ) ), $html, 'The settings link is broken.' );

		foreach ( array( $html, $text ) as $body ) {
			$this->assertDoesNotMatchRegularExpression( '/pricing|upgrade|money-back|\$\d|subscription|billing|Explorer|Essentials|Business|Platform|queries\/month/i', $body, 'The alert sells a plan or links to billing.' );
		}

		foreach ( array( 'API calls', 'Cache hits', 'Errors', 'View API usage', 'Warning' ) as $string ) {
			$this->assertContains( $string, $translated, "\"{$string}\" is not translatable." );
		}
	}

	/**
	 * A critical alert says so in the subject and the heading.
	 */
	public function test_critical_alert_subject(): void {
		$this->set_setting( 'alert_email', 'enabled', 'api-monitoring' );

		$send = new ReflectionMethod( API_Usage_Alerts::class, 'send_email_alert' );
		$send->invoke(
			new API_Usage_Alerts(),
			array(
				'type'     => 'quota_critical',
				'severity' => 'critical',
				'message'  => 'API quota critically low: 4.0% remaining',
				'value'    => 4,
			),
			get_current_blog_id()
		);

		$mail = $this->sent_mail()[0];
		$this->assertStringStartsWith( '[Zero Spam] Critical Alert: ', $mail->subject );
		$this->assertStringContainsString( 'API quota critically low: 4.0% remaining', $this->mail_parts( $mail )['text/plain'] );
	}
}
