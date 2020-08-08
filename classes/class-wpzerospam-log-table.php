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
      'singular'  => __( 'Spam Detection', 'zero-spam' ),
      'plural'    => __( 'Spam Detections', 'zero-spam' ),
      'ajax'      => true
    ];
    parent::__construct( $args );
  }

  // Register columns
  function get_columns() {
    // Render a checkbox instead of text
    $columns = [
      'cb'            => '<input type="checkbox" />',
      'date_recorded' => __( 'Date', 'zero-spam' ),
      'log_type'      => __( 'Type', 'zero-spam' ),
      'user_ip'       => __( 'IP Address', 'zero-spam' ),
      'country'       => __( 'Country', 'zero-spam' ),
      'region'        => __( 'Region', 'zero-spam' ),
      'city'          => __( 'City', 'zero-spam' ),
      'details'       => __( 'Details', 'zero-spam' ),
      'actions'       => __( 'Block IP', 'zero-spam' ),
    ];

    return $columns;
  }

  // Sortable columns
  function get_sortable_columns() {
    $sortable_columns = [
      'date_recorded' => [ 'date_recorded', false ],
      'log_type'      => [ 'log_type', false ],
      'user_ip'       => [ 'user_ip', false ],
      'country'       => [ 'country', false ],
      'region'        => [ 'region', false ],
      'city'          => [ 'city', false ],
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
      $options      = wpzerospam_types();
      $current_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
      ?>
      <select name="type" id="filter-by-type">
        <option value=""><?php _e( 'All types', 'zero-spam' ); ?></option>
        <?php foreach( $options as $key => $value ): ?>
          <option<?php if ( $current_type == $key ): ?> selected="selected" <?php endif; ?> value="<?php echo $key; ?>"><?php echo $value; ?></option>
        <?php endforeach; ?>
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
        /*$2%s*/ $item->log_id
    );
  }

  // Render column
  function column_default( $item, $column_name ) {
    switch( $column_name ) {
      case 'actions':
        if ( wpzerospam_is_blocked( $item->user_ip ) ) {
          return '<span class="wpzerospam-blocked">' . __( 'Blocked', 'zero-spam' ) . '</span>';
        } else {
          return '<a class="button" href="' . admin_url( 'admin.php?page=wordpress-zero-spam-blocked-ips&ip=' . $item->user_ip ) . '">' . __( 'Block IP', 'zero-spam' ) . '</a>';
        }
      break;
      case 'log_id':
        return $item->log_id;
      break;
      case 'log_type':
        return '<span class="wpzerospam-' . $item->log_type . '">' . wpzerospam_types( $item->log_type ) . '</span>';
      break;
      case 'user_ip':
        return '<a href="https://zerospam.org/ip-lookup/' . urlencode( $item->user_ip ) .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>';
      break;
      case 'date_recorded':
        return date( 'M j, Y g:ia' , strtotime( $item->date_recorded ) );
      break;
      case 'country':
        if ( ! $item->country ) {
          return 'N/A';
        }

        return '<img class="wpzerospam-country-flag" width="16" src="https://hatscripts.github.io/circle-flags/flags/' . strtolower( $item->country ) . '.svg" alt="' . wpzerospam_get_location( $item->country ) .'" /> ' . wpzerospam_get_location( $item->country );
      break;
      case 'region':
        $region = wpzerospam_get_location( $item->country, $item->region );
        if ( $region ) {
          return $region;
        } else if ( ! empty( $item->region) ) {
          return $item->region;
        }

        return 'N/A';
      break;
      case 'city':
        if ( ! $item->city ) {
          return 'N/A';
        }
        return $item->city;
      break;
      case 'details':
        if ( empty( $item->submission_data ) ) { return __( 'No details available.', 'zero-spam' ); }
        ob_start();
        ?>
        <button class="button action wpzerospam-details-trigger" data-id="<?php echo $item->log_id; ?>"><?php _e( 'View', 'zero-spam' ); ?></button>
        <div class="wpzerospam-details-modal" id="wpzerospam-details-modal-<?php echo $item->log_id; ?>">
          <div class="wpzerospam-details-modal-inner">
            <?php
            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Detected Spam IP', 'zero-spam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . '<a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a></div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Page URL', 'zero-spam' ) . '</div>';
            echo '<div class="wpzerospam-details-data"><a href="' . esc_url( $item->page_url ) . '" target="_blank" rel="noreferrer noopener">' . $item->page_url . '</a></div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Date', 'zero-spam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . date( 'M j, Y g:ia' , strtotime( $item->date_recorded ) ) . '</div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Type', 'zero-spam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . wpzerospam_types( $item->log_type ) . '</div>';
            echo '</div>';

            if ( $item->country ) {
              echo '<div class="wpzerospam-details-item">';
              echo '<div class="wpzerospam-details-label">' . __( 'Country', 'zero-spam' ) . '</div>';
              echo '<div class="wpzerospam-details-data">' . wpzerospam_get_location( $item->country ) . '</div>';
              echo '</div>';
            }

            if ( $item->region ) {
              echo '<div class="wpzerospam-details-item">';
              echo '<div class="wpzerospam-details-label">' . __( 'Region', 'zero-spam' ) . '</div>';
              echo '<div class="wpzerospam-details-data">' . wpzerospam_get_location( $item->country, $item->region ) . '</div>';
              echo '</div>';
            }

            if ( $item->city ) {
              echo '<div class="wpzerospam-details-item">';
              echo '<div class="wpzerospam-details-label">' . __( 'City', 'zero-spam' ) . '</div>';
              echo '<div class="wpzerospam-details-data">' . $item->city . '</div>';
              echo '</div>';
            }

            if ( ! empty( $item->submission_data ) ) {
              $submission_data = json_decode( $item->submission_data, true );

              foreach( $submission_data as $key => $value ):
                if ( ! $value ) { continue; }

                do_action( 'wpzerospam_admin_submission_data_items', $key, $value );

                switch( $key ):
                  case 'reason':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Reason', 'zero-spam' ) . '</div>';
                    echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                    echo '</div>';
                  break;

                  // Formidable fields
                  case 'frm_action':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Form Action', 'zero-spam' ) . '</div>';
                    echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                    echo '</div>';
                  break;
                  case 'form_id':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Form ID', 'zero-spam' ) . '</div>';
                    echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                    echo '</div>';
                  break;
                  case 'form_key':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Form Key', 'zero-spam' ) . '</div>';
                    echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                    echo '</div>';
                  break;
                  case 'item_key':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Item Key', 'zero-spam' ) . '</div>';
                    echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                    echo '</div>';
                  break;
                  case 'item_meta':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Form Values', 'zero-spam' ) . '</div>';
                    if ( is_array( $value ) ) {
                      echo '<div class="wpzerospam-details-data">' . implode( ", ", array_filter( $value ) ) . '</div>';
                    } else {
                      echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                    }
                    echo '</div>';
                  break;
                  case '_wp_http_referer':
                    echo '<div class="wpzerospam-details-item">';
                    echo '<div class="wpzerospam-details-label">' . __( 'Source', 'zero-spam' ) . '</div>';
                    if ( $value ) {
                      $source_url = esc_url( site_url( $value ) );
                      echo '<div class="wpzerospam-details-data"><a href="' . $source_url . '" target="_blank" rel="noopener noreferrer">' . $source_url . '</a></div>';
                    } else {
                      echo '<div class="wpzerospam-details-data">N/A</div>';
                    }
                    echo '</div>';
                  break;

                  default:
                    $defined_submission_data = apply_filters( 'wpzerospam_defined_submission_data', [] );
                    if ( ! in_array( $key, $defined_submission_data ) ) {
                      echo '<div class="wpzerospam-details-item wpzerospam-details-item-unknown">';
                      echo '<div class="wpzerospam-details-label">' . $key . '</div>';
                      echo '<div class="wpzerospam-details-data">' . json_encode( $value ) . '</div>';
                      echo '</div>';
                    }
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
    $actions = [
      'delete'     => __( 'Delete Selected', 'zero-spam' ) ,
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
    $orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date_recorded';

    $log_type = ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : false;
    $user_ip  = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false;

    $query_args = [
      'limit'   => $per_page,
      'offset'  => $offset,
      'order'   => $order,
      'orderby' => $orderby
    ];

    if ( $log_type || $user_ip ) {
      $query_args['where'] = [];

      if ( $log_type ) {
        $query_args['where']['log_type'] = $log_type;
      }

      if ( $user_ip ) {
        $query_args['where']['user_ip'] = $user_ip;
      }
    }

    $data = wpzerospam_query( 'log', $query_args );
    if ( ! $data ) { return false; }

    // Set the $_SERVER['REQUEST_URI'] for paging
    wpzerospam_set_list_table_request_uri( $query_args );

    $total_items = wpzerospam_query( 'log', $query_args, true );

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
        // Delete query
        $nonce = ( isset( $_REQUEST['wpzerospam_nonce'] ) ) ? $_REQUEST['wpzerospam_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wpzerospam_nonce' ) ) return false;

        if ( ! empty ( $ids ) && is_array( $ids ) ) {
          // Delete query
          foreach( $ids as $k => $log_id ) {
            $wpdb->delete( wpzerospam_tables( 'log' ), [ 'log_id' => $log_id  ] );
          }
        }
      break;
      case 'delete_all':
        $wpdb->query( "TRUNCATE TABLE " . wpzerospam_tables( 'log' ) );
      break;
    }
  }
}
