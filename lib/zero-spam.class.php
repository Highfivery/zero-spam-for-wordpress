<?php
/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Zero_Spam {
    /*
     * For easier overriding we declared the keys
     * here as well as our tabs array which is populated
     * when registering settings
     */
    private $settings = array(
        'zerospam_general_settings' => array(),
    );

    private $tabs = array(
        'zerospam_general_settings' => 'General Settings'
    );

    private $plugins = array(
      'cf7' => false
    );

    private $db_version = "1.0.0";

    /**
     * Plugin initilization.
     *
     * Initializes the plugins functionality.
     *
     * @since 1.0.0
     */
    public function __construct() {
      register_activation_hook( __FILE__, array( &$this, 'install' ) );

      $this->_plugin_check();
      $this->_load_settings();
        $this->_actions();
        $this->_filters();
    }

    /**
     * Uses init.
     *
     * Adds WordPress actions using the plugin API.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/init
     */
    public function init() {
        if ( $this->settings['zerospam_general_settings']['log_spammers'] == 'yes' ) {
            $this->tabs['zerospam_spammer_logs'] = 'Spammer Log';
        }
    }

    /**
     * Uses admin_menu.
     *
     * Used to add extra submenus and menu options to the admin panel's menu structure.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu
     */
    public function admin_menu() {
        // Register plugin settings page
        $hook_suffix = add_options_page(
            __( 'Zero Spam Settings', 'zerospam' ),
            __( 'Zero Spam', 'zerospam' ),
            'manage_options',
            'zerospam',
            array( &$this, 'settings_page' )
        );
        // Add styles to hook
        add_action( "load-{$hook_suffix}", array( &$this, 'load_zerospam_settings' ) );
    }

    public function load_zerospam_settings() {
        if ( 'options-general.php' !== $GLOBALS['pagenow'] )
            return;

        wp_enqueue_style( 'zerospam-admin', plugins_url( 'build/css/style.css', __FILE__ ) );
        wp_enqueue_script( 'zerospam-charts', plugins_url( 'build/js/charts.min.js', __FILE__ ), array( 'jquery' ) );
    }

    /*
     * Plugin options page.
     *
     * Rendering goes here, checks for active tab and replaces key with the related
     * settings key. Uses the _options_tabs method to render the tabs.
     *
     * @since 1.5.0
     */
    public function settings_page() {
        $plugin = get_plugin_data( __FILE__ );
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'zerospam_general_settings';
        ?>
        <div class="wrap">
            <h2><?php echo __( 'WordPress Zero Spam', 'zerospam' ); ?></h2>
            <?php $this->_options_tabs(); ?>
            <div class="zerospam__row">
              <div class="zerospam__right">
                <?php require_once( ZEROSPAM_ROOT . 'inc/admin-sidebar.tpl.php' ); ?>
              </div>
              <div class="zerospam__left">
                <?php
                    if (
                        $tab == 'zerospam_spammer_logs' &&
                        $this->settings['zerospam_general_settings']['log_spammers'] == 'yes'
                    ) {
                        $spam = $this->_get_spam();
                        $spam = $this->_parse_spam_ary( $spam );

                        require_once( ZEROSPAM_ROOT . 'inc/spammer-logs.tpl.php' );
                    } else { ?>
                    <div class="zero-spam__widget">
                        <div class="zero-spam__inner">
                            <form method="post" action="options.php">
                                <?php wp_nonce_field( 'zerospam-options' ); ?>
                                <?php settings_fields( $tab ); ?>
                                <?php do_settings_sections( $tab ); ?>
                                <?php submit_button(); ?>
                            </form>
                        </div>
                    </div>
                    <?php } ?>
              </div>

            </div>
        </div>
        <?php
    }

    private function _plugin_check() {
      // Contact From 7 support
      if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
        $this->plugins['cf7'] = true;
    }
    }

    /**
     * Parses the spammer ary from the DB
     *
     * @since 1.5.0
     */
    private function _parse_spam_ary( $ary ) {
        $return = array(
            'by_date' => array(),
            'raw' => $ary,
            'comment_spam' => 0,
            'registration_spam' => 0,
            'cf7_spam' => 0,
            'unique_spammers' => array(),
        );

        foreach( $ary as $key => $obj ) {
            // By date
            if ( ! isset( $return['by_date'][ substr( $obj->date, 0, 10) ] ) ) {
                $return['by_date'][ substr( $obj->date, 0, 10) ] = array(
                    'data' => array(),
                    'comment_spam' => 0,
                    'registration_spam' => 0,
                    'cf7_spam' => 0
                );
            }

            // By date
            $return['by_date'][ substr( $obj->date, 0, 10) ]['data'][] = array(
                'zerospam_id' => $obj->zerospam_id,
                'type' => $obj->type,
                'ip' => $obj->ip,
                'date' => $obj->date
            );

            // Spam type
            if ( $obj->type == 1 ) {

                // Registration spam
                $return['by_date'][ substr( $obj->date, 0, 10) ]['registration_spam']++;
                $return['registration_spam']++;
            } elseif ( $obj->type == 2 ) {

                // Comment spam
                $return['by_date'][ substr( $obj->date, 0, 10) ]['comment_spam']++;
                $return['comment_spam']++;
            } elseif ( $obj->type == 3 ) {

                // Contact Form 7 spam
                $return['by_date'][ substr( $obj->date, 0, 10) ]['cf7_spam']++;
                $return['cf7_spam']++;
            }

            // Unique spammers
            if ( ! in_array( $obj->ip, $return['unique_spammers'] ) ) {
              $return['unique_spammers'][] = $obj->ip;
            }

        }

        return $return;
    }

    /**
     * Returns spammer array from DB
     *
     * @since 1.5.0
     */
    private function _get_spam() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_log';

        $results = $wpdb->get_results( 'SELECT * FROM ' . $table_name . ' ORDER BY date DESC' );

        return $results;
    }

    /**
     * Uses admin_init.
     *
     * Triggered before any other hook when a user accesses the admin area.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_init
     */
    public function admin_init() {
        $this->_register_settings();
    }

    /*
     * WP generator meta tag option.
     *
     * Field callback, renders radio inputs, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_wp_generator() {
        ?>
        <input type="radio" id="wp_generator_remove" name="zerospam_general_settings[wp_generator]" value="remove"<?php if( $this->settings['zerospam_general_settings']['wp_generator'] == 'remove' ): ?> checked="checked"<?php endif; ?>> <label for="wp_generator_remove"><?php echo __( 'Hide', 'zerospam' ); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;

        <input type="radio" id="wp_generator_show" name="zerospam_general_settings[wp_generator]" value="show"<?php if( $this->settings['zerospam_general_settings']['wp_generator'] == 'show' ): ?> checked="checked"<?php endif; ?>> <label for="wp_generator_show"><?php echo __( 'Show', 'zerospam' ); ?></label>

        <p class="description"><?php echo __( 'It can be considered a security risk to make your WordPress version visible and public you should hide it.', 'zerospam' ); ?></p>
        <?php
    }

    /*
     * Log spammers option.
     *
     * Field callback, renders radio inputs, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_log_spammers() {
        ?>
        <input type="radio" id="log_spammers_yes" name="zerospam_general_settings[log_spammers]" value="yes"<?php if( $this->settings['zerospam_general_settings']['log_spammers'] == 'yes' ): ?> checked="checked"<?php endif; ?>> <label for="log_spammers_remove"><?php echo __( 'Yes', 'zerospam' ); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;

        <input type="radio" id="log_spammers_no" name="zerospam_general_settings[log_spammers]" value="no"<?php if( $this->settings['zerospam_general_settings']['log_spammers'] == 'no' ): ?> checked="checked"<?php endif; ?>> <label for="log_spammers_no"><?php echo __( 'No', 'zerospam' ); ?></label>
        <?php
    }

    /*
     * Spam comment message option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_spammer_msg_comment() {
        ?>
        <input type="text" class="regular-text" anme="zerospam_general_settings[spammer_msg_comment]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_comment'] ); ?>">
        <p class="description"><?php echo __( 'Enter a short message to display when a spam comment has been detected.', 'zerospam' ); ?></p>
        <?php
    }

    /*
     * Spam registration message option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_spammer_msg_registration() {
        ?>
        <input type="text" class="regular-text" anme="zerospam_general_settings[spammer_msg_registration]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_registration'] ); ?>">
        <p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
        <?php
    }

    /*
     * Contact Form 7 spam message option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_spammer_msg_contact_form_7() {
        ?>
        <input type="text" class="regular-text" anme="zerospam_general_settings[spammer_msg_contact_form_7]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_contact_form_7'] ); ?>">
        <p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
        <?php
    }

    /**
     * Add setting link to plugin.
     *
     * Applied to the list of links to display on the plugins page (beside the activate/deactivate links).
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
     */
    public function plugin_action_links( $links ) {
        $link = array( '<a href="' . admin_url( 'options-general.php?page=zerospam' ) . '">' . __( 'Settings', 'zerospam' ) . '</a>' );
        return array_merge( $links, $link );
    }

    /**
     * Uses plugins_loaded.
     *
     * This hook is called once any activated plugins have been loaded. Is
     * generally used for immediate filter setup, or plugin overrides.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded
     */
    public function plugins_loaded() {
        if ( get_site_option( 'zerospam_db_version' ) != $this->db_version ) {
            $this->install();
        }
    }

    /**
     * Installs the plugins DB tables.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Creating_Tables_with_Plugins
     */
    public function install() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_log';

        /*
         * We'll set the default character set and collation for this table.
         * If we don't do this, some characters could end up being converted
         * to just ?'s when saved in our table.
         */
        $charset_collate = '';

        if ( ! empty( $wpdb->charset ) ) {
          $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
        }

        if ( ! empty( $wpdb->collate ) ) {
          $charset_collate .= " COLLATE {$wpdb->collate}";
        }

        $sql = "CREATE TABLE $table_name (
            zerospam_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
            type int(1) unsigned NOT NULL,
            ip int(15) unsigned NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (zerospam_id),
            KEY `type` (type)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'zerospam_db_version', $this->db_version );
    }

    /**
     * Registers the settings.
     *
     * Appends the key to the plugin settings tabs array.
     *
     * @since 1.5.0
     */
    private function _register_settings() {
        register_setting( 'zerospam_general_settings', 'zerospam_general_settings' );
        add_settings_section( 'section_general', __( 'General Settings', 'zerospam' ), false, 'zerospam_general_settings' );
        add_settings_field( 'wp_generator', __( 'WP Generator Meta Tag', 'zerospam' ), array( &$this, 'field_wp_generator' ), 'zerospam_general_settings', 'section_general' );
        add_settings_field( 'spammer_msg_comment', __( 'Spam Comment Message', 'zerospam' ), array( &$this, 'field_spammer_msg_comment' ), 'zerospam_general_settings', 'section_general' );
        add_settings_field( 'spammer_msg_registration', __( 'Spam Registration Message', 'zerospam' ), array( &$this, 'field_spammer_msg_registration' ), 'zerospam_general_settings', 'section_general' );

        if ( $this->plugins['cf7'] ) {
          add_settings_field( 'spammer_msg_contact_form_7', __( 'Contact Form 7 Spam Message', 'zerospam' ), array( &$this, 'field_spammer_msg_contact_form_7' ), 'zerospam_general_settings', 'section_general' );
        }

        add_settings_field( 'log_spammers', __( 'Log Spammers', 'zerospam' ), array( &$this, 'field_log_spammers' ), 'zerospam_general_settings', 'section_general' );
    }

    /**
     * Logs spam.
     *
     * @since 1.5.0
     *
     * @param string (registration|comment) Type of spam
     */
    private function _log_spam( $type ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_log';

        switch( $type ) {
            case 'registration':
                $type = 1;
            break;
            case 'comment':
                $type = 2;
            break;
            case 'cf7':
                $type = 3;
            break;
        }

        $wpdb->insert( $table_name, array(
            'type' => $type,
            'ip' => ip2long( $this->_get_ip() )
        ),
        array(
            '%s',
            '%d'
        ));
    }

    /**
     * Returns a user's IP address
     *
     * @since 1.5.0
     *
     * @return string The current user's IP address.
     */
    private function _get_ip() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
           $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    /**
     * Renders setting tabs.
     *
     * Walks through the object's tabs array and prints them one by one.
     * Provides the heading for the settings_page method.
     *
     * @since 1.5.0
     */
    private function _options_tabs() {
        $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'zerospam_general_settings';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->tabs as $key => $name ) {
            $active = $current_tab == $key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=zerospam&tab=' . $key . '">' . $name . '</a>';
        }
        echo '</h2>';
    }

    /**
     * Load the settings / defaults.
     *
     * Load the settings from the database, and merge with the defaults where required.
     *
     * @since 1.5.0
     * @access private
     */
    private function _load_settings() {
        // Retrieve the settings
        $this->settings['zerospam_general_settings'] = (array) get_option( 'zerospam_general_settings' );
        // Merge with defaults
        $this->settings['zerospam_general_settings'] = array_merge( array(
            'wp_generator' => 'remove',
            'spammer_msg_comment' => 'There was a problem processing your comment.',
            'spammer_msg_registration' => '<strong>ERROR</strong>: There was a problem processing your registration.',
            'spammer_msg_contact_form_7' => 'There was a problem processing your comment.',
        ), $this->settings['zerospam_general_settings'] );
    }

    /**
     * WordPress actions.
     *
     * Adds WordPress actions using the plugin API.
     *
     * @since 1.0.0
     * @access private
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference
     */
    private function _actions() {
        add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
        add_action( 'init', array( &$this, 'init' ) );
        add_action( 'admin_init', array( &$this, 'admin_init' ) );
        add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
        add_action( 'login_footer', array( &$this, 'wp_enqueue_scripts' ) );
        add_action( 'preprocess_comment', array( &$this, 'preprocess_comment' ) );
        add_action( 'wpcf7_validate', array( &$this, 'wpcf7_validate' ) );

        if( $this->settings['zerospam_general_settings']['wp_generator'] == 'remove' ) {
            remove_action( 'wp_head', 'wp_generator' );
        }
    }

    /**
     * WordPress filters.
     *
     * Adds WordPress filters.
     *
     * @since 1.1.0
     * @access private
     *
     * @link http://codex.wordpress.org/Function_Reference/add_filter
     */
    private function _filters() {
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'plugin_action_links' ) );
        add_filter( 'registration_errors', array( &$this, 'preprocess_registration' ), 10, 3 );
    }

    /**
     * Plugin meta links.
     *
     * Adds links to the plugins meta.
     *
     * @since 1.1.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
     */
    public function plugin_row_meta( $links, $file ) {
        if ( false !== strpos( $file, 'zero-spam.php' ) ) {
            $links = array_merge( $links, array( '<a href="http://www.benmarshall.me/wordpress-zero-spam-plugin/">WordPress Zero Spam</a>' ) );
            $links = array_merge( $links, array( '<a href="https://www.gittip.com/bmarshall511/">Donate</a>' ) );
        }
        return $links;
    }

    /**
     * Preprocess comment fields.
     *
     * An action hook that is applied to the comment data prior to any other processing of the
     * comment's information when saving a comment data to the database.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
     */
    public function preprocess_comment( $commentdata ) {
        if ( ! wp_verify_nonce( $_POST['zero-spam'], 'zerospam' ) && ! current_user_can( 'moderate_comments' ) && is_user_logged_in() ) {
            do_action( 'zero_spam_found_spam_comment', $commentdata );

            if ( $this->settings['zerospam_general_settings']['log_spammers'] == 'yes' ) {
                $this->_log_spam( 'comment' );
            }

            die( __( $this->settings['zerospam_general_settings']['spammer_msg_comment'], 'zerospam' ) );
        }
        return $commentdata;
    }

    /**
     * Preprocess registration fields.
     *
     * Used to create custom validation rules on user registration. This fires
     * when the form is submitted but before user information is saved to the
     * database.
     *
     * @since 1.3.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/register_post
     */
    public function preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
        if ( ! wp_verify_nonce( $_POST['zero-spam'], 'zerospam' ) ) {
            do_action( 'zero_spam_found_spam_registration', $errors, $sanitized_user_login, $user_email );

            if ( $this->settings['zerospam_general_settings']['log_spammers'] == 'yes' ) {
                $this->_log_spam( 'registration' );
            }

            $errors->add( 'spam_error', __( $this->settings['zerospam_general_settings']['spammer_msg_registration'], 'zerospam' ) );
        }
        return $errors;
    }

     /**
     * Validate Contact Form 7 form submissions.
     *
     * Validates the Contact Form 7 (https://wordpress.org/plugins/contact-form-7/)
     * form submission, and flags the form submission as invalid if the zero-spam
     * post data isn't present.
     *
     * @since  1.5.0
     *
     */
    public function wpcf7_validate( $result ) {
        if ( ! wp_verify_nonce( $_POST['zero-spam'], 'zerospam' ) ) {
            do_action( 'zero_spam_found_spam_cf7_form_submission' );

            $result['valid'] = false;
            $result['reason']['zero_spam'] = __( $this->settings['zerospam_general_settings']['spammer_msg_contact_form_7'], 'zerospam' );

            $this->_log_spam( 'cf7' );
        }
        return $result;
    }

    /**
     * Add plugin scripts.
     *
     * Adds the plugins JS files.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Function_Reference/wp_enqueue_script
     */
    public function wp_enqueue_scripts() {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            wp_register_script( 'zero-spam', plugins_url( '/src/js/zero-spam.js' , __FILE__ ), array( 'jquery' ), '1.1.0', true );
        } else {
            wp_register_script( 'zero-spam', plugins_url( '/build/js/zero-spam.min.js' , __FILE__ ), array( 'jquery' ), '1.1.0', true );
        }
        wp_localize_script( 'zero-spam', 'zerospam', array(
            'nonce' => wp_create_nonce( 'zerospam' )
        ) );
        wp_enqueue_script( 'zero-spam' );
    }

    private function num_days( $date ) {
      $datediff = time() - strtotime( $date );
      return floor( $datediff / ( 60 * 60 * 24) );
    }

    /**
     * Converts numbers to words.
     *
     * @since 1.5.0
     *
     * @link http://www.karlrixon.co.uk/writing/convert-numbers-to-words-with-php/
     */
    private function num_to_word( $num ) {
      $hyphen      = '-';
      $conjunction = ' and ';
      $separator   = ', ';
      $negative    = 'negative ';
      $decimal     = ' point ';
      $dictionary  = array(
          0                   => 'zero',
          1                   => 'one',
          2                   => 'two',
          3                   => 'three',
          4                   => 'four',
          5                   => 'five',
          6                   => 'six',
          7                   => 'seven',
          8                   => 'eight',
          9                   => 'nine',
          10                  => 'ten',
          11                  => 'eleven',
          12                  => 'twelve',
          13                  => 'thirteen',
          14                  => 'fourteen',
          15                  => 'fifteen',
          16                  => 'sixteen',
          17                  => 'seventeen',
          18                  => 'eighteen',
          19                  => 'nineteen',
          20                  => 'twenty',
          30                  => 'thirty',
          40                  => 'fourty',
          50                  => 'fifty',
          60                  => 'sixty',
          70                  => 'seventy',
          80                  => 'eighty',
          90                  => 'ninety',
          100                 => 'hundred',
          1000                => 'thousand',
          1000000             => 'million',
          1000000000          => 'billion',
          1000000000000       => 'trillion',
          1000000000000000    => 'quadrillion',
          1000000000000000000 => 'quintillion'
      );

      if (!is_numeric($num)) {
          return false;
      }

      if (($num >= 0 && (int) $num < 0) || (int) $num < 0 - PHP_INT_MAX) {
          // overflow
          trigger_error(
              'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
              E_USER_WARNING
          );
          return false;
      }

      if ($num < 0) {
          return $negative . convert_number_to_words(abs($num));
      }

      $string = $fraction = null;

      if (strpos($num, '.') !== false) {
          list($num, $fraction) = explode('.', $num);
      }

      switch (true) {
          case $num < 21:
              $string = $dictionary[$num];
              break;
          case $num < 100:
              $tens   = ((int) ($num / 10)) * 10;
              $units  = $num % 10;
              $string = $dictionary[$tens];
              if ($units) {
                  $string .= $hyphen . $dictionary[$units];
              }
              break;
          case $num < 1000:
              $hundreds  = $num / 100;
              $remainder = $num % 100;
              $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
              if ($remainder) {
                  $string .= $conjunction . convert_number_to_words($remainder);
              }
              break;
          default:
              $baseUnit = pow(1000, floor(log($num, 1000)));
              $numBaseUnits = (int) ($num / $baseUnit);
              $remainder = $num % $baseUnit;
              $string = convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
              if ($remainder) {
                  $string .= $remainder < 100 ? $conjunction : $separator;
                  $string .= convert_number_to_words($remainder);
              }
              break;
      }

      if (null !== $fraction && is_numeric($fraction)) {
          $string .= $decimal;
          $words = array();
          foreach (str_split((string) $fraction) as $num) {
              $words[] = $dictionary[$num];
          }
          $string .= implode(' ', $words);
      }

      return $string;
  }
}
