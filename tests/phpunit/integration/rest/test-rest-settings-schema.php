<?php
/**
 * Tests for the settings schema and the zero-spam/v1/settings/schema route.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Tests\TestCase;

/**
 * The schema describes every setting of the registry once, with valid
 * types, the secrets flagged and masked, and is cheap to build.
 *
 * @covers \ZeroSpam\Core\Settings_Schema
 * @covers \ZeroSpam\Includes\Rest\Settings_Controller::get_settings_schema
 */
class Test_Rest_Settings_Schema extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/settings/schema';

	/**
	 * Name of the constant the constant-source tests use.
	 */
	const TEST_CONSTANT = 'ZEROSPAM_TESTS_SCHEMA_LICENSE';

	/**
	 * Settings_Schema::$constants before the test.
	 *
	 * @var array<string, string>
	 */
	private array $constants_backup = array();

	/**
	 * Logs in an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->constants_backup = ( new ReflectionProperty( Settings_Schema::class, 'constants' ) )->getValue();
		delete_option( 'zero-spam-zerospam' );
		$this->as_role( 'administrator' );
	}

	/**
	 * Restores the constants map.
	 */
	public function tear_down(): void {
		self::set_static( Settings_Schema::class, 'constants', $this->constants_backup );

		parent::tear_down();
	}

	/**
	 * Schema entries by key.
	 *
	 * @param array<int, array<string, mixed>> $settings Setting entries.
	 * @return array<string, array<string, mixed>>
	 */
	private static function by_key( array $settings ): array {
		return array_column( $settings, null, 'key' );
	}

	/**
	 * Every valid registry setting appears exactly once, in registry order.
	 */
	public function test_every_registry_setting_appears_exactly_once(): void {
		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 200, $response->get_status() );

		$keys     = array_column( $response->get_data()['settings'], 'key' );
		$registry = array();
		foreach ( Settings::get_settings() as $key => $setting ) {
			if ( Settings::is_valid_setting( (string) $key, $setting ) ) {
				$registry[] = (string) $key;
			}
		}

		$this->assertSame( $registry, $keys );
		$this->assertSame( $keys, array_values( array_unique( $keys ) ), 'A setting appears twice.' );
		// Integration modules are only registered while their plugin is active.
		$this->assertGreaterThan( 75, count( $keys ) );
	}

	/**
	 * Every entry has the documented fields, a known type and coherent flags.
	 */
	public function test_entries_are_well_formed(): void {
		$fields = array( 'key', 'option', 'module', 'section', 'title', 'description', 'type', 'multiple', 'options', 'min', 'max', 'step', 'default', 'recommended', 'placeholder', 'lockable', 'secret', 'depends_on', 'area', 'group', 'order', 'locked', 'source', 'value', 'html' );

		foreach ( Settings_Schema::get()['settings'] as $entry ) {
			$this->assertSame( $fields, array_keys( $entry ), $entry['key'] );
			$this->assertContains( $entry['type'], Settings_Schema::TYPES, $entry['key'] );
			$this->assertSame( 'html' !== $entry['type'], $entry['lockable'], "{$entry['key']}: only html settings cannot be locked." );
			$this->assertContains( $entry['area'], array( 'protection', 'rules', 'settings' ), $entry['key'] );
			$this->assertNotSame( '', $entry['group'], $entry['key'] );
			$this->assertContains( $entry['source'], array( 'locked', 'site', 'network', 'constant', 'default' ), $entry['key'] );

			if ( in_array( $entry['type'], array( 'checkbox', 'radio', 'select' ), true ) ) {
				$this->assertIsArray( $entry['options'], "{$entry['key']} has no choices." );
			}

			$this->assertIsString( $entry['placeholder'], $entry['key'] );
			if ( 'html' === $entry['type'] ) {
				$this->assertIsString( $entry['html'], $entry['key'] );
			} else {
				$this->assertNull( $entry['html'], $entry['key'] );
			}
		}
	}

	/**
	 * Exactly the listed secrets are flagged; their values and defaults are never shown.
	 */
	public function test_secrets_are_flagged_and_masked(): void {
		$this->set_setting( 'zerospam_license', 'abcd-efgh-1234-a1b2' );

		$secrets = array();
		foreach ( Settings_Schema::get()['settings'] as $entry ) {
			if ( $entry['secret'] ) {
				$secrets[] = $entry['key'];
				$this->assertIsArray( $entry['value'], $entry['key'] );
				$this->assertNull( $entry['default'], $entry['key'] );
				$this->assertNull( $entry['recommended'], $entry['key'] );
			}
		}

		// Every listed secret is registered (the Google module is loaded outside wp-admin too since 6.0.0).
		$expected = Settings_Schema::SECRET_KEYS;
		sort( $secrets );
		sort( $expected );
		$this->assertSame( $expected, $secrets );
		$this->assertCount( 7, $secrets );
		$this->assertContains( 'alert_webhook_url', $secrets, 'Webhook URLs work as credentials.' );

		$license = self::by_key( Settings_Schema::get()['settings'] )['zerospam_license'];
		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'site',
				'hint'   => "\u{2022}\u{2022}\u{2022}\u{2022}a1b2",
			),
			$license['value']
		);

		$body = (string) wp_json_encode( $this->rest( 'GET', self::ROUTE )->get_data() );
		$this->assertStringNotContainsString( 'abcd-efgh-1234', $body, 'The secret leaked into the schema response.' );
	}

	/**
	 * An unset secret is reported as not set, with no hint.
	 */
	public function test_unset_secret(): void {
		$this->assertSame(
			array(
				'set'    => false,
				'source' => 'default',
			),
			Settings_Schema::masked( 'ipstack_api' )
		);
	}

	/**
	 * A license from the constant is reported with its source (and on a single site its hint).
	 *
	 * @group ms-excluded
	 */
	public function test_constant_provided_secret_on_a_single_site(): void {
		$this->use_test_constant();

		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'constant',
				'hint'   => "\u{2022}\u{2022}\u{2022}\u{2022}9z9z",
			),
			Settings_Schema::masked( 'zerospam_license' )
		);
	}

	/**
	 * On a network site a constant-provided key gets no hint.
	 *
	 * @group ms-required
	 */
	public function test_constant_provided_secret_on_a_network_site_has_no_hint(): void {
		$this->use_test_constant();

		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'constant',
			),
			Settings_Schema::masked( 'zerospam_license' )
		);
	}

	/**
	 * A key a network site gets from a network default is reported as
	 * coming from the network, without a hint: the site administrator never
	 * entered it (the 6.0 screens show it read-only).
	 *
	 * @group ms-required
	 */
	public function test_network_provided_secret_has_no_hint(): void {
		delete_option( 'zero-spam-ipstack' );
		$this->set_network_setting( 'ipstack_api', 'network-ipstack-key-5678' );

		$this->assertSame(
			array(
				'set'    => true,
				'source' => 'network',
			),
			Settings_Schema::masked( 'ipstack_api' )
		);
		$entry = self::by_key( Settings_Schema::get()['settings'] )['ipstack_api'];
		$this->assertSame( 'network', $entry['source'] );
		$this->assertStringNotContainsString( '5678', (string) wp_json_encode( $entry ) );
	}

	/**
	 * Settings report where their value comes from and the derived defaults.
	 */
	public function test_sources_and_defaults(): void {
		$this->set_setting( 'max_logs', 500 );

		$settings = self::by_key( Settings_Schema::get()['settings'] );

		$this->assertSame( 'site', $settings['max_logs']['source'] );
		$this->assertSame( 500, $settings['max_logs']['value'] );
		$this->assertSame( 10000, $settings['max_logs']['default'] );
		$this->assertSame( 'default', $settings['zerospam_confidence_min']['source'] );
		$this->assertSame( 30, $settings['zerospam_confidence_min']['default'] );
		$this->assertSame( 'php', $settings['block_method']['default'] );
		$this->assertSame( 'zero-spam-settings', $settings['max_logs']['option'] );
		$this->assertSame( 'zerospam_blocked_email_domains', $settings['blocked_email_domains']['option'] );
	}

	/**
	 * Areas and groups follow the 6.0 information architecture.
	 */
	public function test_areas_and_groups(): void {
		$settings = self::by_key( Settings_Schema::get()['settings'] );

		$this->assertSame( array( 'protection', 'detection' ), array( $settings['verify_comments']['area'], $settings['verify_comments']['group'] ) );
		$this->assertSame( array( 'protection', 'intelligence' ), array( $settings['zerospam']['area'], $settings['zerospam']['group'] ) );
		$this->assertSame( array( 'rules', 'allowed-ips' ), array( $settings['ip_whitelist']['area'], $settings['ip_whitelist']['group'] ) );
		$this->assertSame( array( 'settings', 'general' ), array( $settings['max_logs']['area'], $settings['max_logs']['group'] ) );
		$this->assertSame( array( 'settings', 'proxies' ), array( $settings['trusted_proxies']['area'], $settings['trusted_proxies']['group'] ) );
		$this->assertSame(
			array(
				'key'   => 'block_handler',
				'value' => 'redirect',
			),
			$settings['blocked_redirect_url']['depends_on']
		);
	}

	/**
	 * The optional registry keys override what the schema derives.
	 */
	public function test_optional_registry_keys(): void {
		add_filter(
			'zerospam_settings',
			static function ( array $settings ): array {
				$settings['zs_test_extension'] = array(
					'title'      => 'Extension <b>setting</b>',
					'module'     => 'zs_test_extension',
					'section'    => 'zs_test_extension',
					'type'       => 'text',
					'value'      => false,
					'default'    => 'abc',
					'secret'     => true,
					'lockable'   => false,
					'area'       => 'rules',
					'group'      => 'custom',
					'depends_on' => array(
						'key'   => 'share_data',
						'value' => 'enabled',
					),
					'order'      => 5,
				);

				return $settings;
			}
		);
		Settings_Schema::reset_cache();

		$entry = Settings_Schema::setting( 'zs_test_extension' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'Extension setting', $entry['title'] );
		$this->assertTrue( $entry['secret'] );
		$this->assertFalse( $entry['lockable'] );
		$this->assertSame( array( 'rules', 'custom', 5 ), array( $entry['area'], $entry['group'], $entry['order'] ) );
		$this->assertSame( 'share_data', $entry['depends_on']['key'] );
	}

	/**
	 * A third-party section without an area lands in Protection › Integrations.
	 */
	public function test_unknown_section_defaults_to_integrations(): void {
		add_filter(
			'zerospam_setting_sections',
			static function ( array $sections ): array {
				$sections['zs_test_section'] = array( 'title' => 'Test section' );

				return $sections;
			}
		);
		Settings_Schema::reset_cache();

		$sections = array_column( Settings_Schema::get()['sections'], null, 'key' );

		$this->assertSame( array( 'protection', 'integrations' ), array( $sections['zs_test_section']['area'], $sections['zs_test_section']['group'] ) );
		$this->assertSame( 'settings', $sections['general']['module'] );
	}

	/**
	 * The registry is built once per request, however often the schema is read.
	 */
	public function test_registry_is_built_once_per_request(): void {
		$builds = 0;
		add_filter(
			'zerospam_settings',
			static function ( $settings ) use ( &$builds ) {
				++$builds;

				return $settings;
			}
		);
		Settings_Schema::reset_cache();

		Settings_Schema::get();
		Settings_Schema::resolve( 'max_logs' );
		Settings_Schema::masked( 'zerospam_license' );
		Settings_Schema::get();

		// One build, plus one for the derived defaults.
		$this->assertSame( 2, $builds );
	}

	/**
	 * Changing a setting in the request is seen afterwards.
	 */
	public function test_cache_follows_option_updates(): void {
		$this->assertSame( 'default', Settings_Schema::resolve( 'max_logs' )['source'] );

		update_option( 'zero-spam-settings', array( 'max_logs' => 42 ) );

		$this->assertSame( 'site', Settings_Schema::resolve( 'max_logs' )['source'] );
		$this->assertSame( 42, Settings_Schema::resolve( 'max_logs' )['value'] );
	}

	/**
	 * With the object cache warm the route sends at most 10 queries.
	 */
	public function test_query_bound_after_warm_cache(): void {
		$this->rest( 'GET', self::ROUTE );
		Settings_Schema::reset_cache();

		$queries = $this->capture_queries(
			function (): void {
				$this->assertSame( 200, $this->rest( 'GET', self::ROUTE )->get_status() );
			}
		);

		$this->assertLessThanOrEqual( 10, count( $queries ), implode( "\n", $queries ) );
	}

	/**
	 * Who may read the schema.
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
	 * Only administrators of the site can read the schema.
	 *
	 * @dataProvider data_permissions
	 *
	 * @param string|null $role     Role, or null for a logged-out visitor.
	 * @param int         $expected HTTP status.
	 */
	public function test_permissions( ?string $role, int $expected ): void {
		if ( null === $role ) {
			wp_set_current_user( 0 );
		} else {
			$this->as_role( $role );
		}

		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( $expected, $response->get_status() );
		if ( 200 !== $expected ) {
			$this->assertArrayNotHasKey( 'settings', $response->get_data() );
		}
	}

	/**
	 * Maps the license to a test constant, with a registry filter doing what
	 * modules/class-zerospam.php does for ZEROSPAM_LICENSE_KEY.
	 */
	private function use_test_constant(): void {
		if ( ! defined( self::TEST_CONSTANT ) ) {
			define( self::TEST_CONSTANT, 'constant-key-9z9z' );
		}

		self::set_static( Settings_Schema::class, 'constants', array( 'zerospam_license' => self::TEST_CONSTANT ) );
		add_filter(
			'zerospam_settings',
			static function ( array $settings ): array {
				if ( empty( $settings['zerospam_license']['value'] ) ) {
					$settings['zerospam_license']['value'] = constant( self::TEST_CONSTANT );
				}

				return $settings;
			},
			20
		);
		Settings_Schema::reset_cache();
	}

	/**
	 * Custom Form Selectors is a lockable text setting with a placeholder,
	 * and its how-to is an html entry carrying its list.
	 */
	public function test_form_selectors_are_a_lockable_text_setting(): void {
		update_option( 'zero-spam-davidwalsh', array( 'davidwalsh_form_selectors' => '.kept-form' ) );
		self::reset_static_caches();

		$settings = self::by_key( Settings_Schema::get()['settings'] );
		$entry    = $settings['davidwalsh_form_selectors'];

		$this->assertSame( 'text', $entry['type'] );
		$this->assertTrue( $entry['lockable'] );
		$this->assertSame( '.kept-form', $entry['value'], 'The stored value is read as it was.' );
		$this->assertNotSame( '', $entry['placeholder'] );
		$this->assertStringNotContainsString( '<input', $entry['description'] );
		$this->assertContains( 'davidwalsh_form_selectors', Settings_Schema::value_keys() );

		$help = $settings['davidwalsh_form_selectors_help'];
		$this->assertSame( 'html', $help['type'] );
		$this->assertStringContainsString( '<ol>', $help['html'] );
		$this->assertStringContainsString( '<code>', $help['html'] );
		$this->assertStringNotContainsString( 'zerospam-list', $help['html'], 'Only formatting is kept, not attributes.' );
	}

	/**
	 * Html settings carry their help markup, limited to formatting, lists and
	 * links; the ones made on the settings page (action buttons) carry ''.
	 */
	public function test_html_settings_carry_safe_help(): void {
		add_filter(
			'zerospam_settings',
			static function ( array $settings ): array {
				$settings['zs_test_help'] = array(
					'title'   => 'Help',
					'section' => 'davidwalsh',
					'module'  => 'davidwalsh',
					'type'    => 'html',
					'html'    => '<p onclick="x()">Hi <a href="https://example.com/" style="color:red">there</a><script>alert(1)</script><input name="x"></p><ul><li>One</li></ul>',
				);

				return $settings;
			}
		);
		self::reset_static_caches();

		$settings = self::by_key( Settings_Schema::get()['settings'] );

		$this->assertSame( '<p>Hi <a href="https://example.com/">there</a>alert(1)</p><ul><li>One</li></ul>', $settings['zs_test_help']['html'] );
		$this->assertSame( '', $settings['regenerate_honeypot']['html'] );
		$this->assertNull( $settings['verify_comments']['html'] );
	}

	/**
	 * The new Security switches and the honeypot belong to Protection › Detection.
	 */
	public function test_protection_detection_entries(): void {
		$settings = self::by_key( Settings_Schema::get()['settings'] );

		foreach ( array( 'disable_xmlrpc', 'hide_wp_version', 'regenerate_honeypot', 'davidwalsh_form_selectors' ) as $key ) {
			$this->assertSame( array( 'protection', 'detection' ), array( $settings[ $key ]['area'], $settings[ $key ]['group'] ), $key );
		}
	}

	/**
	 * A module that registers its settings without a section (Security,
	 * ipbase, extensions) has them in its own section, not in General.
	 */
	public function test_settings_without_a_section_belong_to_their_module(): void {
		$settings = self::by_key( Settings_Schema::get()['settings'] );

		$this->assertSame( 'security', $settings['disable_emojis']['section'] );
		$this->assertSame( 'ipbase', $settings['ipbase_api_key']['section'] );
		$this->assertSame( 'general', $settings['max_logs']['section'] );
	}
}
