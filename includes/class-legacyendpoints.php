<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Legacy_Endpoints to this file name.
/**
 * Deprecated AJAX and form handlers of the 5.x admin screens.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes;

use WP_REST_Request;
use WP_REST_Response;
use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Core\Utilities;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The AJAX actions and form handlers of the 5.x admin screens, kept for one
 * release (build plan C10) and removed in 6.1.0.
 *
 * Each one checks the capability and nonce the 5.x handler checked, raises
 * a deprecation notice (shown with WP_DEBUG) and does its work through the
 * REST route that replaced it (`rest_do_request()`, so the route's own
 * permission check and validation apply). The answers keep the 5.x shape
 * where it matters (`wp_send_json_success()` / `wp_send_json_error()` with
 * a `message`, a redirect for the forms), with the route's data as the
 * payload.
 *
 * Not kept: the Enhanced Protection promo's two AJAX actions (the promo was
 * removed in 6.0.0). `zerospam_refresh_dashboard` stays with the dashboard
 * widget (Dashboard_Widget), already deprecated.
 *
 * @since 6.0.0
 */
final class Legacy_Endpoints {

	/**
	 * Nonce action of the 5.x network settings screen's AJAX calls.
	 *
	 * @var string
	 */
	const NETWORK_NONCE = 'zerospam_network_settings';

	/**
	 * Nonce action of the 5.x network statistics export.
	 *
	 * @var string
	 */
	const STATS_NONCE = 'zerospam_network_stats';

	/**
	 * The 5.x network AJAX actions (`wp_ajax_{action}`) and their handlers.
	 *
	 * @var array<string, string>
	 */
	const NETWORK_AJAX = array(
		'zerospam_network_set_setting'                => 'set_setting',
		'zerospam_network_lock_setting'               => 'lock_setting',
		'zerospam_network_unlock_setting'             => 'unlock_setting',
		'zerospam_network_apply_all'                  => 'apply_all',
		'zerospam_network_reset_site'                 => 'reset_site',
		'zerospam_network_get_comparison'             => 'get_comparison',
		'zerospam_network_export'                     => 'export',
		'zerospam_network_import'                     => 'import',
		'zerospam_network_apply_template'             => 'apply_template',
		'zerospam_network_save_template'              => 'save_template',
		'zerospam_network_delete_template'            => 'delete_template',
		'zerospam_network_save_notification_settings' => 'save_notifications',
		'zerospam_network_toggle_notifications'       => 'toggle_notifications',
	);

	/**
	 * The 5.x period names of the network statistics, as 6.0 periods.
	 *
	 * @var array<string, string>
	 */
	const PERIODS = array(
		'today'     => 'today',
		'yesterday' => 'yesterday',
		'week'      => '7',
		'month'     => '30',
	);

	/**
	 * Registers the handlers.
	 */
	public function __construct() {
		if ( is_multisite() ) {
			foreach ( array_keys( self::NETWORK_AJAX ) as $action ) {
				add_action( 'wp_ajax_' . $action, array( $this, 'network_ajax' ) );
			}

			add_action( 'wp_ajax_zerospam_export_network_stats', array( $this, 'export_network_stats' ) );
		}

		add_action( 'admin_action_add_blocked_ip', array( $this, 'add_blocked_ip' ) );
		add_action( 'admin_action_import_settings', array( $this, 'import_settings' ) );
		add_action( 'admin_post_zerospam_refresh_htaccess', array( $this, 'refresh_htaccess' ) );
		add_action( 'admin_post_zerospam_remove_admin_safe_ip', array( $this, 'remove_admin_safe_ip' ) );
	}

	/**
	 * Runs a REST route as the current user.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route below the namespace, such as `network-settings/export`.
	 * @param array<string, mixed> $params Query (GET) or JSON body parameters.
	 * @param array<string, mixed> $query  Query parameters of a request with a body.
	 * @return WP_REST_Response
	 */
	public static function dispatch( string $method, string $route, array $params = array(), array $query = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/zero-spam/v1/' . ltrim( $route, '/' ) );

		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_query_params( $query );

			if ( $params ) {
				$request->set_header( 'content-type', 'application/json' );
				$request->set_body( (string) wp_json_encode( $params ) );
			}
		}

		return rest_ensure_response( rest_do_request( $request ) );
	}

	/**
	 * Raises the deprecation notice of a 5.x handler.
	 *
	 * @param string $handler     The 5.x action or handler.
	 * @param string $replacement The REST route that replaces it.
	 */
	private static function deprecated( string $handler, string $replacement ): void {
		_deprecated_hook( esc_html( $handler ), '6.0.0', esc_html( 'zero-spam/v1/' . $replacement ), esc_html__( 'It is removed in Zero Spam 6.1.0.', 'zero-spam' ) );
	}

	/**
	 * A POST field as text (unslashed, not sanitized: the REST route validates it).
	 *
	 * @param string $name Field.
	 */
	private static function post( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each handler checks the nonce first; the REST route validates the value.
		return isset( $_POST[ $name ] ) && is_scalar( $_POST[ $name ] ) ? trim( (string) wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * Sends a REST answer as the 5.x AJAX answer and ends the request.
	 *
	 * @param WP_REST_Response $response REST answer.
	 * @param string           $success  Message on success.
	 */
	private static function send( WP_REST_Response $response, string $success ): void {
		$data = $response->get_data();

		if ( $response->is_error() ) {
			$error = $response->as_error();

			wp_send_json_error(
				array(
					'message' => $error->get_error_message(),
					'code'    => $error->get_error_code(),
					'params'  => $error->get_error_data()['params'] ?? null,
				),
				$response->get_status()
			);
		}

		wp_send_json_success( array_merge( array( 'message' => $success ), is_array( $data ) ? $data : array( 'data' => $data ) ) );
	}

	/**
	 * The 5.x network settings screen's AJAX actions.
	 */
	public function network_ajax(): void {
		$action = (string) current_action();
		$action = str_starts_with( $action, 'wp_ajax_' ) ? substr( $action, 8 ) : $action;
		$method = self::NETWORK_AJAX[ $action ] ?? '';

		check_ajax_referer( self::NETWORK_NONCE, 'nonce' );

		if ( '' === $method || ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ), 403 );
		}

		$this->{$method}( $action );
	}

	/**
	 * `zerospam_network_set_setting`: PATCH settings?scope=network (which checks
	 * the value as the 5.x screen did, Trusted Proxy IPs included, and keeps
	 * the lock), then POST network-settings/{key}/lock when `locked` is 1.
	 *
	 * @param string $action AJAX action.
	 */
	private function set_setting( string $action ): void {
		$key = sanitize_key( self::post( 'key' ) );
		self::deprecated( $action, 'settings?scope=network' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in network_ajax(); a multi-select sends a list, validated by the route.
		$value = isset( $_POST['value'] ) && is_array( $_POST['value'] ) ? array_map( 'strval', wp_unslash( $_POST['value'] ) ) : self::post( 'value' );

		if ( '' === $key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting key', 'zero-spam' ) ), 400 );
		}

		$response = self::dispatch( 'PATCH', 'settings', array( $key => $value ), array( 'scope' => 'network' ) );

		if ( ! $response->is_error() && '1' === self::post( 'locked' ) ) {
			$response = self::dispatch( 'POST', 'network-settings/' . $key . '/lock' );
		}

		self::send( $response, __( 'Setting saved successfully!', 'zero-spam' ) );
	}

	/**
	 * `zerospam_network_lock_setting`: POST network-settings/{key}/lock.
	 *
	 * @param string $action AJAX action.
	 */
	private function lock_setting( string $action ): void {
		$key = sanitize_key( self::post( 'key' ) );
		self::deprecated( $action, 'network-settings/' . $key . '/lock' );
		self::send( self::dispatch( 'POST', 'network-settings/' . $key . '/lock' ), __( 'Setting locked', 'zero-spam' ) );
	}

	/**
	 * `zerospam_network_unlock_setting`: POST network-settings/{key}/unlock.
	 *
	 * @param string $action AJAX action.
	 */
	private function unlock_setting( string $action ): void {
		$key = sanitize_key( self::post( 'key' ) );
		self::deprecated( $action, 'network-settings/' . $key . '/unlock' );
		self::send( self::dispatch( 'POST', 'network-settings/' . $key . '/unlock' ), __( 'Setting unlocked', 'zero-spam' ) );
	}

	/**
	 * `zerospam_network_apply_all`: POST network-settings/apply-all.
	 *
	 * @param string $action AJAX action.
	 */
	private function apply_all( string $action ): void {
		self::deprecated( $action, 'network-settings/apply-all' );

		$mode = self::post( 'mode' );

		self::send(
			self::dispatch(
				'POST',
				'network-settings/apply-all',
				array(
					'force' => '1' === self::post( 'force' ),
					'mode'  => '' !== $mode ? $mode : 'all',
				)
			),
			__( 'Settings applied.', 'zero-spam' )
		);
	}

	/**
	 * `zerospam_network_reset_site`: POST network-settings/site/{id}/reset.
	 *
	 * @param string $action AJAX action.
	 */
	private function reset_site( string $action ): void {
		$site_id = absint( self::post( 'site_id' ) );
		self::deprecated( $action, 'network-settings/site/' . $site_id . '/reset' );
		self::send( self::dispatch( 'POST', 'network-settings/site/' . $site_id . '/reset' ), __( 'Site reset to network defaults', 'zero-spam' ) );
	}

	/**
	 * `zerospam_network_get_comparison`: GET network/comparison.
	 *
	 * @param string $action AJAX action.
	 */
	private function get_comparison( string $action ): void {
		self::deprecated( $action, 'network/comparison' );
		self::send( self::dispatch( 'GET', 'network/comparison', array( 'per_page' => 100 ) ), '' );
	}

	/**
	 * `zerospam_network_export`: GET network-settings/export (secrets masked).
	 *
	 * @param string $action AJAX action.
	 */
	private function export( string $action ): void {
		self::deprecated( $action, 'network-settings/export' );

		$response = self::dispatch( 'GET', 'network-settings/export' );

		if ( ! $response->is_error() ) {
			$response->set_data( array( 'json' => (string) wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT ) ) );
		}

		self::send( $response, '' );
	}

	/**
	 * `zerospam_network_import`: POST network-settings/import.
	 *
	 * @param string $action AJAX action.
	 */
	private function import( string $action ): void {
		self::deprecated( $action, 'network-settings/import' );

		$data = json_decode( self::post( 'json' ), true );

		if ( ! is_array( $data ) || ! $data ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON format. Please check your file.', 'zero-spam' ) ), 400 );
		}

		$mode = self::post( 'mode' );

		self::send(
			self::dispatch(
				'POST',
				'network-settings/import',
				array(
					'settings' => $data,
					'mode'     => in_array( $mode, array( 'merge', 'replace', 'add_only' ), true ) ? $mode : 'merge',
				)
			),
			__( 'Settings imported.', 'zero-spam' )
		);
	}

	/**
	 * `zerospam_network_apply_template`: POST network-settings/templates/{slug}/apply.
	 *
	 * @param string $action AJAX action.
	 */
	private function apply_template( string $action ): void {
		$slug = sanitize_key( self::post( 'slug' ) );
		self::deprecated( $action, 'network-settings/templates/' . $slug . '/apply' );

		self::send(
			self::dispatch(
				'POST',
				'network-settings/templates/' . $slug . '/apply',
				array(
					'scope' => 'network' === self::post( 'scope' ) || '' === self::post( 'scope' ) ? 'network' : 'sites',
					'lock'  => '1' === self::post( 'lock' ),
				)
			),
			__( 'Template applied', 'zero-spam' )
		);
	}

	/**
	 * `zerospam_network_save_template`: POST network-settings/templates.
	 *
	 * @param string $action AJAX action.
	 */
	private function save_template( string $action ): void {
		self::deprecated( $action, 'network-settings/templates' );

		self::send(
			self::dispatch(
				'POST',
				'network-settings/templates',
				array(
					'name'        => self::post( 'name' ),
					'slug'        => sanitize_title( self::post( 'slug' ) ),
					'description' => self::post( 'description' ),
				)
			),
			__( 'Template saved successfully', 'zero-spam' )
		);
	}

	/**
	 * `zerospam_network_delete_template`: DELETE network-settings/templates/{slug}.
	 *
	 * @param string $action AJAX action.
	 */
	private function delete_template( string $action ): void {
		$slug = sanitize_key( self::post( 'slug' ) );
		self::deprecated( $action, 'network-settings/templates/' . $slug );
		self::send( self::dispatch( 'DELETE', 'network-settings/templates/' . $slug ), __( 'Template deleted successfully', 'zero-spam' ) );
	}

	/**
	 * `zerospam_network_save_notification_settings`: PUT network/notifications.
	 *
	 * @param string $action AJAX action.
	 */
	private function save_notifications( string $action ): void {
		self::deprecated( $action, 'network/notifications' );

		self::send(
			self::dispatch(
				'PUT',
				'network/notifications',
				array(
					'weekly'  => '1' === self::post( 'weekly_enabled' ),
					'changes' => '1' === self::post( 'settings_enabled' ),
				)
			),
			__( 'Notification settings saved.', 'zero-spam' )
		);
	}

	/**
	 * `zerospam_network_toggle_notifications`: PUT network/notifications (weekly).
	 *
	 * @param string $action AJAX action.
	 */
	private function toggle_notifications( string $action ): void {
		self::deprecated( $action, 'network/notifications' );
		self::send( self::dispatch( 'PUT', 'network/notifications', array( 'weekly' => '1' === self::post( 'enabled' ) ) ), __( 'Notification settings saved.', 'zero-spam' ) );
	}

	/**
	 * `zerospam_export_network_stats`: the 5.x CSV rows, from GET network/stats
	 * (this network's sites only; up to 100).
	 */
	public function export_network_stats(): void {
		check_ajax_referer( self::STATS_NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'zero-spam' ) ), 403 );
		}

		self::deprecated( 'zerospam_export_network_stats', 'network/stats' );

		$period   = self::PERIODS[ sanitize_key( self::post( 'period' ) ) ] ?? '30';
		$response = self::dispatch(
			'GET',
			'network/stats',
			array(
				'period'   => $period,
				'per_page' => 100,
			)
		);

		if ( $response->is_error() ) {
			self::send( $response, '' );
		}

		$data = (array) $response->get_data();
		$rows = array( array( 'Site Name', 'Site URL', 'Spam Count', 'Unique IPs', 'Top Type', 'Top Country', 'Trend %', 'Protection' ) );

		foreach ( (array) ( $data['sites'] ?? array() ) as $site ) {
			$site   = (array) $site;
			$rows[] = array(
				(string) ( $site['name'] ?? '' ),
				(string) ( $site['url'] ?? '' ),
				(int) ( $site['blocked'] ?? 0 ),
				(int) ( $site['unique_ips'] ?? 0 ),
				(string) ( $site['top_type'] ?? '' ),
				(string) ( $site['top_country'] ?? '' ),
				$site['trend'] ?? '',
				(string) ( $site['level'] ?? '' ),
			);
		}

		wp_send_json_success(
			array(
				'csv'      => $rows,
				'filename' => 'zero-spam-network-stats-' . gmdate( 'Y-m-d' ) . '.csv',
			)
		);
	}

	/**
	 * Ends a 5.x form handler: back to the 6.0 page that replaced its screen.
	 *
	 * @param string $view Utilities::admin_link() view.
	 * @return never
	 */
	private static function back( string $view ): never {
		wp_safe_redirect( Utilities::admin_link( $view ) );
		exit;
	}

	/**
	 * Refuses a 5.x form handler's request without the capability.
	 *
	 * @return never
	 */
	private static function forbidden(): never {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'zero-spam' ), esc_html__( 'Forbidden', 'zero-spam' ), array( 'response' => 403 ) );
	}

	/**
	 * `admin.php?action=add_blocked_ip` (the 5.x block form): POST rules/blocks.
	 */
	public function add_blocked_ip(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		$location = '' !== self::post( 'key_type' );

		if ( ! wp_verify_nonce( self::post( 'zerospam' ), 'zerospam' ) ) {
			self::back( 'activity' );
		}

		self::deprecated( 'admin_action_add_blocked_ip', 'rules/blocks' );

		$body = array(
			'kind'   => $location ? 'location' : 'ip',
			'type'   => self::post( 'blocked_type' ),
			'reason' => self::post( 'blocked_reason' ),
		);

		if ( $location ) {
			$body['key_type'] = self::post( 'key_type' );
			$body['key']      = self::post( 'blocked_key' );
		} else {
			$body['ip'] = self::post( 'blocked_ip' );
		}

		// 5.x sent site-local dates (datetime-local); the route reads a time without an offset in the site's time zone.
		foreach ( array(
			'start' => 'blocked_start_date',
			'end'   => 'blocked_end_date',
		) as $param => $field ) {
			if ( '' !== self::post( $field ) ) {
				$body[ $param ] = self::post( $field );
			}
		}

		self::dispatch( 'POST', 'rules/blocks', $body );

		wp_safe_redirect( admin_url( 'admin.php?page=zero-spam-rules&tab=' . ( $location ? 'locations' : 'ips' ) ) );
		exit;
	}

	/**
	 * `admin.php?action=import_settings` (the 5.x import form): POST settings/import.
	 */
	public function import_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		if ( wp_verify_nonce( self::post( 'zerospam' ), 'import_settings' ) ) {
			self::deprecated( 'admin_action_import_settings', 'settings/import' );

			$request = new WP_REST_Request( 'POST', '/zero-spam/v1/settings/import' );
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( self::post( 'settings' ) );
			// The 5.x import stored every value it was given, keys included.
			$request->set_query_params( array( 'include_secrets' => true ) );
			rest_do_request( $request );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zero-spam-settings&tab=io' ) );
		exit;
	}

	/**
	 * `admin-post.php?action=zerospam_refresh_htaccess`: POST settings/actions/refresh-htaccess.
	 */
	public function refresh_htaccess(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		check_admin_referer( 'zerospam-refresh-htaccess' );
		self::deprecated( 'admin_post_zerospam_refresh_htaccess', 'settings/actions/refresh-htaccess' );
		self::dispatch( 'POST', 'settings/actions/refresh-htaccess' );

		wp_safe_redirect( admin_url( 'admin.php?page=zero-spam-rules&tab=ips' ) );
		exit;
	}

	/**
	 * `admin-post.php?action=zerospam_remove_admin_safe_ip`: DELETE rules/no-lockout/{ip}.
	 */
	public function remove_admin_safe_ip(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked by check_admin_referer() below, with the address in the nonce action as 5.x did.
		$ip = isset( $_GET['ip'] ) && is_string( $_GET['ip'] ) ? sanitize_text_field( wp_unslash( $_GET['ip'] ) ) : '';
		check_admin_referer( 'zerospam_remove_admin_safe_ip|' . $ip );

		self::deprecated( 'admin_post_zerospam_remove_admin_safe_ip', 'rules/no-lockout/{ip}' );

		if ( Admin_Safe_IPs::contains( $ip ) ) {
			self::dispatch( 'DELETE', 'rules/no-lockout/' . rawurlencode( $ip ), array( 'confirm' => true ) );
		}

		self::back( 'no-lockout' );
	}
}
