<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Settings_Manager to this file name.
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

		// If setting doesn't exist, create it with default value from plugin settings.
		// A secret is never copied from the main site to every site (since 6.0.0): it is locked empty.
		if ( ! isset( $settings['settings'][ $setting_key ] ) ) {
			$plugin_settings = \ZeroSpam\Core\Settings::get_settings();
			$default_value   = \ZeroSpam\Core\Settings_Schema::is_secret( (string) $setting_key ) ? '' : ( $plugin_settings[ $setting_key ]['value'] ?? '' );

			$settings['settings'][ $setting_key ] = array(
				'value'      => $default_value,
				'locked'     => false,
				'updated_by' => get_current_user_id(),
				'updated_at' => current_time( 'mysql' ),
			);
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

		// Nothing to unlock: no network entry is created (since 6.0.0; before, unlocking a
		// setting the network did not set copied the main site's value to every site).
		if ( ! isset( $settings['settings'][ $setting_key ] ) ) {
			return true;
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
	 * @param string $mode      Mode: 'locked_only', 'defaults_only', 'all', or 'follow' (since 6.0.0: sites drop
	 *                          their own values of unlocked settings the network sets, so they follow it).
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
			$sites    = get_sites( array( 'number' => 0 ) );
			$site_ids = wp_list_pluck( $sites, 'blog_id' );
		}

		$updated_count = 0;
		$skipped_count = 0;
		$updated_sites = array();

		// Get all plugin settings to know which module each setting belongs to.
		$all_plugin_settings = \ZeroSpam\Core\Settings::get_settings();

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			$site_updated = false;
			$followed     = array();

			foreach ( $network_settings['settings'] as $key => $config ) {
				$should_apply = false;

				// Get the module for this setting.
				$module = $all_plugin_settings[ $key ]['module'] ?? null;
				if ( ! $module ) {
					continue; // Skip if we can't determine the module.
				}

				// Get current module settings (with this site's removals so far, in follow mode).
				$module_settings = $followed[ $module ] ?? get_option( "zero-spam-{$module}", array() );
				$module_settings = is_array( $module_settings ) ? $module_settings : array();
				$setting_exists  = array_key_exists( $key, $module_settings );

				// Determine if we should apply this setting.
				switch ( $mode ) {
					case 'follow':
						// The site goes back to the network value: its own value of an
						// unlocked setting the network sets is removed (never a locked
						// one, which comes back when the setting is unlocked).
						if ( empty( $config['locked'] ) && isset( $config['value'] ) && $setting_exists ) {
							unset( $module_settings[ $key ] );
							$followed[ $module ] = $module_settings;
						}
						$should_apply = false;
						break;

					case 'locked_only':
						// Only apply if locked.
						$should_apply = ! empty( $config['locked'] );
						break;

					case 'defaults_only':
						// Only apply if setting doesn't exist on the site.
						$should_apply = ! $setting_exists;
						break;

					case 'all':
					default:
						// Apply to all sites.
						$should_apply = true;
						break;
				}

				// Don't apply if we shouldn't apply based on mode, or if the setting
				// is not locked, we are not forcing and the site has an override.
				if ( ! $should_apply ) {
					continue;
				}

				if ( empty( $config['locked'] ) && ! $force && $setting_exists ) {
					continue; // Respect existing site override for unlocked settings.
				}

				// Apply the network setting.
				$module_settings[ $key ] = $config['value'];

				// Save back to the module-specific option.
				update_option( "zero-spam-{$module}", $module_settings );
				$site_updated = true;
			}

			foreach ( $followed as $module => $values ) {
				update_option( "zero-spam-{$module}", $values );
				$site_updated = true;
			}

			if ( $site_updated ) {
				++$updated_count;
				$updated_sites[] = $site_id;
			} else {
				++$skipped_count;
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

		// Get all plugin settings to know all modules.
		$all_plugin_settings = \ZeroSpam\Core\Settings::get_settings();
		$modules             = array();

		// Collect all unique modules.
		foreach ( $all_plugin_settings as $key => $config ) {
			if ( isset( $config['module'] ) && ! in_array( $config['module'], $modules, true ) ) {
				$modules[] = $config['module'];
			}
		}

		// Delete all module-specific options.
		$deleted_count = 0;
		foreach ( $modules as $module ) {
			if ( delete_option( "zero-spam-{$module}" ) ) {
				++$deleted_count;
			}
		}

		restore_current_blog();

		// Log audit.
		if ( $deleted_count > 0 ) {
			$this->log_audit( $site_id, 'reset', null, $deleted_count . ' module settings deleted', 'network defaults' );
		}

		return $deleted_count > 0;
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

		$network_settings     = $this->network_settings->get_network_settings();
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
				++$imported_count;
			} else {
				++$skipped_count;
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
	 * Records a change of one network setting in the network audit log, as the
	 * current user.
	 *
	 * For writes that do not go through set_setting(), such as the
	 * `zero-spam/v1/settings` route with `scope=network`. Mask secrets before
	 * passing them.
	 *
	 * @since 6.0.0
	 *
	 * @param string $setting_key Setting key.
	 * @param mixed  $old_value   Old value (null when there was none).
	 * @param mixed  $new_value   New value.
	 * @return bool Success.
	 */
	public function log_setting_change( string $setting_key, $old_value, $new_value ): bool {
		return (bool) $this->log_audit( 0, 'set', $setting_key, $old_value, $new_value );
	}

	/**
	 * Records an entry in the network audit log, as the current user.
	 *
	 * For network actions that do not go through this class (templates,
	 * network-wide blocks, migrations). Secret values are masked.
	 *
	 * @since 6.0.0
	 *
	 * @param string      $action_type    One of DB::AUDIT_ACTIONS.
	 * @param string|null $setting_key    Setting key, if one setting.
	 * @param mixed       $old_value      Old value, or a plain description.
	 * @param mixed       $new_value      New value, or a plain description.
	 * @param int[]       $affected_sites Sites affected.
	 * @param int         $site_id        Site (0 for the network).
	 * @return bool Success.
	 */
	public function record( string $action_type, ?string $setting_key, $old_value, $new_value, array $affected_sites = array(), int $site_id = 0 ): bool {
		if ( ! in_array( $action_type, DB::AUDIT_ACTIONS, true ) ) {
			return false;
		}

		return (bool) $this->log_audit( $site_id, $action_type, $setting_key, $old_value, $new_value, $affected_sites );
	}

	/**
	 * A value as stored in the audit log: a secret is masked (its last four
	 * characters at most), anything else as given.
	 *
	 * @since 6.0.0
	 *
	 * @param string|null $setting_key Setting key.
	 * @param mixed       $value       Value.
	 * @return mixed
	 */
	public static function audit_safe( ?string $setting_key, $value ) {
		if ( null === $setting_key || ! \ZeroSpam\Core\Settings_Schema::is_secret( $setting_key ) || is_array( $value ) ) {
			return $value;
		}

		$text = is_scalar( $value ) && ! is_bool( $value ) ? trim( (string) $value ) : '';

		return '' === $text ? '' : \ZeroSpam\Core\Settings_Schema::hint( $text );
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

		// Never a secret in the clear (a masked array from the REST route stays as it is).
		$old_value = self::audit_safe( $setting_key, $old_value );
		$new_value = self::audit_safe( $setting_key, $new_value );

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
	 * Filters (since 6.0.0 also `after` and `before`, days in network time,
	 * and a `site_id` of 0 for network-level entries): `site_id`,
	 * `action_type`, `setting_key`, `user_id`. Newest first unless `orderby`
	 * and `order` say otherwise.
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
			'limit'   => 50,
			'offset'  => 0,
			'orderby' => 'date_created',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table        = $wpdb->base_prefix . DB::$tables['network_audit'];
		$where_clause = $this->audit_where( $args );
		$orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		// An invalid order would leave "ORDER BY" empty.
		if ( ! $orderby ) {
			$orderby = 'date_created DESC';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause only joins prepare()d fragments; $orderby passed sanitize_sql_orderby().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY {$orderby}, audit_id DESC LIMIT %d OFFSET %d",
				$table,
				max( 1, (int) $args['limit'] ),
				max( 0, (int) $args['offset'] )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Number of audit entries matching the filters of get_audit_log().
	 *
	 * @since 6.0.0
	 *
	 * @param array $args Filters.
	 * @return int
	 */
	public function count_audit_log( array $args = array() ): int {
		global $wpdb;

		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			return 0;
		}

		$where_clause = $this->audit_where( $args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause only joins prepare()d fragments.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_clause}", $wpdb->base_prefix . DB::$tables['network_audit'] ) );
	}

	/**
	 * WHERE clause of the audit filters, made of prepared fragments.
	 *
	 * @param array $args Filters.
	 * @return string
	 */
	private function audit_where( array $args ): string {
		global $wpdb;

		$where = array( '1=1' );

		if ( isset( $args['site_id'] ) && '' !== $args['site_id'] ) {
			$where[] = $wpdb->prepare( 'site_id = %d', (int) $args['site_id'] );
		}

		if ( ! empty( $args['action_type'] ) ) {
			$where[] = $wpdb->prepare( 'action_type = %s', (string) $args['action_type'] );
		}

		if ( ! empty( $args['setting_key'] ) ) {
			$where[] = $wpdb->prepare( 'setting_key = %s', (string) $args['setting_key'] );
		}

		if ( isset( $args['user_id'] ) && '' !== $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'user_id = %d', (int) $args['user_id'] );
		}

		if ( ! empty( $args['after'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['after'] ) ) {
			$where[] = $wpdb->prepare( 'date_created >= %s', $args['after'] . ' 00:00:00' );
		}

		if ( ! empty( $args['before'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['before'] ) ) {
			$where[] = $wpdb->prepare( 'date_created <= %s', $args['before'] . ' 23:59:59' );
		}

		return implode( ' AND ', $where );
	}

	/**
	 * An audit entry as the REST API returns it: the stored columns, plus
	 * (since 6.0.0) `id`, `date` (ISO 8601), `user`, `action`, `action_label`,
	 * `key`, `key_title`, `site_name`, `old` and `new` (secrets masked) and
	 * `summary`, a plain-language sentence.
	 *
	 * @since 6.0.0
	 *
	 * @param array<string, mixed> $row Audit row.
	 * @return array<string, mixed>
	 */
	public static function describe_audit_entry( array $row ): array {
		$action = (string) ( $row['action_type'] ?? '' );
		$key    = isset( $row['setting_key'] ) && '' !== (string) $row['setting_key'] ? (string) $row['setting_key'] : null;
		$entry  = null !== $key ? \ZeroSpam\Core\Settings_Schema::setting( $key ) : null;
		$title  = null !== $entry && '' !== $entry['title'] ? $entry['title'] : (string) $key;
		$old    = self::audit_value_text( $key, $row['old_value'] ?? null );
		$new    = self::audit_value_text( $key, $row['new_value'] ?? null );
		$site   = (int) ( $row['site_id'] ?? 0 );
		$user   = (string) ( $row['user_login'] ?? '' );
		$sites  = isset( $row['affected_sites'] ) ? json_decode( (string) $row['affected_sites'], true ) : null;
		$count  = is_array( $sites ) ? count( $sites ) : 0;

		switch ( $action ) {
			case 'set':
				/* translators: 1: setting name, 2: old value, 3: new value */
				$summary = sprintf( __( 'Changed “%1$s” from %2$s to %3$s.', 'zero-spam' ), $title, $old, $new );
				break;
			case 'lock':
				/* translators: %s: setting name */
				$summary = sprintf( __( 'Locked “%s” on every site.', 'zero-spam' ), $title );
				break;
			case 'unlock':
				/* translators: %s: setting name */
				$summary = sprintf( __( 'Unlocked “%s”: sites can change it again.', 'zero-spam' ), $title );
				break;
			case 'apply':
				/* translators: %s: number of sites */
				$summary = sprintf( _n( 'Applied the network settings to %s site.', 'Applied the network settings to %s sites.', $count, 'zero-spam' ), number_format_i18n( $count ) );
				break;
			case 'reset':
				$summary = __( 'Reset a site to the network settings.', 'zero-spam' );
				break;
			case 'block':
			case 'unblock':
			case 'template':
			case 'import':
			case 'migrate':
			case 'bulk':
			default:
				$summary = '' !== $new && '—' !== $new ? $new : ucfirst( $action );
		}

		return array_merge(
			$row,
			array(
				'old_value'    => self::audit_safe( $key, $row['old_value'] ?? null ),
				'new_value'    => self::audit_safe( $key, $row['new_value'] ?? null ),
				'id'           => (int) ( $row['audit_id'] ?? 0 ),
				'date'         => isset( $row['date_created'] ) ? mysql_to_rfc3339( (string) $row['date_created'] ) : null,
				'user'         => '' !== $user ? $user : __( 'Zero Spam (automatic)', 'zero-spam' ),
				'action'       => $action,
				'action_label' => self::action_label( $action ),
				'key'          => $key,
				'key_title'    => null !== $key ? $title : null,
				'site_name'    => $site > 0 ? self::site_name( $site ) : null,
				'old'          => 'set' === $action ? $old : null,
				'new'          => 'set' === $action ? $new : null,
				'summary'      => $summary,
			)
		);
	}

	/**
	 * A short label for an audit action.
	 *
	 * @since 6.0.0
	 *
	 * @param string $action Action type.
	 */
	public static function action_label( string $action ): string {
		$labels = array(
			'set'      => __( 'Change', 'zero-spam' ),
			'lock'     => __( 'Lock', 'zero-spam' ),
			'unlock'   => __( 'Unlock', 'zero-spam' ),
			'apply'    => __( 'Apply', 'zero-spam' ),
			'bulk'     => __( 'Bulk change', 'zero-spam' ),
			'template' => __( 'Template', 'zero-spam' ),
			'import'   => __( 'Import', 'zero-spam' ),
			'reset'    => __( 'Reset', 'zero-spam' ),
			'block'    => __( 'Block', 'zero-spam' ),
			'unblock'  => __( 'Unblock', 'zero-spam' ),
			'migrate'  => __( 'Update', 'zero-spam' ),
		);

		return $labels[ $action ] ?? ucfirst( $action );
	}

	/**
	 * A stored audit value as text: secrets masked, empty as "(none)",
	 * switches as On and Off.
	 *
	 * @param string|null $key   Setting key.
	 * @param mixed       $value Stored value (text, JSON or null).
	 */
	private static function audit_value_text( ?string $key, $value ): string {
		$value = self::audit_safe( $key, $value );

		if ( is_string( $value ) && '' !== $value && ( '{' === $value[0] || '[' === $value[0] ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				if ( array_key_exists( 'set', $decoded ) ) {
					return ! empty( $decoded['set'] ) ? (string) ( $decoded['hint'] ?? "\u{2022}\u{2022}\u{2022}\u{2022}" ) : __( '(none)', 'zero-spam' );
				}
				$value = implode( ', ', array_map( 'strval', array_filter( $decoded, 'is_scalar' ) ) );
			}
		}

		if ( null === $value || '' === $value || false === $value ) {
			return __( '(none)', 'zero-spam' );
		}

		// "1" is On only for a switch; for a number it is the number.
		$entry  = null !== $key ? \ZeroSpam\Core\Settings_Schema::setting( $key ) : null;
		$switch = null !== $entry && 'checkbox' === $entry['type'];

		if ( 'enabled' === $value || true === $value || ( $switch && '1' === $value ) ) {
			return __( 'On', 'zero-spam' );
		}

		if ( 'disabled' === $value ) {
			return __( 'Off', 'zero-spam' );
		}

		$text = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );

		return mb_strlen( $text ) > 120 ? mb_substr( $text, 0, 117 ) . '…' : $text;
	}

	/**
	 * A site's name for the audit log.
	 *
	 * @param int $site_id Site.
	 */
	private static function site_name( int $site_id ): string {
		$details = get_blog_details( $site_id );

		return $details ? wp_specialchars_decode( (string) $details->blogname, ENT_QUOTES ) : sprintf(
			/* translators: %d: site ID */
			__( 'Site %d', 'zero-spam' ),
			$site_id
		);
	}
}
