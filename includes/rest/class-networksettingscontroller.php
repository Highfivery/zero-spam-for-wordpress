<?php
/**
 * Network Settings REST API Controller
 *
 * Provides 20+ REST API endpoints for programmatic access
 * to network settings management.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Rest;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Settings Controller class
 */
class Network_Settings_Controller extends \WP_REST_Controller {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * Rest base
	 *
	 * @var string
	 */
	protected $rest_base = 'network-settings';

	/**
	 * Settings Manager instance
	 *
	 * @var \ZeroSpam\Includes\Network_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Network Settings instance
	 *
	 * @var \ZeroSpam\Includes\Network_Settings
	 */
	private $network_settings;

	/**
	 * Templates Manager instance
	 *
	 * @var \ZeroSpam\Includes\Network_Templates
	 */
	private $templates_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager  = new \ZeroSpam\Includes\Network_Settings_Manager();
		$this->network_settings  = new \ZeroSpam\Includes\Network_Settings();
		$this->templates_manager = new \ZeroSpam\Includes\Network_Templates();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		if ( ! is_multisite() ) {
			return;
		}

		// Get all settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Get single setting.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'value'  => array(
							'required' => true,
						),
						'locked' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		// Lock/Unlock setting.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[\w-]+)/lock',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'lock_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[\w-]+)/unlock',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'unlock_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		// Apply to all sites.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/apply-all',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_all' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'mode'  => array(
							'type'    => 'string',
							'default' => 'all',
							'enum'    => array( 'all', 'locked_only', 'defaults_only' ),
						),
						'sites' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		// Get site settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/site/(?P<site_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_settings' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Reset site.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/site/(?P<site_id>\d+)/reset',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_site' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		// Comparison.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/compare',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_comparison' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Audit log.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/audit',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_audit' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'limit'       => array(
							'type'    => 'integer',
							'default' => 50,
						),
						'offset'      => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'action_type' => array(
							'type' => 'string',
						),
						'user_id'     => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		// Export.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_settings' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Import.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_settings' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'settings' => array(
							'required' => true,
							'type'     => 'object',
						),
						'mode'     => array(
							'type'    => 'string',
							'default' => 'merge',
							'enum'    => array( 'merge', 'replace', 'add_only' ),
						),
					),
				),
			)
		);

		// Templates.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/templates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'name'        => array(
							'required' => true,
							'type'     => 'string',
						),
						'slug'        => array(
							'required' => true,
							'type'     => 'string',
						),
						'description' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		// Single template.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/templates/(?P<slug>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_template' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		// Apply template.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/templates/(?P<slug>[\w-]+)/apply',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_template' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'scope' => array(
							'type'    => 'string',
							'default' => 'network',
							'enum'    => array( 'network', 'sites' ),
						),
						'lock'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'sites' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for getting items
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Check permissions for getting item
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Check permissions for updating item
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Get all settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$settings = $this->settings_manager->get_all_with_status();

		return rest_ensure_response( $settings );
	}

	/**
	 * Get single setting
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_item( $request ) {
		$key    = $request->get_param( 'key' );
		$status = $this->network_settings->get_application_status( $key );

		return rest_ensure_response( $status );
	}

	/**
	 * Update setting
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function update_item( $request ) {
		$key    = $request->get_param( 'key' );
		$value  = $request->get_param( 'value' );
		$locked = $request->get_param( 'locked' );

		$result = $this->settings_manager->set_setting( $key, $value, $locked );

		if ( $result ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'Setting updated' ) );
		}

		return new \WP_Error( 'update_failed', 'Failed to update setting', array( 'status' => 500 ) );
	}

	/**
	 * Lock setting
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function lock_item( $request ) {
		$key    = $request->get_param( 'key' );
		$result = $this->settings_manager->lock_setting( $key );

		if ( $result ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'Setting locked' ) );
		}

		return new \WP_Error( 'lock_failed', 'Failed to lock setting', array( 'status' => 500 ) );
	}

	/**
	 * Unlock setting
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function unlock_item( $request ) {
		$key    = $request->get_param( 'key' );
		$result = $this->settings_manager->unlock_setting( $key );

		if ( $result ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'Setting unlocked' ) );
		}

		return new \WP_Error( 'unlock_failed', 'Failed to unlock setting', array( 'status' => 500 ) );
	}

	/**
	 * Apply to all sites
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function apply_all( $request ) {
		$force  = $request->get_param( 'force' );
		$mode   = $request->get_param( 'mode' );
		$sites  = $request->get_param( 'sites' ) ?? array();

		$result = $this->settings_manager->apply_to_sites( $force, $sites, $mode );

		return rest_ensure_response( $result );
	}

	/**
	 * Get site settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_site_settings( $request ) {
		$site_id  = $request->get_param( 'site_id' );
		$settings = $this->settings_manager->get_all_with_status();
		$result   = array();

		foreach ( $settings as $key => $config ) {
			$effective = $this->network_settings->get_effective_value( $key, $site_id );
			$result[ $key ] = array(
				'value'  => $effective['value'],
				'source' => $effective['source'],
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Reset site
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function reset_site( $request ) {
		$site_id = $request->get_param( 'site_id' );
		$result  = $this->settings_manager->reset_site( $site_id );

		if ( $result ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'Site reset' ) );
		}

		return new \WP_Error( 'reset_failed', 'Failed to reset site', array( 'status' => 500 ) );
	}

	/**
	 * Get comparison
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_comparison( $request ) {
		$sites      = get_sites( array( 'number' => 100 ) );
		$settings   = $this->settings_manager->get_all_with_status();
		$comparison = array();

		foreach ( $settings as $key => $config ) {
			$comparison[ $key ] = array(
				'network_value' => $config['value'],
				'locked'        => $config['locked'],
				'sites'         => array(),
			);

			foreach ( $sites as $site ) {
				$effective = $this->network_settings->get_effective_value( $key, $site->blog_id );
				$comparison[ $key ]['sites'][ $site->blog_id ] = array(
					'value'  => $effective['value'],
					'source' => $effective['source'],
				);
			}
		}

		return rest_ensure_response( array( 'comparison' => $comparison, 'sites' => $sites ) );
	}

	/**
	 * Get status
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_status( $request ) {
		$sites                = get_sites( array( 'number' => 0 ) );
		$total_sites          = count( $sites );
		$settings             = $this->settings_manager->get_all_with_status();
		$locked_settings      = 0;
		$total_overrides      = 0;

		foreach ( $settings as $config ) {
			if ( $config['locked'] ) {
				$locked_settings++;
			}
			$total_overrides += $config['overridden'];
		}

		return rest_ensure_response(
			array(
				'total_sites'     => $total_sites,
				'total_settings'  => count( $settings ),
				'locked_settings' => $locked_settings,
				'total_overrides' => $total_overrides,
			)
		);
	}

	/**
	 * Get audit log
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_audit( $request ) {
		$args = array(
			'limit'       => $request->get_param( 'limit' ),
			'offset'      => $request->get_param( 'offset' ),
			'action_type' => $request->get_param( 'action_type' ),
			'user_id'     => $request->get_param( 'user_id' ),
		);

		$entries = $this->settings_manager->get_audit_log( $args );

		return rest_ensure_response( $entries );
	}

	/**
	 * Export settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function export_settings( $request ) {
		$settings = $this->settings_manager->export_settings( 'array' );

		return rest_ensure_response( $settings );
	}

	/**
	 * Import settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function import_settings( $request ) {
		$settings = $request->get_param( 'settings' );
		$mode     = $request->get_param( 'mode' );

		$result = $this->settings_manager->import_settings( $settings, $mode );

		return rest_ensure_response( $result );
	}

	/**
	 * Get templates
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_templates( $request ) {
		$templates = $this->templates_manager->get_all_templates();

		return rest_ensure_response( $templates );
	}

	/**
	 * Get single template
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_template( $request ) {
		$slug     = $request->get_param( 'slug' );
		$template = $this->templates_manager->get_template( $slug );

		if ( ! $template ) {
			return new \WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $template );
	}

	/**
	 * Create template
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function create_template( $request ) {
		$name        = $request->get_param( 'name' );
		$slug        = $request->get_param( 'slug' );
		$description = $request->get_param( 'description' ) ?? '';

		$template_id = $this->templates_manager->save_current_as_template( $name, $slug, $description );

		if ( $template_id ) {
			return rest_ensure_response(
				array(
					'success'     => true,
					'message'     => 'Template created',
					'template_id' => $template_id,
				)
			);
		}

		return new \WP_Error( 'create_failed', 'Failed to create template', array( 'status' => 500 ) );
	}

	/**
	 * Delete template
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function delete_template( $request ) {
		$slug   = $request->get_param( 'slug' );
		$result = $this->templates_manager->delete_template( $slug );

		if ( $result ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'Template deleted' ) );
		}

		return new \WP_Error( 'delete_failed', 'Failed to delete template', array( 'status' => 500 ) );
	}

	/**
	 * Apply template
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function apply_template( $request ) {
		$slug  = $request->get_param( 'slug' );
		$scope = $request->get_param( 'scope' );
		$lock  = $request->get_param( 'lock' );
		$sites = $request->get_param( 'sites' ) ?? array();

		if ( 'network' === $scope ) {
			$result = $this->templates_manager->apply_to_network( $slug, $lock );

			if ( $result ) {
				return rest_ensure_response( array( 'success' => true, 'message' => 'Template applied to network' ) );
			}

			return new \WP_Error( 'apply_failed', 'Failed to apply template', array( 'status' => 500 ) );
		} else {
			if ( empty( $sites ) ) {
				$all_sites = get_sites( array( 'number' => 0 ) );
				$sites     = wp_list_pluck( $all_sites, 'blog_id' );
			}

			$result = $this->templates_manager->apply_to_sites( $slug, $sites );

			return rest_ensure_response( $result );
		}
	}
}
