<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Documentation to this file name.
/**
 * The in-admin guides, prepared for the 6.0 Help screen.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The guides in includes/templates/docs/, plus every section another plugin
 * adds with the `zerospam_documentation_sections` filter, rendered to HTML
 * the Help screen can show as it is.
 *
 * Each template is rendered on the server, then reshaped for the Help
 * screen: its own title is dropped (the screen shows the section's title),
 * each accordion (a toggle button followed by its content) becomes a
 * native <details> disclosure, headings move down to fit under the
 * screen's (h4 and below), IDs are prefixed so they cannot clash with the
 * page's, links to other sites open in a new tab and say so, and
 * decorative emoji are removed (WordPress's emoji script would replace them
 * with images from s.w.org). The result is passed through wp_kses_post(),
 * always last, so whatever a section prints, only post-safe HTML leaves.
 *
 * The classic Documentation tab (includes/templates/admin-documentation.php)
 * keeps its own list until the 6.0 cutover.
 *
 * @since 6.0.0
 */
final class Documentation {

	/**
	 * Prefix of IDs inside a guide.
	 *
	 * @var string
	 */
	const ID_PREFIX = 'zs-doc-';

	/**
	 * Highest heading level inside a guide (the Help screen has h1 for the
	 * page, h2 for the tile and h3 for each guide).
	 *
	 * @var int
	 */
	const FIRST_HEADING = 4;

	/**
	 * The built-in guides, in order. The two network guides (statistics,
	 * settings and management) are listed on a network only.
	 *
	 * @return array<string, array{title: string, template: string, priority: int}>
	 */
	public static function defaults(): array {
		$dir = ZEROSPAM_PATH . 'includes/templates/docs/';

		$guides = array(
			'rest-api'           => array(
				'title'    => __( 'REST API for settings', 'zero-spam' ),
				'template' => $dir . 'rest-api.php',
				'priority' => 10,
			),
			'api-monitoring'     => array(
				'title'    => __( 'API monitoring and alerts', 'zero-spam' ),
				'template' => $dir . 'api-monitoring.php',
				'priority' => 20,
			),
			'network-statistics' => array(
				'title'    => __( 'Network statistics and insights', 'zero-spam' ),
				'template' => $dir . 'network-statistics.php',
				'priority' => 30,
			),
			'lockout-rescue'     => array(
				'title'    => __( 'Getting back in after a lockout', 'zero-spam' ),
				'template' => $dir . 'lockout-rescue.php',
				'priority' => 40,
			),
			'developer-hooks'    => array(
				'title'    => __( 'Developer hooks', 'zero-spam' ),
				'template' => $dir . 'developer-hooks.php',
				'priority' => 50,
			),
			// Written for 5.7 but never listed on the classic Documentation tab.
			'network-settings'   => array(
				'title'    => __( 'Network settings and management', 'zero-spam' ),
				'template' => $dir . 'network-settings.php',
				'priority' => 60,
			),
		);

		if ( ! is_multisite() ) {
			unset( $guides['network-statistics'], $guides['network-settings'] );
		}

		return $guides;
	}

	/**
	 * The sections, after the `zerospam_documentation_sections` filter,
	 * sorted by priority (stable), without entries that have no title or no
	 * readable template.
	 *
	 * @return array<string, array{title: string, template: string, priority: int}>
	 */
	public static function sections(): array {
		/** This filter is documented in includes/templates/admin-documentation.php */
		$sections = apply_filters( 'zerospam_documentation_sections', self::defaults() );
		$sections = is_array( $sections ) ? $sections : array();
		$valid    = array();
		$order    = 0;

		foreach ( $sections as $id => $section ) {
			$id = sanitize_key( (string) $id );

			if ( '' === $id || ! is_array( $section ) || empty( $section['title'] ) || empty( $section['template'] ) ) {
				continue;
			}

			$template = (string) $section['template'];

			if ( ! str_ends_with( strtolower( $template ), '.php' ) || ! is_readable( $template ) ) {
				continue;
			}

			$valid[ $id ] = array(
				'title'    => wp_strip_all_tags( (string) $section['title'] ),
				'template' => $template,
				'priority' => isset( $section['priority'] ) ? (int) $section['priority'] : 10,
				'order'    => $order++,
			);
		}

		uasort(
			$valid,
			static fn( array $a, array $b ): int => array( $a['priority'], $a['order'] ) <=> array( $b['priority'], $b['order'] )
		);

		return array_map(
			static function ( array $section ): array {
				unset( $section['order'] );
				return $section;
			},
			$valid
		);
	}

	/**
	 * Every section, ready for the Help screen.
	 *
	 * @return array<int, array{id: string, title: string, topics: string[], html: string}>
	 */
	public static function for_help(): array {
		$out = array();

		foreach ( self::sections() as $id => $section ) {
			$html  = self::prepare( self::render( $section['template'] ), (string) $id );
			$out[] = array(
				'id'     => (string) $id,
				'title'  => $section['title'],
				'topics' => self::topics( $html ),
				'html'   => $html,
			);
		}

		return $out;
	}

	/**
	 * Renders a template to a string. A template that fails renders nothing.
	 *
	 * @param string $template Path of a PHP template.
	 */
	public static function render( string $template ): string {
		ob_start();

		try {
			require $template;
		} catch ( \Throwable $e ) {
			ob_end_clean();
			return '';
		}

		return (string) ob_get_clean();
	}

	/**
	 * Reshapes a rendered guide for the Help screen and sanitises it.
	 *
	 * @param string $html Rendered template.
	 * @param string $id   Section ID (used to keep IDs unique).
	 */
	public static function prepare( string $html, string $id = '' ): string {
		$html = self::strip_emoji( $html );

		if ( class_exists( '\DOMDocument' ) && '' !== trim( $html ) ) {
			$html = self::reshape( $html, $id );
		}

		return trim( wp_kses_post( $html ) );
	}

	/**
	 * The titles of a prepared guide's disclosures, in order.
	 *
	 * @param string $html Prepared guide.
	 * @return string[]
	 */
	public static function topics( string $html ): array {
		preg_match_all( '#<summary[^>]*>(.*?)</summary>#s', $html, $matches );

		return array_values(
			array_filter(
				array_map(
					static fn( string $text ): string => trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) ),
					$matches[1]
				)
			)
		);
	}

	/**
	 * Removes emoji and the variation selector that follows them.
	 *
	 * @param string $html HTML.
	 */
	private static function strip_emoji( string $html ): string {
		$clean = preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2139}\x{FE0F}\x{200D}]\s?/u', '', $html );

		return null === $clean ? $html : $clean;
	}

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOM extension's own properties (parentNode, childNodes, textContent, nodeName, nodeValue) are camelCase.

	/**
	 * The DOM part of prepare().
	 *
	 * @param string $html Rendered template.
	 * @param string $id   Section ID.
	 */
	private static function reshape( string $html, string $id ): string {
		$doc      = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $doc->loadHTML( '<?xml encoding="UTF-8"><div id="zs-doc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $loaded ? $doc->getElementById( 'zs-doc-root' ) : null;
		if ( null === $root ) {
			return $html;
		}

		$xpath = new \DOMXPath( $doc );

		// Elements whose content is not text for readers go with their content
		// (wp_kses_post() would drop the tags but keep a script's source as text).
		foreach ( iterator_to_array( $xpath->query( './/script|.//style|.//iframe|.//object|.//embed|.//noscript|.//template', $root ) ) as $element ) {
			$element->parentNode->removeChild( $element );
		}

		// The section's own title: the Help screen shows the section title instead.
		$first_h2 = $xpath->query( './/h2', $root );
		if ( $first_h2 && $first_h2->length > 0 ) {
			$h2 = $first_h2->item( 0 );
			$h2->parentNode->removeChild( $h2 );
		}

		self::accordions_to_details( $doc, $xpath, $root );
		self::demote_headings( $doc, $xpath, $root );
		self::prefix_ids( $xpath, $root, $id );
		self::mark_external_links( $doc, $xpath, $root );

		// Tables get the Help screen's table style.
		foreach ( iterator_to_array( $xpath->query( './/table', $root ) ) as $table ) {
			$table->setAttribute( 'class', 'zs-doc-table' );
		}

		// Comments say nothing to readers.
		foreach ( iterator_to_array( $xpath->query( './/comment()', $root ) ) as $comment ) {
			$comment->parentNode->removeChild( $comment );
		}

		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}

		return $out;
	}

	/**
	 * Turns each accordion (`.zerospam-docs-accordion`: a toggle button and
	 * a content block) into <details><summary>.
	 *
	 * @param \DOMDocument $doc   Document.
	 * @param \DOMXPath    $xpath XPath.
	 * @param \DOMElement  $root  Root.
	 */
	private static function accordions_to_details( \DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root ): void {
		$accordions = iterator_to_array( $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " zerospam-docs-accordion ")]', $root ) );

		foreach ( $accordions as $accordion ) {
			$button = $xpath->query( './button[1]', $accordion )->item( 0 );
			if ( null === $button ) {
				continue;
			}

			// The toggle's text without its ▶ / + icon.
			foreach ( iterator_to_array( $xpath->query( './/*[contains(@class, "zerospam-docs-accordion-icon")]', $button ) ) as $icon ) {
				$icon->parentNode->removeChild( $icon );
			}
			$title = trim( (string) preg_replace( '/\s+/u', ' ', $button->textContent ) );

			$details = $doc->createElement( 'details' );
			$details->setAttribute( 'class', 'zs-doc-acc' );
			$summary = $doc->createElement( 'summary' );
			$summary->setAttribute( 'class', 'zs-doc-acc-s' );
			$summary->appendChild( $doc->createTextNode( $title ) );
			$details->appendChild( $summary );

			$content = $doc->createElement( 'div' );
			$content->setAttribute( 'class', 'zs-doc-acc-b' );
			$details->appendChild( $content );

			$source = $xpath->query( './div[contains(@class, "zerospam-docs-accordion-content")]', $accordion )->item( 0 );
			$from   = null !== $source ? $source : $accordion;
			foreach ( iterator_to_array( $from->childNodes ) as $child ) {
				if ( $child === $button ) {
					continue;
				}
				$content->appendChild( $child );
			}

			$accordion->parentNode->replaceChild( $details, $accordion );
		}
	}

	/**
	 * Moves every heading down to h4 and below, keeping their order.
	 *
	 * @param \DOMDocument $doc   Document.
	 * @param \DOMXPath    $xpath XPath.
	 * @param \DOMElement  $root  Root.
	 */
	private static function demote_headings( \DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root ): void {
		$headings = iterator_to_array( $xpath->query( './/h1|.//h2|.//h3|.//h4|.//h5|.//h6', $root ) );

		foreach ( $headings as $heading ) {
			$level = (int) substr( $heading->nodeName, 1 );
			$new   = min( 6, max( self::FIRST_HEADING, $level + 1 ) );

			if ( $new === $level ) {
				continue;
			}

			$replacement = $doc->createElement( 'h' . $new );
			foreach ( iterator_to_array( $heading->attributes ) as $attribute ) {
				$replacement->setAttribute( $attribute->nodeName, $attribute->nodeValue );
			}
			foreach ( iterator_to_array( $heading->childNodes ) as $child ) {
				$replacement->appendChild( $child );
			}
			$heading->parentNode->replaceChild( $replacement, $heading );
		}
	}

	/**
	 * Prefixes IDs (and the copy buttons' targets) so they stay unique.
	 *
	 * @param \DOMXPath   $xpath XPath.
	 * @param \DOMElement $root  Root.
	 * @param string      $id    Section ID.
	 */
	private static function prefix_ids( \DOMXPath $xpath, \DOMElement $root, string $id ): void {
		$prefix = self::ID_PREFIX . ( '' !== $id ? $id . '-' : '' );

		foreach ( iterator_to_array( $xpath->query( './/*[@id]', $root ) ) as $element ) {
			$element->setAttribute( 'id', $prefix . sanitize_html_class( $element->getAttribute( 'id' ) ) );
		}

		foreach ( iterator_to_array( $xpath->query( './/*[@data-clipboard-target]', $root ) ) as $element ) {
			$element->setAttribute( 'data-clipboard-target', $prefix . sanitize_html_class( $element->getAttribute( 'data-clipboard-target' ) ) );
			$element->setAttribute( 'class', 'zs-doc-copy' );
		}
	}

	/**
	 * Links to other sites open in a new tab and say so.
	 *
	 * @param \DOMDocument $doc   Document.
	 * @param \DOMXPath    $xpath XPath.
	 * @param \DOMElement  $root  Root.
	 */
	private static function mark_external_links( \DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root ): void {
		$own = (string) wp_parse_url( admin_url(), PHP_URL_HOST );

		foreach ( iterator_to_array( $xpath->query( './/a[@href]', $root ) ) as $link ) {
			$host = (string) wp_parse_url( $link->getAttribute( 'href' ), PHP_URL_HOST );
			if ( '' === $host || $host === $own ) {
				continue;
			}

			$link->setAttribute( 'target', '_blank' );
			$link->setAttribute( 'rel', 'noopener noreferrer' );
			$hint = $doc->createElement( 'span' );
			$hint->setAttribute( 'class', 'zs-sr-only' );
			$hint->appendChild( $doc->createTextNode( ' ' . __( '(opens in a new tab)', 'zero-spam' ) ) );
			$link->appendChild( $hint );
		}
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
}
