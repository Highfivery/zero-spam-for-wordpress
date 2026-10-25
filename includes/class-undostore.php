<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Undo_Store to this file name.
/**
 * Short-lived copies of deleted rows, so a deletion can be undone.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Keeps the rows a REST deletion removed for two minutes, under a random
 * token that only the user who deleted them can redeem, on the site they
 * were deleted from, once.
 *
 * Each deletion is one transient, `zerospam_undo_{token}`, holding at most
 * MAX_ROWS rows. Restoring puts the rows back with their original IDs, so
 * links and the live feed's `since_id` stay valid.
 *
 * @since 6.0.0
 */
final class Undo_Store {

	/**
	 * Transient name prefix; the token follows.
	 *
	 * @var string
	 */
	const PREFIX = 'zerospam_undo_';

	/**
	 * How long a deletion can be undone, in seconds.
	 *
	 * @var int
	 */
	const TTL = 2 * MINUTE_IN_SECONDS;

	/**
	 * Most rows one token keeps.
	 *
	 * @var int
	 */
	const MAX_ROWS = 500;

	/**
	 * What can be restored: kind => DB::$tables key and the columns written back.
	 *
	 * @var array<string, array{table: string, columns: string[]}>
	 */
	const KINDS = array(
		'activity' => array(
			'table'   => 'log',
			'columns' => array( 'log_id', 'blog_id', 'log_type', 'user_ip', 'date_recorded', 'page_url', 'submission_data', 'country', 'country_name', 'region', 'region_name', 'city', 'zip', 'latitude', 'longitude' ),
		),
		'blocks'   => array(
			'table'   => 'blocked',
			'columns' => array( 'blocked_id', 'blocked_type', 'user_ip', 'blocked_key', 'key_type', 'date_added', 'start_block', 'end_block', 'reason' ),
		),
	);

	/**
	 * Keeps deleted rows and returns the token that restores them.
	 *
	 * @param string                           $kind A key of KINDS.
	 * @param array<int, array<string, mixed>> $rows Rows as read from the table (at most MAX_ROWS).
	 * @return string|null Token, or null for an unknown kind, no rows or too many rows.
	 */
	public static function put( string $kind, array $rows ): ?string {
		if ( ! isset( self::KINDS[ $kind ] ) || ! $rows || count( $rows ) > self::MAX_ROWS ) {
			return null;
		}

		$token = wp_generate_password( 32, false, false );

		set_transient(
			self::PREFIX . $token,
			array(
				'kind'    => $kind,
				'user_id' => get_current_user_id(),
				'blog_id' => get_current_blog_id(),
				'rows'    => array_values( $rows ),
			),
			self::TTL
		);

		return $token;
	}

	/**
	 * Whether a string has the shape of a token.
	 *
	 * @param string $token Token.
	 */
	public static function is_token( string $token ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9]{32}$/', $token );
	}

	/**
	 * Restores the rows of a token, once.
	 *
	 * A token belongs to the user who deleted the rows and to the site they
	 * were deleted from; anyone else, an expired token and a token already
	 * used get null. A row whose ID was taken in the meantime, and a block
	 * whose address or location was blocked again, are skipped (counted in
	 * `skipped`). Restored activity entries are pruned to `max_logs` like
	 * new ones.
	 *
	 * @param string $token Token.
	 * @return array{kind: string, restored: int, skipped: int}|null
	 */
	public static function restore( string $token ): ?array {
		global $wpdb;

		if ( ! self::is_token( $token ) ) {
			return null;
		}

		$entry = get_transient( self::PREFIX . $token );

		if (
			! is_array( $entry ) ||
			! isset( $entry['kind'], $entry['user_id'], $entry['blog_id'], $entry['rows'], self::KINDS[ $entry['kind'] ] ) ||
			! is_array( $entry['rows'] ) ||
			get_current_user_id() !== (int) $entry['user_id'] ||
			get_current_blog_id() !== (int) $entry['blog_id']
		) {
			return null;
		}

		// Single use: gone before anything is written back.
		delete_transient( self::PREFIX . $token );

		$kind    = (string) $entry['kind'];
		$table   = $wpdb->prefix . DB::$tables[ self::KINDS[ $kind ]['table'] ];
		$columns = self::KINDS[ $kind ]['columns'];
		$rows    = array_values( array_filter( $entry['rows'], 'is_array' ) );

		// A block whose address or location was blocked again in the meantime
		// is not restored next to the new block.
		if ( 'blocks' === $kind ) {
			$rows = self::without_existing_blocks( $table, $rows );
		}

		$restored = 0;
		foreach ( array_chunk( $rows, 100 ) as $chunk ) {
			$restored += self::insert_rows( $table, $columns, $chunk );
		}

		if ( 'activity' === $kind && $restored > 0 ) {
			DB::prune_log_table();
		}

		return array(
			'kind'     => $kind,
			'restored' => $restored,
			'skipped'  => count( $entry['rows'] ) - $restored,
		);
	}

	/**
	 * Leaves out the blocks whose IP address or location key has a block again.
	 *
	 * @param string                           $table Full name of the blocked table.
	 * @param array<int, array<string, mixed>> $rows  Block rows to restore.
	 * @return array<int, array<string, mixed>>
	 */
	private static function without_existing_blocks( string $table, array $rows ): array {
		global $wpdb;

		$ips  = array();
		$keys = array();
		foreach ( $rows as $row ) {
			if ( 'ip' === ( $row['key_type'] ?? 'ip' ) ) {
				$ips[] = (string) ( $row['user_ip'] ?? '' );
			} else {
				$keys[] = (string) ( $row['blocked_key'] ?? '' );
			}
		}

		$clauses = array();
		$values  = array( $table );
		if ( $ips ) {
			$clauses[] = "( `key_type` = 'ip' AND `user_ip` IN (" . implode( ', ', array_fill( 0, count( $ips ), '%s' ) ) . ') )';
			$values    = array_merge( $values, $ips );
		}
		if ( $keys ) {
			$clauses[] = "( `key_type` <> 'ip' AND `blocked_key` IN (" . implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) . ') )';
			$values    = array_merge( $values, $keys );
		}

		if ( ! $clauses ) {
			return $rows;
		}

		$where = implode( ' OR ', $clauses );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds fixed fragments with one %s per value (at most MAX_ROWS).
		$existing = $wpdb->get_results( $wpdb->prepare( "SELECT `key_type`, `user_ip`, `blocked_key` FROM %i WHERE {$where}", $values ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$taken = array();
		foreach ( is_array( $existing ) ? $existing : array() as $row ) {
			$taken[ self::block_identity( $row ) ] = true;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $taken ): bool {
					return ! isset( $taken[ self::block_identity( $row ) ] );
				}
			)
		);
	}

	/**
	 * What a block blocks: its IP address, or its location key type and value.
	 *
	 * @param array<string, mixed> $row Block row.
	 */
	private static function block_identity( array $row ): string {
		$key_type = (string) ( $row['key_type'] ?? 'ip' );

		return 'ip' === $key_type ? 'ip|' . (string) ( $row['user_ip'] ?? '' ) : $key_type . '|' . strtolower( (string) ( $row['blocked_key'] ?? '' ) );
	}

	/**
	 * Writes rows back with INSERT IGNORE (an ID taken in the meantime is skipped).
	 *
	 * @param string                           $table   Full table name.
	 * @param string[]                         $columns Columns to write (fixed list from KINDS).
	 * @param array<int, array<string, mixed>> $rows    Rows.
	 * @return int Rows written.
	 */
	private static function insert_rows( string $table, array $columns, array $rows ): int {
		global $wpdb;

		$tuples = array();
		$values = array( $table );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$placeholders = array();
			foreach ( $columns as $column ) {
				$value = $row[ $column ] ?? null;

				if ( null === $value ) {
					$placeholders[] = 'NULL';
					continue;
				}

				$placeholders[] = '%s';
				$values[]       = is_scalar( $value ) ? (string) $value : '';
			}

			$tuples[] = '(' . implode( ', ', $placeholders ) . ')';
		}

		if ( ! $tuples ) {
			return 0;
		}

		$column_list = '`' . implode( '`, `', $columns ) . '`';
		$tuple_list  = implode( ', ', $tuples );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Restores the plugin's own rows; the column list is the fixed list from KINDS and the tuples hold only %s placeholders and NULL; every value is bound by prepare().
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i ({$column_list}) VALUES {$tuple_list}", $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_int( $result ) ? $result : 0;
	}
}
