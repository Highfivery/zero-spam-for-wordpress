<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Notices_Controller to this file name.
/**
 * REST route that dismisses Zero Spam's admin notices.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Admin\Notices;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `POST zero-spam/v1/ui/notices/{id}/dismiss`: dismisses one of Zero Spam's
 * admin notices (Core\Admin\Notices: `shared-ip`, `safe-ip`) as the notice's
 * own Dismiss button does.
 *
 * Answers `{ "id": …, "dismissed": true }`, or `dismissed: false` when the
 * notice is not shown now (nothing to dismiss); an unknown ID is a 404.
 *
 * Internal to the admin screens (the `ui/` routes may change between
 * releases); administrators of the site only (`manage_options`).
 *
 * @since 6.0.0
 */
class Notices_Controller extends Base_Controller {

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
	protected $rest_base = 'ui/notices';

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-z0-9-]{1,64})/dismiss',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'dismiss' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Dismisses a notice.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss( $request ) {
		$id = (string) $request->get_param( 'id' );

		if ( ! in_array( $id, Notices::IDS, true ) ) {
			return new WP_Error( 'zerospam_notice_not_found', __( 'There is no such notice.', 'zero-spam' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'        => $id,
				'dismissed' => true === Notices::dismiss( $id ),
			)
		);
	}
}
