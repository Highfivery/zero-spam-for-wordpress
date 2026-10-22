<?php
/**
 * Access class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Access
 */
class Access {
	/**
	 * Cookie that keeps rescue mode active after a valid rescue key is used.
	 *
	 * @var string
	 */
	const RESCUE_COOKIE = 'zerospam_rescue';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initializes the class by setting up hooks and actions.
	 *
	 * This method is called during the WordPress initialization process. It
	 * registers the 'template_redirect' action to perform access checks and
	 * adds a filter for 'zerospam_access_checks' to determine if the current
	 * request should be blocked.
	 */
	public function init() {
		$this->redeem_rescue_link();

		if ( ! is_admin() && self::process() ) {
			add_action( 'template_redirect', array( $this, 'access_check' ), 0 );
			add_filter( 'zerospam_access_checks', array( $this, 'check_blocked' ), 0, 3 );
		}

		// Protect login.
		add_action( 'login_init', array( $this, 'login_access_check' ) );

		// Protect XML-RPC.
		add_action( 'xmlrpc_call', array( $this, 'xmlrpc_access_check' ) );
	}

	/**
	 * Terminates execution with a custom error message and HTTP status code.
	 *
	 * Registers an action to prevent caching on error conditions by setting
	 * appropriate HTTP headers before leveraging WordPress's wp_die() function
	 * to produce an error page with a specified message, title, and HTTP status
	 * code.
	 *
	 * @param string $title   The text to be used as the page title for the error message.
	 *                        This content will be sanitized to remove unwanted HTML.
	 * @param string $message The error message to display. This content will be escaped
	 *                        to ensure only safe HTML is included.
	 * @param int    $code    Optional. The HTTP status code to be sent in the header.
	 *                        Defaults to 403 to indicate a Forbidden error.
	 * @param bool   $rescue  Optional. Whether to add the "Help for site owners"
	 *                        section with the rescue link form (the blocked
	 *                        page only), unless the zerospam_rescue_form_enabled
	 *                        filter turns it off. Default false.
	 */
	public static function terminate_execution( $title, $message, $code = 403, $rescue = false ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		$rescue = $rescue && Rescue_Link::form_enabled();

		// The administrator's message keeps the post allowlist; only the plugin's own form markup may add form controls and styles.
		wp_die(
			$rescue ? '<h1>' . esc_html( $title ) . '</h1>' . wp_kses_post( $message ) . wp_kses( Rescue_Link::form_html(), Rescue_Link::allowed_html() ) : wp_kses_post( $message ),
			esc_html( $title ),
			array(
				'response' => absint( $code ),
			)
		);
	}

	/**
	 * Checks whether the request uses rescue mode.
	 *
	 * A valid `?zerospam_rescue={ZEROSPAM_RESCUE_KEY}` also sets a cookie that
	 * bypasses checks for an hour, because the key isn't carried through
	 * redirects or the login form (e.g. /wp-admin/ → wp-login.php → POST).
	 * The cookie set by an emailed rescue link (see redeem_rescue_link()) is
	 * accepted too, with or without the constant.
	 *
	 * @return bool
	 */
	private static function is_rescue_request() {
		$key = defined( 'ZEROSPAM_RESCUE_KEY' ) && is_string( ZEROSPAM_RESCUE_KEY ) ? ZEROSPAM_RESCUE_KEY : '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Compared with hash_equals() against secrets only.
		if (
			'' !== $key &&
			isset( $_GET['zerospam_rescue'] ) &&
			is_string( $_GET['zerospam_rescue'] ) &&
			hash_equals( $key, wp_unslash( $_GET['zerospam_rescue'] ) )
		) {
			self::grant_rescue_cookie( self::rescue_key_token( $key ), time() + HOUR_IN_SECONDS );

			return true;
		}

		if ( ! isset( $_COOKIE[ self::RESCUE_COOKIE ] ) || ! is_string( $_COOKIE[ self::RESCUE_COOKIE ] ) ) {
			return false;
		}

		$cookie = wp_unslash( $_COOKIE[ self::RESCUE_COOKIE ] );
		// phpcs:enable

		if ( '' !== $key && hash_equals( self::rescue_key_token( $key ), $cookie ) ) {
			return true;
		}

		return self::is_valid_link_cookie( $cookie );
	}

	/**
	 * Rescue cookie value for the ZEROSPAM_RESCUE_KEY constant.
	 *
	 * @param string $key Rescue key.
	 */
	private static function rescue_key_token( string $key ): string {
		return hash_hmac( 'sha256', 'zerospam_rescue', $key );
	}

	/**
	 * Rescue cookie value for an emailed rescue link.
	 *
	 * Carries its own expiry, signed with the site's auth salt, so the hour is
	 * enforced by the server and not only by the browser. The signature also
	 * covers the site and network IDs: on a network every site shares the
	 * cookie path and domain, and a link used on one site must not open the
	 * others.
	 *
	 * @param int $expires Expiry timestamp.
	 */
	private static function link_cookie_value( int $expires ): string {
		$scope = 'zerospam_rescue_link|' . $expires . '|' . get_current_blog_id() . '|' . get_current_network_id();

		return $expires . '.' . hash_hmac( 'sha256', $scope, wp_salt( 'auth' ) );
	}

	/**
	 * Whether a rescue cookie value comes from an emailed link and is still valid.
	 *
	 * @param string $value Cookie value.
	 */
	private static function is_valid_link_cookie( string $value ): bool {
		$parts = explode( '.', $value, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || (int) $parts[0] < time() ) {
			return false;
		}

		return hash_equals( self::link_cookie_value( (int) $parts[0] ), $value );
	}

	/**
	 * Sets the rescue cookie, which bypasses checks in this browser until it expires.
	 *
	 * Used by the ZEROSPAM_RESCUE_KEY constant and by emailed rescue links.
	 *
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiry timestamp.
	 */
	private static function grant_rescue_cookie( string $value, int $expires ): void {
		if ( ! headers_sent() ) {
			setcookie(
				self::RESCUE_COOKIE,
				$value,
				array(
					'expires'  => $expires,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ self::RESCUE_COOKIE ] = $value;
	}

	/**
	 * Handles an emailed rescue link before any block check (runs on `init`).
	 *
	 * Opening the link (GET) only shows a "Let me back in" button and leaves
	 * the token unused, because email scanners open links on their own. The
	 * button's POST uses the token: it sets the rescue cookie, logs it and
	 * sends the visitor to log in. Only requests carrying the token do any
	 * work; invalid, used and expired tokens are ignored, so the request
	 * continues as usual.
	 */
	private function redeem_rescue_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- The single-use token is the credential; see Rescue_Link.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'POST' === $method && isset( $_POST[ Rescue_Link::TOKEN_PARAM ] ) && is_string( $_POST[ Rescue_Link::TOKEN_PARAM ] ) ) {
			$grant = Rescue_Link::redeem_token( sanitize_text_field( wp_unslash( $_POST[ Rescue_Link::TOKEN_PARAM ] ) ) );
		} elseif ( isset( $_GET[ Rescue_Link::TOKEN_PARAM ] ) && is_string( $_GET[ Rescue_Link::TOKEN_PARAM ] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET[ Rescue_Link::TOKEN_PARAM ] ) );
			if ( Rescue_Link::peek_token( $token ) ) {
				Rescue_Link::show_confirm_page( $token );
			}

			return;
		} else {
			return;
		}
		// phpcs:enable

		if ( null === $grant ) {
			return;
		}

		$expires = time() + HOUR_IN_SECONDS;
		self::grant_rescue_cookie( self::link_cookie_value( $expires ), $expires );
		Rescue_Link::log_use( $grant['ip'] );

		wp_safe_redirect( wp_login_url() );
		exit;
	}

	/**
	 * Determines is security checks need to be triggers.
	 *
	 * @param boolean $ignore_ajax True if AJAX shouldn't be checked.
	 */
	public static function process( $ignore_ajax = false ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$user_ip = \ZeroSpam\Core\User::get_ip();

		// Check for rescue mode.
		if ( self::is_rescue_request() ) {
			return false;
		}

		// Never check the blocked page's rescue form request (admin-post.php only):
		// it can only email a site administrator and is rate limited, see Rescue_Link.
		if ( Rescue_Link::is_rescue_post() ) {
			return false;
		}

		// Sanitize the REQUEST_URI before further processing.
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Check for .ico requests.
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( substr( $path, -4 ) === '.ico' ) {
			return false;
		}

		if ( ( $ignore_ajax && is_admin() ) || is_user_logged_in() || \ZeroSpam\Core\Utilities::is_whitelisted( $user_ip ) ) {
			return false;
		} elseif ( ! $ignore_ajax && ( ( is_admin() && ! wp_doing_ajax() ) || is_user_logged_in() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Access check
	 */
	public function access_check() {
		// Ensure this is the main query for standard template redirects.
		if ( ! is_main_query() ) {
			return;
		}

		$this->run_block_check();
	}

	/**
	 * Login Access check
	 */
	public function login_access_check() {
		if ( self::process( true ) ) {
			$this->run_block_check();
		}
	}

	/**
	 * XML-RPC Access check
	 */
	public function xmlrpc_access_check() {
		if ( self::process( true ) ) {
			$this->run_block_check( true );
		}
	}

	/**
	 * Runs the block check logic.
	 *
	 * @param boolean $is_xmlrpc True if this is an XML-RPC request.
	 */
	public function run_block_check( $is_xmlrpc = false ) {
		$access = self::get_access();

		if ( ! empty( $access['blocked'] ) ) {
			$settings = ZeroSpam\Core\Settings::get_settings();

			if ( ! empty( $access['details'] ) && is_array( $access['details'] ) ) {
				foreach ( $access['details'] as $key => $detail ) {
					if ( ! empty( $detail['blocked'] ) ) {
						if ( empty( $detail['details']['failed'] ) ) {
							$detail['details']['failed'] = $key;
						}

						if ( ! empty( $settings['log_blocked_ips']['value'] ) && 'enabled' === $settings['log_blocked_ips']['value'] ) {
							ZeroSpam\Includes\DB::log( $detail['type'], $detail['details'] );
						}
					}
				}
			}

			if ( $is_xmlrpc ) {
				wp_die(
					'<?xml version="1.0"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>403</int></value></member><member><name>faultString</name><value><string>' . esc_html__( 'Access Denied: Your IP address has been blocked due to suspected malicious activity.', 'zero-spam' ) . '</string></value></member></struct></value></fault></methodResponse>',
					'XML-RPC Access Denied',
					array(
						'response'     => 403,
						'Content-Type' => 'text/xml',
					)
				);
			}

			if ( ! empty( $settings['block_handler']['value'] ) ) {
				switch ( $settings['block_handler']['value'] ) {
					case 403:
						$message = __( 'Your IP address has been blocked due to detected spam/malicious activity.', 'zero-spam' );
						if ( ! empty( $settings['blocked_message']['value'] ) ) {
							$message = $settings['blocked_message']['value'];
						}

						self::terminate_execution( __( 'Blocked', 'zero-spam' ), $message, 403, true );
						break;
					case 'redirect':
						$url = 'https://wordpress.org/plugins/zero-spam/';
						if ( ! empty( $settings['blocked_redirect_url']['value'] ) ) {
							$url = esc_url( $settings['blocked_redirect_url']['value'] );
						}
						// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The administrator chooses this URL, usually on another site.
						wp_redirect( $url );
						exit;
				}
			}
		}
	}

	/**
	 * Helper to get blocked record details.
	 *
	 * @param array|false|null $blocked_record Blocked record from DB::blocked().
	 * @param string|false     $failed         Failed check key, such as `blocked_ip`.
	 */
	public static function get_blocked_details( $blocked_record, $failed = false ) {
		$access_check = array(
			'blocked' => false,
		);

		if ( ! $blocked_record ) {
			return $access_check;
		}

		$today = new \DateTime();

		// Check the start & end dates (all blocks require a start date).
		$start_date = new \DateTime();
		if ( ! empty( $blocked_record['start_block'] ) ) {
			$start_date = new \DateTime( $blocked_record['start_block'] );
		}

		$blocked = false;
		if ( $today >= $start_date ) {
			// Check the end date if temporary block.
			if (
				! empty( $blocked_record['blocked_type'] ) &&
				'temporary' === $blocked_record['blocked_type']
			) {
				// Temporary block.
				if ( ! empty( $blocked_record['end_block'] ) ) {
					$end_date = new \DateTime( $blocked_record['end_block'] );

					if ( $today < $end_date ) {
						$blocked = true;
					}
				}
			} else {
				// Permanent block.
				$blocked = true;
			}
		}

		if ( $blocked ) {
			$access_check['blocked']           = true;
			$access_check['type']              = 'blocked';
			$access_check['details']           = $blocked_record;
			$access_check['details']['failed'] = $failed;
		}

		return $access_check;
	}

	/**
	 * Checks if an IP has been blocked
	 *
	 * @param array  $access_checks Array of existing access checks.
	 * @param string $user_ip The user's IP address.
	 * @param array  $settings The plugin settings.
	 */
	public function check_blocked( $access_checks, $user_ip, $settings ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature of the zerospam_access_checks filter.
		$access_checks['blocked'] = false;

		// Check if geolocation information is available, if so, check if blocked.
		$geolocation_information = \ZeroSpam\Core\Utilities::geolocation( $user_ip );
		if ( $geolocation_information ) {
			// Geolocation information available, check the blocked locations.
			// Available blocked location keys.
			$location_keys = array(
				'country_code',
				'region_code',
				'city',
				'zip',
			);

			foreach ( $location_keys as $key => $loc ) {
				if ( ! empty( $geolocation_information[ $loc ] ) ) {
					$blocked = \ZeroSpam\Includes\DB::blocked( $geolocation_information[ $loc ], $loc );
					if ( $blocked ) {
						$access_checks['blocked'] = self::get_blocked_details( $blocked, 'blocked_' . $loc );
						break;
					}
				}
			}
		}

		// If passed location blocks, check the IP address.
		if ( ! $access_checks['blocked'] ) {
			// Check the user's IP access.
			$blocked = \ZeroSpam\Includes\DB::blocked( $user_ip );
			if ( $blocked ) {
				$access_checks['blocked'] = self::get_blocked_details( $blocked, 'blocked_ip' );
			}
		}

		return $access_checks;
	}

	/**
	 * Gets the current user's access
	 */
	public function get_access() {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$user_ip  = ZeroSpam\Core\User::get_ip();

		$access = array(
			'blocked' => false,
		);

		if ( $user_ip ) {
			$access['ip'] = $user_ip;

			if ( ZeroSpam\Core\Utilities::is_whitelisted( $user_ip ) ) {
				return $access;
			}

			$access_checks = apply_filters(
				'zerospam_access_checks',
				array(),
				$user_ip,
				$settings
			);

			foreach ( $access_checks as $key => $check ) {
				if ( ! empty( $check['blocked'] ) ) {
					$access['blocked'] = true;
					break;
				}
			}

			// An address saved when an administrator activated or updated the
			// plugin is never locked out. Only lockouts: detections still apply
			// (Access::process() is unaffected). Read only when a block applies.
			if ( $access['blocked'] && Admin_Safe_IPs::contains( (string) $user_ip ) ) {
				$access['blocked']    = false;
				$access['admin_safe'] = true;
			}

			$access['details'] = $access_checks;
		}

		return $access;
	}
}
