<?php
/**
 * Network Settings Manager - CRUD operations
 *
 * Handles creating, reading, updating, and deleting network settings.
 * Manages locks, bulk operations, and audit logging.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Settings Manager class
 */
class Network_Settings_Manager {

	/**
	 * Network Settings instance
	 *
	 * @var Network_Settings
	 */
	private $network_settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->network_settings = new Network_Settings();
	}

	/**
	 * Set a network setting
	 *
	 * @param string $setting_key Setting key.
	 * @param mixed  $value       Setting value.
	 * @param bool   $locked      Whether to lock the setting.
	 * @return bool Success.
	 */
	public function set_setting( $setting_key, $value, $locked = false ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Get current settings.
		$settings = $this->network_settings->get_network_settings();

		// Get old value for audit.
		$old_value = $settings['settings'][ $setting_key ]['value'] ?? null;

		// Update setting.
		$settings['settings'][ $setting_key ] = array(
			'value'      => $value,
			'locked'     => $locked,
			'updated_by' => get_current_user_id(),
			'updated_at' => current_time( 'mysql' ),
		);

		// Save.
		$result = update_site_option( Network_Settings::META_KEY, $settings );

		// Log audit.
		if ( $result ) {
			$this->log_audit(
				0, // Network level.
				'set',
				$setting_key,
				$old_value,
				$value
			);

			// Clear cache.
			$this->network_settings->clear_cache();
		}

		return $result;
	}

	/**
	 * Lock a setting
	 *
	 * @param string $setting_key Setting key.
	 * @return bool Success.
	 */
	public function lock_setting( $setting_key ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Get current settings.
		$settings = $this->network_settings->get_network_settings();

		// Ensure setting exists.
		if ( ! isset( $settings['settings'][ $setting_key ] ) ) {
			return false;
		}

		// Lock it.
		$settings['settings'][ $setting_key ]['locked']     = true;
		$settings['settings'][ $setting_key ]['updated_by'] = get_current_user_id();
		$settings['settings'][ $setting_key ]['updated_at'] = current_time( 'mysql' );

		// Save.
		$result = update_site_option( Network_Settings::META_KEY, $settings );

		// Log audit.
		if ( $result ) {
			$this->log_audit( 0, 'lock', $setting_key, false, true );
			$this->network_settings->clear_cache();
		}

		return $result;
	}

	/**
	 * Unlock a setting
	 *
	 * @param string $setting_key Setting key.
	 * @return bool Success.
	 */
	public function unlock_setting( $setting_key ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Get current settings.
		$settings = $this->network_settings->get_network_settings();

		// Ensure setting exists.
		if ( ! isset( $settings['settings'][ $setting_key ] ) ) {
			return false;
		}

		// Unlock it.
		$settings['settings'][ $setting_key ]['locked']     = false;
		$settings['settings'][ $setting_key ]['updated_by'] = get_current_user_id();
		$settings['settings'][ $setting_key ]['updated_at'] = current_time( 'mysql' );

		// Save.
		$result = update_site_option( Network_Settings::META_KEY, $settings );

		// Log audit.
		if ( $result ) {
			$this->log_audit( 0, 'unlock', $setting_key, true, false );
			$this->network_settings->clear_cache();
		}

		return $result;
	}

	/**
	 * Apply network settings to all sites
	 *
	 * @param bool   $force     Force overwrite site overrides.
	 * @param array  $site_ids  Specific site IDs (empty = all sites).
	 * @param string $mode      Mode: 'locked_only', 'defaults_only', 'all'.
	 * @return array Result with counts.
	 */
	public function apply_to_sites( $force = false, $site_ids = array(), $mode = 'all' ) {
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

		// Get network settings.
		$network_settings = $this->network_settings->get_network_settings();

		if ( empty( $network_settings['settings'] ) ) {
			return array(
				'success' => false,
				'message' => 'No network settings to apply',
			);
		}

		// Get sites to process.
		if ( empty( $site_ids ) ) {
			$sites = get_sites( array( 'number' => 0 ) );
			$site_ids = wp_list_pluck( $sites, 'blog_id' );
		}

		$updated_count = 0;
		$skipped_count = 0;
		$updated_sites = array();

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			$site_settings = get_option( 'zero-spam-settings', array() );
			$site_updated  = false;

			foreach ( $network_settings['settings'] as $key => $config ) {
				$should_apply = false;

				// Determine if we should apply this setting.
				switch ( $mode ) {
					case 'locked_only':
						$should_apply = ! empty( $config['locked'] );
						break;

					case 'defaults_only':
						$should_apply = ! isset( $site_settings[ $key ] );
						break;

					case 'all':
						$should_apply = true;
						break;
				}

				// Apply if forced or setting doesn't exist locally.
				if ( $should_apply && ( $force || ! isset( $site_settings[ $key ] ) || ! empty( $config['locked'] ) ) ) {
					$site_settings[ $key ] = $config['value'];
					$site_updated = true;
				}
			}

			if ( $site_updated ) {
				update_option( 'zero-spam-settings', $site_settings );
				$updated_count++;
				$updated_sites[] = $site_id;
			} else {
				$skipped_count++;
			}

			restore_current_blog();
		}

		// Log audit.
		$this->log_audit(
			0,
			'apply',
			null,
			null,
			count( $updated_sites ) . ' sites updated',
			$updated_sites
		);

		return array(
			'success'       => true,
			'updated_count' => $updated_count,
			'skipped_count' => $skipped_count,
			'updated_sites' => $updated_sites,
			'total_sites'   => count( $site_ids ),
		);
	}

	/**
	 * Reset a site's settings to network defaults
	 *
	 * @param int $site_id Site ID.
	 * @return bool Success.
	 */
	public function reset_site( $site_id ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		switch_to_blog( $site_id );

		// Get current site settings.
		$site_settings = get_option( 'zero-spam-settings', array() );

		// Remove all settings (will fall back to network defaults).
		$result = delete_option( 'zero-spam-settings' );

		restore_current_blog();

		// Log audit.
		if ( $result ) {
			$this->log_audit( $site_id, 'reset', null, count( $site_settings ) . ' settings', 'network defaults' );
		}

		return $result;
	}

	/**
	 * Get all network settings with status
	 *
	 * @return array All settings with application status.
	 */
	public function get_all_with_status() {
		if ( ! is_multisite() ) {
			return array();
		}

		$network_settings = $this->network_settings->get_network_settings();
		$settings_with_status = array();

		foreach ( $network_settings['settings'] as $key => $config ) {
			$status = $this->network_settings->get_application_status( $key );

			$settings_with_status[ $key ] = array(
				'value'         => $config['value'],
				'locked'        => ! empty( $config['locked'] ),
				'updated_by'    => $config['updated_by'] ?? null,
				'updated_at'    => $config['updated_at'] ?? null,
				'total_sites'   => $status['total_sites'],
				'using_default' => $status['using_default'],
				'overridden'    => $status['overridden'],
			);
		}

		return $settings_with_status;
	}

	/**
	 * Import network settings
	 *
	 * @param array  $settings_data Settings data to import.
	 * @param string $merge_mode    Mode: 'replace', 'merge', 'add_only'.
	 * @return array Result.
	 */
	public function import_settings( $settings_data, $merge_mode = 'merge' ) {
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

		// Validate data.
		if ( ! is_array( $settings_data ) || empty( $settings_data ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid settings data',
			);
		}

		// Get current settings.
		$current_settings = $this->network_settings->get_network_settings();

		$imported_count = 0;
		$skipped_count  = 0;

		foreach ( $settings_data as $key => $value ) {
			$should_import = false;

			switch ( $merge_mode ) {
				case 'replace':
					$should_import = true;
					break;

				case 'merge':
					$should_import = true;
					break;

				case 'add_only':
					$should_import = ! isset( $current_settings['settings'][ $key ] );
					break;
			}

			if ( $should_import ) {
				// Preserve lock status if merging.
				$locked = ( 'merge' === $merge_mode && isset( $current_settings['settings'][ $key ]['locked'] ) )
					? $current_settings['settings'][ $key ]['locked']
					: ( isset( $value['locked'] ) ? $value['locked'] : false );

				$this->set_setting( $key, $value['value'] ?? $value, $locked );
				$imported_count++;
			} else {
				$skipped_count++;
			}
		}

		// Log audit.
		$this->log_audit( 0, 'import', null, null, "{$imported_count} settings imported" );

		return array(
			'success'        => true,
			'imported_count' => $imported_count,
			'skipped_count'  => $skipped_count,
			'total_settings' => count( $settings_data ),
		);
	}

	/**
	 * Export network settings
	 *
	 * @param string $format Format: 'json', 'array'.
	 * @return mixed Exported data.
	 */
	public function export_settings( $format = 'json' ) {
		if ( ! is_multisite() ) {
			return null;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return null;
		}

		$settings = $this->network_settings->get_network_settings();

		if ( 'json' === $format ) {
			return wp_json_encode( $settings['settings'], JSON_PRETTY_PRINT );
		}

		return $settings['settings'];
	}

	/**
	 * Log audit entry
	 *
	 * @param int    $site_id        Site ID (0 for network level).
	 * @param string $action_type    Action type.
	 * @param string $setting_key    Setting key.
	 * @param mixed  $old_value      Old value.
	 * @param mixed  $new_value      New value.
	 * @param array  $affected_sites Affected site IDs.
	 * @return bool Success.
	 */
	private function log_audit( $site_id, $action_type, $setting_key = null, $old_value = null, $new_value = null, $affected_sites = array() ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return false;
		}

		$user = wp_get_current_user();

		$data = array(
			'site_id'        => $site_id,
			'action_type'    => $action_type,
			'setting_key'    => $setting_key,
			'old_value'      => is_array( $old_value ) || is_object( $old_value ) ? wp_json_encode( $old_value ) : $old_value,
			'new_value'      => is_array( $new_value ) || is_object( $new_value ) ? wp_json_encode( $new_value ) : $new_value,
			'affected_sites' => ! empty( $affected_sites ) ? wp_json_encode( $affected_sites ) : null,
			'user_id'        => get_current_user_id(),
			'user_login'     => $user->user_login,
			'ip_address'     => \ZeroSpam\Core\User::get_ip(),
			'date_created'   => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->insert(
			$wpdb->base_prefix . DB::$tables['network_audit'],
			$data
		);
	}

	/**
	 * Get audit log
	 *
	 * @param array $args Query args.
	 * @return array Audit entries.
	 */
	public function get_audit_log( $args = array() ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return array();
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return array();
		}

		$defaults = array(
			'limit'       => 50,
			'offset'      => 0,
			'site_id'     => null,
			'action_type' => null,
			'setting_key' => null,
			'user_id'     => null,
			'orderby'     => 'date_created',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query.
		$table = $wpdb->base_prefix . DB::$tables['network_audit'];
		$where = array( '1=1' );

		if ( null !== $args['site_id'] ) {
			$where[] = $wpdb->prepare( 'site_id = %d', $args['site_id'] );
		}

		if ( $args['action_type'] ) {
			$where[] = $wpdb->prepare( 'action_type = %s', $args['action_type'] );
		}

		if ( $args['setting_key'] ) {
			$where[] = $wpdb->prepare( 'setting_key = %s', $args['setting_key'] );
		}

		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		$where_clause = implode( ' AND ', $where );
		$orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		return $results;
	}
}
