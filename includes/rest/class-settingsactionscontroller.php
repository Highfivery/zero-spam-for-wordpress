<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Settings_Actions_Controller to this file name.
/**
 * REST routes for the settings actions, export and import.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\API_Usage_Alerts;
use ZeroSpam\Includes\Settings_Resolver;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/settings/actions/{action}`, `settings/export` and
 * `settings/import`, for administrators of the current site.
 *
 * The actions do what the `zerospam-action` links and buttons of the 5.x
 * settings screens do, and respect the settings the network administrator
 * has locked.
 *
 * @since 6.0.0
 */
class Settings_Actions_Controller extends Base_Controller {

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
	protected $rest_base = 'settings';

	/**
	 * The actions, in the route's pattern.
	 *
	 * @var string[]
	 */
	const ACTIONS = array(
		'autoconfigure',
		'regenerate-honeypot',
		'update-blocked-email-domains',
		'update-disallowed-words',
		'clear-error-log',
		'test-alert-email',
		'test-alert-notice',
		'test-alert-webhook',
	);

	/**
	 * Largest import accepted, in bytes (5 MB).
	 *
	 * @var int
	 */
	const MAX_IMPORT_BYTES = 5 * MB_IN_BYTES;

	/**
	 * Value of `format` in an export.
	 *
	 * @var string
	 */
	const EXPORT_FORMAT = 'zero-spam-settings';

	/**
	 * Version of the export format.
	 *
	 * @var int
	 */
	const EXPORT_VERSION = 1;

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/actions/(?P<action>' . implode( '|', self::ACTIONS ) . ')',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_action' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_settings' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_settings' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'include_secrets' => array(
							'description' => __( 'Also import license and API keys and webhook URLs found in the file (query parameter).', 'zero-spam' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Runs one action.
	 *
	 * Answers `{ action, success, message }`; when the action could not be
	 * done, an error with the same message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_action( $request ) {
		$action = (string) $request->get_param( 'action' );
		$extra  = array();

		switch ( $action ) {
			case 'autoconfigure':
				$extra   = array( 'skipped_locked' => $this->autoconfigure() );
				$message = __( 'Zero Spam for WordPress has successfully been auto-configured with the recommended settings.', 'zero-spam' );
				break;

			case 'regenerate-honeypot':
				Utilities::get_honeypot( true );
				$message = __( 'Zero Spam for WordPress honeypot ID has been successfully reset.', 'zero-spam' );
				break;

			case 'update-blocked-email-domains':
				$result = $this->update_blocked_email_domains();
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$extra   = array( 'count' => $result );
				$message = __( 'Zero Spam for WordPress blocked email domains have been successfully updated.', 'zero-spam' );
				break;

			case 'update-disallowed-words':
				if ( ! Settings::update_disallowed_words() ) {
					return $this->action_failed( __( 'The recommended disallowed words list could not be read.', 'zero-spam' ), 500 );
				}
				$message = __( "WordPress's disallowed words list has been successfully updated to the recommended.", 'zero-spam' );
				break;

			case 'clear-error-log':
				Utilities::delete_error_log();
				$message = __( 'Zero Spam for WordPress error log has been successfully deleted.', 'zero-spam' );
				break;

			case 'test-alert-email':
				$result = $this->test_alert_email();
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$message = __( 'Test email alert sent successfully! Check your inbox.', 'zero-spam' );
				break;

			case 'test-alert-notice':
				API_Usage_Alerts::send_test_alert( 'quota_warning', 'admin_notice' );
				$message = __( 'Test admin notice created successfully! You should see it on the next page load.', 'zero-spam' );
				break;

			case 'test-alert-webhook':
				$result = $this->test_alert_webhook();
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$message = __( 'Test webhook sent successfully! Check your webhook endpoint.', 'zero-spam' );
				break;

			default:
				return new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.', 'zero-spam' ), array( 'status' => 404 ) );
		}

		$this->reset_caches();

		return rest_ensure_response(
			array_merge(
				array(
					'action'  => $action,
					'success' => true,
					'message' => $message,
				),
				$extra
			)
		);
	}

	/**
	 * Exports the settings.
	 *
	 * The values are the effective ones the site uses (its own, the network's
	 * or the defaults), so an export moved to another site reproduces how this
	 * one behaves. Secrets (license and API keys, webhook URLs) are never
	 * exported; their keys are listed in `omitted_secrets`. Settings of type
	 * `html` hold no value and are left out.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export_settings( $request ) {
		unset( $request );

		$settings = array();
		$omitted  = array();

		// Values only: no `default`, so the registry is built once.
		foreach ( Settings_Schema::get( false )['settings'] as $entry ) {
			if ( 'html' === $entry['type'] ) {
				continue;
			}

			if ( $entry['secret'] ) {
				$omitted[] = $entry['key'];
				continue;
			}

			$settings[ $entry['module'] ][ $entry['key'] ] = $this->exportable_value( $entry );
		}

		return rest_ensure_response(
			array(
				'format'          => self::EXPORT_FORMAT,
				'version'         => self::EXPORT_VERSION,
				'plugin_version'  => ZEROSPAM_VERSION,
				'exported_at'     => gmdate( 'c' ),
				'settings'        => $settings,
				'omitted_secrets' => $omitted,
			)
		);
	}

	/**
	 * Imports settings.
	 *
	 * Accepts an export of this route (`format` `zero-spam-settings`) or the
	 * 5.x Import/Export screen's JSON (`{ module: { key: value } }`), up to
	 * 5 MB. Each value is checked with the rules PATCH /settings applies. The
	 * import is partial: valid values are stored, and the response reports the
	 * rest: `errors` (unknown keys, invalid values), `ignored` (settings that
	 * hold no value, and entries that are not a module's settings) and
	 * `skipped_locked` (settings the network administrator has locked). Secrets
	 * in the import are stored. Nothing is stored when the body is not a JSON
	 * object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_settings( $request ) {
		$body = (string) $request->get_body();

		if ( strlen( $body ) > self::MAX_IMPORT_BYTES ) {
			return new WP_Error(
				'zerospam_import_too_large',
				__( 'The settings file is too large. Imports are limited to 5 MB.', 'zero-spam' ),
				array( 'status' => 413 )
			);
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! $data || array_is_list( $data ) ) {
			return $this->invalid_params_error( array( 'body' => __( 'Send the exported settings as a JSON object.', 'zero-spam' ) ) );
		}

		if ( isset( $data['format'] ) ) {
			if ( self::EXPORT_FORMAT !== $data['format'] || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
				return $this->invalid_params_error( array( 'body' => __( 'This is not a Zero Spam settings export.', 'zero-spam' ) ) );
			}

			$data = $data['settings'];
		}

		$values  = array();
		$ignored = array();

		foreach ( $data as $module => $module_values ) {
			if ( ! is_array( $module_values ) || ( $module_values && array_is_list( $module_values ) ) ) {
				$ignored[] = (string) $module;
				continue;
			}

			foreach ( $module_values as $key => $value ) {
				$values[ (string) $key ] = $value;
			}
		}

		$registry = Settings_Schema::registry();
		foreach ( array_keys( $values ) as $key ) {
			if ( isset( $registry[ $key ]['type'] ) && 'html' === $registry[ $key ]['type'] ) {
				$ignored[] = $key;
				unset( $values[ $key ] );
			}
		}

		// Keys and webhook URLs only with include_secrets: a shared or old
		// export must not replace them silently.
		$skipped_secrets = array();
		if ( true !== $request->get_param( 'include_secrets' ) ) {
			foreach ( array_keys( $values ) as $key ) {
				if ( Settings_Schema::is_secret( (string) $key ) ) {
					$skipped_secrets[] = (string) $key;
					unset( $values[ $key ] );
				}
			}
		}

		$controller = new Settings_Controller();
		$locked     = $controller->get_locked_keys( array_keys( $values ) );
		$values     = array_diff_key( $values, array_flip( $locked ) );
		$checked    = $controller->validate_values( $values );
		$changes    = $controller->write_site_values( $checked['valid'] );

		if ( is_wp_error( $changes ) ) {
			return $changes;
		}

		$this->reset_caches();

		return rest_ensure_response(
			array(
				'imported'        => array_keys( $checked['valid'] ),
				'changed'         => array_keys( $changes ),
				'skipped_locked'  => array_values( $locked ),
				'skipped_secrets' => $skipped_secrets,
				'errors'          => (object) $checked['errors'],
				'ignored'         => array_values( array_unique( $ignored ) ),
			)
		);
	}

	/**
	 * A setting's value in the form the import accepts.
	 *
	 * The registry reports `false` for a setting that is not set; text
	 * settings export that as an empty string and multiple choices as an
	 * empty list, so an export can be imported again as it is.
	 *
	 * @param array<string, mixed> $entry Schema entry.
	 * @return mixed
	 */
	private function exportable_value( array $entry ) {
		$value = $entry['value'];

		if ( false !== $value && null !== $value ) {
			return $value;
		}

		if ( in_array( $entry['type'], array( 'text', 'textarea', 'url', 'date' ), true ) ) {
			return '';
		}

		if ( ! empty( $entry['multiple'] ) ) {
			return array();
		}

		return $value;
	}

	/**
	 * Stores the recommended settings, leaving locked ones alone.
	 *
	 * @return string[] The locked settings that were skipped.
	 */
	private function autoconfigure(): array {
		$controller = new Settings_Controller();
		$keys       = array_keys( Settings_Schema::registry() );
		$locked     = $controller->get_locked_keys( array_map( 'strval', $keys ) );
		$before     = $this->site_values();

		Settings::auto_configure( $locked );
		$this->reset_caches();

		// One audit entry, as PATCH /settings writes (secrets masked).
		$changes = array();
		foreach ( $this->site_values() as $key => $value ) {
			$old = $before[ $key ] ?? null;
			if ( $old !== $value ) {
				$changes[ $key ] = array(
					'old' => $old,
					'new' => $value,
				);
			}
		}
		$controller->audit_site_changes( $changes );

		return $locked;
	}

	/**
	 * The values the current site uses, by key (settings that hold a value).
	 *
	 * @return array<string, mixed>
	 */
	private function site_values(): array {
		$values = array();
		foreach ( Settings_Schema::get( false )['settings'] as $entry ) {
			if ( 'html' !== $entry['type'] ) {
				$values[ (string) $entry['key'] ] = $entry['value'];
			}
		}

		return $values;
	}

	/**
	 * Replaces the blocked email domains with the recommended list.
	 *
	 * Writes the `zerospam_blocked_email_domains` option only (not autoloaded).
	 *
	 * @return int|WP_Error Number of domains stored.
	 */
	private function update_blocked_email_domains() {
		if ( ( new Settings_Controller() )->get_locked_keys( array( 'blocked_email_domains' ) ) ) {
			return $this->locked_error( array( 'blocked_email_domains' ) );
		}

		$domains = Utilities::blocked_email_domains();

		if ( ! is_array( $domains ) || ! $domains ) {
			return $this->action_failed( __( 'The recommended blocked email domains list could not be read.', 'zero-spam' ), 500 );
		}

		update_option( 'zerospam_blocked_email_domains', trim( implode( "\n", $domains ) ), false );

		return count( $domains );
	}

	/**
	 * Sends the test email alert.
	 *
	 * @return true|WP_Error
	 */
	private function test_alert_email() {
		$failed   = false;
		$listener = static function () use ( &$failed ): void {
			$failed = true;
		};

		add_action( 'wp_mail_failed', $listener );
		try {
			API_Usage_Alerts::send_test_alert( 'quota_warning', 'email' );
		} finally {
			remove_action( 'wp_mail_failed', $listener );
		}

		return $failed
			? $this->action_failed( __( 'Failed to send test email alert. Please check your email configuration.', 'zero-spam' ) )
			: true;
	}

	/**
	 * Sends the test webhook alert.
	 *
	 * @return true|WP_Error
	 */
	private function test_alert_webhook() {
		$resolved = Settings_Schema::resolve( 'alert_webhook_url' );
		$url      = null !== $resolved && is_string( $resolved['value'] ) ? trim( $resolved['value'] ) : '';

		if ( '' === $url ) {
			return new WP_Error(
				'zerospam_webhook_missing',
				__( 'Add a webhook URL first, then send a test.', 'zero-spam' ),
				array( 'status' => 400 )
			);
		}

		if ( ! API_Usage_Alerts::send_test_alert( 'quota_warning', 'webhook' ) ) {
			return $this->action_failed( __( 'Failed to send test webhook. Please check your webhook URL configuration.', 'zero-spam' ) );
		}

		return true;
	}

	/**
	 * The error for an action that could not be done.
	 *
	 * @param string $message What went wrong.
	 * @param int    $status  HTTP status: 502 when another service failed, 500 otherwise.
	 * @return WP_Error
	 */
	private function action_failed( string $message, int $status = 502 ): WP_Error {
		return new WP_Error( 'zerospam_action_failed', $message, array( 'status' => $status ) );
	}

	/**
	 * Forgets cached settings after a write.
	 */
	private function reset_caches(): void {
		Settings_Resolver::invalidate_all();
		Settings_Schema::reset_cache();
		Settings::$settings = array();
		self::reset_request_cache();
	}
}
