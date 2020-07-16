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
        if ( '0000-00-00 00:00:00' == $item->start_block ) {
          $item->start_block = 'N/A';
        }

        return $item->start_block;
      break;
      case 'end_block':
        if ( '0000-00-00 00:00:00' == $item->end_block ) {
          $item->end_block = 'N/A';
        }

        return $item->end_block;
      break;
      case 'reason':
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

  // Get results
  function prepare_items($args = []) {
    $this->process_bulk_action();

    $columns  = $this->get_columns();
    $sortable = $this->get_sortable_columns();
    $hidden   = [];

    $this->_column_headers = [ $columns, $hidden, $sortable ];

    $current_page = $this->get_pagenum() ? $this->get_pagenum() : 1;
    $paged = ( isset( $_REQUEST['page'])) ? $_REQUEST['page'] : $current_page;
    $paged = ( isset( $_REQUEST['paged'])) ? $_REQUEST['paged'] : $current_page;
    $paged = ( isset( $args['paged'] ) ) ? $args['paged'] : $paged;

    $per_page = ( isset( $args['per_page'] ) ) ? $args['per_page'] : 500;
    $orderby  = ( isset( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'id';
    $order    = ( isset( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';

    $this->items = wpzerospam_get_blocked_ips();

    // Set pagniation args
    $pagination_args = [
      'total_items' => count( $this->items ),
      'per_page'    => $per_page,
      'total_pages' => ceil( count( $this->items ) / $per_page ),
    ];
    $this->set_pagination_args( $pagination_args );
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

        if ( ! empty ( $ids ) ) {
          // Delete query
          foreach( $ids as $k => $blocked_id ) {
            $wpdb->delete( wpzerospam_tables( 'blocked' ), [ 'blocked_id' => $blocked_id  ] );
          }
        }
      break;
    }
  }
}
