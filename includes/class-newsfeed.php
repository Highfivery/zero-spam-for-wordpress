<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps News_Feed to this file name.
/**
 * News and security notices from zerospam.org.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes;

use ZeroSpam\Core\Settings_Schema;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Downloads, parses and caches the public article feed of zerospam.org.
 *
 * One download serves the whole network: the parsed articles are kept in a
 * site transient and refreshed every six hours. When a download fails, no
 * other is tried for an hour and the last good copy (up to a week old) is
 * served instead. The request carries a generic user agent and no site data.
 *
 * Everything returned is plain text (titles, excerpts, categories) or a link
 * to zerospam.org; the screens that show it escape it.
 *
 * @since 6.0.0
 */
final class News_Feed {

	/**
	 * Feed address.
	 *
	 * @var string
	 */
	const FEED_URL = 'https://www.zerospam.org/articles/feed/';

	/**
	 * Site transient holding the parsed articles and when they were downloaded.
	 *
	 * @var string
	 */
	const TRANSIENT = 'zerospam_news';

	/**
	 * Site transient set for an hour after a failed download.
	 *
	 * @var string
	 */
	const BACKOFF_TRANSIENT = 'zerospam_news_backoff';

	/**
	 * How long a download is used before the feed is asked again.
	 *
	 * @var int
	 */
	const FRESH_FOR = 6 * HOUR_IN_SECONDS;

	/**
	 * How long a copy is kept to be served when downloads fail.
	 *
	 * @var int
	 */
	const KEEP_FOR = WEEK_IN_SECONDS;

	/**
	 * How long no download is tried after one failed.
	 *
	 * @var int
	 */
	const BACKOFF_FOR = HOUR_IN_SECONDS;

	/**
	 * Most articles kept.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 8;

	/**
	 * Longest excerpt, in characters.
	 *
	 * @var int
	 */
	const EXCERPT_LENGTH = 200;

	/**
	 * Largest feed read, in bytes.
	 *
	 * @var int
	 */
	const MAX_BYTES = MB_IN_BYTES;

	/**
	 * Hosts article links may point to.
	 *
	 * @var string[]
	 */
	const ALLOWED_HOSTS = array( 'zerospam.org', 'www.zerospam.org' );

	/**
	 * Category that marks a security notice (compared case-insensitively).
	 *
	 * @var string
	 */
	const SECURITY_CATEGORY = 'incident response';

	/**
	 * Whether the news is shown: the "Zero Spam News" setting is on and the
	 * `zerospam_news_feed_enabled` filter allows it.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$resolved = Settings_Schema::resolve( 'show_news' );
		$enabled  = null !== $resolved && 'enabled' === $resolved['value'];

		/**
		 * Filters whether Zero Spam downloads and shows news from zerospam.org.
		 *
		 * Return false to turn the news off whatever the "Zero Spam News"
		 * setting says; nothing is downloaded then.
		 *
		 * @since 6.0.0
		 *
		 * @param bool $enabled Whether the news is on. Default: the setting.
		 */
		return (bool) apply_filters( 'zerospam_news_feed_enabled', $enabled );
	}

	/**
	 * The articles, downloading them when the copy is older than six hours.
	 *
	 * @return array{items: array<int, array<string, mixed>>, fetched_at: int, stale: bool}|null Null when there is no copy at all.
	 */
	public static function get(): ?array {
		$cached = self::cached();

		if ( null !== $cached && time() - $cached['fetched_at'] < self::FRESH_FOR ) {
			return $cached + array( 'stale' => false );
		}

		if ( false === get_site_transient( self::BACKOFF_TRANSIENT ) ) {
			$items = self::download();

			if ( null !== $items ) {
				$fresh = array(
					'items'      => $items,
					'fetched_at' => time(),
				);
				set_site_transient( self::TRANSIENT, $fresh, self::KEEP_FOR );

				return $fresh + array( 'stale' => false );
			}

			set_site_transient( self::BACKOFF_TRANSIENT, time(), self::BACKOFF_FOR );
		}

		return null !== $cached ? $cached + array( 'stale' => true ) : null;
	}

	/**
	 * The stored copy, if it is usable.
	 *
	 * @return array{items: array<int, array<string, mixed>>, fetched_at: int}|null
	 */
	private static function cached(): ?array {
		$cached = get_site_transient( self::TRANSIENT );

		if ( ! is_array( $cached ) || ! isset( $cached['items'], $cached['fetched_at'] ) || ! is_array( $cached['items'] ) || ! is_int( $cached['fetched_at'] ) ) {
			return null;
		}

		return array(
			'items'      => array_values( $cached['items'] ),
			'fetched_at' => $cached['fetched_at'],
		);
	}

	/**
	 * Downloads and parses the feed.
	 *
	 * @return array<int, array<string, mixed>>|null Articles, or null when the download or the feed is unusable.
	 */
	private static function download(): ?array {
		$response = wp_safe_remote_get(
			self::FEED_URL,
			array(
				'timeout'             => 5,
				'redirection'         => 2,
				'limit_response_size' => self::MAX_BYTES,
				// Generic: WordPress's default user agent names the site.
				'user-agent'          => 'Zero Spam for WordPress/' . ZEROSPAM_VERSION,
				'headers'             => array( 'Accept' => 'application/rss+xml, application/xml;q=0.9' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$items = self::parse( (string) wp_remote_retrieve_body( $response ) );

		return $items ? $items : null;
	}

	/**
	 * Parses an RSS feed into plain-text articles.
	 *
	 * Articles without a title or without a link to zerospam.org are left out.
	 *
	 * @param string $xml Feed.
	 * @return array<int, array<string, mixed>>|null Articles (at most MAX_ITEMS), or null when the feed cannot be read.
	 */
	public static function parse( string $xml ): ?array {
		if ( '' === trim( $xml ) || ! function_exists( 'simplexml_load_string' ) ) {
			return null;
		}

		// No DOCTYPE means no entity can be defined, let alone loaded. A DOCTYPE
		// hidden in another encoding (UTF-16) is not caught here, but libxml
		// neither loads external entities (default since PHP 8.0, and
		// LIBXML_NONET) nor expands internal ones (no LIBXML_NOENT).
		if ( false !== stripos( $xml, '<!DOCTYPE' ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );
		$feed     = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $feed || ! isset( $feed->channel->item ) ) {
			return null;
		}

		$items = array();

		foreach ( $feed->channel->item as $item ) {
			$title = self::plain_text( (string) $item->title );
			$link  = self::safe_link( (string) $item->link );

			if ( '' === $title || null === $link ) {
				continue;
			}

			$categories = array();
			foreach ( $item->category as $category ) {
				$name = self::plain_text( (string) $category );
				if ( '' !== $name ) {
					$categories[] = $name;
				}
			}
			$categories = array_values( array_unique( $categories ) );

			$timestamp = strtotime( trim( (string) $item->pubDate ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- RSS element name.

			$items[] = array(
				'title'           => $title,
				'excerpt'         => self::excerpt( self::plain_text( (string) $item->description ) ),
				'date'            => false !== $timestamp ? gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) : null,
				'link'            => $link,
				'categories'      => $categories,
				'security_notice' => in_array( self::SECURITY_CATEGORY, array_map( 'strtolower', $categories ), true ),
			);

			if ( count( $items ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Text without markup, entities or runs of white space.
	 *
	 * @param string $text Text from the feed.
	 * @return string
	 */
	private static function plain_text( string $text ): string {
		// Decoded twice (feeds often double-encode), then stripped, so an encoded tag cannot survive as markup.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_strip_all_tags( $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Shortens text to EXCERPT_LENGTH characters on a word boundary.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function excerpt( string $text ): string {
		if ( mb_strlen( $text ) <= self::EXCERPT_LENGTH ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, self::EXCERPT_LENGTH );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > self::EXCERPT_LENGTH / 2 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, " \t\n\r\0\x0B.,;:" ) . "\u{2026}";
	}

	/**
	 * A link, when it is an https address on zerospam.org.
	 *
	 * @param string $link Link from the feed.
	 * @return string|null
	 */
	private static function safe_link( string $link ): ?string {
		$link  = trim( $link );
		$parts = wp_parse_url( $link );

		if (
			! is_array( $parts ) ||
			! isset( $parts['scheme'], $parts['host'] ) ||
			'https' !== strtolower( $parts['scheme'] ) ||
			! in_array( strtolower( $parts['host'] ), self::ALLOWED_HOSTS, true ) ||
			isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] )
		) {
			return null;
		}

		$clean = esc_url_raw( $link, array( 'https' ) );

		return $clean === $link ? $link : null;
	}
}
