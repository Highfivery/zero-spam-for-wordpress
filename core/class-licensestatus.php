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
	 * Protection levels a network site can have (site_level()).
	 */
	const LEVEL_ENHANCED   = 'enhanced';
	const LEVEL_BASIC      = 'basic';
	const LEVEL_INVALID    = 'invalid';
	const LEVEL_UNVERIFIED = 'unverified';

	/**
	 * A network site's protection level, without asking the API and without
	 * building that site's settings registry (the network statistics list
	 * many sites).
	 *
	 * `enhanced` only when Enhanced Protection is on and the site's last
	 * license answer (its cached status, for the key in use) says the key is
	 * valid (or valid with no queries left); `invalid` when it said the key
	 * is not valid; `unverified` when it is on with a key that has not been
	 * checked recently, or the API could not be reached; `basic` otherwise.
	 * The values follow the precedence of the settings: a locked network
	 * value, the site's own value, the network default, then (for the key)
	 * ZEROSPAM_LICENSE_KEY.
	 *
	 * @since 6.0.0
	 *
	 * @param int $blog_id Site.
	 * @return string One of the LEVEL_* constants.
	 */
	public static function site_level( int $blog_id ): string {
		$network  = is_multisite() ? Settings_Schema::normalize_network_store( get_site_option( \ZeroSpam\Includes\Network_Settings::META_KEY, array() ) )['settings'] : array();
		$switched = is_multisite() && get_current_blog_id() !== $blog_id;

		if ( $switched ) {
			switch_to_blog( $blog_id );
		}

		try {
			$stored  = get_option( 'zero-spam-zerospam', array() );
			$stored  = is_array( $stored ) ? $stored : array();
			$value   = static function ( string $key ) use ( $network, $stored ) {
				$config = isset( $network[ $key ] ) && is_array( $network[ $key ] ) ? $network[ $key ] : array();
				if ( ! empty( $config['locked'] ) ) {
					return $config['value'] ?? null;
				}
				if ( array_key_exists( $key, $stored ) ) {
					return $stored[ $key ];
				}

				return $config['value'] ?? null;
			};
			$enabled = 'enabled' === $value( 'zerospam' );
			$key     = $value( 'zerospam_license' );
			$key     = is_scalar( $key ) && ! is_bool( $key ) ? trim( (string) $key ) : '';

			if ( '' === $key && defined( 'ZEROSPAM_LICENSE_KEY' ) && is_scalar( constant( 'ZEROSPAM_LICENSE_KEY' ) ) ) {
				$key = trim( (string) constant( 'ZEROSPAM_LICENSE_KEY' ) );
			}

			if ( ! $enabled || '' === $key ) {
				return self::LEVEL_BASIC;
			}

			$cached = get_transient( self::TRANSIENT );
			$status = is_array( $cached ) && isset( $cached['hash'], $cached['answer']['status'] ) && md5( $key ) === $cached['hash'] ? (string) $cached['answer']['status'] : '';
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}

		if ( self::VALID === $status || self::QUOTA_EXCEEDED === $status ) {
			return self::LEVEL_ENHANCED;
		}

		return self::INVALID === $status ? self::LEVEL_INVALID : self::LEVEL_UNVERIFIED;
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
	 * Keeps an answer from check() as the cached status of that key, so
	 * saving a key that was just checked asks the API only once.
	 *
	 * @param string               $key    License key the answer is for.
	 * @param array<string, mixed> $answer check() result.
	 */
	public static function remember( string $key, array $answer ): void {
		$key = trim( $key );

		if ( '' === $key || empty( $answer['status'] ) || self::NONE === $answer['status'] ) {
			return;
		}

		// Read on every call by present(), never cached.
		unset( $answer['enabled'], $answer['reset_date'] );

		self::store( md5( $key ), $answer );
		self::$current = array();
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
	 * The quota reset date is the next reset after the API Monitoring "Quota
	 * Reset Date" setting (see quota_period()); the API does not report one.
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
	 * The next quota reset (Y-m-d), or null when no Quota Reset Date is set.
	 */
	private static function reset_date(): ?string {
		$period = self::quota_period();

		return null === $period ? null : $period['next'];
	}

	/**
	 * The current quota period, from the API Monitoring "Quota Reset Date"
	 * setting: the date the quota last reset (`start`) and the next reset
	 * (`next`), both Y-m-d.
	 *
	 * Quotas reset monthly, so a date in the past rolls forward a month at a
	 * time, keeping its day of the month (the last day of a shorter month):
	 * a site that set 2026-01-31 sees 2026-02-28, then 2026-03-31.
	 *
	 * @since 6.0.0
	 *
	 * @param \DateTimeImmutable|null $today Today (default: today in the site's time zone).
	 * @return array{start: string, next: string}|null Null when the setting holds no valid date.
	 */
	public static function quota_period( ?\DateTimeImmutable $today = null ): ?array {
		$resolved = Settings_Schema::resolve( 'quota_reset_date' );
		$value    = null !== $resolved && is_string( $resolved['value'] ) ? trim( $resolved['value'] ) : '';

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return null;
		}

		$today = ( $today ?? current_datetime() )->format( 'Y-m-d' );
		$year  = (int) $parts[1];
		$month = (int) $parts[2];
		$day   = (int) $parts[3];

		// Months after the set date until the next reset is after today (a reset due today has happened; bounded: about 80 years).
		$next = $value;
		for ( $add = 0; $next <= $today && $add < 1000; ++$add ) {
			$next = self::monthly_date( $year, $month + $add + 1, $day );
		}

		$next_parts = array_map( 'intval', explode( '-', $next ) );

		return array(
			'start' => self::monthly_date( $next_parts[0], $next_parts[1] - 1, $day ),
			'next'  => $next,
		);
	}

	/**
	 * A day of a month (Y-m-d); a day the month does not have becomes its last day.
	 *
	 * @param int $year  Year.
	 * @param int $month Month; values outside 1–12 move into the next or previous years.
	 * @param int $day   Day of the month.
	 */
	private static function monthly_date( int $year, int $month, int $day ): string {
		$index  = $year * 12 + $month - 1;
		$year   = intdiv( $index, 12 );
		$month  = $index % 12 + 1;
		$length = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );

		return sprintf( '%04d-%02d-%02d', $year, $month, min( $day, $length ) );
	}
}
