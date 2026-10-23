<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Settings_Controller to this file name.
/**
 * REST API Settings Controller
 *
 * Handles REST API endpoints for remote settings management.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Settings_Resolver;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * REST API Settings Controller
 *
 * Provides endpoints for reading and updating Zero Spam settings remotely.
 * Supports multisite with network/site scoping, dry-run mode, and audit trails.
 *
 * Since 6.0.0 every setting that holds a value (every type but `html`) can be
 * read and written, secrets are write-only (reads return them masked), network
 * values live in the network settings store only, and `settings/schema`
 * describes every setting.
 */
class Settings_Controller extends Base_Controller {

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
	 * Settings whitelist schema used for the route arguments: the original
	 * Enhanced Protection settings plus what `zero_spam_rest_settings_whitelist`
	 * adds. Built without the settings registry, so registering the routes
	 * stays cheap.
	 *
	 * @var array
	 */
	private $whitelist = array();

	/**
	 * Every setting the route accepts, built on first use in a request, by blog ID.
	 *
	 * @var array<int, array>
	 */
	private $full_whitelist = array();

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings_schema' ),
					'permission_callback' => array( $this, 'get_schema_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get the settings schema.
	 *
	 * Every section and setting of the registry, with its type, choices,
	 * limits, default, recommended value, area and group, whether it can be
	 * locked, whether it is locked, where its value comes from, and its value
	 * (secrets masked).
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings_schema( $request ) {
		unset( $request );

		$schema = Settings_Schema::get();

		return rest_ensure_response(
			array(
				'sections' => $schema['sections'],
				'settings' => $schema['settings'],
				'meta'     => $this->get_response_meta( 'resolved' ),
			)
		);
	}

	/**
	 * Permission check for the settings schema: the same as reading settings.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if permitted, error otherwise.
	 */
	public function get_schema_permissions_check( $request ) {
		$request = clone $request;
		$request->set_param( 'scope', 'resolved' );

		return $this->get_settings_permissions_check( $request );
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

		$response_data = array();

		// Determine which settings to return based on scope.
		switch ( $scope ) {
			case 'site':
				$response_data['settings'] = $this->mask_layer( $this->get_site_values( array_keys( $this->get_full_whitelist() ) ), Settings_Schema::SOURCE_SITE );
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

				$network                   = $this->get_network_layer( array_keys( $this->get_full_whitelist() ) );
				$response_data['settings'] = $this->mask_layer( $network['values'], Settings_Schema::SOURCE_NETWORK );
				$response_data['locked']   = $network['locked'];
				$response_data['meta']     = $this->get_response_meta( 'network' );
				break;

			case 'resolved':
			default:
				$resolved                  = $this->get_resolved( 'sources' === $include );
				$response_data['settings'] = $resolved['values'];
				if ( 'sources' === $include ) {
					$response_data['sources'] = $resolved['sources'];
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

		// A site may not change what the network administrator has locked: refuse the whole request.
		$locked_keys = 'site' === $scope ? $this->get_locked_keys( array_keys( $validated ) ) : array();
		if ( $locked_keys ) {
			return $this->locked_error( $locked_keys );
		}

		// Get existing settings to compute diff.
		if ( 'network' === $scope ) {
			$existing = $this->get_network_layer( array_keys( $validated ) )['values'];
		} else {
			$existing = $this->get_site_values( array_keys( $validated ) );
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
					'changes'  => $this->mask_changes( $changes, $this->layer_source( $scope ) ),
					'settings' => $this->mask_layer( $validated, $this->layer_source( $scope ) ),
					'meta'     => $this->get_response_meta( $scope ),
				)
			);
		}

		// Perform actual update.
		$result = $this->write_settings( $scope, $validated );

		if ( ! $result ) {
			return new WP_Error(
				'rest_update_failed',
				__( 'Failed to update settings.', 'zero-spam' ),
				array( 'status' => 500 )
			);
		}

		// On a network site, a value equal to the network value is not stored: the site keeps following the network default.
		if ( 'site' === $scope && is_multisite() ) {
			foreach ( $validated as $key => $new_value ) {
				if ( isset( Settings_Schema::OWN_OPTIONS[ $key ] ) ) {
					continue;
				}

				$stored = get_option( 'zero-spam-' . $this->module_of( $key ), array() );
				$stored = is_array( $stored ) ? $stored : array();

				if ( ! array_key_exists( $key, $stored ) ) {
					$changes[ $key ] = array(
						'old'    => isset( $existing[ $key ] ) ? $existing[ $key ] : null,
						'new'    => $new_value,
						'source' => 'network_default',
					);
				} elseif ( isset( $changes[ $key ] ) ) {
					$changes[ $key ]['source'] = 'site';
				}
			}
		}

		// Record audit entry (secrets masked).
		$this->add_audit_entry( $scope, $this->mask_changes( $changes, $this->layer_source( $scope ) ), false );

		$resolved = $this->get_resolved( false );

		return rest_ensure_response(
			array(
				'success'  => true,
				'changes'  => $this->mask_changes( $changes, $this->layer_source( $scope ) ),
				'settings' => $resolved['values'],
				'meta'     => $this->get_response_meta( $scope ),
			)
		);
	}

	/**
	 * Writes validated settings, module by module.
	 *
	 * @param string               $scope     `site` or `network`.
	 * @param array<string, mixed> $validated Validated values.
	 * @return bool Whether every write succeeded.
	 */
	private function write_settings( string $scope, array $validated ): bool {
		$by_module = array();
		$result    = true;

		foreach ( $validated as $key => $value ) {
			if ( 'site' === $scope && isset( Settings_Schema::OWN_OPTIONS[ $key ] ) ) {
				// Stored on its own and never autoloaded, as the settings page does.
				update_option( Settings_Schema::OWN_OPTIONS[ $key ], $value, false );
				continue;
			}

			$by_module[ $this->module_of( $key ) ][ $key ] = $value;
		}

		foreach ( $by_module as $module => $values ) {
			$result = Settings_Resolver::update_settings( $module, $scope, $values ) && $result;
		}

		Settings_Resolver::invalidate_all();
		Settings_Schema::reset_cache();
		self::reset_request_cache();

		// After the schema cache is reset, so the cache of the new key is the one cleared.
		if ( array_key_exists( 'zerospam_license', $validated ) ) {
			License_Status::reset_cache();
		}

		return $result;
	}

	/**
	 * Resolved values of every accepted setting (secrets masked).
	 *
	 * @param bool $with_sources Whether to return where each value comes from.
	 * @return array{values: array<string, mixed>, sources: array<string, string>}
	 */
	private function get_resolved( bool $with_sources ): array {
		$whitelist = $this->get_full_whitelist();
		$modules   = array();

		foreach ( $whitelist as $key => $schema ) {
			$modules[ $this->module_of( (string) $key ) ][ $key ] = isset( $schema['default'] ) ? $schema['default'] : null;
		}

		$values  = array();
		$sources = array();

		foreach ( $modules as $module => $module_keys ) {
			$defaults = array_filter(
				$module_keys,
				static function ( $value ) {
					return null !== $value;
				}
			);

			if ( $with_sources ) {
				$resolved       = Settings_Resolver::get_resolved_with_sources( $module, $defaults );
				$module_values  = $resolved['values'];
				$module_sources = $resolved['sources'];
			} else {
				$module_values  = Settings_Resolver::get_resolved_settings( $module, $defaults );
				$module_sources = array();
			}

			foreach ( array_keys( $module_keys ) as $key ) {
				if ( Settings_Schema::is_secret( (string) $key ) ) {
					$masked          = Settings_Schema::masked( (string) $key );
					$values[ $key ]  = $masked;
					$sources[ $key ] = $masked['source'];
					continue;
				}

				if ( isset( Settings_Schema::OWN_OPTIONS[ $key ] ) ) {
					$resolved_own = Settings_Schema::resolve( (string) $key );
					if ( null !== $resolved_own ) {
						$values[ $key ]  = $resolved_own['value'];
						$sources[ $key ] = $resolved_own['source'];
						continue;
					}
				}

				if ( array_key_exists( $key, $module_values ) ) {
					$values[ $key ] = $module_values[ $key ];
					if ( isset( $module_sources[ $key ] ) ) {
						$sources[ $key ] = $module_sources[ $key ];
					}
				}
			}
		}

		if ( ! $with_sources ) {
			$sources = array();
		}

		return array(
			'values'  => $values,
			'sources' => $sources,
		);
	}

	/**
	 * Values the current site stores for some settings.
	 *
	 * @param string[] $keys Setting keys.
	 * @return array<string, mixed>
	 */
	private function get_site_values( array $keys ): array {
		$values = array();

		foreach ( $keys as $key ) {
			$key = (string) $key;

			if ( isset( Settings_Schema::OWN_OPTIONS[ $key ] ) ) {
				$own = get_option( Settings_Schema::OWN_OPTIONS[ $key ] );
				if ( false !== $own ) {
					$values[ $key ] = $own;
				}
				continue;
			}

			$stored = get_option( 'zero-spam-' . $this->module_of( $key ), array() );
			if ( is_array( $stored ) && array_key_exists( $key, $stored ) ) {
				$values[ $key ] = $stored[ $key ];
			}
		}

		return $values;
	}

	/**
	 * Network values of some settings, and which of them are locked.
	 *
	 * @param string[] $keys Setting keys.
	 * @return array{values: array<string, mixed>, locked: string[]}
	 */
	private function get_network_layer( array $keys ): array {
		$layer = array(
			'values' => array(),
			'locked' => array(),
		);

		if ( ! is_multisite() ) {
			return $layer;
		}

		$store = Settings_Schema::normalize_network_store( get_site_option( \ZeroSpam\Includes\Network_Settings::META_KEY, array() ) );

		foreach ( $keys as $key ) {
			$config = $store['settings'][ $key ] ?? null;

			if ( ! is_array( $config ) ) {
				continue;
			}

			// Locked whatever its value, as Network_Settings::is_locked() says.
			if ( ! empty( $config['locked'] ) ) {
				$layer['locked'][] = (string) $key;
			}

			// A null value is no network value (the isset() test Network_Settings uses).
			if ( isset( $config['value'] ) ) {
				$layer['values'][ $key ] = $config['value'];
			}
		}

		return $layer;
	}

	/**
	 * Replaces secret values of one storage layer by their masked form.
	 *
	 * @param array<string, mixed> $values Values by key.
	 * @param string               $source Where they are stored (`site` or `network`).
	 * @return array<string, mixed>
	 */
	private function mask_layer( array $values, string $source ): array {
		foreach ( $values as $key => $value ) {
			if ( Settings_Schema::is_secret( (string) $key ) ) {
				$values[ $key ] = $this->mask_value( $value, $source );
			}
		}

		return $values;
	}

	/**
	 * Masks the secrets in a list of changes.
	 *
	 * @param array<string, array<string, mixed>> $changes Changes by key.
	 * @param string                              $source  Where the values are stored (`site` or `network`).
	 * @return array<string, array<string, mixed>>
	 */
	private function mask_changes( array $changes, string $source ): array {
		foreach ( $changes as $key => $change ) {
			if ( ! Settings_Schema::is_secret( (string) $key ) ) {
				continue;
			}

			foreach ( array( 'old', 'new' ) as $side ) {
				if ( array_key_exists( $side, $change ) ) {
					$changes[ $key ][ $side ] = $this->mask_value( $change[ $side ], $source );
				}
			}
		}

		return $changes;
	}

	/**
	 * The source a write scope stores values in.
	 *
	 * @param string $scope `site` or `network`.
	 */
	private function layer_source( string $scope ): string {
		return 'network' === $scope ? Settings_Schema::SOURCE_NETWORK : Settings_Schema::SOURCE_SITE;
	}

	/**
	 * The masked form of one secret value.
	 *
	 * @param mixed  $value  Secret.
	 * @param string $source Where it is stored.
	 * @return array{set: bool, source: string, hint?: string}
	 */
	private function mask_value( $value, string $source ): array {
		$value  = is_scalar( $value ) && ! is_bool( $value ) ? trim( (string) $value ) : '';
		$masked = array(
			'set'    => '' !== $value,
			'source' => $source,
		);

		if ( '' !== $value ) {
			$masked['hint'] = Settings_Schema::hint( $value );
		}

		return $masked;
	}

	/**
	 * Module (and so option) a setting belongs to.
	 *
	 * Keys added through `zero_spam_rest_settings_whitelist` that the registry
	 * does not know are stored with the Enhanced Protection settings, as
	 * before 6.0.0, unless their whitelist entry names a `module`.
	 *
	 * @param string $key Setting key.
	 */
	private function module_of( string $key ): string {
		$whitelist = $this->get_full_whitelist();

		if ( ! empty( $whitelist[ $key ]['module'] ) && is_string( $whitelist[ $key ]['module'] ) ) {
			return $whitelist[ $key ]['module'];
		}

		$entry = Settings_Schema::setting( $key );

		return null !== $entry ? (string) $entry['module'] : 'zerospam';
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
	 * Settings among the given keys that the network administrator has locked.
	 *
	 * @param string[] $keys Setting keys.
	 * @return string[] Locked keys (empty on a single site).
	 */
	private function get_locked_keys( array $keys ): array {
		if ( ! is_multisite() || ! $keys ) {
			return array();
		}

		return $this->get_network_layer( array_map( 'strval', $keys ) )['locked'];
	}

	/**
	 * Get settings whitelist with schema.
	 *
	 * The original Enhanced Protection settings, filtered. Used for the route
	 * arguments; requests use get_full_whitelist().
	 *
	 * @return array Whitelist array.
	 */
	private function get_settings_whitelist() {
		/**
		 * Filter the settings whitelist for REST API.
		 *
		 * Allows adding custom settings to REST API exposure, or removing
		 * settings from it. Since 6.0.0 the filter also runs when a request is
		 * handled, then with every setting of the registry that holds a value.
		 *
		 * @param array $whitelist Settings whitelist array.
		 */
		return apply_filters( 'zero_spam_rest_settings_whitelist', $this->get_base_whitelist() );
	}

	/**
	 * The settings the route accepted before 6.0.0, with their own rules.
	 *
	 * @return array Whitelist array.
	 */
	private function get_base_whitelist() {
		return array(
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
	}

	/**
	 * Every setting the route accepts: the original ones, every setting of the
	 * registry that holds a value, and what `zero_spam_rest_settings_whitelist`
	 * adds or removes.
	 *
	 * @since 6.0.0
	 *
	 * @return array Whitelist array.
	 */
	private function get_full_whitelist() {
		$blog_id = get_current_blog_id();

		if ( isset( $this->full_whitelist[ $blog_id ] ) ) {
			return $this->full_whitelist[ $blog_id ];
		}

		$whitelist = $this->get_base_whitelist();

		foreach ( Settings_Schema::get()['settings'] as $entry ) {
			if ( 'html' === $entry['type'] ) {
				continue;
			}

			if ( isset( $whitelist[ $entry['key'] ] ) ) {
				$whitelist[ $entry['key'] ]['module'] = $entry['module'];
				continue;
			}

			$whitelist[ $entry['key'] ] = array(
				'type'            => $this->json_type( $entry ),
				'description'     => $entry['title'],
				'default'         => $entry['default'],
				'module'          => $entry['module'],
				'zerospam_schema' => true,
			);
		}

		/** This filter is documented in get_settings_whitelist(). */
		$filtered                         = apply_filters( 'zero_spam_rest_settings_whitelist', $whitelist );
		$this->full_whitelist[ $blog_id ] = is_array( $filtered ) ? $filtered : array();

		return $this->full_whitelist[ $blog_id ];
	}

	/**
	 * JSON type of a setting.
	 *
	 * @param array<string, mixed> $entry Schema entry.
	 * @return string|string[]
	 */
	private function json_type( array $entry ) {
		switch ( $entry['type'] ) {
			case 'number':
				return 'number';
			case 'checkbox':
				return array( 'string', 'boolean' );
			case 'select':
				return $entry['multiple'] ? 'array' : 'string';
			default:
				return 'string';
		}
	}

	/**
	 * Checks and normalizes a value for a setting of the registry.
	 *
	 * @since 6.0.0
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Submitted value.
	 * @return mixed|WP_Error Value to store, or the reason it is refused.
	 */
	private function normalize_value( string $key, $value ) {
		$entry   = Settings_Schema::setting( $key );
		$options = $this->option_keys( $key );

		if ( null === $entry ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : new WP_Error( 'invalid', __( 'Must be text.', 'zero-spam' ) );
		}

		switch ( $entry['type'] ) {
			case 'checkbox':
				if ( false === $value || '' === $value || null === $value ) {
					return false;
				}
				if ( true === $value && $options ) {
					return $options[0];
				}
				$match = $this->match_option( $value, $options );

				return null !== $match ? $match : new WP_Error(
					'invalid',
					/* translators: %s: comma-separated list of allowed values */
					sprintf( __( 'Must be false or one of: %s.', 'zero-spam' ), implode( ', ', array_map( 'strval', $options ) ) )
				);

			case 'radio':
			case 'select':
				if ( ! empty( $entry['multiple'] ) ) {
					if ( ! is_array( $value ) ) {
						return new WP_Error( 'invalid', __( 'Must be a list.', 'zero-spam' ) );
					}

					$selected = array();
					foreach ( $value as $item ) {
						$match = $this->match_option( $item, $options );
						if ( null === $match ) {
							return new WP_Error(
								'invalid',
								/* translators: %s: comma-separated list of allowed values */
								sprintf( __( 'Every item must be one of: %s.', 'zero-spam' ), implode( ', ', array_map( 'strval', $options ) ) )
							);
						}
						$selected[] = $match;
					}

					return array_values( array_unique( $selected ) );
				}

				$match = $this->match_option( $value, $options );

				return null !== $match ? $match : new WP_Error(
					'invalid',
					/* translators: %s: comma-separated list of allowed values */
					sprintf( __( 'Must be one of: %s.', 'zero-spam' ), implode( ', ', array_map( 'strval', $options ) ) )
				);

			case 'number':
				return $this->normalize_number( $entry, $value );

			case 'url':
				if ( ! is_string( $value ) ) {
					return new WP_Error( 'invalid', __( 'Must be a web address.', 'zero-spam' ) );
				}
				$value = trim( $value );
				if ( '' === $value ) {
					return '';
				}
				$url = esc_url_raw( $value, array( 'http', 'https' ) );

				return '' !== $url && false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : new WP_Error( 'invalid', __( 'Must be a web address starting with http:// or https://.', 'zero-spam' ) );

			case 'date':
				if ( ! is_string( $value ) ) {
					return new WP_Error( 'invalid', __( 'Must be a date (YYYY-MM-DD) or empty.', 'zero-spam' ) );
				}
				$value = trim( $value );
				if ( '' === $value ) {
					return '';
				}
				$valid = 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) && checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );

				return $valid ? $value : new WP_Error( 'invalid', __( 'Must be a date (YYYY-MM-DD) or empty.', 'zero-spam' ) );

			case 'textarea':
				if ( ! is_string( $value ) ) {
					return new WP_Error( 'invalid', __( 'Must be text.', 'zero-spam' ) );
				}

				if ( 'trusted_proxies' === $key ) {
					$proxies = \ZeroSpam\Core\Utilities::parse_ip_ranges( $value );
					$dropped = \ZeroSpam\Core\Utilities::describe_dropped_ip_ranges( $proxies );

					return $dropped ? new WP_Error(
						'invalid',
						/* translators: %s: comma-separated list of the entries that are not accepted */
						sprintf( __( 'These entries are not IP addresses or ranges, or are too broad: %s', 'zero-spam' ), implode( ', ', $dropped ) )
					) : implode( "\n", $proxies['valid'] );
				}

				return sanitize_textarea_field( $value );

			default:
				if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
					return new WP_Error( 'invalid', __( 'Must be text.', 'zero-spam' ) );
				}

				$value = sanitize_text_field( (string) $value );

				return $entry['secret'] ? trim( $value ) : $value;
		}
	}

	/**
	 * Checks a number against a setting's limits.
	 *
	 * @param array<string, mixed> $entry Schema entry.
	 * @param mixed                $value Submitted value.
	 * @return int|float|WP_Error
	 */
	private function normalize_number( array $entry, $value ) {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return new WP_Error( 'invalid', __( 'Must be a number.', 'zero-spam' ) );
		}

		$number = 0 + $value;

		if ( null !== $entry['min'] && $number < $entry['min'] ) {
			/* translators: %s: smallest allowed number */
			return new WP_Error( 'invalid', sprintf( __( 'Must be at least %s.', 'zero-spam' ), $entry['min'] ) );
		}

		if ( null !== $entry['max'] && $number > $entry['max'] ) {
			/* translators: %s: largest allowed number */
			return new WP_Error( 'invalid', sprintf( __( 'Must be at most %s.', 'zero-spam' ), $entry['max'] ) );
		}

		$whole_steps = null === $entry['step'] || is_int( $entry['step'] );

		if ( $whole_steps && floor( (float) $number ) !== (float) $number ) {
			return new WP_Error( 'invalid', __( 'Must be a whole number.', 'zero-spam' ) );
		}

		return $whole_steps ? (int) $number : (float) $number;
	}

	/**
	 * The choices of a setting as the registry defines them (keys keep their type).
	 *
	 * @param string $key Setting key.
	 * @return array<int, int|string>
	 */
	private function option_keys( string $key ): array {
		$registry = Settings_Schema::registry();

		return isset( $registry[ $key ]['options'] ) && is_array( $registry[ $key ]['options'] ) ? array_keys( $registry[ $key ]['options'] ) : array();
	}

	/**
	 * The choice a submitted value names, or null.
	 *
	 * @param mixed                  $value   Submitted value.
	 * @param array<int, int|string> $options Choices.
	 * @return int|string|null
	 */
	private function match_option( $value, array $options ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return null;
		}

		foreach ( $options as $option ) {
			if ( (string) $option === (string) $value ) {
				return $option;
			}
		}

		return null;
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
	 * Errors keep the `rest_invalid_settings` code and `errors` list the
	 * route has always returned; since 6.0.0 `params` carries the same
	 * messages in the shape WordPress uses for `rest_invalid_param`.
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

		$whitelist = $this->get_full_whitelist();
		$validated = array();
		$errors    = array();

		foreach ( $settings as $key => $value ) {
			$key = (string) $key;

			// Check if key is whitelisted.
			if ( ! isset( $whitelist[ $key ] ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: setting key */
					__( 'Setting "%s" is not allowed or does not exist.', 'zero-spam' ),
					$key
				);
				continue;
			}

			$schema = $whitelist[ $key ];

			if ( ! empty( $schema['zerospam_schema'] ) ) {
				$normalized = $this->normalize_value( $key, $value );

				if ( is_wp_error( $normalized ) ) {
					$errors[ $key ] = sprintf(
						/* translators: 1: setting key, 2: what is wrong with the value */
						__( 'Invalid value for "%1$s". %2$s', 'zero-spam' ),
						$key,
						$normalized->get_error_message()
					);
					continue;
				}

				$validated[ $key ] = $normalized;
				continue;
			}

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
					'params' => $errors,
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
