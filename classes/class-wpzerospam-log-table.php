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
      'country'       => __( 'Country', 'wpzerospam' ),
      'region'        => __( 'Region', 'wpzerospam' ),
      'city'          => __( 'City', 'wpzerospam' ),
      'details'       => __( 'Details', 'wpzerospam' ),
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
      case 'country':
        if ( ! $item->country ) {
          return 'N/A';
        }
        return wpzerospam_get_location( $item->country );
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
        if ( empty( $item->submission_data ) ) { return __( 'No details available.', 'wpzerospam' ); }
        ob_start();
        ?>
        <button class="button action wpzerospam-details-trigger" data-id="<?php echo $item->log_id; ?>"><?php _e( 'View Details', 'wpzerospam' ); ?></button>
        <div class="wpzerospam-details-modal" id="wpzerospam-details-modal-<?php echo $item->log_id; ?>">
          <div class="wpzerospam-details-modal-inner">
            <?php
            $item->submission_data = json_decode( $item->submission_data, true );

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Detected Spam IP', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . '<a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $item->user_ip . '</a>' . '</div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Page URL', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . $item->page_url . '</div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Date', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . date( 'M j, Y g:ia' , strtotime( $item->date_recorded ) ) . '</div>';
            echo '</div>';

            echo '<div class="wpzerospam-details-item">';
            echo '<div class="wpzerospam-details-label">' . __( 'Type', 'wpzerospam' ) . '</div>';
            echo '<div class="wpzerospam-details-data">' . $item->log_type . '</div>';
            echo '</div>';

            if ( $item->country ) {
              echo '<div class="wpzerospam-details-item">';
              echo '<div class="wpzerospam-details-label">' . __( 'Country', 'wpzerospam' ) . '</div>';
              echo '<div class="wpzerospam-details-data">' . wpzerospam_get_location( $item->country ) . '</div>';
              echo '</div>';
            }

            if ( $item->region ) {
              echo '<div class="wpzerospam-details-item">';
              echo '<div class="wpzerospam-details-label">' . __( 'Region', 'wpzerospam' ) . '</div>';
              echo '<div class="wpzerospam-details-data">' . wpzerospam_get_location( $item->country, $item->region ) . '</div>';
              echo '</div>';
            }

            if ( $item->city ) {
              echo '<div class="wpzerospam-details-item">';
              echo '<div class="wpzerospam-details-label">' . __( 'City', 'wpzerospam' ) . '</div>';
              echo '<div class="wpzerospam-details-data">' . $item->city . '</div>';
              echo '</div>';
            }

            foreach( $item->submission_data as $key => $value ):
              if ( ! $value ) { continue; }
              switch( $key ):
                case 'comment_post_ID':
                  $post = get_post( $value  );
                  echo '<div class="wpzerospam-details-item">';
                  if ( ! $post ) { echo 'N/A'; } else {
                    echo '<div class="wpzerospam-details-label">' . __( 'Comment Post', 'wpzerospam' ) . '</div>';
                    echo '<div class="wpzerospam-details-data"><a href="' . get_the_permalink( $value ) . '">' . get_the_title( $value ) . '</a></div>';
                  }
                  echo '</div>';
                break;
                case 'comment_author':
                  $author_shown = true;
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Author', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'comment_author_email':
                  $author_email = true;
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Email', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'comment_author_url':
                  $author_url= true;
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Website', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'comment_content':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Comment', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . sanitize_text_field( $value ) . '</div>';
                  echo '</div>';
                break;
                case 'comment_type':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Comment Type', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'comment_parent':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Comment Parent ID', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . '<a href="' . get_comment_link( $value  ) . '">' . $value . '</a>' . '</div>';
                  echo '</div>';
                break;
                case 'comment_as_submitted':
                  foreach( $value as $k => $v ):
                    if ( ! $v ) { continue; }

                    switch( $k ):
                      case 'comment_author':
                        if ( empty( $author_shown ) ) {
                          echo '<div class="wpzerospam-details-item">';
                          echo '<div class="wpzerospam-details-label">' . __( 'Author', 'wpzerospam' ) . '</div>';
                          echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                          echo '</div>';
                        }
                      break;
                      case 'comment_author_email':
                        if ( empty( $author_email ) ) {
                          echo '<div class="wpzerospam-details-item">';
                          echo '<div class="wpzerospam-details-label">' . __( 'Email', 'wpzerospam' ) . '</div>';
                          echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                          echo '</div>';
                        }
                      break;
                      case 'comment_author_url':
                        if ( empty( $author_url ) ) {
                          echo '<div class="wpzerospam-details-item">';
                          echo '<div class="wpzerospam-details-label">' . __( 'Website', 'wpzerospam' ) . '</div>';
                          echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                          echo '</div>';
                        }
                      break;
                      case 'comment_content':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'Comment', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data">' . sanitize_text_field( $v ) . '</div>';
                        echo '</div>';
                      break;
                      case 'user_ip':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'User IP', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data"><a href="https://whatismyipaddress.com/ip/' . $item->user_ip .'" target="_blank" rel="noopener noreferrer">' . $v . '</a></div>';
                        echo '</div>';
                      break;
                      case 'user_agent':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'User Agent', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                        echo '</div>';
                      break;
                      case 'blog':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'Site', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                        echo '</div>';
                      break;
                      case 'blog_lang':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'Site Language', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                        echo '</div>';
                      break;
                      case 'blog_charset':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'Site Charset', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data">' . $v . '</div>';
                        echo '</div>';
                      break;
                      case 'permalink':
                        echo '<div class="wpzerospam-details-item">';
                        echo '<div class="wpzerospam-details-label">' . __( 'Permalink', 'wpzerospam' ) . '</div>';
                        echo '<div class="wpzerospam-details-data">' . '<a href="' . $v . '" target="_blank">' . $v . '</a>' . '</div>';
                        echo '</div>';
                      break;
                      default:
                        echo '<div class="wpzerospam-details-item">';
                        echo $k . ' - ';
                        print_r( $v );
                        echo '</div>';
                    endswitch;
                  endforeach;
                break;
                case 'akismet_result':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Akismet Result', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'akismet_pro_tip':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Akismet Pro Tip', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'sanitized_user_login':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Sanitized User Login', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'user_email':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'User Email', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                case 'errors':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Errors', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . json_encode( $value ) . '</div>';
                  echo '</div>';
                break;
                case 'reason':
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . __( 'Reason', 'wpzerospam' ) . '</div>';
                  echo '<div class="wpzerospam-details-data">' . $value . '</div>';
                  echo '</div>';
                break;
                default:
                  echo '<div class="wpzerospam-details-item">';
                  echo '<div class="wpzerospam-details-label">' . $key . '</div>';
                  echo '<div class="wpzerospam-details-data">' . json_encode( $value ) . '</div>';
                  echo '</div>';
              endswitch;
            endforeach;
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
