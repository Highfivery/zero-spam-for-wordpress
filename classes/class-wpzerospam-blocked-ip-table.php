<?php
/**
 * WordPress Zero Spam blocked IP table
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_List_Table' ) ) {
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPZeroSpam_Blocked_IP_Table extends WP_List_Table {
  function __construct() {
    global $status, $page;

    $args = [
      'singular'  => __( 'Blocked IP', 'wpzerospam' ),
      'plural'    => __( 'Blocked IPs', 'wpzerospam' ),
      'ajax'      => true
    ];
    parent::__construct( $args );
  }

  // Register columns
  function get_columns() {
    // Render a checkbox instead of text
    $columns = [
      'cb'           => '<input type="checkbox" />',
      'user_ip'      => __( 'IP Address', 'wpzerospam' ),
      'blocked_type' => __( 'Type', 'wpzerospam' ),
      'date_added'   => __( 'Date Added', 'wpzerospam' ),
      'start_block'  => __( 'Start Date', 'wpzerospam' ),
      'end_block'    => __( 'End Date', 'wpzerospam' ),
      'reason'       => __( 'Reason', 'wpzerospam' ),
      'attempts'     => __( 'Attempts', 'wpzerospam' ),
    ];

    return $columns;
  }

  // Sortable columns
  function get_sortable_columns() {
    $sortable_columns = [
      'user_ip'      => [ 'user_ip', false ],
      'blocked_type' => [ 'blocked_type', false ],
      'date_added'   => [ 'date_added', false ],
      'start_block'  => [ 'start_block', false ],
      'end_block'    => [ 'end_block', false ],
      'attempts'     => [ 'attempts', false ],
    ];

    return $sortable_columns;
  }

  function extra_tablenav( $which ) {
    global $cat_id;

    if ( 'top' !== $which ) {
      return;
    }
    ?>
    <div class="alignleft actions">
      <?php
      echo '<label class="screen-reader-text" for="filter-by-type">' . __( 'Filter by type' ) . '</label>';
      $current_type = ! empty( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : false;
      ?>
      <select name="type" id="filter-by-type">
        <option value=""><?php _e( 'All types', 'wpzerospam' ); ?></option>
        <option<?php if ( $current_type == 'permanent' ): ?> selected="selected" <?php endif; ?> value="permanent"><?php _e( 'Permanent', 'wpzerospam' ); ?></option>
        <option<?php if ( $current_type == 'temporary' ): ?> selected="selected" <?php endif; ?> value="temporary"><?php _e( 'Temporary', 'wpzerospam' ); ?></option>
      </select>
      <?php
      submit_button( __( 'Filter' ), '', 'filter_action', false );
      ?>
    </div>
    <?php
  }

  // Checkbox column
  function column_cb( $item ){
    return sprintf(
        '<input type="checkbox" name="%1$s[]" value="%2$s" />',
        /*$1%s*/ 'ids',
        /*$2%s*/ $item->blocked_id
    );
  }

  // Render column
  function column_default( $item, $column_name ) {
    switch( $column_name ) {
      case 'user_ip':
        return '<a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>';
      break;
      case 'blocked_id':
        return $item->blocked_id;
      break;
      case 'blocked_type':
        return $item->blocked_type;
      break;
      case 'date_added':
        return date( 'M j, Y g:ia' , strtotime( $item->date_added ) );
      break;
      case 'start_block':
        if ( ! $item->start_block || '0000-00-00 00:00:00' == $item->start_block  || 'permanent' == $item->blocked_type ) {
          return 'N/A';
        }

        return date( 'M j, Y g:ia' , strtotime( $item->start_block ) );
      break;
      case 'end_block':
        if ( ! $item->end_block || '0000-00-00 00:00:00' == $item->end_block || 'permanent' == $item->blocked_type ) {
          return 'N/A';
        }

        return date( 'M j, Y g:ia' , strtotime( $item->end_block ) );
      break;
      case 'reason':
        if ( ! $item->reason ) {
          return 'N/A';
        }

        return $item->reason;
      break;
      case 'attempts':
        return $item->attempts;
      break;
    }
  }

  // Register bulk actions
  function get_bulk_actions() {
    $actions = [ 'delete' => __( 'Delete', 'wpzerospam' ) ];

    return $actions;
  }

  /**
   * Define which columns are hidden
   *
   * @return Array
   */
  public function get_hidden_columns() {
    return [];
  }

  // Get results
  function prepare_items($args = []) {
    $this->process_bulk_action();

    $columns  = $this->get_columns();
    $hidden   = $this->get_hidden_columns();
    $sortable = $this->get_sortable_columns();

    $per_page     = 50;
    $current_page = $this->get_pagenum();
    $offset       = $per_page * ( $current_page - 1 );
    $order        = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';
    $orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date_added';

    $user_ip      = ! empty( $_POST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false;
    $blocked_type = ! empty( $_POST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;

    $query_args = [
      'limit'   => $per_page,
      'offset'  => $offset,
      'order'   => $order,
      'orderby' => $orderby
    ];

    if ( $blocked_type || $user_ip ) {
      $query_args['where'] = [];

      if ( $blocked_type ) {
        $query_args['where']['blocked_type'] = $blocked_type;
      }

      if ( $user_ip ) {
        $query_args['where']['user_ip'] = $user_ip;
      }
    }

    $data = wpzerospam_query( 'blocked', $query_args );
    if ( ! $data ) { return false; }

    $total_items = wpzerospam_query( 'blocked', $query_args, true );

    $this->set_pagination_args([
      'total_items' => $total_items,
      'per_page'    => $per_page,
      'total_pages'	=> ceil( $total_items / $per_page ),
      'orderby'	    => $orderby,
			'order'		    => $order
    ]);

    $this->_column_headers = [ $columns, $hidden, $sortable ];
    $this->items           = $data;
  }

  // Process bulk actions
  function process_bulk_action() {
    global $wpdb;

    $ids = ( isset( $_REQUEST['ids'] ) ) ? $_REQUEST['ids'] : '';

    switch( $this->current_action() ) {
      // Delete
      case 'delete':
        $nonce = ( isset( $_POST['wpzerospam_nonce'] ) ) ? $_POST['wpzerospam_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wpzerospam_nonce' ) ) return false;

        if ( ! empty ( $ids ) && is_array( $ids ) ) {
          // Delete query
          foreach( $ids as $k => $blocked_id ) {
            $wpdb->delete( wpzerospam_tables( 'blocked' ), [ 'blocked_id' => $blocked_id  ] );
          }
        }
      break;
    }
  }
}
