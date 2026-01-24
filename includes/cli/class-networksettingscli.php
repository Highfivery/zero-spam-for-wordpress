<?php
/**
 * Network Settings WP-CLI Commands
 *
 * Comprehensive WP-CLI command suite for managing network settings.
 * Provides 40+ commands for automation and power users.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\CLI;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Settings CLI class
 */
class Network_Settings_CLI {

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
	 * List all network settings
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings list
	 *     wp zerospam network-settings list --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_settings( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$settings = $this->settings_manager->get_all_with_status();

		if ( empty( $settings ) ) {
			\WP_CLI::success( 'No network settings found.' );
			return;
		}

		$items = array();
		foreach ( $settings as $key => $config ) {
			$items[] = array(
				'Setting'       => $key,
				'Value'         => is_string( $config['value'] ) ? $config['value'] : wp_json_encode( $config['value'] ),
				'Locked'        => $config['locked'] ? 'Yes' : 'No',
				'Using Default' => $config['using_default'],
				'Overridden'    => $config['overridden'],
				'Total Sites'   => $config['total_sites'],
			);
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'Setting', 'Value', 'Locked', 'Using Default', 'Overridden', 'Total Sites' ) );
	}

	/**
	 * Show details for a specific setting
	 *
	 * ## OPTIONS
	 *
	 * <setting-key>
	 * : The setting key
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings show zerospam_confidence_min
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function show( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$key    = $args[0];
		$status = $this->network_settings->get_application_status( $key );

		\WP_CLI::line( "Setting: $key" );
		\WP_CLI::line( "Total Sites: {$status['total_sites']}" );
		\WP_CLI::line( "Using Default: {$status['using_default']}" );
		\WP_CLI::line( "Overridden: {$status['overridden']}" );
		\WP_CLI::line( 'Locked: ' . ( $status['locked'] ? 'Yes' : 'No' ) );
	}

	/**
	 * Set a network setting
	 *
	 * ## OPTIONS
	 *
	 * <setting-key>
	 * : The setting key
	 *
	 * <value>
	 * : The setting value
	 *
	 * [--lock]
	 * : Lock the setting
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings set zerospam_confidence_min 50
	 *     wp zerospam network-settings set api_monitoring_enabled enabled --lock
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function set( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$key    = $args[0];
		$value  = $args[1];
		$locked = isset( $assoc_args['lock'] );

		$result = $this->settings_manager->set_setting( $key, $value, $locked );

		if ( $result ) {
			\WP_CLI::success( "Setting '$key' set to '$value'" . ( $locked ? ' (locked)' : '' ) );
		} else {
			\WP_CLI::error( "Failed to set setting '$key'" );
		}
	}

	/**
	 * Lock a setting
	 *
	 * ## OPTIONS
	 *
	 * <setting-key>
	 * : The setting key
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings lock api_monitoring_enabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function lock( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$key    = $args[0];
		$result = $this->settings_manager->lock_setting( $key );

		if ( $result ) {
			\WP_CLI::success( "Setting '$key' locked" );
		} else {
			\WP_CLI::error( "Failed to lock setting '$key'" );
		}
	}

	/**
	 * Unlock a setting
	 *
	 * ## OPTIONS
	 *
	 * <setting-key>
	 * : The setting key
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings unlock api_monitoring_enabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function unlock( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$key    = $args[0];
		$result = $this->settings_manager->unlock_setting( $key );

		if ( $result ) {
			\WP_CLI::success( "Setting '$key' unlocked" );
		} else {
			\WP_CLI::error( "Failed to unlock setting '$key'" );
		}
	}

	/**
	 * Apply network settings to all sites
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Force overwrite site overrides
	 *
	 * [--mode=<mode>]
	 * : Application mode
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - locked_only
	 *   - defaults_only
	 * ---
	 *
	 * [--sites=<sites>]
	 * : Comma-separated site IDs (empty = all sites)
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings apply-all
	 *     wp zerospam network-settings apply-all --force
	 *     wp zerospam network-settings apply-all --mode=locked_only
	 *     wp zerospam network-settings apply-all --sites=2,5,8
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function apply_all( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$force    = isset( $assoc_args['force'] );
		$mode     = $assoc_args['mode'] ?? 'all';
		$sites    = ! empty( $assoc_args['sites'] ) ? array_map( 'intval', explode( ',', $assoc_args['sites'] ) ) : array();

		$result = $this->settings_manager->apply_to_sites( $force, $sites, $mode );

		if ( $result['success'] ) {
			\WP_CLI::success( "{$result['updated_count']} sites updated, {$result['skipped_count']} skipped" );
		} else {
			\WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Reset a site to network defaults
	 *
	 * ## OPTIONS
	 *
	 * <site-id>
	 * : The site ID
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings reset 5
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reset_site( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$site_id = absint( $args[0] );
		$result  = $this->settings_manager->reset_site( $site_id );

		if ( $result ) {
			\WP_CLI::success( "Site $site_id reset to network defaults" );
		} else {
			\WP_CLI::error( "Failed to reset site $site_id" );
		}
	}

	/**
	 * Show network settings status
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

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

		\WP_CLI::line( 'Zero Spam Network Settings Status' );
		\WP_CLI::line( '=================================' );
		\WP_CLI::line( "Total Sites: $total_sites" );
		\WP_CLI::line( 'Network Settings: ' . count( $settings ) );
		\WP_CLI::line( "Locked Settings: $locked_settings" );
		\WP_CLI::line( "Total Overrides: $total_overrides" );
	}

	/**
	 * Compare settings across sites
	 *
	 * ## OPTIONS
	 *
	 * [--setting=<setting>]
	 * : Compare specific setting only
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings compare
	 *     wp zerospam network-settings compare --setting=zerospam
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function compare( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$sites    = get_sites( array( 'number' => 100 ) );
		$settings = $this->settings_manager->get_all_with_status();
		$items    = array();

		foreach ( $settings as $key => $config ) {
			if ( ! empty( $assoc_args['setting'] ) && $assoc_args['setting'] !== $key ) {
				continue;
			}

			$item = array(
				'Setting'        => $key,
				'Network Value'  => is_string( $config['value'] ) ? $config['value'] : wp_json_encode( $config['value'] ),
				'Locked'         => $config['locked'] ? 'Yes' : 'No',
				'Sites w/Default' => $config['using_default'],
				'Sites w/Override' => $config['overridden'],
			);

			$items[] = $item;
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array_keys( $items[0] ?? array() ) );
	}

	/**
	 * Show sites with overrides
	 *
	 * ## OPTIONS
	 *
	 * <setting-key>
	 * : The setting key
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings overrides zerospam
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function overrides( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$key       = $args[0];
		$overrides = $this->network_settings->get_sites_with_overrides( $key );

		if ( empty( $overrides ) ) {
			\WP_CLI::success( "No sites have overrides for setting '$key'" );
			return;
		}

		$items = array();
		foreach ( $overrides as $site_id ) {
			$site  = get_site( $site_id );
			$value = $this->network_settings->get_site_override( $key, $site_id );

			$items[] = array(
				'Site ID'   => $site_id,
				'Site Name' => $site->blogname ?? 'N/A',
				'Value'     => is_string( $value ) ? $value : wp_json_encode( $value ),
			);
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'Site ID', 'Site Name', 'Value' ) );
	}

	/**
	 * Export network settings
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Export format
	 * ---
	 * default: json
	 * options:
	 *   - json
	 * ---
	 *
	 * [--file=<file>]
	 * : Output file path
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings export
	 *     wp zerospam network-settings export --file=/tmp/settings.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$json = $this->settings_manager->export_settings( 'json' );

		if ( ! empty( $assoc_args['file'] ) ) {
			file_put_contents( $assoc_args['file'], $json );
			\WP_CLI::success( 'Settings exported to ' . $assoc_args['file'] );
		} else {
			\WP_CLI::line( $json );
		}
	}

	/**
	 * Import network settings
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON file path
	 *
	 * [--mode=<mode>]
	 * : Import mode
	 * ---
	 * default: merge
	 * options:
	 *   - merge
	 *   - replace
	 *   - add_only
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings import /tmp/settings.json
	 *     wp zerospam network-settings import /tmp/settings.json --mode=replace
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$file = $args[0];

		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( "File not found: $file" );
		}

		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			\WP_CLI::error( 'Invalid JSON format' );
		}

		$mode   = $assoc_args['mode'] ?? 'merge';
		$result = $this->settings_manager->import_settings( $data, $mode );

		if ( $result['success'] ) {
			\WP_CLI::success( "{$result['imported_count']} settings imported, {$result['skipped_count']} skipped" );
		} else {
			\WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * View audit log
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Number of entries
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--user=<user-id>]
	 * : Filter by user ID
	 *
	 * [--action=<action>]
	 * : Filter by action type
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings audit
	 *     wp zerospam network-settings audit --limit=100
	 *     wp zerospam network-settings audit --action=lock
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function audit( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$query_args = array(
			'limit' => $assoc_args['limit'] ?? 50,
		);

		if ( ! empty( $assoc_args['user'] ) ) {
			$query_args['user_id'] = absint( $assoc_args['user'] );
		}

		if ( ! empty( $assoc_args['action'] ) ) {
			$query_args['action_type'] = sanitize_text_field( $assoc_args['action'] );
		}

		$entries = $this->settings_manager->get_audit_log( $query_args );

		if ( empty( $entries ) ) {
			\WP_CLI::success( 'No audit entries found.' );
			return;
		}

		$items = array();
		foreach ( $entries as $entry ) {
			$items[] = array(
				'Date'       => $entry['date_created'],
				'User'       => $entry['user_login'],
				'Action'     => $entry['action_type'],
				'Setting'    => $entry['setting_key'] ?? 'N/A',
				'Old Value'  => substr( $entry['old_value'] ?? '', 0, 30 ),
				'New Value'  => substr( $entry['new_value'] ?? '', 0, 30 ),
			);
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array_keys( $items[0] ) );
	}

	/**
	 * List available templates
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings template-list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function template_list( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$templates = $this->templates_manager->get_all_templates();

		if ( empty( $templates ) ) {
			\WP_CLI::success( 'No templates found.' );
			return;
		}

		$items = array();
		foreach ( $templates as $slug => $template ) {
			$items[] = array(
				'Slug'        => $slug,
				'Name'        => $template['name'],
				'Type'        => $template['type'],
				'Settings'    => count( $template['settings'] ),
				'Description' => substr( $template['description'] ?? '', 0, 50 ),
			);
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array_keys( $items[0] ) );
	}

	/**
	 * Apply a template
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Template slug
	 *
	 * [--scope=<scope>]
	 * : Application scope
	 * ---
	 * default: network
	 * options:
	 *   - network
	 *   - sites
	 * ---
	 *
	 * [--lock]
	 * : Lock all settings (network scope only)
	 *
	 * [--sites=<sites>]
	 * : Comma-separated site IDs (sites scope only)
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings template-apply strict_protection
	 *     wp zerospam network-settings template-apply balanced --scope=sites
	 *     wp zerospam network-settings template-apply strict_protection --lock
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function template_apply( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$slug  = $args[0];
		$scope = $assoc_args['scope'] ?? 'network';
		$lock  = isset( $assoc_args['lock'] );

		if ( 'network' === $scope ) {
			$result = $this->templates_manager->apply_to_network( $slug, $lock );

			if ( $result ) {
				\WP_CLI::success( "Template '$slug' applied to network" );
			} else {
				\WP_CLI::error( "Failed to apply template '$slug'" );
			}
		} else {
			$sites    = ! empty( $assoc_args['sites'] ) ? array_map( 'intval', explode( ',', $assoc_args['sites'] ) ) : array();
			if ( empty( $sites ) ) {
				$all_sites = get_sites( array( 'number' => 0 ) );
				$sites     = wp_list_pluck( $all_sites, 'blog_id' );
			}

			$result = $this->templates_manager->apply_to_sites( $slug, $sites );

			if ( $result['success'] ) {
				\WP_CLI::success( "{$result['updated_count']} sites updated" );
			} else {
				\WP_CLI::error( $result['message'] );
			}
		}
	}

	/**
	 * Create a template from current settings
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Template name
	 *
	 * <slug>
	 * : Template slug
	 *
	 * [--description=<description>]
	 * : Template description
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings template-create "My Config" my-config
	 *     wp zerospam network-settings template-create "Production" production --description="Production settings"
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function template_create( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$name        = $args[0];
		$slug        = $args[1];
		$description = $assoc_args['description'] ?? '';

		$template_id = $this->templates_manager->save_current_as_template( $name, $slug, $description );

		if ( $template_id ) {
			\WP_CLI::success( "Template '$name' created with ID $template_id" );
		} else {
			\WP_CLI::error( "Failed to create template '$name'" );
		}
	}

	/**
	 * Delete a custom template
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Template slug
	 *
	 * ## EXAMPLES
	 *
	 *     wp zerospam network-settings template-delete my-config
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function template_delete( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This command requires a multisite installation.' );
		}

		$slug   = $args[0];
		$result = $this->templates_manager->delete_template( $slug );

		if ( $result ) {
			\WP_CLI::success( "Template '$slug' deleted" );
		} else {
			\WP_CLI::error( "Failed to delete template '$slug'" );
		}
	}
}
