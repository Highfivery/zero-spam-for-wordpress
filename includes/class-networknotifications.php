<?php
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
		$notifications_enabled = get_site_option( 'zerospam_network_notifications_enabled', true );
		
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
		// Check if notifications are enabled.
		$notifications_enabled = get_site_option( 'zerospam_network_notifications_enabled', true );

		if ( ! $notifications_enabled ) {
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
			if ( isset( $old_config['locked'] ) && $old_config['locked'] !== ( ! empty( $config['locked'] ) ) ) {
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
			$affected = array_merge( $affected, $sites_using_default );
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

		$user = wp_get_current_user();

		foreach ( $affected_sites as $site_id ) {
			switch_to_blog( $site_id );

			// Get site admin email.
			$admin_email = get_option( 'admin_email' );
			$site_name   = get_bloginfo( 'name' );

			// Build email.
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Zero Spam Network Settings Updated', 'zero-spam' ),
				$site_name
			);

			$message = sprintf(
				/* translators: 1: network admin name, 2: site name */
				__( 'Hello,

The Zero Spam settings for your site "%2$s" have been updated by network administrator %1$s.

The following settings were changed:

', 'zero-spam' ),
				$user->display_name,
				$site_name
			);

			foreach ( $changed_settings as $key => $change ) {
				$message .= sprintf(
					"• %s:\n  Old: %s\n  New: %s\n",
					$key,
					$change['old_value'] ?? 'Not set',
					$change['new_value']
				);

				if ( ! empty( $change['locked'] ) ) {
					$message .= __( '  🔒 This setting is now locked and cannot be changed at the site level.', 'zero-spam' ) . "\n";
				}

				if ( ! empty( $change['lock_changed'] ) && empty( $change['locked'] ) ) {
					$message .= __( '  🔓 This setting is now unlocked and can be customized for your site.', 'zero-spam' ) . "\n";
				}

				$message .= "\n";
			}

			$message .= sprintf(
				/* translators: %s: site URL */
				__( 'You can view your Zero Spam settings at: %s', 'zero-spam' ),
				admin_url( 'options-general.php?page=wordpress-zero-spam-settings' )
			);

			// Send email.
			wp_mail( $admin_email, $subject, $message );

			restore_current_blog();
		}
	}

	/**
	 * Send weekly summary
	 */
	public function send_weekly_summary() {
		if ( ! is_multisite() ) {
			return;
		}

		// Check if notifications are enabled.
		$notifications_enabled = get_site_option( 'zerospam_network_notifications_enabled', true );
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
		$sites                = get_sites( array( 'number' => 0 ) );
		$total_sites          = count( $sites );
		$settings_manager     = new Network_Settings_Manager();
		$all_settings         = $settings_manager->get_all_with_status();
		$locked_settings_count = 0;
		$total_overrides      = 0;

		foreach ( $all_settings as $config ) {
			if ( $config['locked'] ) {
				$locked_settings_count++;
			}
			$total_overrides += $config['overridden'];
		}

		// Get recent changes.
		$recent_changes = $settings_manager->get_audit_log(
			array(
				'limit' => 10,
			)
		);

		// Build email.
		$subject = __( 'Zero Spam Network - Weekly Summary', 'zero-spam' );

		$message = sprintf(
			__( 'Zero Spam Network Weekly Summary

Here is your weekly summary for the Zero Spam network:

Network Statistics:
• Total Sites: %d
• Network Settings: %d
• Locked Settings: %d
• Total Site Overrides: %d

', 'zero-spam' ),
			$total_sites,
			count( $all_settings ),
			$locked_settings_count,
			$total_overrides
		);

		if ( ! empty( $recent_changes ) ) {
			$message .= __( 'Recent Changes (Last 10):

', 'zero-spam' );

			foreach ( $recent_changes as $change ) {
				$message .= sprintf(
					"• %s - %s - %s\n",
					$change['date_created'],
					$change['user_login'],
					ucfirst( $change['action_type'] )
				);
			}
		}

		$message .= sprintf(
			"\n" . __( 'View full network settings: %s', 'zero-spam' ),
			network_admin_url( 'settings.php?page=zerospam-network-settings' )
		);

		// Send email.
		wp_mail( $network_admin_email, $subject, $message );
	}

	/**
	 * Send notification to specific site admin
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

		$admin_email = get_option( 'admin_email' );
		$result      = wp_mail( $admin_email, $subject, $message );

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
				$sent_count++;
			} else {
				$failed_count++;
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

		$enabled = (bool) $enabled;
		
		// Check if the value is already set to the desired state.
		$current_value = (bool) get_site_option( 'zerospam_network_notifications_enabled', true );
		
		// If already set to the desired value, consider it a success.
		if ( $current_value === $enabled ) {
			return true;
		}

		return update_site_option( 'zerospam_network_notifications_enabled', $enabled );
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

		return (bool) get_site_option( 'zerospam_network_notifications_enabled', true );
	}
}
