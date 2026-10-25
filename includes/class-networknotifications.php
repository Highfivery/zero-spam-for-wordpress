<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Notifications to this file name.
/**
 * Network Notifications - Email notification system
 *
 * Sends email notifications to site admins when network settings change.
 * Handles weekly summaries and important alerts.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Notifications class
 */
class Network_Notifications {

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
		if ( ! is_multisite() ) {
			return;
		}

		$this->network_settings = new Network_Settings();

		// Hook into settings changes.
		add_action( 'update_site_option_' . Network_Settings::META_KEY, array( $this, 'on_network_settings_updated' ), 10, 3 );

		// Schedule weekly summary only if notifications are enabled.
		$notifications_enabled = get_site_option( 'zerospam_network_notifications_enabled', '1' );

		if ( $notifications_enabled ) {
			if ( ! wp_next_scheduled( 'zerospam_network_weekly_summary' ) ) {
				wp_schedule_event( time(), 'weekly', 'zerospam_network_weekly_summary' );
			}
		} else {
			// If notifications are disabled, unschedule the event if it exists.
			$timestamp = wp_next_scheduled( 'zerospam_network_weekly_summary' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'zerospam_network_weekly_summary' );
			}
		}

		add_action( 'zerospam_network_weekly_summary', array( $this, 'send_weekly_summary' ) );
	}

	/**
	 * Handle network settings update
	 *
	 * @param string $option    Option name.
	 * @param mixed  $value     New value.
	 * @param mixed  $old_value Old value.
	 */
	public function on_network_settings_updated( $option, $value, $old_value ) {
		// Check if settings change notifications are enabled (separate from weekly summaries).
		$settings_notifications_enabled = get_site_option( 'zerospam_network_settings_notifications_enabled', '1' );

		if ( ! $settings_notifications_enabled ) {
			return;
		}

		// Detect which settings changed.
		$changed_settings = $this->detect_changes( $old_value, $value );

		if ( empty( $changed_settings ) ) {
			return;
		}

		// Determine which sites are affected.
		$affected_sites = $this->get_affected_sites( $changed_settings );

		// Send notifications.
		$this->send_change_notifications( $changed_settings, $affected_sites );
	}

	/**
	 * Detect changed settings
	 *
	 * @param array $old_value Old settings.
	 * @param array $new_value New settings.
	 * @return array Changed settings.
	 */
	private function detect_changes( $old_value, $new_value ) {
		$changes = array();

		if ( ! is_array( $old_value ) || ! isset( $old_value['settings'] ) ) {
			$old_value = array( 'settings' => array() );
		}

		if ( ! is_array( $new_value ) || ! isset( $new_value['settings'] ) ) {
			return $changes;
		}

		$old_settings = $old_value['settings'];
		$new_settings = $new_value['settings'];

		foreach ( $new_settings as $key => $config ) {
			$old_config = $old_settings[ $key ] ?? array();

			// Check if value changed.
			if ( ! isset( $old_config['value'] ) || $old_config['value'] !== $config['value'] ) {
				$changes[ $key ] = array(
					'old_value' => $old_config['value'] ?? null,
					'new_value' => $config['value'],
					'locked'    => ! empty( $config['locked'] ),
				);
			}

			// Check if lock status changed.
			if ( isset( $old_config['locked'] ) && ( ! empty( $config['locked'] ) ) !== $old_config['locked'] ) {
				if ( ! isset( $changes[ $key ] ) ) {
					$changes[ $key ] = array(
						'old_value' => $config['value'],
						'new_value' => $config['value'],
						'locked'    => ! empty( $config['locked'] ),
					);
				}
				$changes[ $key ]['lock_changed'] = true;
			}
		}

		return $changes;
	}

	/**
	 * Get sites affected by changes
	 *
	 * @param array $changed_settings Changed settings.
	 * @return array Affected site IDs.
	 */
	private function get_affected_sites( $changed_settings ) {
		$affected = array();

		foreach ( $changed_settings as $key => $change ) {
			// If locked, all sites are affected.
			if ( ! empty( $change['locked'] ) ) {
				$sites = get_sites( array( 'number' => 0 ) );
				return wp_list_pluck( $sites, 'blog_id' );
			}

			// Otherwise, only sites using defaults are affected.
			$sites_using_default = $this->network_settings->get_sites_using_default( $key );
			$affected            = array_merge( $affected, $sites_using_default );
		}

		return array_unique( $affected );
	}

	/**
	 * Send change notifications
	 *
	 * @param array $changed_settings Changed settings.
	 * @param array $affected_sites   Affected site IDs.
	 */
	private function send_change_notifications( $changed_settings, $affected_sites ) {
		if ( empty( $affected_sites ) ) {
			return;
		}

		$user   = wp_get_current_user();
		$titles = $this->setting_titles();

		foreach ( $affected_sites as $site_id ) {
			switch_to_blog( $site_id );

			// Get site admin email.
			$admin_email = get_option( 'admin_email' );
			$site_name   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

			$rows = array();
			foreach ( $changed_settings as $key => $change ) {
				$value = sprintf(
					/* translators: 1: old value, 2: new value of a setting */
					__( '%1$s → %2$s', 'zero-spam' ),
					$this->display_value( $change['old_value'] ?? null ),
					$this->display_value( $change['new_value'] ?? null )
				);

				if ( ! empty( $change['locked'] ) ) {
					$value .= ' · ' . __( 'Locked by the network: sites can\'t change it.', 'zero-spam' );
				} elseif ( ! empty( $change['lock_changed'] ) ) {
					$value .= ' · ' . __( 'Unlocked: you can now change it for your site.', 'zero-spam' );
				}

				$rows[] = array( $titles[ $key ] ?? (string) $key, $value );
			}

			Email::send(
				$admin_email,
				sprintf(
					/* translators: %s: site name */
					__( '[%s] Zero Spam Network Settings Updated', 'zero-spam' ),
					$site_name
				),
				array(
					'heading' => __( 'Zero Spam settings updated', 'zero-spam' ),
					'blocks'  => array(
						array(
							'type' => 'text',
							/* translators: 1: network administrator's name, 2: site name */
							'text' => __( 'Network administrator %1$s changed the Zero Spam settings for %2$s.', 'zero-spam' ),
							'args' => array(
								array(
									'text'  => (string) $user->display_name,
									'style' => 'strong',
								),
								array(
									'text'  => $site_name,
									'style' => 'strong',
								),
							),
						),
						array(
							'type'  => 'rows',
							'title' => __( 'What changed', 'zero-spam' ),
							'rows'  => $rows,
						),
						array(
							'type'  => 'button',
							'label' => __( 'View your Zero Spam settings', 'zero-spam' ),
							'url'   => admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ),
						),
					),
				)
			);

			restore_current_blog();
		}
	}

	/**
	 * Setting titles keyed by setting key, read once from the settings registry.
	 *
	 * @return array<string, string>
	 */
	private function setting_titles() {
		$titles = array();
		foreach ( (array) \ZeroSpam\Core\Settings::get_settings() as $key => $setting ) {
			if ( is_array( $setting ) && ! empty( $setting['title'] ) && is_string( $setting['title'] ) ) {
				$titles[ $key ] = wp_strip_all_tags( $setting['title'] );
			}
		}

		return $titles;
	}

	/**
	 * A setting value as plain text for an email.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function display_value( $value ) {
		if ( null === $value || '' === $value || false === $value || array() === $value ) {
			return __( 'Not set', 'zero-spam' );
		}

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'display_value' ), $value ) );
		}

		if ( true === $value ) {
			return __( 'Enabled', 'zero-spam' );
		}

		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
	}

	/**
	 * A translated name for an audit log action type.
	 *
	 * @param string $action Action type (wpzerospam_network_audit.action_type).
	 * @return string
	 */
	private function action_label( $action ) {
		$labels = array(
			'set'      => __( 'Changed a setting', 'zero-spam' ),
			'lock'     => __( 'Locked a setting', 'zero-spam' ),
			'unlock'   => __( 'Unlocked a setting', 'zero-spam' ),
			'apply'    => __( 'Applied to sites', 'zero-spam' ),
			'bulk'     => __( 'Bulk change', 'zero-spam' ),
			'template' => __( 'Applied a template', 'zero-spam' ),
			'import'   => __( 'Imported settings', 'zero-spam' ),
			'reset'    => __( 'Reset settings', 'zero-spam' ),
		);

		return $labels[ $action ] ?? ucfirst( (string) $action );
	}

	/**
	 * Send weekly summary
	 */
	public function send_weekly_summary() {
		if ( ! is_multisite() ) {
			return;
		}

		// Check if notifications are enabled.
		$notifications_enabled = get_site_option( 'zerospam_network_notifications_enabled', '1' );
		if ( ! $notifications_enabled ) {
			return;
		}

		// Get network admin email.
		$network_admin = get_super_admins();
		if ( empty( $network_admin ) ) {
			return;
		}

		$network_admin_email = '';
		foreach ( $network_admin as $admin_login ) {
			$admin_user = get_user_by( 'login', $admin_login );
			if ( $admin_user ) {
				$network_admin_email = $admin_user->user_email;
				break;
			}
		}

		if ( ! $network_admin_email ) {
			return;
		}

		// Get stats for the week.
		$total_sites           = (int) get_sites( array( 'count' => true ) );
		$settings_manager      = new Network_Settings_Manager();
		$all_settings          = $settings_manager->get_all_with_status();
		$locked_settings_count = 0;
		$total_overrides       = 0;

		foreach ( $all_settings as $config ) {
			if ( $config['locked'] ) {
				++$locked_settings_count;
			}
			$total_overrides += $config['overridden'];
		}

		// Get recent changes.
		$recent_changes = $settings_manager->get_audit_log(
			array(
				'limit' => 10,
			)
		);

		$network = wp_specialchars_decode( (string) get_network()->site_name, ENT_QUOTES );

		$blocks = array(
			array(
				'type' => 'text',
				/* translators: %s: network name */
				'text' => __( 'Here is this week\'s summary of Zero Spam on the %s network.', 'zero-spam' ),
				'args' => array(
					array(
						'text'  => $network,
						'style' => 'strong',
					),
				),
			),
			array(
				'type'  => 'rows',
				'title' => __( 'Network', 'zero-spam' ),
				'rows'  => array(
					array( __( 'Total sites', 'zero-spam' ), number_format_i18n( $total_sites ) ),
					array( __( 'Network settings', 'zero-spam' ), number_format_i18n( count( $all_settings ) ) ),
					array( __( 'Locked settings', 'zero-spam' ), number_format_i18n( $locked_settings_count ) ),
					array( __( 'Site overrides', 'zero-spam' ), number_format_i18n( (int) $total_overrides ) ),
				),
			),
		);

		if ( ! empty( $recent_changes ) ) {
			$rows = array();
			foreach ( $recent_changes as $change ) {
				$when   = strtotime( (string) $change['date_created'] );
				$rows[] = array(
					$when ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $when ) : (string) $change['date_created'],
					sprintf(
						/* translators: 1: user login, 2: what they did */
						__( '%1$s: %2$s', 'zero-spam' ),
						(string) $change['user_login'],
						$this->action_label( (string) $change['action_type'] ) . ( empty( $change['setting_key'] ) ? '' : ' (' . $change['setting_key'] . ')' )
					),
				);
			}

			$blocks[] = array(
				'type'  => 'rows',
				'title' => __( 'Recent changes', 'zero-spam' ),
				'rows'  => $rows,
			);
		}

		$blocks[] = array(
			'type'  => 'button',
			'label' => __( 'View network settings', 'zero-spam' ),
			'url'   => network_admin_url( 'settings.php?page=zerospam-network-settings' ),
		);

		Email::send(
			$network_admin_email,
			__( 'Zero Spam Network - Weekly Summary', 'zero-spam' ),
			array(
				'site'    => $network,
				'heading' => __( 'Weekly summary', 'zero-spam' ),
				'blocks'  => $blocks,
			)
		);
	}

	/**
	 * Send notification to specific site admin
	 *
	 * The message is plain text; blank lines separate paragraphs.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @return bool Success.
	 */
	public function send_site_notification( $site_id, $subject, $message ) {
		if ( ! is_multisite() ) {
			return false;
		}

		switch_to_blog( $site_id );

		$blocks = array();
		foreach ( preg_split( '/\R{2,}/', trim( (string) $message ) ) as $paragraph ) {
			$blocks[] = array(
				'type' => 'text',
				'text' => $paragraph,
			);
		}

		$admin_email = get_option( 'admin_email' );
		$result      = Email::send(
			$admin_email,
			(string) $subject,
			array(
				'heading' => (string) $subject,
				'blocks'  => $blocks,
			)
		);

		restore_current_blog();

		return $result;
	}

	/**
	 * Send bulk notification to all site admins
	 *
	 * @param string $subject  Email subject.
	 * @param string $message  Email message.
	 * @param array  $site_ids Specific site IDs (empty = all sites).
	 * @return array Result with counts.
	 */
	public function send_bulk_notification( $subject, $message, $site_ids = array() ) {
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

		// Get sites.
		if ( empty( $site_ids ) ) {
			$sites    = get_sites( array( 'number' => 0 ) );
			$site_ids = wp_list_pluck( $sites, 'blog_id' );
		}

		$sent_count   = 0;
		$failed_count = 0;

		foreach ( $site_ids as $site_id ) {
			$result = $this->send_site_notification( $site_id, $subject, $message );

			if ( $result ) {
				++$sent_count;
			} else {
				++$failed_count;
			}
		}

		return array(
			'success'      => true,
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'total_sites'  => count( $site_ids ),
		);
	}

	/**
	 * Enable/disable notifications
	 *
	 * @param bool $enabled Enable or disable.
	 * @return bool Success.
	 */
	public function toggle_notifications( $enabled ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Convert to string for reliable storage (WordPress site options handle strings better than booleans).
		$value = $enabled ? '1' : '0';

		// Check if the value is already set to the desired state.
		$current_value = get_site_option( 'zerospam_network_notifications_enabled', '1' );

		// If already set to the desired value, consider it a success.
		if ( $current_value === $value ) {
			return true;
		}

		return update_site_option( 'zerospam_network_notifications_enabled', $value );
	}

	/**
	 * Check if notifications are enabled
	 *
	 * @return bool Enabled status.
	 */
	public function are_notifications_enabled() {
		if ( ! is_multisite() ) {
			return false;
		}

		return '1' === get_site_option( 'zerospam_network_notifications_enabled', '1' );
	}

	/**
	 * Enable/disable settings change notifications
	 *
	 * @param bool $enabled Enable or disable.
	 * @return bool Success.
	 */
	public function toggle_settings_notifications( $enabled ) {
		if ( ! is_multisite() ) {
			return false;
		}

		// Check permission.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		// Convert to string for reliable storage (WordPress site options handle strings better than booleans).
		$value = $enabled ? '1' : '0';

		// Check if the value is already set to the desired state.
		$current_value = get_site_option( 'zerospam_network_settings_notifications_enabled', '1' );

		// If already set to the desired value, consider it a success.
		if ( $current_value === $value ) {
			return true;
		}

		return update_site_option( 'zerospam_network_settings_notifications_enabled', $value );
	}

	/**
	 * Check if settings change notifications are enabled
	 *
	 * @return bool Enabled status.
	 */
	public function are_settings_notifications_enabled() {
		if ( ! is_multisite() ) {
			return false;
		}

		return '1' === get_site_option( 'zerospam_network_settings_notifications_enabled', '1' );
	}
}
