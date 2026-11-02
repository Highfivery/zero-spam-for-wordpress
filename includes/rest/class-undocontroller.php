<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Undo_Controller to this file name.
/**
 * REST route that undoes a deletion.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Undo_Store;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/undo/{token}`: puts back what a deletion through the REST
 * API removed (activity entries or blocks), within two minutes, once, for
 * the user who deleted it and on the same site (see Undo_Store).
 *
 * @since 6.0.0
 */
class Undo_Controller extends Base_Controller {

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
	protected $rest_base = 'undo';

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<token>[A-Za-z0-9]{32})',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'undo' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Restores the rows of a token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function undo( $request ) {
		$result = Undo_Store::restore( (string) $request->get_param( 'token' ) );

		if ( null === $result ) {
			return new WP_Error(
				'zerospam_undo_expired',
				__( 'This can no longer be undone: the two minutes have passed, it was already undone, or it was done by someone else or on another site.', 'zero-spam' ),
				array( 'status' => 404 )
			);
		}

		if ( 'activity' === $result['kind'] ) {
			Stats_Controller::flush_cache();
		} elseif ( 'network_blocks' !== $result['kind'] ) {
			// Restored IP blocks go back into the .htaccess rules (when that block method is used).
			Utilities::refresh_htaccess();
		}

		return rest_ensure_response( $result );
	}
}
