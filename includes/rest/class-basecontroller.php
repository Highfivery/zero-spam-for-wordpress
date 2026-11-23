<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Base_Controller to this file name.
/**
 * Shared base for Zero Spam's REST controllers.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use ZeroSpam\Includes\Network_Report;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Permission checks, error shapes, paging and caching shared by the
 * `zero-spam/v1` controllers.
 *
 * The capability always comes from the route: `manage_options` on the
 * current site, `manage_network_options` for network routes. A scope flag
 * sent by the client never widens it.
 *
 * Only adds methods; it overrides nothing in WP_REST_Controller, so a
 * controller that extends it instead of WP_REST_Controller behaves the same.
 *
 * @since 6.0.0
 */
abstract class Base_Controller extends WP_REST_Controller {

	/**
	 * Largest page size any collection route accepts.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Values computed once per request, by key (see request_cache()).
	 *
	 * @var array<string, mixed>
	 */
	private static array $request_cache = array();

	/**
	 * Allows administrators of the current site (`manage_options`).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function site_permissions_check( $request ) {
		unset( $request );

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return $this->forbidden_error();
	}

	/**
	 * Allows network administrators (`manage_network_options`) on a network.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function network_permissions_check( $request ) {
		unset( $request );

		if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
			return true;
		}

		return $this->forbidden_error();
	}

	/**
	 * Allows any logged-in user who can read the current site.
	 *
	 * On a network that is a member of the site (or a super admin).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function logged_in_permissions_check( $request ) {
		unset( $request );

		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return true;
		}

		return $this->forbidden_error();
	}

	/**
	 * The error a permission check returns: 401 when logged out, 403 otherwise.
	 *
	 * @param string $message Optional message; a generic one when empty.
	 * @return WP_Error
	 */
	protected function forbidden_error( string $message = '' ): WP_Error {
		return new WP_Error(
			'rest_forbidden',
			'' !== $message ? $message : __( 'Sorry, you are not allowed to do that.', 'zero-spam' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * A 400 error in the shape WordPress uses for invalid parameters.
	 *
	 * @param array<string, string> $params Parameter name => what is wrong with it.
	 * @return WP_Error `rest_invalid_param` with `data.params`.
	 */
	protected function invalid_params_error( array $params ): WP_Error {
		return new WP_Error(
			'rest_invalid_param',
			/* translators: %s: comma-separated list of parameter names */
			sprintf( __( 'Invalid parameter(s): %s', 'zero-spam' ), implode( ', ', array_keys( $params ) ) ),
			array(
				'status' => 400,
				'params' => $params,
			)
		);
	}

	/**
	 * The 403 error for settings the network administrator has locked.
	 *
	 * @param string[] $keys Locked setting keys.
	 * @return WP_Error `rest_setting_locked` with `data.locked_keys`.
	 */
	protected function locked_error( array $keys ): WP_Error {
		return new WP_Error(
			'rest_setting_locked',
			__( 'These settings are locked by the network administrator and cannot be changed on this site. Nothing was saved.', 'zero-spam' ),
			array(
				'status'      => 403,
				'locked_keys' => array_values( $keys ),
			)
		);
	}

	/**
	 * The site a request may read, from its optional `site_id` parameter.
	 *
	 * The one place a route that takes a site ID decides whose data it answers
	 * with. No `site_id`, or the current site's, is the current site (the route's
	 * own permission check already applies). Any other site needs a network
	 * administrator (`manage_network_options`) and must be on the current network:
	 * a network administrator of one network never reads a site of another.
	 *
	 * @since 6.0.2
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int|WP_Error The site ID; 403 for a site the user may not read, 404 for one that is not on this network.
	 */
	protected function requested_site_id( WP_REST_Request $request ): int|WP_Error {
		$current = get_current_blog_id();
		$site_id = $request->get_param( 'site_id' );

		if ( null === $site_id ) {
			return $current;
		}

		// Checked here as well as by site_id_arg(), so a route that forgets the schema cannot read site 0 or "1abc" as a site.
		if ( ! is_int( $site_id ) && ! ( is_string( $site_id ) && ctype_digit( $site_id ) ) ) {
			return $this->invalid_params_error( array( 'site_id' => __( 'A site ID is a positive whole number.', 'zero-spam' ) ) );
		}

		$site_id = (int) $site_id;

		if ( $site_id < 1 ) {
			return $this->invalid_params_error( array( 'site_id' => __( 'A site ID is a positive whole number.', 'zero-spam' ) ) );
		}

		if ( $site_id === $current ) {
			return $current;
		}

		if ( ! is_multisite() ) {
			return $this->site_not_found_error();
		}

		if ( ! current_user_can( 'manage_network_options' ) ) {
			return $this->forbidden_error( __( 'You can only view this site.', 'zero-spam' ) );
		}

		if ( ! Network_Report::is_on_network( $site_id ) ) {
			return $this->site_not_found_error();
		}

		return $site_id;
	}

	/**
	 * The 404 error for a site ID that is not a site of the current network.
	 *
	 * @since 6.0.2
	 *
	 * @return WP_Error `rest_site_not_found`.
	 */
	protected function site_not_found_error(): WP_Error {
		return new WP_Error(
			'rest_site_not_found',
			__( 'That site does not exist on this network.', 'zero-spam' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * The argument schema of an optional `site_id` read through requested_site_id().
	 *
	 * @since 6.0.2
	 *
	 * @return array<string, mixed>
	 */
	protected function site_id_arg(): array {
		return array(
			'description' => __( 'Site ID on a network (default: the current site). Other sites need a network administrator.', 'zero-spam' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
	}

	/**
	 * A page size between 1 and MAX_PER_PAGE.
	 *
	 * @param mixed $value Requested page size.
	 * @param int   $fallback Page size when none (or nothing usable) was requested.
	 * @return int
	 */
	protected function clamp_per_page( $value, int $fallback = 20 ): int {
		$per_page = is_numeric( $value ) ? (int) $value : $fallback;

		return max( 1, min( self::MAX_PER_PAGE, $per_page ) );
	}

	/**
	 * Adds the X-WP-Total and X-WP-TotalPages headers WordPress uses for paging.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param int              $total    Number of items across all pages.
	 * @param int              $per_page Page size.
	 * @return WP_REST_Response
	 */
	protected function add_pagination_headers( WP_REST_Response $response, int $total, int $per_page ): WP_REST_Response {
		$total = max( 0, $total );

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / max( 1, $per_page ) ) );

		return $response;
	}

	/**
	 * Keeps only the fields a request asked for with `_fields`.
	 *
	 * WordPress filters the top level of every response itself; this lets a
	 * controller skip work for fields nobody asked for, and filters each item
	 * of a collection the same way.
	 *
	 * @param array<string, mixed> $item    Item.
	 * @param WP_REST_Request      $request Request.
	 * @return array<string, mixed>
	 */
	protected function filter_fields( array $item, WP_REST_Request $request ): array {
		$fields = $this->requested_fields( $request );

		if ( ! $fields ) {
			return $item;
		}

		foreach ( array_keys( $item ) as $field ) {
			if ( ! rest_is_field_included( (string) $field, $fields ) ) {
				unset( $item[ $field ] );
			}
		}

		return $item;
	}

	/**
	 * The fields named in `_fields`, or an empty array for all fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string[]
	 */
	protected function requested_fields( WP_REST_Request $request ): array {
		$fields = $request->get_param( '_fields' );

		if ( null === $fields || '' === $fields ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', wp_parse_list( $fields ) ) ) );
	}

	/**
	 * Computes a value once per request.
	 *
	 * Include anything the value depends on (the blog ID on a network, the
	 * user) in the key.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $producer Returns the value.
	 * @return mixed
	 */
	protected static function request_cache( string $key, callable $producer ) {
		if ( ! array_key_exists( $key, self::$request_cache ) ) {
			self::$request_cache[ $key ] = $producer();
		}

		return self::$request_cache[ $key ];
	}

	/**
	 * Empties the per-request cache (after a write, and between tests).
	 */
	public static function reset_request_cache(): void {
		self::$request_cache = array();
	}
}
