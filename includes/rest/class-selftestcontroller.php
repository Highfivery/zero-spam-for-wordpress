<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Self_Test_Controller to this file name.
/**
 * REST route that checks the site's protection works.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Access;
use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\User;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\DB;
use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Modules\DavidWalsh\DavidWalsh;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/self-test`: runs a few quick checks in this request and says
 * for each whether it passed, needs a look (`warn`) or failed.
 *
 * - `honeypot`: a comment with the honeypot field filled is stopped by the
 *   real comment check (nothing is logged or shared);
 * - `browser_check`: a submission without a browser check key is refused;
 * - `lists`: how many blocked email domains, disallowed and allowed words,
 *   allowed IPs, blocked IPs and blocked locations are loaded;
 * - `reputation`: the reputation services that are on answer (Stop Forum
 *   Spam, and the Zero Spam network with Enhanced Protection), asked about a
 *   documentation-only address (192.0.2.1);
 * - `ip_source`: where the visitor address comes from.
 *
 * The self-test writes nothing to the detection log. It runs at most once a
 * minute per site.
 *
 * @since 6.0.0
 */
class Self_Test_Controller extends Base_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'self-test';

	/**
	 * Transient that limits the self-test to one run a minute per site.
	 *
	 * @var string
	 */
	const LOCK_TRANSIENT = 'zerospam_self_test_lock';

	/**
	 * Seconds between two runs.
	 *
	 * @var int
	 */
	const LOCK_SECONDS = MINUTE_IN_SECONDS;

	/**
	 * Address used for the reputation lookups (TEST-NET-1, never routable).
	 *
	 * @var string
	 */
	const TEST_IP = '192.0.2.1';

	/**
	 * Seconds a service may take to answer.
	 *
	 * @var int
	 */
	const TIMEOUT = 5;

	/**
	 * Timeout of the Zero Spam network test lookup, in seconds.
	 *
	 * @var int
	 */
	const PING_TIMEOUT = 3;

	/**
	 * A service answering more slowly than this (ms) is reported with a warning.
	 *
	 * @var int
	 */
	const SLOW_MS = 2000;


	/**
	 * Step results, worst last.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'pass', 'warn', 'fail' );

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Runs the self-test.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run( $request ) {
		unset( $request );

		$started = get_transient( self::LOCK_TRANSIENT );
		$now     = time();

		if ( is_numeric( $started ) && $now - (int) $started < self::LOCK_SECONDS ) {
			$retry = max( 1, self::LOCK_SECONDS - ( $now - (int) $started ) );

			return new WP_Error(
				'zerospam_self_test_rate_limited',
				/* translators: %d: number of seconds */
				sprintf( _n( 'The self-test just ran. Try again in %d second.', 'The self-test just ran. Try again in %d seconds.', $retry, 'zero-spam' ), $retry ),
				array(
					'status'      => 429,
					'retry_after' => $retry,
				)
			);
		}

		set_transient( self::LOCK_TRANSIENT, $now, self::LOCK_SECONDS );

		$steps = array(
			$this->timed( 'honeypot', array( $this, 'check_honeypot' ) ),
			$this->timed( 'browser_check', array( $this, 'check_browser' ) ),
			$this->timed( 'lists', array( $this, 'check_lists' ) ),
			$this->timed( 'reputation', array( $this, 'check_reputation' ) ),
			$this->timed( 'ip_source', array( $this, 'check_ip_source' ) ),
		);

		$worst = 0;
		foreach ( $steps as $step ) {
			$worst = max( $worst, (int) array_search( $step['status'], self::STATUSES, true ) );
		}

		$response = rest_ensure_response(
			array(
				'status' => self::STATUSES[ $worst ],
				'steps'  => $steps,
				'ran_at' => gmdate( 'c', $now ),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Runs one step and measures how long it took.
	 *
	 * @param string   $id    Step ID.
	 * @param callable $check Returns status, message and any extra fields.
	 * @return array<string, mixed>
	 */
	private function timed( string $id, callable $check ): array {
		$start  = microtime( true );
		$result = $check();

		return array_merge(
			array(
				'id'      => $id,
				'status'  => $result['status'],
				'message' => $result['message'],
				'ms'      => (int) round( ( microtime( true ) - $start ) * 1000 ),
			),
			array_diff_key( $result, array_flip( array( 'status', 'message' ) ) )
		);
	}

	/**
	 * A comment with the honeypot field filled is stopped.
	 *
	 * Runs the comment module's own check twice, without and with the trap
	 * filled, with logging and sharing turned off for the duration and the
	 * stop intercepted (Access::intercept_termination()). Any other check
	 * that adds an error through `zerospam_preprocess_comment_submission`
	 * (the browser check) is set aside, so only the honeypot can stop the
	 * trapped comment.
	 *
	 * @return array<string, mixed>
	 */
	private function check_honeypot(): array {
		$enabled = 'enabled' === $this->setting( 'verify_comments' );

		global $wpdb;
		$log_table = $wpdb->prefix . DB::$tables['log'];
		$inserts   = 0;
		$spy       = static function ( $query ) use ( &$inserts, $log_table ) {
			if ( is_string( $query ) && 1 === preg_match( '/^\s*(INSERT|REPLACE)\b/i', $query ) && false !== strpos( $query, $log_table ) ) {
				++$inserts;
			}

			return $query;
		};

		add_filter( 'query', $spy );
		try {
			$control = $this->comment_is_stopped( false );
			$trapped = $this->comment_is_stopped( true );
		} finally {
			remove_filter( 'query', $spy );
		}

		if ( $inserts > 0 ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The test comment was written to the activity log. Please report this to Zero Spam support.', 'zero-spam' ),
			);
		}

		if ( ! $trapped ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'A comment with the honeypot field filled in was let through.', 'zero-spam' ),
			);
		}

		if ( $control ) {
			return array(
				'status'  => 'warn',
				'message' => __( 'Comments are stopped even without the honeypot filled in, so the honeypot could not be checked on its own. Check your disallowed words.', 'zero-spam' ),
			);
		}

		if ( ! $enabled ) {
			return array(
				'status'  => 'warn',
				'message' => __( 'The honeypot works, but comment protection is off, so comments are not checked.', 'zero-spam' ),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => __( 'A comment with the honeypot field filled in was stopped.', 'zero-spam' ),
		);
	}

	/**
	 * Whether the comment check stops a test comment.
	 *
	 * Access::intercept_termination() turns the stop into an answer: no page,
	 * no header and no wp_die() reach this REST response.
	 *
	 * @param bool $fill_trap Whether to fill the honeypot field.
	 */
	private function comment_is_stopped( bool $fill_trap ): bool {
		$post_backup = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Saved to restore it; not read.
		$honeypot    = Utilities::get_honeypot();

		$quiet = static function ( $settings ) {
			foreach ( array( 'log_blocked_comments', 'share_data' ) as $key ) {
				if ( is_array( $settings ) && isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
					$settings[ $key ]['value'] = false;
				}
			}

			return $settings;
		};

		$no_other_checks = static function (): array {
			return array();
		};

		add_filter( 'zerospam_settings', $quiet, PHP_INT_MAX );
		add_filter( 'zerospam_preprocess_comment_submission', $no_other_checks, PHP_INT_MAX );

		$_POST = array();
		if ( $fill_trap ) {
			$_POST[ $honeypot ] = 'zero-spam-self-test';
		}

		try {
			return Access::intercept_termination(
				static function (): void {
					( new Comments() )->preprocess_comments(
						array(
							'comment_author'       => '',
							'comment_author_email' => '',
							'comment_author_url'   => '',
							'comment_content'      => '',
							'comment_agent'        => '',
						)
					);
				}
			);
		} finally {
			$_POST = $post_backup;

			remove_filter( 'zerospam_settings', $quiet, PHP_INT_MAX );
			remove_filter( 'zerospam_preprocess_comment_submission', $no_other_checks, PHP_INT_MAX );

			Settings::$settings = array();
			Settings_Schema::reset_cache();
		}
	}

	/**
	 * A submission without a browser check key is refused.
	 *
	 * Asks about an empty key only, which stores nothing.
	 *
	 * @return array<string, mixed>
	 */
	private function check_browser(): array {
		$rejected = DavidWalsh::STATUS_VALID !== DavidWalsh::get_token_status( '' );

		if ( ! $rejected ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'A form sent without the browser check key was accepted.', 'zero-spam' ),
			);
		}

		if ( 'enabled' !== $this->setting( 'davidwalsh' ) ) {
			return array(
				'status'  => 'warn',
				'message' => __( 'The browser check is off. Turn it on to stop bots that do not run JavaScript.', 'zero-spam' ),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => __( 'Forms sent without the browser check key are refused.', 'zero-spam' ),
		);
	}

	/**
	 * Counts what the lists hold.
	 *
	 * @return array<string, mixed>
	 */
	private function check_lists(): array {
		global $wpdb;

		$domains = Utilities::get_blocked_email_domains();
		$counts  = array(
			'blocked_email_domains' => is_array( $domains ) ? count( $domains ) : 0,
			'disallowed_words'      => $this->count_lines( get_option( 'disallowed_keys', '' ) ),
			'allowed_words'         => $this->count_lines( $this->setting( 'allowed_words' ) ),
			'allowed_ips'           => $this->count_lines( $this->setting( 'ip_whitelist' ) ),
			'blocked_ips'           => 0,
			'blocked_locations'     => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One grouped count of the plugin's own table, on demand.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT key_type, COUNT(*) AS total FROM %i GROUP BY key_type', $wpdb->prefix . DB::$tables['blocked'] ), ARRAY_A );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$bucket             = 'ip' === $row['key_type'] ? 'blocked_ips' : 'blocked_locations';
			$counts[ $bucket ] += (int) $row['total'];
		}

		$message = sprintf(
			/* translators: 1: blocked email domains, 2: disallowed words, 3: allowed words, 4: allowed IP addresses, 5: blocked IP addresses, 6: blocked locations */
			__( 'Loaded %1$s blocked email domains, %2$s disallowed words, %3$s allowed words, %4$s allowed IP addresses, %5$s blocked IP addresses and %6$s blocked locations.', 'zero-spam' ),
			number_format_i18n( $counts['blocked_email_domains'] ),
			number_format_i18n( $counts['disallowed_words'] ),
			number_format_i18n( $counts['allowed_words'] ),
			number_format_i18n( $counts['allowed_ips'] ),
			number_format_i18n( $counts['blocked_ips'] ),
			number_format_i18n( $counts['blocked_locations'] )
		);

		$empty = 0 === $counts['blocked_email_domains'] || 0 === $counts['disallowed_words'];

		return array(
			'status'  => $empty ? 'warn' : 'pass',
			'message' => $empty
				? $message . ' ' . __( 'The blocked email domains or disallowed words list is empty; use the recommended lists to catch more spam.', 'zero-spam' )
				: $message,
			'counts'  => $counts,
		);
	}

	/**
	 * Asks the reputation services that are on about a test address.
	 *
	 * @return array<string, mixed>
	 */
	private function check_reputation(): array {
		$services = array();

		if ( 'enabled' === $this->setting( 'stop_forum_spam' ) ) {
			$services[] = $this->ping(
				'stop_forum_spam',
				static function () {
					return wp_safe_remote_get(
						'https://api.stopforumspam.org/api?ip=' . self::TEST_IP . '&json',
						array( 'timeout' => self::TIMEOUT )
					);
				},
				static function ( array $data ): bool {
					return ! empty( $data['success'] );
				}
			);
		}

		if ( License_Status::is_enabled() && License_Status::has_key() ) {
			$services[] = $this->ping(
				'zero_spam',
				static function () {
					// A plain request: not counted in the API usage statistics and
					// never opening the circuit breaker the real lookups use.
					return wp_safe_remote_get(
						add_query_arg(
							array(
								'license_key' => rawurlencode( License_Status::key() ),
								'ip'          => self::TEST_IP,
							),
							ZEROSPAM_URL . 'wp-json/v3/query'
						),
						array( 'timeout' => self::PING_TIMEOUT )
					);
				},
				static function ( array $data ): bool {
					return isset( $data['status'] ) && 200 === (int) $data['status'];
				}
			);
		}

		if ( ! $services ) {
			return array(
				'status'   => 'warn',
				'message'  => __( 'No reputation service is on. Stop Forum Spam (free) or Enhanced Protection checks visitors against known spammers.', 'zero-spam' ),
				'services' => array(),
			);
		}

		$failed = array_filter(
			$services,
			static function ( array $service ): bool {
				return ! $service['ok'];
			}
		);
		$slow   = array_filter(
			$services,
			static function ( array $service ): bool {
				return $service['ok'] && $service['ms'] > self::SLOW_MS;
			}
		);

		if ( $failed ) {
			$status  = 'fail';
			$message = __( 'A reputation service did not answer correctly. Visitors are still checked by everything else.', 'zero-spam' );
		} elseif ( $slow ) {
			$status  = 'warn';
			$message = __( 'The reputation services answered, but slowly. Slow answers delay form submissions.', 'zero-spam' );
		} else {
			$status  = 'pass';
			$message = __( 'The reputation services answered.', 'zero-spam' );
		}

		return array(
			'status'   => $status,
			'message'  => $message,
			'services' => $services,
		);
	}

	/**
	 * Sends one test lookup and times it.
	 *
	 * @param string   $id      Service ID.
	 * @param callable $request Sends the request; returns the HTTP API response.
	 * @param callable $valid   Whether the decoded body is a correct answer.
	 * @return array{id: string, ok: bool, code: int, ms: int}
	 */
	private function ping( string $id, callable $request, callable $valid ): array {
		$start    = microtime( true );
		$response = $request();
		$ms       = (int) round( ( microtime( true ) - $start ) * 1000 );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$data     = 200 === $code ? json_decode( (string) wp_remote_retrieve_body( $response ), true ) : null;

		return array(
			'id'   => $id,
			'ok'   => is_array( $data ) && $valid( $data ),
			'code' => $code,
			'ms'   => $ms,
		);
	}

	/**
	 * Where the visitor address comes from in this request.
	 *
	 * @return array<string, mixed>
	 */
	private function check_ip_source(): array {
		$remote = User::get_remote_addr();
		$ip     = User::get_ip();
		$ip     = is_string( $ip ) ? $ip : '';

		if ( '' === $remote ) {
			return array(
				'status'  => 'fail',
				'message' => __( 'The server does not report a valid visitor IP address, so IP checks cannot work.', 'zero-spam' ),
				'source'  => 'none',
				'ip'      => $ip,
			);
		}

		if ( User::is_cloudflare_ip( $remote ) ) {
			return array(
				'status'  => 'pass',
				'message' => __( 'Your site is behind Cloudflare. Visitor addresses are read from the address Cloudflare passes on.', 'zero-spam' ),
				'source'  => 'cloudflare',
				'ip'      => $ip,
			);
		}

		if ( User::is_configured_proxy( $remote ) ) {
			return array(
				'status'  => 'pass',
				'message' => __( 'Your site is behind a trusted proxy. Visitor addresses are read from the address it passes on.', 'zero-spam' ),
				'source'  => 'trusted_proxy',
				'ip'      => $ip,
			);
		}

		foreach ( User::PROXY_HEADERS as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return array(
					'status'  => 'warn',
					'message' => __( 'This request came through a proxy or CDN that Zero Spam does not trust, so every visitor may get the same address. Add it to Trusted Proxy IPs.', 'zero-spam' ),
					'source'  => 'untrusted_proxy',
					'ip'      => $ip,
				);
			}
		}

		return array(
			'status'  => 'pass',
			'message' => __( 'Visitors connect to your site directly. Their own address is used.', 'zero-spam' ),
			'source'  => 'direct',
			'ip'      => $ip,
		);
	}

	/**
	 * A setting's effective value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	private function setting( string $key ) {
		$resolved = Settings_Schema::resolve( $key );

		return null !== $resolved ? $resolved['value'] : null;
	}

	/**
	 * Number of non-empty lines in a list setting.
	 *
	 * @param mixed $value Text with one entry per line.
	 */
	private function count_lines( $value ): int {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}

		return count( array_filter( array_map( 'trim', preg_split( '/\R/', $value ) ) ) );
	}
}
