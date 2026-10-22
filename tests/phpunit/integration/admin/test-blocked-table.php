<?php
/**
 * Tests for the admin Blocked IPs table.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Tables\BlockedTable;
use ZeroSpam\Tests\TestCase;

/**
 * Rows of the Blocked IPs screen render safely and prefill the Update Block
 * modal correctly.
 *
 * @covers \ZeroSpam\Core\Admin\Tables\BlockedTable
 */
class Test_Blocked_Table extends TestCase {

	/**
	 * Loads the dashboard screen as an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen( 'dashboard_page_wordpress-zero-spam-dashboard' );
		$this->as_role( 'administrator' );
	}

	/**
	 * Renders the table's rows, collecting PHP deprecations and notices.
	 *
	 * @param string[] $errors Collected error messages (by reference).
	 * @return string Rows HTML.
	 */
	private function render_rows( array &$errors ): string {
		$table = new BlockedTable();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Collects deprecations raised while the rows render.
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ): bool {
				$errors[] = $errstr;

				return true;
			},
			E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_USER_NOTICE | E_WARNING | E_USER_WARNING
		);

		try {
			$table->prepare_items();
			ob_start();
			$table->display_rows();
			$html = (string) ob_get_clean();
		} finally {
			restore_error_handler();
		}

		return $html;
	}

	/**
	 * A permanent block has no end date: the modal gets an empty end date
	 * (not 1970-01-01) and strtotime() is never handed NULL.
	 */
	public function test_permanent_block_row_has_an_empty_end_date(): void {
		$this->block( '192.0.2.30', 'permanent', '2026-10-01 08:30:00' );

		$errors = array();
		$html   = $this->render_rows( $errors );

		$this->assertStringContainsString( 'data-ip="192.0.2.30"', $html );
		$this->assertStringContainsString( 'data-end=""', $html );
		$this->assertStringContainsString( 'data-start="2026-10-01T08:30"', $html );
		$this->assertStringNotContainsString( '1970-01-01', $html );
		$this->assertSame( array(), $errors, 'Rendering a permanent block raised PHP errors.' );
	}

	/**
	 * A temporary block keeps its end date in the modal data.
	 */
	public function test_temporary_block_row_keeps_its_end_date(): void {
		$this->block( '192.0.2.31', 'temporary', '2026-10-01 08:30:00', '2026-11-01 17:45:00' );

		$errors = array();
		$html   = $this->render_rows( $errors );

		$this->assertStringContainsString( 'data-end="2026-11-01T17:45"', $html );
		$this->assertSame( array(), $errors );
	}

	/**
	 * Free-text columns are escaped and the row checkbox value is an integer.
	 */
	public function test_columns_escape_their_values(): void {
		$table = new BlockedTable();

		$this->assertSame( '&lt;b&gt;spam&lt;/b&gt;', $table->column_default( array( 'reason' => '<b>spam</b>' ), 'reason' ) );
		$this->assertSame(
			'<input type="checkbox" name="ids[]" value="7" />',
			$table->column_cb( array( 'blocked_id' => '7"><script>' ) )
		);
	}
}
