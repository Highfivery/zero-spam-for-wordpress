<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Rules_Controller to this file name.
/**
 * REST routes for blocks, allow and deny lists, and the never-locked-out addresses.
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
use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\User;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Undo_Store;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/rules/…`:
 *
 * - `rules/blocks` and `rules/blocks/{id}`: blocked IP addresses and
 *   locations (the wpzerospam_blocked table). Deleting one returns an undo
 *   token. In an .htaccess block mode the deny rules are rewritten after
 *   every change, also when the last blocked address is removed.
 * - `rules/lists/{allowed-ips|email-domains|allowed-words}`: the IP
 *   Allowlist (setting `ip_whitelist`), the blocked email domains and the
 *   allowed words (with `disallowed_min_length`), stored like PATCH
 *   /settings stores them and refused when the network administrator has
 *   locked them.
 * - `rules/no-lockout` and `rules/no-lockout/{ip}`: the administrator
 *   addresses Zero Spam never locks out (Admin_Safe_IPs).
 *
 * Block dates are UTC (they end in `Z`): the block check compares them with
 * the current UTC time.
 *
 * @since 6.0.0
 */
class Rules_Controller extends Base_Controller {

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
	protected $rest_base = 'rules';

	/**
	 * Location key types a block can use (see Access::check_blocked()).
	 *
	 * @var string[]
	 */
	const LOCATION_KEYS = array( 'country_code', 'region_code', 'city', 'zip' );

	/**
	 * Sort columns of the block list.
	 *
	 * @var array<string, string>
	 */
	const BLOCK_ORDERBY = array(
		'date_added'   => 'date_added',
		'start'        => 'start_block',
		'end'          => 'end_block',
		'type'         => 'blocked_type',
		'key'          => 'key',
		'key_type'     => 'key_type',
		'blocked_type' => 'blocked_type',
	);

	/**
	 * The lists: route name => setting key, and the extra setting of a list.
	 *
	 * @var array<string, array{key: string, extra?: string}>
	 */
	const LISTS = array(
		'allowed-ips'   => array( 'key' => 'ip_whitelist' ),
		'email-domains' => array( 'key' => 'blocked_email_domains' ),
		'allowed-words' => array(
			'key'   => 'allowed_words',
			'extra' => 'disallowed_min_length',
		),
	);

	/**
	 * Most entries a list may hold.
	 *
	 * @var int
	 */
	const MAX_LIST_ITEMS = 100000;

	/**
	 * Longest allowed `disallowed_min_length`.
	 *
	 * @var int
	 */
	const MAX_MIN_LENGTH = 100;

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/blocks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_blocks' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'kind'     => array(
							'description' => __( 'ip (default) or location.', 'zero-spam' ),
							'type'        => 'string',
							'enum'        => array( 'ip', 'location' ),
							'default'     => 'ip',
						),
						'key_type' => array(
							'description' => __( 'Only location blocks of this kind.', 'zero-spam' ),
							'type'        => 'string',
							'enum'        => self::LOCATION_KEYS,
						),
						'search'   => array(
							'description' => __( 'IP address (or its start), or text in the location, country name or reason.', 'zero-spam' ),
							'type'        => 'string',
							'maxLength'   => 100,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => 20,
						),
						'orderby'  => array(
							'type'    => 'string',
							'enum'    => array_keys( self::BLOCK_ORDERBY ),
							'default' => 'date_added',
						),
						'order'    => array(
							'type'    => 'string',
							'enum'    => array( 'asc', 'desc' ),
							'default' => 'desc',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_block' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/blocks/(?P<id>\d+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Block ID.', 'zero-spam' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_block' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_block' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_block' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lists/(?P<list>allowed-ips|email-domains|allowed-words)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_list' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => self::MAX_PER_PAGE,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_list' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/no-lockout',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_no_lockout' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/no-lockout/(?P<ip>[0-9A-Fa-f:.]{2,45})',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_no_lockout' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Blocked IP addresses or locations, filtered, sorted and paged.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_blocks( $request ) {
		global $wpdb;

		$kind     = 'location' === $request->get_param( 'kind' ) ? 'location' : 'ip';
		$per_page = $this->clamp_per_page( $request->get_param( 'per_page' ) );
		$order    = 'asc' === $request->get_param( 'order' ) ? 'ASC' : 'DESC';
		$orderby  = self::BLOCK_ORDERBY[ (string) $request->get_param( 'orderby' ) ] ?? 'date_added';
		$orderby  = 'key' === $orderby ? ( 'ip' === $kind ? 'user_ip' : 'blocked_key' ) : $orderby;
		$clauses  = array();
		$values   = array();

		if ( 'ip' === $kind ) {
			$clauses[] = "`key_type` = 'ip'";
		} else {
			$key_type  = in_array( $request->get_param( 'key_type' ), self::LOCATION_KEYS, true ) ? array( (string) $request->get_param( 'key_type' ) ) : self::LOCATION_KEYS;
			$clauses[] = '`key_type` IN (' . implode( ', ', array_fill( 0, count( $key_type ), '%s' ) ) . ')';
			$values    = array_merge( $values, $key_type );
		}

		$search = $request->get_param( 'search' );
		$search = is_string( $search ) ? trim( sanitize_text_field( $search ) ) : '';

		if ( '' !== $search ) {
			if ( 'ip' === $kind ) {
				$canonical = User::normalize_ip( $search );
				$clauses[] = '(`user_ip` LIKE %s OR `user_ip` = %s OR `reason` LIKE %s)';
				$values    = array_merge( $values, array( $wpdb->esc_like( $search ) . '%', '' !== $canonical ? $canonical : $search, '%' . $wpdb->esc_like( $search ) . '%' ) );
			} else {
				// The location value as stored, its reason, or the name of a blocked country.
				$codes     = self::country_codes_matching( $search );
				$condition = '`blocked_key` LIKE %s OR `reason` LIKE %s';
				$values    = array_merge( $values, array( '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' ) );

				if ( $codes ) {
					$condition .= " OR ( `key_type` = 'country_code' AND `blocked_key` IN (" . implode( ', ', array_fill( 0, count( $codes ), '%s' ) ) . ') )';
					$values     = array_merge( $values, $codes );
				}

				$clauses[] = '(' . $condition . ')';
			}
		}

		$table = self::blocked_table();
		$where = ' WHERE ' . implode( ' AND ', $clauses );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Paged list of the plugin's own table; $where holds only fixed fragments with placeholders and $order is ASC or DESC.
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i{$where}", array_merge( array( $table ), $values ) ) );
		$last_page = max( 1, (int) ceil( $total / $per_page ) );
		$page      = min( max( 1, (int) $request->get_param( 'page' ) ), $last_page );
		$rows      = array();

		if ( $total > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i{$where} ORDER BY %i {$order}, `blocked_id` {$order} LIMIT %d OFFSET %d",
					array_merge( array( $table ), $values, array( $orderby, $per_page, ( $page - 1 ) * $per_page ) )
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$items = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$items[] = $this->filter_fields( self::prepare_block( $row ), $request );
		}

		$response = $this->add_pagination_headers( rest_ensure_response( $items ), $total, $per_page );
		$response->header( 'X-ZeroSpam-Page', (string) $page );

		return $response;
	}

	/**
	 * Blocks an IP address or a location. An address or location that is
	 * already blocked has its block replaced (200 instead of 201).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_block( $request ) {
		global $wpdb;

		$record = $this->check_block( $this->body( $request ), null );

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$existing = 'ip' === $record['key_type']
			? DB::blocked( $record['user_ip'] )
			: DB::blocked( (string) $record['blocked_key'], $record['key_type'] );

		$record['date_added'] = current_time( 'mysql' );

		if ( is_array( $existing ) && ! empty( $existing['blocked_id'] ) ) {
			$id = (int) $existing['blocked_id'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Updates the plugin's own row.
			$wpdb->update( self::blocked_table(), $record, array( 'blocked_id' => $id ) );
			$status = 200;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserts into the plugin's own table.
			if ( ! $wpdb->insert( self::blocked_table(), $record ) ) {
				return new WP_Error( 'zerospam_block_failed', __( 'The block could not be saved. Please try again.', 'zero-spam' ), array( 'status' => 500 ) );
			}
			$id     = (int) $wpdb->insert_id;
			$status = 201;
		}

		Utilities::refresh_htaccess();

		$response = rest_ensure_response( $this->filter_fields( self::prepare_block( (array) self::read_block( $id ) ), $request ) );
		$response->set_status( $status );

		return $response;
	}

	/**
	 * One block.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_block( $request ) {
		$row = self::read_block( (int) $request->get_param( 'id' ) );

		return null === $row ? $this->block_not_found() : rest_ensure_response( $this->filter_fields( self::prepare_block( $row ), $request ) );
	}

	/**
	 * Changes a block. Its kind (IP or location) cannot change.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_block( $request ) {
		global $wpdb;

		$row = self::read_block( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return $this->block_not_found();
		}

		$record = $this->check_block( $this->body( $request ), $row );

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$other = 'ip' === $record['key_type']
			? DB::blocked( $record['user_ip'] )
			: DB::blocked( (string) $record['blocked_key'], $record['key_type'] );

		if ( is_array( $other ) && ! empty( $other['blocked_id'] ) && (int) $other['blocked_id'] !== (int) $row['blocked_id'] ) {
			return new WP_Error(
				'zerospam_block_exists',
				__( 'That address or location already has a block. Change that block instead.', 'zero-spam' ),
				array(
					'status'   => 409,
					'block_id' => (int) $other['blocked_id'],
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Updates the plugin's own row.
		$wpdb->update( self::blocked_table(), $record, array( 'blocked_id' => (int) $row['blocked_id'] ) );
		Utilities::refresh_htaccess();

		return rest_ensure_response( $this->filter_fields( self::prepare_block( (array) self::read_block( (int) $row['blocked_id'] ) ), $request ) );
	}

	/**
	 * Removes a block; the response carries a token that restores it for two minutes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_block( $request ) {
		global $wpdb;

		$row = self::read_block( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return $this->block_not_found();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes the plugin's own row.
		$wpdb->delete( self::blocked_table(), array( 'blocked_id' => (int) $row['blocked_id'] ), array( '%d' ) );

		// Also when it was the last blocked address: the rules are cleared, not left in place.
		Utilities::refresh_htaccess();

		return rest_ensure_response(
			array(
				'deleted'    => 1,
				'undo_token' => Undo_Store::put( 'blocks', array( $row ) ),
				'previous'   => self::prepare_block( $row ),
			)
		);
	}

	/**
	 * A list setting, paged.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_list( $request ) {
		$name     = (string) $request->get_param( 'list' );
		$per_page = $this->clamp_per_page( $request->get_param( 'per_page' ), self::MAX_PER_PAGE );
		$items    = self::list_items( self::LISTS[ $name ]['key'] );
		$search   = $request->get_param( 'search' );
		$search   = is_string( $search ) ? strtolower( trim( $search ) ) : '';

		if ( '' !== $search ) {
			$items = array_values(
				array_filter(
					$items,
					static function ( string $item ) use ( $search ): bool {
						return str_contains( strtolower( $item ), $search );
					}
				)
			);
		}

		$total     = count( $items );
		$last_page = max( 1, (int) ceil( $total / $per_page ) );
		$page      = min( max( 1, (int) $request->get_param( 'page' ) ), $last_page );

		$data = $this->list_response( $name, array_slice( $items, ( $page - 1 ) * $per_page, $per_page ), $total );

		$response = $this->add_pagination_headers( rest_ensure_response( $this->filter_fields( $data, $request ) ), $total, $per_page );
		$response->header( 'X-ZeroSpam-Page', (string) $page );

		return $response;
	}

	/**
	 * Replaces a list (`items`) or edits it (`add`, `remove`); for the allowed
	 * words, `min_length` changes the shortest disallowed word checked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_list( $request ) {
		$name   = (string) $request->get_param( 'list' );
		$config = self::LISTS[ $name ];
		$body   = $this->body( $request );
		$errors = array();

		$has_items = array_key_exists( 'items', $body );
		$has_edits = array_key_exists( 'add', $body ) || array_key_exists( 'remove', $body );
		$has_extra = isset( $config['extra'] ) && array_key_exists( 'min_length', $body );

		if ( ! $has_items && ! $has_edits && ! $has_extra ) {
			return $this->invalid_params_error( array( 'items' => __( 'Send items (the whole list), or add and remove (entries to change).', 'zero-spam' ) ) );
		}

		if ( $has_items && $has_edits ) {
			return $this->invalid_params_error( array( 'items' => __( 'Send either items or add and remove, not both.', 'zero-spam' ) ) );
		}

		$values = array();

		if ( $has_items || $has_edits ) {
			$current = self::list_items( $config['key'] );
			$list    = $current;

			foreach ( $has_items ? array( 'items' ) : array( 'add', 'remove' ) as $field ) {
				if ( ! array_key_exists( $field, $body ) ) {
					continue;
				}

				$entries = $this->check_entries( $name, $body[ $field ], 'remove' === $field );

				if ( is_wp_error( $entries ) ) {
					$errors[ $field ] = $entries->get_error_message();
					continue;
				}

				if ( 'items' === $field ) {
					$list = $entries;
				} elseif ( 'add' === $field ) {
					$list = array_merge( $list, $entries );
				} else {
					$remove = array_map( 'strtolower', $entries );
					$list   = array_values(
						array_filter(
							$list,
							static function ( string $item ) use ( $remove ): bool {
								return ! in_array( strtolower( $item ), $remove, true );
							}
						)
					);
				}
			}

			$list = self::unique( $list );

			if ( count( $list ) > self::MAX_LIST_ITEMS ) {
				/* translators: %s: largest number of entries */
				$errors['items'] = sprintf( __( 'A list holds at most %s entries.', 'zero-spam' ), number_format_i18n( self::MAX_LIST_ITEMS ) );
			}

			$values[ $config['key'] ] = implode( "\n", $list );
		}

		if ( $has_extra ) {
			$min = $body['min_length'];

			if ( ! is_int( $min ) || $min < 0 || $min > self::MAX_MIN_LENGTH ) {
				/* translators: %d: largest allowed number */
				$errors['min_length'] = sprintf( __( 'Must be a whole number from 0 to %d.', 'zero-spam' ), self::MAX_MIN_LENGTH );
			} else {
				$values[ (string) $config['extra'] ] = $min;
			}
		}

		if ( $errors ) {
			return $this->invalid_params_error( $errors );
		}

		$settings = new Settings_Controller();
		$locked   = $settings->get_locked_keys( array_keys( $values ) );

		if ( $locked ) {
			return $this->locked_error( $locked );
		}

		$checked = $settings->validate_values( $values );

		if ( $checked['errors'] ) {
			return $this->invalid_params_error( $checked['errors'] );
		}

		$written = $settings->write_site_values( $checked['valid'] );

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		// The word lists are cached for the request.
		Utilities::reset_word_caches();

		$items = self::list_items( $config['key'] );
		$data  = $this->list_response( $name, array_slice( $items, 0, self::MAX_PER_PAGE ), count( $items ) );

		return $this->add_pagination_headers( rest_ensure_response( $this->filter_fields( $data, $request ) ), count( $items ), self::MAX_PER_PAGE );
	}

	/**
	 * The addresses Zero Spam never locks out on this site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_no_lockout( $request ) {
		return rest_ensure_response( $this->filter_fields( $this->no_lockout_response(), $request ) );
	}

	/**
	 * Removes an address from the never-locked-out list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_no_lockout( $request ) {
		$ip        = (string) $request->get_param( 'ip' );
		$canonical = User::normalize_ip( $ip );
		$ips       = Admin_Safe_IPs::get_ips();
		$match     = null;

		foreach ( $ips as $saved ) {
			if ( $saved === $ip || ( '' !== $canonical && User::normalize_ip( $saved ) === $canonical ) ) {
				$match = $saved;
				break;
			}
		}

		if ( null === $match ) {
			return new WP_Error( 'zerospam_no_lockout_not_found', __( 'That address is not on the list.', 'zero-spam' ), array( 'status' => 404 ) );
		}

		$remaining = array_values( array_diff( $ips, array( $match ) ) );
		if ( $remaining ) {
			update_option( Admin_Safe_IPs::OPTION, $remaining, false );
		} else {
			delete_option( Admin_Safe_IPs::OPTION );
		}

		// In .htaccess mode the address may be denied by the web server again.
		Utilities::refresh_htaccess();

		$notice = get_option( Admin_Safe_IPs::NOTICE_OPTION );
		if ( is_array( $notice ) && isset( $notice['ip'] ) && $match === $notice['ip'] ) {
			delete_option( Admin_Safe_IPs::NOTICE_OPTION );
		}

		$data            = $this->no_lockout_response();
		$data['removed'] = $match;

		return rest_ensure_response( $this->filter_fields( $data, $request ) );
	}

	/**
	 * A block as the API returns it.
	 *
	 * @param array<string, mixed> $row Row of the blocked table.
	 * @return array<string, mixed>
	 */
	public static function prepare_block( array $row ): array {
		$key_type = (string) ( $row['key_type'] ?? 'ip' );
		$is_ip    = 'ip' === $key_type;
		$key      = $is_ip ? (string) ( $row['user_ip'] ?? '' ) : (string) ( $row['blocked_key'] ?? '' );
		$country  = 'country_code' === $key_type ? Utilities::countries( strtoupper( $key ) ) : false;

		return array(
			'id'         => (int) ( $row['blocked_id'] ?? 0 ),
			'kind'       => $is_ip ? 'ip' : 'location',
			'key_type'   => $key_type,
			'key'        => $key,
			'ip'         => $is_ip ? $key : null,
			'label'      => is_string( $country ) ? $country : $key,
			'type'       => 'temporary' === ( $row['blocked_type'] ?? '' ) ? 'temporary' : 'permanent',
			'start'      => self::utc_date( $row['start_block'] ?? null ),
			'end'        => 'temporary' === ( $row['blocked_type'] ?? '' ) ? self::utc_date( $row['end_block'] ?? null ) : null,
			'reason'     => isset( $row['reason'] ) && '' !== (string) $row['reason'] ? (string) $row['reason'] : null,
			'date_added' => isset( $row['date_added'] ) && '' !== (string) $row['date_added'] ? mysql_to_rfc3339( (string) $row['date_added'] ) : null,
			'active'     => ! empty( Access::get_blocked_details( $row )['blocked'] ),
		);
	}

	/**
	 * Checks a block sent by a client.
	 *
	 * @param array<string, mixed>      $body     Request body.
	 * @param array<string, mixed>|null $existing The block being changed, if any.
	 * @return array<string, mixed>|WP_Error Columns to store, or the errors (data.params).
	 */
	private function check_block( array $body, ?array $existing ) {
		$errors = array();
		$record = array();

		$kind = null !== $existing
			? ( 'ip' === ( $existing['key_type'] ?? 'ip' ) ? 'ip' : 'location' )
			: ( $body['kind'] ?? 'ip' );

		if ( null !== $existing && isset( $body['kind'] ) && $body['kind'] !== $kind ) {
			$errors['kind'] = __( 'A block cannot change between an IP address and a location.', 'zero-spam' );
		} elseif ( ! in_array( $kind, array( 'ip', 'location' ), true ) ) {
			$errors['kind'] = __( 'Must be ip or location.', 'zero-spam' );
		}

		if ( 'ip' === $kind ) {
			$ip = $body['ip'] ?? ( null !== $existing ? $existing['user_ip'] : null );

			if ( ! is_string( $ip ) || '' === trim( $ip ) ) {
				$errors['ip'] = __( 'Enter the IP address to block.', 'zero-spam' );
			} elseif ( str_contains( $ip, '/' ) ) {
				$errors['ip'] = __( 'Blocks are for single addresses; ranges (CIDR) are not supported yet.', 'zero-spam' );
			} else {
				$canonical = User::normalize_ip( $ip );

				if ( '' === $canonical ) {
					$errors['ip'] = __( 'Must be an IPv4 or IPv6 address.', 'zero-spam' );
				} else {
					$record['user_ip']     = $canonical;
					$record['key_type']    = 'ip';
					$record['blocked_key'] = null;
				}
			}
		} elseif ( 'location' === $kind ) {
			$key_type = $body['key_type'] ?? ( null !== $existing ? $existing['key_type'] : null );
			$key      = $body['key'] ?? ( null !== $existing ? $existing['blocked_key'] : null );

			if ( ! is_string( $key_type ) || ! in_array( $key_type, self::LOCATION_KEYS, true ) ) {
				/* translators: %s: comma-separated list of allowed values */
				$errors['key_type'] = sprintf( __( 'Must be one of: %s.', 'zero-spam' ), implode( ', ', self::LOCATION_KEYS ) );
			} else {
				$checked = self::check_location_key( $key_type, $key );

				if ( is_wp_error( $checked ) ) {
					$errors['key'] = $checked->get_error_message();
				} else {
					$record['user_ip']     = '';
					$record['key_type']    = $key_type;
					$record['blocked_key'] = $checked;
				}
			}
		}

		$type = $body['type'] ?? ( null !== $existing ? $existing['blocked_type'] : null );

		if ( ! in_array( $type, array( 'permanent', 'temporary' ), true ) ) {
			$errors['type'] = __( 'Must be permanent or temporary.', 'zero-spam' );
		} else {
			$record['blocked_type'] = $type;
		}

		$now   = time();
		$start = $now;

		if ( isset( $body['start'] ) && '' !== $body['start'] ) {
			$parsed = is_string( $body['start'] ) ? self::parse_utc( $body['start'], false ) : null;

			if ( null === $parsed ) {
				$errors['start'] = __( 'Must be a date (YYYY-MM-DD) or a date and time (ISO 8601).', 'zero-spam' );
			} else {
				$start = $parsed;
			}
		} elseif ( null !== $existing && ! empty( $existing['start_block'] ) && ! array_key_exists( 'start', $body ) ) {
			$start = (int) strtotime( $existing['start_block'] . ' UTC' );
		}

		$record['start_block'] = gmdate( 'Y-m-d H:i:s', $start );
		$record['end_block']   = null;

		if ( 'temporary' === $type ) {
			$sent = $body['end'] ?? null;
			$kept = null === $sent && null !== $existing && ! empty( $existing['end_block'] ) && 'temporary' === $existing['blocked_type']
				? (int) strtotime( $existing['end_block'] . ' UTC' )
				: null;

			if ( null !== $kept && $kept > $start ) {
				// Unchanged end of the block being edited (it may have passed already).
				$record['end_block'] = gmdate( 'Y-m-d H:i:s', $kept );
			} elseif ( ! is_string( $sent ) || '' === trim( $sent ) ) {
				$errors['end'] = __( 'A temporary block needs an end date.', 'zero-spam' );
			} else {
				$parsed = self::parse_utc( $sent, true );

				if ( null === $parsed ) {
					$errors['end'] = __( 'Must be a date (YYYY-MM-DD) or a date and time (ISO 8601).', 'zero-spam' );
				} elseif ( $parsed <= $start || $parsed <= $now ) {
					$errors['end'] = __( 'The end must be in the future and after the start.', 'zero-spam' );
				} else {
					$record['end_block'] = gmdate( 'Y-m-d H:i:s', $parsed );
				}
			}
		}

		if ( array_key_exists( 'reason', $body ) ) {
			$reason = $body['reason'];

			if ( null !== $reason && ! is_string( $reason ) ) {
				$errors['reason'] = __( 'Must be text.', 'zero-spam' );
			} else {
				$reason = sanitize_text_field( (string) $reason );

				if ( strlen( $reason ) > 255 ) {
					$errors['reason'] = __( 'Must be at most 255 characters.', 'zero-spam' );
				}

				$record['reason'] = $reason;
			}
		} elseif ( null === $existing ) {
			$record['reason'] = '';
		}

		return $errors ? $this->invalid_params_error( $errors ) : $record;
	}

	/**
	 * Checks the value of a location block.
	 *
	 * @param string $key_type Location key type.
	 * @param mixed  $key      Value sent.
	 * @return string|WP_Error The value to store.
	 */
	private static function check_location_key( string $key_type, $key ) {
		if ( ! is_string( $key ) || '' === trim( $key ) ) {
			return new WP_Error( 'invalid', __( 'Enter the location to block.', 'zero-spam' ) );
		}

		$key = trim( sanitize_text_field( $key ) );

		switch ( $key_type ) {
			case 'country_code':
				$key = strtoupper( $key );

				return 1 === preg_match( '/^[A-Z]{2}$/', $key ) && false !== Utilities::countries( $key )
					? $key
					: new WP_Error( 'invalid', __( 'Must be a two-letter country code, such as DE or US.', 'zero-spam' ) );

			case 'zip':
				return 1 === preg_match( '/^[A-Za-z0-9 -]{1,10}$/', $key )
					? $key
					: new WP_Error( 'invalid', __( 'Must be a postal code of at most 10 letters, digits, spaces or dashes.', 'zero-spam' ) );

			default:
				return strlen( $key ) <= 100
					? $key
					: new WP_Error( 'invalid', __( 'Must be at most 100 characters.', 'zero-spam' ) );
		}
	}

	/**
	 * Checks entries of a list.
	 *
	 * @param string $name     List name.
	 * @param mixed  $entries  Entries sent.
	 * @param bool   $removing Whether they are being removed (only their form is loose then).
	 * @return string[]|WP_Error
	 */
	private function check_entries( string $name, $entries, bool $removing ) {
		if ( ! is_array( $entries ) || ! array_is_list( $entries ) || count( $entries ) > self::MAX_LIST_ITEMS ) {
			/* translators: %s: largest number of entries */
			return new WP_Error( 'invalid', sprintf( __( 'Must be a list of at most %s entries.', 'zero-spam' ), number_format_i18n( self::MAX_LIST_ITEMS ) ) );
		}

		$valid = array();
		$bad   = array();

		foreach ( $entries as $entry ) {
			if ( ! is_string( $entry ) ) {
				$bad[] = is_scalar( $entry ) ? (string) $entry : gettype( $entry );
				continue;
			}

			$entry = trim( $entry );

			if ( '' === $entry ) {
				continue;
			}

			if ( $removing ) {
				$valid[] = 'allowed-ips' === $name && '' !== User::normalize_ip( $entry ) ? User::normalize_ip( $entry ) : $entry;
				continue;
			}

			$checked = self::check_entry( $name, $entry );

			if ( null === $checked ) {
				$bad[] = $entry;
			} else {
				$valid[] = $checked;
			}
		}

		if ( $bad ) {
			$shown = array_map( 'sanitize_text_field', array_slice( $bad, 0, 10 ) );

			switch ( $name ) {
				case 'allowed-ips':
					/* translators: %s: comma-separated list of entries */
					$message = __( 'These are not IP addresses: %s', 'zero-spam' );
					break;
				case 'email-domains':
					/* translators: %s: comma-separated list of entries */
					$message = __( 'These are not domain names (example.com): %s', 'zero-spam' );
					break;
				default:
					/* translators: %s: comma-separated list of entries */
					$message = __( 'These are not words of at most 100 characters: %s', 'zero-spam' );
			}

			return new WP_Error( 'invalid', sprintf( $message, implode( ', ', $shown ) . ( count( $bad ) > 10 ? ', …' : '' ) ) );
		}

		return $valid;
	}

	/**
	 * One list entry in the form it is stored, or null when it is not valid.
	 *
	 * @param string $name  List name.
	 * @param string $entry Entry.
	 */
	private static function check_entry( string $name, string $entry ): ?string {
		switch ( $name ) {
			case 'allowed-ips':
				$canonical = User::normalize_ip( $entry );

				return '' !== $canonical ? $canonical : null;

			case 'email-domains':
				$domain = strtolower( ltrim( $entry, '@' ) );

				return 1 === preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $domain ) ? $domain : null;

			default:
				$word = sanitize_text_field( $entry );

				return '' !== $word && strlen( $word ) <= 100 && ! str_contains( $word, "\n" ) ? $word : null;
		}
	}

	/**
	 * The entries of a list setting, as the site uses them.
	 *
	 * @param string $key Setting key.
	 * @return string[]
	 */
	private static function list_items( string $key ): array {
		$resolved = Settings_Schema::resolve( $key );
		$value    = null !== $resolved && is_string( $resolved['value'] ) ? $resolved['value'] : '';
		$items    = array();

		foreach ( (array) preg_split( '/\R/', $value ) as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$items[] = $line;
			}
		}

		return $items;
	}

	/**
	 * A list without repeated entries (compared without case), in order.
	 *
	 * @param string[] $items Entries.
	 * @return string[]
	 */
	private static function unique( array $items ): array {
		$seen   = array();
		$unique = array();

		foreach ( $items as $item ) {
			$lower = strtolower( $item );
			if ( ! isset( $seen[ $lower ] ) ) {
				$seen[ $lower ] = true;
				$unique[]       = $item;
			}
		}

		return $unique;
	}

	/**
	 * A list as the API returns it.
	 *
	 * @param string   $name  List name.
	 * @param string[] $items Entries of the page.
	 * @param int      $total Entries in the whole list (or the search).
	 * @return array<string, mixed>
	 */
	private function list_response( string $name, array $items, int $total ): array {
		$config   = self::LISTS[ $name ];
		$resolved = Settings_Schema::resolve( $config['key'] );

		$data = array(
			'list'   => $name,
			'key'    => $config['key'],
			'items'  => $items,
			'total'  => $total,
			'locked' => null !== $resolved && $resolved['locked'],
			'source' => null !== $resolved ? $resolved['source'] : Settings_Schema::SOURCE_DEFAULT,
		);

		if ( isset( $config['extra'] ) ) {
			$extra              = Settings_Schema::resolve( $config['extra'] );
			$data['min_length'] = array(
				'key'    => $config['extra'],
				'value'  => null !== $extra && is_numeric( $extra['value'] ) ? max( 0, (int) $extra['value'] ) : 0,
				'locked' => null !== $extra && $extra['locked'],
				'source' => null !== $extra ? $extra['source'] : Settings_Schema::SOURCE_DEFAULT,
			);
		}

		return $data;
	}

	/**
	 * The never-locked-out list as the API returns it.
	 *
	 * @return array<string, mixed>
	 */
	private function no_lockout_response(): array {
		$current = User::get_ip();
		$current = is_string( $current ) ? User::normalize_ip( $current ) : '';
		$items   = array();

		foreach ( Admin_Safe_IPs::get_ips() as $ip ) {
			$items[] = array(
				'ip'      => $ip,
				'current' => '' !== $current && User::normalize_ip( $ip ) === $current,
			);
		}

		return array(
			'items' => $items,
			'max'   => Admin_Safe_IPs::MAX_IPS,
		);
	}

	/**
	 * The JSON body of a request, or an empty array.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function body( WP_REST_Request $request ): array {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * A date sent by a client as a UTC timestamp.
	 *
	 * A date alone is the start of that day in the site's time zone (as the
	 * end of a block, the end of that day); a time without an offset is in
	 * the site's time zone.
	 *
	 * @param string $value  Date or time.
	 * @param bool   $is_end Whether it is the end of a block.
	 */
	private static function parse_utc( string $value, bool $is_end ): ?int {
		$value = trim( $value );

		if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
				return null;
			}

			return (int) strtotime( get_gmt_from_date( $value . ( $is_end ? ' 23:59:59' : ' 00:00:00' ) ) . ' UTC' );
		}

		$timestamp = rest_parse_date( $value );

		if ( false === $timestamp ) {
			return null;
		}

		if ( 1 === preg_match( '/(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $value ) ) {
			return $timestamp;
		}

		// Without an offset: site time.
		return (int) strtotime( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $timestamp ) ) . ' UTC' );
	}

	/**
	 * A stored block date (UTC) in ISO 8601 with `Z`, or null.
	 *
	 * @param mixed $value Stored date.
	 */
	private static function utc_date( $value ): ?string {
		if ( ! is_string( $value ) || '' === $value || str_starts_with( $value, '0000-00-00' ) ) {
			return null;
		}

		$timestamp = strtotime( $value . ' UTC' );

		return false === $timestamp ? null : gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	/**
	 * Codes of the countries whose name contains a text.
	 *
	 * @param string $search Text.
	 * @return string[]
	 */
	private static function country_codes_matching( string $search ): array {
		$codes     = array();
		$countries = Utilities::countries();

		foreach ( is_array( $countries ) ? $countries : array() as $code => $country ) {
			if ( is_string( $country ) && false !== stripos( $country, $search ) ) {
				$codes[] = (string) $code;
			}
		}

		return array_slice( $codes, 0, 50 );
	}

	/**
	 * One block row, or null.
	 *
	 * @param int $id Block ID.
	 * @return array<string, mixed>|null
	 */
	private static function read_block( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row of the plugin's own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE `blocked_id` = %d', self::blocked_table(), $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * The 404 for a block that does not exist.
	 */
	private function block_not_found(): WP_Error {
		return new WP_Error( 'zerospam_block_not_found', __( 'No block has that ID.', 'zero-spam' ), array( 'status' => 404 ) );
	}

	/**
	 * Full name of the current site's blocked table.
	 */
	private static function blocked_table(): string {
		global $wpdb;

		return $wpdb->prefix . DB::$tables['blocked'];
	}
}
