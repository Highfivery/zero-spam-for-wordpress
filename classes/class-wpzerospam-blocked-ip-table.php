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
      'singular'  => __( 'Blocked IP', 'zero-spam' ),
      'plural'    => __( 'Blocked IPs', 'zero-spam' ),
      'ajax'      => true
    ];
    parent::__construct( $args );
  }

  // Register columns
  function get_columns() {
    // Render a checkbox instead of text
    $columns = [
      'cb'           => '<input type="checkbox" />',
      'user_ip'      => __( 'IP Address', 'zero-spam' ),
      'blocked_type' => __( 'Type', 'zero-spam' ),
      'date_added'   => __( 'Date Added', 'zero-spam' ),
      'start_block'  => __( 'Start Date', 'zero-spam' ),
      'end_block'    => __( 'End Date', 'zero-spam' ),
      'reason'       => __( 'Reason', 'zero-spam' ),
      'attempts'     => __( 'Attempts', 'zero-spam' ),
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
      echo '<label class="screen-reader-text" for="filter-by-type">' . __( 'Filter by type', 'zero-spam' ) . '</label>';
      $current_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
      ?>
      <select name="type" id="filter-by-type">
        <option value=""><?php _e( 'All types', 'zero-spam' ); ?></option>
        <option<?php if ( $current_type == 'permanent' ): ?> selected="selected" <?php endif; ?> value="permanent"><?php _e( 'Permanent', 'zero-spam' ); ?></option>
        <option<?php if ( $current_type == 'temporary' ): ?> selected="selected" <?php endif; ?> value="temporary"><?php _e( 'Temporary', 'zero-spam' ); ?></option>
      </select>
      <?php
      submit_button( __( 'Filter', 'zero-spam' ), '', 'filter_action', false );
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
        return '<a href="https://zerospam.org/ip-lookup/' . urlencode( $item->user_ip ) .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>';
      break;
      case 'blocked_id':
        return $item->blocked_id;
      break;
      case 'blocked_type':
        switch( $item->blocked_type ) {
          case 'permanent':
            return '<span class="wpzerospam-blocked">' . __( 'Permanent', 'zero-spam' ) . '</span>';
          break;
          case 'temporary':
            return '<span class="wpzerospam-warning">' . __( 'Temporary', 'zero-spam' ) . '</span>';
          break;
          default:
            return $item->blocked_type;
        }
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

        switch( $item->reason ) {
          case 'comment (permanently auto-blocked)':
            return sprintf(
              wp_kses(
                __( 'Comment <span class="wpzerospam-small">(<strong>permanently</strong> auto-blocked)</span>', 'zero-spam' ),
                [ 'span' => [ 'class' => [] ], 'strong' => [] ]
              )
            );
          break;
          case 'comment (auto-blocked)':
            return sprintf(
              wp_kses(
                __( 'Comment <span class="wpzerospam-small">(auto-blocked)</span>', 'zero-spam' ),
                [ 'span' => [ 'class' => [] ] ]
              )
            );
          break;
          default:
            return $item->reason;
        }
      break;
      case 'attempts':
        return $item->attempts;
      break;
    }
  }

  // Register bulk actions
  function get_bulk_actions() {
    $actions = [
      'delete'     => __( 'Delete', 'zero-spam' ),
      'delete_all' => __( 'Delete All Entries', 'zero-spam' )
    ];

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

    $user_ip      = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false;
    $blocked_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;

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

    // Set the $_SERVER['REQUEST_URI'] for paging
    wpzerospam_set_list_table_request_uri( $query_args );

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
        $nonce = ( isset( $_REQUEST['wpzerospam_nonce'] ) ) ? $_REQUEST['wpzerospam_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wpzerospam_nonce' ) ) return false;

        if ( ! empty ( $ids ) && is_array( $ids ) ) {
          // Delete query
          foreach( $ids as $k => $blocked_id ) {
            $wpdb->delete( wpzerospam_tables( 'blocked' ), [ 'blocked_id' => $blocked_id  ] );
          }
        }
      break;
      case 'delete_all':
        $wpdb->query( "TRUNCATE TABLE " . wpzerospam_tables( 'blocked' ) );
      break;
    }
  }
}
