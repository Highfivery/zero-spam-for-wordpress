<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps UI_Prefs_Controller to this file name.
/**
 * REST route for a user's Zero Spam admin preferences.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/ui/prefs`: the current user's preferences for the Zero Spam
 * admin (theme, motion, live feed, dismissed tips, onboarding progress),
 * stored in the user meta `zerospam_ui`.
 *
 * Internal to the admin screens (the `ui/` routes may change between
 * releases). Any logged-in user who can read the site may read and change
 * their own preferences, and only their own.
 *
 * @since 6.0.0
 */
class UI_Prefs_Controller extends Base_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'ui/prefs';

	/**
	 * User meta key.
	 *
	 * @var string
	 */
	const META_KEY = 'zerospam_ui';

	/**
	 * Most dismissed IDs kept.
	 *
	 * @var int
	 */
	const MAX_DISMISSED = 200;

	/**
	 * Last onboarding step.
	 *
	 * @var int
	 */
	const MAX_ONBOARDING_STEP = 4;

	/**
	 * Preferences and their defaults.
	 *
	 * @var array<string, mixed>
	 */
	const DEFAULTS = array(
		'theme'           => 'auto',
		'reduce_motion'   => false,
		'live_paused'     => false,
		'dismissed'       => array(),
		'whats_new_seen'  => '',
		'onboarding_step' => 0,
	);

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'logged_in_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'logged_in_permissions_check' ),
				),
			)
		);
	}

	/**
	 * The current user's preferences.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return rest_ensure_response( $this->filter_fields( $this->read( get_current_user_id() ), $request ) );
	}

	/**
	 * Changes some of the current user's preferences.
	 *
	 * The JSON body names only the preferences to change. Unknown names and
	 * values of the wrong type are refused, and nothing is saved then.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) || ! $body ) {
			return $this->invalid_params_error( array( 'body' => __( 'Send the preferences to change as a JSON object.', 'zero-spam' ) ) );
		}

		$changes = array();
		$errors  = array();

		foreach ( $body as $name => $value ) {
			$name = (string) $name;

			if ( ! array_key_exists( $name, self::DEFAULTS ) ) {
				$errors[ $name ] = __( 'Unknown preference.', 'zero-spam' );
				continue;
			}

			$checked = $this->check( $name, $value );

			if ( is_wp_error( $checked ) ) {
				$errors[ $name ] = $checked->get_error_message();
				continue;
			}

			$changes[ $name ] = $checked;
		}

		if ( $errors ) {
			return $this->invalid_params_error( $errors );
		}

		$user_id = get_current_user_id();
		$prefs   = array_merge( $this->read( $user_id ), $changes );

		update_user_meta( $user_id, self::META_KEY, $prefs );

		return rest_ensure_response( $this->filter_fields( $prefs, $request ) );
	}

	/**
	 * A user's preferences, for screens rendered in PHP (the dashboard widget
	 * reads the theme and motion preference from here).
	 *
	 * @since 6.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> As GET ui/prefs returns them.
	 */
	public static function for_user( int $user_id ): array {
		return ( new self() )->read( $user_id );
	}

	/**
	 * A user's preferences, defaults filled in and invalid stored values dropped.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	private function read( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META_KEY, true );
		$prefs  = self::DEFAULTS;

		if ( is_array( $stored ) ) {
			foreach ( self::DEFAULTS as $name => $default ) {
				if ( array_key_exists( $name, $stored ) && ! is_wp_error( $this->check( $name, $stored[ $name ] ) ) ) {
					$prefs[ $name ] = $stored[ $name ];
				}
			}
		}

		return $prefs;
	}

	/**
	 * Checks one preference value.
	 *
	 * @param string $name  Preference.
	 * @param mixed  $value Value.
	 * @return mixed|WP_Error The value, or why it is refused.
	 */
	private function check( string $name, $value ) {
		switch ( $name ) {
			case 'theme':
				return in_array( $value, array( 'light', 'dark', 'auto' ), true )
					? $value
					: new WP_Error( 'invalid', __( 'Must be light, dark or auto.', 'zero-spam' ) );

			case 'reduce_motion':
			case 'live_paused':
				return is_bool( $value ) ? $value : new WP_Error( 'invalid', __( 'Must be true or false.', 'zero-spam' ) );

			case 'dismissed':
				if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > self::MAX_DISMISSED ) {
					/* translators: %d: maximum number of IDs */
					return new WP_Error( 'invalid', sprintf( __( 'Must be a list of at most %d IDs.', 'zero-spam' ), self::MAX_DISMISSED ) );
				}

				foreach ( $value as $id ) {
					if ( ! is_string( $id ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9_.:-]{0,63}$/', $id ) ) {
						return new WP_Error( 'invalid', __( 'Each ID must be 1 to 64 lowercase letters, digits, dots, colons, dashes or underscores.', 'zero-spam' ) );
					}
				}

				return array_values( array_unique( $value ) );

			case 'whats_new_seen':
				return is_string( $value ) && ( '' === $value || 1 === preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}(?:-[0-9A-Za-z.]{1,16})?$/', $value ) )
					? $value
					: new WP_Error( 'invalid', __( 'Must be a version number such as 6.0.0, or empty.', 'zero-spam' ) );

			case 'onboarding_step':
				return is_int( $value ) && $value >= 0 && $value <= self::MAX_ONBOARDING_STEP
					? $value
					/* translators: %d: last step number */
					: new WP_Error( 'invalid', sprintf( __( 'Must be a whole number from 0 to %d.', 'zero-spam' ), self::MAX_ONBOARDING_STEP ) );
		}

		return new WP_Error( 'invalid', __( 'Unknown preference.', 'zero-spam' ) );
	}
}
