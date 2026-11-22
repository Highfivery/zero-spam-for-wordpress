<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Card_Testing to this file name.
/**
 * Card-testing limits for WooCommerce checkouts.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Modules\WooCommerce;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Counts failed payments per visitor and across the store.
 *
 * Card testers have to fail many times to learn which cards work. Failed
 * payments are counted per IP address, per customer account and per billing
 * email; at the limit within an hour, that key is paused at checkout (only
 * checkout: the rest of the site stays open). When failed payments pile up
 * across the store, from many addresses at once, a surge starts: for half an
 * hour, orders that did not come from the store's own checkout page are
 * refused (see Checkout_Token).
 *
 * - An IP address only counts when it is a public address that visitors do
 *   not share: not private or reserved, and not the address the shared-IP
 *   check found (an untrusted proxy or CDN in front of the site), or one
 *   customer's declined cards would pause every guest.
 * - A pause on the billing email alone only refuses orders that did not come
 *   from the checkout page, since anyone can type someone else's email; and an
 *   email that already paid an order on the store is never counted.
 * - Counters and the surge live in one non-autoloaded option, written under a
 *   short lock so failures that arrive at the same time (gateway webhooks)
 *   are all counted. Each pause is a transient of its own, so no write can
 *   drop one. Everything is keyed by keyed hashes, never the address, email
 *   or account ID itself.
 *
 * Nothing here runs on a page view: it is read at checkout and written when a
 * payment fails.
 *
 * @since 6.0.2
 */
final class Card_Testing {

	/**
	 * The option that holds the counters and the surge.
	 */
	public const OPTION = 'zerospam_card_testing';

	/**
	 * The option used as the write lock.
	 */
	public const LOCK = 'zerospam_card_testing_lock';

	/**
	 * The prefix of a pause's transient.
	 */
	public const PAUSE_PREFIX = 'zerospam_ct_pause_';

	/**
	 * The cron event that emails the administrator about a surge.
	 */
	public const ALERT_EVENT = 'zerospam_card_testing_surge_alert';

	/**
	 * How long failed payments count towards a visitor's limit.
	 */
	public const WINDOW = 3600;

	/**
	 * How long failed payments count towards a surge.
	 */
	public const SURGE_WINDOW = 600;

	/**
	 * How long a surge lasts.
	 */
	public const SURGE_LENGTH = 1800;

	/**
	 * Most counters kept; the oldest go first.
	 */
	public const MAX_ENTRIES = 1000;

	/**
	 * Default failed payments before a visitor is paused.
	 */
	public const DEFAULT_FAILURES = 3;

	/**
	 * Default pause, in minutes.
	 */
	public const DEFAULT_MINUTES = 60;

	/**
	 * Default failed payments across the store, per 10 minutes, that start a surge.
	 */
	public const DEFAULT_SURGE = 10;

	/**
	 * The time used instead of time(), for tests.
	 *
	 * @var int|null
	 */
	public static ?int $now = null;

	/**
	 * Whether the defence is on (and the checkout protected at all).
	 *
	 * "Stop card testing" is on unless turned off: a missing value or
	 * `enabled` is on, anything else is off.
	 */
	public static function enabled(): bool {
		if ( 'enabled' !== \ZeroSpam\Core\Settings_Schema::value( 'verify_woocommerce_checkout' ) ) {
			return false;
		}

		$value = \ZeroSpam\Core\Settings_Schema::value( 'woocommerce_card_testing' );

		return null === $value || 'enabled' === $value;
	}

	/**
	 * Failed payments before a visitor is paused (2 to 20).
	 */
	public static function failures_allowed(): int {
		return self::bounded( 'woocommerce_card_testing_failures', self::DEFAULT_FAILURES, 2, 20 );
	}

	/**
	 * How long a pause lasts, in seconds (5 minutes to a day).
	 */
	public static function pause_seconds(): int {
		return self::bounded( 'woocommerce_card_testing_minutes', self::DEFAULT_MINUTES, 5, 1440 ) * 60;
	}

	/**
	 * Failed payments across the store, per 10 minutes, that start a surge (3 to 500).
	 */
	public static function surge_threshold(): int {
		return self::bounded( 'woocommerce_card_testing_surge', self::DEFAULT_SURGE, 3, 500 );
	}

	/**
	 * Records a failed payment.
	 *
	 * An unverified attempt (one that did not come from the checkout page)
	 * needs one failure fewer before the visitor is paused, never fewer than two.
	 *
	 * @param string $ip          The visitor's IP address, or ''.
	 * @param string $email       The billing email, or ''.
	 * @param int    $user        The customer's account ID, or 0.
	 * @param bool   $unverified  Whether the attempt did not come from the checkout page.
	 * @param bool   $count_email Whether the email counts (false when it already paid an order).
	 * @return array{paused: string[], surge_started: bool}
	 */
	public static function record_failure( string $ip, string $email, int $user = 0, bool $unverified = false, bool $count_email = true ): array {
		$result = array(
			'paused'        => array(),
			'surge_started' => false,
		);

		$keys  = self::keys( $ip, $count_email ? $email : '', $user );
		$now   = self::now();
		$limit = $unverified ? max( 2, self::failures_allowed() - 1 ) : self::failures_allowed();

		self::with_lock(
			static function () use ( $keys, $now, $limit, &$result ): void {
				$state = self::state();

				foreach ( $keys as $kind => $key ) {
					$entry = $state['failures'][ $key ] ?? null;

					if ( ! is_array( $entry ) || (int) $entry['first'] <= $now - self::WINDOW ) {
						$entry = array(
							'count' => 0,
							'first' => $now,
						);
					}

					++$entry['count'];

					if ( $entry['count'] >= $limit ) {
						set_transient( self::PAUSE_PREFIX . $key, $now + self::pause_seconds(), self::pause_seconds() );
						unset( $state['failures'][ $key ] );
						$result['paused'][] = $kind;
					} else {
						$state['failures'][ $key ] = $entry;
					}
				}

				$state['store'][] = $now;
				$state['store']   = array_slice(
					array_values(
						array_filter(
							$state['store'],
							static fn( $time ): bool => (int) $time > $now - self::SURGE_WINDOW
						)
					),
					-self::surge_threshold()
				);

				if ( count( $state['store'] ) >= self::surge_threshold() && $state['surge_until'] <= $now ) {
					$state['surge_until']    = $now + self::SURGE_LENGTH;
					$result['surge_started'] = true;
				}

				self::save( $state );
			}
		);

		if ( $result['surge_started'] && ! wp_next_scheduled( self::ALERT_EVENT ) ) {
			wp_schedule_single_event( $now, self::ALERT_EVENT );
		}

		return $result;
	}

	/**
	 * What is paused for this visitor: `ip`, `user` or `email` (in that
	 * order), or '' when nothing is.
	 *
	 * @param string $ip    The visitor's IP address, or ''.
	 * @param string $email The billing email, or ''.
	 * @param int    $user  The customer's account ID, or 0.
	 */
	public static function paused_by( string $ip, string $email, int $user = 0 ): string {
		$now = self::now();

		foreach ( self::keys( $ip, $email, $user ) as $kind => $key ) {
			$until = get_transient( self::PAUSE_PREFIX . $key );

			if ( is_numeric( $until ) && (int) $until > $now ) {
				return $kind;
			}
		}

		return '';
	}

	/**
	 * Whether checkout is paused for this visitor on any key.
	 *
	 * @param string $ip    The visitor's IP address, or ''.
	 * @param string $email The billing email, or ''.
	 * @param int    $user  The customer's account ID, or 0.
	 */
	public static function is_paused( string $ip, string $email, int $user = 0 ): bool {
		return '' !== self::paused_by( $ip, $email, $user );
	}

	/**
	 * Whether a surge is under way.
	 */
	public static function surge_active(): bool {
		return self::state()['surge_until'] > self::now();
	}

	/**
	 * When the current surge ends (a Unix time), or 0.
	 */
	public static function surge_until(): int {
		$until = self::state()['surge_until'];

		return $until > self::now() ? $until : 0;
	}

	/**
	 * Ends every pause and the surge, and forgets the counters.
	 *
	 * The pauses' transients cannot be listed (they may live in an object
	 * cache), so a new generation makes every earlier key, and its pause, stale.
	 */
	public static function clear(): void {
		update_option( self::OPTION, array( 'generation' => wp_generate_password( 12, false ) ), false );
	}

	/**
	 * The keys of a visitor: keyed hashes, never the values.
	 *
	 * @param string $ip    IP address, or ''.
	 * @param string $email Email, or ''.
	 * @param int    $user  Account ID, or 0.
	 * @return array<string, string> Kind (ip, user, email) => key.
	 */
	private static function keys( string $ip, string $email, int $user ): array {
		$keys = array();

		$ip = self::countable_ip( $ip );
		if ( '' !== $ip ) {
			$keys['ip'] = self::hash( 'ip|' . $ip );
		}

		if ( $user > 0 ) {
			$keys['user'] = self::hash( 'user|' . $user );
		}

		$email = strtolower( trim( $email ) );
		if ( '' !== $email && is_email( $email ) ) {
			$keys['email'] = self::hash( 'email|' . $email );
		}

		return $keys;
	}

	/**
	 * The address in canonical form when it identifies one visitor, or ''.
	 *
	 * @param string $ip IP address.
	 */
	private static function countable_ip( string $ip ): string {
		$ip = \ZeroSpam\Core\User::normalize_ip( trim( $ip ) );

		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}

		// The address many visitors share (the shared-IP check's last verdict; never computed here).
		$shared = get_transient( \ZeroSpam\Core\Admin\Shared_IP_Warning::TRANSIENT );
		if ( is_array( $shared ) && ! empty( $shared['ip'] ) && is_string( $shared['ip'] ) && \ZeroSpam\Core\User::normalize_ip( $shared['ip'] ) === $ip ) {
			return '';
		}

		return $ip;
	}

	/**
	 * A short keyed hash, specific to the current generation (see clear()).
	 *
	 * @param string $value Value.
	 */
	private static function hash( string $value ): string {
		return substr( hash_hmac( 'sha256', self::state()['generation'] . '|' . $value, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * The stored counters and surge, without expired entries.
	 *
	 * @return array{generation: string, failures: array<string, array{count: int, first: int}>, store: int[], surge_until: int}
	 */
	private static function state(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$now    = self::now();

		$failures = isset( $stored['failures'] ) && is_array( $stored['failures'] ) ? $stored['failures'] : array();
		$failures = array_filter(
			$failures,
			static fn( $entry ): bool => is_array( $entry ) && isset( $entry['count'], $entry['first'] ) && (int) $entry['first'] > $now - self::WINDOW
		);

		return array(
			'generation'  => isset( $stored['generation'] ) && is_string( $stored['generation'] ) ? $stored['generation'] : '',
			'failures'    => $failures,
			'store'       => isset( $stored['store'] ) && is_array( $stored['store'] ) ? array_map( 'intval', $stored['store'] ) : array(),
			'surge_until' => isset( $stored['surge_until'] ) ? (int) $stored['surge_until'] : 0,
		);
	}

	/**
	 * Saves the counters, capped at MAX_ENTRIES.
	 *
	 * @param array{generation: string, failures: array<string, array{count: int, first: int}>, store: int[], surge_until: int} $state State.
	 */
	private static function save( array $state ): void {
		if ( count( $state['failures'] ) > self::MAX_ENTRIES ) {
			uasort( $state['failures'], static fn( array $a, array $b ): int => (int) $b['first'] <=> (int) $a['first'] );
			$state['failures'] = array_slice( $state['failures'], 0, self::MAX_ENTRIES, true );
		}

		if ( get_option( self::OPTION ) !== $state && ! update_option( self::OPTION, $state, false ) ) {
			\ZeroSpam\Core\Utilities::log( 'Card testing: could not save the failed-payment counters.' );
		}
	}

	/**
	 * Runs a read-modify-write of the counters under a short lock.
	 *
	 * The lock is an option added with add_option(), which only one request
	 * can create; a lock older than 10 seconds (a request that died) is
	 * taken over. After about two seconds of waiting the write goes ahead
	 * unlocked rather than lose the failure.
	 *
	 * @param callable $write The write.
	 */
	private static function with_lock( callable $write ): void {
		$locked = false;

		for ( $try = 0; $try < 40; $try++ ) {
			if ( add_option( self::LOCK, (string) time(), '', false ) ) {
				$locked = true;
				break;
			}

			$since = (int) get_option( self::LOCK );
			if ( $since > 0 && $since < time() - 10 ) {
				delete_option( self::LOCK );
				continue;
			}

			usleep( 50000 );
			wp_cache_delete( self::LOCK, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}

		// Read the counters as stored now, not as cached earlier in this request.
		wp_cache_delete( self::OPTION, 'options' );

		try {
			$write();
		} finally {
			if ( $locked ) {
				delete_option( self::LOCK );
			}
		}
	}

	/**
	 * A whole-number setting within bounds, or its default.
	 *
	 * @param string $key      Setting key.
	 * @param int    $fallback Default.
	 * @param int    $min      Smallest value.
	 * @param int    $max      Largest value.
	 */
	private static function bounded( string $key, int $fallback, int $min, int $max ): int {
		$value = \ZeroSpam\Core\Settings_Schema::value( $key );

		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * The current time.
	 */
	public static function now(): int {
		return self::$now ?? time();
	}
}
