<?php
/**
 * Tests for the admin dashboard handlers.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Dashboard;
use ZeroSpam\Core\Admin\Tables\BlockedTable;
use ZeroSpam\Includes\DB;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\TestCase;

/**
 * Admin actions need a capability check and a nonce.
 *
 * The fix for the .htaccess refresh must use the nonce action
 * `zerospam-refresh-htaccess` in the `_wpnonce` field (for example
 * check_admin_referer( 'zerospam-refresh-htaccess' ) behind a
 * manage_options check); test_refresh_htaccess_runs_for_an_admin_with_a_nonce
 * pins that contract.
 *
 * Utilities::refresh_htaccess() starts by reading the `block_method` setting,
 * which reads the zero-spam-settings option; a spy on that option shows
 * whether it ran.
 *
 * block_ip() (admin.php?action=add_blocked_ip) needs manage_options and the
 * `zerospam` nonce from includes/templates/admin-block-ip.php.
 *
 * @covers \ZeroSpam\Core\Admin\Dashboard::admin_init
 * @covers \ZeroSpam\Core\Admin\Dashboard::block_ip
 */
class Test_Dashboard extends TestCase {

	/**
	 * Number of times refresh_htaccess() started.
	 *
	 * @var int
	 */
	private int $refreshes = 0;

	/**
	 * Installs the spy and the admin screen.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$this->refreshes = 0;
		add_filter(
			'pre_option_zero-spam-settings',
			function ( $pre ) {
				++$this->refreshes;

				return $pre;
			}
		);
	}

	/**
	 * Runs admin_init; a refused request may end in wp_die().
	 */
	private function run_admin_init(): void {
		try {
			( new Dashboard() )->admin_init();
		} catch ( WPDieException $e ) {
			// A nonce or capability failure; the spy count is what matters.
			unset( $e );
		}
	}

	/**
	 * A subscriber cannot make the plugin rewrite .htaccess with a bare query flag.
	 */
	public function test_refresh_htaccess_requires_capability_and_nonce(): void {
		$this->as_role( 'subscriber' );
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$this->run_admin_init();

		$this->assertSame( 0, $this->refreshes, 'refresh_htaccess() ran for a subscriber without a nonce.' );
	}

	/**
	 * An administrator without a nonce cannot trigger it either.
	 */
	public function test_refresh_htaccess_requires_a_nonce_for_admins(): void {
		$this->as_role( 'administrator' );
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$this->run_admin_init();

		$this->assertSame( 0, $this->refreshes, 'refresh_htaccess() ran for an administrator without a nonce.' );
	}

	/**
	 * An administrator with a valid nonce does refresh .htaccess.
	 */
	public function test_refresh_htaccess_runs_for_an_admin_with_a_nonce(): void {
		$this->as_role( 'administrator' );
		$nonce                                 = wp_create_nonce( 'zerospam-refresh-htaccess' );
		$_GET['_wpnonce']                      = $nonce;
		$_REQUEST['_wpnonce']                  = $nonce;
		$_REQUEST['zerospam-refresh-htaccess'] = '1';

		$this->run_admin_init();

		$this->assertGreaterThan( 0, $this->refreshes, 'refresh_htaccess() did not run for an administrator with a valid nonce.' );
	}

	/**
	 * Without the flag nothing runs.
	 */
	public function test_admin_init_without_flag_does_nothing(): void {
		$this->as_role( 'administrator' );

		$this->run_admin_init();

		$this->assertSame( 0, $this->refreshes );
	}

	/**
	 * The "Refresh .htaccess" link on the Blocked IPs screen carries a nonce
	 * that admin_init() accepts.
	 *
	 * @covers \ZeroSpam\Core\Admin\Tables\BlockedTable::extra_tablenav
	 */
	public function test_refresh_link_carries_a_valid_nonce(): void {
		$this->as_role( 'administrator' );
		$this->set_setting( 'block_method', 'htaccess_modern' );

		ob_start();
		( new BlockedTable() )->extra_tablenav( 'top' );
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '/href="([^"]*zerospam-refresh-htaccess=1[^"]*)"/', $html, $matches ), 'The Refresh .htaccess link is missing.' );
		$query = array();
		wp_parse_str( (string) wp_parse_url( html_entity_decode( $matches[1] ), PHP_URL_QUERY ), $query );

		$this->assertSame( 'blocked-ips', $query['subview'] ?? null );
		$this->assertNotEmpty( $query['_wpnonce'] ?? '', 'The Refresh .htaccess link has no nonce.' );
		$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'zerospam-refresh-htaccess' ) );
	}

	/**
	 * Runs block_ip() and returns how it ended.
	 *
	 * Both wp_die() and wp_safe_redirect() throw in tests (the redirect through
	 * a wp_redirect filter added here), so the exit() after them never runs.
	 *
	 * @return Throwable|null WPDieException, RedirectException or null.
	 */
	private function run_block_ip(): ?Throwable {
		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			},
			1,
			2
		);

		try {
			( new Dashboard() )->block_ip();
		} catch ( WPDieException | RedirectException $e ) {
			return $e;
		}

		return null;
	}

	/**
	 * Posts the add/update block form for an IP with the current user's nonce.
	 *
	 * @param string $ip IP address to block.
	 */
	private function post_block_form( string $ip ): void {
		$_POST = array(
			'zerospam'           => wp_create_nonce( 'zerospam' ),
			'action'             => 'add_blocked_ip',
			'blocked_ip'         => $ip,
			'blocked_type'       => 'permanent',
			'blocked_reason'     => 'Test block',
			'blocked_start_date' => '',
			'blocked_end_date'   => '',
		);

		$_REQUEST = $_POST;
	}

	/**
	 * Number of block records for an IP.
	 *
	 * @param string $ip IP address.
	 */
	private function count_blocks( string $ip ): int {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['blocked'];

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_ip = %s", $ip ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed plugin table name; test assertion.
	}

	/**
	 * A user without manage_options cannot add a block, even with a valid nonce.
	 */
	public function test_block_ip_requires_manage_options(): void {
		$this->as_role( 'editor' );
		$this->post_block_form( '192.0.2.10' );

		$result = $this->run_block_ip();

		$this->assertInstanceOf( WPDieException::class, $result, 'block_ip() did not stop a user without manage_options.' );
		$this->assertSame( 403, $result->getCode() );
		$this->assertSame( 0, $this->count_blocks( '192.0.2.10' ), 'block_ip() added a block for a user without manage_options.' );
	}

	/**
	 * An administrator with an invalid nonce is sent back with an error.
	 */
	public function test_block_ip_rejects_an_invalid_nonce(): void {
		$this->as_role( 'administrator' );
		$this->post_block_form( '192.0.2.11' );
		$_POST['zerospam']    = 'invalid';
		$_REQUEST['zerospam'] = 'invalid';

		$result = $this->run_block_ip();

		$this->assertInstanceOf( RedirectException::class, $result );
		$this->assertStringContainsString( 'zerospam-type=error', $result->location );
		$this->assertSame( 0, $this->count_blocks( '192.0.2.11' ) );
	}

	/**
	 * An administrator with a valid nonce adds the block and is redirected back.
	 */
	public function test_block_ip_adds_the_block_for_an_admin_with_a_nonce(): void {
		$this->as_role( 'administrator' );
		$this->post_block_form( '192.0.2.12' );

		$result = $this->run_block_ip();

		$this->assertInstanceOf( RedirectException::class, $result );
		$this->assertStringStartsWith( admin_url( 'index.php?page=wordpress-zero-spam-dashboard' ), $result->location );
		$this->assertStringContainsString( 'subview=blocked-ips', $result->location );
		$this->assertStringContainsString( 'zerospam-type=success', $result->location );
		$this->assertSame( 1, $this->count_blocks( '192.0.2.12' ) );
	}
}
