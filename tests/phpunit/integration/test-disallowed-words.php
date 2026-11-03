<?php
/**
 * Tests for Utilities::is_disallowed() and check_fields_for_spam().
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * A word on the spam list matches anywhere in ordinary text, as WordPress's
 * own list does. Inside an email address it must match whole, because a
 * list entry such as "123@" or "ugg" would otherwise refuse real people whose
 * address only happens to contain it.
 *
 * @covers \ZeroSpam\Core\Utilities::is_disallowed
 * @covers \ZeroSpam\Core\Utilities::check_fields_for_spam
 */
class Test_Disallowed_Words extends TestCase {

	/**
	 * Sets the disallowed words list.
	 *
	 * @param string[] $words Entries.
	 */
	private function set_list( array $words ): void {
		update_option( 'disallowed_keys', implode( "\n", $words ) );
		self::reset_static_caches();
	}

	/**
	 * What must and must not match.
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool}> Entry, content, whether it matches.
	 */
	public static function data_matches(): array {
		return array(
			'digits before @ in a real address'     => array( '123@', 'jane123@example.com', false ),
			'digits after a dot in a real address'  => array( '123@', 'jane.123@example.com', false ),
			'digits after a dash in a real address' => array( '123@', 'jane-123@example.com', false ),
			'the whole name before @'               => array( '123@', '123@example.com', true ),
			'an @ entry in an address in a message' => array( '123@', 'Write to jane123@example.com please', false ),
			'a domain start that runs on'           => array( '@123', 'jane@123mail.example.com', false ),
			'a whole domain start'                  => array( '@123', 'jane@123.example.com', true ),
			'a misspelt domain'                     => array( '@gmai.', 'jane@gmai.com', true ),
			'a word inside a name'                  => array( 'ugg', 'hugg@example.com', false ),
			'a word inside a domain'                => array( 'ugg', 'jane@shrugged.example.com', false ),
			'a word as part of a name'              => array( 'viagra', 'buy-viagra@example.com', true ),
			'a word as a domain part'               => array( 'viagra', 'jane@viagra.example.com', true ),
			'upper case, whole'                     => array( 'viagra', 'VIAGRA@example.com', true ),
			'a whole address'                       => array( 'spammer@example.com', 'spammer@example.com', true ),
			'a word inside a word in a message'     => array( 'ugg', 'I love my hugging chair', true ),
			'a word in a message'                   => array( 'viagra', 'Cheap viagra here', true ),
			'a word that is not there'              => array( 'viagra', 'Hello there', false ),
			'html cannot split a word'              => array( 'viagra', 'via<b></b>gra', true ),
		);
	}

	/**
	 * Entries match whole inside email addresses, anywhere in other text.
	 *
	 * @dataProvider data_matches
	 *
	 * @param string $entry   List entry.
	 * @param string $content Content.
	 * @param bool   $matches Whether it matches.
	 */
	public function test_matching( string $entry, string $content, bool $matches ): void {
		$this->set_list( array( $entry ) );

		$this->assertSame( $matches, Utilities::is_disallowed( $content ) );
	}

	/**
	 * A form's fields: a real address passes, the same entry whole is refused.
	 */
	public function test_form_fields(): void {
		$this->set_list( array( '123@' ) );

		$this->assertSame( array(), Utilities::check_fields_for_spam( array( 'email' => 'jane123@example.com' ), false, true ) );
		$this->assertSame( array( 'disallowed_list' ), Utilities::check_fields_for_spam( array( 'email' => '123@example.com' ), false, true ) );
	}

	/**
	 * Allowed words and the minimum length still apply.
	 */
	public function test_allowed_words_and_minimum_length_still_apply(): void {
		$this->set_list( array( 'viagra', 'ab' ) );
		$this->set_setting( 'allowed_words', 'viagra', 'settings' );
		$this->set_setting( 'disallowed_min_length', 3, 'settings' );
		self::reset_static_caches();

		$this->assertFalse( Utilities::is_disallowed( 'Cheap viagra' ) );
		$this->assertFalse( Utilities::is_disallowed( 'abba' ) );
	}
}
