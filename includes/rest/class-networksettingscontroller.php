<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Settings_Controller to this file name.
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
class Network_Settings_Controller extends Base_Controller {

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
							'enum'    => array( 'all', 'locked_only', 'defaults_only', 'follow' ),
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
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
						),
						'offset'      => array(
							'type'    => 'integer',
							'default' => 0,
							'minimum' => 0,
						),
						'page'        => array(
							'description' => __( 'Page (with per_page); the answer then carries X-WP-Total and X-WP-TotalPages (since 6.0.0).', 'zero-spam' ),
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'per_page'    => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => 25,
						),
						'action_type' => array(
							'type' => 'string',
							'enum' => \ZeroSpam\Includes\DB::AUDIT_ACTIONS,
						),
						'user_id'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'site_id'     => array(
							'description' => __( 'Entries of one site (0: the network).', 'zero-spam' ),
							'type'        => 'integer',
							'minimum'     => 0,
						),
						'setting_key' => array(
							'type'      => 'string',
							'maxLength' => 191,
						),
						'after'       => array(
							'description' => __( 'Entries on or after this day (YYYY-MM-DD, network time).', 'zero-spam' ),
							'type'        => 'string',
							'pattern'     => '^\\d{4}-\\d{2}-\\d{2}$',
						),
						'before'      => array(
							'description' => __( 'Entries on or before this day (YYYY-MM-DD, network time).', 'zero-spam' ),
							'type'        => 'string',
							'pattern'     => '^\\d{4}-\\d{2}-\\d{2}$',
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
						'settings'    => array(
							'description' => __( 'Values by setting key (since 6.0.0). Without it, the current network values are saved.', 'zero-spam' ),
							'type'        => 'object',
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
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_template' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'name'        => array(
							'type'      => 'string',
							'maxLength' => 255,
						),
						'description' => array(
							'type' => 'string',
						),
						'settings'    => array(
							'type' => 'object',
						),
					),
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
						'force' => array(
							'description' => __( 'With scope sites: also replace values a site changed itself (since 6.0.0). Locked settings are never written.', 'zero-spam' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'sites' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'maxItems' => \ZeroSpam\Includes\Network_Report::MAX_SITES,
						),
					),
				),
			)
		);

		// One setting, and its lock (registered last: `{key}` would also match
		// audit, export, import, templates and the other fixed routes above).
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
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function update_item( $request ) {
		$key    = $request->get_param( 'key' );
		$value  = $request->get_param( 'value' );
		$locked = $request->get_param( 'locked' );

		$result = $this->settings_manager->set_setting( $key, $value, $locked );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Setting updated',
				)
			);
		}

		return new \WP_Error( 'update_failed', 'Failed to update setting', array( 'status' => 500 ) );
	}

	/**
	 * Lock setting
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function lock_item( $request ) {
		$key    = $request->get_param( 'key' );
		$result = $this->settings_manager->lock_setting( $key );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Setting locked',
				)
			);
		}

		return new \WP_Error( 'lock_failed', 'Failed to lock setting', array( 'status' => 500 ) );
	}

	/**
	 * Unlock setting
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function unlock_item( $request ) {
		$key    = $request->get_param( 'key' );
		$result = $this->settings_manager->unlock_setting( $key );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Setting unlocked',
				)
			);
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
		$force = $request->get_param( 'force' );
		$mode  = $request->get_param( 'mode' );
		$sites = $request->get_param( 'sites' ) ?? array();

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
			$effective      = $this->network_settings->get_effective_value( $key, $site_id );
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
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function reset_site( $request ) {
		$site_id = $request->get_param( 'site_id' );
		$result  = $this->settings_manager->reset_site( $site_id );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Site reset',
				)
			);
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
				$effective                                     = $this->network_settings->get_effective_value( $key, (int) $site->blog_id );
				$comparison[ $key ]['sites'][ $site->blog_id ] = array(
					'value'  => $effective['value'],
					'source' => $effective['source'],
				);
			}
		}

		return rest_ensure_response(
			array(
				'comparison' => $comparison,
				'sites'      => $sites,
			)
		);
	}

	/**
	 * Get status
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_status( $request ) {
		$sites           = get_sites( array( 'number' => 0 ) );
		$total_sites     = count( $sites );
		$settings        = $this->settings_manager->get_all_with_status();
		$locked_settings = 0;
		$total_overrides = 0;

		foreach ( $settings as $config ) {
			if ( $config['locked'] ) {
				++$locked_settings;
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
	 * Without `page`, the entries as stored (`limit` and `offset`), as before
	 * 6.0.0. With `page`, a page of `per_page` entries and the X-WP-Total and
	 * X-WP-TotalPages headers. Filters: `action_type`, `user_id`, `site_id`,
	 * `setting_key`, `after` and `before` (days, network time). Since 6.0.0
	 * each entry also carries `id`, `date` (ISO 8601), `user`, `action`,
	 * `action_label`, `key`, `key_title`, `site_name`, `old`, `new` (secrets
	 * masked) and `summary`, a plain-language sentence.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_audit( $request ) {
		$paged    = null !== $request->get_param( 'page' );
		$per_page = $paged ? $this->clamp_per_page( $request->get_param( 'per_page' ), 25 ) : max( 1, min( self::MAX_PER_PAGE, (int) $request->get_param( 'limit' ) ) );
		$filters  = array(
			'action_type' => $request->get_param( 'action_type' ),
			'user_id'     => $request->get_param( 'user_id' ),
			'site_id'     => $request->get_param( 'site_id' ),
			'setting_key' => $request->get_param( 'setting_key' ),
			'after'       => $request->get_param( 'after' ),
			'before'      => $request->get_param( 'before' ),
		);

		$total = $paged ? $this->settings_manager->count_audit_log( $filters ) : 0;
		$page  = $paged ? max( 1, min( (int) $request->get_param( 'page' ), max( 1, (int) ceil( $total / $per_page ) ) ) ) : 1;

		$entries = $this->settings_manager->get_audit_log(
			$filters + array(
				'limit'  => $per_page,
				'offset' => $paged ? ( $page - 1 ) * $per_page : max( 0, (int) $request->get_param( 'offset' ) ),
			)
		);

		$items = array_map( array( \ZeroSpam\Includes\Network_Settings_Manager::class, 'describe_audit_entry' ), is_array( $entries ) ? $entries : array() );

		$response = rest_ensure_response( $items );

		if ( $paged ) {
			$response = $this->add_pagination_headers( $response, $total, $per_page );
			$response->header( 'X-ZeroSpam-Page', (string) $page );
		}

		return $response;
	}

	/**
	 * Export settings
	 *
	 * Since 6.0.0 license and API keys and webhook addresses are left out:
	 * they are secrets, and an export is meant to be copied around.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function export_settings( $request ) {
		$settings = $this->settings_manager->export_settings( 'array' );
		$settings = is_array( $settings ) ? $settings : array();

		foreach ( array_keys( $settings ) as $key ) {
			if ( \ZeroSpam\Core\Settings_Schema::is_secret( (string) $key ) ) {
				unset( $settings[ $key ] );
			}
		}

		return rest_ensure_response( (object) $settings );
	}

	/**
	 * Import settings
	 *
	 * Since 6.0.0 only settings Zero Spam knows are imported, with their
	 * values checked as PATCH /settings checks them; the answer lists what
	 * was skipped (`skipped_keys`) and what was invalid (`invalid`).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public function import_settings( $request ) {
		$settings = $request->get_param( 'settings' );
		$mode     = $request->get_param( 'mode' );

		if ( ! is_array( $settings ) || ! $settings ) {
			return $this->invalid_params_error( array( 'settings' => __( 'The file has no settings.', 'zero-spam' ) ) );
		}

		$values  = array();
		$locks   = array();
		$skipped = array();

		foreach ( $settings as $key => $item ) {
			$key   = (string) $key;
			$entry = \ZeroSpam\Core\Settings_Schema::setting( $key );

			if ( null === $entry || 'html' === $entry['type'] ) {
				$skipped[] = $key;
				continue;
			}

			$values[ $key ] = is_array( $item ) && array_key_exists( 'value', $item ) ? $item['value'] : $item;
			if ( is_array( $item ) && array_key_exists( 'locked', $item ) ) {
				$locks[ $key ] = (bool) $item['locked'];
			}
		}

		$checked = $values ? ( new Settings_Controller() )->validate_values( $values ) : array(
			'valid'  => array(),
			'errors' => array(),
		);

		$import = array();
		foreach ( $checked['valid'] as $key => $value ) {
			$import[ $key ] = array(
				'value'  => $value,
				'locked' => $locks[ $key ] ?? false,
			);
		}

		$result = $import ? $this->settings_manager->import_settings( $import, $mode ) : array(
			'success'        => true,
			'imported_count' => 0,
			'skipped_count'  => 0,
			'total_settings' => 0,
		);

		$result['skipped_keys']  = $skipped;
		$result['invalid']       = (object) $checked['errors'];
		$result['total_in_file'] = count( $settings );

		\ZeroSpam\Includes\Network_Report::flush();

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
	 * @return \WP_REST_Response|\WP_Error Response object or error.
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
	 * With `settings` (since 6.0.0), a template of those values (checked:
	 * settings a network can lock, valid values); without, the current
	 * network values.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function create_template( $request ) {
		$name        = trim( (string) $request->get_param( 'name' ) );
		$slug        = (string) $request->get_param( 'slug' );
		$description = (string) ( $request->get_param( 'description' ) ?? '' );
		$settings    = $request->get_param( 'settings' );
		$errors      = $this->template_errors( $name, $slug );
		$checked     = array(
			'valid'  => array(),
			'errors' => array(),
		);

		if ( ! isset( $errors['slug'] ) && $this->templates_manager->get_template( $slug ) ) {
			$errors['slug'] = __( 'A template already uses that slug. Choose another.', 'zero-spam' );
		}

		if ( null !== $settings ) {
			$checked = \ZeroSpam\Includes\Network_Templates::check_settings( $settings );
			if ( $checked['errors'] ) {
				$errors['settings'] = implode( ' ', $checked['errors'] );
			} elseif ( ! $checked['valid'] ) {
				$errors['settings'] = __( 'Choose at least one setting.', 'zero-spam' );
			}
		}

		if ( $errors ) {
			return $this->invalid_params_error( $errors );
		}

		$template_id = null !== $settings
			? $this->templates_manager->create_template( $name, $slug, $checked['valid'], $description )
			: $this->templates_manager->save_current_as_template( $name, $slug, $description );

		if ( $template_id ) {
			$response = rest_ensure_response(
				array(
					'success'     => true,
					'message'     => 'Template created',
					'template_id' => $template_id,
					'template'    => $this->templates_manager->get_template( $slug ),
				)
			);
			$response->set_status( 201 );

			return $response;
		}

		return new \WP_Error( 'create_failed', __( 'The template could not be saved. Please try again.', 'zero-spam' ), array( 'status' => 500 ) );
	}

	/**
	 * Changes a custom template (since 6.0.0): its name, description and
	 * settings. Built-in templates cannot be changed (save a copy instead).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function update_template( $request ) {
		$slug     = (string) $request->get_param( 'slug' );
		$template = $this->templates_manager->get_template( $slug );

		if ( ! $template ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'zero-spam' ), array( 'status' => 404 ) );
		}

		if ( 'custom' !== $template['type'] ) {
			return new \WP_Error( 'zerospam_template_built_in', __( 'Built-in templates cannot be changed. Save a copy instead.', 'zero-spam' ), array( 'status' => 403 ) );
		}

		$updates = array();
		$errors  = array();

		if ( null !== $request->get_param( 'name' ) ) {
			$updates['name'] = trim( (string) $request->get_param( 'name' ) );
			if ( '' === $updates['name'] ) {
				$errors['name'] = __( 'Enter a template name.', 'zero-spam' );
			}
		}

		if ( null !== $request->get_param( 'description' ) ) {
			$updates['description'] = (string) $request->get_param( 'description' );
		}

		if ( null !== $request->get_param( 'settings' ) ) {
			$checked = \ZeroSpam\Includes\Network_Templates::check_settings( $request->get_param( 'settings' ), array_keys( (array) $template['settings'] ) );
			if ( $checked['errors'] ) {
				$errors['settings'] = implode( ' ', $checked['errors'] );
			} elseif ( ! $checked['valid'] ) {
				$errors['settings'] = __( 'Choose at least one setting.', 'zero-spam' );
			} else {
				$updates['settings'] = $checked['valid'];
			}
		}

		if ( $errors ) {
			return $this->invalid_params_error( $errors );
		}

		if ( ! $updates || $this->templates_manager->update_template( $slug, $updates ) ) {
			return rest_ensure_response( $this->templates_manager->get_template( $slug ) );
		}

		return new \WP_Error( 'update_failed', __( 'The template could not be saved. Please try again.', 'zero-spam' ), array( 'status' => 500 ) );
	}

	/**
	 * What is wrong with a template's name and slug.
	 *
	 * @param string $name Name.
	 * @param string $slug Slug.
	 * @return array<string, string>
	 */
	private function template_errors( string $name, string $slug ): array {
		$errors = array();

		if ( '' === $name ) {
			$errors['name'] = __( 'Enter a template name.', 'zero-spam' );
		}

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,62}$/', $slug ) ) {
			$errors['slug'] = __( 'Use lowercase letters, numbers and hyphens, like clinic-sites.', 'zero-spam' );
		}

		return $errors;
	}

	/**
	 * Delete template
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function delete_template( $request ) {
		$slug   = $request->get_param( 'slug' );
		$result = $this->templates_manager->delete_template( $slug );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Template deleted',
				)
			);
		}

		return new \WP_Error( 'delete_failed', 'Failed to delete template', array( 'status' => 500 ) );
	}

	/**
	 * Apply template
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function apply_template( $request ) {
		$slug  = (string) $request->get_param( 'slug' );
		$scope = $request->get_param( 'scope' );
		$lock  = $request->get_param( 'lock' );
		$sites = $request->get_param( 'sites' ) ?? array();

		if ( ! $this->templates_manager->get_template( $slug ) ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'zero-spam' ), array( 'status' => 404 ) );
		}

		if ( 'network' === $scope ) {
			$result = $this->templates_manager->apply_to_network( $slug, $lock );

			if ( $result ) {
				return rest_ensure_response(
					array(
						'success' => true,
						'message' => 'Template applied to network',
					)
				);
			}

			return new \WP_Error( 'apply_failed', 'Failed to apply template', array( 'status' => 500 ) );
		}

		if ( empty( $sites ) ) {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => \ZeroSpam\Includes\Network_Report::MAX_SITES,
				)
			);
		}

		$result = $this->templates_manager->apply_to_sites( $slug, $sites, (bool) $request->get_param( 'force' ) );

		\ZeroSpam\Includes\Network_Report::flush();

		return rest_ensure_response( $result );
	}
}
