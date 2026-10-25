<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Block_Page to this file name.
/**
 * Block_Page class
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Front;

use ZeroSpam\Core\Rescue_Link;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The pages Zero Spam answers with instead of the site: the blocked page,
 * the rescue link's "Check your email", "Let me back in" and "This link has
 * expired" pages, the rescue form with an error, and the page a comment
 * flagged as spam gets.
 *
 * Each page is a template in includes/templates/front/ (a theme can replace
 * one with the `zerospam_block_page_template` filter) that renders the page's
 * copy. The request ends through wp_die() with that copy as the message, as
 * before; for normal page requests Block_Page then replaces WordPress's
 * default wp_die() page with its own document around it: the site's language
 * and direction, assets/dist/blocked.css inlined (light and dark follow the
 * operating system; nothing moves under reduced motion), the static mark
 * and the brick wall drawn inline. The pages run no JavaScript and request
 * nothing from other sites; the only file they load is the plugin's own
 * Plus Jakarta Sans (latin) font, from the plugin's URL.
 *
 * AJAX, REST/JSON and XML requests keep WordPress's own wp_die() answer with
 * the same copy, and a wp_die() handler that other code installed on purpose
 * still decides (see select_handler()).
 */
final class Block_Page {

	/**
	 * Page templates in includes/templates/front/.
	 *
	 * @var string[]
	 */
	const TEMPLATES = array( 'blocked', 'rescue-sent', 'rescue-confirm', 'rescue-expired', 'form-error', 'comment-blocked' );

	/**
	 * The one font file the pages load (Plus Jakarta Sans, latin subset, 27 KB).
	 *
	 * @var string
	 */
	const FONT_FILE = 'assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin.woff2';

	/**
	 * The six reds of the mark, darkest first.
	 *
	 * @var string[]
	 */
	const RAMP = array( '#63000D', '#840012', '#BE0000', '#FF2929', '#FF7038', '#FFA17C' );

	/**
	 * The flat mark (treatment A, for 20px and below): x, y, width, colour index of each brick, on a 20×19 grid of 4px rows.
	 *
	 * @var array<int, array{0: int, 1: int, 2: int, 3: int}>
	 */
	const MARK = array(
		array( 11, 0, 5, 2 ),
		array( 0, 5, 6, 0 ),
		array( 7, 5, 3, 2 ),
		array( 11, 5, 2, 3 ),
		array( 14, 5, 6, 4 ),
		array( 0, 10, 3, 2 ),
		array( 4, 10, 6, 1 ),
		array( 11, 10, 5, 2 ),
		array( 17, 10, 3, 3 ),
		array( 0, 15, 6, 0 ),
		array( 7, 15, 3, 2 ),
		array( 11, 15, 2, 3 ),
		array( 14, 15, 6, 5 ),
	);

	/**
	 * Icon paths (24×24, stroked), as in assets/src/admin/icons/sprite.svg.
	 *
	 * @var array<string, string>
	 */
	const ICONS = array(
		'buoy' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M5.6 5.6l4 4M14.4 14.4l4 4M18.4 5.6l-4 4M9.6 14.4l-4 4"/>',
		'chev' => '<path d="M6 9l6 6 6-6"/>',
		'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'back' => '<path d="M15 6l-6 6 6 6"/>',
	);

	/**
	 * Directory of the built stylesheets; empty for assets/dist/ (tests point it elsewhere).
	 *
	 * @var string
	 */
	private static string $dist_dir = '';

	/**
	 * Stylesheets read in this request, keyed by file name.
	 *
	 * @var array<string, string>
	 */
	private static array $stylesheets = array();

	/**
	 * Ends the request with the blocked page (403).
	 *
	 * @param string $message Blocked message (the administrator's; filtered with wp_kses_post()).
	 * @param array  $details {
	 *     Optional. What the visitor sees in the Details box.
	 *
	 *     @type string $ip        Visitor IP.
	 *     @type int    $reference Detection log ID, when the block was logged.
	 * }
	 */
	public static function blocked( string $message, array $details = array() ): void {
		self::render(
			'blocked',
			array(
				'heading'    => __( 'Blocked.', 'zero-spam' ),
				'subheading' => __( 'Your connection was flagged.', 'zero-spam' ),
				'message'    => $message,
				'details'    => self::details( $details ),
				'rescue'     => Rescue_Link::form_enabled() ? Rescue_Link::form_args() : null,
			),
			__( 'Blocked', 'zero-spam' ),
			403
		);
	}

	/**
	 * Ends the request with a detection page that is not the blocked page (Access::terminate_execution() without the rescue form).
	 *
	 * @param string $title   Heading and page title (plain text).
	 * @param string $message Message (filtered with wp_kses_post()).
	 * @param int    $status  HTTP status.
	 */
	public static function detection( string $title, string $message, int $status = 403 ): void {
		self::render(
			'blocked',
			array(
				'heading'    => $title,
				'subheading' => '',
				'message'    => $message,
				'details'    => array(),
				'rescue'     => null,
			),
			$title,
			$status
		);
	}

	/**
	 * Ends the request with the page a comment flagged as spam gets (403, as before).
	 *
	 * @param string $message  The comment spam message (filtered with wp_kses_post()).
	 * @param string $back_url Where "Go back" leads (the post's comment form).
	 */
	public static function comment_blocked( string $message, string $back_url ): void {
		self::render(
			'comment-blocked',
			array(
				'message'  => $message,
				'back_url' => $back_url,
			),
			__( 'Comment not posted', 'zero-spam' ),
			403
		);
	}

	/**
	 * Renders a page template and ends the request with it.
	 *
	 * @param string $template One of self::TEMPLATES.
	 * @param array  $args     Template arguments.
	 * @param string $title    Page title (plain text).
	 * @param int    $status   HTTP status.
	 */
	public static function render( string $template, array $args, string $title, int $status ): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$fragment = self::template( $template, $args );

		$die_args = array(
			'response'      => $status,
			'zerospam_page' => $template,
		);

		add_filter( 'wp_die_handler', array( self::class, 'select_handler' ), PHP_INT_MAX );
		try {
			// The fragment is escaped by the template; the arguments are not output.
			wp_die( $fragment, esc_html( $title ), $die_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- See above.
		} finally {
			remove_filter( 'wp_die_handler', array( self::class, 'select_handler' ), PHP_INT_MAX );
		}
	}

	/**
	 * The markup of a page template: the page's copy, without the document around it.
	 *
	 * @param string $template One of self::TEMPLATES.
	 * @param array  $args     Template arguments.
	 */
	public static function template( string $template, array $args ): string {
		$default = ZEROSPAM_PATH . 'includes/templates/front/' . $template . '.php';
		$path    = $default;

		/**
		 * Filters the file that renders one of the blocked, rescue and expired-link pages.
		 *
		 * The file receives `$args` (the page's arguments; see the default
		 * template in includes/templates/front/) and must escape everything it
		 * prints. It renders the page's copy only: the document around it
		 * (language, styles, the mark and the wall) stays Zero Spam's. A path
		 * that is not a readable file is ignored.
		 *
		 * @since 6.0.0
		 *
		 * @param string $path     Full path of the template file.
		 * @param string $template Template name: blocked, rescue-sent, rescue-confirm, rescue-expired, form-error or comment-blocked.
		 * @param array  $args     Template arguments.
		 */
		$filtered = apply_filters( 'zerospam_block_page_template', $path, $template, $args );
		if ( is_string( $filtered ) && '' !== $filtered && is_file( $filtered ) && is_readable( $filtered ) ) {
			$path = $filtered;
		}

		if ( ! is_readable( $path ) ) {
			return '';
		}

		ob_start();
		self::load( $path, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Includes a template file with only `$args` in scope.
	 *
	 * @param string $path Template file.
	 * @param array  $args Template arguments.
	 */
	private static function load( string $path, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the included template.
		include $path;
	}

	/**
	 * Prints a template part (includes/templates/front/parts/), such as the rescue form.
	 *
	 * @param string $part Part name.
	 * @param array  $args Part arguments.
	 */
	public static function part( string $part, array $args ): void {
		$path = ZEROSPAM_PATH . 'includes/templates/front/parts/' . sanitize_key( $part ) . '.php';
		if ( is_readable( $path ) ) {
			self::load( $path, $args );
		}
	}

	/**
	 * Lets Block_Page answer wp_die() for its pages, instead of WordPress's default handler only.
	 *
	 * A handler other code chose (a test suite, a plugin that renders every
	 * wp_die() its own way) is kept: it receives the page's copy as its
	 * message, as it did before 6.0.
	 *
	 * @param callable|string $handler The wp_die() handler WordPress chose.
	 * @return callable|string
	 */
	public static function select_handler( $handler ) {
		return '_default_wp_die_handler' === $handler ? array( self::class, 'die_handler' ) : $handler;
	}

	/**
	 * Sends a page: the status and headers, then the document around the page's copy.
	 *
	 * @param string|\WP_Error $message The page's copy (escaped markup).
	 * @param string           $title   Page title (escaped).
	 * @param array            $args    wp_die() arguments: `response` (status) and `exit`.
	 */
	public static function die_handler( $message, $title = '', $args = array() ): void {
		$status = isset( $args['response'] ) ? (int) $args['response'] : 403;
		$copy   = is_string( $message ) ? $message : '';

		if ( ! headers_sent() ) {
			status_header( $status );
			foreach ( self::headers() as $name => $value ) {
				header( $name . ': ' . $value );
			}
			header_remove( 'Last-Modified' );
		}

		echo self::document( $copy, wp_specialchars_decode( (string) $title, ENT_QUOTES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in document().

		if ( ! isset( $args['exit'] ) || $args['exit'] ) {
			exit;
		}
	}

	/**
	 * Headers every page sends besides the status.
	 *
	 * WordPress's no-cache headers (as nocache_headers() sends them), no
	 * indexing, and a policy that forbids scripts, plugins and a changed base
	 * URL (the pages have none; the policy keeps it that way even for markup
	 * in the administrator's blocked message).
	 *
	 * @return array<string, string>
	 */
	public static function headers(): array {
		$headers = wp_get_nocache_headers();
		unset( $headers['Last-Modified'] );

		return array_merge(
			array_map( 'strval', $headers ),
			array(
				'Content-Type'            => 'text/html; charset=utf-8',
				'X-Robots-Tag'            => 'noindex, nofollow',
				'Content-Security-Policy' => "script-src 'none'; object-src 'none'; base-uri 'none'",
			)
		);
	}

	/**
	 * The full document around a page's copy.
	 *
	 * @param string $copy  The page's copy (escaped markup).
	 * @param string $title Page title (plain text).
	 */
	public static function document( string $copy, string $title ): string {
		$site = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$css  = self::stylesheet( is_rtl() ? 'blocked-rtl.css' : 'blocked.css' );

		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="' . esc_attr( (string) get_bloginfo( 'language' ) ) . '" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8">' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
		$html .= '<meta name="robots" content="noindex, nofollow">' . "\n";
		$html .= '<meta name="referrer" content="no-referrer">' . "\n";
		$html .= '<meta name="color-scheme" content="light dark">' . "\n";
		$html .= '<title>' . esc_html( $title ) . '</title>' . "\n";
		if ( '' !== $css ) {
			$html .= '<style>' . self::font_face() . $css . '</style>' . "\n";
		}
		$html .= '</head>' . "\n";
		$html .= '<body class="zs-blocked">' . "\n";
		$html .= '<main class="zs-blk">' . "\n";
		$html .= '<div class="zs-blk-l">' . "\n";
		$html .= '<p class="zs-byline">' . self::mark() . esc_html( $site ) . '</p>' . "\n";
		$html .= '<div class="zs-blk-copy">' . "\n" . $copy . "\n" . '</div>' . "\n";
		$html .= '<p class="zs-byline">' . sprintf(
			/* translators: %s: the Zero Spam mark and name */
			esc_html__( 'Protected by %s', 'zero-spam' ),
			self::mark() . '<b>Zero Spam</b>'
		) . '</p>' . "\n";
		$html .= '</div>' . "\n";
		$html .= '<div class="zs-blk-r" aria-hidden="true">' . self::wall() . '</div>' . "\n";
		$html .= '</main>' . "\n";
		$html .= '</body>' . "\n";
		$html .= '</html>' . "\n";

		return $html;
	}

	/**
	 * A built stylesheet, or an empty string when it is missing (the page is then unstyled).
	 *
	 * @param string $file File name in assets/dist/.
	 */
	private static function stylesheet( string $file ): string {
		if ( ! isset( self::$stylesheets[ $file ] ) ) {
			$path = ( '' === self::$dist_dir ? ZEROSPAM_PATH . 'assets/dist/' : self::$dist_dir ) . $file;
			$css  = is_readable( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file of the plugin.

			// Inside <style>, only a closing tag could end the element early.
			self::$stylesheets[ $file ] = str_ireplace( '</style', '<\/style', trim( (string) $css ) );
		}

		return self::$stylesheets[ $file ];
	}

	/**
	 * The @font-face rule for the one font the pages load, from the plugin's URL.
	 */
	private static function font_face(): string {
		return '@font-face{font-family:"Plus Jakarta Sans";font-style:normal;font-weight:400 800;font-display:swap;'
			. 'src:url("' . esc_url_raw( plugins_url( self::FONT_FILE, ZEROSPAM ) ) . '") format("woff2");'
			. 'unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}';
	}

	/**
	 * The Details box rows.
	 *
	 * @param array $details Visitor IP and log reference.
	 * @return array<int, array{label: string, value: string, mono: bool}>
	 */
	private static function details( array $details ): array {
		$rows = array();

		if ( ! empty( $details['ip'] ) && is_string( $details['ip'] ) ) {
			$rows[] = array(
				'label' => __( 'Your IP', 'zero-spam' ),
				'value' => $details['ip'],
				'mono'  => true,
			);
		}

		if ( ! empty( $details['reference'] ) ) {
			$rows[] = array(
				'label' => __( 'Reference', 'zero-spam' ),
				'value' => '#' . absint( $details['reference'] ),
				'mono'  => true,
			);
		}

		$rows[] = array(
			'label' => __( 'Time', 'zero-spam' ),
			/* translators: Date and time on the blocked page. See https://www.php.net/manual/datetime.format.php */
			'value' => (string) wp_date( __( 'D, M j · g:i a T', 'zero-spam' ) ),
			'mono'  => false,
		);

		return $rows;
	}

	/**
	 * The flat mark at 20px (treatment A), inline.
	 */
	public static function mark(): string {
		$svg = '<svg class="zs-mark" width="20" height="19" viewBox="0 0 20 19" shape-rendering="crispEdges" aria-hidden="true" focusable="false">';
		foreach ( self::MARK as $brick ) {
			$svg .= '<rect x="' . $brick[0] . '" y="' . $brick[1] . '" width="' . $brick[2] . '" height="4" rx="1" fill="' . self::RAMP[ $brick[3] ] . '"/>';
		}

		return $svg . '</svg>';
	}

	/**
	 * A 24×24 stroked icon, hidden from assistive technology.
	 *
	 * @param string $name       Icon name (a key of self::ICONS).
	 * @param string $extra_class Extra class.
	 */
	public static function icon( string $name, string $extra_class = '' ): string {
		if ( ! isset( self::ICONS[ $name ] ) ) {
			return '';
		}

		return '<svg class="zs-icon' . ( '' === $extra_class ? '' : ' ' . esc_attr( $extra_class ) ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . self::ICONS[ $name ] . '</svg>';
	}

	/**
	 * The angled brick wall beside the copy (below 860px, above it).
	 *
	 * Drawn from a fixed seed, so every page shows the same wall, the same way
	 * the prototype draws it (seed 21). Each brick carries its lay-in delay;
	 * blocked.css animates them only without a reduced-motion preference.
	 */
	public static function wall(): string {
		$w      = 600;
		$h      = 1000;
		$bh     = 150;
		$gap    = 8;
		$widths = array( 1.1, 1.25, 1.6, 1.3 );
		$seed   = 21;
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 1664525 + 1013904223 ) & 0xFFFFFFFF;

			return $seed / 4294967296;
		};

		$cx    = $w / 2;
		$cy    = $h / 2;
		$span  = sqrt( $w * $w + $h * $h ) + $bh * 2;
		$rows  = (int) ceil( $span / $bh ) + 1;
		$top   = $cy - $rows * $bh / 2;
		$f     = static fn ( float $v ): string => number_format( $v, 1, '.', '' );
		$rects = '';

		for ( $r = 0; $r < $rows; $r++ ) {
			$y = $top + $r * $bh;
			$x = $cx - $span / 2 - $bh * 1.6 * $random();
			while ( $x < $cx + $span / 2 ) {
				$bw    = $bh * $widths[ (int) floor( $random() * 4 ) ];
				$fx    = ( $x + $bw / 2 ) / $w;
				$fy    = ( $y + $bh / 2 ) / $h;
				$t     = $fx * 0.45 + $fy * 0.55 - 0.05 + ( $random() - 0.5 ) * 0.5;
				$i     = max( 0, min( 5, (int) floor( $t * 6 ) ) );
				$delay = (int) round( max( 0, min( 1, ( 1 - $fy ) * 0.55 + $fx * 0.45 ) ) * 640 );

				$rects .= '<rect style="--d:' . $delay . 'ms" x="' . $f( $x + $gap / 2 ) . '" y="' . $f( $y + $gap / 2 ) . '" width="' . $f( $bw - $gap ) . '" height="' . $f( $bh - $gap ) . '" rx="15" fill="' . self::RAMP[ $i ] . '"/>';
				$x     += $bw;
			}
		}

		return '<svg class="zs-wall" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false"><g transform="rotate(-6 ' . $cx . ' ' . $cy . ')">' . $rects . '</g></svg>';
	}
}
