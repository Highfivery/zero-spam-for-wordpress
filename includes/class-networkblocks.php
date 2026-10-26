<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Blocks to this file name.
/**
 * IP addresses blocked on every site of a network.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes;

use ZeroSpam\Core\User;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The network-wide block list: one table for the whole network
 * (`{base_prefix}wpzerospam_network_blocked`, DB 1.5), written by network
 * administrators (`zero-spam/v1/network/blocks`) and enforced by
 * Access::check_blocked() on every site, after the site's own blocks.
 *
 * Addresses are stored and matched in canonical form (User::normalize_ip()),
 * as site blocks are; ranges are not accepted, as for site blocks. A row has
 * the columns of a site block that Access::get_blocked_details() reads
 * (`blocked_type`, `start_block`, `end_block`, UTC), so the same start and
 * end rules apply.
 *
 * The block check runs on every front-end request of every site, so it
 * reads one cached index: a site transient with the number of network
 * blocks and, while there are at most INDEX_MAX of them, every block by
 * address. A network with no network blocks pays one transient read; one
 * with more than INDEX_MAX pays one indexed query (by address, LIMIT 1)
 * instead. Every write clears the index.
 *
 * @since 6.0.0
 */
final class Network_Blocks {

	/**
	 * Site transient holding the index (see index()).
	 *
	 * @var string
	 */
	const TRANSIENT = 'zerospam_network_blocks';

	/**
	 * How long the index is kept, in seconds (writes clear it at once).
	 *
	 * @var int
	 */
	const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Most blocks the index holds; beyond it, each check is one indexed query.
	 *
	 * @var int
	 */
	const INDEX_MAX = 1000;

	/**
	 * Columns of a block row (a subset of a site block's, plus `created_by`).
	 *
	 * @var string[]
	 */
	const COLUMNS = array( 'blocked_id', 'blocked_type', 'user_ip', 'date_added', 'start_block', 'end_block', 'reason', 'created_by' );

	/**
	 * The index, read once per request.
	 *
	 * @var array{count: int, ips: array<string, array<string, mixed>>|null}|null
	 */
	private static ?array $index = null;

	/**
	 * Whether the table exists, by table name (checked once per request).
	 *
	 * @var array<string, bool>
	 */
	private static array $exists = array();

	/**
	 * Full name of the table.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->base_prefix . DB::$tables['network_blocked'];
	}

	/**
	 * The network block of an address, or null.
	 *
	 * Expired and future blocks are returned too: Access::get_blocked_details()
	 * decides whether a block applies now. Null on a single site.
	 *
	 * @param string $ip Visitor IP address (any form).
	 * @return array<string, mixed>|null Block row.
	 */
	public static function find( string $ip ): ?array {
		if ( ! is_multisite() ) {
			return null;
		}

		$canonical = User::normalize_ip( $ip );

		if ( '' === $canonical ) {
			return null;
		}

		$index = self::index();

		if ( 0 === $index['count'] ) {
			return null;
		}

		if ( null !== $index['ips'] ) {
			return $index['ips'][ $canonical ] ?? null;
		}

		// The index counted more than INDEX_MAX rows, so the table exists: one indexed query, no existence check.
		return self::query_by_ip( $canonical );
	}

	/**
	 * Number of network blocks (from the index).
	 */
	public static function count(): int {
		return is_multisite() ? self::index()['count'] : 0;
	}

	/**
	 * The index: how many network blocks there are and, up to INDEX_MAX,
	 * every block by address (one site transient, rebuilt with at most two
	 * bounded queries when missing).
	 *
	 * @return array{count: int, ips: array<string, array<string, mixed>>|null}
	 */
	public static function index(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}

		$cached = get_site_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['count'] ) && array_key_exists( 'ips', $cached ) ) {
			self::$index = array(
				'count' => (int) $cached['count'],
				'ips'   => is_array( $cached['ips'] ) ? $cached['ips'] : null,
			);

			return self::$index;
		}

		self::$index = self::build_index();
		set_site_transient( self::TRANSIENT, self::$index, self::TTL );

		return self::$index;
	}

	/**
	 * Forgets the index (after every write).
	 */
	public static function flush(): void {
		self::$index = null;
		delete_site_transient( self::TRANSIENT );
	}

	/**
	 * Forgets the index kept in this request only (between tests).
	 */
	public static function reset_request_cache(): void {
		self::$index  = null;
		self::$exists = array();
	}

	/**
	 * One block by ID, or null.
	 *
	 * @param int $id Block ID.
	 * @return array<string, mixed>|null
	 */
	public static function read( int $id ): ?array {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row of the plugin's own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE `blocked_id` = %d', self::table(), $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * The block of a canonical address, or null (one indexed query).
	 *
	 * @param string $canonical Address in canonical form.
	 * @return array<string, mixed>|null
	 */
	public static function read_by_ip( string $canonical ): ?array {
		return self::table_exists() ? self::query_by_ip( $canonical ) : null;
	}

	/**
	 * The block of a canonical address, with one query by the unique key.
	 *
	 * @param string $canonical Address in canonical form.
	 * @return array<string, mixed>|null
	 */
	private static function query_by_ip( string $canonical ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row of the plugin's own table, by its unique key.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE `user_ip` = %s LIMIT 1', self::table(), $canonical ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * The blocks of some addresses, by canonical address (one query).
	 *
	 * @param string[] $ips Addresses (any form; at most 500).
	 * @return array<string, array<string, mixed>>
	 */
	public static function by_ips( array $ips ): array {
		global $wpdb;

		$canonical = array();
		foreach ( array_slice( $ips, 0, 500 ) as $ip ) {
			$normalized = User::normalize_ip( (string) $ip );
			if ( '' !== $normalized ) {
				$canonical[ $normalized ] = true;
			}
		}

		if ( ! $canonical || ! is_multisite() || 0 === self::count() || ! self::table_exists() ) {
			return array();
		}

		$list         = array_keys( $canonical );
		$placeholders = implode( ', ', array_fill( 0, count( $list ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders holds one %s per address (at most 500).
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `user_ip` IN ({$placeholders})", array_merge( array( self::table() ), $list ) ), ARRAY_A );

		$found = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$found[ (string) $row['user_ip'] ] = $row;
		}

		return $found;
	}

	/**
	 * Adds a block, or replaces the block of the same address.
	 *
	 * @param array<string, mixed> $record Columns: user_ip (canonical), blocked_type, start_block, end_block, reason.
	 * @return array{id: int, created: bool, previous: array<string, mixed>|null}|null Null when it could not be saved.
	 */
	public static function save( array $record ): ?array {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return null;
		}

		$record = array_intersect_key( $record, array_flip( self::COLUMNS ) );
		unset( $record['blocked_id'] );

		$record['date_added'] = current_time( 'mysql' );
		$record['created_by'] = get_current_user_id();

		$existing = self::read_by_ip( (string) $record['user_ip'] );

		if ( null !== $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Updates the plugin's own row.
			$wpdb->update( self::table(), $record, array( 'blocked_id' => (int) $existing['blocked_id'] ) );
			self::flush();

			return array(
				'id'       => (int) $existing['blocked_id'],
				'created'  => false,
				'previous' => $existing,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserts into the plugin's own table.
		if ( ! $wpdb->insert( self::table(), $record ) ) {
			return null;
		}

		$id = (int) $wpdb->insert_id;
		self::flush();

		return array(
			'id'       => $id,
			'created'  => true,
			'previous' => null,
		);
	}

	/**
	 * Removes a block.
	 *
	 * @param int $id Block ID.
	 * @return bool Whether a row was removed.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes the plugin's own row.
		$deleted = (bool) $wpdb->delete( self::table(), array( 'blocked_id' => $id ), array( '%d' ) );
		self::flush();

		return $deleted;
	}

	/**
	 * Writes removed blocks back with their IDs (undo); an address blocked
	 * again in the meantime, or an ID taken, is skipped.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows as read.
	 * @return int Blocks written back.
	 */
	public static function restore( array $rows ): int {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$restored = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['user_ip'] ) || null !== self::read_by_ip( (string) $row['user_ip'] ) ) {
				continue;
			}

			$data = array();
			foreach ( self::COLUMNS as $column ) {
				if ( isset( $row[ $column ] ) && is_scalar( $row[ $column ] ) ) {
					$data[ $column ] = (string) $row[ $column ];
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes the plugin's own row back.
			$restored += (int) $wpdb->insert( self::table(), $data );
		}

		self::flush();

		return $restored;
	}

	/**
	 * Builds the index (count, and the blocks while there are few enough).
	 *
	 * @return array{count: int, ips: array<string, array<string, mixed>>|null}
	 */
	private static function build_index(): array {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'count' => 0,
				'ips'   => array(),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts the plugin's own table; cached in a site transient.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );

		if ( $count > self::INDEX_MAX ) {
			return array(
				'count' => $count,
				'ips'   => null,
			);
		}

		$ips = array();

		if ( $count > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- At most INDEX_MAX rows of the plugin's own table; cached in a site transient.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT `blocked_id`, `blocked_type`, `user_ip`, `start_block`, `end_block`, `reason` FROM %i LIMIT %d', self::table(), self::INDEX_MAX ), ARRAY_A );

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$ips[ (string) $row['user_ip'] ] = $row;
			}
		}

		return array(
			'count' => $count,
			'ips'   => $ips,
		);
	}

	/**
	 * Whether the table exists (it is created with DB 1.5; a site that has
	 * not run the upgrade yet may load before it does). Checked once per request.
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table = self::table();

		if ( ! isset( self::$exists[ $table ] ) ) {
			$suppress = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checks the plugin's own table once per request.
			self::$exists[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$wpdb->suppress_errors( $suppress );
		}

		return self::$exists[ $table ];
	}
}
