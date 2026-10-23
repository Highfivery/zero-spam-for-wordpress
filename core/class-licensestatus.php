<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps License_Status to this file name.
/**
 * Status of the Zero Spam license.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

use ZeroSpam\Modules\Zero_Spam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * One answer to "is Enhanced Protection licensed?" for every screen.
 *
 * Before 6.0 the admin header, the dashboard widget and the notices each
 * worked it out differently (two API endpoints, different cache times), so
 * they could disagree. They all read this service now. It asks the Zero Spam
 * API through Zero_Spam::get_license() (`v2/get-license`).
 *
 * Statuses: `none` (no key), `valid`, `invalid`, `unreachable` (the API did
 * not answer; protection stays on) and `quota_exceeded` (valid key with no
 * queries left). The answer is cached per site in a transient for 12 hours,
 * 15 minutes when the API was unreachable, and refreshed when the key changes.
 *
 * @since 6.0.0
 */
final class License_Status {

	/**
	 * Statuses.
	 */
	const NONE           = 'none';
	const VALID          = 'valid';
	const INVALID        = 'invalid';
	const UNREACHABLE    = 'unreachable';
	const QUOTA_EXCEEDED = 'quota_exceeded';

	/**
	 * Transient holding the last answer.
	 *
	 * @var string
	 */
	const TRANSIENT = 'zerospam_license_status';

	/**
	 * How long an answer is kept.
	 *
	 * @var int
	 */
	const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * How long an "unreachable" answer is kept.
	 *
	 * @var int
	 */
	const UNREACHABLE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Plan names by the product IDs the API reports.
	 *
	 * @var array<string, string>
	 */
	const PLANS = array(
		'ZEROSPAMEXPLORER'     => 'Explorer',
		'ZEROSPAMESSENTIALS'   => 'Essentials',
		'ZEROSPAMESSENTIALSYR' => 'Essentials',
		'ZEROSPAMBUSINESS'     => 'Business',
		'ZEROSPAMBUSINESSYR'   => 'Business',
		'ZEROSPAMPLATFORM'     => 'Platform',
		'ZEROSPAMPLATFORMYR'   => 'Platform',
	);

	/**
	 * Answers computed in this request, by blog ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $current = array();

	/**
	 * The license key in use (setting, network value or ZEROSPAM_LICENSE_KEY).
	 */
	public static function key(): string {
		$resolved = Settings_Schema::resolve( 'zerospam_license' );
		$value    = null !== $resolved && is_scalar( $resolved['value'] ) && ! is_bool( $resolved['value'] ) ? (string) $resolved['value'] : '';

		return trim( $value );
	}

	/**
	 * Whether a license key is set. Makes no request.
	 */
	public static function has_key(): bool {
		return '' !== self::key();
	}

	/**
	 * Whether the Enhanced Protection setting is on. Makes no request.
	 */
	public static function is_enabled(): bool {
		$resolved = Settings_Schema::resolve( 'zerospam' );

		return null !== $resolved && 'enabled' === $resolved['value'];
	}

	/**
	 * The license status, from the cache when it is fresh.
	 *
	 * @return array{status: string, enabled: bool, plan: array{id: string, name: string}|null, queries: array{limit: int, used: int, remaining: int}|null, reset_date: string|null, renewal: array{status: string|null, date: string|null}, checked_at: int|null}
	 */
	public static function get(): array {
		$blog_id = get_current_blog_id();
		$key     = self::key();
		$hash    = '' === $key ? '' : md5( $key );

		if ( isset( self::$current[ $blog_id ] ) && self::$current[ $blog_id ]['hash'] === $hash ) {
			return self::present( self::$current[ $blog_id ]['answer'] );
		}

		if ( '' === $key ) {
			$answer = self::answer( self::NONE );
		} else {
			$cached = get_transient( self::TRANSIENT );
			$answer = is_array( $cached ) && isset( $cached['hash'], $cached['answer'] ) && $cached['hash'] === $hash && is_array( $cached['answer'] )
				? $cached['answer']
				: self::store( $hash, self::evaluate( $key ) );
		}

		self::$current[ $blog_id ] = array(
			'hash'   => $hash,
			'answer' => $answer,
		);

		return self::present( $answer );
	}

	/**
	 * Asks the API again, ignoring every cache.
	 *
	 * @return array<string, mixed> As get().
	 */
	public static function refresh(): array {
		self::reset_cache();

		return self::get();
	}

	/**
	 * Checks a key that is not stored yet (for example before saving it).
	 *
	 * Asks the API without its cache. Nothing is stored.
	 *
	 * @param string $key License key.
	 * @return array<string, mixed> As get().
	 */
	public static function check( string $key ): array {
		$key = trim( $key );

		if ( '' === $key ) {
			return self::present( self::answer( self::NONE ) );
		}

		Zero_Spam::delete_license_cache( $key );

		return self::present( self::evaluate( $key ) );
	}

	/**
	 * Forgets the cached answers, the API answer for the current key included.
	 */
	public static function reset_cache(): void {
		$key = self::key();

		if ( '' !== $key ) {
			Zero_Spam::delete_license_cache( $key );
		}

		delete_transient( self::TRANSIENT );
		self::$current = array();
	}

	/**
	 * Forgets the answers kept in this request only (between tests).
	 */
	public static function reset_request_cache(): void {
		self::$current = array();
	}

	/**
	 * Asks the API about a key.
	 *
	 * @param string $key License key.
	 * @return array<string, mixed> Answer (see answer()).
	 */
	private static function evaluate( string $key ): array {
		$license = Zero_Spam::get_license( $key );

		if ( false === $license ) {
			return self::answer( self::UNREACHABLE );
		}

		if ( ! is_array( $license ) || empty( $license['license_key'] ) ) {
			return self::answer( self::INVALID );
		}

		$queries = null;
		if ( isset( $license['queries_limit'] ) || isset( $license['queries_made'] ) || isset( $license['queries_remaining'] ) ) {
			$queries = array(
				'limit'     => (int) ( $license['queries_limit'] ?? 0 ),
				'used'      => (int) ( $license['queries_made'] ?? 0 ),
				'remaining' => (int) ( $license['queries_remaining'] ?? 0 ),
			);
		}

		$status = isset( $license['queries_remaining'] ) && (int) $license['queries_remaining'] <= 0 ? self::QUOTA_EXCEEDED : self::VALID;

		$plan = null;
		if ( ! empty( $license['product_id'] ) && is_scalar( $license['product_id'] ) ) {
			$id   = strtoupper( sanitize_key( (string) $license['product_id'] ) );
			$plan = array(
				'id'   => $id,
				'name' => self::PLANS[ $id ] ?? '',
			);
		}

		$answer            = self::answer( $status );
		$answer['plan']    = $plan;
		$answer['queries'] = $queries;

		if ( ! empty( $license['subscription_status'] ) && is_scalar( $license['subscription_status'] ) ) {
			$answer['renewal']['status'] = sanitize_key( (string) $license['subscription_status'] );
		}

		return $answer;
	}

	/**
	 * Caches an answer for the current site.
	 *
	 * @param string               $hash   Hash of the key it is for.
	 * @param array<string, mixed> $answer Answer.
	 * @return array<string, mixed> The answer.
	 */
	private static function store( string $hash, array $answer ): array {
		set_transient(
			self::TRANSIENT,
			array(
				'hash'   => $hash,
				'answer' => $answer,
			),
			self::UNREACHABLE === $answer['status'] ? self::UNREACHABLE_TTL : self::TTL
		);

		return $answer;
	}

	/**
	 * An answer with only a status.
	 *
	 * @param string $status Status.
	 * @return array<string, mixed>
	 */
	private static function answer( string $status ): array {
		return array(
			'status'     => $status,
			'plan'       => null,
			'queries'    => null,
			'renewal'    => array(
				'status' => null,
				'date'   => null,
			),
			'checked_at' => self::NONE === $status ? null : time(),
		);
	}

	/**
	 * Adds what is read from settings on every call (not cached).
	 *
	 * The quota reset date is the API Monitoring "Quota Reset Date" setting
	 * when it holds a valid date; the API does not report one.
	 *
	 * @param array<string, mixed> $answer Answer.
	 * @return array<string, mixed>
	 */
	private static function present( array $answer ): array {
		$answer['enabled']    = self::is_enabled();
		$answer['reset_date'] = self::reset_date();

		return $answer;
	}

	/**
	 * The quota reset date set in API Monitoring (Y-m-d), or null.
	 */
	private static function reset_date(): ?string {
		$resolved = Settings_Schema::resolve( 'quota_reset_date' );
		$value    = null !== $resolved && is_string( $resolved['value'] ) ? trim( $resolved['value'] ) : '';

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return null;
		}

		return $value;
	}
}
