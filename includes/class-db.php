<?php
/**
 * DB class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WordPress Zero Spam DB class.
 *
 * @since 5.0.0
 */
class DB {

	/**
	 * Current DB version.
	 */
	const DB_VERSION = '0.8';

	/**
	 * DB tables.
	 */
	public static $tables = array(
		'log'       => 'wpzerospam_log',
		'blocked'   => 'wpzerospam_blocked',
		'blacklist' => 'wpzerospam_blacklist',
	);

	/**
	 * DB constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'update' ) );
	}

	/**
	 * Installs & updates the DB tables
	 */
	public function update() {
		if ( self::DB_VERSION !== get_site_option( 'zerospam_db_version' ) ) {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sql = 'CREATE TABLE ' . $wpdb->prefix . self::$tables['log'] . " (
				log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				log_type VARCHAR(255) NOT NULL,
				user_ip VARCHAR(39) NOT NULL,
				date_recorded DATETIME NOT NULL,
				page_url VARCHAR(255) NULL DEFAULT NULL,
				submission_data LONGTEXT NULL DEFAULT NULL,
				country VARCHAR(2) NULL DEFAULT NULL,
				country_name VARCHAR(255) NULL DEFAULT NULL,
				region VARCHAR(255) NULL DEFAULT NULL,
				region_name VARCHAR(255) NULL DEFAULT NULL,
				city VARCHAR(255) NULL DEFAULT NULL,
				zip VARCHAR(10) NULL DEFAULT NULL,
				latitude VARCHAR(255) NULL DEFAULT NULL,
				longitude VARCHAR(255) NULL DEFAULT NULL,
				PRIMARY KEY (log_id)) $charset_collate;";

			$sql .= 'CREATE TABLE ' . $wpdb->prefix . self::$tables['blocked'] . " (
				blocked_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				blocked_type ENUM('permanent','temporary') NOT NULL DEFAULT 'temporary',
				user_ip VARCHAR(39) NOT NULL,
				blocked_key VARCHAR(255) NULL,
				key_type ENUM('ip','email','username','country_code','region_code','zip') NOT NULL DEFAULT 'ip',
				date_added DATETIME NOT NULL,
				start_block DATETIME NULL DEFAULT NULL,
				end_block DATETIME NULL DEFAULT NULL,
				reason VARCHAR(255) NULL DEFAULT NULL,
				PRIMARY KEY (blocked_id)) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( 'zerospam_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Returns all blocked IP addresses.
	 */
	public static function get_blocked() {
		global $wpdb;

		return $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::$tables['blocked'], ARRAY_A );
	}

	/**
	 * Adds/returns a blocked IP.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function blocked( $record, $key_type = false ) {
		global $wpdb;

		if ( is_array( $record ) ) {
			$blocked = false;

			// Add or update a record.
			if ( ! empty( $record['blocked_id'] ) ) {
				// Update a record.
				$blocked['blocked_id'] = $record['blocked_id'];
			} elseif ( ! empty( $record['user_ip'] ) ) {
				// Add a record, but first check if IP is unique.
				$blocked = self::blocked( $record['user_ip'] );
			} elseif ( ! empty( $record['key_type'] ) && ! empty( $record['blocked_key'] ) ) {
				// Add a record, but first check if key is unique.
				$blocked = self::blocked( $record['blocked_key'], $record['key_type'] );
			}

			if ( $blocked ) {
				// Update the record.
				$record['date_added'] = current_time( 'mysql' );
				return $wpdb->update(
					$wpdb->prefix . self::$tables['blocked'],
					$record,
					array(
						'blocked_id' => $blocked['blocked_id'],
					)
				);
			} else {
				// Insert the record.
				$record['date_added'] = current_time( 'mysql' );
				return $wpdb->insert( $wpdb->prefix . self::$tables['blocked'], $record );
			}
		} elseif ( $key_type ) {
			// Get record by key.
			return $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . self::$tables['blocked'] . ' WHERE key_type = "' . $key_type . '" AND blocked_key = "' . $record . '"', ARRAY_A );
		} elseif ( is_int( $record ) ) {
			// Get record by ID.
			return $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . self::$tables['blocked'] . ' WHERE blocked_id = "' . $record . '"', ARRAY_A );
		} elseif ( rest_is_ip_address( $record ) ) {
			// Get record by IP.
			return $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . self::$tables['blocked'] . ' WHERE user_ip = "' . $record . '"', ARRAY_A );
		}

		return false;
	}

	/**
	 * Log.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function log( $type, $details ) {
		global $wpdb;

		$page_url  = ZeroSpam\Core\Utilities::current_url();
		$extension = substr( $page_url, strrpos( $page_url, '.' ) + 1 );
		$ignore    = array( 'map', 'js', 'css', 'ico' );
		if ( in_array( $extension, $ignore, true ) ) {
			// Ignore assets.
			return false;
		}

		/**
		 * Check the total number of entries and delete the oldest if the maximum
		 * has been reached.
		 */
		$total = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::$tables['log'] );
		$max   = ZeroSpam\Core\Settings::get_settings( 'max_logs' );
		if ( $total > $max ) {
			$difference = $total - $max;
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . self::$tables['log'] . ' ORDER BY date_recorded ASC LIMIT ' . $difference );
		}

		$record = array(
			'user_ip'         => ZeroSpam\Core\User::get_ip(),
			'log_type'        => $type,
			'date_recorded'   => current_time( 'mysql' ),
			'page_url'        => $page_url,
			'submission_data' => wp_json_encode( $details ),
		);

		$record = apply_filters( 'zerospam_log_record', $record );

		return $wpdb->insert( $wpdb->prefix . self::$tables['log'], $record );
	}

	/**
	 * Delete a record.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function delete( $table, $key, $value ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . self::$tables[ $table ],
			array(
				$key => $value,
			)
		);
	}

	/**
	 * Delete everything in a table.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function delete_all( $table ) {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE " . $wpdb->prefix . self::$tables[ $table ] );
	}

	/**
	 * Query the DB.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function query( $table, $args = array() ) {
		global $wpdb;

		if ( ! array_key_exists( $table, self::$tables ) ) {
			return false;
		}

		$sql = 'SELECT';

		if ( ! empty( $args['select'] ) ) {
			$sql .= implode( ', ', $args['select'] );
		} else {
			$sql .= ' * ';
		}

		$sql .= 'FROM ' . $wpdb->prefix . self::$tables[ $table ];

		if ( ! empty( $args['where'] ) ) {
			$sql .= ' WHERE ';

			$where_stmt = '';
			foreach ( $args['where'] as $key => $where ) {
				if ( $where_stmt ) {
					$where_stmt .= ' AND ';
				}

				$where_stmt .= $key;

				if ( ! empty( $where['relation'] ) ) {
					$where_stmt .= ' ' . $where['relation'] . ' ';
				} else {
					$where_stmt .= ' = ';
				}

				if ( is_numeric( $where['value'] ) ) {
					$where_stmt .= $where['value'];
				} elseif( is_array( $where['value'] ) ) {
					$where_stmt .= "('" . implode( "','", $where['value'] ) . "')";
				} else {
					$where_stmt .= '"' . $where['value'] . '"';
				}
			}

			$sql .= $where_stmt;
		}

		if ( ! empty( $args['orderby'] ) ) {
			$sql .= ' ORDER BY ' . $args['orderby'];
		}

		if ( ! empty( $args['order'] ) ) {
			$sql .= ' ' . $args['order'];
		}

		if ( ! empty( $args['limit'] ) ) {
			$sql .= ' LIMIT ' . $args['limit'];
		}

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . $args['offset'];
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}
}
