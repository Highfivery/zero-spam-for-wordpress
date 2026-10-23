<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps License_Controller to this file name.
/**
 * REST route for the Zero Spam license.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Settings_Resolver;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/license`: read the license status, save a key after checking
 * it, or remove it. Administrators of the current site only; the key itself
 * is never returned, only whether it is set, where it comes from and (for a
 * key the site stores) its last four characters.
 *
 * @since 6.0.0
 */
class License_Controller extends Base_Controller {

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
	protected $rest_base = 'license';

	/**
	 * Setting that holds the key.
	 *
	 * @var string
	 */
	const SETTING = 'zerospam_license';

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'refresh' => array(
							'description' => __( 'Ask the Zero Spam API again instead of using the cached status.', 'zero-spam' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'license_key' => array(
							'description' => __( 'The Zero Spam license key.', 'zero-spam' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * The license status and the masked key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$status = $request->get_param( 'refresh' ) ? License_Status::refresh() : License_Status::get();

		return rest_ensure_response( $this->prepare( $status ) );
	}

	/**
	 * Checks a key with the Zero Spam API and saves it for this site.
	 *
	 * A key the API rejects is not saved. A key that could not be checked
	 * (API unreachable) is saved, as the settings page does, and checked
	 * again later.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$key = $request->get_param( 'license_key' );
		$key = is_string( $key ) ? trim( sanitize_text_field( $key ) ) : '';

		if ( '' === $key ) {
			return $this->invalid_params_error( array( 'license_key' => __( 'Enter a license key.', 'zero-spam' ) ) );
		}

		$resolved = Settings_Schema::resolve( self::SETTING );
		if ( null !== $resolved && $resolved['locked'] ) {
			return $this->locked_error( array( self::SETTING ) );
		}

		$check = License_Status::check( $key );

		if ( License_Status::INVALID === $check['status'] ) {
			return $this->invalid_params_error( array( 'license_key' => __( 'The Zero Spam license key you entered is invalid. Please check the key and try again.', 'zero-spam' ) ) );
		}

		if ( ! Settings_Resolver::update_settings( 'zerospam', 'site', array( self::SETTING => $key ) ) ) {
			return new WP_Error( 'rest_update_failed', __( 'Failed to update settings.', 'zero-spam' ), array( 'status' => 500 ) );
		}

		$this->forget_cached_settings();

		return rest_ensure_response( $this->prepare( License_Status::get() ) );
	}

	/**
	 * Removes the key this site stores.
	 *
	 * A key set by the network or in wp-config.php (ZEROSPAM_LICENSE_KEY)
	 * cannot be removed here.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		unset( $request );

		$resolved = Settings_Schema::resolve( self::SETTING );

		if ( null !== $resolved && $resolved['locked'] ) {
			return $this->locked_error( array( self::SETTING ) );
		}

		$stored = get_option( 'zero-spam-zerospam', array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( null !== $resolved && Settings_Schema::SOURCE_CONSTANT === $resolved['source'] && empty( $stored[ self::SETTING ] ) ) {
			return new WP_Error(
				'rest_license_constant',
				__( 'The license key is set in wp-config.php (ZEROSPAM_LICENSE_KEY) and can only be removed there.', 'zero-spam' ),
				array( 'status' => 409 )
			);
		}

		if ( array_key_exists( self::SETTING, $stored ) ) {
			License_Status::reset_cache();
			unset( $stored[ self::SETTING ] );
			update_option( 'zero-spam-zerospam', $stored, true );
		}

		$this->forget_cached_settings();

		return rest_ensure_response( $this->prepare( License_Status::get() ) );
	}

	/**
	 * The response: the masked key and the status.
	 *
	 * @param array<string, mixed> $status License_Status::get().
	 * @return array<string, mixed>
	 */
	private function prepare( array $status ): array {
		return array_merge(
			array( 'key' => Settings_Schema::masked( self::SETTING ) ),
			$status
		);
	}

	/**
	 * Clears the per-request caches after the key changed.
	 */
	private function forget_cached_settings(): void {
		Settings_Resolver::invalidate_all();
		Settings_Schema::reset_cache();
		License_Status::reset_request_cache();
		self::reset_request_cache();
	}
}
