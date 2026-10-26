<?php
/**
 * Tests for zero-spam/v1/protection/modules.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Shared_IP_Warning;
use ZeroSpam\Includes\Rest\Protection_Controller;
use ZeroSpam\Modules\WPForms\WPForms;
use ZeroSpam\Tests\TestCase;

/**
 * Every section with its state, key, plugin, locks and detections; the
 * integrations whose plugin is not active; and the "needs attention" items.
 *
 * @covers \ZeroSpam\Includes\Rest\Protection_Controller
 */
class Test_Rest_Protection extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/protection/modules';

	/**
	 * Empties the log and the settings tests write; logs in an administrator.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->log_table() ) );
		delete_transient( Protection_Controller::TRANSIENT );
		foreach ( array( 'settings', 'comments', 'ipstack', 'wpforms', 'security', 'zs_widgets' ) as $module ) {
			delete_option( "zero-spam-{$module}" );
		}
		delete_option( Shared_IP_Warning::DISMISSED_OPTION );

		$this->as_role( 'administrator' );
	}

	/**
	 * Modules by key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function modules(): array {
		return array_column( $this->rest( 'GET', self::ROUTE )->get_data()['modules'], null, 'key' );
	}

	/**
	 * Attention items by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function attention(): array {
		return array_column( $this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'attention' ) )->get_data()['attention'], null, 'id' );
	}

	/**
	 * Only administrators.
	 */
	public function test_only_administrators(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', self::ROUTE )->get_status() );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( 'GET', self::ROUTE )->get_status() );
	}

	/**
	 * A detection module: its switch, state, last detection and the last seven days.
	 */
	public function test_detection_module(): void {
		$this->set_setting( 'verify_comments', 'enabled' );
		$this->insert_log_rows( 3, array( 'log_type' => 'comment' ) );
		$this->insert_log_rows( 2, array( 'log_type' => 'comment', 'date_recorded' => '2020-05-01 08:00:00' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short fixture.

		$comments = $this->modules()['comments'];

		$this->assertTrue( $comments['enabled'] );
		$this->assertSame( array( 'verify_comments' ), $comments['switches'] );
		$this->assertSame( 'protection', $comments['area'] );
		$this->assertSame( 'detection', $comments['group'] );
		$this->assertSame( 3, $comments['detections_7d'] );
		$this->assertNotNull( $comments['last_detection'] );
		$this->assertNull( $comments['plugin'] );
		$this->assertFalse( $comments['locked'] );

		$this->set_setting( 'verify_comments', false );
		$this->assertFalse( $this->modules()['comments']['enabled'] );
	}

	/**
	 * A key-only service is on once its key is set; the key stays masked.
	 */
	public function test_service_that_needs_a_key(): void {
		$ipstack = $this->modules()['ipstack'];

		$this->assertTrue( $ipstack['needs_key'] );
		$this->assertFalse( $ipstack['enabled'] );
		$this->assertSame( 'ipstack_api', $ipstack['key_setting'] );
		$this->assertSame( 'intelligence', $ipstack['group'] );

		$this->set_setting( 'ipstack_api', 'ipstack-SECRET-key-1234' );
		$response = $this->rest( 'GET', self::ROUTE );
		$ipstack  = array_column( $response->get_data()['modules'], null, 'key' )['ipstack'];

		$this->assertFalse( $ipstack['needs_key'] );
		$this->assertTrue( $ipstack['enabled'] );
		$this->assertStringNotContainsString( 'SECRET', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Integrations whose plugin is not active are listed as not available.
	 */
	public function test_inactive_integrations_are_listed(): void {
		$modules = $this->modules();

		$this->assertFalse( $modules['contactform7']['available'] );
		$this->assertSame(
			array(
				'name'   => 'Contact Form 7',
				'active' => false,
			),
			$modules['contactform7']['plugin']
		);
		$this->assertSame( 'integrations', $modules['contactform7']['group'] );
	}

	/**
	 * An active integration that is not protected needs attention.
	 */
	public function test_unprotected_integration_needs_attention(): void {
		update_option( 'active_plugins', array( 'wpforms-lite/wpforms.php' ) );
		( new WPForms() )->init();
		self::reset_static_caches();

		$modules = $this->modules();
		$this->assertTrue( $modules['wpforms']['available'] );
		$this->assertTrue( $modules['wpforms']['plugin']['active'] );
		$this->assertFalse( $modules['wpforms']['enabled'] );
		$this->assertArrayHasKey( 'integration_unprotected:wpforms', $this->attention() );

		$this->set_setting( 'verify_wpforms', 'enabled' );
		$this->assertArrayNotHasKey( 'integration_unprotected:wpforms', $this->attention() );
	}

	/**
	 * A log at 90% of its size or more needs attention.
	 */
	public function test_full_log_needs_attention(): void {
		$this->set_setting( 'max_logs', 10 );
		$this->insert_log_rows( 8 );
		$this->assertArrayNotHasKey( 'log_fill', $this->attention() );

		$this->insert_log_rows( 1 );
		$item = $this->attention()['log_fill'];

		$this->assertSame( 90, $item['percent'] );
		$this->assertSame( 9, $item['count'] );
		$this->assertSame( 10, $item['max'] );
	}

	/**
	 * The shared-IP verdict needs attention until it is dismissed.
	 */
	public function test_shared_ip_needs_attention_until_dismissed(): void {
		$verdict = array(
			'ip'     => '10.0.0.5',
			'reason' => 'shared_ip',
		);
		add_filter(
			'zerospam_shared_ip_warning',
			static function () use ( $verdict ) {
				return $verdict;
			}
		);

		$this->assertSame( '10.0.0.5', $this->attention()['shared_ip']['ip'] );

		update_option( Shared_IP_Warning::DISMISSED_OPTION, 'shared_ip:10.0.0.5', false );
		$this->assertArrayNotHasKey( 'shared_ip', $this->attention() );
	}

	/**
	 * A forwarding header from an address that is not a trusted proxy needs attention.
	 */
	public function test_untrusted_proxy_needs_attention(): void {
		$this->assertArrayNotHasKey( 'proxy_untrusted', $this->attention() );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.77';
		$this->assertSame( self::DEFAULT_IP, $this->attention()['proxy_untrusted']['ip'] );

		$this->set_setting( 'trusted_proxies', self::DEFAULT_IP );
		$this->assertArrayNotHasKey( 'proxy_untrusted', $this->attention() );
	}

	/**
	 * Detection counts come from one grouped query, cached until a new detection.
	 */
	public function test_detections_are_read_once_and_cached(): void {
		$this->insert_log_rows( 2 );

		$queries = $this->capture_queries(
			function () {
				$this->rest( 'GET', self::ROUTE );
			}
		);
		$this->assertCount( 1, preg_grep( '/GROUP BY `log_type`/', $queries ), implode( "\n", $queries ) );

		$queries = $this->capture_queries(
			function () {
				$this->rest( 'GET', self::ROUTE );
			}
		);
		$this->assertCount( 0, preg_grep( '/GROUP BY `log_type`/', $queries ), 'Served from the cache.' );

		$this->insert_log_rows( 1 );
		$this->assertSame( 3, $this->modules()['comments']['detections_7d'] );
	}

	/**
	 * `_fields` leaves out what was not asked for.
	 */
	public function test_fields(): void {
		$this->assertSame( array( 'attention' ), array_keys( $this->rest( 'GET', self::ROUTE, array(), array( '_fields' => 'attention' ) )->get_data() ) );
	}

	/**
	 * The modules route builds the settings registry at most once.
	 */
	public function test_builds_the_registry_once(): void {
		$this->rest( 'GET', self::ROUTE );

		$builds = $this->count_registry_builds(
			function () {
				$this->assertSame( 200, $this->rest( 'GET', self::ROUTE )->get_status() );
			}
		);

		$this->assertLessThanOrEqual( 1, $builds );
	}

	/**
	 * Each core entry point (comments, login, registration) that is off needs
	 * attention, first; turned on, it goes away.
	 */
	public function test_core_entry_point_off_needs_attention(): void {
		$this->set_setting( 'verify_comments', false, 'comments' );
		$this->set_setting( 'verify_login', 'enabled', 'login' );
		$this->set_setting( 'verify_registrations', false, 'registration' );

		$items = $this->attention();
		$this->assertArrayHasKey( 'entry_point_off:comments', $items );
		$this->assertArrayHasKey( 'entry_point_off:registration', $items );
		$this->assertArrayNotHasKey( 'entry_point_off:login', $items );
		$this->assertSame( 'warning', $items['entry_point_off:comments']['severity'] );
		$this->assertSame( 'comments', $items['entry_point_off:comments']['module'] );
		$this->assertSame( 'Comment protection is off', $items['entry_point_off:comments']['title'] );
		$this->assertStringStartsWith( 'entry_point_off:', array_key_first( $items ), 'Entry points come first.' );

		$this->set_setting( 'verify_comments', 'enabled', 'comments' );
		$this->assertArrayNotHasKey( 'entry_point_off:comments', $this->attention() );
	}

	/**
	 * The Security card's switches include the two added in 6.0; it is on by
	 * default because they are.
	 */
	public function test_security_switches(): void {
		$security = $this->modules()['security'];

		$this->assertSame( array( 'disable_xmlrpc', 'hide_wp_version', 'remove_resource_query_parameters', 'disable_emojis', 'disable_rss_feed' ), $security['switches'] );
		$this->assertTrue( $security['enabled'] );

		update_option(
			'zero-spam-security',
			array(
				'disable_xmlrpc'  => 'disabled',
				'hide_wp_version' => 'disabled',
			)
		);
		self::reset_static_caches();

		$this->assertFalse( $this->modules()['security']['enabled'] );
	}

	/**
	 * Registers a third-party section the way an extension would: a section
	 * without an area, a switch named verify_*, and a few other settings.
	 */
	private function register_fake_section(): void {
		add_filter(
			'zerospam_setting_sections',
			static function ( array $sections ): array {
				$sections['zs_widgets'] = array( 'title' => 'Widget Forms' );

				return $sections;
			}
		);
		add_filter(
			'zerospam_settings',
			static function ( array $settings ): array {
				$options = get_option( 'zero-spam-zs_widgets', array() );

				$settings['verify_zs_widgets']  = array(
					'title'   => 'Protect widget forms',
					'section' => 'zs_widgets',
					'module'  => 'zs_widgets',
					'type'    => 'checkbox',
					'options' => array( 'enabled' => 'Enabled' ),
					'value'   => $options['verify_zs_widgets'] ?? false,
				);
				$settings['zs_widgets_message'] = array(
					'title'   => 'Message',
					'section' => 'zs_widgets',
					'module'  => 'zs_widgets',
					'type'    => 'text',
					'value'   => $options['zs_widgets_message'] ?? '',
				);
				$settings['zs_widgets_mode']    = array(
					'title'   => 'Mode',
					'section' => 'zs_widgets',
					'module'  => 'zs_widgets',
					'type'    => 'radio',
					'options' => array(
						'strict'  => 'Strict',
						'relaxed' => 'Relaxed',
					),
					'value'   => $options['zs_widgets_mode'] ?? 'strict',
				);

				return $settings;
			}
		);
		self::reset_static_caches();
	}

	/**
	 * A third-party section is listed in Protection › Integrations with its
	 * switch and state, and its settings can be read and saved like any other.
	 */
	public function test_third_party_section_is_listed_generically(): void {
		$this->register_fake_section();

		$module = $this->modules()['zs_widgets'];

		$this->assertSame( 'Widget Forms', $module['title'] );
		$this->assertSame( array( 'protection', 'integrations' ), array( $module['area'], $module['group'] ) );
		$this->assertTrue( $module['available'] );
		$this->assertSame( array( 'verify_zs_widgets' ), $module['switches'] );
		$this->assertFalse( $module['enabled'] );
		$this->assertNull( $module['plugin'] );

		$response = $this->rest(
			'PATCH',
			'/zero-spam/v1/settings',
			array(
				'verify_zs_widgets'  => true,
				'zs_widgets_message' => 'Hello',
				'zs_widgets_mode'    => 'relaxed',
			)
		);
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame(
			array(
				'verify_zs_widgets'  => 'enabled',
				'zs_widgets_message' => 'Hello',
				'zs_widgets_mode'    => 'relaxed',
			),
			get_option( 'zero-spam-zs_widgets' )
		);

		self::reset_static_caches();
		$this->assertTrue( $this->modules()['zs_widgets']['enabled'] );

		$schema = array_column( $this->rest( 'GET', '/zero-spam/v1/settings/schema' )->get_data()['settings'], null, 'key' );
		$this->assertSame( 'radio', $schema['zs_widgets_mode']['type'] );
		$this->assertSame( 'zs_widgets', $schema['zs_widgets_mode']['section'] );
		$this->assertSame( 'relaxed', $schema['zs_widgets_mode']['value'] );
	}
}
