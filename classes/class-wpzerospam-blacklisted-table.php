<?php
/**
 * WordPress Zero Spam blacklisted table
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_List_Table' ) ) {
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPZeroSpam_Blacklisted_Table extends WP_List_Table {
  function __construct() {
    global $status, $page;

    $args = [
      'singular'  => __( 'Blacklist', 'wpzerospam' ),
      'plural'    => __( 'Blacklist IPs', 'wpzerospam' ),
      'ajax'      => true
    ];
    parent::__construct( $args );
  }

  // Register columns
  function get_columns() {
    // Render a checkbox instead of text
    $columns = [
      'cb'           => '<input type="checkbox" />',
      'last_updated' => __( 'Last Updated', 'wpzerospam' ),
      'user_ip'      => __( 'IP Address', 'wpzerospam' ),
      'service'      => __( 'Service', 'wpzerospam' ),
      'details'      => __( 'Details', 'wpzerospam' )
    ];

    return $columns;
  }

  // Sortable columns
  function get_sortable_columns() {
    $sortable_columns = [
      'last_updated' => [ 'last_updated', false ],
      'user_ip'      => [ 'user_ip', false ],
      'service'      => [ 'service', false ],
    ];

    return $sortable_columns;
  }

  // Checkbox column
  function column_cb( $item ){
    return sprintf(
        '<input type="checkbox" name="%1$s[]" value="%2$s" />',
        /*$1%s*/ 'ids',
        /*$2%s*/ $item->blacklist_id
    );
  }

  // Render column
  function column_default( $item, $column_name ) {
    switch( $column_name ) {
      case 'service':
        switch( $item->blacklist_service ) {
          case 'stopforumspam':
            return '<a href="https://www.stopforumspam.com/" target="_blank" rel="noopener noreferrer">Stop Forum Spam</a>';
          break;
          case 'botscout':
            return '<a href="https://botscout.com/" target="_blank" rel="noopener noreferrer">BotScout</a>';
          break;
          default:
            return $item->blacklist_service;
        }
      break;
      case 'user_ip':
        return '<a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>';
      break;
      case 'last_updated':
        return date( 'M j, Y g:ia' , strtotime( $item->last_updated ) );
      break;
      case 'details':
        if ( empty( $item->blacklist_data ) ) { return __( 'No details available.', 'wpzerospam' ); }
        ob_start();
        ?>
        <button class="button action wpzerospam-details-trigger" data-id="<?php echo $item->blacklist_id; ?>"><?php _e( 'View Details', 'wpzerospam' ); ?></button>
        <div class="wpzerospam-details-modal" id="wpzerospam-details-modal-<?php echo $item->blacklist_id; ?>">
          <div class="wpzerospam-details-modal-inner">
            <?php
            $item->blacklist_data = json_decode( $item->blacklist_data, true );

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Detected Spam IP', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . '<a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>' . '</div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Last Updated', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . date( 'M j, Y g:ia' , strtotime( $item->last_updated ) ) . '</div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Service', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . $item->blacklist_service . '</div>';
            echo '</div>';

            if ( ! empty( $item->blacklist_data ) ) {
              foreach( $item->blacklist_data as $key => $value ):
                if ( ! $value ) { continue; }
                switch( $key ):
                  default:
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . $key . '</div>';
                    echo '<div class="wpzerospam-details-data">' . json_encode( $value ) . '</div>';
                    echo '</div>';
                endswitch;
              endforeach;
            };
            ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
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
    $orderby = 'last_updated';
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

    $data = wpzerospam_get_blacklist();
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
          foreach( $ids as $k => $blacklist_id ) {
            $wpdb->delete( wpzerospam_tables( 'blacklist' ), [ 'blacklist_id' => $blacklist_id  ] );
          }
        }
      break;
    }
  }
}
