<?php
/**
 * Tests for the blocked, rescue, expired-link and comment-blocked pages.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Access;
use ZeroSpam\Core\Front\Block_Page;
use ZeroSpam\Core\Rescue_Link;
use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\Stubs;
use ZeroSpam\Tests\TestCase;

/**
 * The pages Block_Page renders: what they show, the status and headers they
 * send, what they leave out (JavaScript, third-party requests) and the
 * template override filter.
 *
 * @covers \ZeroSpam\Core\Front\Block_Page
 * @covers \ZeroSpam\Core\Access
 * @covers \ZeroSpam\Core\Rescue_Link
 */
class Test_Block_Page extends TestCase {

	/**
	 * Temporary template files written by a test.
	 *
	 * @var string[]
	 */
	private array $temp_files = array();

	/**
	 * Resets the request and the stylesheet location.
	 */
	public function set_up(): void {
		parent::set_up();

		$_POST              = array();
		$_REQUEST           = array();
		$GLOBALS['pagenow'] = 'index.php';
		self::set_static( Block_Page::class, 'dist_dir', '' );
		self::set_static( Block_Page::class, 'stylesheets', array() );
	}

	/**
	 * Removes temporary files and restores the request globals.
	 */
	public function tear_down(): void {
		foreach ( $this->temp_files as $file ) {
			wp_delete_file( $file );
		}
		$_POST    = array();
		$_REQUEST = array();
		self::set_static( Block_Page::class, 'dist_dir', '' );
		self::set_static( Block_Page::class, 'stylesheets', array() );
		$GLOBALS['wp_locale']->text_direction = 'ltr';

		parent::tear_down();
	}

	/**
	 * Runs code that ends the request with a page and returns what wp_die() received.
	 *
	 * @param callable $callback Code that ends the request.
	 * @return array{message: string, code: int}
	 */
	private function page( callable $callback ): array {
		try {
			$callback();
		} catch ( WPDieException $e ) {
			return array(
				'message' => $e->getMessage(),
				'code'    => (int) $e->getCode(),
			);
		}

		$this->fail( 'The request was not ended with a page.' );
	}

	/**
	 * The full document Block_Page's wp_die() handler sends for a page, with the headers it sent.
	 *
	 * @param array{message: string, code: int} $page  Page from page().
	 * @param string                            $title Page title.
	 * @return array{html: string, headers: string[], status: int}
	 */
	private function document( array $page, string $title = 'Blocked' ): array {
		$status = 0;
		$spy    = static function ( $header, $code ) use ( &$status ) {
			$status = (int) $code;

			return $header;
		};
		add_filter( 'status_header', $spy, 10, 2 );

		ob_start();
		try {
			Block_Page::die_handler(
				$page['message'],
				$title,
				array(
					'response' => $page['code'],
					'exit'     => false,
				)
			);
		} finally {
			$html = (string) ob_get_clean();
			remove_filter( 'status_header', $spy, 10 );
		}

		return array(
			'html'    => $html,
			'headers' => Stubs::$headers,
			'status'  => $status,
		);
	}

	/**
	 * Ends the request the way a blocked visitor's request ends.
	 *
	 * @return array{message: string, code: int}
	 */
	private function blocked_page(): array {
		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		return $this->page( array( $access, 'run_block_check' ) );
	}

	/**
	 * Opens a rescue link (GET) and returns the page, or null when the request continues.
	 *
	 * @param string $token Token.
	 * @return array{message: string, code: int}|null
	 */
	private function open_link( string $token ): ?array {
		$this->set_request( '/?' . Rescue_Link::TOKEN_PARAM . '=' . rawurlencode( $token ) );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			( new Access() )->init();
		} catch ( WPDieException $e ) {
			return array(
				'message' => $e->getMessage(),
				'code'    => (int) $e->getCode(),
			);
		}

		return null;
	}

	/**
	 * Presses "Let me back in" (POST of the token).
	 *
	 * @param string $token Token.
	 * @return array{message: string, code: int}|string|null The page shown, where it redirected, or null.
	 */
	private function confirm_link( string $token ) {
		$this->set_request( '/' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array( Rescue_Link::TOKEN_PARAM => $token );
		$_REQUEST                  = $_POST;
		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			},
			1,
			2
		);

		try {
			( new Access() )->init();
		} catch ( RedirectException $e ) {
			return $e->getMessage();
		} catch ( WPDieException $e ) {
			return array(
				'message' => $e->getMessage(),
				'code'    => (int) $e->getCode(),
			);
		} finally {
			$_POST    = array();
			$_REQUEST = array();
		}

		return null;
	}

	/**
	 * A valid rescue token, as an emailed link carries.
	 */
	private function issue_token(): string {
		return Rescue_Link::issue_token( self::DEFAULT_IP );
	}

	/**
	 * Parses an HTML fragment or document.
	 *
	 * @param string $html Markup.
	 */
	private function parse( string $html ): DOMXPath {
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( false === stripos( $html, '<html' ) ? '<?xml encoding="utf-8"?><body>' . $html . '</body>' : '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		return new DOMXPath( $document );
	}

	/**
	 * Every page this class renders, as wp_die() receives it.
	 *
	 * @return array<string, array{message: string, code: int}>
	 */
	private function every_page(): array {
		$this->block( self::DEFAULT_IP );

		$pages = array(
			'blocked'         => $this->blocked_page(),
			'rescue-confirm'  => $this->open_link( $this->issue_token() ),
			'rescue-expired'  => $this->open_link( str_repeat( 'ab', 32 ) ),
			'comment-blocked' => $this->page( static fn () => Block_Page::comment_blocked( 'Spam detected.', home_url( '/?p=1#respond' ) ) ),
			'rescue-sent'     => $this->page(
				function (): void {
					$this->set_request( '/wp-admin/admin-post.php' );
					$_SERVER['REQUEST_METHOD'] = 'POST';
					$_POST                     = array(
						'action'                 => Rescue_Link::ACTION,
						Rescue_Link::NONCE_FIELD => wp_create_nonce( Rescue_Link::NONCE_ACTION ),
						Rescue_Link::EMAIL_FIELD => 'nobody@example.com',
					);
					Rescue_Link::handle_request();
				}
			),
			'form-error'      => $this->page(
				function (): void {
					$this->set_request( '/wp-admin/admin-post.php', '192.0.2.77' );
					$_SERVER['REQUEST_METHOD'] = 'POST';
					$_POST                     = array(
						'action'                 => Rescue_Link::ACTION,
						Rescue_Link::NONCE_FIELD => wp_create_nonce( Rescue_Link::NONCE_ACTION ),
						Rescue_Link::EMAIL_FIELD => 'not an email',
					);
					Rescue_Link::handle_request();
				}
			),
		);

		foreach ( $pages as $name => $page ) {
			$this->assertIsArray( $page, "The {$name} page was not shown." );
		}

		return $pages;
	}

	/**
	 * The blocked page: 403, the message, the visitor's details and the collapsed rescue form.
	 */
	public function test_blocked_page_shows_the_message_details_and_rescue_form(): void {
		$this->set_setting( 'log_blocked_ips', 'enabled', 'settings' );
		$this->set_setting( 'blocked_message', 'Custom <strong>blocked</strong> message.', 'settings' );
		$this->block( self::DEFAULT_IP );

		$page = $this->blocked_page();

		$this->assertSame( 403, $page['code'] );
		$xpath = $this->parse( $page['message'] );
		$this->assertSame( 1, $xpath->query( '//h1' )->length );
		$this->assertSame( 'Blocked. Your connection was flagged.', $xpath->evaluate( 'normalize-space(//h1)' ) );
		$this->assertStringContainsString( 'Custom <strong>blocked</strong> message.', $page['message'] );

		$this->assertSame( self::DEFAULT_IP, $xpath->evaluate( 'normalize-space(//dl/dt[.="Your IP"]/following-sibling::dd[1])' ) );
		global $wpdb;
		$log_id = (int) $wpdb->get_var( "SELECT MAX(log_id) FROM `{$this->log_table()}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name only.
		$this->assertSame( '#' . $log_id, $xpath->evaluate( 'normalize-space(//dl/dt[.="Reference"]/following-sibling::dd[1])' ) );
		$this->assertNotSame( '', $xpath->evaluate( 'normalize-space(//dl/dt[.="Time"]/following-sibling::dd[1])' ) );

		$details = $xpath->query( '//details' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $details );
		$this->assertFalse( $details->hasAttribute( 'open' ) );
		$this->assertSame( 'Help for site owners', $xpath->evaluate( 'normalize-space(//details/summary)' ) );
	}

	/**
	 * The reference is the log row's ID even when pruning (which reads the settings) inserts other rows first.
	 */
	public function test_reference_survives_inserts_during_pruning(): void {
		$this->set_setting( 'log_blocked_ips', 'enabled', 'settings' );
		$this->block( self::DEFAULT_IP );
		add_filter(
			'zerospam_settings',
			static function ( $settings ) {
				add_option( 'zerospam_test_insert_' . wp_generate_password( 8, false ), 'x' );

				return $settings;
			}
		);

		$xpath = $this->parse( $this->blocked_page()['message'] );

		global $wpdb;
		$log_id = (int) $wpdb->get_var( "SELECT MAX(log_id) FROM `{$this->log_table()}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name only.
		$this->assertGreaterThan( 0, $log_id );
		$this->assertSame( '#' . $log_id, $xpath->evaluate( 'normalize-space(//dl/dt[.="Reference"]/following-sibling::dd[1])' ) );
	}

	/**
	 * Without logging there is no log row, so no reference is shown.
	 */
	public function test_blocked_page_without_logging_has_no_reference(): void {
		$this->block( self::DEFAULT_IP );

		$xpath = $this->parse( $this->blocked_page()['message'] );

		$this->assertSame( 0, $xpath->query( '//dl/dt[.="Reference"]' )->length );
		$this->assertSame( 1, $xpath->query( '//dl/dt[.="Your IP"]' )->length );
	}

	/**
	 * The administrator's blocked message keeps the post allowlist: no scripts, styles, event handlers or form controls.
	 *
	 * @dataProvider data_message_payloads
	 *
	 * @param string $payload   Stored message.
	 * @param string $forbidden Text that must not reach the page.
	 */
	public function test_blocked_message_is_filtered( string $payload, string $forbidden ): void {
		$this->set_setting( 'blocked_message', 'Before ' . $payload . ' <em>after</em>', 'settings' );
		$this->block( self::DEFAULT_IP );

		$page     = $this->blocked_page();
		$document = $this->document( $page )['html'];

		foreach ( array( $page['message'], $document ) as $html ) {
			$this->assertStringNotContainsStringIgnoringCase( $forbidden, $html );
			$this->assertStringContainsString( '<em>after</em>', $html );
		}
		$this->assertStringNotContainsStringIgnoringCase( '<script', $document );
	}

	/**
	 * Markup an administrator (or an attacker with their account) could save as the blocked message.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_message_payloads(): array {
		return array(
			'script'        => array( '<script>alert(1)</script>', '<script' ),
			'style'         => array( '<style>body { display: none; }</style>', '<style>body' ),
			'onerror'       => array( '<img src="x" onerror="alert(1)">', 'onerror' ),
			'javascript'    => array( '<a href="javascript:alert(1)">x</a>', 'javascript:' ),
			'form control'  => array( '<input type="text" name="injected">', 'name="injected"' ),
			'iframe'        => array( '<iframe src="https://example.com/"></iframe>', '<iframe' ),
			'closing style' => array( '</style><b>x</b>', '</style><b>' ),
		);
	}

	/**
	 * The document: 403, uncached, not indexed, the site's language and direction, the stylesheet inlined.
	 */
	public function test_document_status_headers_language_and_inline_styles(): void {
		$this->block( self::DEFAULT_IP );
		$sent = $this->document( $this->blocked_page() );
		$html = $sent['html'];

		$this->assertSame( 403, $sent['status'] );
		$this->assertContains( 'X-Robots-Tag: noindex, nofollow', $sent['headers'] );
		$this->assertContains( 'Content-Type: text/html; charset=utf-8', $sent['headers'] );
		$this->assertContains( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private', $sent['headers'] );
		$this->assertNotEmpty( preg_grep( "/^Content-Security-Policy: .*script-src 'none'/", $sent['headers'] ), 'No policy forbids scripts.' );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );

		$this->assertStringStartsWith( '<!DOCTYPE html>', $html );
		$this->assertMatchesRegularExpression( '/<html [^>]*lang="en-US"/', $html );
		$this->assertMatchesRegularExpression( '/<html [^>]*dir="ltr"/', $html );
		$this->assertStringContainsString( '<meta name="robots" content="noindex, nofollow">', $html );
		$this->assertStringContainsString( '<meta name="viewport" content="width=device-width, initial-scale=1">', $html );
		$this->assertStringContainsString( '<title>Blocked</title>', $html );

		$css = trim( (string) file_get_contents( ZEROSPAM_PATH . 'assets/dist/blocked.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$this->assertNotSame( '', $css );
		$this->assertStringContainsString( $css, $html, 'blocked.css is not inlined.' );
		$this->assertStringContainsString( 'prefers-color-scheme:dark', $html );
		$this->assertStringContainsString( 'prefers-reduced-motion', $html );

		$xpath = $this->parse( $html );
		$this->assertSame( 1, $xpath->query( '//body[contains(@class,"zs-blocked")]' )->length );
		$this->assertSame( 1, $xpath->query( '//main' )->length );
		$this->assertSame( 'Blocked. Your connection was flagged.', $xpath->evaluate( 'normalize-space(//main//h1)' ) );
		$this->assertSame( 0, $xpath->query( '//link' )->length, 'The page loads a stylesheet.' );
	}

	/**
	 * Right-to-left sites get dir="rtl" and the right-to-left stylesheet.
	 */
	public function test_document_follows_right_to_left_languages(): void {
		$GLOBALS['wp_locale']->text_direction = 'rtl';
		$this->block( self::DEFAULT_IP );

		$html = $this->document( $this->blocked_page() )['html'];

		$this->assertMatchesRegularExpression( '/<html [^>]*dir="rtl"/', $html );
		$this->assertStringContainsString( trim( (string) file_get_contents( ZEROSPAM_PATH . 'assets/dist/blocked-rtl.css' ) ), $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	}

	/**
	 * Without the built stylesheet the page still renders, unstyled.
	 */
	public function test_missing_stylesheet_renders_an_unstyled_page(): void {
		self::set_static( Block_Page::class, 'dist_dir', '/nonexistent/zero-spam-dist/' );
		$this->block( self::DEFAULT_IP );

		$sent = $this->document( $this->blocked_page() );

		$this->assertSame( 403, $sent['status'] );
		$this->assertStringContainsString( 'Your connection was flagged.', $sent['html'] );
		$this->assertStringNotContainsString( '--zs-bg', $sent['html'] );
		$this->assertStringContainsString( '<form', $sent['html'] );
	}

	/**
	 * No page carries JavaScript or makes a request to another site; the only file it loads is the plugin's own font.
	 */
	public function test_no_page_has_javascript_or_third_party_requests(): void {
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$font      = plugins_url( 'assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin.woff2', ZEROSPAM );

		foreach ( $this->every_page() as $name => $page ) {
			Stubs::$headers = array();
			$html           = $this->document( $page )['html'];

			$this->assertStringNotContainsStringIgnoringCase( '<script', $html, "{$name}: has a script." );
			$this->assertDoesNotMatchRegularExpression( '/\son[a-z]+\s*=/i', $html, "{$name}: has an event handler attribute." );
			$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $html, "{$name}: has a javascript: URL." );
			$this->assertDoesNotMatchRegularExpression( '/<(img|iframe|link|embed|object|video|audio|source)\b/i', $html, "{$name}: loads an external resource." );
			$this->assertDoesNotMatchRegularExpression( '/@import/i', $html, "{$name}: imports a stylesheet." );

			preg_match_all( '/url\(\s*["\']?([^"\')\s]+)/i', $html, $urls );
			$this->assertSame( array( $font ), array_values( array_unique( $urls[1] ) ), "{$name}: CSS loads something other than the plugin's font." );
			$this->assertSame( $home_host, wp_parse_url( $font, PHP_URL_HOST ) );

			preg_match_all( '/\saction="([^"]*)"/i', $html, $actions );
			foreach ( $actions[1] as $action ) {
				$this->assertSame( $home_host, wp_parse_url( html_entity_decode( $action ), PHP_URL_HOST ), "{$name}: a form posts to another site." );
			}
		}
	}

	/**
	 * The plugin's font is the single latin file, under 30 KB.
	 */
	public function test_the_only_font_is_small(): void {
		$file = ZEROSPAM_PATH . 'assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin.woff2';

		$this->assertFileExists( $file );
		$this->assertLessThanOrEqual( 30 * 1024, filesize( $file ) );
	}

	/**
	 * Rendering a page adds no database queries beyond the block check itself.
	 */
	public function test_rendering_adds_no_queries(): void {
		$this->block( self::DEFAULT_IP );
		$this->blocked_page();
		Block_Page::template( 'blocked', array( 'message' => 'x' ) );

		$queries = $this->capture_queries(
			function (): void {
				$this->page( static fn () => Block_Page::blocked( 'Blocked.', array( 'ip' => self::DEFAULT_IP ) ) );
				$this->page( static fn () => Block_Page::comment_blocked( 'Spam detected.', home_url( '/' ) ) );
			}
		);

		$this->assertSame( array(), $queries );
	}

	/**
	 * The rescue form keeps its labelled field, hidden fields and the FAQ link; it is collapsed on the blocked page.
	 */
	public function test_rescue_form_markup(): void {
		$this->block( self::DEFAULT_IP );
		$xpath = $this->parse( $this->blocked_page()['message'] );

		$form = $xpath->query( '//details//form' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $form );
		$this->assertSame( 'post', $form->getAttribute( 'method' ) );
		$this->assertSame( admin_url( 'admin-post.php' ), $form->getAttribute( 'action' ) );

		$input = $xpath->query( '//form//input[@type="email"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertSame( Rescue_Link::EMAIL_FIELD, $input->getAttribute( 'name' ) );
		$this->assertTrue( $input->hasAttribute( 'required' ) );
		$this->assertSame( 'email', $input->getAttribute( 'autocomplete' ) );
		$this->assertSame( 'Administrator email address', $xpath->evaluate( 'normalize-space(//label[@for="' . $input->getAttribute( 'id' ) . '"])' ) );
		$help = $xpath->query( '//*[@id="' . $input->getAttribute( 'aria-describedby' ) . '"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $help, 'The field is not described by the help text.' );

		$this->assertSame( 1, $xpath->query( '//form//input[@type="hidden"][@name="action"][@value="' . Rescue_Link::ACTION . '"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form//input[@type="hidden"][@name="' . Rescue_Link::NONCE_FIELD . '"]' )->length );
		$this->assertSame( 'Email me a rescue link', $xpath->evaluate( 'normalize-space(//form//button[@type="submit"])' ) );
		$this->assertSame( 1, $xpath->query( '//details//a[@href="https://wordpress.org/plugins/zero-spam/#faq"]' )->length );
		$this->assertSame( 0, $xpath->query( '//details//svg[not(@aria-hidden="true")]' )->length, 'An icon is not hidden from assistive technology.' );
	}

	/**
	 * The "Check your email" page answers every valid request the same way.
	 */
	public function test_sent_page(): void {
		$page  = $this->every_page()['rescue-sent'];
		$xpath = $this->parse( $page['message'] );

		$this->assertSame( 200, $page['code'] );
		$this->assertSame( 'Check your email. A rescue link is on its way.', $xpath->evaluate( 'normalize-space(//h1)' ) );
		$this->assertStringContainsString( 'If that address belongs to a site administrator, a rescue link has been sent.', $page['message'] );
		$this->assertStringContainsString( 'It can take a few minutes to arrive.', $page['message'] );
		$this->assertSame( 0, $xpath->query( '//form' )->length );
	}

	/**
	 * The form error page: the form, open, with the error tied to the field.
	 */
	public function test_form_error_page(): void {
		$page  = $this->every_page()['form-error'];
		$xpath = $this->parse( $page['message'] );

		$this->assertSame( 400, $page['code'] );
		$this->assertSame( 1, $xpath->query( '//h1' )->length );
		$details = $xpath->query( '//details' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $details );
		$this->assertTrue( $details->hasAttribute( 'open' ) );
		$input = $xpath->query( '//input[@id="zerospam-rescue-email"]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertSame( 'true', $input->getAttribute( 'aria-invalid' ) );
		$this->assertTrue( $input->hasAttribute( 'autofocus' ) );
		$this->assertStringContainsString( 'zerospam-rescue-email-error', $input->getAttribute( 'aria-describedby' ) );
		$this->assertSame( 'Enter an email address, for example name@example.com.', $xpath->evaluate( 'normalize-space(//*[@id="zerospam-rescue-email-error"][@role="alert"])' ) );
		$this->assertSame( 'not an email', $input->getAttribute( 'value' ) );
	}

	/**
	 * The form error page shows the blocked message through the same post allowlist.
	 *
	 * @dataProvider data_message_payloads
	 *
	 * @param string $payload   Stored message.
	 * @param string $forbidden Text that must not reach the page.
	 */
	public function test_form_error_page_filters_the_blocked_message( string $payload, string $forbidden ): void {
		$this->set_setting( 'blocked_message', 'Before ' . $payload . ' <em>after</em>', 'settings' );

		$page = $this->every_page()['form-error'];

		$this->assertStringNotContainsStringIgnoringCase( $forbidden, $page['message'] );
		$this->assertStringContainsString( '<em>after</em>', $page['message'] );
	}

	/**
	 * Opening a valid link shows the "Let me back in" page; its button posts the token, as before.
	 */
	public function test_confirm_page_and_post_are_unchanged(): void {
		$token = $this->issue_token();
		$page  = $this->open_link( $token );

		$this->assertIsArray( $page );
		$this->assertSame( 200, $page['code'] );
		$xpath = $this->parse( $page['message'] );
		$this->assertSame( 'Let me back in. One click, then log in.', $xpath->evaluate( 'normalize-space(//h1)' ) );
		$form = $xpath->query( '//form' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $form );
		$this->assertSame( 'post', $form->getAttribute( 'method' ) );
		$this->assertSame( home_url( '/' ), $form->getAttribute( 'action' ) );
		$this->assertSame( 1, $xpath->query( '//form//input[@type="hidden"][@name="' . Rescue_Link::TOKEN_PARAM . '"][@value="' . $token . '"]' )->length );
		$this->assertSame( 'Let me back in', $xpath->evaluate( 'normalize-space(//form//button[@type="submit"])' ) );
		$this->assertNotFalse( get_transient( Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) ), 'Opening the link used the token.' );

		$this->assertSame( wp_login_url(), $this->confirm_link( $token ) );
		$this->assertCount( 1, Stubs::$cookies );
	}

	/**
	 * A used link shows the expired page, with a form to ask for a new one, instead of the site or the blocked page.
	 */
	public function test_used_link_shows_the_expired_page(): void {
		$this->block( self::DEFAULT_IP );
		$token = $this->issue_token();
		$this->assertSame( wp_login_url(), $this->confirm_link( $token ) );
		Stubs::$cookies = array();
		unset( $_COOKIE[ Access::RESCUE_COOKIE ] );

		foreach ( array(
			'GET'  => $this->open_link( $token ),
			'POST' => $this->confirm_link( $token ),
		) as $method => $page ) {
			$this->assertIsArray( $page, "{$method} of a used link showed no page." );
			$this->assertSame( 403, $page['code'] );
			$xpath = $this->parse( $page['message'] );
			$this->assertSame( 'This link has expired. Ask for a new one.', $xpath->evaluate( 'normalize-space(//h1)' ) );
			$details = $xpath->query( '//details' )->item( 0 );
			$this->assertInstanceOf( DOMElement::class, $details );
			$this->assertTrue( $details->hasAttribute( 'open' ), 'The form to ask for a new link is collapsed.' );
			$this->assertSame( 1, $xpath->query( '//details//form[@action="' . admin_url( 'admin-post.php' ) . '"]//input[@name="' . Rescue_Link::EMAIL_FIELD . '"]' )->length );
			$this->assertSame( 1, $xpath->query( '//details//form//input[@type="hidden"][@name="action"][@value="' . Rescue_Link::ACTION . '"]' )->length, 'The new-link form does not post the rescue action.' );
			$nonce = $xpath->query( '//details//form//input[@type="hidden"][@name="' . Rescue_Link::NONCE_FIELD . '"]' )->item( 0 );
			$this->assertInstanceOf( DOMElement::class, $nonce );
			$this->assertSame( 1, wp_verify_nonce( $nonce->getAttribute( 'value' ), Rescue_Link::NONCE_ACTION ), 'The new-link form carries no valid nonce.' );
			$this->assertStringNotContainsString( $token, $page['message'], 'The used token is repeated on the page.' );
		}

		$this->assertSame( array(), Stubs::$cookies, 'A used link set the rescue cookie.' );
	}

	/**
	 * A link past its 15 minutes shows the expired page too, for a visitor who is not blocked as well.
	 */
	public function test_expired_link_shows_the_expired_page(): void {
		$token  = $this->issue_token();
		$key    = Rescue_Link::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token );
		$stored = get_transient( $key );
		$this->assertIsArray( $stored );
		$stored['expires'] = time() - 1;
		set_transient( $key, $stored, MINUTE_IN_SECONDS );

		$page = $this->open_link( $token );

		$this->assertIsArray( $page );
		$this->assertSame( 403, $page['code'] );
		$this->assertStringContainsString( 'This link has expired.', $page['message'] );

		$confirm = $this->confirm_link( $token );
		$this->assertIsArray( $confirm, 'An expired link worked or showed nothing.' );
		$this->assertStringContainsString( 'This link has expired.', $confirm['message'] );
		$this->assertSame( array(), Stubs::$cookies );
	}

	/**
	 * Malformed tokens are still ignored: the request continues as usual.
	 *
	 * @dataProvider data_malformed_tokens
	 *
	 * @param string $token Token.
	 */
	public function test_malformed_tokens_are_ignored( string $token ): void {
		$this->assertNull( $this->open_link( $token ) );
		$this->assertNull( $this->confirm_link( $token ) );
	}

	/**
	 * Values that can never be a token.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_malformed_tokens(): array {
		return array(
			'too short' => array( 'abc123' ),
			'not hex'   => array( str_repeat( 'zz', 32 ) ),
			'empty'     => array( '' ),
		);
	}

	/**
	 * Without the rescue form, the expired page explains and offers no form.
	 */
	public function test_expired_page_without_the_rescue_form(): void {
		add_filter( 'zerospam_rescue_form_enabled', '__return_false' );

		$page = $this->open_link( str_repeat( 'cd', 32 ) );

		$this->assertIsArray( $page );
		$this->assertStringContainsString( 'This link has expired.', $page['message'] );
		$this->assertStringNotContainsString( '<form', $page['message'] );
	}

	/**
	 * A comment flagged as spam: 403 as before, the comment message and a way back to the post.
	 */
	public function test_comment_blocked_page(): void {
		$this->set_setting( 'verify_comments', 'enabled', 'comments' );
		$this->set_setting( 'comment_spam_message', 'Your <strong>comment</strong> looks like spam.<script>alert(1)</script>', 'comments' );
		$post_id = self::factory()->post->create();
		$_POST   = array( \ZeroSpam\Core\Utilities::get_honeypot() => 'i-am-a-bot' );

		$page = $this->page(
			static function () use ( $post_id ): void {
				( new Comments() )->preprocess_comments(
					array(
						'comment_post_ID'      => $post_id,
						'comment_author'       => 'Bot',
						'comment_author_email' => 'bot@example.com',
						'comment_content'      => 'Buy now',
					)
				);
			}
		);

		$this->assertSame( 403, $page['code'] );
		$xpath = $this->parse( $page['message'] );
		$this->assertSame( 'Comment not posted. It was flagged as spam.', $xpath->evaluate( 'normalize-space(//h1)' ) );
		$this->assertStringContainsString( 'Your <strong>comment</strong> looks like spam.', $page['message'] );
		$this->assertStringNotContainsString( '<script', $page['message'] );
		$this->assertSame( get_permalink( $post_id ) . '#respond', $xpath->evaluate( 'string(//a[normalize-space(.)="Go back"]/@href)' ) );
		$this->assertSame( 0, $xpath->query( '//form' )->length );
	}

	/**
	 * Other callers of terminate_execution() (spam detections) get their title as the heading and no rescue form.
	 */
	public function test_terminate_execution_without_rescue(): void {
		$page = $this->page( static fn () => Access::terminate_execution( 'Spam <b>detected</b>', 'Your message was <em>flagged</em>.' ) );

		$this->assertSame( 403, $page['code'] );
		$xpath = $this->parse( $page['message'] );
		$this->assertSame( 'Spam <b>detected</b>', $xpath->evaluate( 'normalize-space(//h1)' ) );
		$this->assertStringContainsString( 'Your message was <em>flagged</em>.', $page['message'] );
		$this->assertStringNotContainsString( '<form', $page['message'] );
	}

	/**
	 * terminate_execution() with the rescue form uses the caller's title for the page and still sends no-cache headers.
	 */
	public function test_terminate_execution_with_rescue_keeps_title_and_no_cache(): void {
		$title = '';
		add_filter(
			'wp_die_handler',
			static function () use ( &$title ) {
				return static function ( $message, $page_title, $args ) use ( &$title ) {
					$title = (string) $page_title;
					throw new WPDieException( (string) $message, (int) $args['response'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the test.
				};
			},
			20
		);

		$page = $this->page( static fn () => Access::terminate_execution( 'Custom title', 'Blocked here.', 403, true ) );

		$this->assertSame( 403, $page['code'] );
		$this->assertSame( 'Custom title', $title );
		$this->assertStringContainsString( '<form', $page['message'] );
		$this->assertContains( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private', Stubs::$headers, 'No-cache headers were not sent before a custom wp_die() handler.' );
		$this->assertContains( 'X-Robots-Tag: noindex, nofollow', Stubs::$headers );
	}

	/**
	 * The zerospam_block_page_template filter lets a theme replace a template; an unreadable path falls back.
	 */
	public function test_template_filter_overrides_a_template(): void {
		$file               = wp_tempnam( 'zerospam-blocked' ) . '.php';
		$this->temp_files[] = $file;
		file_put_contents( $file, '<?php echo "<h1>Theme blocked page</h1><p>" . esc_html( $args["message"] ) . "</p>";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$seen = array();
		add_filter(
			'zerospam_block_page_template',
			static function ( $path, $template, $args ) use ( $file, &$seen ) {
				$seen[] = array( $template, $args );

				return 'blocked' === $template ? $file : '/nonexistent/' . $template . '.php';
			},
			10,
			3
		);

		$this->set_setting( 'blocked_message', 'Go <away>', 'settings' );
		$this->block( self::DEFAULT_IP );
		$blocked = $this->blocked_page();
		$comment = $this->page( static fn () => Block_Page::comment_blocked( 'Spam detected.', home_url( '/' ) ) );

		$this->assertSame( 403, $blocked['code'] );
		$this->assertStringContainsString( '<h1>Theme blocked page</h1>', $blocked['message'] );
		$this->assertSame( 'blocked', $seen[0][0] );
		$this->assertArrayHasKey( 'message', $seen[0][1] );

		$this->assertStringContainsString( 'Comment not posted.', $comment['message'], 'An unreadable override broke the page.' );
		$this->assertSame( 'comment-blocked', $seen[1][0] );
	}

	/**
	 * The filter only accepts an existing .php file: other readable files fall back to the default template.
	 *
	 * @dataProvider data_rejected_template_paths
	 *
	 * @param string $kind What the filter returns: a .txt file holding PHP, or a relative path to a readable non-PHP file.
	 */
	public function test_template_filter_rejects_non_php_files( string $kind ): void {
		if ( 'txt' === $kind ) {
			$file               = wp_tempnam( 'zerospam-blocked' ) . '.txt';
			$this->temp_files[] = $file;
			file_put_contents( $file, '<?php echo "<h1>Ran a text file</h1>";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		} else {
			$file = ZEROSPAM_PATH . 'includes/templates/front/../../../readme.txt';
			$this->assertFileExists( $file );
		}

		add_filter( 'zerospam_block_page_template', static fn () => $file );
		$this->block( self::DEFAULT_IP );

		$page = $this->blocked_page();

		$this->assertStringNotContainsString( 'Ran a text file', $page['message'] );
		$this->assertStringNotContainsString( 'Stable tag', $page['message'], 'The readme was shown.' );
		$this->assertStringContainsString( 'Your connection was flagged.', $page['message'] );
	}

	/**
	 * Paths the template filter must refuse.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_rejected_template_paths(): array {
		return array(
			'text file with PHP' => array( 'txt' ),
			'readme via ../'     => array( 'readme' ),
		);
	}

	/**
	 * XML-RPC keeps its XML fault.
	 */
	public function test_xmlrpc_keeps_the_xml_fault(): void {
		$this->block( self::DEFAULT_IP );
		$access = new Access();
		add_filter( 'zerospam_access_checks', array( $access, 'check_blocked' ), 0, 3 );

		$page = $this->page( static fn () => $access->run_block_check( true ) );

		$this->assertSame( 403, $page['code'] );
		$this->assertStringStartsWith( '<?xml version="1.0"?><methodResponse><fault>', $page['message'] );
	}

	/**
	 * Block_Page replaces only WordPress's default wp_die() handler; a handler installed on purpose still decides.
	 */
	public function test_handler_replaces_only_the_default(): void {
		$this->assertSame( array( Block_Page::class, 'die_handler' ), Block_Page::select_handler( '_default_wp_die_handler' ) );
		$this->assertSame( 'my_handler', Block_Page::select_handler( 'my_handler' ) );
	}
}
