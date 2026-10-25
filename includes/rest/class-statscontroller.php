<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Stats_Controller to this file name.
/**
 * REST routes for detection statistics.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Core\Settings_Schema;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/stats/summary` and `zero-spam/v1/stats/timeseries`: totals,
 * detections by type, country and day, and how full the activity log is.
 *
 * Only detections count: log types in DB::NON_DETECTION_TYPES (rescue links)
 * are left out. Dates are in the site's time zone, as the log stores them.
 * Answers are cached in a transient tagged with the highest log ID, so a new
 * detection refreshes them at once; deletions through the REST API clear
 * them (flush_cache()).
 *
 * @since 6.0.0
 */
class Stats_Controller extends Base_Controller {

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
	protected $rest_base = 'stats';

	/**
	 * Periods the summary accepts, in days.
	 *
	 * @var int[]
	 */
	const SUMMARY_RANGES = array( 1, 7, 30 );

	/**
	 * Longest time series, in days.
	 *
	 * @var int
	 */
	const MAX_SERIES_DAYS = 30;

	/**
	 * Transients holding the cached answers, by range.
	 *
	 * @var string
	 */
	const SUMMARY_TRANSIENT    = 'zerospam_stats_summary';
	const TIMESERIES_TRANSIENT = 'zerospam_stats_timeseries';

	/**
	 * Cache lifetimes, in seconds.
	 *
	 * @var int
	 */
	const SUMMARY_TTL    = MINUTE_IN_SECONDS;
	const TIMESERIES_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Number of countries in the summary.
	 *
	 * @var int
	 */
	const TOP_COUNTRIES = 5;

	/**
	 * Default `max_logs` (see Settings::get_settings()).
	 *
	 * @var int
	 */
	const DEFAULT_MAX_LOGS = 10000;

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'range' => array(
							'description' => __( 'Period in days: 1, 7 or 30.', 'zero-spam' ),
							'type'        => 'integer',
							'enum'        => self::SUMMARY_RANGES,
							'default'     => 7,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/timeseries',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_timeseries' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'range'  => array(
							'description' => __( 'Number of days, today included (1 to 30).', 'zero-spam' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_SERIES_DAYS,
							'default'     => self::MAX_SERIES_DAYS,
						),
						'bucket' => array(
							'description' => __( 'Bucket size: day.', 'zero-spam' ),
							'type'        => 'string',
							'enum'        => array( 'day' ),
							'default'     => 'day',
						),
					),
				),
			)
		);
	}

	/**
	 * Totals for a period and the one before it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_summary( $request ) {
		$range = (int) $request->get_param( 'range' );
		$data  = $this->cached(
			self::SUMMARY_TRANSIENT,
			$range,
			self::SUMMARY_TTL,
			function () use ( $range ): array {
				return $this->summary( $range );
			}
		);

		// The maximum is a setting: read on every request, so a change shows at once.
		$max         = self::max_logs();
		$count       = (int) ( $data['log']['count'] ?? 0 );
		$data['log'] = array(
			'count'   => $count,
			'max'     => $max,
			'percent' => min( 100, round( $count / $max * 100, 1 ) ),
		);

		return rest_ensure_response( $this->filter_fields( $data, $request ) );
	}

	/**
	 * Detections per day and type, and per hour of the day.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_timeseries( $request ) {
		$range = (int) $request->get_param( 'range' );
		$data  = $this->cached(
			self::TIMESERIES_TRANSIENT,
			$range,
			self::TIMESERIES_TTL,
			function () use ( $range ): array {
				return $this->timeseries( $range );
			}
		);

		return rest_ensure_response( $this->filter_fields( $data, $request ) );
	}

	/**
	 * Forgets every cached answer of the current site (after rows are deleted or restored).
	 */
	public static function flush_cache(): void {
		delete_transient( self::SUMMARY_TRANSIENT );
		delete_transient( self::TIMESERIES_TRANSIENT );
	}

	/**
	 * A value from a transient tagged with the highest log ID, computed when
	 * missing or out of date. One transient holds every range.
	 *
	 * @param string   $transient Transient name.
	 * @param int      $range     Range the value is for.
	 * @param int      $ttl       Lifetime in seconds.
	 * @param callable $producer  Computes the value.
	 * @return array<string, mixed>
	 */
	private function cached( string $transient, int $range, int $ttl, callable $producer ): array {
		$tag    = self::max_log_id();
		$cached = get_transient( $transient );
		$cached = is_array( $cached ) ? $cached : array();
		$entry  = $cached[ $range ] ?? null;

		if ( is_array( $entry ) && isset( $entry['tag'], $entry['data'], $entry['expires'] ) && $entry['tag'] === $tag && $entry['expires'] > time() && is_array( $entry['data'] ) ) {
			return $entry['data'];
		}

		$data = $producer();

		$cached[ $range ] = array(
			'tag'     => $tag,
			'expires' => time() + $ttl,
			'data'    => $data,
		);

		set_transient( $transient, $cached, $ttl );

		return $data;
	}

	/**
	 * The highest log ID (0 for an empty log): one primary-key lookup.
	 */
	public static function max_log_id(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache tag; reads the primary key only.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(log_id) FROM %i', self::log_table() ) );
	}

	/**
	 * Builds the summary.
	 *
	 * @param int $range Days.
	 * @return array<string, mixed>
	 */
	private function summary( int $range ): array {
		global $wpdb;

		$table      = self::log_table();
		$now        = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log stores site-local times; periods are measured on the same clock.
		$start      = gmdate( 'Y-m-d H:i:s', $now - $range * DAY_IN_SECONDS );
		$prev_start = gmdate( 'Y-m-d H:i:s', $now - 2 * $range * DAY_IN_SECONDS );
		$detections = DB::detections_sql();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Statistics of the plugin's own table, cached above; $detections is prepared by DB::detections_sql().

		// Both periods by day and type: the current and previous totals, the
		// types, the busiest day and the daily average, from one grouped read.
		$days = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(date_recorded) AS day, log_type, SUM(date_recorded >= %s) AS current, COUNT(*) AS total FROM %i WHERE date_recorded >= %s AND {$detections} GROUP BY day, log_type LIMIT 10000",
				$start,
				$table,
				$prev_start
			),
			ARRAY_A
		);

		$countries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT country, MAX(country_name) AS country_name, COUNT(*) AS count FROM %i WHERE date_recorded >= %s AND country IS NOT NULL AND country <> '' AND {$detections} GROUP BY country ORDER BY count DESC, country ASC LIMIT %d",
				$table,
				$start,
				self::TOP_COUNTRIES
			),
			ARRAY_A
		);

		// Every row counts towards the log size, as DB::log() prunes it.
		$log_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total    = 0;
		$previous = 0;
		$per_day  = array();
		$per_type = array();

		foreach ( is_array( $days ) ? $days : array() as $row ) {
			$current   = (int) $row['current'];
			$total    += $current;
			$previous += (int) $row['total'] - $current;

			if ( $current > 0 ) {
				$per_day[ (string) $row['day'] ]       = ( $per_day[ (string) $row['day'] ] ?? 0 ) + $current;
				$per_type[ (string) $row['log_type'] ] = ( $per_type[ (string) $row['log_type'] ] ?? 0 ) + $current;
			}
		}

		$busiest = null;
		ksort( $per_day );
		foreach ( $per_day as $day => $count ) {
			// The latest of equally busy days.
			if ( null === $busiest || $count >= $busiest['count'] ) {
				$busiest = array(
					'date'  => (string) $day,
					'count' => $count,
				);
			}
		}

		uksort(
			$per_type,
			static function ( $a, $b ) use ( $per_type ): int {
				$by_count = $per_type[ $b ] <=> $per_type[ $a ];

				return 0 !== $by_count ? $by_count : strcmp( (string) $a, (string) $b );
			}
		);

		$labels  = self::type_labels();
		$by_type = array();
		foreach ( $per_type as $type => $count ) {
			$by_type[] = array(
				'type'  => (string) $type,
				'label' => $labels[ $type ] ?? (string) $type,
				'count' => $count,
			);
		}

		$top_countries = array();
		foreach ( is_array( $countries ) ? $countries : array() as $row ) {
			$top_countries[] = array(
				'code'  => (string) $row['country'],
				'name'  => '' !== (string) $row['country_name'] ? (string) $row['country_name'] : (string) $row['country'],
				'count' => (int) $row['count'],
			);
		}

		return array(
			'range'          => $range,
			'start'          => mysql_to_rfc3339( $start ),
			'end'            => mysql_to_rfc3339( gmdate( 'Y-m-d H:i:s', $now ) ),
			'total'          => $total,
			'previous_total' => $previous,
			'delta'          => $total - $previous,
			'delta_percent'  => $previous > 0 ? round( ( $total - $previous ) / $previous * 100, 1 ) : null,
			'daily_average'  => round( $total / $range, 1 ),
			'by_type'        => $by_type,
			'top_country'    => $top_countries[0] ?? null,
			'top_countries'  => $top_countries,
			'busiest_day'    => $busiest,
			'log'            => array( 'count' => $log_count ),
		);
	}

	/**
	 * Builds the time series: every day of the period (empty days included),
	 * split by type, and the detections per hour of the day.
	 *
	 * @param int $range Days, today included.
	 * @return array<string, mixed>
	 */
	private function timeseries( int $range ): array {
		global $wpdb;

		$table      = self::log_table();
		$now        = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log stores site-local times.
		$first_day  = gmdate( 'Y-m-d', $now - ( $range - 1 ) * DAY_IN_SECONDS );
		$detections = DB::detections_sql();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Statistics of the plugin's own table, cached above; $detections is prepared by DB::detections_sql().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(date_recorded) AS day, log_type, COUNT(*) AS count FROM %i WHERE date_recorded >= %s AND {$detections} GROUP BY day, log_type LIMIT 5000",
				$table,
				$first_day . ' 00:00:00'
			),
			ARRAY_A
		);

		$hour_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(date_recorded) AS hour, COUNT(*) AS count FROM %i WHERE date_recorded >= %s AND {$detections} GROUP BY hour LIMIT 24",
				$table,
				$first_day . ' 00:00:00'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$days = array();
		for ( $i = $range - 1; $i >= 0; $i-- ) {
			$date          = gmdate( 'Y-m-d', $now - $i * DAY_IN_SECONDS );
			$days[ $date ] = array(
				'date'    => $date,
				'total'   => 0,
				'by_type' => array(),
			);
		}

		$seen = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$date = (string) $row['day'];
			if ( ! isset( $days[ $date ] ) ) {
				continue;
			}

			$type  = (string) $row['log_type'];
			$count = (int) $row['count'];

			$days[ $date ]['total']           += $count;
			$days[ $date ]['by_type'][ $type ] = $count;
			$seen[ $type ]                     = ( $seen[ $type ] ?? 0 ) + $count;
		}

		arsort( $seen );
		$labels = self::type_labels();
		$types  = array();
		foreach ( $seen as $type => $count ) {
			$types[] = array(
				'type'  => (string) $type,
				'label' => $labels[ $type ] ?? (string) $type,
				'count' => $count,
			);
		}

		$hours = array_fill( 0, 24, 0 );
		foreach ( is_array( $hour_rows ) ? $hour_rows : array() as $row ) {
			$hour = (int) $row['hour'];
			if ( $hour >= 0 && $hour < 24 ) {
				$hours[ $hour ] = (int) $row['count'];
			}
		}

		foreach ( $days as $date => $day ) {
			// An empty object, not an empty list, in JSON.
			$days[ $date ]['by_type'] = (object) $day['by_type'];
		}

		return array(
			'range'  => $range,
			'bucket' => 'day',
			'types'  => $types,
			'days'   => array_values( $days ),
			'hours'  => $hours,
		);
	}

	/**
	 * Labels of the detection types (`zerospam_types`), by type.
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return self::request_cache(
			'type_labels',
			static function (): array {
				$labels = array();
				$types  = apply_filters( 'zerospam_types', array() );

				foreach ( is_array( $types ) ? $types : array() as $type => $config ) {
					if ( is_array( $config ) && isset( $config['label'] ) && is_string( $config['label'] ) ) {
						$labels[ (string) $type ] = wp_strip_all_tags( $config['label'] );
					}
				}

				return $labels;
			}
		);
	}

	/**
	 * The most log rows kept (the `max_logs` setting), read without building
	 * the settings registry: locked network value, site value, network
	 * default, then the plugin default, as Network_Settings resolves it.
	 */
	public static function max_logs(): int {
		$site  = get_option( 'zero-spam-settings', array() );
		$value = is_array( $site ) && isset( $site['max_logs'] ) ? $site['max_logs'] : null;

		if ( is_multisite() ) {
			$store = Settings_Schema::normalize_network_store( get_site_option( Network_Settings::META_KEY, array() ) );
			$entry = $store['settings']['max_logs'] ?? null;

			if ( is_array( $entry ) && ( ! empty( $entry['locked'] ) || ( null === $value && isset( $entry['value'] ) ) ) ) {
				$value = $entry['value'] ?? null;
			}
		}

		$max = is_numeric( $value ) ? absint( $value ) : 0;

		return $max > 0 ? $max : self::DEFAULT_MAX_LOGS;
	}

	/**
	 * Full name of the current site's log table.
	 */
	private static function log_table(): string {
		global $wpdb;

		return $wpdb->prefix . DB::$tables['log'];
	}
}
