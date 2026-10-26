<?php
/**
 * Tests for zero-spam/v1/settings/actions/{action}.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * Each action does what the 5.x `zerospam-action` link or button does, for
 * administrators only, and says whether it worked.
 *
 * @covers \ZeroSpam\Includes\Rest\Settings_Actions_Controller
 * @covers \ZeroSpam\Core\Settings::auto_configure
 */
class Test_Rest_Settings_Actions extends TestCase {

	/**
	 * Route prefix.
	 */
	const ROUTE = '/zero-spam/v1/settings/actions/';

	/**
	 * Webhook URL used by the tests.
	 */
	const WEBHOOK = 'https://hooks.example.com/zero-spam';

	/**
	 * Logs in an administrator and clears what the actions write.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'settings', 'comments', 'api-monitoring' ) as $module ) {
			delete_option( "zero-spam-{$module}" );
		}
		delete_option( 'zerospam_blocked_email_domains' );
		delete_option( 'zerospam_api_notices' );
		update_option( 'disallowed_keys', '' );
		reset_phpmailer_instance();

		$this->as_role( 'administrator' );
	}

	/**
	 * Who may run an action.
	 *
	 * @dataProvider provide_roles
	 *
	 * @param string $role     Role, or '' for a logged-out visitor.
	 * @param int    $expected Expected status.
	 */
	public function test_permissions( string $role, int $expected ): void {
		$before = Utilities::get_honeypot();

		if ( '' === $role ) {
			wp_set_current_user( 0 );
		} else {
			$this->as_role( $role );
		}

		$response = $this->rest( 'POST', self::ROUTE . 'regenerate-honeypot' );

		$this->assertSame( $expected, $response->get_status() );
		if ( 200 !== $expected ) {
			$this->assertSame( $before, get_option( 'wpzerospam_honeypot' ), 'A refused action changed nothing.' );
		}
	}

	/**
	 * Roles and the status they get.
	 *
	 * @return array<string, array{0: string, 1: int}>
	 */
	public function provide_roles(): array {
		return array(
			'logged out'    => array( '', 401 ),
			'subscriber'    => array( 'subscriber', 403 ),
			'editor'        => array( 'editor', 403 ),
			'administrator' => array( 'administrator', 200 ),
		);
	}

	/**
	 * A cookie-authenticated request needs the REST nonce; without it the action runs as a logged-out visitor.
	 */
	public function test_cookie_request_needs_a_nonce(): void {
		global $wp_rest_auth_cookie;

		$before              = Utilities::get_honeypot();
		$user_id             = get_current_user_id();
		$wp_rest_auth_cookie = true;
		unset( $_REQUEST['_wpnonce'], $_SERVER['HTTP_X_WP_NONCE'] );

		try {
			rest_cookie_check_errors( null );
			$refused = $this->rest( 'POST', self::ROUTE . 'regenerate-honeypot' );

			wp_set_current_user( $user_id );
			$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
			$this->assertTrue( rest_cookie_check_errors( null ) );
			$allowed = $this->rest( 'POST', '/zero-spam/v1/self-test' );
		} finally {
			$wp_rest_auth_cookie = false;
			delete_transient( 'zerospam_self_test_lock' );
		}

		$this->assertSame( 401, $refused->get_status() );
		$this->assertSame( $before, get_option( 'wpzerospam_honeypot' ) );
		$this->assertSame( 200, $allowed->get_status() );
	}

	/**
	 * An unknown action has no route, and GET is not an action.
	 */
	public function test_unknown_action_and_method(): void {
		$this->assertSame( 'rest_no_route', $this->rest( 'POST', self::ROUTE . 'drop-tables' )->get_data()['code'] );
		$this->assertSame( 404, $this->rest( 'GET', self::ROUTE . 'autoconfigure' )->get_status() );
	}

	/**
	 * Regenerating the honeypot changes its ID.
	 */
	public function test_regenerate_honeypot(): void {
		update_option( 'wpzerospam_honeypot', 'old01' );

		$response = $this->rest( 'POST', self::ROUTE . 'regenerate-honeypot' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'regenerate-honeypot', $response->get_data()['action'] );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertNotSame( '', $response->get_data()['message'] );
		$this->assertNotSame( 'old01', get_option( 'wpzerospam_honeypot' ) );
	}

	/**
	 * The recommended blocked email domains are stored in their own, non-autoloaded option only.
	 */
	public function test_update_blocked_email_domains(): void {
		global $wpdb;

		update_option( 'zero-spam-settings', array( 'max_logs' => 4321 ) );

		$response = $this->rest( 'POST', self::ROUTE . 'update-blocked-email-domains' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThan( 100, $response->get_data()['count'] );
		$this->assertStringContainsString( "\n", (string) get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertContains(
			$wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'zerospam_blocked_email_domains' ) ),
			array( 'no', 'off' )
		);
		$this->assertSame( array( 'max_logs' => 4321 ), get_option( 'zero-spam-settings' ), 'The other settings are not rewritten.' );
	}

	/**
	 * Refresh .htaccess rewrites the deny rules in an .htaccess block mode,
	 * and explains when the block method is PHP or the file cannot be written.
	 */
	public function test_refresh_htaccess(): void {
		global $wpdb;

		$file = get_temp_dir() . 'zerospam-actions-' . wp_generate_password( 8, false ) . '.htaccess';
		file_put_contents( $file, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$use_file = static function () use ( &$file ) {
			return $file;
		};
		add_filter( 'zerospam_htaccess_file', $use_file );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . \ZeroSpam\Includes\DB::$tables['blocked'] ) );
		$this->block( '192.0.2.61' );

		$php = $this->rest( 'POST', self::ROUTE . 'refresh-htaccess' );
		$this->assertSame( 400, $php->get_status() );
		$this->assertSame( 'zerospam_htaccess_off', $php->get_data()['code'] );
		$this->assertStringNotContainsString( '192.0.2.61', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.

		$this->set_setting( 'block_method', 'htaccess_modern' );
		$done = $this->rest( 'POST', self::ROUTE . 'refresh-htaccess' );
		$this->assertSame( 200, $done->get_status(), (string) wp_json_encode( $done->get_data() ) );
		$this->assertSame( 'refresh-htaccess', $done->get_data()['action'] );
		$this->assertStringContainsString( 'Require not ip 192.0.2.61', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test file.

		wp_delete_file( $file );
		$file   = get_temp_dir() . 'zerospam-missing-dir-' . wp_generate_password( 8, false ) . '/.htaccess';
		$failed = $this->rest( 'POST', self::ROUTE . 'refresh-htaccess' );
		$this->assertSame( 500, $failed->get_status() );
		$this->assertSame( 'zerospam_action_failed', $failed->get_data()['code'] );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( 'POST', self::ROUTE . 'refresh-htaccess' )->get_status() );
	}

	/**
	 * On a network, Refresh .htaccess is refused on a subsite: the network
	 * shares one .htaccess, which a subsite would rewrite with only its own
	 * addresses (AGENTS.md §8).
	 *
	 * @group ms-required
	 */
	public function test_refresh_htaccess_is_refused_on_a_subsite(): void {
		$blog = self::factory()->blog->create();
		switch_to_blog( $blog );
		$this->as_role( 'administrator' );
		$this->set_setting( 'block_method', 'htaccess_modern' );
		$written = false;
		add_filter(
			'insert_with_markers_inline_instructions',
			static function ( $lines ) use ( &$written ) {
				$written = true;
				return $lines;
			}
		);

		$response = $this->rest( 'POST', self::ROUTE . 'refresh-htaccess' );
		restore_current_blog();

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'zerospam_htaccess_subsite', $response->get_data()['code'] );
		$this->assertFalse( $written, 'Nothing was written.' );
	}

	/**
	 * WordPress's disallowed words are replaced by the recommended list.
	 */
	public function test_update_disallowed_words(): void {
		$response = $this->rest( 'POST', self::ROUTE . 'update-disallowed-words' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThan( 100, count( explode( "\n", (string) get_option( 'disallowed_keys' ) ) ) );
	}

	/**
	 * When the recommended list cannot be read the action fails, even if the
	 * site already has disallowed words (they are left as they are).
	 */
	public function test_update_disallowed_words_reports_an_unreadable_list(): void {
		global $wp_filesystem;

		update_option( 'disallowed_keys', "existing\nwords" );
		$previous      = $wp_filesystem;
		$wp_filesystem = new class() {
			/**
			 * Reads nothing.
			 *
			 * @param string $file File.
			 */
			public function get_contents( $file ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature of WP_Filesystem_Base::get_contents().
				return false;
			}
		};

		try {
			$response = $this->rest( 'POST', self::ROUTE . 'update-disallowed-words' );
		} finally {
			$wp_filesystem = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the test's own replacement.
		}

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'zerospam_action_failed', $response->get_data()['code'] );
		$this->assertSame( "existing\nwords", get_option( 'disallowed_keys' ) );
	}

	/**
	 * The error log is deleted.
	 */
	public function test_clear_error_log(): void {
		$file = wp_upload_dir()['basedir'] . '/zerospam.log';
		file_put_contents( $file, "error\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Seeds a fixture file.

		$response = $this->rest( 'POST', self::ROUTE . 'clear-error-log' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Autoconfigure stores the recommended values.
	 */
	public function test_autoconfigure_stores_recommended_values(): void {
		update_option( 'zero-spam-comments', array( 'verify_comments' => false ) );

		$response = $this->rest( 'POST', self::ROUTE . 'autoconfigure' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data()['skipped_locked'] );
		$this->assertSame( 'enabled', get_option( 'zero-spam-comments' )['verify_comments'] );
		$this->assertSame( '1', (string) get_option( 'zerospam_configured' ) );

		$audit = get_option( 'zerospam_settings_audit' );
		$this->assertIsArray( $audit, 'Autoconfigure writes an audit entry.' );
		$this->assertSame( get_current_user_id(), $audit[0]['user_id'] );
		$this->assertSame(
			array(
				'old' => false,
				'new' => 'enabled',
			),
			$audit[0]['changes']['verify_comments']
		);
	}

	/**
	 * Settings::auto_configure() leaves skipped keys as they are stored.
	 */
	public function test_auto_configure_skip_keys_keep_stored_values(): void {
		update_option(
			'zero-spam-comments',
			array(
				'verify_comments'      => false,
				'log_blocked_comments' => false,
			)
		);

		\ZeroSpam\Core\Settings::auto_configure( array( 'verify_comments', 'comment_spam_message' ) );

		$stored = get_option( 'zero-spam-comments' );
		$this->assertFalse( $stored['verify_comments'], 'A skipped key keeps its stored value.' );
		$this->assertArrayNotHasKey( 'comment_spam_message', $stored, 'A skipped key that was not stored is not added.' );
		$this->assertSame( 'enabled', $stored['log_blocked_comments'] );
	}

	/**
	 * The test email goes to the site administrator.
	 */
	public function test_test_alert_email(): void {
		$response = $this->rest( 'POST', self::ROUTE . 'test-alert-email' );

		$this->assertSame( 200, $response->get_status() );
		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertNotFalse( $mail );
		$this->assertSame( get_option( 'admin_email' ), $mail->to[0][0] );
	}

	/**
	 * A test email that cannot be sent is reported.
	 */
	public function test_test_alert_email_failure(): void {
		$fail = static function () {
			do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'No mail server.' ) );

			return false;
		};
		add_filter( 'pre_wp_mail', $fail );

		$response = $this->rest( 'POST', self::ROUTE . 'test-alert-email' );

		remove_filter( 'pre_wp_mail', $fail );
		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'zerospam_action_failed', $response->get_data()['code'] );
	}

	/**
	 * The test notice is stored for the next page load.
	 */
	public function test_test_alert_notice(): void {
		$response = $this->rest( 'POST', self::ROUTE . 'test-alert-notice' );

		$this->assertSame( 200, $response->get_status() );
		$notices = get_option( 'zerospam_api_notices' );
		$this->assertCount( 1, $notices );
		$this->assertSame( 'quota_warning', $notices[0]['type'] );
	}

	/**
	 * The test webhook is posted to the configured URL; a failure or a missing URL is reported.
	 */
	public function test_test_alert_webhook(): void {
		$this->assertSame( 'zerospam_webhook_missing', $this->rest( 'POST', self::ROUTE . 'test-alert-webhook' )->get_data()['code'] );

		$this->set_setting( 'alert_webhook_url', self::WEBHOOK );
		$this->mock_http( '#^https://hooks\.example\.com/zero-spam$#', array( 'ok' => true ) );

		$response = $this->rest( 'POST', self::ROUTE . 'test-alert-webhook' );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 1, \ZeroSpam\Tests\HttpMock::hits( '#^https://hooks\.example\.com/zero-spam$#' ) );

		$this->mock_http( '#^https://hooks\.example\.com/zero-spam$#', 'down', 500 );
		$failed = $this->rest( 'POST', self::ROUTE . 'test-alert-webhook' );

		$this->assertSame( 502, $failed->get_status() );
		$this->assertSame( 'zerospam_action_failed', $failed->get_data()['code'] );
	}

	/**
	 * Webhooks go through wp_safe_remote_post(): an address on a private
	 * network is refused (so the test action cannot probe internal hosts) and
	 * reported as failed; a public address is posted to.
	 *
	 * The HTTP mock answers before WordPress validates the address, so the
	 * check WordPress makes for safe requests is applied right after it, and
	 * the mock only counts as contacted when that check lets the URL through.
	 */
	public function test_webhook_to_a_private_address_is_refused(): void {
		$sent = array();
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$sent ) {
				$sent[] = $url;

				if ( ! empty( $args['reject_unsafe_urls'] ) && false === wp_http_validate_url( $url ) ) {
					return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
				}

				return $preempt;
			},
			2,
			3
		);
		$this->mock_http( '#/hook$#', array( 'ok' => true ) );

		$this->set_setting( 'alert_webhook_url', 'https://10.0.0.5/hook' );
		$refused = $this->rest( 'POST', self::ROUTE . 'test-alert-webhook' );

		$this->assertSame( 502, $refused->get_status() );
		$this->assertSame( 'zerospam_action_failed', $refused->get_data()['code'] );

		// A public address (nothing leaves the test: the mock answers).
		$this->set_setting( 'alert_webhook_url', 'https://8.8.8.8/hook' );
		$public = $this->rest( 'POST', self::ROUTE . 'test-alert-webhook' );
		$this->assertSame( 200, $public->get_status(), (string) wp_json_encode( $public->get_data() ) );

		// An internal host the site owner allows with WordPress's own filter.
		add_filter(
			'http_request_host_is_external',
			static function ( $external, $host ) {
				return '10.0.0.5' === $host ? true : $external;
			},
			10,
			2
		);
		$this->set_setting( 'alert_webhook_url', 'https://10.0.0.5/hook' );
		$this->assertSame( 200, $this->rest( 'POST', self::ROUTE . 'test-alert-webhook' )->get_status() );

		$this->assertSame( array( 'https://10.0.0.5/hook', 'https://8.8.8.8/hook', 'https://10.0.0.5/hook' ), $sent );
	}
}
