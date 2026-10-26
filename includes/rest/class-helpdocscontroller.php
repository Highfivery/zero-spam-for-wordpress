<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Help_Docs_Controller to this file name.
/**
 * REST route for the guides shown on the Help screen.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Admin\Documentation;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/ui/help/docs`: every guide (Documentation::for_help()), as
 * a list of `id`, `title`, `topics` (the titles of its parts) and `html`.
 *
 * The HTML is rendered from the plugin's templates and from sections other
 * plugins add with `zerospam_documentation_sections`, and is always passed
 * through wp_kses_post() on the server, so the Help screen can insert it as
 * it is. Internal to the admin screens (the `ui/` routes may change between
 * releases); administrators of the site only (`manage_options`).
 *
 * @since 6.0.0
 */
class Help_Docs_Controller extends Base_Controller {

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
	protected $rest_base = 'ui/help/docs';

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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * The guides.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$items = array();

		foreach ( Documentation::for_help() as $section ) {
			$items[] = $this->filter_fields( $section, $request );
		}

		return rest_ensure_response( $items );
	}
}
