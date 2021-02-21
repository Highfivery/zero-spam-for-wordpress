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
	const DB_VERSION = '0.2';

	/**
	 * DB tables.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @var Assets_Manager
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
	 * Installs & updates the DB tables.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function update() {
		if ( self::DB_VERSION !== get_site_option( 'zerospam_db_version' ) ) {
			global $wpdb;

			$charset_collate      = $wpdb->get_charset_collate();
			$installed_db_version = get_option( 'zerospam_db_version' );

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
				PRIMARY KEY (`log_id`)) $charset_collate;";

			$sql .= 'CREATE TABLE ' . $wpdb->prefix . self::$tables['blocked'] . " (
				blocked_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				blocked_type ENUM('permanent','temporary') NOT NULL DEFAULT 'temporary',
				user_ip VARCHAR(39) NOT NULL,
				blocked_key VARCHAR(255) NULL,
				key_type ENUM('ip','email') NOT NULL DEFAULT 'ip',
				date_added DATETIME NOT NULL,
				start_block DATETIME NULL DEFAULT NULL,
				end_block DATETIME NULL DEFAULT NULL,
				reason VARCHAR(255) NULL DEFAULT NULL,
				attempts BIGINT UNSIGNED NOT NULL,
				PRIMARY KEY (`blocked_id`)) $charset_collate;";

			$sql .= 'CREATE TABLE ' . $wpdb->prefix . self::$tables['blacklist'] . " (
				blacklist_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_ip VARCHAR(39) NOT NULL,
				blocked_key VARCHAR(255) NULL,
				key_type ENUM('ip','email') NOT NULL DEFAULT 'ip',
				last_updated DATETIME NOT NULL,
				blacklist_service VARCHAR(255) NULL DEFAULT NULL,
				attempts BIGINT UNSIGNED NOT NULL,
				blacklist_data LONGTEXT NULL DEFAULT NULL,
				PRIMARY KEY (`blacklist_id`)) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( 'zerospam_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Log.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function log( $type, $details ) {
		global $wpdb;

		$record = array(
			'user_ip'         => ZeroSpam\Core\User::get_ip(),
			'log_type'        => $type,
			'date_recorded'   => current_time( 'mysql' ),
			'page_url'        => ZeroSpam\Core\Utilities::current_url(),
			'submission_data' => wp_json_encode( $details ),
		);

		$record = apply_filters( 'zerospam_log_record', $record );

		return $wpdb->insert( $wpdb->prefix . self::$tables['log'], $record );
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
				} else {
					$where_stmt .= '"' . $where['value'] . '"';
				}
			}

			$sql .= $where_stmt;
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
