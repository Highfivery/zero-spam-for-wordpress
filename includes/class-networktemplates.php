<?php
/**
 * Network Templates - Template management system
 *
 * Manages settings templates (Strict Protection, Balanced, Relaxed + custom).
 * Allows quick application of pre-configured settings across sites.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Templates class
 */
class Network_Templates {

	/**
	 * Built-in templates
	 *
	 * @var array
	 */
	private $built_in_templates = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_built_in_templates();
	}

	/**
	 * Initialize built-in templates
	 */
	private function init_built_in_templates() {
		$this->built_in_templates = array(
			'strict_protection' => array(
				'name'        => __( 'Strict Protection', 'zero-spam' ),
				'description' => __( 'Maximum security settings for high-risk sites. All protection features enabled.', 'zero-spam' ),
				'settings'    => array(
					'zerospam'                       => 'enabled',
					'zerospam_confidence_min'        => 30,
					'comments_enabled'               => 'enabled',
					'comments_log_flagged_attempts'  => 'enabled',
					'registrations_enabled'          => 'enabled',
					'registrations_log_flagged_attempts' => 'enabled',
					'cf7_enabled'                    => 'enabled',
					'cf7_log_flagged_attempts'       => 'enabled',
					'wpforms_enabled'                => 'enabled',
					'wpforms_log_flagged_attempts'   => 'enabled',
					'gravityforms_enabled'           => 'enabled',
					'gravityforms_log_flagged_attempts' => 'enabled',
					'fluentform_enabled'             => 'enabled',
					'fluentform_log_flagged_attempts' => 'enabled',
					'woocommerce_enabled'            => 'enabled',
					'woocommerce_log_flagged_attempts' => 'enabled',
					'api_monitoring_enabled'         => 'enabled',
					'api_daily_limit'                => 500,
					'api_hourly_limit'               => 50,
					'api_anomaly_detection'          => 'enabled',
				),
			),
			'balanced'          => array(
				'name'        => __( 'Balanced Protection', 'zero-spam' ),
				'description' => __( 'Recommended settings for most sites. Good balance of security and usability.', 'zero-spam' ),
				'settings'    => array(
					'zerospam'                       => 'enabled',
					'zerospam_confidence_min'        => 50,
					'comments_enabled'               => 'enabled',
					'comments_log_flagged_attempts'  => 'enabled',
					'registrations_enabled'          => 'enabled',
					'registrations_log_flagged_attempts' => 'disabled',
					'cf7_enabled'                    => 'enabled',
					'cf7_log_flagged_attempts'       => 'disabled',
					'wpforms_enabled'                => 'enabled',
					'wpforms_log_flagged_attempts'   => 'disabled',
					'gravityforms_enabled'           => 'enabled',
					'gravityforms_log_flagged_attempts' => 'disabled',
					'fluentform_enabled'             => 'enabled',
					'fluentform_log_flagged_attempts' => 'disabled',
					'woocommerce_enabled'            => 'enabled',
					'woocommerce_log_flagged_attempts' => 'disabled',
					'api_monitoring_enabled'         => 'enabled',
					'api_daily_limit'                => 1000,
					'api_hourly_limit'               => 100,
					'api_anomaly_detection'          => 'enabled',
				),
			),
			'relaxed'           => array(
				'name'        => __( 'Relaxed Protection', 'zero-spam' ),
				'description' => __( 'Minimal protection for low-risk sites or development environments.', 'zero-spam' ),
				'settings'    => array(
					'zerospam'                       => 'enabled',
					'zerospam_confidence_min'        => 70,
					'comments_enabled'               => 'enabled',
					'comments_log_flagged_attempts'  => 'disabled',
					'registrations_enabled'          => 'disabled',
					'registrations_log_flagged_attempts' => 'disabled',
					'cf7_enabled'                    => 'enabled',
					'cf7_log_flagged_attempts'       => 'disabled',
					'wpforms_enabled'                => 'disabled',
					'wpforms_log_flagged_attempts'   => 'disabled',
					'gravityforms_enabled'           => 'disabled',
					'gravityforms_log_flagged_attempts' => 'disabled',
					'fluentform_enabled'             => 'disabled',
					'fluentform_log_flagged_attempts' => 'disabled',
					'woocommerce_enabled'            => 'disabled',
					'woocommerce_log_flagged_attempts' => 'disabled',
					'api_monitoring_enabled'         => 'disabled',
					'api_daily_limit'                => 5000,
					'api_hourly_limit'               => 500,
					'api_anomaly_detection'          => 'disabled',
				),
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
			$templates[ $template['template_slug'] ] = array(
				'slug'        => $template['template_slug'],
				'name'        => $template['template_name'],
				'description' => $template['description'],
				'settings'    => json_decode( $template['settings'], true ),
				'type'        => 'custom',
				'created_by'  => $template['created_by'],
				'created_at'  => $template['created_at'],
			);
		}

		return $templates;
	}

	/**
	 * Get custom templates from database
	 *
	 * @return array Custom templates.
	 */
	private function get_custom_templates() {
		global $wpdb;

		if ( ! is_multisite() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT * FROM {$wpdb->base_prefix}" . DB::$tables['network_templates'] . ' ORDER BY created_at DESC',
			ARRAY_A
		);

		return $results ?: array();
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
		return $wpdb->update(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			$data,
			array( 'template_slug' => $slug )
		);
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
		return $wpdb->delete(
			$wpdb->base_prefix . DB::$tables['network_templates'],
			array( 'template_slug' => $slug )
		);
	}

	/**
	 * Apply a template to network settings
	 *
	 * @param string $slug Template slug.
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

		$settings_manager = new Network_Settings_Manager();

		// Apply each setting.
		foreach ( $template['settings'] as $key => $value ) {
			$settings_manager->set_setting( $key, $value, $lock_all );
		}

		// Log audit.
		$this->log_template_application( $slug, 'network', 0 );

		return true;
	}

	/**
	 * Apply a template to specific sites
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

		// Get all plugin settings to know which module each setting belongs to.
		$all_plugin_settings = \ZeroSpam\Core\Settings::get_settings();

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			$site_updated = false;

			foreach ( $template['settings'] as $key => $value ) {
				// Get the module for this setting.
				$module = $all_plugin_settings[ $key ]['module'] ?? null;
				
				if ( $module ) {
					// Get current module settings.
					$module_settings = get_option( "zero-spam-{$module}", array() );
					
					// Apply if forced or setting doesn't exist.
					if ( $force || ! isset( $module_settings[ $key ] ) ) {
						$module_settings[ $key ] = $value;
						
						// Save back to the module-specific option.
						update_option( "zero-spam-{$module}", $module_settings );
						$site_updated = true;
					}
				}
			}

			if ( $site_updated ) {
				$updated_count++;
			} else {
				$skipped_count++;
			}

			restore_current_blog();
		}

		// Log audit.
		foreach ( $site_ids as $site_id ) {
			$this->log_template_application( $slug, 'site', $site_id );
		}

		return array(
			'success'       => true,
			'updated_count' => $updated_count,
			'skipped_count' => $skipped_count,
			'total_sites'   => count( $site_ids ),
		);
	}

	/**
	 * Save current network settings as a template
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
			$clean_settings[ $key ] = $config['value'] ?? $config;
		}

		return $this->create_template( $name, $slug, $clean_settings, $description );
	}

	/**
	 * Log template application
	 *
	 * @param string $slug    Template slug.
	 * @param string $scope   Scope: 'network' or 'site'.
	 * @param int    $site_id Site ID (if scope is site).
	 */
	private function log_template_application( $slug, $scope, $site_id = 0 ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return;
		}

		$user = wp_get_current_user();

		$data = array(
			'site_id'        => $site_id,
			'action_type'    => 'template',
			'setting_key'    => null,
			'old_value'      => null,
			'new_value'      => "Template '{$slug}' applied to {$scope}",
			'affected_sites' => null,
			'user_id'        => get_current_user_id(),
			'user_login'     => $user->user_login,
			'ip_address'     => \ZeroSpam\Core\User::get_ip(),
			'date_created'   => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->base_prefix . DB::$tables['network_audit'],
			$data
		);
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
				"SELECT COUNT(*) FROM {$wpdb->base_prefix}" . DB::$tables['network_audit'] . " 
				WHERE action_type = 'template' AND new_value LIKE %s",
				'%' . $wpdb->esc_like( $slug ) . '%'
			)
		);

		return array(
			'count' => (int) $count,
		);
	}
}
