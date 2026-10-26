<?php
/**
 * Tests for zero-spam/v1/ui/help/docs and Documentation.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Documentation;
use ZeroSpam\Tests\TestCase;

/**
 * The Help screen's guides: administrators only, every built-in guide
 * (including the network settings guide the classic tab never listed and
 * the developer hooks), sections other plugins add, and HTML that is always
 * passed through wp_kses_post() on the server, reshaped for the screen
 * (no page-level headings, accordions as <details>, prefixed IDs, external
 * links marked, no emoji).
 *
 * @covers \ZeroSpam\Includes\Rest\Help_Docs_Controller
 * @covers \ZeroSpam\Core\Admin\Documentation
 */
class Test_Rest_Help_Docs extends TestCase {

	/**
	 * Route under test.
	 */
	const ROUTE = '/zero-spam/v1/ui/help/docs';

	/**
	 * Templates written by a test, deleted afterwards.
	 *
	 * @var string[]
	 */
	private array $files = array();

	/**
	 * Removes the filters and temporary templates.
	 */
	public function tear_down(): void {
		remove_all_filters( 'zerospam_documentation_sections' );
		foreach ( $this->files as $file ) {
			wp_delete_file( $file );
		}
		$this->files = array();

		parent::tear_down();
	}

	/**
	 * Writes a template file and returns its path.
	 *
	 * @param string $php Template source.
	 */
	private function template( string $php ): string {
		$file = trailingslashit( get_temp_dir() ) . 'zs-doc-' . wp_generate_password( 8, false ) . '.php';
		file_put_contents( $file, $php ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary test fixture.
		$this->files[] = $file;

		return $file;
	}

	/**
	 * Adds a section through the documented filter.
	 *
	 * @param string $id       Section ID.
	 * @param string $template Template path.
	 * @param int    $priority Priority.
	 */
	private function add_section( string $id, string $template, int $priority = 70 ): void {
		add_filter(
			'zerospam_documentation_sections',
			static function ( $sections ) use ( $id, $template, $priority ) {
				$sections[ $id ] = array(
					'title'    => 'Third-party guide',
					'template' => $template,
					'priority' => $priority,
				);
				return $sections;
			}
		);
	}

	/**
	 * The guides, by ID, as an administrator reads them.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function guides(): array {
		$this->as_role( 'administrator' );
		$response = $this->rest( 'GET', self::ROUTE );
		$this->assertSame( 200, $response->get_status() );

		return array_column( $response->get_data(), null, 'id' );
	}

	/**
	 * Logged out: 401; an editor: 403; an administrator: 200.
	 */
	public function test_administrators_only(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', self::ROUTE )->get_status() );

		$this->as_role( 'editor' );
		$this->assertSame( 403, $this->rest( 'GET', self::ROUTE )->get_status() );

		$this->as_role( 'administrator' );
		$this->assertSame( 200, $this->rest( 'GET', self::ROUTE )->get_status() );
	}

	/**
	 * On a single site every built-in guide but the two network ones is
	 * there, in order, with HTML and its topics, including the developer
	 * hooks.
	 *
	 * @group ms-excluded
	 */
	public function test_every_single_site_guide_is_present(): void {
		$guides = $this->guides();

		$this->assertSame(
			array( 'rest-api', 'api-monitoring', 'lockout-rescue', 'developer-hooks' ),
			array_keys( $guides )
		);
		$this->assert_guides_have_content( $guides );
	}

	/**
	 * On a network the two network guides are listed too, including the
	 * network settings guide the classic tab never listed.
	 *
	 * @group ms-required
	 */
	public function test_every_network_guide_is_present(): void {
		$guides = $this->guides();

		$this->assertSame(
			array( 'rest-api', 'api-monitoring', 'network-statistics', 'lockout-rescue', 'developer-hooks', 'network-settings' ),
			array_keys( $guides )
		);
		$this->assert_guides_have_content( $guides );
		$this->assertSame( 'Network settings and management', $guides['network-settings']['title'] );
		$this->assertStringContainsString( 'Settings Hierarchy', $guides['network-settings']['html'] );
		$this->assertStringContainsString( 'id="zs-doc-network-statistics-cli-network-stats"', $guides['network-statistics']['html'] );
	}

	/**
	 * Each guide has a title, HTML and its topics; the developer hooks list
	 * the login hooks.
	 *
	 * @param array<string, array<string, mixed>> $guides Guides by ID.
	 */
	private function assert_guides_have_content( array $guides ): void {

		foreach ( $guides as $id => $guide ) {
			$this->assertNotSame( '', $guide['title'], "{$id} has a title." );
			$this->assertNotSame( '', $guide['html'], "{$id} has content." );
			$this->assertNotEmpty( $guide['topics'], "{$id} lists its topics." );
		}

		$this->assertStringContainsString( '<code>zerospam_login_skip_check</code>', $guides['developer-hooks']['html'] );
		$this->assertContains( 'Login protection', $guides['developer-hooks']['topics'] );
	}

	/**
	 * The guides are reshaped for the screen: no title of their own, no h1
	 * to h3, accordions as <details>, IDs prefixed, the copy buttons kept,
	 * external links in a new tab that says so, and no emoji.
	 */
	public function test_guides_are_reshaped_for_the_help_screen(): void {
		$guides = $this->guides();
		$all    = implode( "\n", array_column( $guides, 'html' ) );

		$this->assertDoesNotMatchRegularExpression( '#<h[1-3][\s>]#', $all, 'Headings start at h4 (the screen has h1 to h3).' );
		$this->assertStringNotContainsString( 'zerospam-docs-accordion-toggle', $all );
		$this->assertStringNotContainsString( 'zerospam-docs-accordion-btn', $all );
		$this->assertStringContainsString( '<details class="zs-doc-acc"><summary class="zs-doc-acc-s">Quick Start Guide (5 Minutes)</summary>', $guides['rest-api']['html'] );
		$this->assertStringNotContainsString( 'REST API for Settings Management', $guides['rest-api']['html'], 'The guide’s own title is dropped.' );
		$this->assertStringNotContainsString( '<!--', $all );
		$this->assertDoesNotMatchRegularExpression( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $all, 'No emoji (the emoji script would load images from s.w.org).' );

		$this->assertStringContainsString( 'id="zs-doc-api-monitoring-cli-usage"', $guides['api-monitoring']['html'] );
		$this->assertStringContainsString( 'class="zs-doc-copy" type="button" data-clipboard-target="zs-doc-api-monitoring-cli-usage"', $guides['api-monitoring']['html'] );

		$this->assertMatchesRegularExpression(
			'#<a href="https://github\.com/Highfivery/zero-spam-for-wordpress/wiki/Hooks" target="_blank" rel="noopener noreferrer">Hooks page of the GitHub wiki<span class="zs-sr-only"> \(opens in a new tab\)</span></a>#',
			$guides['developer-hooks']['html']
		);
		$this->assertStringContainsString( '└─ zerospam_confidence_min', $guides['rest-api']['html'], 'Non-ASCII text survives as UTF-8.' );
	}

	/**
	 * Whatever a section prints, only post-safe HTML leaves the server:
	 * scripts, styles, frames, event handlers and javascript: links are gone,
	 * and the output is a fixed point of wp_kses_post().
	 */
	public function test_section_html_is_sanitised_on_the_server(): void {
		$template = $this->template(
			'<?php defined( "ABSPATH" ) || die(); ?>'
			. '<div><h2>Third party</h2><p onclick="alert(1)">Hello <b>there</b></p>'
			. '<script>alert(2)</script><style>body{display:none}</style>'
			. '<iframe src="https://example.com/"></iframe>'
			. '<a href="javascript:alert(3)">bad</a><img src="x" onerror="alert(4)">'
			. '<form action="https://example.com/"><input name="x"></form>'
			. '<div class="zerospam-docs-accordion"><button class="zerospam-docs-accordion-toggle" type="button"><span class="zerospam-docs-accordion-icon">▶</span><strong>Part one</strong></button>'
			. '<div class="zerospam-docs-accordion-content"><h3>Inside</h3><p>Body</p></div></div></div>'
		);
		$this->add_section( 'third-party', $template );

		$guides = $this->guides();
		$this->assertArrayHasKey( 'third-party', $guides );
		$html = $guides['third-party']['html'];

		foreach ( array( '<script', 'alert(2)', '<style', '<iframe', 'onclick', 'onerror', 'javascript:', '<form', '<input' ) as $bad ) {
			$this->assertStringNotContainsString( $bad, $html, "{$bad} is removed." );
		}
		$this->assertStringContainsString( 'Hello <b>there</b>', $html );
		$this->assertStringContainsString( '<summary class="zs-doc-acc-s">Part one</summary>', $html );
		$this->assertStringContainsString( '<h4>Inside</h4>', $html );
		$this->assertSame( array( 'Part one' ), $guides['third-party']['topics'] );
		$this->assertSame( wp_kses_post( $html ), $html, 'The output is already what wp_kses_post() lets through.' );
	}

	/**
	 * A section with no title, no readable template or a template that is not
	 * a .php file is left out; a template that fails renders nothing.
	 */
	public function test_invalid_sections_are_left_out(): void {
		$fails = $this->template( '<?php throw new RuntimeException( "broken" );' );
		add_filter(
			'zerospam_documentation_sections',
			static function ( $sections ) use ( $fails ) {
				$sections['no-title'] = array( 'template' => $fails );
				$sections['missing']  = array(
					'title'    => 'Missing',
					'template' => '/nonexistent/doc.php',
				);
				$sections['not-php']  = array(
					'title'    => 'Not PHP',
					'template' => ABSPATH . 'license.txt',
				);
				$sections['fails']    = array(
					'title'    => 'Fails',
					'template' => $fails,
				);
				$sections['first']    = array(
					'title'    => 'First',
					'template' => ZEROSPAM_PATH . 'includes/templates/docs/lockout-rescue.php',
					'priority' => 1,
				);
				return $sections;
			}
		);

		$guides = $this->guides();

		$this->assertArrayNotHasKey( 'no-title', $guides );
		$this->assertArrayNotHasKey( 'missing', $guides );
		$this->assertArrayNotHasKey( 'not-php', $guides );
		$this->assertSame( '', $guides['fails']['html'] );
		$this->assertSame( 'first', array_key_first( $guides ), 'Sorted by priority.' );
	}

	/**
	 * The filter can remove a built-in guide.
	 */
	public function test_filter_can_remove_a_guide(): void {
		add_filter(
			'zerospam_documentation_sections',
			static function ( $sections ) {
				unset( $sections['api-monitoring'] );
				return $sections;
			}
		);

		$this->assertArrayNotHasKey( 'api-monitoring', $this->guides() );
	}

	/**
	 * Documentation::prepare() sanitises any HTML handed to it, script source included.
	 */
	public function test_prepare_always_sanitises(): void {
		$this->assertSame( '<p>Safe</p>', Documentation::prepare( '<p>Safe</p><script>alert(1)</script>' ) );
		$this->assertSame( '', Documentation::prepare( '' ) );
	}
}
