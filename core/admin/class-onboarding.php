<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Onboarding to this file name.
/**
 * First-run setup and the "What's new in 6.0" panel: when each is shown.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Core\User;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Two site flags for the 6.0 admin:
 *
 * - `zerospam_onboarding` = `pending`: a fresh install whose administrator
 *   has not been through first-run setup yet. Set by the activation hook
 *   only when the site has no Zero Spam settings and the plugin was
 *   activated on its own (not network-wide, not in a bulk activation).
 *   The Overview shows the setup instead while it is set; finishing or
 *   skipping the setup removes it (`zero-spam/v1/ui/onboarding`).
 * - `zerospam_whats_new` = `6.0.0`: a site upgraded from 5.x, set by the
 *   `v6_whats_new` migration. The Overview then shows the "What's new in
 *   6.0" tile to each administrator until they dismiss it (the per-user
 *   `whats_new_seen` preference, ui/prefs).
 *
 * Neither shows anything outside Zero Spam's own screens.
 *
 * @since 6.0.0
 */
final class Onboarding {

	/**
	 * Site option flagging a fresh install for first-run setup.
	 *
	 * @var string
	 */
	const OPTION = 'zerospam_onboarding';

	/**
	 * Value of OPTION while the setup has not been finished or skipped.
	 *
	 * @var string
	 */
	const PENDING = 'pending';

	/**
	 * Site option naming the release whose "What's new" panel to show.
	 *
	 * @var string
	 */
	const WHATS_NEW_OPTION = 'zerospam_whats_new';

	/**
	 * The release the "What's new" panel describes.
	 *
	 * @var string
	 */
	const WHATS_NEW_VERSION = '6.0.0';

	/**
	 * Activation: flags a fresh install for first-run setup.
	 *
	 * Only when the plugin was activated on this site alone (a network
	 * activation covers sites nobody is looking at; a bulk activation
	 * leaves the administrator on the Plugins screen with other plugins to
	 * set up) and the site has never stored Zero Spam settings (a
	 * reactivation keeps its configuration).
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 */
	public static function on_activation( bool $network_wide ): void {
		if ( $network_wide || self::is_bulk_activation() ) {
			return;
		}

		if ( false !== get_option( 'zero-spam-settings' ) ) {
			return;
		}

		update_option( self::OPTION, self::PENDING, true );
	}

	/**
	 * Whether the current request activates several plugins at once
	 * (Plugins screen, Bulk actions → Activate).
	 */
	public static function is_bulk_activation(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only to decide whether to flag first-run setup; WordPress checked the bulk action's nonce before activating.
		foreach ( array( 'action', 'action2' ) as $field ) {
			if ( isset( $_REQUEST[ $field ] ) && 'activate-selected' === sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) ) {
				return true;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return false;
	}

	/**
	 * Whether first-run setup is pending on the current site.
	 */
	public static function is_pending(): bool {
		return self::PENDING === get_option( self::OPTION );
	}

	/**
	 * Marks first-run setup as done (finished or skipped).
	 */
	public static function complete(): void {
		delete_option( self::OPTION );
	}

	/**
	 * The release whose "What's new" panel the site shows, or ''.
	 */
	public static function whats_new(): string {
		$version = get_option( self::WHATS_NEW_OPTION, '' );

		return is_string( $version ) && 1 === preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $version ) ? $version : '';
	}

	/**
	 * Migration `v6_whats_new`: an upgraded site shows "What's new in 6.0".
	 *
	 * A site counts as upgraded when an earlier version left its traces
	 * (`zero-spam-last-update`, written on every load since 5.x, or the
	 * core settings option). A fresh install has neither when this first
	 * runs, and one waiting for first-run setup is never upgraded. An
	 * existing value is kept, so the migration can run again safely.
	 *
	 * @return true
	 */
	public static function migrate_whats_new(): bool {
		if ( self::is_pending() ) {
			return true;
		}

		if ( false === get_option( 'zero-spam-last-update' ) && false === get_option( 'zero-spam-settings' ) ) {
			return true;
		}

		add_option( self::WHATS_NEW_OPTION, self::WHATS_NEW_VERSION, '', true );

		return true;
	}

	/**
	 * The current administrator's address when Zero Spam never locks it
	 * out (Admin_Safe_IPs), for the last step of the setup; '' otherwise.
	 */
	public static function safe_ip(): string {
		$ip = User::get_ip();

		return is_string( $ip ) && Admin_Safe_IPs::contains( $ip ) ? $ip : '';
	}
}
