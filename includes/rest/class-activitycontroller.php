<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Activity_Controller to this file name.
/**
 * REST routes for the activity log (detection log).
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Access;
use ZeroSpam\Core\User;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Undo_Store;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/activity`: the current site's activity log, filtered, sorted
 * and paged; one entry with its details; deleting entries with undo.
 *
 * Logged submission data is sanitized when it is written (DB::log()) and is
 * returned as stored: clients must escape it. Every row says whether its IP
 * is blocked right now; that is worked out with one query for the whole page.
 *
 * The live feed asks for `since_id`: entries newer than the last one it has,
 * newest first, without a total (a primary-key range read). A caller that
 * does not page (a short "latest" list) sends `no_count` to skip the total.
 *
 * `days` limits a list to the last N days of the site's calendar (today
 * included, as stats/timeseries counts them), `detections_only` leaves out
 * entries that are not detections (DB::NON_DETECTION_TYPES), and
 * `network_only` keeps the detections only the Zero Spam network made
 * (Stats_Controller::network_only_sql()); every row says whether it is one
 * (`network`).
 *
 * @since 6.0.0
 */
class Activity_Controller extends Base_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'activity';

	/**
	 * Columns a list may be sorted by.
	 *
	 * @var string[]
	 */
	const ORDERBY = array( 'date_recorded', 'log_type', 'user_ip', 'country', 'region' );

	/**
	 * Columns of a list row (submission_data is added when `failed` is asked for).
	 *
	 * @var string[]
	 */
	const LIST_COLUMNS = array( 'log_id', 'log_type', 'user_ip', 'date_recorded', 'page_url', 'country', 'country_name', 'region', 'region_name', 'city', 'zip', 'latitude', 'longitude' );

	/**
	 * Most entries one bulk delete accepts (and keeps for undo).
	 *
	 * @var int
	 */
	const MAX_BULK = Undo_Store::MAX_ROWS;

	/**
	 * Most values of each facet.
	 *
	 * @var array<string, int>
	 */
	const FACET_LIMITS = array(
		'type'    => 100,
		'country' => 250,
		'ip'      => 10,
	);

	/**
	 * Longest `days` period.
	 *
	 * @var int
	 */
	const MAX_DAYS = 365;

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Activity entry ID.', 'zero-spam' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_delete' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'ids'     => array(
							'description' => __( 'IDs of the entries to delete (at most 500).', 'zero-spam' ),
							'type'        => 'array',
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'maxItems'    => self::MAX_BULK,
						),
						'all'     => array(
							'description' => __( 'Delete every entry of the log (needs confirm; cannot be undone).', 'zero-spam' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'confirm' => array(
							'description' => __( 'Confirms deleting every entry.', 'zero-spam' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Query parameters of the list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		return array(
			'type'            => array(
				'description' => __( 'Only these detection types.', 'zero-spam' ),
				'type'        => 'array',
				'items'       => array(
					'type'    => 'string',
					'pattern' => '^[A-Za-z0-9_-]{1,64}$',
				),
				'maxItems'    => 50,
			),
			'country'         => array(
				'description' => __( 'Only these countries (two-letter codes).', 'zero-spam' ),
				'type'        => 'array',
				'items'       => array(
					'type'    => 'string',
					'pattern' => '^[A-Za-z]{2}$',
				),
				'maxItems'    => 250,
			),
			'ip'              => array(
				'description' => __( 'Only this IP address.', 'zero-spam' ),
				'type'        => 'string',
				'maxLength'   => 64,
			),
			'after'           => array(
				'description' => __( 'Only entries recorded at or after this date (YYYY-MM-DD) or time (ISO 8601).', 'zero-spam' ),
				'type'        => 'string',
				'maxLength'   => 40,
			),
			'before'          => array(
				'description' => __( 'Only entries recorded before this time (ISO 8601); a date (YYYY-MM-DD) includes that whole day.', 'zero-spam' ),
				'type'        => 'string',
				'maxLength'   => 40,
			),
			'search'          => array(
				'description' => __( 'Text to look for in the IP address, page address, country, region or city.', 'zero-spam' ),
				'type'        => 'string',
				'maxLength'   => 100,
			),
			'page'            => array(
				'description' => __( 'Page of results; a page past the last one returns the last page.', 'zero-spam' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
			),
			'per_page'        => array(
				'description' => __( 'Entries per page (1 to 100).', 'zero-spam' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => self::MAX_PER_PAGE,
				'default'     => 20,
			),
			'orderby'         => array(
				'description' => __( 'Sort column.', 'zero-spam' ),
				'type'        => 'string',
				'enum'        => self::ORDERBY,
				'default'     => 'date_recorded',
			),
			'order'           => array(
				'description' => __( 'Sort direction.', 'zero-spam' ),
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
			),
			'since_id'        => array(
				'description' => __( 'Only entries newer than this ID, newest first, without a total (the live feed).', 'zero-spam' ),
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'facets'          => array(
				'description' => __( 'Return the number of entries per type, country and IP address for the filters instead of the entries.', 'zero-spam' ),
				'type'        => 'boolean',
				'default'     => false,
			),
			'no_count'        => array(
				'description' => __( 'Skip counting every matching entry: no X-WP-Total or X-WP-TotalPages headers, and a page past the last one is empty (for lists that are not paged).', 'zero-spam' ),
				'type'        => 'boolean',
				'default'     => false,
			),
			'days'            => array(
				'description' => __( 'Only entries of the last N days of the site\'s calendar, today included (1 to 365).', 'zero-spam' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => self::MAX_DAYS,
			),
			'detections_only' => array(
				'description' => __( 'Only detections: leave out entries such as sent rescue links.', 'zero-spam' ),
				'type'        => 'boolean',
				'default'     => false,
			),
			'network_only'    => array(
				'description' => __( 'Only the detections the Zero Spam network made (Enhanced Protection).', 'zero-spam' ),
				'type'        => 'boolean',
				'default'     => false,
			),
		);
	}

	/**
	 * The list, the live feed or the facets.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$filters = $this->filters( $request );

		if ( is_wp_error( $filters ) ) {
			return $filters;
		}

		if ( $request->get_param( 'facets' ) ) {
			return rest_ensure_response( $this->filter_fields( $this->facets( $filters ), $request ) );
		}

		$per_page = $this->clamp_per_page( $request->get_param( 'per_page' ) );
		$since_id = $request->get_param( 'since_id' );

		if ( null !== $since_id ) {
			return $this->live_feed( $request, $filters, (int) $since_id, $per_page );
		}

		global $wpdb;

		$where    = $this->where( $filters );
		$table    = self::log_table();
		$orderby  = in_array( $request->get_param( 'orderby' ), self::ORDERBY, true ) ? (string) $request->get_param( 'orderby' ) : 'date_recorded';
		$order    = 'asc' === $request->get_param( 'order' ) ? 'ASC' : 'DESC';
		$columns  = $this->select_columns( $request );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$sql_cond = '' !== $where['sql'] ? ' WHERE ' . $where['sql'] : '';
		$total    = null;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Paged list of the plugin's own table; $sql_cond holds only fixed fragments with placeholders, $columns the fixed LIST_COLUMNS and $order ASC or DESC; every value is bound by prepare().
		if ( ! $request->get_param( 'no_count' ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i{$sql_cond}", array_merge( array( $table ), $where['values'] ) ) );

			// A page past the last one shows the last page.
			$page = min( $page, max( 1, (int) ceil( $total / $per_page ) ) );
		}

		$rows = array();

		if ( null === $total || $total > 0 ) {
			// The log ID breaks ties, so equal sort values never repeat or go missing between pages.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM %i{$sql_cond} ORDER BY %i {$order}, `log_id` {$order} LIMIT %d OFFSET %d",
					array_merge( array( $table ), $where['values'], array( $orderby, $per_page, ( $page - 1 ) * $per_page ) )
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$response = rest_ensure_response( $this->prepare_rows( is_array( $rows ) ? $rows : array(), $request ) );

		if ( null !== $total ) {
			$response = $this->add_pagination_headers( $response, $total, $per_page );
		}

		$response->header( 'X-ZeroSpam-Page', (string) $page );

		return $response;
	}

	/**
	 * Entries newer than `since_id`, newest first: one primary-key range read
	 * and one query for the blocked state.
	 *
	 * @param WP_REST_Request                                                                                                                        $request  Request.
	 * @param array{type: string[], country: string[], ip: string[], after: string, before: string, search: string, detections: bool, network: bool} $filters  Filters.
	 * @param int                                                                                                                                    $since_id Last ID the client has.
	 * @param int                                                                                                                                    $per_page Most entries.
	 * @return WP_REST_Response
	 */
	private function live_feed( WP_REST_Request $request, array $filters, int $since_id, int $per_page ): WP_REST_Response {
		global $wpdb;

		$where   = $this->where( $filters );
		$columns = $this->select_columns( $request );
		$cond    = '' !== $where['sql'] ? ' AND ' . $where['sql'] : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Primary-key range read of the plugin's own table; $cond holds only fixed fragments with placeholders and $columns the fixed LIST_COLUMNS.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$columns} FROM %i WHERE `log_id` > %d{$cond} ORDER BY `log_id` DESC LIMIT %d",
				array_merge( array( self::log_table(), $since_id ), $where['values'], array( $per_page ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return rest_ensure_response( $this->prepare_rows( is_array( $rows ) ? $rows : array(), $request ) );
	}

	/**
	 * One entry with its details.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$row = self::read_row( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return $this->not_found();
		}

		$blocked = self::blocked_ips( array( (string) $row['user_ip'] ) );
		$item    = $this->prepare_row( $row, $blocked, true );
		$details = json_decode( (string) $row['submission_data'], true );
		$record  = $blocked[ User::normalize_ip( (string) $row['user_ip'] ) ] ?? null;

		$item['details'] = is_array( $details ) ? $details : null;
		$item['block']   = null === $record ? null : Rules_Controller::prepare_block( $record['record'] );
		$item['allowed'] = Rules_Controller::is_allowed_ip( (string) $row['user_ip'] );
		$item['map_url'] = self::map_url( $row['latitude'] ?? null, $row['longitude'] ?? null );

		return rest_ensure_response( $this->filter_fields( $item, $request ) );
	}

	/**
	 * Deletes one entry; the response carries a token that restores it for two minutes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$row = self::read_row( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return $this->not_found();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes one row of the plugin's own table.
		$wpdb->delete( self::log_table(), array( 'log_id' => (int) $row['log_id'] ), array( '%d' ) );
		Stats_Controller::flush_cache();

		return rest_ensure_response(
			array(
				'deleted'    => 1,
				'undo_token' => Undo_Store::put( 'activity', array( $row ) ),
				'previous'   => $this->prepare_row( $row, array(), true ),
			)
		);
	}

	/**
	 * Deletes several entries (with an undo token), or every entry (no undo).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_delete( $request ) {
		global $wpdb;

		$table = self::log_table();

		if ( $request->get_param( 'all' ) ) {
			if ( true !== $request->get_param( 'confirm' ) ) {
				return $this->invalid_params_error( array( 'confirm' => __( 'Deleting every entry cannot be undone: send confirm: true.', 'zero-spam' ) ) );
			}

			// DELETE, not TRUNCATE: IDs keep growing, so the live feed's since_id stays valid.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Empties the plugin's own table.
			$deleted = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
			Stats_Controller::flush_cache();

			return rest_ensure_response(
				array(
					'deleted'    => $deleted,
					'undo_token' => null,
				)
			);
		}

		$ids = $request->get_param( 'ids' );
		$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();

		if ( ! $ids ) {
			return $this->invalid_params_error( array( 'ids' => __( 'Send the IDs of the entries to delete, or all: true with confirm: true.', 'zero-spam' ) ) );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders holds one %d per ID (at most MAX_BULK).
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM %i WHERE `log_id` IN ({$placeholders}) LIMIT %d", array_merge( array( $table ), $ids, array( self::MAX_BULK ) ) ),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();

		$deleted = 0;
		if ( $rows ) {
			$found        = array_map( 'intval', array_column( $rows, 'log_id' ) );
			$placeholders = implode( ', ', array_fill( 0, count( $found ), '%d' ) );
			$deleted      = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE `log_id` IN ({$placeholders})", array_merge( array( $table ), $found ) ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( $deleted > 0 ) {
			Stats_Controller::flush_cache();
		}

		return rest_ensure_response(
			array(
				'deleted'    => $deleted,
				'undo_token' => $deleted > 0 ? Undo_Store::put( 'activity', $rows ) : null,
			)
		);
	}

	/**
	 * Counts per type, country and IP address (the ten with most entries) for the filters.
	 *
	 * `total` counts the entries every filter matches, `max_id` is the newest
	 * of them (where a live feed for these filters starts), and
	 * `network_only` how many of them the Zero Spam network caught.
	 *
	 * Each facet ignores its own filter, so a client can show how many
	 * entries every choice would give.
	 *
	 * @param array{type: string[], country: string[], ip: string[], after: string, before: string, search: string, detections: bool, network: bool} $filters Filters.
	 * @return array<string, mixed>
	 */
	private function facets( array $filters ): array {
		global $wpdb;

		$table  = self::log_table();
		$labels = Stats_Controller::type_labels();

		$type_where    = $this->where( $filters, array( 'type' ) );
		$country_where = $this->where( $filters, array( 'country' ) );
		$ip_where      = $this->where( $filters, array( 'ip' ) );
		$network_where = $this->where( array_merge( $filters, array( 'network' => true ) ) );

		$type_cond    = '' !== $type_where['sql'] ? ' WHERE ' . $type_where['sql'] : '';
		$country_cond = ' WHERE `country` IS NOT NULL AND `country` <> \'\'' . ( '' !== $country_where['sql'] ? ' AND ' . $country_where['sql'] : '' );
		$ip_cond      = '' !== $ip_where['sql'] ? ' WHERE ' . $ip_where['sql'] : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Grouped counts of the plugin's own table, each bounded by LIMIT; the conditions hold only fixed fragments with placeholders.
		$types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `log_type` AS value, COUNT(*) AS count, MAX(`log_id`) AS max_id FROM %i{$type_cond} GROUP BY `log_type` ORDER BY count DESC, value ASC LIMIT %d",
				array_merge( array( $table ), $type_where['values'], array( self::FACET_LIMITS['type'] ) )
			),
			ARRAY_A
		);

		$countries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `country` AS value, MAX(`country_name`) AS label, COUNT(*) AS count FROM %i{$country_cond} GROUP BY `country` ORDER BY count DESC, value ASC LIMIT %d",
				array_merge( array( $table ), $country_where['values'], array( self::FACET_LIMITS['country'] ) )
			),
			ARRAY_A
		);

		$ips = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `user_ip` AS value, MAX(`country`) AS country, COUNT(*) AS count FROM %i{$ip_cond} GROUP BY `user_ip` ORDER BY count DESC, value ASC LIMIT %d",
				array_merge( array( $table ), $ip_where['values'], array( self::FACET_LIMITS['ip'] ) )
			),
			ARRAY_A
		);

		$network_only = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$network_where['sql']}", array_merge( array( $table ), $network_where['values'] ) )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$types     = is_array( $types ) ? $types : array();
		$countries = is_array( $countries ) ? $countries : array();
		$ips       = is_array( $ips ) ? $ips : array();

		$total  = 0;
		$max_id = 0;
		$type   = array();
		foreach ( $types as $row ) {
			$value = (string) $row['value'];
			$count = (int) $row['count'];

			if ( ! $filters['type'] || in_array( $value, $filters['type'], true ) ) {
				$total += $count;
				$max_id = max( $max_id, (int) $row['max_id'] );
			}

			$type[] = array(
				'value' => $value,
				'label' => $labels[ $value ] ?? $value,
				'count' => $count,
			);
		}

		$country = array();
		foreach ( $countries as $row ) {
			$country[] = array(
				'value' => (string) $row['value'],
				'label' => '' !== (string) $row['label'] ? (string) $row['label'] : (string) $row['value'],
				'count' => (int) $row['count'],
			);
		}

		$blocked = self::blocked_ips( array_column( $ips, 'value' ) );
		$ip      = array();
		foreach ( $ips as $row ) {
			$value     = (string) $row['value'];
			$canonical = User::normalize_ip( $value );
			$ip[]      = array(
				'value'   => $value,
				'country' => null !== $row['country'] && '' !== (string) $row['country'] ? (string) $row['country'] : null,
				'count'   => (int) $row['count'],
				'blocked' => ! empty( $blocked[ $canonical ]['active'] ),
			);
		}

		return array(
			'total'        => $total,
			'max_id'       => $max_id,
			'network_only' => $network_only,
			'type'         => $type,
			'country'      => $country,
			'ip'           => $ip,
		);
	}

	/**
	 * The filters of a request, checked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{type: string[], country: string[], ip: string[], after: string, before: string, search: string, detections: bool, network: bool}|WP_Error
	 */
	private function filters( WP_REST_Request $request ) {
		$errors  = array();
		$filters = array(
			'type'       => array_values( array_unique( array_map( 'strval', (array) ( $request->get_param( 'type' ) ?? array() ) ) ) ),
			'country'    => array_values( array_unique( array_map( 'strtoupper', array_map( 'strval', (array) ( $request->get_param( 'country' ) ?? array() ) ) ) ) ),
			'ip'         => array(),
			'after'      => '',
			'before'     => '',
			'search'     => '',
			'detections' => (bool) $request->get_param( 'detections_only' ),
			'network'    => (bool) $request->get_param( 'network_only' ),
		);

		$ip = $request->get_param( 'ip' );
		if ( is_string( $ip ) && '' !== trim( $ip ) ) {
			$canonical = User::normalize_ip( $ip );

			if ( '' === $canonical ) {
				$errors['ip'] = __( 'Must be an IPv4 or IPv6 address.', 'zero-spam' );
			} else {
				// As typed or in canonical form: entries are logged canonical now, older ones as the server reported them.
				$filters['ip'] = array_values( array_unique( array( trim( $ip ), $canonical ) ) );
			}
		}

		foreach ( array( 'after', 'before' ) as $bound ) {
			$value = $request->get_param( $bound );

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			$date = self::parse_date( trim( $value ), 'before' === $bound );

			if ( null === $date ) {
				$errors[ $bound ] = __( 'Must be a date (YYYY-MM-DD) or a date and time (ISO 8601).', 'zero-spam' );
				continue;
			}

			$filters[ $bound ] = $date;
		}

		$days = $request->get_param( 'days' );
		if ( null !== $days ) {
			// The first day of the period, as stats/timeseries counts it; the later of it and `after` applies.
			$now   = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log stores site-local times; the period is measured on the same clock.
			$start = gmdate( 'Y-m-d', $now - ( max( 1, (int) $days ) - 1 ) * DAY_IN_SECONDS ) . ' 00:00:00';

			$filters['after'] = max( $filters['after'], $start );
		}

		$search = $request->get_param( 'search' );
		if ( is_string( $search ) ) {
			$filters['search'] = trim( sanitize_text_field( $search ) );
		}

		return $errors ? $this->invalid_params_error( $errors ) : $filters;
	}

	/**
	 * A date filter as a site-local `Y-m-d H:i:s` (the clock the log uses).
	 *
	 * A date alone is the start of that day; as `before` it is the start of
	 * the next day, so the day itself is included. A time without an offset
	 * is site-local; one with an offset is converted.
	 *
	 * @param string $value     Date or time.
	 * @param bool   $is_before Whether it is the `before` bound.
	 * @return string|null Null when it is not a date.
	 */
	private static function parse_date( string $value, bool $is_before ): ?string {
		if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
				return null;
			}

			$day = $value . ' 00:00:00';

			return $is_before ? gmdate( 'Y-m-d H:i:s', (int) strtotime( $day . ' UTC' ) + DAY_IN_SECONDS ) : $day;
		}

		$timestamp = rest_parse_date( $value );

		if ( false === $timestamp ) {
			return null;
		}

		$utc = gmdate( 'Y-m-d H:i:s', $timestamp );

		// rest_parse_date() reads a time without an offset as UTC, which keeps its wall-clock value.
		return 1 === preg_match( '/(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $value ) ? get_date_from_gmt( $utc ) : $utc;
	}

	/**
	 * WHERE fragments and values for the filters.
	 *
	 * @param array{type: string[], country: string[], ip: string[], after: string, before: string, search: string, detections: bool, network: bool} $filters Filters.
	 * @param string[]                                                                                                                               $skip    Filters to leave out.
	 * @return array{sql: string, values: array<int, string>}
	 */
	private function where( array $filters, array $skip = array() ): array {
		global $wpdb;

		$clauses = array();
		$values  = array();

		foreach ( array(
			'type'    => 'log_type',
			'country' => 'country',
			'ip'      => 'user_ip',
		) as $filter => $column ) {
			if ( in_array( $filter, $skip, true ) || ! $filters[ $filter ] ) {
				continue;
			}

			$clauses[] = '`' . $column . '` IN (' . implode( ', ', array_fill( 0, count( $filters[ $filter ] ), '%s' ) ) . ')';
			$values    = array_merge( $values, $filters[ $filter ] );
		}

		if ( '' !== $filters['after'] ) {
			$clauses[] = '`date_recorded` >= %s';
			$values[]  = $filters['after'];
		}

		if ( '' !== $filters['before'] ) {
			$clauses[] = '`date_recorded` < %s';
			$values[]  = $filters['before'];
		}

		if ( $filters['detections'] ) {
			$clauses[] = DB::detections_sql();
		}

		if ( $filters['network'] ) {
			$clauses[] = Stats_Controller::network_only_sql();
		}

		if ( '' !== $filters['search'] ) {
			$contains  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$clauses[] = '(`user_ip` LIKE %s OR `page_url` LIKE %s OR `country_name` LIKE %s OR `region_name` LIKE %s OR `city` LIKE %s)';
			$values    = array_merge( $values, array( $wpdb->esc_like( $filters['search'] ) . '%', $contains, $contains, $contains, $contains ) );
		}

		return array(
			'sql'    => implode( ' AND ', $clauses ),
			'values' => $values,
		);
	}

	/**
	 * The column list to read: submission_data only when `failed` (or what
	 * is worked out from it) is wanted.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function select_columns( WP_REST_Request $request ): string {
		$columns = self::LIST_COLUMNS;
		$fields  = $this->requested_fields( $request );

		if ( ! $fields || rest_is_field_included( 'failed', $fields ) || rest_is_field_included( 'failed_label', $fields ) || rest_is_field_included( 'network', $fields ) ) {
			$columns[] = 'submission_data';
		}

		return '`' . implode( '`, `', $columns ) . '`';
	}

	/**
	 * Rows as the API returns them, with the blocked state of their IPs (one query).
	 *
	 * @param array<int, array<string, mixed>> $rows    Rows.
	 * @param WP_REST_Request                  $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_rows( array $rows, WP_REST_Request $request ): array {
		$fields  = $this->requested_fields( $request );
		$blocked = ! $fields || rest_is_field_included( 'blocked', $fields )
			? self::blocked_ips( array_map( 'strval', array_column( $rows, 'user_ip' ) ) )
			: array();

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->filter_fields( $this->prepare_row( $row, $blocked, false ), $request );
		}

		return $items;
	}

	/**
	 * One row as the API returns it.
	 *
	 * @param array<string, mixed>                                             $row     Row.
	 * @param array<string, array{active: bool, record: array<string, mixed>}> $blocked Blocked state by canonical IP.
	 * @param bool                                                             $full    Whether the row is shown on its own (detail).
	 * @return array<string, mixed>
	 */
	private function prepare_row( array $row, array $blocked, bool $full ): array {
		$ip     = (string) $row['user_ip'];
		$date   = (string) $row['date_recorded'];
		$failed = null;

		if ( isset( $row['submission_data'] ) && is_string( $row['submission_data'] ) && '' !== $row['submission_data'] ) {
			$data   = json_decode( $row['submission_data'], true );
			$failed = is_array( $data ) && isset( $data['failed'] ) && is_scalar( $data['failed'] ) && '' !== (string) $data['failed'] ? (string) $data['failed'] : null;
		}

		$labels = Stats_Controller::type_labels();
		$type   = (string) $row['log_type'];

		$item = array(
			'id'           => (int) $row['log_id'],
			'date'         => mysql_to_rfc3339( $date ),
			// With the UTC offset, so clients never read it as local time (mysql_to_rfc3339() writes none).
			'date_gmt'     => mysql_to_rfc3339( get_gmt_from_date( $date ) ) . '+00:00',
			'type'         => $type,
			'type_label'   => $labels[ $type ] ?? $type,
			'ip'           => $ip,
			'blocked'      => ! empty( $blocked[ User::normalize_ip( $ip ) ]['active'] ),
			'failed'       => $failed,
			'failed_label' => null === $failed ? null : self::failed_label( $failed ),
			'network'      => Stats_Controller::is_network_detection( $type, $failed ),
			'page_url'     => self::nullable( $row['page_url'] ?? null ),
			'country'      => self::nullable( $row['country'] ?? null ),
			'country_name' => self::nullable( $row['country_name'] ?? null ),
			'region'       => self::nullable( $row['region'] ?? null ),
			'region_name'  => self::nullable( $row['region_name'] ?? null ),
			'city'         => self::nullable( $row['city'] ?? null ),
			'zip'          => self::nullable( $row['zip'] ?? null ),
			'latitude'     => self::nullable( $row['latitude'] ?? null ),
			'longitude'    => self::nullable( $row['longitude'] ?? null ),
		);

		if ( ! $full ) {
			unset( $item['zip'], $item['latitude'], $item['longitude'] );
		}

		return $item;
	}

	/**
	 * The blocked state of IP addresses, from one query.
	 *
	 * The record that applies is chosen as DB::blocked() does (a permanent
	 * block first, then the latest end), and whether it blocks right now as
	 * Access::get_blocked_details() decides.
	 *
	 * @param string[] $ips IP addresses as logged.
	 * @return array<string, array{active: bool, record: array<string, mixed>}> By canonical IP; IPs without a block record are missing.
	 */
	public static function blocked_ips( array $ips ): array {
		global $wpdb;

		$lookup = array();
		foreach ( $ips as $ip ) {
			$ip        = trim( (string) $ip );
			$canonical = User::normalize_ip( $ip );

			if ( '' === $canonical ) {
				continue;
			}

			$lookup[ $ip ]        = true;
			$lookup[ $canonical ] = true;
		}

		if ( ! $lookup ) {
			return array();
		}

		$values       = array_keys( $lookup );
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One query for a page of IPs; $placeholders holds one %s per IP.
		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE `user_ip` IN ({$placeholders}) ORDER BY `blocked_type` = 'permanent' DESC, `end_block` DESC LIMIT %d",
				array_merge( array( $wpdb->prefix . DB::$tables['blocked'] ), $values, array( count( $values ) * 4 ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$state = array();
		foreach ( is_array( $records ) ? $records : array() as $record ) {
			$canonical = User::normalize_ip( (string) $record['user_ip'] );

			// Records arrive best first.
			if ( '' === $canonical || isset( $state[ $canonical ] ) ) {
				continue;
			}

			$state[ $canonical ] = array(
				'active' => ! empty( Access::get_blocked_details( $record )['blocked'] ),
				'record' => $record,
			);
		}

		return $state;
	}

	/**
	 * A link that opens a location in Google Maps (no map is embedded).
	 *
	 * @param mixed $latitude  Latitude.
	 * @param mixed $longitude Longitude.
	 */
	public static function map_url( $latitude, $longitude ): ?string {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return null;
		}

		$latitude  = (float) $latitude;
		$longitude = (float) $longitude;

		if ( abs( $latitude ) > 90 || abs( $longitude ) > 180 ) {
			return null;
		}

		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $latitude . ',' . $longitude );
	}

	/**
	 * Label of a failed check (`zerospam_failed_types`), or the key made readable.
	 *
	 * @param string $failed Failed check key.
	 */
	public static function failed_label( string $failed ): string {
		$labels = self::request_cache(
			'failed_labels',
			static function (): array {
				$types = apply_filters( 'zerospam_failed_types', array() );

				return is_array( $types ) ? $types : array();
			}
		);

		if ( isset( $labels[ $failed ] ) && is_string( $labels[ $failed ] ) ) {
			return wp_strip_all_tags( $labels[ $failed ] );
		}

		return ucfirst( str_replace( array( '_', '-' ), ' ', $failed ) );
	}

	/**
	 * One log row, or null.
	 *
	 * @param int $id Log ID.
	 * @return array<string, mixed>|null
	 */
	private static function read_row( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row of the plugin's own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE `log_id` = %d', self::log_table(), $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * The 404 for an entry that does not exist.
	 */
	private function not_found(): WP_Error {
		return new WP_Error( 'zerospam_activity_not_found', __( 'No activity entry has that ID.', 'zero-spam' ), array( 'status' => 404 ) );
	}

	/**
	 * A string, or null for null and the empty string.
	 *
	 * @param mixed $value Value.
	 */
	private static function nullable( $value ): ?string {
		return null === $value || '' === (string) $value ? null : (string) $value;
	}

	/**
	 * Full name of the current site's log table.
	 */
	private static function log_table(): string {
		global $wpdb;

		return $wpdb->prefix . DB::$tables['log'];
	}
}
