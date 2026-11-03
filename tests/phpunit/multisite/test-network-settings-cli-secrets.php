<?php
/**
 * Tests that the network WP-CLI commands never print a secret in clear.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\CLI\Network_Settings_CLI;
use ZeroSpam\Tests\TestCase;

require_once dirname( __DIR__ ) . '/includes/stubs/wp-cli.php';
require_once dirname( __DIR__ ) . '/includes/stubs/wp-cli-utils.php';
require_once ZEROSPAM_PATH . 'includes/cli/class-networksettingscli.php';

/**
 * `wp zerospam network-settings export` leaves secrets out, as the REST
 * export does; list, compare and overrides show a secret only as its hint.
 * In 5.x they printed license keys and webhook addresses in clear.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\CLI\Network_Settings_CLI
 */
class Test_Network_Settings_CLI_Secrets extends TestCase {

	/**
	 * The network license key.
	 */
	const SECRET = 'cli-network-secret-key-9876';

	/**
	 * A super admin; a secret and an ordinary setting on the network and a site override of the secret.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->as_role( 'administrator' );
		grant_super_admin( get_current_user_id() );
		$this->set_network_setting( 'zerospam_license', self::SECRET );
		$this->set_network_setting( 'max_logs', 900 );
		update_option( 'zero-spam-zerospam', array( 'zerospam_license' => 'cli-site-secret-key-5432' ) );
		WP_CLI::reset();
	}

	/**
	 * Everything the command printed.
	 */
	private function output(): string {
		return (string) wp_json_encode( WP_CLI::$messages );
	}

	/**
	 * The export leaves the secret out and keeps the rest.
	 */
	public function test_export_leaves_secrets_out(): void {
		( new Network_Settings_CLI() )->export( array(), array() );

		$this->assertStringNotContainsString( self::SECRET, $this->output() );
		$json = json_decode( WP_CLI::of( 'line' )[0], true );
		$this->assertArrayHasKey( 'max_logs', $json );
		$this->assertArrayNotHasKey( 'zerospam_license', $json );
	}

	/**
	 * List and compare print the hint only.
	 */
	public function test_list_and_compare_mask_secrets(): void {
		( new Network_Settings_CLI() )->list_settings( array(), array( 'format' => 'json' ) );
		( new Network_Settings_CLI() )->compare( array(), array( 'format' => 'json' ) );

		$this->assertStringNotContainsString( self::SECRET, $this->output() );
		foreach ( WP_CLI::of( 'line' ) as $line ) {
			$row = json_decode( $line, true )[0];
			$this->assertSame( 'zerospam_license', $row['Setting'] );
			$this->assertSame( "\u{2022}\u{2022}\u{2022}\u{2022}9876", $row['Value'] ?? $row['Network Value'] );
		}
	}

	/**
	 * A site's own secret is masked too.
	 */
	public function test_overrides_mask_secrets(): void {
		( new Network_Settings_CLI() )->overrides( array( 'zerospam_license' ), array( 'format' => 'json' ) );

		$this->assertStringNotContainsString( 'cli-site-secret-key-5432', $this->output() );
	}
}
