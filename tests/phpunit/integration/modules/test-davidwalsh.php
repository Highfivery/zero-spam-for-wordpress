<?php
/**
 * Tests for the David Walsh token checks and the reasons they report.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Modules\DavidWalsh\DavidWalsh;
use ZeroSpam\Tests\TestCase;

/**
 * DavidWalsh::validate_post() keys its error by why the key was rejected, so
 * a lapsed key (expired or used up) can be told apart from a forged one.
 *
 * @covers \ZeroSpam\Modules\DavidWalsh\DavidWalsh
 */
class Test_DavidWalsh extends TestCase {

	/**
	 * A token signed by this site, issued $age seconds ago.
	 *
	 * @param int $age Seconds since issue (negative: in the future).
	 */
	public static function signed_token( int $age ): string {
		$payload = base_convert( (string) ( time() - $age ), 10, 36 ) . '.' . wp_generate_password( 12, false, false );
		$sign    = new ReflectionMethod( DavidWalsh::class, 'sign' );

		return $payload . '.' . $sign->invoke( null, $payload );
	}

	/**
	 * A fresh token that has already been accepted MAX_USES times.
	 */
	public static function used_up_token(): string {
		$token = DavidWalsh::generate_token();
		set_transient( 'zerospam_dw_' . md5( $token ), DavidWalsh::MAX_USES, HOUR_IN_SECONDS );

		return $token;
	}

	/**
	 * Runs validate_post() on a submitted key.
	 *
	 * @param string|null $key Key, or null to leave the field out.
	 * @return array<string, string> Errors.
	 */
	private function validate( ?string $key ): array {
		$post = null === $key ? array() : array( 'zerospam_david_walsh_key' => $key );

		return ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor()->validate_post( array(), $post, 'comment_spam_message' );
	}

	/**
	 * Submitted keys and the error key each must produce.
	 *
	 * @return array<string, array{0: callable, 1: string|null}>
	 */
	public function data_keys(): array {
		return array(
			'valid'                  => array( array( DavidWalsh::class, 'generate_token' ), null ),
			'missing'                => array( static fn() => null, 'zerospam_david_walsh' ),
			'expired (signed)'       => array( static fn() => self::signed_token( DavidWalsh::KEY_TTL + 120 ), 'zerospam_david_walsh_expired' ),
			'used up (signed)'       => array( array( self::class, 'used_up_token' ), 'zerospam_david_walsh_exhausted' ),
			'forged signature'       => array( static fn() => substr( DavidWalsh::generate_token(), 0, -32 ) . str_repeat( 'a', 32 ), 'zerospam_david_walsh_invalid' ),
			'expired, forged'        => array( static fn() => substr( self::signed_token( DavidWalsh::KEY_TTL + 120 ), 0, -32 ) . str_repeat( 'b', 32 ), 'zerospam_david_walsh_invalid' ),
			'issued in the future'   => array( static fn() => self::signed_token( -600 ), 'zerospam_david_walsh_invalid' ),
			'malformed'              => array( static fn() => 'not-a-key', 'zerospam_david_walsh_invalid' ),
			'used up after 3 checks' => array(
				static function () {
					$token = DavidWalsh::generate_token();
					set_transient( 'zerospam_dw_' . md5( $token ), DavidWalsh::MAX_USES - 1, HOUR_IN_SECONDS );
					return $token;
				},
				null,
			),
		);
	}

	/**
	 * The error key says why a key was rejected; only this site's own
	 * expired or used-up keys count as lapsed.
	 *
	 * @dataProvider data_keys
	 *
	 * @param callable    $make     Builds the submitted key.
	 * @param string|null $expected Expected error key, or null for no error.
	 */
	public function test_validate_post_keys_the_error_by_reason( callable $make, ?string $expected ): void {
		$errors = $this->validate( $make() );

		if ( null === $expected ) {
			$this->assertSame( array(), $errors );
			return;
		}

		$this->assertSame( array( $expected ), array_keys( $errors ) );

		$lapsed = in_array( $expected, array( 'zerospam_david_walsh', 'zerospam_david_walsh_expired', 'zerospam_david_walsh_exhausted' ), true );
		$this->assertSame( $lapsed, DavidWalsh::is_lapsed_error( $expected ) );
	}

	/**
	 * The validate_token() method still answers true or false.
	 */
	public function test_validate_token_stays_boolean(): void {
		$this->assertTrue( DavidWalsh::validate_token( DavidWalsh::generate_token() ) );
		$this->assertFalse( DavidWalsh::validate_token( self::signed_token( DavidWalsh::KEY_TTL + 120 ) ) );
		$this->assertFalse( DavidWalsh::validate_token( '' ) );
	}

	/**
	 * Every reason has a label in the detection log.
	 */
	public function test_every_reason_has_a_failed_type_label(): void {
		$types = ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor()->failed_types( array() );

		foreach ( array( 'david_walsh', 'david_walsh_expired', 'david_walsh_exhausted', 'david_walsh_invalid' ) as $failed ) {
			$this->assertArrayHasKey( $failed, $types );
		}
	}

	/**
	 * Forms without a retry path (here: comments) still block an expired
	 * key, as they block a missing one, and log the specific reason.
	 */
	public function test_comment_with_an_expired_key_is_blocked_and_logged_with_the_reason(): void {
		$this->set_setting( 'log_blocked_comments', 'enabled', 'comments' );
		$davidwalsh = ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor();
		add_filter( 'zerospam_preprocess_comment_submission', array( $davidwalsh, 'validate_post' ), 10, 3 );

		$_POST = array( 'zerospam_david_walsh_key' => self::signed_token( DavidWalsh::KEY_TTL + 120 ) );

		try {
			( new Comments() )->preprocess_comments(
				array(
					'comment_author'       => 'Visitor',
					'comment_author_email' => 'visitor@example.org',
					'comment_content'      => 'Hello there.',
				)
			);
			$this->fail( 'The comment was not blocked.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'comment' ) ) );
		}

		global $wpdb;
		$data = (array) json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT submission_data FROM %i WHERE log_type = %s', $this->log_table(), 'comment' ) ), true );
		$this->assertSame( 'david_walsh_expired', $data['failed'] ?? null );
	}
}
