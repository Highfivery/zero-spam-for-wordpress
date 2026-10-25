<?php
/**
 * Email class
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The one template for every email Zero Spam sends (the rescue link, API
 * usage alerts, network notifications).
 *
 * A message is data, not markup, so the HTML and the plain-text version say
 * the same thing and everything from data is escaped in one place:
 *
 *     array(
 *         'heading' => 'Your rescue link',            // plain text
 *         'site'    => 'Lakeside Dental',             // plain text; default: the site name
 *         'blocks'  => array(
 *             array( 'type' => 'text', 'text' => __( 'Hello %s.' ), 'args' => array( $name ), 'size' => 'small' ),
 *             array( 'type' => 'button', 'label' => __( 'Open' ), 'url' => $url, 'paste' => true ),
 *             array( 'type' => 'rows', 'title' => __( 'Today' ), 'rows' => array( array( $label, $value ) ) ),
 *         ),
 *         'footer'  => 'Sent by …',                   // plain text; default: "Sent by Zero Spam for WordPress on {site}."
 *     )
 *
 * In a text block, `text` is a translated sprintf() format (plain text) and
 * each of `args` is a plain string or array( 'text', 'style' => strong|mono|link, 'url' ).
 *
 * The HTML is a table layout at most 600px wide with inline styles only (no
 * classes, images, SVG or web fonts, which many mail clients drop); the mark
 * is drawn as coloured table cells. send() adds the plain-text version as the
 * PHPMailer AltBody, through a phpmailer_init callback that is removed when
 * wp_mail() returns, also when another plugin sends the email itself
 * (pre_wp_mail) and the callback never runs.
 */
final class Email {

	/**
	 * Colours (the light theme of the 6.0 design; emails are always light).
	 *
	 * @var array<string, string>
	 */
	const COLORS = array(
		'bg'       => '#F7F3F3',
		'surface'  => '#FFFFFF',
		'footer'   => '#FBF8F8',
		'line'     => '#E8DCDD',
		'ink'      => '#1A0506',
		'ink_2'    => '#5C3A3E',
		'action'   => '#BE0000',
		'on_dark'  => '#FFFFFF',
		'dark'     => '#1A0506',
		'sunken'   => '#F1EAEA',
		'link_ink' => '#BE0000',
	);

	/**
	 * Font stacks (no web fonts in email).
	 *
	 * @var array<string, string>
	 */
	const FONTS = array(
		'ui'   => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
		'mono' => "ui-monospace,SFMono-Regular,Menlo,Consolas,'Courier New',monospace",
	);

	/**
	 * The mark as rows of bricks: [ x, width, colour ] on a 20-unit grid (assets/src/admin/icons/sprite.svg, zs-mark-flat).
	 *
	 * @var array<int, array<int, array{0: int, 1: int, 2: string}>>
	 */
	const MARK_ROWS = array(
		array( array( 11, 5, '#BE0000' ) ),
		array( array( 0, 6, '#63000D' ), array( 7, 3, '#BE0000' ), array( 11, 2, '#FF2929' ), array( 14, 6, '#FF7038' ) ),
		array( array( 0, 3, '#BE0000' ), array( 4, 6, '#840012' ), array( 11, 5, '#BE0000' ), array( 17, 3, '#FF2929' ) ),
		array( array( 0, 6, '#63000D' ), array( 7, 3, '#BE0000' ), array( 11, 2, '#FF2929' ), array( 14, 6, '#FFA17C' ) ),
	);

	/**
	 * Sends a message as HTML with a plain-text alternative.
	 *
	 * @param string|string[] $to      Recipient(s).
	 * @param string          $subject Subject (plain text).
	 * @param array           $message Message (see the class description).
	 * @param string[]        $headers Extra headers.
	 * @return bool Whether wp_mail() accepted the email.
	 */
	public static function send( $to, string $subject, array $message, array $headers = array() ): bool {
		$text  = self::text( $message );
		$alt   = static function ( $phpmailer ) use ( $text ): void {
			if ( is_object( $phpmailer ) && property_exists( $phpmailer, 'AltBody' ) ) {
				$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			}
		};
		$heads = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers );

		add_action( 'phpmailer_init', $alt );
		try {
			return (bool) wp_mail( $to, $subject, self::html( $message ), $heads );
		} finally {
			remove_action( 'phpmailer_init', $alt );
		}
	}

	/**
	 * The HTML version.
	 *
	 * @param array $message Message (see the class description).
	 */
	public static function html( array $message ): string {
		$c       = self::COLORS;
		$site    = self::site( $message );
		$heading = (string) ( $message['heading'] ?? '' );
		$font    = 'font-family:' . self::FONTS['ui'] . ';';

		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="' . esc_attr( (string) get_bloginfo( 'language' ) ) . '" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8">' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
		$html .= '<meta name="color-scheme" content="light">' . "\n";
		$html .= '<meta name="supported-color-schemes" content="light">' . "\n";
		$html .= '<title>' . esc_html( $heading ) . '</title>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body style="margin:0;padding:0;background-color:' . $c['bg'] . ';">' . "\n";
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' . $c['bg'] . ';">' . "\n";
		$html .= '<tr><td align="center" style="padding:24px 12px;">' . "\n";
		$html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:' . $c['surface'] . ';border:1px solid ' . $c['line'] . ';border-radius:12px;border-collapse:separate;">' . "\n";

		// Header: the mark and the site name on the dark band.
		$html .= '<tr><td style="background-color:' . $c['dark'] . ';padding:14px 20px;border-radius:12px 12px 0 0;">' . "\n";
		$html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>' . "\n";
		$html .= '<td style="padding:0;vertical-align:middle;">' . self::mark() . '</td>' . "\n";
		$html .= '<td style="padding:0 0 0 10px;vertical-align:middle;' . $font . 'font-size:15px;font-weight:700;line-height:1.3;color:' . $c['on_dark'] . ';">' . esc_html( $site ) . '</td>' . "\n";
		$html .= '</tr></table>' . "\n";
		$html .= '</td></tr>' . "\n";

		// Body.
		$html .= '<tr><td style="padding:22px 24px;' . $font . 'font-size:15px;line-height:1.55;color:' . $c['ink'] . ';">' . "\n";
		if ( '' !== $heading ) {
			$html .= '<h1 style="margin:0 0 12px;' . $font . 'font-size:22px;font-weight:800;line-height:1.2;color:' . $c['ink'] . ';">' . esc_html( $heading ) . '</h1>' . "\n";
		}
		foreach ( (array) ( $message['blocks'] ?? array() ) as $block ) {
			if ( is_array( $block ) ) {
				$html .= self::html_block( $block );
			}
		}
		$html .= '</td></tr>' . "\n";

		// Footer.
		$html .= '<tr><td style="padding:12px 24px;border-top:1px solid ' . $c['line'] . ';background-color:' . $c['footer'] . ';border-radius:0 0 12px 12px;' . $font . 'font-size:12px;line-height:1.5;color:' . $c['ink_2'] . ';">' . esc_html( self::footer( $message ) ) . '</td></tr>' . "\n";

		$html .= '</table>' . "\n";
		$html .= '</td></tr>' . "\n";
		$html .= '</table>' . "\n";
		$html .= '</body>' . "\n";
		$html .= '</html>' . "\n";

		return $html;
	}

	/**
	 * The plain-text version.
	 *
	 * @param array $message Message (see the class description).
	 */
	public static function text( array $message ): string {
		$paragraphs = array();

		$heading = (string) ( $message['heading'] ?? '' );
		if ( '' !== $heading ) {
			$paragraphs[] = $heading;
		}

		foreach ( (array) ( $message['blocks'] ?? array() ) as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			switch ( $block['type'] ?? '' ) {
				case 'text':
					$paragraphs[] = self::format( $block, false );
					break;

				case 'button':
					// The paragraph before says what the link does; the address goes on a line of its own.
					$paragraphs[] = (string) ( $block['url'] ?? '' );
					break;

				case 'rows':
					$lines = array();
					if ( ! empty( $block['title'] ) ) {
						$lines[] = (string) $block['title'];
					}
					foreach ( (array) ( $block['rows'] ?? array() ) as $row ) {
						$lines[] = sprintf(
							/* translators: 1: label, 2: value, in a plain-text email */
							__( '%1$s: %2$s', 'zero-spam' ),
							(string) ( $row[0] ?? '' ),
							(string) ( $row[1] ?? '' )
						);
					}
					$paragraphs[] = implode( "\n", $lines );
					break;
			}
		}

		$paragraphs[] = '-- ' . "\n" . self::footer( $message );

		return implode( "\n\n", $paragraphs ) . "\n";
	}

	/**
	 * One block of the HTML version.
	 *
	 * @param array $block Block.
	 */
	private static function html_block( array $block ): string {
		$c    = self::COLORS;
		$font = 'font-family:' . self::FONTS['ui'] . ';';
		$mono = 'font-family:' . self::FONTS['mono'] . ';';

		switch ( $block['type'] ?? '' ) {
			case 'text':
				$small = 'small' === ( $block['size'] ?? '' );
				$style = $small ? 'font-size:13px;line-height:1.55;color:' . $c['ink_2'] . ';' : 'font-size:15px;line-height:1.55;color:' . $c['ink'] . ';';

				return '<p style="margin:0 0 12px;' . $font . $style . 'word-wrap:break-word;overflow-wrap:anywhere;">' . self::format( $block, true ) . '</p>' . "\n";

			case 'button':
				$url   = (string) ( $block['url'] ?? '' );
				$label = (string) ( $block['label'] ?? '' );

				$html  = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 16px;"><tr>' . "\n";
				$html .= '<td style="background-color:' . $c['action'] . ';border-radius:8px;">' . "\n";
				$html .= '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 20px;' . $font . 'font-size:15px;font-weight:700;line-height:1.2;color:#FFFFFF;text-decoration:none;border-radius:8px;">' . esc_html( $label ) . '</a>' . "\n";
				$html .= '</td>' . "\n";
				$html .= '</tr></table>' . "\n";

				if ( ! empty( $block['paste'] ) ) {
					$html .= '<p style="margin:0 0 12px;' . $font . 'font-size:13px;line-height:1.55;color:' . $c['ink_2'] . ';word-wrap:break-word;overflow-wrap:anywhere;">' . esc_html__( 'Or paste this address into your browser:', 'zero-spam' ) . '<br>' . "\n";
					$html .= '<span style="' . $mono . 'color:' . $c['ink'] . ';word-break:break-all;">' . esc_html( $url ) . '</span></p>' . "\n";
				}

				return $html;

			case 'rows':
				$html = '';
				if ( ! empty( $block['title'] ) ) {
					$html .= '<h2 style="margin:16px 0 8px;' . $font . 'font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;line-height:1.4;color:' . $c['ink_2'] . ';">' . esc_html( (string) $block['title'] ) . '</h2>' . "\n";
				}
				$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;border:1px solid ' . $c['line'] . ';border-radius:8px;border-collapse:separate;">' . "\n";
				foreach ( array_values( (array) ( $block['rows'] ?? array() ) ) as $i => $row ) {
					$border = $i > 0 ? 'border-top:1px solid ' . $c['line'] . ';' : '';
					$html  .= '<tr>';
					$html  .= '<td style="padding:8px 12px;' . $border . $font . 'font-size:14px;line-height:1.4;color:' . $c['ink_2'] . ';vertical-align:top;">' . esc_html( (string) ( $row[0] ?? '' ) ) . '</td>';
					$html  .= '<td style="padding:8px 12px;' . $border . $font . 'font-size:14px;font-weight:600;line-height:1.4;color:' . $c['ink'] . ';vertical-align:top;word-break:break-word;">' . esc_html( (string) ( $row[1] ?? '' ) ) . '</td>';
					$html  .= '</tr>' . "\n";
				}
				$html .= '</table>' . "\n";

				return $html;
		}

		return '';
	}

	/**
	 * Fills a text block's format with its arguments, escaped for HTML or as plain text.
	 *
	 * @param array $block Text block.
	 * @param bool  $html  Whether to build HTML.
	 */
	private static function format( array $block, bool $html ): string {
		$format = (string) ( $block['text'] ?? '' );
		$args   = array_values( (array) ( $block['args'] ?? array() ) );

		if ( ! $args ) {
			return $html ? self::breaks( esc_html( $format ) ) : $format;
		}

		$parts = array();
		foreach ( $args as $arg ) {
			$parts[] = $html ? self::html_part( $arg ) : self::text_part( $arg );
		}

		// Placeholders survive esc_html() unchanged; a literal "%" in a translation is written "%%".
		return $html ? self::breaks( vsprintf( esc_html( $format ), $parts ) ) : vsprintf( $format, $parts );
	}

	/**
	 * Keeps the line breaks inside a paragraph in HTML.
	 *
	 * @param string $html Escaped text.
	 */
	private static function breaks( string $html ): string {
		return str_replace( array( "\r\n", "\n" ), "<br>\n", $html );
	}

	/**
	 * One argument of a text block, as HTML.
	 *
	 * @param string|array $arg Plain text, or array( 'text', 'style', 'url' ).
	 */
	private static function html_part( $arg ): string {
		if ( ! is_array( $arg ) ) {
			return esc_html( (string) $arg );
		}

		$text = esc_html( (string) ( $arg['text'] ?? '' ) );

		switch ( $arg['style'] ?? '' ) {
			case 'strong':
				return '<strong>' . $text . '</strong>';

			case 'mono':
				return '<span style="font-family:' . self::FONTS['mono'] . ';">' . $text . '</span>';

			case 'link':
				return '<a href="' . esc_url( (string) ( $arg['url'] ?? '' ) ) . '" style="color:' . self::COLORS['link_ink'] . ';text-decoration:underline;">' . $text . '</a>';
		}

		return $text;
	}

	/**
	 * One argument of a text block, as plain text (a link adds its address in brackets).
	 *
	 * @param string|array $arg Plain text, or array( 'text', 'style', 'url' ).
	 */
	private static function text_part( $arg ): string {
		if ( ! is_array( $arg ) ) {
			return (string) $arg;
		}

		$text = (string) ( $arg['text'] ?? '' );

		if ( 'link' === ( $arg['style'] ?? '' ) && ! empty( $arg['url'] ) ) {
			return $text . ' (' . $arg['url'] . ')';
		}

		return $text;
	}

	/**
	 * The name in the header: the message's `site`, or the site name.
	 *
	 * @param array $message Message.
	 */
	private static function site( array $message ): string {
		if ( isset( $message['site'] ) && is_string( $message['site'] ) ) {
			return $message['site'];
		}

		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * The footer line.
	 *
	 * @param array $message Message.
	 */
	private static function footer( array $message ): string {
		if ( isset( $message['footer'] ) && is_string( $message['footer'] ) ) {
			return $message['footer'];
		}

		return sprintf(
			/* translators: %s: site name */
			__( 'Sent by Zero Spam for WordPress on %s.', 'zero-spam' ),
			self::site( $message )
		);
	}

	/**
	 * The mark as table cells: four rows of bricks in the six reds, about 28px wide.
	 */
	private static function mark(): string {
		$unit = 1.4;
		$cell = static function ( int $width, string $color ): string {
			$fill = '' === $color ? '' : 'background-color:' . $color . ';';

			return '<td width="' . $width . '" height="6" style="width:' . $width . 'px;height:6px;padding:0;' . $fill . 'font-size:0;line-height:0;mso-line-height-rule:exactly;">&nbsp;</td>' . "\n";
		};

		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">' . "\n";
		foreach ( self::MARK_ROWS as $i => $bricks ) {
			if ( $i > 0 ) {
				$html .= '<tr><td height="1" style="height:1px;padding:0;font-size:0;line-height:0;">&nbsp;</td></tr>' . "\n";
			}

			$row = '';
			$at  = 0;
			foreach ( $bricks as $brick ) {
				$start = (int) round( $brick[0] * $unit );
				$end   = (int) round( ( $brick[0] + $brick[1] ) * $unit );
				if ( $start > $at ) {
					$row .= $cell( $start - $at, '' );
				}
				$row .= $cell( $end - $start, $brick[2] );
				$at   = $end;
			}
			$total = (int) round( 20 * $unit );
			if ( $total > $at ) {
				$row .= $cell( $total - $at, '' );
			}

			$html .= '<tr><td style="padding:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr>' . "\n" . $row . '</tr></table></td></tr>' . "\n";
		}

		return $html . '</table>';
	}
}
