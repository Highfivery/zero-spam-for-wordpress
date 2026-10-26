<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Report to this file name.
/**
 * Bounded reads across the sites of a network: their settings and their logs.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes;

use ZeroSpam\Core\Settings_Schema;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * What the 6.0 network page reads about its sites, without loading every
 * site: each site's stored settings are read straight from its options
 * table (no switch_to_blog(), no settings registry per site), at most
 * CHUNK sites per query and MAX_SITES sites in all; each site's log is read
 * with one UNION ALL per question over at most MAX_SITES log tables.
 *
 * - overview(): how many sites changed a network default, by setting
 *   (cached for five minutes in the site transient REPORT_TRANSIENT, cleared
 *   when the network values or a site's settings change).
 * - comparison(): one page of sites against the network values.
 * - stats(): detections per site for a period, the network's totals and
 *   the addresses seen on several sites (cached for ten minutes per period).
 *
 * Network values come from the network settings store
 * (`zerospam_network_settings`); a site overrides a network default when
 * its `zero-spam-{module}` option stores a different value for a key the
 * network sets and has not locked (the rule Network_Settings applies).
 *
 * @since 6.0.0
 */
final class Network_Report {

	/**
	 * Most sites read for the overview, the statistics and the attackers.
	 *
	 * @var int
	 */
	const MAX_SITES = 1000;

	/**
	 * Sites whose options one query reads.
	 *
	 * @var int
	 */
	const CHUNK = 100;

	/**
	 * Site transient of the overview.
	 *
	 * @var string
	 */
	const REPORT_TRANSIENT = 'zerospam_network_report';

	/**
	 * Site transient prefix of the statistics (the period follows).
	 *
	 * @var string
	 */
	const STATS_TRANSIENT = 'zerospam_network_stats_v6_';

	/**
	 * Periods of the statistics.
	 *
	 * @var string[]
	 */
	const PERIODS = array( 'today', 'yesterday', '7', '30' );

	/**
	 * Days the multi-site attackers look back.
	 *
	 * @var int
	 */
	const ATTACKER_DAYS = 7;

	/**
	 * Most multi-site attackers listed.
	 *
	 * @var int
	 */
	const ATTACKERS = 20;

	/**
	 * Clears the cached overview when the network values or a site's settings change.
	 */
	public static function register_hooks(): void {
		add_action( 'update_site_option_' . Network_Settings::META_KEY, array( self::class, 'flush' ), 10, 0 );
		add_action( 'add_site_option_' . Network_Settings::META_KEY, array( self::class, 'flush' ), 10, 0 );
		add_action( 'delete_site_option_' . Network_Settings::META_KEY, array( self::class, 'flush' ), 10, 0 );
		add_action( 'updated_option', array( self::class, 'flush_for_option' ), 10, 1 );
		add_action( 'added_option', array( self::class, 'flush_for_option' ), 10, 1 );
		add_action( 'deleted_option', array( self::class, 'flush_for_option' ), 10, 1 );
	}

	/**
	 * Clears the cached overview when a site's settings option changed.
	 *
	 * @param mixed $option Option name.
	 */
	public static function flush_for_option( $option ): void {
		if ( is_multisite() && is_string( $option ) && 0 === strpos( $option, 'zero-spam-' ) ) {
			self::flush();
		}
	}

	/**
	 * Forgets the cached overview.
	 */
	public static function flush(): void {
		delete_site_transient( self::REPORT_TRANSIENT );
	}

	/**
	 * The network settings store (`settings` => key => `value`, `locked`).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function network_values(): array {
		$store = Settings_Schema::normalize_network_store( get_site_option( Network_Settings::META_KEY, array() ) );

		return array_filter( (array) $store['settings'], 'is_array' );
	}

	/**
	 * Keys a site can override: the network sets a value and has not locked it.
	 *
	 * @param array<string, array<string, mixed>> $network Network values.
	 * @return array<string, mixed> Network value by key.
	 */
	public static function defaults_of( array $network ): array {
		$defaults = array();
		foreach ( $network as $key => $config ) {
			if ( empty( $config['locked'] ) && isset( $config['value'] ) ) {
				$defaults[ (string) $key ] = $config['value'];
			}
		}

		return $defaults;
	}

	/**
	 * How many sites changed each network default, cached for five minutes.
	 *
	 * @return array{sites: int, checked: int, capped: bool, custom: array<string, int>, overrides: int, sites_with_overrides: int, network_settings: int, locked: int}
	 */
	public static function overview(): array {
		$cached = get_site_transient( self::REPORT_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['custom'], $cached['sites'], $cached['checked'] ) ) {
			return $cached;
		}

		$network  = self::network_values();
		$defaults = self::defaults_of( $network );
		$types    = self::types();
		$total    = (int) get_sites( array( 'count' => true ) );
		$ids      = self::site_ids( self::MAX_SITES );
		$custom   = array_fill_keys( array_keys( $defaults ), 0 );
		$with     = 0;

		if ( $defaults ) {
			foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
				foreach ( self::stored_settings( $chunk ) as $stored ) {
					$changed = false;
					foreach ( $defaults as $key => $value ) {
						if ( array_key_exists( $key, $stored ) && ! self::same_value( $stored[ $key ], $value, $types[ $key ] ?? '' ) ) {
							++$custom[ $key ];
							$changed = true;
						}
					}
					$with += $changed ? 1 : 0;
				}
			}
		}

		$report = array(
			'sites'                => $total,
			'checked'              => count( $ids ),
			'capped'               => $total > count( $ids ),
			'custom'               => $custom,
			'overrides'            => array_sum( $custom ),
			'sites_with_overrides' => $with,
			'network_settings'     => count(
				array_filter(
					$network,
					static function ( array $config ): bool {
						return isset( $config['value'] );
					}
				)
			),
			'locked'               => count(
				array_filter(
					$network,
					static function ( array $config ): bool {
						return ! empty( $config['locked'] );
					}
				)
			),
		);

		set_site_transient( self::REPORT_TRANSIENT, $report, 5 * MINUTE_IN_SECONDS );

		return $report;
	}

	/**
	 * Sites that follow the network value of a setting (they store none, or
	 * the same value), among at most MAX_SITES sites; every site when the
	 * setting is locked.
	 *
	 * @param string[] $keys Setting keys.
	 * @return int[] Blog IDs that follow at least one of them.
	 */
	public static function sites_following( array $keys ): array {
		$network  = self::network_values();
		$ids      = self::site_ids( self::MAX_SITES );
		$types    = self::types();
		$keys     = array_values( array_filter( array_map( 'strval', $keys ) ) );
		$follow   = array();
		$all_keys = array();

		foreach ( $keys as $key ) {
			if ( ! empty( $network[ $key ]['locked'] ) ) {
				return $ids;
			}
			$all_keys[ $key ] = $network[ $key ]['value'] ?? null;
		}

		foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
			$stored_by_site = self::stored_settings( $chunk );
			foreach ( $chunk as $blog_id ) {
				$stored = $stored_by_site[ $blog_id ] ?? array();
				foreach ( $all_keys as $key => $value ) {
					if ( ! array_key_exists( $key, $stored ) || self::same_value( $stored[ $key ], $value, $types[ $key ] ?? '' ) ) {
						$follow[] = $blog_id;
						break;
					}
				}
			}
		}

		return $follow;
	}

	/**
	 * One page of sites against the network values: per setting the network
	 * holds, each site's value and where it comes from (locked, override or
	 * default). Secret values are never returned, only whether one is set.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Sites per page (at most 20).
	 * @param string $search   Text in the site address.
	 * @return array{total: int, sites: array<int, array{id: int, name: string, url: string}>, settings: array<int, array<string, mixed>>}
	 */
	public static function comparison( int $page, int $per_page, string $search = '' ): array {
		$per_page = max( 1, min( 20, $per_page ) );
		$sites    = self::sites_page( $page, $per_page, $search );
		$ids      = array_column( $sites['items'], 'id' );
		$stored   = $ids ? self::stored_settings( $ids ) : array();
		$network  = self::network_values();
		$types    = self::types();
		$rows     = array();

		foreach ( $network as $key => $config ) {
			$key   = (string) $key;
			$entry = Settings_Schema::setting( $key );

			if ( null === $entry || 'html' === $entry['type'] ) {
				continue;
			}

			$secret = (bool) $entry['secret'];
			$locked = ! empty( $config['locked'] );
			$has    = isset( $config['value'] );
			$cells  = array();

			foreach ( $ids as $blog_id ) {
				$site_has = array_key_exists( $key, $stored[ $blog_id ] ?? array() );
				$value    = $site_has ? $stored[ $blog_id ][ $key ] : null;

				if ( $locked ) {
					$source = 'locked';
					$value  = $config['value'] ?? null;
				} elseif ( $has && $site_has && ! self::same_value( $value, $config['value'], $types[ $key ] ?? '' ) ) {
					$source = 'override';
				} elseif ( $has ) {
					$source = 'default';
					$value  = $config['value'];
				} else {
					$source = $site_has ? 'site' : 'plugin';
				}

				$cells[ (string) $blog_id ] = array(
					'source' => $source,
					'value'  => $secret ? array( 'set' => is_scalar( $value ) && '' !== trim( (string) $value ) ) : $value,
				);
			}

			$network_value = $config['value'] ?? null;

			$rows[] = array(
				'key'       => $key,
				'title'     => $entry['title'],
				'type'      => $entry['type'],
				'secret'    => $secret,
				'locked'    => $locked,
				'has_value' => $has,
				'network'   => $secret ? array( 'set' => is_scalar( $network_value ) && '' !== trim( (string) $network_value ) ) : $network_value,
				'sites'     => (object) $cells,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return array(
			'total'    => $sites['total'],
			'sites'    => $sites['items'],
			'settings' => $rows,
		);
	}

	/**
	 * One page of the network's sites (ID, name, address), by ID.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Sites per page (at most 100).
	 * @param string $search   Text in the site's domain or path.
	 * @return array{total: int, items: array<int, array{id: int, name: string, url: string}>}
	 */
	public static function sites_page( int $page, int $per_page, string $search = '' ): array {
		$per_page = max( 1, min( 100, $per_page ) );
		$args     = array(
			'orderby'  => 'id',
			'order'    => 'ASC',
			'deleted'  => 0,
			'archived' => 0,
			'spam'     => 0,
		);

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$total = (int) get_sites( $args + array( 'count' => true ) );
		$page  = max( 1, min( $page, max( 1, (int) ceil( $total / $per_page ) ) ) );
		$items = array();

		foreach ( get_sites(
			$args + array(
				'number' => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		) as $site ) {
			$details = get_blog_details( (int) $site->blog_id );
			$items[] = array(
				'id'   => (int) $site->blog_id,
				'name' => $details ? wp_specialchars_decode( (string) $details->blogname, ENT_QUOTES ) : '',
				'url'  => untrailingslashit( (string) $site->domain . (string) $site->path ),
			);
		}

		return array(
			'total' => $total,
			'items' => $items,
		);
	}

	/**
	 * Network statistics for a period, cached for ten minutes: per site the
	 * detections, unique addresses, top type and country and the change from
	 * the period before; the network's totals; and the addresses seen on
	 * several sites in the last seven days.
	 *
	 * @param string $period today, yesterday, 7 or 30.
	 * @return array<string, mixed>
	 */
	public static function stats( string $period ): array {
		$period = in_array( $period, self::PERIODS, true ) ? $period : '30';
		$cached = get_site_transient( self::STATS_TRANSIENT . $period );

		if ( is_array( $cached ) && isset( $cached['sites'], $cached['totals'] ) ) {
			return $cached;
		}

		$stats = self::read_stats( $period );
		set_site_transient( self::STATS_TRANSIENT . $period, $stats, 10 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Forgets the cached statistics of every period.
	 */
	public static function flush_stats(): void {
		foreach ( self::PERIODS as $period ) {
			delete_site_transient( self::STATS_TRANSIENT . $period );
		}
	}

	/**
	 * Every site's log table, by blog ID (at most $max, lowest IDs first).
	 *
	 * @param int $max Most tables.
	 * @return array{tables: array<int, string>, capped: bool}
	 */
	public static function log_tables( int $max = self::MAX_SITES ): array {
		global $wpdb;

		$suffix = DB::$tables['log'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lists the plugin's own tables; callers cache what they read.
		$names = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' . $wpdb->esc_like( $suffix ) ) );
		$main  = get_main_site_id();
		$found = array();

		foreach ( is_array( $names ) ? $names : array() as $name ) {
			$name = (string) $name;

			if ( $wpdb->base_prefix . $suffix === $name ) {
				$found[ $main ] = $name;
			} elseif ( preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(\d+)_' . preg_quote( $suffix, '/' ) . '$/', $name, $match ) ) {
				$found[ (int) $match[1] ] = $name;
			}
		}

		ksort( $found );

		return array(
			'tables' => array_slice( $found, 0, $max, true ),
			'capped' => count( $found ) > $max,
		);
	}

	/**
	 * Whether two values of a setting are the same, as Site_Admin_Overrides
	 * compares a site's value with the network's: an unticked checkbox
	 * matches `disabled`, empty values match each other, numbers match their
	 * text and the order of a multi-select does not matter.
	 *
	 * @param mixed  $a    Value.
	 * @param mixed  $b    Value.
	 * @param string $type Setting type.
	 */
	public static function same_value( $a, $b, string $type ): bool {
		return self::comparable( $a, $type ) === self::comparable( $b, $type );
	}

	/**
	 * A value in a form that compares equal however it was stored.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Setting type.
	 * @return string|string[]
	 */
	private static function comparable( $value, string $type ) {
		if ( 'checkbox' === $type && 'disabled' === $value ) {
			$value = '';
		}

		if ( null === $value || false === $value || array() === $value ) {
			return '';
		}

		if ( is_array( $value ) ) {
			$items = array();
			foreach ( $value as $item ) {
				$items[] = is_scalar( $item ) ? (string) $item : (string) wp_json_encode( $item );
			}
			sort( $items, SORT_STRING );

			return $items;
		}

		if ( true === $value ) {
			return '1';
		}

		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
	}

	/**
	 * Setting types by key (from the registry built once per request).
	 *
	 * @return array<string, string>
	 */
	private static function types(): array {
		$types = array();
		foreach ( Settings_Schema::registry() as $key => $setting ) {
			if ( is_array( $setting ) && isset( $setting['type'] ) ) {
				$types[ (string) $key ] = (string) $setting['type'];
			}
		}

		return $types;
	}

	/**
	 * IDs of the network's active sites (at most $max, lowest first).
	 *
	 * @param int $max Most sites.
	 * @return int[]
	 */
	private static function site_ids( int $max ): array {
		return array_map(
			'intval',
			(array) get_sites(
				array(
					'fields'   => 'ids',
					'number'   => $max,
					'orderby'  => 'id',
					'order'    => 'ASC',
					'deleted'  => 0,
					'archived' => 0,
					'spam'     => 0,
				)
			)
		);
	}

	/**
	 * Each site's stored settings (every `zero-spam-{module}` option merged
	 * into one array of key => value), read with one query.
	 *
	 * @param int[] $blog_ids Sites (at most CHUNK).
	 * @return array<int, array<string, mixed>>
	 */
	public static function stored_settings( array $blog_ids ): array {
		global $wpdb;

		$blog_ids = array_slice( array_values( array_unique( array_map( 'intval', $blog_ids ) ) ), 0, self::CHUNK );
		$selects  = array();
		$like     = $wpdb->esc_like( 'zero-spam-' ) . '%';

		foreach ( $blog_ids as $blog_id ) {
			$selects[] = $wpdb->prepare( '(SELECT %d AS blog_id, option_name, option_value FROM %i WHERE option_name LIKE %s)', $blog_id, $wpdb->get_blog_prefix( $blog_id ) . 'options', $like );
		}

		$settings = array_fill_keys( $blog_ids, array() );

		if ( ! $selects ) {
			return $settings;
		}

		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Every part is prepared above; the plugin's own options of at most CHUNK sites; callers cache the result.
		$rows = $wpdb->get_results( implode( ' UNION ALL ', $selects ), ARRAY_A );
		$wpdb->suppress_errors( $suppress );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$value = maybe_unserialize( (string) $row['option_value'] );

			if ( ! is_array( $value ) ) {
				continue;
			}

			foreach ( $value as $key => $item ) {
				$settings[ (int) $row['blog_id'] ][ (string) $key ] = $item;
			}
		}

		return $settings;
	}

	/**
	 * Reads the statistics of a period (see stats()).
	 *
	 * @param string $period today, yesterday, 7 or 30.
	 * @return array<string, mixed>
	 */
	private static function read_stats( string $period ): array {
		global $wpdb;

		$range  = self::period_range( $period );
		$found  = self::log_tables();
		$tables = $found['tables'];
		$stats  = array(
			'period'    => $period,
			'from'      => $range['from'],
			'to'        => $range['to'],
			'totals'    => array(
				'blocked'    => 0,
				'unique_ips' => 0,
				'sites'      => (int) get_sites( array( 'count' => true ) ),
				'types'      => 0,
				'previous'   => 0,
			),
			'checked'   => count( $tables ),
			'capped'    => $found['capped'],
			'sites'     => array(),
			'attackers' => array(),
		);

		if ( ! $tables ) {
			return $stats;
		}

		$detections = DB::detections_sql();
		$names      = self::blog_names( array_keys( $tables ) );
		$totals     = array();
		$top_type   = array();
		$top_cty    = array();
		$ips_parts  = array();
		$type_parts = array();
		$by_site    = array();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $detections is prepared by DB::detections_sql().
		foreach ( $tables as $blog_id => $table ) {
			$totals[]     = $wpdb->prepare(
				"(SELECT %d AS blog_id, SUM(date_recorded >= %s) AS cur, SUM(date_recorded < %s) AS prev, COUNT(DISTINCT CASE WHEN date_recorded >= %s THEN user_ip END) AS ips FROM %i WHERE date_recorded >= %s AND date_recorded < %s AND {$detections})",
				$blog_id,
				$range['from'],
				$range['from'],
				$range['from'],
				$table,
				$range['prev_from'],
				$range['to']
			);
			$top_type[]   = $wpdb->prepare( "(SELECT %d AS blog_id, log_type AS v, COUNT(*) AS n FROM %i WHERE date_recorded >= %s AND date_recorded < %s AND {$detections} GROUP BY log_type ORDER BY n DESC LIMIT 1)", $blog_id, $table, $range['from'], $range['to'] );
			$top_cty[]    = $wpdb->prepare( "(SELECT %d AS blog_id, country AS v, COUNT(*) AS n FROM %i WHERE date_recorded >= %s AND date_recorded < %s AND country IS NOT NULL AND country <> '' AND {$detections} GROUP BY country ORDER BY n DESC LIMIT 1)", $blog_id, $table, $range['from'], $range['to'] );
			$ips_parts[]  = $wpdb->prepare( "(SELECT DISTINCT user_ip FROM %i WHERE date_recorded >= %s AND date_recorded < %s AND {$detections})", $table, $range['from'], $range['to'] );
			$type_parts[] = $wpdb->prepare( "(SELECT DISTINCT log_type FROM %i WHERE date_recorded >= %s AND date_recorded < %s AND {$detections})", $table, $range['from'], $range['to'] );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Every part is prepared above; one row per site (at most MAX_SITES), cached in a site transient.
		$rows = $wpdb->get_results( implode( ' UNION ALL ', $totals ), ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$by_site[ (int) $row['blog_id'] ] = array(
				'blocked'  => (int) $row['cur'],
				'previous' => (int) $row['prev'],
				'ips'      => (int) $row['ips'],
			);
		}

		$types = array();
		foreach ( (array) $wpdb->get_results( implode( ' UNION ALL ', $top_type ), ARRAY_A ) as $row ) {
			$types[ (int) $row['blog_id'] ] = (string) $row['v'];
		}

		$countries = array();
		foreach ( (array) $wpdb->get_results( implode( ' UNION ALL ', $top_cty ), ARRAY_A ) as $row ) {
			$countries[ (int) $row['blog_id'] ] = strtoupper( (string) $row['v'] );
		}

		$unique_ips  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM (' . implode( ' UNION ', $ips_parts ) . ') AS zs_ips' );
		$type_count  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM (' . implode( ' UNION ', $type_parts ) . ') AS zs_types' );
		$attackers   = self::read_attackers( $tables );
		$type_labels = \ZeroSpam\Includes\Rest\Stats_Controller::type_labels();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $tables as $blog_id => $table ) {
			$site     = $by_site[ $blog_id ] ?? array(
				'blocked'  => 0,
				'previous' => 0,
				'ips'      => 0,
			);
			$details  = get_site( $blog_id );
			$code     = $countries[ $blog_id ] ?? '';
			$country  = '' !== $code ? \ZeroSpam\Core\Utilities::countries( $code ) : '';
			$type     = $types[ $blog_id ] ?? '';
			$previous = $site['previous'];

			$stats['totals']['blocked']  += $site['blocked'];
			$stats['totals']['previous'] += $previous;

			$stats['sites'][] = array(
				'id'             => $blog_id,
				'name'           => $names[ $blog_id ] ?? '',
				'url'            => $details ? untrailingslashit( (string) $details->domain . (string) $details->path ) : '',
				'blocked'        => $site['blocked'],
				'previous'       => $previous,
				'unique_ips'     => $site['ips'],
				'top_type'       => $type,
				'top_type_label' => '' !== $type ? ( $type_labels[ $type ] ?? ucfirst( str_replace( array( '_', '-' ), ' ', $type ) ) ) : '',
				'top_country'    => $code,
				'country_name'   => is_string( $country ) ? $country : $code,
				'trend'          => $previous > 0 ? (int) round( ( $site['blocked'] - $previous ) / $previous * 100 ) : ( $site['blocked'] > 0 ? null : 0 ),
			);
		}

		$stats['totals']['unique_ips'] = $unique_ips;
		$stats['totals']['types']      = $type_count;
		$stats['attackers']            = $attackers;

		return $stats;
	}

	/**
	 * Addresses detected on at least two sites in the last ATTACKER_DAYS days.
	 *
	 * @param array<int, string> $tables Log tables by blog ID.
	 * @return array<int, array{ip: string, sites: int, country: string, country_name: string}>
	 */
	private static function read_attackers( array $tables ): array {
		global $wpdb;

		$since      = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - self::ATTACKER_DAYS * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The logs store site-local times.
		$detections = DB::detections_sql();
		$parts      = array();

		foreach ( $tables as $blog_id => $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $detections is prepared by DB::detections_sql().
			$parts[] = $wpdb->prepare( "(SELECT %d AS blog_id, user_ip, MAX(country) AS country FROM %i WHERE date_recorded >= %s AND {$detections} GROUP BY user_ip)", $blog_id, $table, $since );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Every part is prepared above; at most ATTACKERS rows, cached in a site transient.
		$rows = $wpdb->get_results( 'SELECT user_ip, COUNT(*) AS sites, MAX(country) AS country FROM (' . implode( ' UNION ALL ', $parts ) . ') AS zs_seen GROUP BY user_ip HAVING sites >= 2 ORDER BY sites DESC, user_ip ASC LIMIT ' . (int) self::ATTACKERS, ARRAY_A );

		$attackers = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$code        = strtoupper( (string) $row['country'] );
			$name        = '' !== $code ? \ZeroSpam\Core\Utilities::countries( $code ) : '';
			$attackers[] = array(
				'ip'           => (string) $row['user_ip'],
				'sites'        => (int) $row['sites'],
				'country'      => $code,
				'country_name' => is_string( $name ) ? $name : $code,
			);
		}

		return $attackers;
	}

	/**
	 * Site names by blog ID, read with one query per CHUNK sites.
	 *
	 * @param int[] $blog_ids Sites.
	 * @return array<int, string>
	 */
	private static function blog_names( array $blog_ids ): array {
		global $wpdb;

		$names = array();
		foreach ( array_chunk( $blog_ids, self::CHUNK ) as $chunk ) {
			$selects = array();
			foreach ( $chunk as $blog_id ) {
				$selects[] = $wpdb->prepare( "(SELECT %d AS blog_id, option_value FROM %i WHERE option_name = 'blogname')", $blog_id, $wpdb->get_blog_prefix( (int) $blog_id ) . 'options' );
			}

			$suppress = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Every part is prepared above; one row per site, cached with the statistics.
			$rows = $wpdb->get_results( implode( ' UNION ALL ', $selects ), ARRAY_A );
			$wpdb->suppress_errors( $suppress );

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$names[ (int) $row['blog_id'] ] = wp_specialchars_decode( (string) $row['option_value'], ENT_QUOTES );
			}
		}

		return $names;
	}

	/**
	 * The dates (site-local, as the logs store them) of a period and of the
	 * period before it, of the same length.
	 *
	 * @param string $period today, yesterday, 7 or 30.
	 * @return array{from: string, to: string, prev_from: string}
	 */
	public static function period_range( string $period ): array {
		$now   = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The logs store site-local times.
		$today = (int) strtotime( gmdate( 'Y-m-d 00:00:00', $now ) . ' UTC' );

		switch ( $period ) {
			case 'today':
				$from = $today;
				$to   = $now + 1;
				$len  = DAY_IN_SECONDS;
				break;
			case 'yesterday':
				$from = $today - DAY_IN_SECONDS;
				$to   = $today;
				$len  = DAY_IN_SECONDS;
				break;
			case '7':
				$from = $now - 7 * DAY_IN_SECONDS;
				$to   = $now + 1;
				$len  = 7 * DAY_IN_SECONDS;
				break;
			default:
				$from = $now - 30 * DAY_IN_SECONDS;
				$to   = $now + 1;
				$len  = 30 * DAY_IN_SECONDS;
		}

		return array(
			'from'      => gmdate( 'Y-m-d H:i:s', $from ),
			'to'        => gmdate( 'Y-m-d H:i:s', $to ),
			'prev_from' => gmdate( 'Y-m-d H:i:s', $from - $len ),
		);
	}
}
