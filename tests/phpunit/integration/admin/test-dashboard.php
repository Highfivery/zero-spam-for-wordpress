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
 * The .htaccess refresh is the admin-post action `zerospam_refresh_htaccess`
 * (Dashboard::refresh_htaccess()) with the nonce action
 * `zerospam-refresh-htaccess` in the `_wpnonce` field, checked with
 * check_admin_referer() behind a manage_options check;
 * test_refresh_htaccess_runs_for_an_admin_with_a_nonce pins that contract.
 * It used to run from admin_init on a query flag, which broke the Blocked IPs
 * list table's own forms (see
 * test_list_table_post_with_the_old_refresh_flag_is_not_refused).
 *
 * Utilities::refresh_htaccess() starts by reading the `block_method` setting,
 * which reads the zero-spam-settings option; a spy on that option shows
 * whether it ran.
 *
 * block_ip() (admin.php?action=add_blocked_ip) needs manage_options and the
 * `zerospam` nonce from includes/templates/admin-block-ip.php.
 *
 * @covers \ZeroSpam\Core\Admin\Dashboard::refresh_htaccess
 * @covers \ZeroSpam\Core\Admin\Dashboard::block_ip
 */
class Test_Dashboard extends TestCase {

	/**
	 * Number of times Utilities::refresh_htaccess() started.
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
	 * Runs a handler and returns how it ended.
	 *
	 * Both wp_die() and wp_safe_redirect() throw in tests (the redirect through
	 * a wp_redirect filter added here), so the exit() after them never runs.
	 *
	 * @param callable $handler Handler to run.
	 * @return Throwable|null WPDieException, RedirectException or null.
	 */
	private function run_handler( callable $handler ): ?Throwable {
		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			},
			1,
			2
		);

		try {
			$handler();
		} catch ( WPDieException | RedirectException $e ) {
			return $e;
		}

		return null;
	}

	/**
	 * Runs the admin-post refresh handler.
	 *
	 * @return Throwable|null WPDieException, RedirectException or null.
	 */
	private function run_refresh(): ?Throwable {
		return $this->run_handler( array( new Dashboard(), 'refresh_htaccess' ) );
	}

	/**
	 * Runs block_ip().
	 *
	 * @return Throwable|null WPDieException, RedirectException or null.
	 */
	private function run_block_ip(): ?Throwable {
		return $this->run_handler( array( new Dashboard(), 'block_ip' ) );
	}

	/**
	 * Puts a refresh nonce in the request, as the Refresh .htaccess link does.
	 */
	private function request_refresh_nonce(): void {
		$nonce                = wp_create_nonce( 'zerospam-refresh-htaccess' );
		$_GET['action']       = 'zerospam_refresh_htaccess';
		$_GET['_wpnonce']     = $nonce;
		$_REQUEST['action']   = 'zerospam_refresh_htaccess';
		$_REQUEST['_wpnonce'] = $nonce;
	}

	/**
	 * A user without manage_options cannot refresh .htaccess, even with a nonce.
	 */
	public function test_refresh_htaccess_requires_capability_and_nonce(): void {
		$this->as_role( 'subscriber' );
		$this->request_refresh_nonce();

		$result = $this->run_refresh();

		$this->assertInstanceOf( WPDieException::class, $result, 'The refresh did not stop a subscriber.' );
		$this->assertSame( 403, $result->getCode() );
		$this->assertSame( 0, $this->refreshes, 'refresh_htaccess() ran for a subscriber.' );
	}

	/**
	 * An administrator without a nonce cannot trigger it either.
	 */
	public function test_refresh_htaccess_requires_a_nonce_for_admins(): void {
		$this->as_role( 'administrator' );
		$_GET['action']     = 'zerospam_refresh_htaccess';
		$_REQUEST['action'] = 'zerospam_refresh_htaccess';

		$result = $this->run_refresh();

		$this->assertInstanceOf( WPDieException::class, $result, 'The refresh did not stop a request without a nonce.' );
		$this->assertSame( 0, $this->refreshes, 'refresh_htaccess() ran for an administrator without a nonce.' );
	}

	/**
	 * An administrator with a valid nonce refreshes .htaccess and is sent back
	 * to the Blocked IPs screen with a notice.
	 */
	public function test_refresh_htaccess_runs_for_an_admin_with_a_nonce(): void {
		$this->as_role( 'administrator' );
		$this->request_refresh_nonce();

		$result = $this->run_refresh();

		$this->assertGreaterThan( 0, $this->refreshes, 'refresh_htaccess() did not run for an administrator with a valid nonce.' );
		$this->assertInstanceOf( RedirectException::class, $result );
		$this->assertStringStartsWith( admin_url( 'index.php?page=wordpress-zero-spam-dashboard' ), $result->location );
		$this->assertStringContainsString( 'subview=blocked-ips', $result->location );
		$this->assertStringContainsString( 'zerospam-type=', $result->location );
		$this->assertStringContainsString( 'zerospam-msg=', $result->location );
		$this->assertStringNotContainsString( '_wpnonce', $result->location, 'The redirect kept the nonce in the page URL.' );
	}

	/**
	 * Regression: a Blocked IPs list-table form (search, bulk delete) posted
	 * back to a URL that still carries the old refresh flag must not be
	 * refused or refresh .htaccess.
	 *
	 * The table posts its own bulk nonce as `_wpnonce`, which wins over the
	 * URL's in $_REQUEST; the old admin_init handler then failed
	 * check_admin_referer() with "The link you followed has expired".
	 *
	 * @covers \ZeroSpam\Core\Admin\Dashboard::__construct
	 */
	public function test_list_table_post_with_the_old_refresh_flag_is_not_refused(): void {
		$this->as_role( 'administrator' );
		$this->block( '192.0.2.20' );

		$table = new BlockedTable();
		$table->prepare_items();
		ob_start();
		$table->display();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '/name="_wpnonce" value="([^"]+)"/', $html, $matches ), 'The list table rendered no _wpnonce field.' );

		$_GET  = array(
			'page'                      => 'wordpress-zero-spam-dashboard',
			'subview'                   => 'blocked-ips',
			'zerospam-refresh-htaccess' => '1',
			'_wpnonce'                  => wp_create_nonce( 'zerospam-refresh-htaccess' ),
		);
		$_POST = array(
			'_wpnonce' => $matches[1],
			's'        => '192.0.2.20',
			'paged'    => '1',
		);

		$_REQUEST        = array_merge( $_GET, $_POST );
		$this->refreshes = 0;

		remove_all_actions( 'admin_init' );
		new Dashboard();

		$result = $this->run_handler(
			static function () {
				do_action( 'admin_init' );
			}
		);

		$this->assertNull( $result, 'An admin_init handler refused or redirected the list-table form post.' );
		$this->assertSame( 0, $this->refreshes, 'A list-table form post refreshed .htaccess.' );
	}

	/**
	 * The "Refresh .htaccess" link on the Blocked IPs screen goes to the
	 * admin-post handler with a nonce it accepts.
	 *
	 * @covers \ZeroSpam\Core\Admin\Tables\BlockedTable::extra_tablenav
	 */
	public function test_refresh_link_carries_a_valid_nonce(): void {
		$this->as_role( 'administrator' );
		$this->set_setting( 'block_method', 'htaccess_modern' );

		ob_start();
		( new BlockedTable() )->extra_tablenav( 'top' );
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '/href="([^"]*action=zerospam_refresh_htaccess[^"]*)"/', $html, $matches ), 'The Refresh .htaccess link is missing.' );
		$url   = html_entity_decode( $matches[1] );
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertStringStartsWith( admin_url( 'admin-post.php' ), $url );
		$this->assertArrayNotHasKey( 'zerospam-refresh-htaccess', $query );
		$this->assertNotEmpty( $query['_wpnonce'] ?? '', 'The Refresh .htaccess link has no nonce.' );
		$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'zerospam-refresh-htaccess' ) );
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
