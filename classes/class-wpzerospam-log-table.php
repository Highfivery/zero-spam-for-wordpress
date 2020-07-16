<?php
/**
 * WordPress Zero Spam log table
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_List_Table' ) ) {
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPZeroSpam_Log_Table extends WP_List_Table {
  function __construct() {
    global $status, $page;

    $args = [
      'singular'  => __( 'Spam Detection', 'wpzerospam' ),
      'plural'    => __( 'Spam Detections', 'wpzerospam' ),
      'ajax'      => true
    ];
    parent::__construct( $args );
  }

  // Register columns
  function get_columns() {
    // Render a checkbox instead of text
    $columns = [
      'cb'            => '<input type="checkbox" />',
      'date_recorded' => __( 'Date', 'wpzerospam' ),
      'log_type'      => __( 'Type', 'wpzerospam' ),
      'user_ip'       => __( 'IP Address', 'wpzerospam' ),
      'page_url'      => __( 'Page URL', 'wpzerospam' ),
      'country'       => __( 'Country', 'wpzerospam' ),
      'region'        => __( 'Region', 'wpzerospam' ),
      'city'          => __( 'City', 'wpzerospam' ),
    ];

    return $columns;
  }

  // Sortable columns
  function get_sortable_columns() {
    $sortable_columns = [
      'date_recorded' => [ 'date_recorded', false ],
      'log_type'      => [ 'log_type', false ],
      'user_ip'       => [ 'user_ip', false ],
      'page_url'      => [ 'page_url', false ],
      'country'       => [ 'country', false ],
      'region'        => [ 'region', false ],
      'city'          => [ 'city', false ],
    ];

    return $sortable_columns;
  }

  // Checkbox column
  function column_cb( $item ){
    return sprintf(
        '<input type="checkbox" name="%1$s[]" value="%2$s" />',
        /*$1%s*/ 'ids',
        /*$2%s*/ $item->log_id
    );
  }

  // Render column
  function column_default( $item, $column_name ) {
    switch( $column_name ) {
      case 'log_id':
        return $item->log_id;
      break;
      case 'log_type':
        return $item->log_type;
      break;
      case 'user_ip':
        return '<a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>';
      break;
      case 'date_recorded':
        return date( 'M j, Y g:ia' , strtotime( $item->date_recorded ) );
      break;
      case 'page_url':
        return $item->page_url;
      break;
      case 'country':
        if ( ! $item->country ) {
          return 'N/A';
        }
        return $item->country;
      break;
      case 'region':
        if ( ! $item->region ) {
          return 'N/A';
        }
        return $item->region;
      break;
      case 'city':
        if ( ! $item->city ) {
          return 'N/A';
        }
        return $item->city;
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

    $this->items = wpzerospam_get_log();

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
        // Delete query
        $nonce = ( isset( $_POST['wpzerospam_nonce'] ) ) ? $_POST['wpzerospam_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wpzerospam_nonce' ) ) return false;

        if ( ! empty ( $ids ) ) {
          // Delete query
          foreach( $ids as $k => $log_id ) {
            $wpdb->delete( wpzerospam_tables( 'log' ), [ 'log_id' => $log_id  ] );
          }
        }
      break;
    }
  }
}
