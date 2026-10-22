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
 * radios keep WordPress's control; the setting actions ("Override & Update",
 * "Regenerate Honeypot ID") are neutral secondary buttons, so Save Settings
 * stays the only primary button of a settings view.
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
		$this->assertSame( $label, self::next_element( $input ), 'The label follows the input: the CSS styles it with "input + label".' );
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

		$input = $xpath->query( '//input[@id="share_dataenabled"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertFalse( $input->hasAttribute( 'checked' ) );
		$this->assertSame( $label, self::next_element( $input ), 'The label follows the input.' );
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
		$radio = $xpath->query( '//input[@id="block_handler403"]' )->item( 0 );
		$label = $xpath->query( '//label[@for="block_handler403"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $radio );
		$this->assertTrue( $radio->hasAttribute( 'checked' ) );
		$this->assertSame( 'Show them an error message', self::text_of( $label ) );
		$this->assertSame( $label, self::next_element( $radio ), 'The label follows the radio.' );
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
	 * The setting actions are neutral secondary buttons (plain .button), also while locked.
	 *
	 * @dataProvider data_action_buttons
	 *
	 * @param string $key    Setting key.
	 * @param bool   $locked Whether the network administrator locked the setting.
	 * @param string $tag    Expected element.
	 */
	public function test_action_buttons_are_secondary( string $key, bool $locked, string $tag ): void {
		$lock = static function ( bool $disabled, string $field_key ) use ( $key, $locked ): bool {
			return $locked && $key === $field_key ? true : $disabled;
		};
		add_filter( 'zerospam_setting_field_disabled', $lock, 10, 2 );

		$xpath = $this->render(
			array(
				'label_for' => $key,
				'type'      => 'html',
				'title'     => $key,
			)
		);

		remove_filter( 'zerospam_setting_field_disabled', $lock, 10 );

		$buttons = $xpath->query( '//*[contains(concat(" ", @class, " "), " button ")]' );
		$this->assertSame( 1, $buttons->length );
		$button = $buttons->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $button );
		$this->assertSame( $tag, $button->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		$this->assertSame( 'button', $button->getAttribute( 'class' ), 'Secondary: no button-primary, Save Settings is the only primary.' );
		$this->assertSame( $locked, $button->hasAttribute( 'disabled' ) );
	}

	/**
	 * Action settings, whether they are locked, and the element they render.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: string}>
	 */
	public static function data_action_buttons(): array {
		return array(
			'recommended settings'           => array( 'use_recommended_settings', false, 'a' ),
			'blocked email domains'          => array( 'update_blocked_email_domains', false, 'a' ),
			'blocked email domains (locked)' => array( 'update_blocked_email_domains', true, 'button' ),
			'core disallowed words'          => array( 'update_disallowed_words', false, 'a' ),
			'regenerate honeypot id'         => array( 'regenerate_honeypot', false, 'a' ),
		);
	}

	/**
	 * The next element sibling of a node.
	 *
	 * @param DOMNode $node Node.
	 */
	private static function next_element( DOMNode $node ): ?DOMNode {
		$next = $node->nextSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		while ( null !== $next && XML_ELEMENT_NODE !== $next->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
			$next = $next->nextSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		}

		return $next;
	}
}
