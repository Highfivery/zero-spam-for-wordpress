<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Templates to this file name.
/**
 * Network templates: saved sets of network settings.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

use ZeroSpam\Core\Settings_Schema;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Templates are saved sets of settings a network administrator applies to
 * the network defaults or to chosen sites. Three are built in (Strict,
 * Balanced and Relaxed Protection); custom ones live in the
 * `wpzerospam_network_templates` table.
 *
 * Since 6.0.0 the built-in templates use the plugin's real setting keys
 * (before, 18 of their keys were names no setting had, so applying them
 * changed nothing; the `v6_template_orphans` migration removed those keys
 * from network settings and custom templates). A template holds settings a
 * network can lock (no secrets, no help text); keys of integrations whose
 * plugin is not active on the main site are kept as they are.
 *
 * Every change is recorded in the network audit log.
 *
 * @since 5.6.0
 */
class Network_Templates {

	/**
	 * Built-in templates.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $built_in_templates = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_built_in_templates();
	}

	/**
	 * Settings of the built-in templates, on real keys (since 6.0.0).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function built_in_settings(): array {
		return array(
			'strict_protection' => array(
				'zerospam'                    => 'enabled',
				'zerospam_confidence_min'     => 30,
				'verify_comments'             => 'enabled',
				'log_blocked_comments'        => 'enabled',
				'verify_registrations'        => 'enabled',
				'log_blocked_registrations'   => 'enabled',
				'verify_login'                => 'enabled',
				'davidwalsh'                  => 'enabled',
				'verify_contactform7'         => 'enabled',
				'verify_wpforms'              => 'enabled',
				'verify_gravityforms'         => 'enabled',
				'verify_fluentforms'          => 'enabled',
				'verify_woocommerce_checkout' => 'enabled',
				'api_monitoring'              => 'enabled',
				'disable_xmlrpc'              => 'enabled',
				'hide_wp_version'             => 'enabled',
			),
			'balanced'          => array(
				'zerospam'                => 'enabled',
				'zerospam_confidence_min' => 50,
				'verify_comments'         => 'enabled',
				'log_blocked_comments'    => 'enabled',
				'verify_registrations'    => 'enabled',
				'verify_login'            => 'enabled',
				'davidwalsh'              => 'enabled',
				'verify_contactform7'     => 'enabled',
				'verify_wpforms'          => 'enabled',
				'api_monitoring'          => 'enabled',
				'disable_xmlrpc'          => 'enabled',
			),
			'relaxed'           => array(
				'zerospam'                => 'enabled',
				'zerospam_confidence_min' => 70,
				'verify_comments'         => 'enabled',
				'log_blocked_comments'    => false,
				'verify_registrations'    => false,
				'davidwalsh'              => 'enabled',
				'api_monitoring'          => false,
				'disable_xmlrpc'          => 'disabled',
			),
		);
	}

	/**
	 * Initialize built-in templates
	 */
	private function init_built_in_templates() {
		$settings = self::built_in_settings();

		$this->built_in_templates = array(
			'strict_protection' => array(
				'name'        => __( 'Strict Protection', 'zero-spam' ),
				'description' => __( 'Maximum security for high-risk sites. Every protection on and everything logged.', 'zero-spam' ),
				'settings'    => $settings['strict_protection'],
			),
			'balanced'          => array(
				'name'        => __( 'Balanced Protection', 'zero-spam' ),
				'description' => __( 'Recommended for most sites. A good balance of security and usability.', 'zero-spam' ),
				'settings'    => $settings['balanced'],
			),
			'relaxed'           => array(
				'name'        => __( 'Relaxed Protection', 'zero-spam' ),
				'description' => __( 'Minimal protection for low-risk sites or development environments.', 'zero-spam' ),
				'settings'    => $settings['relaxed'],
			),
		);
	}

	/**
	 * Get all templates (built-in + custom)
	 *
	 * @return array All templates.
	 */
	public function get_all_templates() {
		$templates = array();

		// Add built-in templates.
		foreach ( $this->built_in_templates as $slug => $template ) {
			$templates[ $slug ] = array_merge(
				$template,
				array(
					'slug' => $slug,
					'type' => 'built_in',
				)
			);
		}

		// Add custom templates from database.
		$custom_templates = $this->get_custom_templates();
		foreach ( $custom_templates as $template ) {
			$settings = json_decode( (string) $template['settings'], true );

			$templates[ $template['template_slug'] ] = array(
				'slug'        => $template['template_slug'],
				'name'        => $template['template_name'],
				'description' => $template['description'],
				'settings'    => is_array( $settings ) ? $settings : array(),
				'type'        => 'custom',
				'created_by'  => $template['created_by'],
				'created_at'  => $template['created_at'],
				'updated_at'  => $template['updated_at'] ?? $template['created_at'],
			);
		}

		return $templates;
	}

	/**
	 * Custom templates from the database (newest first, at most 200).
	 *
	 * @return array Custom templates.
	 */
	private function get_custom_templates() {
		global $wpdb;

		if ( ! is_multisite() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table, bounded.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d', $wpdb->base_prefix . DB::$tables['network_templates'], 200 ), ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get a specific template
	 *
	 * @param string $slug Template slug.
	 * @return array|null Template data or null.
	 */
	public function get_template( $slug ) {
		$templates = $this->get_all_templates();
		return $templates[ $slug ] ?? null;
	}

	/**
	 * Checks the settings of a template: every key must be a setting a
	 * network can lock (not secret, not help text), with a valid value; a
	 * key the registry does not know (an integration whose plugin is not
	 * active on this site) is kept when a built-in template or `$keep` has it.
	 *
	 * @since 6.0.0
	 *
	 * @param mixed    $settings Values by key.
	 * @param string[] $keep     Unknown keys to keep as they are.
	 * @return array{valid: array<string, mixed>, errors: array<string, string>}
	 */
	public static function check_settings( $settings, array $keep = array() ): array {
		$result = array(
			'valid'  => array(),
			'errors' => array(),
		);

		if ( ! is_array( $settings ) ) {
			$result['errors']['settings'] = __( 'Must be an object of settings.', 'zero-spam' );

			return $result;
		}

		$known_unknown = $keep;
		foreach ( self::built_in_settings() as $values ) {
			$known_unknown = array_merge( $known_unknown, array_keys( $values ) );
		}

		$to_validate = array();
		foreach ( $settings as $key => $value ) {
			$key   = (string) $key;
			$entry = Settings_Schema::setting( $key );

			if ( null === $entry ) {
				if ( in_array( $key, $known_unknown, true ) && ( is_scalar( $value ) || null === $value ) ) {
					$result['valid'][ $key ] = $value;
				} else {
					/* translators: %s: setting key */
					$result['errors'][ $key ] = sprintf( __( '"%s" is not a Zero Spam setting.', 'zero-spam' ), $key );
				}
				continue;
			}

			if ( $entry['secret'] || ! $entry['lockable'] ) {
				$result['errors'][ $key ] = __( 'License and API keys, webhook addresses and help text cannot be part of a template.', 'zero-spam' );
				continue;
			}

			$to_validate[ $key ] = $value;
		}

		if ( $to_validate ) {
			$checked          = ( new Rest\Settings_Controller() )->validate_values( $to_validate );
			$result['valid']  = array_merge( $result['valid'], $checked['valid'] );
			$result['errors'] = array_merge( $result['errors'], $checked['errors'] );
		}

		return $result;
	}

	/**
	 * Create a custom template
	 *
	 * @param string $name        Template name.
	 * @param string $slug        Template slug.
	 * @param array  $settings    Settings array.
	 * @param string $description Description.
	 * @return int|false Template ID or false on failure.
	 */
	public function create_template( $name, $slug, $settings, $description = '' ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Check if slug already exists.
		if ( $this->get_template( $slug ) ) {
			return false;
		}

		$data = array(
			'template_name' => sanitize_text_field( $name ),
			'template_slug' => sanitize_title( $slug ),
			'template_type' => 'custom',
			'settings'      => wp_json_encode( $settings ),
			'description'   => sanitize_textarea_field( $description ),
			'created_by'    => get_current_user_id(),
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			$data
		);

		if ( $result ) {
			$this->audit(
				sprintf(
					/* translators: %s: template name */
					__( 'Created the template "%s"', 'zero-spam' ),
					$data['template_name']
				)
			);
		}

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a custom template
	 *
	 * @param string $slug        Template slug.
	 * @param array  $updates     Updates array (name, settings, description).
	 * @return bool Success.
	 */
	public function update_template( $slug, $updates ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Check if template exists and is custom.
		$template = $this->get_template( $slug );
		if ( ! $template || 'custom' !== $template['type'] ) {
			return false;
		}

		$data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $updates['name'] ) ) {
			$data['template_name'] = sanitize_text_field( $updates['name'] );
		}

		if ( isset( $updates['settings'] ) ) {
			$data['settings'] = wp_json_encode( $updates['settings'] );
		}

		if ( isset( $updates['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $updates['description'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = false !== $wpdb->update(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			$data,
			array( 'template_slug' => $slug )
		);

		if ( $result ) {
			$this->audit(
				sprintf(
					/* translators: %s: template name */
					__( 'Changed the template "%s"', 'zero-spam' ),
					$data['template_name'] ?? $template['name']
				)
			);
		}

		return $result;
	}

	/**
	 * Delete a custom template
	 *
	 * @param string $slug Template slug.
	 * @return bool Success.
	 */
	public function delete_template( $slug ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Check if template exists and is custom.
		$template = $this->get_template( $slug );
		if ( ! $template || 'custom' !== $template['type'] ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = (bool) $wpdb->delete(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			array( 'template_slug' => $slug )
		);

		if ( $result ) {
			$this->audit(
				sprintf(
					/* translators: %s: template name */
					__( 'Deleted the template "%s"', 'zero-spam' ),
					$template['name']
				)
			);
		}

		return $result;
	}

	/**
	 * Applies a template to the network values: one write of the network
	 * settings (each key keeps its lock unless `$lock_all`), one audit entry.
	 *
	 * @param string $slug     Template slug.
	 * @param bool   $lock_all Lock all settings after applying.
	 * @return bool Success.
	 */
	public function apply_to_network( $slug, $lock_all = false ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		$template = $this->get_template( $slug );
		if ( ! $template ) {
			return false;
		}

		$store = Settings_Schema::normalize_network_store( get_site_option( Network_Settings::META_KEY, array() ) );
		$now   = current_time( 'mysql' );

		foreach ( (array) $template['settings'] as $key => $value ) {
			$entry = isset( $store['settings'][ $key ] ) && is_array( $store['settings'][ $key ] ) ? $store['settings'][ $key ] : array();

			$store['settings'][ $key ] = array_merge(
				$entry,
				array(
					'value'      => $value,
					'locked'     => $lock_all || ! empty( $entry['locked'] ),
					'updated_by' => get_current_user_id(),
					'updated_at' => $now,
				)
			);
		}

		update_site_option( Network_Settings::META_KEY, $store );
		delete_site_transient( Network_Settings::CACHE_KEY );
		Settings_Schema::reset_cache();

		// Log audit.
		$this->log_template_application( $slug, 'network', 0 );

		return true;
	}

	/**
	 * Applies a template to sites: on each, every setting the site has not
	 * changed itself (all of them with `$force`) is stored with the
	 * template's value. Settings the network has locked are never written.
	 *
	 * @param string $slug     Template slug.
	 * @param array  $site_ids Site IDs.
	 * @param bool   $force    Force overwrite existing settings.
	 * @return array Result with counts.
	 */
	public function apply_to_sites( $slug, $site_ids, $force = false ) {
		if ( ! is_multisite() ) {
			return array(
				'success' => false,
				'message' => 'Not a multisite installation',
			);
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return array(
				'success' => false,
				'message' => 'Insufficient permissions',
			);
		}

		$template = $this->get_template( $slug );
		if ( ! $template ) {
			return array(
				'success' => false,
				'message' => 'Template not found',
			);
		}

		$updated_count = 0;
		$skipped_count = 0;
		$updated_sites = array();
		$locked        = array();

		foreach ( Network_Report::network_values() as $key => $config ) {
			if ( ! empty( $config['locked'] ) ) {
				$locked[] = (string) $key;
			}
		}

		// Modules of the template's keys, from the registry built once (main site), and the built-in map for inactive integrations.
		$modules = array();
		foreach ( array_keys( (array) $template['settings'] ) as $key ) {
			$entry = Settings_Schema::setting( (string) $key );
			if ( null !== $entry ) {
				$modules[ $key ] = (string) $entry['module'];
			}
		}

		foreach ( array_map( 'intval', (array) $site_ids ) as $site_id ) {
			if ( ! get_site( $site_id ) ) {
				++$skipped_count;
				continue;
			}

			switch_to_blog( $site_id );

			$by_module = array();

			foreach ( $template['settings'] as $key => $value ) {
				$module = $modules[ $key ] ?? null;

				if ( ! $module || in_array( (string) $key, $locked, true ) ) {
					continue;
				}

				if ( ! isset( $by_module[ $module ] ) ) {
					$stored               = get_option( "zero-spam-{$module}", array() );
					$by_module[ $module ] = array(
						'values'  => is_array( $stored ) ? $stored : array(),
						'changed' => false,
					);
				}

				if ( $force || ! array_key_exists( $key, $by_module[ $module ]['values'] ) ) {
					$by_module[ $module ]['values'][ $key ] = $value;
					$by_module[ $module ]['changed']        = true;
				}
			}

			$site_updated = false;
			foreach ( $by_module as $module => $option ) {
				if ( $option['changed'] ) {
					update_option( "zero-spam-{$module}", $option['values'] );
					$site_updated = true;
				}
			}

			if ( $site_updated ) {
				++$updated_count;
				$updated_sites[] = $site_id;
			} else {
				++$skipped_count;
			}

			restore_current_blog();
		}

		// One audit entry for the sites the template changed.
		$this->log_template_application( $slug, 'sites', 0, $updated_sites );

		return array(
			'success'       => true,
			'updated_count' => $updated_count,
			'skipped_count' => $skipped_count,
			'updated_sites' => $updated_sites,
			'total_sites'   => count( $site_ids ),
		);
	}

	/**
	 * Save current network settings as a template
	 *
	 * Secrets and keys that are not settings are left out (since 6.0.0).
	 *
	 * @param string $name        Template name.
	 * @param string $slug        Template slug.
	 * @param string $description Description.
	 * @return int|false Template ID or false.
	 */
	public function save_current_as_template( $name, $slug, $description = '' ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$settings_manager = new Network_Settings_Manager();
		$current_settings = $settings_manager->export_settings( 'array' );

		if ( ! $current_settings ) {
			return false;
		}

		// Extract just the values (remove metadata like locked, updated_by, etc.).
		$clean_settings = array();
		foreach ( $current_settings as $key => $config ) {
			$clean_settings[ $key ] = is_array( $config ) && array_key_exists( 'value', $config ) ? $config['value'] : $config;
		}

		$checked = self::check_settings( $clean_settings );

		return $this->create_template( $name, $slug, $checked['valid'], $description );
	}

	/**
	 * Records a template being applied.
	 *
	 * @param string $slug     Template slug.
	 * @param string $scope    `network` or `sites` (`site` before 6.0.0).
	 * @param int    $site_id  Site ID (if one site).
	 * @param int[]  $affected Sites the template changed.
	 */
	private function log_template_application( $slug, $scope, $site_id = 0, $affected = array() ) {
		$template = $this->get_template( $slug );
		$name     = $template ? (string) $template['name'] : (string) $slug;

		if ( 'network' === $scope ) {
			/* translators: %s: template name */
			$text = sprintf( __( '"%s" applied to the network defaults', 'zero-spam' ), $name );
		} else {
			$text = sprintf(
				/* translators: 1: template name, 2: number of sites */
				_n( '"%1$s" applied to %2$s site', '"%1$s" applied to %2$s sites', count( $affected ), 'zero-spam' ),
				$name,
				number_format_i18n( count( $affected ) )
			);
		}

		( new Network_Settings_Manager() )->record( 'template', null, $slug, $text, $affected, (int) $site_id );
	}

	/**
	 * Records a change to a template.
	 *
	 * @param string $text What happened.
	 */
	private function audit( string $text ): void {
		( new Network_Settings_Manager() )->record( 'template', null, null, $text );
	}

	/**
	 * Get template usage statistics
	 *
	 * @param string $slug Template slug.
	 * @return array Usage stats.
	 */
	public function get_template_usage( $slug ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return array( 'count' => 0 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE action_type = 'template' AND old_value = %s",
				$wpdb->base_prefix . DB::$tables['network_audit'],
				$slug
			)
		);

		return array(
			'count' => (int) $count,
		);
	}
}
