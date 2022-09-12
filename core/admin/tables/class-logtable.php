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
	 * Constructor
	 */
	public function __construct() {
		global $status, $page;

		$args = array(
			'singular' => __( 'Zero Spam for WordPress Log', 'zero-spam' ),
			'plural'   => __( 'Zero Spam for WordPress Logs', 'zero-spam' ),
		);
		parent::__construct( $args );
	}

	/**
	 * Define table columns
	 */
	public function get_columns() {
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
	 */
	public function get_sortable_columns() {
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
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ 'ids',
			/*$2%s*/ $item['log_id']
		);
	}

	/**
	 * Column values
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'log_type':
				$value = ! empty( $item[ $column_name ] ) ? $item[ $column_name ] : false;
				if ( ! $value ) {
					return 'N/A';
				}

				$type = '<span class="zerospam-type-' . $value . '">';

				$types = apply_filters( 'zerospam_types', array() );
				if ( ! empty( $types[ $value ] ) ) {
					$type .= $types[ $value ]['label'];
				} else {
					$type .= $value;
				}

				$type .= '</span>';

				return $type;
				break;
			case 'user_ip':
				return '<a href="' . ZEROSPAM_URL . 'ip-lookup/' . urlencode( $item[ $column_name ] ) .'" target="_blank" rel="noopener noreferrer">' . $item[ $column_name ] . '</a>';
				break;
			case 'date_recorded':
				$date_time_format = 'm/d/Y g:ia';
				return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $item[ $column_name ] ) ), $date_time_format );
				break;
			case 'actions':
				ob_start();
				?>
				<button class="button zerospam-details-trigger" data-id="<?php echo esc_attr( $item['log_id'] ); ?>" aria-label="<?php esc_html_e( 'Details', 'zero-spam' ); ?>"><img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-info.svg" width="13" /></button>
				<div class="zerospam-modal" id="zerospam-details-<?php echo esc_attr( $item['log_id'] ); ?>">
					<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zero-spam' ) ); ?>"></button>

					<div class="zerospam-block zerospam-block--list">
						<h3 class="zerospam-block__headline"><?php esc_html_e( 'Detection ID #', 'zero-spam' ); ?><?php echo esc_attr( $item['log_id'] ); ?></h3>
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
						data-start="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $blocked['start_block'] ) ) ); ?>T<?php echo esc_attr( gmdate( 'H:i', strtotime( $blocked['start_block'] ) ) ); ?>"
						data-end="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $blocked['end_block'] ) ) ); ?>T<?php echo esc_attr( gmdate( 'H:i', strtotime( $blocked['end_block'] ) ) ); ?>"
						data-type="<?php echo esc_attr( $blocked['blocked_type'] ); ?>"
						aria-label="<?php esc_html_e( 'Update Block', 'zero-spam' ); ?>"
					>
						<img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-edit.svg" width="13" />
					</button>
					<?php
				else :
					?>
					<button class="button zerospam-block-trigger" data-ip="<?php echo esc_attr( $item['user_ip'] ); ?>" aria-label="<?php esc_html_e( 'Block IP', 'zero-spam' ); ?>"><img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-blocked.svg" width="13" /></button>
					<?php
				endif;

				return ob_get_clean();
				break;
			case 'country':
				if ( ! empty( $item[ $column_name ] ) ) {
					$country_name = ! empty( $item['country_name'] ) ? $item['country_name'] : false;
					$flag         = ZeroSpam\Core\Utilities::country_flag_url( $item[ $column_name ] );

					$return = '<img src="' . $flag. '" width="16" height="16" alt="' . esc_attr( $country_name . ' (' . $item[ $column_name ] . ')' ) . '" class="zerospam-flag" />';
					if ( $country_name ) {
						$return .= $country_name . ' (' . $item[ $column_name ] . ')';
					} else {
						$return .= $item[ $column_name ];
					}

					return $return;
				}
				return 'N/A';
				break;
			default:
				if ( empty( $item[ $column_name ] ) ) {
					return 'N/A';
				} else {
					return $item[ $column_name ];
				}
		}
	}

	/**
	 * Bulk actions
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete'     => __( 'Delete Selected', 'zero-spam' ),
			'delete_all' => __( 'Delete All Logs', 'zero-spam' ),
		);

		return $actions;
	}

	/**
	 * Hidable columns
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * Get the data
	 */
	public function prepare_items() {
		global $wpdb;

		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = 1 === $current_page ? false : $per_page * $current_page;
		// @codingStandardsIgnoreLine
		$order = ! empty( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		// @codingStandardsIgnoreLine
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'date_recorded';

		// @codingStandardsIgnoreLine
		$log_type   = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : false;
		// @codingStandardsIgnoreLine
		$country    = ! empty( $_REQUEST['country'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['country'] ) ) : false;
		// @codingStandardsIgnoreLine
		$user_ip    = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : false;

		// Define the database table.
		$database_table = $wpdb->prefix . \ZeroSpam\Includes\DB::$tables['log'];

		// Prepare the select statements.
		$select_array = array( '*' );

		// Order & add extra select statements.
		switch ( $orderby ) {
			case 'user_ip':
				$order_statement = "ORDER BY user_ip $order";
				break;
			case 'country':
				$order_statement = "ORDER BY country $order";
				break;
			case 'region':
				$order_statement = "ORDER BY country $order";
				break;
			case 'date_recorded':
				$order_statement = "ORDER BY date_recorded $order";
				break;
			case 'log_type':
				$order_statement = "ORDER BY log_type $order";
				break;
		}

		// Where.
		$where_array = array();

		if ( $log_type ) {
			$where_array[] = "log_type = '$log_type'";
		}

		if ( $country ) {
			$where_array[] = "country = '$country'";
		}

		if ( $user_ip ) {
			$where_array[] = "user_ip = '$user_ip'";
		}

		// Limit.
		$limit_statement = "LIMIT $per_page";
		if ( $offset ) {
			$limit_statement .= ", $offset";
		}

		// Create the query.
		$database_query = 'SELECT ';

		$select_statement = implode( ', ', $select_array );
		$database_query  .= $select_statement . ' ';

		$database_query .= "FROM $database_table ";

		if ( $where_array ) {
			$database_query .= 'WHERE ';
			$database_query .= implode( ' AND ', $where_array );
		}

		if ( ! empty( $order_statement ) ) {
			$database_query .= $order_statement . ' ';
		}

		$database_query .= $limit_statement;

		// @codingStandardsIgnoreLine
		$data = $wpdb->get_results( $database_query, ARRAY_A );

		if ( ! $data ) {
			return false;
		}

		// Get total number of rows.
		$count_query = "SELECT COUNT(*) FROM $database_table ";

		if ( $where_array ) {
			$count_query .= 'WHERE ';
			$count_query .= implode( ' AND ', $where_array );
		}

		// @codingStandardsIgnoreLine
		$total_items = $wpdb->get_var( $count_query );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
				'orderby'     => $orderby,
				'order'       => $order,
			)
		);

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;

		$paging_options = array();
		if ( $log_type ) {
			$paging_options['log_type'] = $log_type;
		}

		if ( $country ) {
			$paging_options['country'] = $country;
		}

		if ( $user_ip ) {
			$paging_options['s'] = $user_ip;
		}
		// @codingStandardsIgnoreLine
		$_SERVER['REQUEST_URI'] = add_query_arg( $paging_options, wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Add more filters
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			echo '<label class="screen-reader-text" for="filter-by-type">' . __( 'Filter by type', 'zero-spam' ) . '</label>';
			$options      = apply_filters( 'zerospam_types', array() );
			$current_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
			?>
			<select name="type" id="filter-by-type">
				<option value=""><?php _e( 'All types', 'zero-spam' ); ?></option>
				<?php foreach ( $options as $key => $value ) : ?>
					<option<?php if ( $current_type === $key ) : ?> selected="selected"<?php endif; ?> value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value['label'] ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php
			echo '<label class="screen-reader-text" for="filter-by-country">' . __( 'Filter by country', 'zero-spam' ) . '</label>';
			$current_country = ! empty( $_REQUEST['country'] ) ? sanitize_text_field( $_REQUEST['country'] ) : false;
			?>
			<select name="country" id="filter-by-country">
				<option value=""><?php _e( 'All countries', 'zero-spam' ); ?></option>
				<?php foreach ( ZeroSpam\Core\Utilities::countries() as $key => $value ) : ?>
					<option<?php if ( $current_country === $key ) : ?> selected="selected"<?php endif; ?> value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			submit_button( __( 'Filter', 'zero-spam' ), '', 'filter_action', false );
			?>
		</div>
		<?php
	 }

	/**
	 * Process bulk actions
	 */
	public function process_bulk_action() {
		global $wpdb;

		$nonce = ( isset( $_REQUEST['zerospam_nonce'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['zerospam_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zerospam_nonce' ) || empty( $_REQUEST['ids'] ) ) {
			return false;
		}

		$ids = array_map( 'sanitize_text_field',  $_REQUEST['ids'] );

		switch ( $this->current_action() ) {
			case 'delete':
				if ( ! empty ( $ids ) && is_array( $ids ) ) {
					foreach ( $ids as $k => $log_id ) {
						\ZeroSpam\Includes\DB::delete( 'log', 'log_id', $log_id );
					}
				}
				break;
			case 'delete_all':
				\ZeroSpam\Includes\DB::delete_all( 'log' );
				break;
		}
	}
}
