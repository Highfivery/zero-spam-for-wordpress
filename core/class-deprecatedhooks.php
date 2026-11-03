<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Deprecated_Hooks to this file name.
/**
 * Hooks of the 5.x admin screens, deprecated in 6.0.0.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Tells developers that a hook of the 5.x admin screens no longer fires.
 *
 * The screens that fired these hooks were removed in 6.0.0 (the 6.0 admin
 * renders settings from the settings schema in the browser), so nothing
 * fires them any more: there is no field, page or map for a callback to
 * act on. When a plugin still hooks one, the same deprecation notice
 * do_action_deprecated() and apply_filters_deprecated() give is raised
 * once per request in the admin (`_deprecated_hook()`, shown with
 * WP_DEBUG). The hooks are removed from this list, and from the docs, in
 * 7.0.0.
 *
 * @since 6.0.0
 */
final class Deprecated_Hooks {

	/**
	 * Deprecated hooks and what to use instead ('' for nothing).
	 *
	 * @var array<string, string>
	 */
	const HOOKS = array(
		// The 5.x settings screen and its fields.
		'zerospam_settings_page_top'      => '',
		'zerospam_setting_field_before'   => '',
		'zerospam_setting_field_after'    => '',
		'zerospam_setting_field_disabled' => 'zerospam_settings',
		// The map in the 5.x detection details.
		'zerospam_google_map'             => '',
		// The Enhanced Protection promo notice, removed in 6.0.0.
		'zerospam_display_promo_notice'   => '',
		'zerospam_promo_notice_displayed' => '',
		'zerospam_promo_notice_dismissed' => '',
		'zerospam_promo_notice_clicked'   => '',
	);

	/**
	 * Version the hooks were deprecated in.
	 *
	 * @var string
	 */
	const VERSION = '6.0.0';

	/**
	 * Checks for callbacks once the plugins have added theirs.
	 */
	public function __construct() {
		add_action( 'admin_init', array( self::class, 'warn' ) );
	}

	/**
	 * Raises a deprecation notice for each deprecated hook that has a callback
	 * (the `admin_init` callback).
	 */
	public static function warn(): void {
		self::check();
	}

	/**
	 * Raises a deprecation notice for each deprecated hook that has a callback.
	 *
	 * @return string[] The hooks that have callbacks.
	 */
	public static function check(): array {
		$hooked = array();

		foreach ( self::HOOKS as $hook => $replacement ) {
			if ( ! has_filter( $hook ) ) {
				continue;
			}

			$hooked[] = $hook;

			_deprecated_hook(
				esc_html( $hook ),
				esc_html( self::VERSION ),
				esc_html( $replacement ),
				esc_html__( 'The Zero Spam 5.x admin screen that fired it was removed, so it never fires.', 'zero-spam' )
			);
		}

		return $hooked;
	}
}
