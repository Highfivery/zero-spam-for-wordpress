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

  /**
   * Define which columns are hidden
   *
   * @return Array
   */
  public function get_hidden_columns() {
    return [];
  }

  /**
   * Allows you to sort the data by the variables set in the $_GET
   *
   * @return Mixed
   */
  private function sort_data( $a, $b ) {
    // Set defaults
    $orderby = 'date_recorded';
    $order   = 'desc';

    // If orderby is set, use this as the sort column
    if( ! empty( $_GET['orderby'] ) ) {
      $orderby = $_GET['orderby'];
    }

    // If order is set use this as the order
    if ( ! empty($_GET['order'] ) ) {
      $order = $_GET['order'];
    }

    $result = strcmp( $a->$orderby, $b->$orderby );

    if ( $order === 'asc' ) {
      return $result;
    }

    return -$result;
  }

  // Get results
  function prepare_items($args = []) {
    $this->process_bulk_action();

    $columns  = $this->get_columns();
    $hidden   = $this->get_hidden_columns();
    $sortable = $this->get_sortable_columns();

    $data = wpzerospam_get_log();
    usort( $data, [ &$this, 'sort_data' ] );

    $per_page     = 50;
    $current_page = $this->get_pagenum();
    $total_items  = count( $data );

    $this->set_pagination_args([
      'total_items' => $total_items,
      'per_page'    => $per_page
    ]);

    $data = array_slice ( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

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
        // Delete query
        $nonce = ( isset( $_POST['wpzerospam_nonce'] ) ) ? $_POST['wpzerospam_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wpzerospam_nonce' ) ) return false;

        if ( ! empty ( $ids ) && is_array( $ids ) ) {
          // Delete query
          foreach( $ids as $k => $log_id ) {
            $wpdb->delete( wpzerospam_tables( 'log' ), [ 'log_id' => $log_id  ] );
          }
        }
      break;
    }
  }
}
