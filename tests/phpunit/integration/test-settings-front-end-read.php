<?php
/**
 * Tests for what a front-end request reads of the settings.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Tests\TestCase;

/**
 * The blocked email domains can be about 1.1 MB in an option of their own, and
 * only a submitted form needs them (#404): an anonymous page view must not read
 * them. The rest of the registry is built once per request instead of on every
 * Settings::get_settings() call.
 *
 * @covers \ZeroSpam\Core\Settings::get_settings
 * @covers \ZeroSpam\Core\Settings::get_settings_without_lists
 * @covers \ZeroSpam\Core\Settings_Schema::value
 */
class Test_Settings_Front_End_Read extends TestCase {

	/**
	 * The list's own option.
	 *
	 * @var string
	 */
	const OWN = 'zerospam_blocked_email_domains';

	/**
	 * Starts each test with a list stored and an anonymous visitor.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( self::OWN, "listed.example\nother.example", false );
		wp_set_current_user( 0 );
		$this->set_request( '/', '203.0.113.9' );
		self::reset_static_caches();
	}

	/**
	 * The queries that read the list while a callback runs, from a cold cache.
	 *
	 * @param callable $callback Code to run.
	 * @return string[]
	 */
	private function list_reads( callable $callback ): array {
		wp_cache_delete( self::OWN, 'options' );
		self::reset_static_caches();

		return array_values( (array) preg_grep( '/' . self::OWN . '/', $this->capture_queries( $callback ) ) );
	}

	/**
	 * What every request does while the plugin loads, and the block check.
	 */
	public function test_anonymous_page_view_does_not_read_the_list(): void {
		$reads = $this->list_reads(
			static function (): void {
				foreach ( array( 'zerospam', 'verify_registrations', 'verify_login', 'verify_comments', 'stop_forum_spam', 'davidwalsh' ) as $key ) {
					Settings::get_settings( $key );
				}

				Settings_Schema::value( 'trusted_proxies' );
				Access::process();
				( new Access() )->run_block_check();
				Utilities::is_whitelisted( '203.0.113.9' );
			}
		);

		$this->assertSame( array(), $reads );
	}

	/**
	 * The security module, loaded on every request, stays out of the list.
	 */
	public function test_security_module_does_not_read_the_list(): void {
		$reads = $this->list_reads(
			static function (): void {
				new \ZeroSpam\Modules\Security\Security();
			}
		);

		$this->assertSame( array(), $reads );
	}

	/**
	 * Reading settings one by one builds the registry once.
	 */
	public function test_builds_the_registry_once_for_a_page_view(): void {
		$builds = $this->count_registry_builds(
			static function (): void {
				foreach ( array( 'zerospam', 'verify_registrations', 'verify_login', 'verify_comments', 'stop_forum_spam', 'davidwalsh' ) as $key ) {
					Settings::get_settings( $key );
				}

				Settings_Schema::value( 'trusted_proxies' );
				Access::process();
				( new Access() )->get_access();
			}
		);

		$this->assertSame( 1, $builds );
	}

	/**
	 * The list is there for everyone who asks for it.
	 */
	public function test_list_is_read_when_asked_for(): void {
		$list = "listed.example\nother.example";

		$this->assertSame( $list, Settings::get_settings( 'blocked_email_domains' ) );
		$this->assertSame( $list, Settings::get_settings()['blocked_email_domains']['value'] );
		$this->assertSame( $list, Settings_Schema::value( 'blocked_email_domains' ) );
		$this->assertSame( $list, Settings_Schema::registry()['blocked_email_domains']['value'] );
		$this->assertSame( $list, Settings_Schema::resolve( 'blocked_email_domains' )['value'] );
	}

	/**
	 * A submitted form still finds a listed domain, after settings were read without it.
	 */
	public function test_form_check_finds_a_listed_domain(): void {
		Settings::get_settings( 'verify_comments' );
		Settings::get_settings_without_lists();

		$this->assertTrue( Utilities::is_email_domain_blocked( 'jane@listed.example' ) );
		$this->assertFalse( Utilities::is_email_domain_blocked( 'jane@fine.example' ) );
	}

	/**
	 * The registry without lists differs from the whole one by the list's value only.
	 */
	public function test_without_lists_differs_only_by_the_list(): void {
		$whole = Settings::get_settings();
		$light = Settings::get_settings_without_lists();

		$this->assertArrayHasKey( 'blocked_email_domains', $light );
		$this->assertArrayNotHasKey( 'value', $light['blocked_email_domains'] );

		unset( $whole['blocked_email_domains']['value'] );

		$this->assertEquals( $whole, $light );
	}

	/**
	 * Every setting read on its own answers what the whole registry holds.
	 */
	public function test_single_reads_match_the_whole_registry(): void {
		$this->set_setting( 'share_data', 'enabled' );

		foreach ( Settings::get_settings() as $key => $setting ) {
			$expected = ! empty( $setting['value'] ) ? $setting['value'] : false;

			$this->assertSame( $expected, Settings::get_settings( $key ), $key );
		}
	}

	/**
	 * A stored setting is read back in the same request.
	 */
	public function test_saved_setting_is_read_in_the_same_request(): void {
		$this->assertNotSame( 'written-in-request', Settings::get_settings( 'blocked_message' ) );

		$options                    = (array) get_option( 'zero-spam-settings', array() );
		$options['blocked_message'] = 'written-in-request';
		update_option( 'zero-spam-settings', $options );

		$this->assertSame( 'written-in-request', Settings::get_settings( 'blocked_message' ) );

		delete_option( 'zero-spam-settings' );

		$this->assertNotSame( 'written-in-request', Settings::get_settings( 'blocked_message' ) );
	}

	/**
	 * A changed list is read back in the same request.
	 */
	public function test_saved_list_is_read_in_the_same_request(): void {
		Settings::get_settings( 'blocked_email_domains' );

		update_option( self::OWN, 'changed.example', false );

		$this->assertSame( 'changed.example', Settings::get_settings( 'blocked_email_domains' ) );
	}

	/**
	 * A filter added or removed later in the request counts, as modules and
	 * other plugins add theirs during init.
	 */
	public function test_filter_added_later_is_applied(): void {
		$this->assertFalse( Settings::get_settings( 'zs_test_added_later' ) );

		$filter = static function ( array $settings ): array {
			$settings['zs_test_added_later'] = array(
				'type'   => 'text',
				'module' => 'settings',
				'value'  => 'from-the-filter',
			);

			return $settings;
		};

		add_filter( 'zerospam_settings', $filter, 10, 1 );
		$this->assertSame( 'from-the-filter', Settings::get_settings( 'zs_test_added_later' ) );

		remove_filter( 'zerospam_settings', $filter, 10 );
		$this->assertFalse( Settings::get_settings( 'zs_test_added_later' ) );
	}

	/**
	 * A filter may answer per user, as the developer guide's example does.
	 */
	public function test_filter_that_depends_on_the_user_follows_the_user(): void {
		$filter = static function ( array $settings ): array {
			$settings['zs_test_per_user'] = array(
				'type'   => 'text',
				'module' => 'settings',
				'value'  => current_user_can( 'manage_options' ) ? 'admin' : 'visitor',
			);

			return $settings;
		};

		add_filter( 'zerospam_settings', $filter, 200, 1 );
		$this->assertSame( 'visitor', Settings::get_settings( 'zs_test_per_user' ) );

		$this->as_role( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		$this->assertSame( 'admin', Settings::get_settings( 'zs_test_per_user' ) );

		wp_set_current_user( 0 );
		$this->assertSame( 'visitor', Settings::get_settings( 'zs_test_per_user' ) );

		remove_filter( 'zerospam_settings', $filter, 200 );
	}

	/**
	 * A filter may use conditional tags: it is asked again once the query has run.
	 */
	public function test_registry_is_built_again_after_the_query(): void {
		global $wp_actions;

		Settings::get_settings_without_lists();
		$fired = (int) did_action( 'wp' );

		$builds = 0;
		$count  = static function ( array $settings ) use ( &$builds ): array {
			++$builds;

			return $settings;
		};
		add_filter( 'zerospam_settings', $count, 200, 1 );
		Settings::get_settings_without_lists();
		$this->assertSame( 1, $builds );

		$wp_actions['wp'] = $fired ? 0 : 1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Stands in for the query having run.
		Settings::get_settings_without_lists();
		$wp_actions['wp'] = $fired; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts it back.

		remove_filter( 'zerospam_settings', $count, 200 );
		$this->assertSame( 2, $builds );
	}

	/**
	 * What a caller does to its copy stays with it.
	 */
	public function test_callers_cannot_change_the_cached_registry(): void {
		$copy                            = Settings::get_settings_without_lists();
		$copy['verify_comments']['zs_x'] = true;

		$this->assertArrayNotHasKey( 'zs_x', Settings::get_settings_without_lists()['verify_comments'] );
	}

	/**
	 * A module adds its settings before init, or each module's read during
	 * init would build the registry again.
	 */
	public function test_modules_add_their_settings_in_the_constructor(): void {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ZEROSPAM_PATH . 'modules', FilesystemIterator::SKIP_DOTS ) );
		$checked  = 0;

		foreach ( $iterator as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own source.
			$source = 'php' === $file->getExtension() ? (string) file_get_contents( $file->getPathname() ) : '';
			$filter = strpos( $source, "add_filter( 'zerospam_settings'" );

			if ( false === $filter ) {
				continue;
			}

			++$checked;
			$this->assertLessThan( (int) strpos( $source, 'public function init()' ), $filter, $file->getFilename() );
		}

		$this->assertGreaterThan( 20, $checked );
	}
}
