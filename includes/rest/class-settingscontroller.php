<?php
/**
 * REST API Settings Controller
 *
 * Handles REST API endpoints for remote settings management.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * REST API Settings Controller
 *
 * Provides endpoints for reading and updating Zero Spam settings remotely.
 * Supports multisite with network/site scoping, dry-run mode, and audit trails.
 */
class Settings_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'settings';

	/**
	 * Settings whitelist schema.
	 *
	 * @var array
	 */
	private $whitelist = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->whitelist = $this->get_settings_whitelist();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_settings_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_settings_permissions_check' ),
					'args'                => $this->get_update_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_settings( $request ) {
		$scope   = $request->get_param( 'scope' );
		$include = $request->get_param( 'include' );

		// Get defaults from whitelist.
		$defaults = array();
		foreach ( $this->whitelist as $key => $schema ) {
			if ( isset( $schema['default'] ) ) {
				$defaults[ $key ] = $schema['default'];
			}
		}

		$response_data = array();

		// Determine which settings to return based on scope.
		switch ( $scope ) {
			case 'site':
				$site_settings             = get_option( 'zero-spam-zerospam', array() );
				$response_data['settings'] = is_array( $site_settings ) ? $site_settings : array();
				$response_data['meta']     = $this->get_response_meta( 'site' );
				break;

			case 'network':
				if ( ! is_multisite() ) {
					return new WP_Error(
						'rest_not_multisite',
						__( 'Network scope is only available in multisite installations.', 'zero-spam' ),
						array( 'status' => 400 )
					);
				}

				$network_settings          = get_site_option( 'zero-spam-network-zerospam', array() );
				$response_data['settings'] = is_array( $network_settings ) ? $network_settings : array();
				$response_data['meta']     = $this->get_response_meta( 'network' );
				break;

			case 'resolved':
			default:
				if ( 'sources' === $include ) {
					$resolved                  = \ZeroSpam\Includes\Settings_Resolver::get_resolved_with_sources( 'zerospam', $defaults );
					$response_data['settings'] = $resolved['values'];
					$response_data['sources']  = $resolved['sources'];
				} else {
					$response_data['settings'] = \ZeroSpam\Includes\Settings_Resolver::get_resolved_settings( 'zerospam', $defaults );
				}
				$response_data['meta'] = $this->get_response_meta( 'resolved' );
				break;
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_settings( $request ) {
		$scope   = $request->get_param( 'scope' );
		$dry_run = $request->get_param( 'dry_run' );
		$body    = $request->get_json_params();

		// Validate scope for network updates.
		if ( 'network' === $scope && ! is_multisite() ) {
			return new WP_Error(
				'rest_not_multisite',
				__( 'Network scope is only available in multisite installations.', 'zero-spam' ),
				array( 'status' => 400 )
			);
		}

		// Use 'site' as default scope if not specified or if 'resolved' provided.
		if ( empty( $scope ) || 'resolved' === $scope ) {
			$scope = 'site';
		}

		// Validate and sanitize settings.
		$validated = $this->validate_settings( $body );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		// Get existing settings to compute diff.
		$existing = array();
		if ( 'network' === $scope ) {
			$existing = get_site_option( 'zero-spam-network-zerospam', array() );
		} else {
			$existing = get_option( 'zero-spam-zerospam', array() );
		}

		// Compute changes.
		$changes = array();
		foreach ( $validated as $key => $new_value ) {
			$old_value = isset( $existing[ $key ] ) ? $existing[ $key ] : null;
			if ( $old_value !== $new_value ) {
				$changes[ $key ] = array(
					'old' => $old_value,
					'new' => $new_value,
				);
			}
		}

		// If dry-run, return validation result without writing.
		if ( $dry_run ) {
			return rest_ensure_response(
				array(
					'dry_run'  => true,
					'valid'    => true,
					'changes'  => $changes,
					'settings' => $validated,
					'meta'     => $this->get_response_meta( $scope ),
				)
			);
		}

		// Perform actual update.
		$result = \ZeroSpam\Includes\Settings_Resolver::update_settings( 'zerospam', $scope, $validated );

		if ( ! $result ) {
			return new WP_Error(
				'rest_update_failed',
				__( 'Failed to update settings.', 'zero-spam' ),
				array( 'status' => 500 )
			);
		}

		// Record audit entry.
		$this->add_audit_entry( $scope, $changes, false );

		// Get updated settings.
		$defaults = array();
		foreach ( $this->whitelist as $key => $schema ) {
			if ( isset( $schema['default'] ) ) {
				$defaults[ $key ] = $schema['default'];
			}
		}

		$updated_settings = \ZeroSpam\Includes\Settings_Resolver::get_resolved_settings( 'zerospam', $defaults );

		return rest_ensure_response(
			array(
				'success'  => true,
				'changes'  => $changes,
				'settings' => $updated_settings,
				'meta'     => $this->get_response_meta( $scope ),
			)
		);
	}

	/**
	 * Permission check for getting settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if permitted, error otherwise.
	 */
	public function get_settings_permissions_check( $request ) {
		$scope = $request->get_param( 'scope' );

		if ( 'network' === $scope ) {
			$capability = 'manage_network_options';
		} else {
			$capability = 'manage_options';
		}

		/**
		 * Filter the capability required to read settings via REST API.
		 *
		 * @param string $capability Required capability.
		 * @param string $scope      Settings scope (site, network, resolved).
		 */
		$capability = apply_filters( 'zero_spam_rest_settings_read_capability', $capability, $scope );

		if ( ! current_user_can( $capability ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view settings.', 'zero-spam' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Permission check for updating settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if permitted, error otherwise.
	 */
	public function update_settings_permissions_check( $request ) {
		$scope = $request->get_param( 'scope' );

		// Default to site scope if not specified or if resolved.
		if ( empty( $scope ) || 'resolved' === $scope ) {
			$scope = 'site';
		}

		if ( 'network' === $scope ) {
			$capability = 'manage_network_options';
		} else {
			$capability = 'manage_options';
		}

		/**
		 * Filter the capability required to update settings via REST API.
		 *
		 * @param string $capability Required capability.
		 * @param string $scope      Settings scope (site or network).
		 */
		$capability = apply_filters( 'zero_spam_rest_settings_update_capability', $capability, $scope );

		if ( ! current_user_can( $capability ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to update settings.', 'zero-spam' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get settings whitelist with schema.
	 *
	 * @return array Whitelist array.
	 */
	private function get_settings_whitelist() {
		$whitelist = array(
			'zerospam'                => array(
				'type'              => 'string',
				'enum'              => array( 'enabled', false ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_zerospam' ),
				'default'           => false,
				'description'       => __( 'Enable Enhanced Protection', 'zero-spam' ),
			),
			'zerospam_confidence_min' => array(
				'type'              => 'number',
				'minimum'           => 0,
				'maximum'           => 100,
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'validate_callback' => array( $this, 'validate_confidence' ),
				'default'           => 30,
				'description'       => __( 'Confidence threshold (0-100%)', 'zero-spam' ),
			),
		);

		/**
		 * Filter the settings whitelist for REST API.
		 *
		 * Allows adding custom settings to REST API exposure.
		 *
		 * @param array $whitelist Settings whitelist array.
		 */
		return apply_filters( 'zero_spam_rest_settings_whitelist', $whitelist );
	}

	/**
	 * Sanitize float value for REST API.
	 *
	 * WordPress REST API passes 3 arguments to sanitize callbacks,
	 * but floatval only accepts 1.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return float Sanitized float value.
	 */
	public function sanitize_float( $value ) {
		return floatval( $value );
	}

	/**
	 * Validate Enhanced Protection setting.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool True if valid.
	 */
	public function validate_zerospam( $value ) {
		return in_array( $value, array( 'enabled', false, '' ), true );
	}

	/**
	 * Validate confidence threshold setting.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool True if valid.
	 */
	public function validate_confidence( $value ) {
		$float_value = floatval( $value );
		return $float_value >= 0 && $float_value <= 100;
	}

	/**
	 * Validate settings against whitelist.
	 *
	 * @param array $settings Settings to validate.
	 * @return array|WP_Error Validated settings or error.
	 */
	private function validate_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Settings must be provided as an object.', 'zero-spam' ),
				array( 'status' => 400 )
			);
		}

		$validated = array();
		$errors    = array();

		foreach ( $settings as $key => $value ) {
			// Check if key is whitelisted.
			if ( ! isset( $this->whitelist[ $key ] ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: setting key */
					__( 'Setting "%s" is not allowed or does not exist.', 'zero-spam' ),
					$key
				);
				continue;
			}

			$schema = $this->whitelist[ $key ];

			// Sanitize value.
			if ( isset( $schema['sanitize_callback'] ) && is_callable( $schema['sanitize_callback'] ) ) {
				$value = call_user_func( $schema['sanitize_callback'], $value );
			}

			// Validate value.
			if ( isset( $schema['validate_callback'] ) && is_callable( $schema['validate_callback'] ) ) {
				$is_valid = call_user_func( $schema['validate_callback'], $value );
				if ( ! $is_valid ) {
					$errors[ $key ] = sprintf(
						/* translators: 1: setting key, 2: schema description */
						__( 'Invalid value for "%1$s". %2$s', 'zero-spam' ),
						$key,
						isset( $schema['description'] ) ? $schema['description'] : ''
					);
					continue;
				}
			}

			$validated[ $key ] = $value;
		}

		// Return errors if any.
		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'rest_invalid_settings',
				__( 'One or more settings are invalid.', 'zero-spam' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		return $validated;
	}

	/**
	 * Add audit entry for settings change.
	 *
	 * @param string $scope   Scope (site or network).
	 * @param array  $changes Changes array.
	 * @param bool   $dry_run Whether this was a dry-run.
	 */
	private function add_audit_entry( $scope, $changes, $dry_run ) {
		if ( empty( $changes ) ) {
			return;
		}

		$audit = get_option( 'zerospam_settings_audit', array() );
		if ( ! is_array( $audit ) ) {
			$audit = array();
		}

		$current_user = wp_get_current_user();

		$entry = array(
			'timestamp'  => current_time( 'mysql' ),
			'user_id'    => $current_user->ID,
			'user_login' => $current_user->user_login,
			'scope'      => $scope,
			'dry_run'    => $dry_run,
			'changes'    => $changes,
		);

		if ( is_multisite() ) {
			$entry['blog_id'] = get_current_blog_id();
		}

		// Add entry to beginning of array.
		array_unshift( $audit, $entry );

		// Keep only last 100 entries.
		$audit = array_slice( $audit, 0, 100 );

		update_option( 'zerospam_settings_audit', $audit, false );
	}

	/**
	 * Get response metadata.
	 *
	 * @param string $scope Scope.
	 * @return array Metadata array.
	 */
	private function get_response_meta( $scope ) {
		$meta = array(
			'plugin_version' => ZEROSPAM_VERSION,
			'timestamp'      => current_time( 'c' ),
			'scope'          => $scope,
			'is_multisite'   => is_multisite(),
		);

		if ( is_multisite() ) {
			$meta['blog_id'] = get_current_blog_id();

			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}
			$meta['is_network_active'] = is_plugin_active_for_network( ZEROSPAM_PLUGIN_BASE );
		}

		return $meta;
	}

	/**
	 * Get collection parameters for GET requests.
	 *
	 * @return array Parameters array.
	 */
	public function get_collection_params() {
		return array(
			'scope'   => array(
				'description'       => __( 'Settings scope: site, network, or resolved (default).', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'site', 'network', 'resolved' ),
				'default'           => 'resolved',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'include' => array(
				'description'       => __( 'Additional data to include: sources.', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'sources' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get update parameters for PATCH requests.
	 *
	 * @return array Parameters array.
	 */
	public function get_update_params() {
		$params = array(
			'scope'   => array(
				'description'       => __( 'Settings scope: site or network.', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'site', 'network' ),
				'default'           => 'site',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'dry_run' => array(
				'description'       => __( 'Validate without writing (1) or write changes (0).', 'zero-spam' ),
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);

		// Add whitelisted settings as body parameters.
		foreach ( $this->whitelist as $key => $schema ) {
			$params[ $key ] = array(
				'description'       => isset( $schema['description'] ) ? $schema['description'] : '',
				'type'              => $schema['type'],
				'sanitize_callback' => isset( $schema['sanitize_callback'] ) ? $schema['sanitize_callback'] : null,
			);

			if ( isset( $schema['enum'] ) ) {
				$params[ $key ]['enum'] = $schema['enum'];
			}

			if ( isset( $schema['minimum'] ) ) {
				$params[ $key ]['minimum'] = $schema['minimum'];
			}

			if ( isset( $schema['maximum'] ) ) {
				$params[ $key ]['maximum'] = $schema['maximum'];
			}
		}

		return $params;
	}

	/**
	 * Get public item schema.
	 *
	 * @return array Schema array.
	 */
	public function get_public_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'zero-spam-settings',
			'type'       => 'object',
			'properties' => array(
				'settings' => array(
					'description' => __( 'Settings values.', 'zero-spam' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(),
				),
				'sources'  => array(
					'description' => __( 'Source of each setting (default, network, site).', 'zero-spam' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
				),
				'meta'     => array(
					'description' => __( 'Response metadata.', 'zero-spam' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		// Add whitelisted settings to schema.
		foreach ( $this->whitelist as $key => $setting_schema ) {
			$schema['properties']['settings']['properties'][ $key ] = array(
				'description' => isset( $setting_schema['description'] ) ? $setting_schema['description'] : '',
				'type'        => $setting_schema['type'],
			);
		}

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
