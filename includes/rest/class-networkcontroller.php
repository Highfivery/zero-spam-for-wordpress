<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Controller to this file name.
/**
 * REST routes of the 6.0 network page.
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
use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\User;
use ZeroSpam\Includes\Network_Blocks;
use ZeroSpam\Includes\Network_Notifications;
use ZeroSpam\Includes\Network_Report;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Includes\Undo_Store;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/network/…`, for network administrators
 * (`manage_network_options`) on a network only:
 *
 * - `network/overview`: sites, network values, locks and how many sites
 *   changed each network default (Network_Report::overview()).
 * - `network/sites`: a page of the network's sites (search by address).
 * - `network/comparison`: a page of sites against the network values.
 * - `network/stats`: detections per site for a period (paged), the
 *   network's totals and the addresses seen on several sites.
 * - `network/blocks` (GET, POST) and `network/blocks/{id}` (DELETE, with an
 *   undo token): addresses blocked on every site (Network_Blocks).
 * - `network/notifications` (GET, PUT): the weekly summary and the
 *   settings change emails.
 *
 * Site administrators get 403 on every route: none of it is theirs to see
 * (they see the network's blocks through `rules/network-blocks`).
 *
 * @since 6.0.0
 */
class Network_Controller extends Base_Controller {

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
	protected $rest_base = 'network';

	/**
	 * Sort columns of the per-site statistics.
	 *
	 * @var string[]
	 */
	const STATS_ORDERBY = array( 'blocked', 'name', 'trend' );

	/**
	 * Registers the routes (on a network only).
	 */
	public function register_routes() {
		if ( ! is_multisite() ) {
			return;
		}

		$paging = array(
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
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/overview',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_overview' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sites' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
					'args'                => $paging + array(
						'search' => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/comparison',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_comparison' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
					'args'                => array(
						'page'     => $paging['page'],
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 20,
							'default' => 5,
						),
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
					'args'                => $paging + array(
						'period'  => array(
							'type'    => 'string',
							'enum'    => Network_Report::PERIODS,
							'default' => '30',
						),
						'orderby' => array(
							'type'    => 'string',
							'enum'    => self::STATS_ORDERBY,
							'default' => 'blocked',
						),
						'order'   => array(
							'type'    => 'string',
							'enum'    => array( 'asc', 'desc' ),
							'default' => 'desc',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/blocks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_blocks' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
					'args'                => $paging + array(
						'search' => array(
							'description' => __( 'IP address (or its start), or text in the reason.', 'zero-spam' ),
							'type'        => 'string',
							'maxLength'   => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_block' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/blocks/(?P<id>\d+)',
			array(
				'args' => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_block' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notifications' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_notifications' ),
					'permission_callback' => array( $this, 'network_permissions_check' ),
					'args'                => array(
						'weekly'  => array(
							'description' => __( 'Weekly summary emails to the network administrator.', 'zero-spam' ),
							'type'        => 'boolean',
						),
						'changes' => array(
							'description' => __( 'Emails to site administrators when network settings change.', 'zero-spam' ),
							'type'        => 'boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * Sites, network values, locks and site changes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_overview( $request ) {
		unset( $request );

		return rest_ensure_response( Network_Report::overview() + array( 'blocks' => Network_Blocks::count() ) );
	}

	/**
	 * A page of sites.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_sites( $request ) {
		$per_page = $this->clamp_per_page( $request->get_param( 'per_page' ) );
		$search   = trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) );
		$sites    = Network_Report::sites_page( (int) $request->get_param( 'page' ), $per_page, $search );

		return $this->add_pagination_headers( rest_ensure_response( $sites['items'] ), $sites['total'], $per_page );
	}

	/**
	 * A page of sites against the network values.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_comparison( $request ) {
		$per_page = max( 1, min( 20, (int) $request->get_param( 'per_page' ) ) );
		$search   = trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) );
		$data     = Network_Report::comparison( (int) $request->get_param( 'page' ), $per_page, $search );
		$overview = Network_Report::overview();

		foreach ( $data['settings'] as $i => $row ) {
			$data['settings'][ $i ]['custom'] = (int) ( $overview['custom'][ $row['key'] ] ?? 0 );
		}

		$response = rest_ensure_response(
			array(
				'sites'    => $data['sites'],
				'settings' => $data['settings'],
				'checked'  => $overview['checked'],
				'capped'   => $overview['capped'],
			)
		);

		return $this->add_pagination_headers( $response, $data['total'], $per_page );
	}

	/**
	 * Network statistics for a period, with one page of sites.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$stats    = Network_Report::stats( (string) $request->get_param( 'period' ) );
		$per_page = $this->clamp_per_page( $request->get_param( 'per_page' ) );
		$orderby  = in_array( $request->get_param( 'orderby' ), self::STATS_ORDERBY, true ) ? (string) $request->get_param( 'orderby' ) : 'blocked';
		$desc     = 'asc' !== $request->get_param( 'order' );
		$sites    = $stats['sites'];

		usort(
			$sites,
			static function ( array $a, array $b ) use ( $orderby, $desc ): int {
				if ( 'name' === $orderby ) {
					$order = strcasecmp( (string) ( '' !== $a['name'] ? $a['name'] : $a['url'] ), (string) ( '' !== $b['name'] ? $b['name'] : $b['url'] ) );

					return $desc ? -$order : $order;
				}

				$key   = 'trend' === $orderby ? 'trend' : 'blocked';
				$order = ( (float) ( $a[ $key ] ?? PHP_INT_MAX ) ) <=> ( (float) ( $b[ $key ] ?? PHP_INT_MAX ) );
				$order = 0 === $order ? $a['id'] <=> $b['id'] : $order;

				return $desc ? -$order : $order;
			}
		);

		$total = count( $sites );
		$page  = max( 1, min( (int) $request->get_param( 'page' ), max( 1, (int) ceil( $total / $per_page ) ) ) );
		$shown = array_slice( $sites, ( $page - 1 ) * $per_page, $per_page );

		// The protection level of the sites on this page only (no API request, no registry per site).
		foreach ( $shown as $i => $site ) {
			$shown[ $i ]['level'] = License_Status::site_level( (int) $site['id'] );
		}

		$blocked   = Network_Blocks::by_ips( array_column( $stats['attackers'], 'ip' ) );
		$attackers = array();
		foreach ( $stats['attackers'] as $attacker ) {
			$block       = $blocked[ User::normalize_ip( $attacker['ip'] ) ] ?? null;
			$attackers[] = $attacker + array(
				'blocked'  => null !== $block && ! empty( Access::get_blocked_details( $block )['blocked'] ),
				'block_id' => null !== $block ? (int) $block['blocked_id'] : null,
			);
		}

		$response = rest_ensure_response(
			array(
				'period'        => $stats['period'],
				'totals'        => $stats['totals'],
				'checked'       => $stats['checked'],
				'capped'        => $stats['capped'],
				'sites'         => $shown,
				'attackers'     => $attackers,
				'attacker_days' => Network_Report::ATTACKER_DAYS,
			)
		);

		$response = $this->add_pagination_headers( $response, $total, $per_page );
		$response->header( 'X-ZeroSpam-Page', (string) $page );

		return $response;
	}

	/**
	 * The network's blocks, newest first, paged.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_blocks( $request ) {
		$list = self::list_blocks( (string) $request->get_param( 'search' ), (int) $request->get_param( 'page' ), $this->clamp_per_page( $request->get_param( 'per_page' ) ), true );

		$response = $this->add_pagination_headers( rest_ensure_response( $list['items'] ), $list['total'], $list['per_page'] );
		$response->header( 'X-ZeroSpam-Page', (string) $list['page'] );

		return $response;
	}

	/**
	 * A page of network blocks (shared with `rules/network-blocks`).
	 *
	 * @param string $search   IP address start or text in the reason.
	 * @param int    $page     Page.
	 * @param int    $per_page Page size.
	 * @param bool   $full     Whether to include who added each block (network administrators only).
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public static function list_blocks( string $search, int $page, int $per_page, bool $full ): array {
		global $wpdb;

		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$result   = array(
			'items'    => array(),
			'total'    => 0,
			'page'     => 1,
			'per_page' => $per_page,
		);

		if ( ! is_multisite() || ! Network_Blocks::table_exists() ) {
			return $result;
		}

		$search = trim( sanitize_text_field( $search ) );
		$where  = '1=1';
		$values = array( Network_Blocks::table() );

		if ( '' !== $search ) {
			$canonical = User::normalize_ip( $search );
			$where     = '(`user_ip` LIKE %s OR `user_ip` = %s OR `reason` LIKE %s)';
			$values    = array_merge( $values, array( $wpdb->esc_like( $search ) . '%', '' !== $canonical ? $canonical : $search, '%' . $wpdb->esc_like( $search ) . '%' ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- A paged list of the plugin's own table; $where is a fixed fragment with one placeholder per value in $values.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", $values ) );
		$page  = max( 1, min( $page, max( 1, (int) ceil( $total / $per_page ) ) ) );
		$rows  = $total > 0 ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY `date_added` DESC, `blocked_id` DESC LIMIT %d OFFSET %d", array_merge( $values, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A ) : array();
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result['items'][] = self::prepare_block( $row, $full );
		}

		$result['total'] = $total;
		$result['page']  = $page;

		return $result;
	}

	/**
	 * Blocks an address on every site. An address the network already
	 * blocks has its block replaced (200 instead of 201).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_block( $request ) {
		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : (array) $request->get_body_params();
		$record = $this->check_block( $body );

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$saved = Network_Blocks::save( $record );

		if ( null === $saved ) {
			return new WP_Error( 'zerospam_block_failed', __( 'The block could not be saved. Please try again.', 'zero-spam' ), array( 'status' => 500 ) );
		}

		( new Network_Settings_Manager() )->record(
			'block',
			null,
			null,
			sprintf(
				/* translators: %s: IP address */
				__( '%s blocked on every site', 'zero-spam' ),
				$record['user_ip']
			)
		);

		$response = rest_ensure_response( self::prepare_block( (array) Network_Blocks::read( $saved['id'] ), true ) );
		$response->set_status( $saved['created'] ? 201 : 200 );

		return $response;
	}

	/**
	 * Removes a network block; the answer carries a token that restores it
	 * for two minutes (`undo/{token}`, by the same network administrator).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_block( $request ) {
		$row = Network_Blocks::read( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return new WP_Error( 'zerospam_block_not_found', __( 'No block has that ID.', 'zero-spam' ), array( 'status' => 404 ) );
		}

		Network_Blocks::delete( (int) $row['blocked_id'] );

		( new Network_Settings_Manager() )->record(
			'unblock',
			null,
			null,
			sprintf(
				/* translators: %s: IP address */
				__( '%s unblocked on every site', 'zero-spam' ),
				$row['user_ip']
			)
		);

		return rest_ensure_response(
			array(
				'deleted'    => 1,
				'undo_token' => Undo_Store::put( 'network_blocks', array( $row ) ),
				'previous'   => self::prepare_block( $row, true ),
			)
		);
	}

	/**
	 * The two email preferences.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_notifications( $request ) {
		unset( $request );

		return rest_ensure_response( self::notifications() );
	}

	/**
	 * Saves the email preferences sent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_notifications( $request ) {
		$weekly  = $request->get_param( 'weekly' );
		$changes = $request->get_param( 'changes' );

		if ( null === $weekly && null === $changes ) {
			return $this->invalid_params_error( array( 'weekly' => __( 'Send weekly, changes, or both.', 'zero-spam' ) ) );
		}

		$saved = ( new Network_Notifications() )->save_preferences(
			null === $weekly ? null : (bool) $weekly,
			null === $changes ? null : (bool) $changes
		);

		if ( ! $saved ) {
			return new WP_Error( 'zerospam_save_failed', __( 'The notification settings could not be saved. Please try again.', 'zero-spam' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( self::notifications() );
	}

	/**
	 * The email preferences as the API returns them.
	 *
	 * @return array{weekly: bool, changes: bool}
	 */
	private static function notifications(): array {
		return array(
			'weekly'  => '1' === (string) get_site_option( 'zerospam_network_notifications_enabled', '1' ),
			'changes' => '1' === (string) get_site_option( 'zerospam_network_settings_notifications_enabled', '1' ),
		);
	}

	/**
	 * A network block as the API returns it.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param bool                 $full Include who added it.
	 * @return array<string, mixed>
	 */
	public static function prepare_block( array $row, bool $full ): array {
		$temporary = 'temporary' === ( $row['blocked_type'] ?? '' );
		$block     = array(
			'id'         => (int) ( $row['blocked_id'] ?? 0 ),
			'ip'         => (string) ( $row['user_ip'] ?? '' ),
			'type'       => $temporary ? 'temporary' : 'permanent',
			'start'      => Rules_Controller::utc_date( $row['start_block'] ?? null ),
			'end'        => $temporary ? Rules_Controller::utc_date( $row['end_block'] ?? null ) : null,
			'reason'     => isset( $row['reason'] ) && '' !== (string) $row['reason'] ? (string) $row['reason'] : null,
			'date_added' => isset( $row['date_added'] ) && '' !== (string) $row['date_added'] ? mysql_to_rfc3339( (string) $row['date_added'] ) : null,
			'active'     => ! empty( Access::get_blocked_details( $row )['blocked'] ),
		);

		if ( $full ) {
			$user              = ! empty( $row['created_by'] ) ? get_userdata( (int) $row['created_by'] ) : false;
			$block['added_by'] = $user ? (string) $user->user_login : null;
		}

		return $block;
	}

	/**
	 * Checks a block sent by a client.
	 *
	 * @param array<string, mixed> $body Request body: ip, type, end, reason.
	 * @return array<string, mixed>|WP_Error Columns to store, or the errors (data.params).
	 */
	private function check_block( array $body ) {
		$errors = array();
		$record = array();
		$ip     = $body['ip'] ?? null;

		if ( ! is_string( $ip ) || '' === trim( $ip ) ) {
			$errors['ip'] = __( 'Enter the IP address to block.', 'zero-spam' );
		} elseif ( str_contains( $ip, '/' ) ) {
			$errors['ip'] = __( 'Blocks are for single addresses; ranges (CIDR) are not supported yet.', 'zero-spam' );
		} else {
			$canonical = User::normalize_ip( $ip );

			if ( '' === $canonical ) {
				$errors['ip'] = __( 'Must be an IPv4 or IPv6 address.', 'zero-spam' );
			} else {
				$record['user_ip'] = $canonical;
			}
		}

		$type = $body['type'] ?? 'permanent';

		if ( ! in_array( $type, array( 'permanent', 'temporary' ), true ) ) {
			$errors['type'] = __( 'Must be permanent or temporary.', 'zero-spam' );
		} else {
			$record['blocked_type'] = $type;
		}

		$now                   = time();
		$record['start_block'] = gmdate( 'Y-m-d H:i:s', $now );
		$record['end_block']   = null;

		if ( 'temporary' === $type ) {
			$end    = $body['end'] ?? null;
			$parsed = is_string( $end ) && '' !== trim( $end ) ? Rules_Controller::parse_utc( $end, true ) : null;

			if ( ! is_string( $end ) || '' === trim( $end ) ) {
				$errors['end'] = __( 'A temporary block needs an end date.', 'zero-spam' );
			} elseif ( null === $parsed ) {
				$errors['end'] = __( 'Must be a date (YYYY-MM-DD) or a date and time (ISO 8601).', 'zero-spam' );
			} elseif ( $parsed <= $now ) {
				$errors['end'] = __( 'The end must be in the future and after the start.', 'zero-spam' );
			} else {
				$record['end_block'] = gmdate( 'Y-m-d H:i:s', $parsed );
			}
		}

		$reason = $body['reason'] ?? '';

		if ( ! is_string( $reason ) ) {
			$errors['reason'] = __( 'Must be text.', 'zero-spam' );
		} else {
			$reason = sanitize_text_field( (string) $reason );

			if ( strlen( $reason ) > 255 ) {
				$errors['reason'] = __( 'Must be at most 255 characters.', 'zero-spam' );
			}

			$record['reason'] = $reason;
		}

		return $errors ? $this->invalid_params_error( $errors ) : $record;
	}
}
