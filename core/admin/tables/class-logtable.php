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
 * Log table.
 *
 * @since 5.0.0
 */
class LogTable extends WP_List_Table {

	/**
	 * Log table constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		global $status, $page;

		$args = array(
			'singular' => __( 'WordPress Zero Spam Log', 'zerospam' ),
			'plural'   => __( 'WordPress Zero Spam Logs', 'zerospam' ),
		);
		parent::__construct( $args );
	}

	/**
	 * Column values.
	 *
	 * @since 5.0.0
	 * @access public
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
					$type .= $types[ $value ];
				} else {
					$type .= $value;
				}

				$type .= '</span>';

				return $type;
				break;
			case 'user_ip':
				return '<a href="https://www.zerospam.org/ip-lookup/' . urlencode( $item[ $column_name ] ) .'" target="_blank" rel="noopener noreferrer">' . $item[ $column_name ] . '</a>';
				break;
			case 'date_recorded':
				return date( 'M j, Y g:ia' , strtotime( $item[ $column_name ] ) );
				break;
			case 'details':
				ob_start();
				?>
				<button class="button zerospam-details-trigger" data-id="<?php echo esc_attr( $item['log_id'] ); ?>"><?php _e( 'View', 'zerospam' ); ?></button>
				<div class="zerospam-modal" id="zerospam-details-<?php echo esc_attr( $item['log_id'] ); ?>">
					<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zerospam' ) ); ?>"></button>
					<?php require ZEROSPAM_PATH . 'includes/templates/admin-modal-details.php'; ?>
				</div>
				<?php
				return ob_get_clean();
				break;
			case 'actions':
				ob_start();
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
					>
						<?php _e( 'Update Block', 'zerospam' ); ?>
					</button>
					<?php
				else :
					?>
					<button class="button zerospam-block-trigger" data-ip="<?php echo esc_attr( $item['user_ip'] ); ?>"><?php _e( 'Block IP', 'zerospam' ); ?></button>
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
	 * Bulk actions.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete'     => __( 'Delete Selected', 'zerospam' ),
			'delete_all' => __( 'Delete All Logs', 'zerospam' ),
		);

		return $actions;
	}

	/**
	 * Hidable columns.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * Prepare log items.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function prepare_items( $args = array() ) {
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = $per_page * ( $current_page - 1 );
		$order        = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';
		$orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date_recorded';

		$log_type   = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
		$country    = ! empty( $_REQUEST['country'] ) ? sanitize_text_field( $_REQUEST['country'] ) : false;
		$user_ip    = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false;

		$query_args = array(
			'limit'   => $per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby,
			'where'   => array(),
		);

		if ( $log_type ) {
			$query_args['where']['log_type'] = array(
				'value' => $log_type,
			);
		}

		if ( $user_ip ) {
			$query_args['where']['user_ip'] = array(
				'value' => $user_ip,
			);
		}

		if ( $country ) {
			$query_args['where']['country'] = array(
				'value' => $country,
			);
		}

		$data = ZeroSpam\Includes\DB::query( 'log', $query_args );
		if ( ! $data ) {
			return false;
		}

		$this->items = $data;

		unset( $query_args['limit'] );
		unset( $query_args['offset'] );
		$data = ZeroSpam\Includes\DB::query( 'log', $query_args );
		$total_items = count( $data );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages'	=> ceil( $total_items / $per_page ),
				'orderby'	    => $orderby,
				'order'		    => $order,
			)
		);

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$paging_options = array();
		if ( ! empty( $query_args['where'] ) ) {
			foreach ( $query_args['where'] as $key => $value ) {
				switch( $key ) {
					case 'log_type':
						$paging_options['type'] = $value['value'];
						break;
					case 'user_ip':
						$paging_options['s'] = $value['value'];
						break;
				}
			}
		}

		$_SERVER['REQUEST_URI'] = add_query_arg( $paging_options, $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Add more filters.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			echo '<label class="screen-reader-text" for="filter-by-type">' . __( 'Filter by type', 'zerospam' ) . '</label>';
			$options      = apply_filters( 'zerospam_types', array() );
			$current_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
			?>
			<select name="type" id="filter-by-type">
				<option value=""><?php _e( 'All types', 'zerospam' ); ?></option>
				<?php foreach ( $options as $key => $value ) : ?>
					<option<?php if ( $current_type === $key ) : ?> selected="selected"<?php endif; ?> value="<?php echo esc_attr( $key ); ?>"><?php echo $value; ?></option>
				<?php endforeach; ?>
			</select>

			<?php
			echo '<label class="screen-reader-text" for="filter-by-country">' . __( 'Filter by country', 'zerospam' ) . '</label>';
			$current_country = ! empty( $_REQUEST['country'] ) ? sanitize_text_field( $_REQUEST['country'] ) : false;
			?>
			<select name="country" id="filter-by-country">
				<option value=""><?php _e( 'All countries', 'zerospam' ); ?></option>
				<?php foreach ( ZeroSpam\Core\Utilities::countries() as $key => $value ) : ?>
					<option<?php if ( $current_country === $key ) : ?> selected="selected"<?php endif; ?> value="<?php echo esc_attr( $key ); ?>"><?php echo $value; ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			submit_button( __( 'Filter', 'zerospam' ), '', 'filter_action', false );
			?>
		</div>
		<?php
	 }

	/**
	 * Define table columns.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_columns() {
		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'date_recorded' => __( 'Date', 'zerospam' ),
			'log_type'      => __( 'Type', 'zerospam' ),
			'user_ip'       => __( 'IP Address', 'zerospam' ),
			'country'       => __( 'Country', 'zerospam' ),
			'region'        => __( 'Region', 'zerospam' ),
			'city'          => __( 'City', 'zerospam' ),
			'details'       => __( 'Details', 'zerospam' ),
			'actions'       => __( 'Actions', 'zerospam' ),
		);

		return $columns;
	}

	/**
	 * Sortable columns.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'date_recorded' => array( 'date_recorded', false ),
			'log_type'      => array( 'log_type', false ),
			'user_ip'       => array( 'user_ip', false ),
			'country'       => array( 'country', false ),
			'region'        => array( 'region', false ),
			'city'          => array( 'city', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Column contact.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ 'ids',
			/*$2%s*/ $item['log_id']
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function process_bulk_action() {
		global $wpdb;

		$ids = ( isset( $_REQUEST['ids'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ) : '';

		switch ( $this->current_action() ) {
			case 'delete':
				$nonce = ( isset( $_REQUEST['zerospam_nonce'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['zerospam_nonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'zerospam_nonce' ) ) {
					return false;
				}

				if ( ! empty ( $ids ) && is_array( $ids ) ) {
					foreach ( $ids as $k => $log_id ) {
						ZeroSpam\Includes\DB::delete( 'log', 'log_id', $log_id );
					}
				}
				break;
			case 'delete_all':
				ZeroSpam\Includes\DB::delete_all( 'log' );
				break;
		}
	}
}
