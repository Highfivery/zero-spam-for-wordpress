<?php
/**
 * Tests for the controls the settings renderer prints.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Settings as Settings_Page;
use ZeroSpam\Tests\TestCase;

/**
 * Checkbox settings are switches whose input stays in the accessibility tree;
 * radios keep WordPress's control; the "Override & Update" actions are the
 * primary button of their card.
 *
 * @covers \ZeroSpam\Core\Admin\Settings::settings_field
 */
class Test_Settings_Field_Controls extends TestCase {

	/**
	 * Settings page under test.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $page;

	/**
	 * Renders as an administrator on the settings screen.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		$this->page = new Settings_Page();
	}

	/**
	 * Renders one field and parses it.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	private function render( array $args ): DOMXPath {
		ob_start();
		$this->page->settings_field( $args );
		$html = (string) ob_get_clean();

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new DOMXPath( $dom );
	}

	/**
	 * Text content of a node, whitespace trimmed.
	 *
	 * @param DOMNode|null $node Node.
	 */
	private static function text_of( ?DOMNode $node ): string {
		return null === $node ? '' : trim( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
	}

	/**
	 * A checkbox setting is a switch: a focusable input named by its label.
	 */
	public function test_checkbox_renders_a_switch(): void {
		$xpath = $this->render(
			array(
				'label_for'   => 'verify_comments',
				'module'      => 'comments',
				'type'        => 'checkbox',
				'title'       => 'Protect Comments',
				'options'     => array( 'enabled' => 'Check comments for <strong>spam</strong>.' ),
				'value'       => 'enabled',
				'field_class' => 'extra-class',
			)
		);

		$input = $xpath->query( '//div[@class="zerospam-switch"]/input[@id="verify_commentsenabled"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input, 'The input sits in the switch wrapper.' );
		$this->assertSame( 'checkbox', $input->getAttribute( 'type' ) );
		$this->assertSame( 'switch', $input->getAttribute( 'role' ) );
		$this->assertSame( 'zerospam-switch__input extra-class', $input->getAttribute( 'class' ), 'The field class is kept.' );
		$this->assertTrue( $input->hasAttribute( 'checked' ) );
		$this->assertFalse( $input->hasAttribute( 'style' ), 'Nothing hides the input inline.' );

		$label = $xpath->query( '//label[@for="verify_commentsenabled"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $label );
		$this->assertSame( 'zerospam-switch__label', $label->getAttribute( 'class' ) );
		$text = $xpath->query( './/span[@class="zerospam-switch__text"]', $label )->item( 0 );
		$this->assertSame( 'Check comments for spam.', self::text_of( $text ) );
		$this->assertSame( 1, $xpath->query( './/strong', $label )->length, 'Allowed markup in the option text is kept.' );
	}

	/**
	 * A switch without option text is named after its setting for screen readers.
	 */
	public function test_switch_without_option_text_is_named_after_the_setting(): void {
		$xpath = $this->render(
			array(
				'label_for' => 'share_data',
				'type'      => 'checkbox',
				'title'     => 'Usage Data Sharing',
				'options'   => array( 'enabled' => false ),
			)
		);

		$label = $xpath->query( '//label[@for="share_dataenabled"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $label );
		$this->assertSame( 'Usage Data Sharing', self::text_of( $xpath->query( './/span[@class="screen-reader-text"]', $label )->item( 0 ) ) );
		$this->assertSame( 0, $xpath->query( './/span[@class="zerospam-switch__text"]', $label )->length );
		$this->assertFalse( $xpath->query( '//input[@id="share_dataenabled"]' )->item( 0 )->hasAttribute( 'checked' ) );
	}

	/**
	 * Radios keep WordPress's control and a plain label.
	 */
	public function test_radio_keeps_the_native_control(): void {
		$xpath = $this->render(
			array(
				'label_for' => 'block_handler',
				'type'      => 'radio',
				'title'     => 'IP Block Handler',
				'options'   => array(
					'redirect' => 'Send them to another website',
					'403'      => 'Show them an error message',
				),
				'value'     => '403',
			)
		);

		$this->assertSame( 0, $xpath->query( '//*[contains(@class, "zerospam-switch")]' )->length, 'No switch markup for radios.' );
		$radios = $xpath->query( '//input[@type="radio"]' );
		$this->assertSame( 2, $radios->length );
		foreach ( $radios as $radio ) {
			$this->assertInstanceOf( DOMElement::class, $radio );
			$this->assertFalse( $radio->hasAttribute( 'role' ) );
			$this->assertFalse( $radio->hasAttribute( 'class' ) );
		}
		$this->assertTrue( $xpath->query( '//input[@id="block_handler403"]' )->item( 0 )->hasAttribute( 'checked' ) );
		$this->assertSame( 'Show them an error message', self::text_of( $xpath->query( '//label[@for="block_handler403"]' )->item( 0 ) ) );
	}

	/**
	 * A network-locked switch is disabled, keeps its role and points to the lock explanation.
	 */
	public function test_locked_switch_is_disabled_and_described(): void {
		$lock = static function ( bool $disabled, string $key ): bool {
			return 'log_blocked_ips' === $key ? true : $disabled;
		};
		add_filter( 'zerospam_setting_field_disabled', $lock, 10, 2 );

		$xpath = $this->render(
			array(
				'label_for' => 'log_blocked_ips',
				'type'      => 'checkbox',
				'title'     => 'Log Blocked IPs',
				'options'   => array( 'enabled' => 'Enabled' ),
			)
		);

		remove_filter( 'zerospam_setting_field_disabled', $lock, 10 );

		$input = $xpath->query( '//input[@id="log_blocked_ipsenabled"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertTrue( $input->hasAttribute( 'disabled' ) );
		$this->assertSame( 'log_blocked_ips-zerospam-locked', $input->getAttribute( 'aria-describedby' ) );
		$this->assertSame( 'switch', $input->getAttribute( 'role' ) );
	}

	/**
	 * The "Override & Update" actions are primary buttons; regenerating the honeypot is secondary.
	 *
	 * @dataProvider data_action_buttons
	 *
	 * @param string $key      Setting key.
	 * @param string $expected Expected class attribute.
	 */
	public function test_action_buttons_use_the_intended_level( string $key, string $expected ): void {
		$xpath = $this->render(
			array(
				'label_for' => $key,
				'type'      => 'html',
				'title'     => $key,
			)
		);

		$button = $xpath->query( '//*[contains(concat(" ", @class, " "), " button ")]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $button );
		$this->assertSame( $expected, $button->getAttribute( 'class' ) );
	}

	/**
	 * Action settings and the button class they render.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_action_buttons(): array {
		return array(
			'recommended settings'   => array( 'use_recommended_settings', 'button button-primary' ),
			'blocked email domains'  => array( 'update_blocked_email_domains', 'button button-primary' ),
			'core disallowed words'  => array( 'update_disallowed_words', 'button button-primary' ),
			'regenerate honeypot id' => array( 'regenerate_honeypot', 'button' ),
		);
	}
}
