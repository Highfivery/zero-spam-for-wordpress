<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Notices to this file name.
/**
 * Dismissals of Zero Spam's admin notices.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\Admin_Safe_IPs;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The one way Zero Spam's admin notices are dismissed, from the notice's
 * own Dismiss button (admin-post.php, works without JavaScript) or over
 * REST (`POST zero-spam/v1/ui/notices/{id}/dismiss`).
 *
 * Notices about the site (a fact about the site, not a tip for one user)
 * are dismissed for every administrator of that site, in the site option
 * `zerospam_dismissed_notices` (not autoloaded): notice ID => what was
 * dismissed. Tips inside the admin app are dismissed per user in the
 * `zerospam_ui` user meta (`ui/prefs`, `dismissed`).
 *
 * Notices:
 * - `shared-ip`: visitors seem to share one IP address
 *   (Shared_IP_Warning). The dismissal covers that warning only: every
 *   trusted-proxy warning, or the shared-IP warning for one address.
 * - `safe-ip`: the administrator's address was saved as one that is never
 *   locked out (Admin_Safe_IPs). Shown once to that administrator;
 *   dismissing it removes it.
 *
 * @since 6.0.0
 */
final class Notices {

	/**
	 * Site option of the site notices' dismissals.
	 *
	 * @var string
	 */
	const OPTION = 'zerospam_dismissed_notices';

	/**
	 * Notices that can be dismissed.
	 *
	 * @var string[]
	 */
	const IDS = array( 'shared-ip', 'safe-ip' );

	/**
	 * What was dismissed for a notice, or null.
	 *
	 * @param string $id Notice ID.
	 * @return string|null
	 */
	public static function dismissed( string $id ): ?string {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) && isset( $stored[ $id ] ) && is_string( $stored[ $id ] ) ? $stored[ $id ] : null;
	}

	/**
	 * Records what was dismissed for a notice.
	 *
	 * @param string $id    Notice ID.
	 * @param string $value What was dismissed.
	 */
	public static function remember( string $id, string $value ): void {
		$stored        = get_option( self::OPTION, array() );
		$stored        = is_array( $stored ) ? $stored : array();
		$stored[ $id ] = $value;

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Dismisses a notice as it is shown now. The caller checked the user's
	 * capability (and the nonce, outside REST).
	 *
	 * @param string $id Notice ID.
	 * @return bool|null True when it was dismissed, false when there was nothing to dismiss, null for an unknown notice.
	 */
	public static function dismiss( string $id ): ?bool {
		switch ( $id ) {
			case 'shared-ip':
				$verdict = Shared_IP_Warning::get_verdict();

				if ( null === $verdict ) {
					return false;
				}

				self::remember( $id, Shared_IP_Warning::dismissal_key( $verdict ) );

				return true;

			case 'safe-ip':
				return Admin_Safe_IPs::dismiss_for_current_user();
		}

		return null;
	}
}
