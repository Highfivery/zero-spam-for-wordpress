<?php
/**
 * Log table class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin\Tables;

use ZeroSpam;
use WP_List_Table;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Log table
 */
class LogTable extends WP_List_Table {

	/**
	 * Number of rows per page.
	 *
	 * @var int
	 */
	public const PER_PAGE = 50;

	/**
	 * Constructor
	 */
	public function __construct() {
		$args = array(
			'singular' => __( 'Zero Spam for WordPress Log', 'zero-spam' ),
			'plural'   => __( 'Zero Spam for WordPress Logs', 'zero-spam' ),
		);
		parent::__construct( $args );
	}

	/**
	 * Define table columns
	 *
	 * @return array<string, string> Column key => header.
	 */
	public function get_columns(): array {
		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'date_recorded' => __( 'Date', 'zero-spam' ),
			'log_type'      => __( 'Type', 'zero-spam' ),
			'user_ip'       => __( 'IP Address', 'zero-spam' ),
			'country'       => __( 'Country', 'zero-spam' ),
			'region'        => __( 'Region', 'zero-spam' ),
			'actions'       => __( 'Actions', 'zero-spam' ),
		);

		return $columns;
	}

	/**
	 * Sortable columns
	 *
	 * The keys are the only `orderby` values prepare_items() accepts, and the
	 * first element of each value is the database column it sorts by.
	 *
	 * @return array<string, array{0: string, 1: bool}> Column key => [ column, initially descending ].
	 */
	public function get_sortable_columns(): array {
		$sortable_columns = array(
			'date_recorded' => array( 'date_recorded', false ),
			'log_type'      => array( 'log_type', false ),
			'user_ip'       => array( 'user_ip', false ),
			'country'       => array( 'country', false ),
			'region'        => array( 'region', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Checkbox column
	 *
	 * @param array<string, mixed> $item Log row.
	 * @return string Checkbox markup.
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'ids',
			esc_attr( $item['log_id'] )
		);
	}

	/**
	 * Column values
	 *
	 * Logged data comes from visitors (sanitized on write), so every value is
	 * escaped here; WP_List_Table prints the return value as is.
	 *
	 * @param array<string, mixed> $item        Log row.
	 * @param string               $column_name Column key.
	 * @return string Escaped cell markup.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'log_type':
				$value = ! empty( $item[ $column_name ] ) ? (string) $item[ $column_name ] : '';
				if ( '' === $value ) {
					return esc_html__( 'N/A', 'zero-spam' );
				}

				$types = apply_filters( 'zerospam_types', array() );
				$label = ! empty( $types[ $value ]['label'] ) ? $types[ $value ]['label'] : $value;

				return '<span class="zerospam-type-' . esc_attr( $value ) . '">' . esc_html( $label ) . '</span>';
			case 'user_ip':
				$user_ip = (string) $item[ $column_name ];

				return '<a href="' . esc_url( ZEROSPAM_URL . 'ip-lookup/' . rawurlencode( $user_ip ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $user_ip ) . '</a>';
			case 'date_recorded':
				$date_time_format = 'm/d/Y g:ia';

				return esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $item[ $column_name ] ) ), $date_time_format ) );
			case 'actions':
				$icon_url = plugin_dir_url( ZEROSPAM ) . 'assets/img/';
				ob_start();
				?>
				<button class="button zerospam-details-trigger" data-id="<?php echo esc_attr( $item['log_id'] ); ?>" aria-label="<?php esc_attr_e( 'Details', 'zero-spam' ); ?>"><img src="<?php echo esc_url( $icon_url . 'icon-info.svg' ); ?>" width="13" /></button>
				<div class="zerospam-modal" id="zerospam-details-<?php echo esc_attr( $item['log_id'] ); ?>">
					<button class="zerospam-close-modal" aria-label="<?php esc_attr_e( 'Close Modal', 'zero-spam' ); ?>"></button>

					<div class="zerospam-block zerospam-block--list">
						<h3 class="zerospam-block__headline"><?php esc_html_e( 'Detection ID #', 'zero-spam' ); ?><?php echo esc_html( $item['log_id'] ); ?></h3>
						<div class="zerospam-block__content">
							<?php require ZEROSPAM_PATH . 'includes/templates/admin-modal-details.php'; ?>
						</div>
					</div>

				</div>
				<?php
				$blocked = ZeroSpam\Includes\DB::blocked( $item['user_ip'] );
				if ( $blocked ) :
					?>
					<button
						class="button zerospam-block-trigger"
						data-ip="<?php echo esc_attr( $item['user_ip'] ); ?>"
						data-reason="<?php echo esc_attr( $blocked['reason'] ); ?>"
						data-start="<?php echo esc_attr( $this->datetime_local( $blocked['start_block'] ) ); ?>"
						data-end="<?php echo esc_attr( $this->datetime_local( $blocked['end_block'] ) ); ?>"
						data-type="<?php echo esc_attr( $blocked['blocked_type'] ); ?>"
						aria-label="<?php esc_attr_e( 'Update Block', 'zero-spam' ); ?>"
					>
						<img src="<?php echo esc_url( $icon_url . 'icon-edit.svg' ); ?>" width="13" />
					</button>
					<?php
				else :
					?>
					<button class="button zerospam-block-trigger" data-ip="<?php echo esc_attr( $item['user_ip'] ); ?>" aria-label="<?php esc_attr_e( 'Block IP', 'zero-spam' ); ?>"><img src="<?php echo esc_url( $icon_url . 'icon-blocked.svg' ); ?>" width="13" /></button>
					<?php
				endif;

				return (string) ob_get_clean();
			case 'country':
				if ( empty( $item[ $column_name ] ) ) {
					return esc_html__( 'N/A', 'zero-spam' );
				}

				$country_code = (string) $item[ $column_name ];
				$country_name = ! empty( $item['country_name'] ) ? (string) $item['country_name'] : '';
				$flag         = ZeroSpam\Core\Utilities::country_flag_url( $country_code );
				$text         = $country_name ? $country_name . ' (' . $country_code . ')' : $country_code;

				return '<img src="' . esc_url( $flag ) . '" width="16" height="16" alt="' . esc_attr( $country_name . ' (' . $country_code . ')' ) . '" class="zerospam-flag" />' . esc_html( $text );
			default:
				if ( empty( $item[ $column_name ] ) ) {
					return esc_html__( 'N/A', 'zero-spam' );
				}

				return esc_html( (string) $item[ $column_name ] );
		}
	}

	/**
	 * Bulk actions
	 *
	 * @return array<string, string> Action key => label.
	 */
	public function get_bulk_actions(): array {
		$actions = array(
			'delete'     => __( 'Delete Selected', 'zero-spam' ),
			'delete_all' => __( 'Delete All Logs', 'zero-spam' ),
		);

		return $actions;
	}

	/**
	 * Hidable columns
	 *
	 * @return string[] Hidden column keys.
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Formats a stored block date for a datetime-local field.
	 *
	 * Permanent blocks have no end date: they get an empty value, which the
	 * block form leaves blank, instead of 1970-01-01T00:00.
	 *
	 * @param string|null $value Stored date (Y-m-d H:i:s), or null.
	 * @return string Y-m-d\TH:i, or an empty string.
	 */
	private function datetime_local( ?string $value ): string {
		$timestamp = ! empty( $value ) ? strtotime( $value ) : false;

		return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	/**
	 * Reads a read-only list argument (filter, search, sort order).
	 *
	 * @param string $key Request key.
	 * @return string Sanitized value, or an empty string when absent.
	 */
	private function get_request_arg( string $key ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filtering, searching and sorting, as in core list tables; bulk actions verify their nonce in process_bulk_action().
		if ( ! isset( $_REQUEST[ $key ] ) || ! is_string( $_REQUEST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get the data
	 */
	public function prepare_items(): void {
		global $wpdb;

		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page     = self::PER_PAGE;
		$current_page = $this->get_pagenum();
		$offset       = $per_page * ( $current_page - 1 );

		// Only a sortable column and ASC/DESC can reach the ORDER BY clause.
		$orderby = sanitize_key( $this->get_request_arg( 'orderby' ) );
		if ( ! isset( $sortable[ $orderby ] ) ) {
			$orderby = 'date_recorded';
		}
		$order_column = $sortable[ $orderby ][0];
		$order        = 'asc' === strtolower( $this->get_request_arg( 'order' ) ) ? 'ASC' : 'DESC';

		$log_type = $this->get_request_arg( 'type' );
		$country  = $this->get_request_arg( 'country' );
		$user_ip  = $this->get_request_arg( 's' );

		$database_table = $wpdb->prefix . ZeroSpam\Includes\DB::$tables['log'];

		// Fixed "column = %s" fragments only; the values are bound by prepare().
		$where_clauses = array();
		$where_values  = array();

		if ( $log_type ) {
			$where_clauses[] = '`log_type` = %s';
			$where_values[]  = $log_type;
		}

		if ( $country ) {
			$where_clauses[] = '`country` = %s';
			$where_values[]  = $country;
		}

		if ( $user_ip ) {
			$where_clauses[] = '`user_ip` = %s';
			$where_values[]  = $user_ip;
		}

		$where_statement = $where_clauses ? ' WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$count_values    = array_merge( array( $database_table ), $where_values );
		$page_values     = array_merge( $count_values, array( $order_column, $per_page, $offset ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paged admin list of the plugin's own table; it changes with every detection.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where_statement only holds the fixed fragments above (WPCS cannot count their placeholders) and $order is ASC or DESC; every value is a placeholder.

		// The log ID breaks ties, so rows with equal sort values never repeat or go missing between pages.
		$data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i{$where_statement} ORDER BY %i {$order}, `log_id` {$order} LIMIT %d OFFSET %d",
				$page_values
			),
			ARRAY_A
		);

		if ( ! $data ) {
			return;
		}

		$total_items = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i{$where_statement}",
				$count_values
			)
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;

		// Carry POSTed filters into the pagination and sorting links, under
		// the keys this method reads.
		$paging_options = array();
		if ( $log_type ) {
			$paging_options['type'] = $log_type;
		}

		if ( $country ) {
			$paging_options['country'] = $country;
		}

		if ( $user_ip ) {
			$paging_options['s'] = $user_ip;
		}

		if ( $paging_options && isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = add_query_arg( $paging_options, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}
	}

	/**
	 * Add more filters
	 *
	 * @param string $which Table navigation position (top or bottom).
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$options         = apply_filters( 'zerospam_types', array() );
		$current_type    = $this->get_request_arg( 'type' );
		$current_country = $this->get_request_arg( 'country' );
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="filter-by-type"><?php esc_html_e( 'Filter by type', 'zero-spam' ); ?></label>
			<select name="type" id="filter-by-type">
				<option value=""><?php esc_html_e( 'All types', 'zero-spam' ); ?></option>
				<?php foreach ( $options as $key => $value ) : ?>
					<option <?php selected( $current_type, (string) $key ); ?> value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value['label'] ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="filter-by-country"><?php esc_html_e( 'Filter by country', 'zero-spam' ); ?></label>
			<select name="country" id="filter-by-country">
				<option value=""><?php esc_html_e( 'All countries', 'zero-spam' ); ?></option>
				<?php foreach ( ZeroSpam\Core\Utilities::countries() as $key => $value ) : ?>
					<option <?php selected( $current_country, (string) $key ); ?> value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'zero-spam' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Process bulk actions
	 *
	 * Both actions need the form nonce, the manage_options capability and at
	 * least one selected row.
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();
		if ( 'delete' !== $action && 'delete_all' !== $action ) {
			return;
		}

		$nonce = isset( $_REQUEST['zerospam_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['zerospam_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zerospam_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// sanitize_text_field() turns non-scalar entries (ids[][]=x) into '', which
		// absint() makes 0 and array_filter() drops; absint() alone would turn an
		// array into 1.
		$ids = isset( $_REQUEST['ids'] ) && is_array( $_REQUEST['ids'] ) ? array_filter( array_map( 'absint', array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['ids'] ) ) ) ) : array();
		if ( ! $ids ) {
			return;
		}

		if ( 'delete_all' === $action ) {
			ZeroSpam\Includes\DB::delete_all( 'log' );
			return;
		}

		// DB::delete() takes the key value as a string.
		foreach ( $ids as $log_id ) {
			ZeroSpam\Includes\DB::delete( 'log', 'log_id', (string) $log_id );
		}
	}
}
