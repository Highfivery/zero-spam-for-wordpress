<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Onboarding_Controller to this file name.
/**
 * REST route for first-run setup.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Admin\Onboarding;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/ui/onboarding`: whether first-run setup is pending on this
 * site (`pending`), whether the site already has settings of its own
 * (`configured`), the current administrator's step on this site (`step`,
 * 0–4) and which release's "What's new" panel it shows (`whats_new`, ''
 * for none). POST `{ "step": n }` saves the step (per site and user);
 * POST `{ "status": "done" }` ends the setup, whether it was finished or
 * skipped. The setup writes its settings through the settings and license
 * routes itself.
 *
 * Internal to the admin screens (the `ui/` routes may change between
 * releases); administrators of the site only (`manage_options`).
 *
 * @since 6.0.0
 */
class Onboarding_Controller extends Base_Controller {

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
	protected $rest_base = 'ui/onboarding';

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
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'status' => array(
							'description' => __( 'done: first-run setup was finished or skipped.', 'zero-spam' ),
							'type'        => 'string',
							'enum'        => array( 'done' ),
						),
						'step'   => array(
							'description' => __( 'The step the current administrator is at on this site (0 to start again).', 'zero-spam' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => 4,
						),
					),
				),
			)
		);
	}

	/**
	 * The setup state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		unset( $request );

		return rest_ensure_response( $this->state() );
	}

	/**
	 * Ends first-run setup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$status = $request->get_param( 'status' );
		$step   = $request->get_param( 'step' );

		if ( null === $status && null === $step ) {
			return $this->invalid_params_error( array( 'status' => __( 'Send status done, or a step.', 'zero-spam' ) ) );
		}

		if ( null !== $step && Onboarding::is_pending() ) {
			Onboarding::save_step( (int) $step );
		}

		if ( 'done' === $status ) {
			Onboarding::complete();
		}

		return rest_ensure_response( $this->state() );
	}

	/**
	 * The state the routes answer with.
	 *
	 * @return array{pending: bool, configured: bool, step: int, whats_new: string}
	 */
	private function state(): array {
		return array(
			'pending'    => Onboarding::is_pending(),
			'configured' => Onboarding::is_configured(),
			'step'       => Onboarding::step(),
			'whats_new'  => Onboarding::whats_new(),
		);
	}
}
