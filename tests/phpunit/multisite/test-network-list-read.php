<?php
/**
 * Tests for what a page view on a network reads of the blocked email domains.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * A network can set the blocked email domains for every site; the list can be
 * about 1.1 MB. Only a submitted form needs it (#404), so a page view on a
 * site must not load it, and the network's other settings must still apply.
 *
 * @group ms-required
 *
 * @covers \ZeroSpam\Includes\Network_Settings::resolve_all_settings
 * @covers \ZeroSpam\Includes\Network_Settings::get_network_settings
 */
class Test_Network_List_Read extends TestCase {

	/**
	 * A long list, so that loading it would be visible in a size check.
	 *
	 * @var string
	 */
	private string $list = '';

	/**
	 * Network list, network default for another setting, anonymous visitor.
	 */
	public function set_up(): void {
		parent::set_up();

		$domains = array( 'listed.example' );
		for ( $i = 0; $i < 2000; $i++ ) {
			$domains[] = "spam-domain-{$i}.example";
		}
		$this->list = implode( "\n", $domains );

		delete_option( 'zerospam_blocked_email_domains' );
		$this->set_network_setting( 'blocked_message', 'Network says no.' );
		$this->set_network_setting( 'blocked_email_domains', $this->list );
		wp_set_current_user( 0 );
		$this->set_request( '/', '203.0.113.9' );
	}

	/**
	 * Runs a page view's settings reads and counts what loaded the network's list.
	 *
	 * @return int Number of times a value holding the list was read.
	 */
	private function list_loads_during_page_view(): int {
		$loads   = 0;
		$watcher = function ( $value ) use ( &$loads ) {
			if ( is_string( $value ) ? str_contains( $value, 'spam-domain-1999.example' ) : str_contains( (string) wp_json_encode( $value ), 'spam-domain-1999.example' ) ) {
				++$loads;
			}

			return $value;
		};

		add_filter( 'site_option_' . Network_Settings::META_KEY, $watcher );
		add_filter( 'site_transient_' . Network_Settings::cache_key(), $watcher );
		try {
			foreach ( array( 'zerospam', 'verify_comments', 'blocked_message', 'stop_forum_spam' ) as $key ) {
				Settings::get_settings( $key );
			}
			Settings_Schema::value( 'trusted_proxies' );
			Access::process();
			( new Access() )->get_access();
		} finally {
			remove_filter( 'site_option_' . Network_Settings::META_KEY, $watcher );
			remove_filter( 'site_transient_' . Network_Settings::cache_key(), $watcher );
		}

		return $loads;
	}

	/**
	 * Once the network's settings are cached, a page view does not load the list.
	 */
	public function test_page_view_does_not_load_the_network_list(): void {
		$this->list_loads_during_page_view(); // Warms the network cache.
		self::reset_static_caches();

		$this->assertSame( 0, $this->list_loads_during_page_view() );
	}

	/**
	 * The network's other settings still apply without the list.
	 */
	public function test_other_network_settings_still_apply(): void {
		$this->assertSame( 'Network says no.', Settings::get_settings( 'blocked_message' ) );
		$this->assertArrayNotHasKey( 'value', Settings::get_settings_without_lists()['blocked_email_domains'] );
	}

	/**
	 * A submitted form still uses the network's list, locked or not.
	 */
	public function test_form_check_uses_the_network_list(): void {
		$this->assertTrue( Utilities::is_email_domain_blocked( 'jane@listed.example' ) );
		$this->assertFalse( Utilities::is_email_domain_blocked( 'jane@fine.example' ) );

		$this->set_network_setting( 'blocked_email_domains', 'locked.example', true );
		$this->assertTrue( Utilities::is_email_domain_blocked( 'jane@locked.example' ) );
		$this->assertFalse( Utilities::is_email_domain_blocked( 'jane@listed.example' ) );
	}

	/**
	 * A cache written before 6.0.1 still holds the list; it is rewritten
	 * without it on first use, so the next page view does not load it.
	 */
	public function test_cache_from_before_holding_the_list_is_rewritten(): void {
		set_site_transient( Network_Settings::cache_key(), get_site_option( Network_Settings::META_KEY ), HOUR_IN_SECONDS );
		self::reset_static_caches();

		$this->list_loads_during_page_view();
		self::reset_static_caches();

		$this->assertSame( 0, $this->list_loads_during_page_view() );
		$this->assertTrue( Utilities::is_email_domain_blocked( 'jane@listed.example' ) );
	}

	/**
	 * The network page's checks read the network's list from the store.
	 */
	public function test_network_default_reads_the_list_from_the_store(): void {
		$network = new Network_Settings();

		$this->assertTrue( $network->is_using_default( 'blocked_email_domains' ) );
		$this->assertNull( $network->get_site_override( 'blocked_email_domains' ) );
		$this->assertSame( $this->list, $network->get_network_default( 'blocked_email_domains' ) );
	}

	/**
	 * Saving through the manager keeps the network's list (it reads the whole store).
	 */
	public function test_network_list_survives_the_cache(): void {
		$full = ( new Network_Settings() )->get_network_settings();

		$this->assertSame( $this->list, $full['settings']['blocked_email_domains']['value'] );
		$this->assertArrayNotHasKey( Network_Settings::VALUE_IN_STORE, $full['settings']['blocked_email_domains'] );
	}

	/**
	 * Saving another network setting through the manager, with the cache
	 * warm, writes the list back to the store unchanged and without the marker.
	 */
	public function test_manager_saves_keep_the_network_list(): void {
		Settings::get_settings( 'blocked_message' ); // Warms the cache.
		$manager = new \ZeroSpam\Includes\Network_Settings_Manager();

		$manager->lock_setting( 'blocked_message' );
		$manager->unlock_setting( 'blocked_message' );

		$stored = get_site_option( Network_Settings::META_KEY );
		$this->assertSame( $this->list, $stored['settings']['blocked_email_domains']['value'] );
		$this->assertArrayNotHasKey( Network_Settings::VALUE_IN_STORE, $stored['settings']['blocked_email_domains'] );
	}

	/**
	 * The schema still reports the network as the list's source, with its value.
	 */
	public function test_schema_reports_the_network_list(): void {
		Settings::get_settings( 'blocked_message' ); // Warms the cache.
		self::reset_static_caches();

		$entry = Settings_Schema::resolve( 'blocked_email_domains' );
		$this->assertSame( 'network', $entry['source'] );
		$this->assertSame( $this->list, $entry['value'] );
	}

	/**
	 * A locked list the store no longer holds reads as null without a PHP
	 * warning (a cache older than the change).
	 */
	public function test_locked_list_missing_from_the_store_reads_as_empty(): void {
		$this->set_network_setting( 'blocked_email_domains', null, true );
		Settings::get_settings( 'blocked_message' ); // Warms the cache.
		self::reset_static_caches();

		$this->assertFalse( Settings::get_settings( 'blocked_email_domains' ) );
		$this->assertFalse( Utilities::is_email_domain_blocked( 'jane@listed.example' ) );
	}

	/**
	 * Deleting the network store clears the cache.
	 */
	public function test_deleting_the_store_clears_the_cache(): void {
		Settings::get_settings( 'blocked_message' ); // Warms the cache.
		delete_site_option( Network_Settings::META_KEY );
		self::reset_static_caches();

		$this->assertNotSame( 'Network says no.', Settings::get_settings( 'blocked_message' ) );
	}

	/**
	 * A locked network list wins over the site's own.
	 */
	public function test_locked_network_list_wins_over_the_site_list(): void {
		update_option( 'zerospam_blocked_email_domains', 'site.example', false );
		$this->set_network_setting( 'blocked_email_domains', 'locked.example', true );

		$this->assertSame( 'locked.example', Settings::get_settings( 'blocked_email_domains' ) );
	}
}
