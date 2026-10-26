<?php
/**
 * Tests for zero-spam/v1/settings with every setting of the registry.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * Every setting that holds a value can be read and written, values are
 * checked against the registry, secrets are write-only, and the original
 * behaviour of the route is unchanged.
 *
 * @covers \ZeroSpam\Includes\Rest\Settings_Controller
 */
class Test_Rest_Settings extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/settings';

	/**
	 * A license key.
	 */
	const LICENSE = 'live-key-QWERTY-c3d4';

	/**
	 * Logs in an administrator and clears the settings the tests write.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'zerospam', 'settings', 'comments', 'api-monitoring', 'ipstack', 'security', 'davidwalsh' ) as $module ) {
			delete_option( "zero-spam-{$module}" );
		}
		delete_option( 'zerospam_blocked_email_domains' );
		delete_option( 'zerospam_settings_audit' );

		$this->as_role( 'administrator' );
	}

	/**
	 * The original keys keep working as before.
	 */
	public function test_original_keys_unchanged(): void {
		$response = $this->rest( 'PATCH', self::ROUTE, array( 'zerospam_confidence_min' => 45 ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 45.0, get_option( 'zero-spam-zerospam' )['zerospam_confidence_min'] );
		$this->assertSame( 45.0, $response->get_data()['settings']['zerospam_confidence_min'] );

		$response = $this->rest( 'GET', self::ROUTE );
		$this->assertFalse( $response->get_data()['settings']['zerospam'], 'The default of zerospam is still false.' );
	}

	/**
	 * Settings of any module are written to their own module option.
	 */
	public function test_patch_writes_every_module(): void {
		$response = $this->rest(
			'PATCH',
			self::ROUTE,
			array(
				'max_logs'             => '2500',
				'block_handler'        => '403',
				'widget_visibility'    => array( 'administrator', 'editor' ),
				'log_blocked_comments' => 'enabled',
				'quota_reset_date'     => '2026-12-01',
			)
		);

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		$settings = get_option( 'zero-spam-settings' );
		$this->assertSame( 2500, $settings['max_logs'] );
		$this->assertSame( 403, $settings['block_handler'], 'The registry choice keeps its type.' );
		$this->assertSame( array( 'administrator', 'editor' ), $settings['widget_visibility'] );
		$this->assertSame( 'enabled', get_option( 'zero-spam-comments' )['log_blocked_comments'] );
		$this->assertSame( '2026-12-01', get_option( 'zero-spam-api-monitoring' )['quota_reset_date'] );
	}

	/**
	 * Unchecking a checkbox stores false.
	 */
	public function test_checkbox_off_stores_false(): void {
		$this->rest( 'PATCH', self::ROUTE, array( 'log_blocked_comments' => false ) );

		$this->assertFalse( get_option( 'zero-spam-comments' )['log_blocked_comments'] );
	}

	/**
	 * The blocked email domains stay in their own, non-autoloaded option.
	 */
	public function test_blocked_email_domains_use_their_own_option(): void {
		global $wpdb;

		$this->rest( 'PATCH', self::ROUTE, array( 'blocked_email_domains' => "spam.example\nmore.example" ) );

		$this->assertSame( "spam.example\nmore.example", get_option( 'zerospam_blocked_email_domains' ) );
		$this->assertArrayNotHasKey( 'blocked_email_domains', (array) get_option( 'zero-spam-settings', array() ) );
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'zerospam_blocked_email_domains' ) );
		$this->assertContains( $autoload, array( 'off', 'no' ) );
	}

	/**
	 * Invalid values are refused with one message per key, and nothing is written.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function data_invalid_values(): array {
		return array(
			'number below min'     => array( 'zerospam_confidence_min', -1 ),
			'number not numeric'   => array( 'max_logs', 'lots' ),
			'fraction for integer' => array( 'max_logs', 10.5 ),
			'number above max'     => array( 'api_retention', 400 ),
			'unknown choice'       => array( 'block_method', 'nginx' ),
			'checkbox junk'        => array( 'log_blocked_comments', 'yes' ),
			'multi select scalar'  => array( 'widget_visibility', 'administrator' ),
			'multi select junk'    => array( 'widget_visibility', array( 'pirate' ) ),
			'bad url'              => array( 'blocked_redirect_url', 'javascript:alert(1)' ),
			'bad date'             => array( 'quota_reset_date', '2026-02-30' ),
			'text as array'        => array( 'comment_spam_message', array( 'x' ) ),
			'bad trusted proxy'    => array( 'trusted_proxies', "203.0.113.5\nnot-an-ip" ),
			'unknown key'          => array( 'zs_no_such_setting', 'x' ),
			'html setting'         => array( 'rescue_mode', 'x' ),
		);
	}

	/**
	 * Each invalid value is refused.
	 *
	 * @dataProvider data_invalid_values
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 */
	public function test_invalid_values_are_refused( string $key, $value ): void {
		$response = $this->rest(
			'PATCH',
			self::ROUTE,
			array(
				$key                    => $value,
				'disallowed_min_length' => 3,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( $key, $data['data']['params'] );
		$this->assertSame( $data['data']['params'], $data['data']['errors'], 'The old errors list is kept.' );
		$this->assertFalse( get_option( 'zero-spam-settings' ), 'Nothing was written.' );
	}

	/**
	 * Values are sanitized as the registry type requires.
	 */
	public function test_values_are_sanitized(): void {
		$this->rest(
			'PATCH',
			self::ROUTE,
			array(
				'comment_spam_message' => '<script>x</script>Go away',
				'trusted_proxies'      => '203.0.113.5, 198.51.100.0/24',
				'zerospam_license'     => '  ' . self::LICENSE . '  ',
			)
		);

		$this->assertSame( 'Go away', get_option( 'zero-spam-comments' )['comment_spam_message'] );
		$this->assertSame( "203.0.113.5\n198.51.100.0/24", get_option( 'zero-spam-settings' )['trusted_proxies'] );
		$this->assertSame( self::LICENSE, get_option( 'zero-spam-zerospam' )['zerospam_license'] );
	}

	/**
	 * Secrets can be written but are only ever returned masked.
	 */
	public function test_secrets_are_write_only(): void {
		$response = $this->rest( 'PATCH', self::ROUTE, array( 'zerospam_license' => self::LICENSE ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::LICENSE, get_option( 'zero-spam-zerospam' )['zerospam_license'] );

		$masked = array(
			'set'    => true,
			'source' => 'site',
			'hint'   => "\u{2022}\u{2022}\u{2022}\u{2022}c3d4",
		);

		$this->assertSame( $masked, $response->get_data()['settings']['zerospam_license'] );
		$this->assertSame( $masked, $response->get_data()['changes']['zerospam_license']['new'] );

		foreach ( array( 'resolved', 'site' ) as $scope ) {
			$data = $this->rest( 'GET', self::ROUTE, array(), array( 'scope' => $scope ) )->get_data();
			$this->assertSame( $masked, $data['settings']['zerospam_license'], $scope );
			$this->assertStringNotContainsString( 'QWERTY', (string) wp_json_encode( $data ), "The key leaked in scope {$scope}." );
		}

		$this->assertStringNotContainsString( self::LICENSE, (string) wp_json_encode( get_option( 'zerospam_settings_audit' ) ), 'The key leaked into the audit log.' );
	}

	/**
	 * A dry run validates and masks but writes nothing.
	 */
	public function test_dry_run(): void {
		$response = $this->rest(
			'PATCH',
			self::ROUTE,
			array(
				'max_logs'    => 300,
				'ipstack_api' => 'ipstack-secret-9999',
			),
			array( 'dry_run' => true )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['dry_run'] );
		$this->assertSame( 300, $response->get_data()['settings']['max_logs'] );
		$this->assertTrue( $response->get_data()['settings']['ipstack_api']['set'] );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
		$this->assertFalse( get_option( 'zero-spam-ipstack' ) );
	}

	/**
	 * Resolved reads cover every setting, with their sources.
	 */
	public function test_resolved_with_sources_covers_all_modules(): void {
		$this->set_setting( 'max_logs', 900 );

		$data = $this->rest( 'GET', self::ROUTE, array(), array( 'include' => 'sources' ) )->get_data();

		$this->assertSame( 900, $data['settings']['max_logs'] );
		$this->assertSame( 'site', $data['sources']['max_logs'] );
		$this->assertSame( 'default', $data['sources']['zerospam_confidence_min'] );
		$this->assertArrayHasKey( 'verify_comments', $data['settings'] );
		$this->assertArrayNotHasKey( 'rescue_mode', $data['settings'], 'html settings hold no value.' );
	}

	/**
	 * The whitelist filter still restricts what the route accepts.
	 */
	public function test_whitelist_filter_is_applied(): void {
		add_filter(
			'zero_spam_rest_settings_whitelist',
			static function ( array $whitelist ): array {
				unset( $whitelist['max_logs'] );

				return $whitelist;
			}
		);

		$response = $this->rest( 'PATCH', self::ROUTE, array( 'max_logs' => 5 ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayNotHasKey( 'max_logs', $this->rest( 'GET', self::ROUTE )->get_data()['settings'] );
	}

	/**
	 * Network scope is refused on a single site, whatever the client says.
	 *
	 * @group ms-excluded
	 */
	public function test_network_scope_on_single_site(): void {
		$get   = $this->rest( 'GET', self::ROUTE, array(), array( 'scope' => 'network' ) );
		$patch = $this->rest( 'PATCH', self::ROUTE, array( 'max_logs' => 5 ), array( 'scope' => 'network' ) );

		$this->assertContains( $get->get_status(), array( 400, 403 ) );
		$this->assertContains( $patch->get_status(), array( 400, 403 ) );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );
	}

	/**
	 * Who may read and write settings, per method.
	 *
	 * @return array<string, array{0: string|null, 1: int}>
	 */
	public static function data_permissions(): array {
		return array(
			'anonymous'     => array( null, 401 ),
			'subscriber'    => array( 'subscriber', 403 ),
			'editor'        => array( 'editor', 403 ),
			'administrator' => array( 'administrator', 200 ),
		);
	}

	/**
	 * Only administrators can read or write; nobody else gets any value.
	 *
	 * @dataProvider data_permissions
	 *
	 * @param string|null $role     Role, or null for a logged-out visitor.
	 * @param int         $expected HTTP status.
	 */
	public function test_permissions( ?string $role, int $expected ): void {
		$this->set_setting( 'max_logs', 1234 );

		if ( null === $role ) {
			wp_set_current_user( 0 );
		} else {
			$this->as_role( $role );
		}

		$get   = $this->rest( 'GET', self::ROUTE );
		$patch = $this->rest( 'PATCH', self::ROUTE, array( 'max_logs' => 99 ) );

		$this->assertSame( $expected, $get->get_status() );
		$this->assertSame( $expected, $patch->get_status() );

		if ( 200 !== $expected ) {
			$this->assertStringNotContainsString( '1234', (string) wp_json_encode( $get->get_data() ) );
			$this->assertSame( 1234, get_option( 'zero-spam-settings' )['max_logs'] );
		}
	}

	/**
	 * A cookie-authenticated request without a valid REST nonce runs as a logged-out visitor.
	 */
	public function test_cookie_request_without_nonce_is_refused(): void {
		global $wp_rest_auth_cookie;

		$wp_rest_auth_cookie = true;
		unset( $_REQUEST['_wpnonce'], $_SERVER['HTTP_X_WP_NONCE'] );

		try {
			rest_cookie_check_errors( null );
			$response = $this->rest( 'GET', self::ROUTE );
		} finally {
			$wp_rest_auth_cookie = false;
		}

		$this->assertSame( 0, get_current_user_id() );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * With a valid REST nonce the same request is allowed.
	 */
	public function test_cookie_request_with_nonce_is_allowed(): void {
		global $wp_rest_auth_cookie;

		$user_id                    = get_current_user_id();
		$wp_rest_auth_cookie        = true;
		$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );

		try {
			$this->assertTrue( rest_cookie_check_errors( null ) );
			$response = $this->rest( 'GET', self::ROUTE );
		} finally {
			$wp_rest_auth_cookie = false;
		}

		$this->assertSame( $user_id, get_current_user_id() );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * A switch that is on unless turned off stores `disabled` when turned
	 * off (false, '' or `disabled`), and `enabled` when turned on.
	 */
	public function test_default_on_switch_stores_disabled(): void {
		foreach ( array( false, '', 'disabled' ) as $off ) {
			$response = $this->rest( 'PATCH', self::ROUTE, array( 'disable_xmlrpc' => $off ) );

			$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
			$this->assertSame( 'disabled', get_option( 'zero-spam-security' )['disable_xmlrpc'], (string) wp_json_encode( $off ) );
			$this->assertSame( 'disabled', $response->get_data()['settings']['disable_xmlrpc'] );
		}

		$this->rest( 'PATCH', self::ROUTE, array( 'disable_xmlrpc' => true ) );
		$this->assertSame( 'enabled', get_option( 'zero-spam-security' )['disable_xmlrpc'] );
	}

	/**
	 * Turning a switch off through the route turns its behaviour off.
	 */
	public function test_switch_off_through_the_route_enables_xmlrpc(): void {
		$this->rest( 'PATCH', self::ROUTE, array( 'disable_xmlrpc' => false ) );
		self::reset_static_caches();
		Test_Security::restore_core_hooks();

		( new \ZeroSpam\Modules\Security\Security() )->init();

		$this->assertTrue( (bool) apply_filters( 'xmlrpc_enabled', true ) );
	}

	/**
	 * An ordinary switch still stores false, and `disabled` is accepted as off.
	 */
	public function test_off_by_default_switch_still_stores_false(): void {
		$this->rest( 'PATCH', self::ROUTE, array( 'verify_comments' => 'disabled' ) );

		$this->assertFalse( get_option( 'zero-spam-comments' )['verify_comments'] );
	}

	/**
	 * Custom Form Selectors is a text setting: the route reads the stored
	 * value as it is and writes plain text.
	 */
	public function test_form_selectors_are_text(): void {
		update_option( 'zero-spam-davidwalsh', array( 'davidwalsh_form_selectors' => '.legacy-form, #old' ) );
		self::reset_static_caches();

		$this->assertSame( '.legacy-form, #old', $this->rest( 'GET', self::ROUTE )->get_data()['settings']['davidwalsh_form_selectors'] );

		$response = $this->rest( 'PATCH', self::ROUTE, array( 'davidwalsh_form_selectors' => '.my-form, #signup<script>' ) );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( '.my-form, #signup', get_option( 'zero-spam-davidwalsh' )['davidwalsh_form_selectors'] );
		self::reset_static_caches();
		$this->assertContains( '#signup', \ZeroSpam\Modules\DavidWalsh\DavidWalsh::get_all_selectors() );
	}

	/**
	 * A network can lock Custom Form Selectors: the site cannot change it,
	 * and the locked value is the one the forms use.
	 *
	 * @group ms-required
	 */
	public function test_form_selectors_can_be_locked_by_the_network(): void {
		update_option( 'zero-spam-davidwalsh', array( 'davidwalsh_form_selectors' => '.site-form' ) );
		$this->set_network_setting( 'davidwalsh_form_selectors', '.network-form', true );

		$response = $this->rest( 'PATCH', self::ROUTE, array( 'davidwalsh_form_selectors' => '.mine' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_setting_locked', $response->get_data()['code'] );
		$selectors = \ZeroSpam\Modules\DavidWalsh\DavidWalsh::get_all_selectors();
		$this->assertContains( '.network-form', $selectors );
		$this->assertNotContains( '.site-form', $selectors );
	}

	/**
	 * On a network, a site without its own value uses the network default
	 * selectors; its own value wins while the network value is unlocked.
	 *
	 * @group ms-required
	 */
	public function test_form_selectors_follow_the_network_default(): void {
		$this->set_network_setting( 'davidwalsh_form_selectors', '.network-form' );

		$this->assertContains( '.network-form', \ZeroSpam\Modules\DavidWalsh\DavidWalsh::get_all_selectors() );

		update_option( 'zero-spam-davidwalsh', array( 'davidwalsh_form_selectors' => '.site-form' ) );

		$selectors = \ZeroSpam\Modules\DavidWalsh\DavidWalsh::get_all_selectors();
		$this->assertContains( '.site-form', $selectors );
		$this->assertNotContains( '.network-form', $selectors );
	}

	/**
	 * The Google API key can be saved through the route (the Google module
	 * is loaded outside wp-admin too), and it stays write-only.
	 */
	public function test_google_key_is_a_writable_secret(): void {
		delete_option( 'zero-spam-google' );
		self::reset_static_caches();

		$response = $this->rest( 'PATCH', self::ROUTE, array( 'google_api' => 'AIza-test-key-9z8y' ) );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'AIza-test-key-9z8y', get_option( 'zero-spam-google' )['google_api'] );
		$this->assertTrue( $response->get_data()['settings']['google_api']['set'] );
		$this->assertStringNotContainsString( 'AIza-test-key', (string) wp_json_encode( $response->get_data() ) );
	}
}
