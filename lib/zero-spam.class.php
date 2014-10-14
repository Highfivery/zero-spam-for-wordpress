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
        'zerospam_general_settings' => 'General Settings',
        'zerospam_ip_block' => 'IP Block'
    );

    private $plugins = array(
      'cf7' => false,
      'gf' => false
    );

    private $db_version = "0.0.1";

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
        $this->_ip_check();
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
        if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) {
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

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            wp_enqueue_style( 'zerospam-admin', plugins_url( 'build/css-dev/style.css', ZEROSPAM_PLUGIN ) );
            wp_enqueue_script( 'zerospam-charts', plugins_url( 'build/js-dev/charts.js', ZEROSPAM_PLUGIN ), array( 'jquery' ) );
        } else {
            wp_enqueue_style( 'zerospam-admin', plugins_url( 'build/css/style.css', ZEROSPAM_PLUGIN ) );
            wp_enqueue_script( 'zerospam-charts', plugins_url( 'build/js/charts.min.js', ZEROSPAM_PLUGIN ), array( 'jquery' ) );
        }
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
        $plugin = get_plugin_data( ZEROSPAM_PLUGIN );
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
                        'zerospam_spammer_logs' == $tab &&
                        '1' == $this->settings['zerospam_general_settings']['log_spammers']
                    ) {
                        $spam = $this->_get_spam();
                        $spam = $this->_parse_spam_ary( $spam );

                        $total_spam = count( $spam['raw'] );
                        $unique_spammers = count( $spam['unique_spammers'] );

                        if ( $total_spam ) {
                          $per_day = $this->_num_days( end( $spam['raw'] )->date ) ? number_format( ( count( $spam['raw'] ) / $this->_num_days( end( $spam['raw'] )->date ) ), 2 ) : 0;
                          $num_days = $this->_num_days( end( $spam['raw'] )->date );
                          $starting_date = end( $spam['raw'] )->date;
                        }

                        require_once( ZEROSPAM_ROOT . 'inc/spammer-logs.tpl.php' );
                    } elseif ( $tab == 'zerospam_ip_block' ) {
                        $ips = $this->_get_blocked_ips();

                        require_once( ZEROSPAM_ROOT . 'inc/ip-block.tpl.php' );
                    } else {
                        require_once( ZEROSPAM_ROOT . 'inc/general-settings.tpl.php' );
                    } ?>
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

        // Gravity Form support.
        if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
            $this->plugins['gf'] = true;
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
            'gf_spam' => 0,
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

                // Registration spam.
                $return['by_date'][ substr( $obj->date, 0, 10) ]['registration_spam']++;
                $return['registration_spam']++;
            } elseif ( $obj->type == 2 ) {

                // Comment spam.
                $return['by_date'][ substr( $obj->date, 0, 10) ]['comment_spam']++;
                $return['comment_spam']++;
            } elseif ( $obj->type == 3 ) {

                // Contact Form 7 spam.
                $return['by_date'][ substr( $obj->date, 0, 10) ]['cf7_spam']++;
                $return['cf7_spam']++;
            } elseif ( $obj->type == 4 ) {

                // Gravity Form spam.
                $return['by_date'][ substr( $obj->date, 0, 10) ]['gf_spam']++;
                $return['gf_spam']++;
            }

            // Unique spammers
            if ( ! in_array( $obj->ip, $return['unique_spammers'] ) ) {
              $return['unique_spammers'][] = $obj->ip;
            }

        }

        return $return;
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
	    if ( ! isset( $this->settings['zerospam_general_settings']['wp_generator'] ) ) {
			$this->settings['zerospam_general_settings']['wp_generator'] = '0';
	    }
        ?>
	    <label for="wp_generator_remove">
			<input type="checkbox" id="wp_generator_remove" name="zerospam_general_settings[wp_generator]" value="1" <?php if ( isset( $this->settings['zerospam_general_settings']['wp_generator']) ): checked( $this->settings['zerospam_general_settings']['wp_generator'] ); endif; ?> /> <?php echo __( 'Hide WP Generator Meta Tag', 'zerospam' ); ?>
		 </label>

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
	    <label for="log_spammers">
	        <input type="checkbox" id="log_spammers" name="zerospam_general_settings[log_spammers]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) ) : checked( $this->settings['zerospam_general_settings']['log_spammers'] ); endif; ?> /> <?php echo __( 'Log Spammers', 'zerospam' ); ?>
	    </label>
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
	    <label for="spammer_msg_comment">
            <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_comment]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_comment'] ); ?>">
        <p class="description"><?php echo __( 'Enter a short message to display when a spam comment has been detected.', 'zerospam' ); ?></p>
	    </label>
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
	    <label for="spammer_msg_registration">
            <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_registration]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_registration'] ); ?>">
        <p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
	    </label>
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
	    <label for="spammer_msg_contact_form_7">
            <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_contact_form_7]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_contact_form_7'] ); ?>">
        <p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
	    </label>
        <?php
    }

    /*
     * Contact Form 7 support option.
     *
     * Field callback, renders a checkbox input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_cf7_support() {
        ?>
        <label for="cf7_support">
            <input type="checkbox" id="cf7_support" name="zerospam_general_settings[cf7_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['cf7_support'] ) ) : checked( $this->settings['zerospam_general_settings']['cf7_support'] ); endif; ?> /> <?php echo __( 'Enable', 'zerospam' ); ?>
        </label>
        <?php
    }

    /*
     * Gravity Forms support option.
     *
     * Field callback, renders a checkbox input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_gf_support() {
        ?>
        <label for="gf_support">
            <input type="checkbox" id="gf_support" name="zerospam_general_settings[gf_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['gf_support'] ) ) : checked( $this->settings['zerospam_general_settings']['gf_support'] ); endif; ?> /> <?php echo __( 'Enable', 'zerospam' ); ?>
        </label>
        <?php
    }

    /*
     * Comment support option.
     *
     * Field callback, renders a checkbox input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_comment_support() {
        ?>
        <label for="comment_support">
            <input type="checkbox" id="comment_support" name="zerospam_general_settings[comment_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['comment_support'] ) ) : checked( $this->settings['zerospam_general_settings']['comment_support'] ); endif; ?> /> <?php echo __( 'Enable', 'zerospam' ); ?>
        </label>
        <?php
    }

    /*
     * Registration support option.
     *
     * Field callback, renders a checkbox input, note the name and value.
     *
     * @since 1.5.0
     */
    public function field_registration_support() {
        ?>
        <label for="registration_support">
            <input type="checkbox" id="registration_support" name="zerospam_general_settings[registration_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['registration_support'] ) ) : checked( $this->settings['zerospam_general_settings']['registration_support'] ); endif; ?> /> <?php echo __( 'Enable', 'zerospam' ); ?>
        </label>
        <?php
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

        $log_table_name = $wpdb->prefix . 'zerospam_log';
        $ip_table_name = $wpdb->prefix . 'zerospam_blocked_ips';

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

        $sql = "CREATE TABLE $log_table_name (
            zerospam_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
            type int(1) unsigned NOT NULL,
            ip varchar(15) NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            page varchar(255) DEFAULT NULL,
            PRIMARY KEY  (zerospam_id),
            KEY type (type)
        ) $charset_collate;";

        $sql .= "CREATE TABLE $ip_table_name (
          zerospam_ip_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
          ip varchar(15) NOT NULL,
          type enum('permanent','temporary') NOT NULL DEFAULT 'temporary',
          start_date datetime DEFAULT NULL,
          end_date datetime DEFAULT NULL,
          reason varchar(255) DEFAULT NULL,
          PRIMARY KEY  (zerospam_ip_id),
          UNIQUE KEY ip (ip)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'zerospam_db_version', $this->db_version );

        $options = (array) $this->settings['zerospam_general_settings'];
        $options['registration_support'] = 1;
        $options['comment_support'] = 1;
        $options['log_spammers'] = 1;
        $options['wp_generator'] = 1;
        $options['cf7_support'] = 1;
        $options['gf_support'] = 1;
        update_option( 'zerospam_general_settings', $options );
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
        add_settings_field( 'log_spammers', __( 'Log Spammers', 'zerospam' ), array( &$this, 'field_log_spammers' ), 'zerospam_general_settings', 'section_general' );

        add_settings_field( 'comment_support', __( 'Comment Support', 'zerospam' ), array( &$this, 'field_comment_support' ), 'zerospam_general_settings', 'section_general' );
        if ( isset( $this->settings['zerospam_general_settings']['comment_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['comment_support'] ) ) {
            add_settings_field( 'spammer_msg_comment', __( 'Spam Comment Message', 'zerospam' ), array( &$this, 'field_spammer_msg_comment' ), 'zerospam_general_settings', 'section_general' );
        }

        add_settings_field( 'registration_support', __( 'Registration Support', 'zerospam' ), array( &$this, 'field_registration_support' ), 'zerospam_general_settings', 'section_general' );
        if ( isset( $this->settings['zerospam_general_settings']['registration_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['registration_support'] ) ) {
            add_settings_field( 'spammer_msg_registration', __( 'Spam Registration Message', 'zerospam' ), array( &$this, 'field_spammer_msg_registration' ), 'zerospam_general_settings', 'section_general' );
        }

        // Contact Form 7 support.
        if ( $this->plugins['cf7'] ) {
            add_settings_field( 'cf7_support', __( 'Contact Form 7 Support', 'zerospam' ), array( &$this, 'field_cf7_support' ), 'zerospam_general_settings', 'section_general' );

            if ( isset( $this->settings['zerospam_general_settings']['cf7_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['cf7_support'] ) ) {
                add_settings_field( 'spammer_msg_contact_form_7', __( 'Contact Form 7 Spam Message', 'zerospam' ), array( &$this, 'field_spammer_msg_contact_form_7' ), 'zerospam_general_settings', 'section_general' );
            }
        }

        // Gravity Forms support.
        if ( $this->plugins['gf'] ) {
            add_settings_field( 'gf_support', __( 'Gravity Forms Support', 'zerospam' ), array( &$this, 'field_gf_support' ), 'zerospam_general_settings', 'section_general' );
        }
    }

    /**
     * Checks if the current IP is blocked.
     *
     * @since 1.5.0
     */
    private function _ip_check() {
    	$ips = $this->_get_blocked_ips();
    	$ip = $this->_get_ip();

    	if ( is_array( $ips ) && count( $ips ) ) {
	    	foreach( $ips as $key => $obj ) {
	    		if( $obj->ip == $ip ) {
	    			die( "Access denied." );
	    		}
	    	}
	    }
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
            case 'gf':
                $type = 4;
            break;
        }

        $wpdb->insert( $table_name, array(
            'type' => $type,
            'ip' => $this->_get_ip(),
            'page' =>$this->_get_url()
        ),
        array(
            '%s',
            '%s',
            '%s'
        ));
    }

    /**
     * Blocks an IP address.
     *
     * Adds an IP to the blocked list so the user can't access the site.
     *
     * @since 1.5.0
     *
     * @param array $args Array of arguments.
     */
    private function _block_ip( $args ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_blocked_ips';
        $ip = $this->_get_ip();

        $type = isset( $args['type'] ) ? $args['type'] : 'temporary';

        // Check is IP has already been blocked.
        if ( $this->_is_blocked( $ip, false ) ) {

            // Update existing record.
            $wpdb->update(
                $table_name,
                array(
                    'type' => $type,
                    'start_date' => $args['start_date'],
                    'end_date' => $args['end_date'],
                    'reason' => $args['reason']
                ),
                array( 'ip' => $ip ),
                array(
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                ),
                array( '%s' )
            );
        } else {

            // Insert new record.
            $insert = array(
                'ip' => $ip,
                'type' => $type
            );

            if ( 'temporary' == $type ) {
                $insert['start_date'] = $args['start_date'];
                $insert['end_date'] = $args['end_date'];
            }

            if ( isset( $args['reason'] ) && $args['reason'] ) {
                $insert['reason'] = $args['reason'];
            }

            $wpdb->insert( $table_name, $insert,
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ));
        }
    }

    /**
     * Returns the current URL.
     *
     * @since 1.5.0
     *
     * @return string The current URL the user is on.
     */
    private function _get_url() {
        $pageURL = 'http';

        if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on")
            $pageURL .= "s";

        $pageURL .= "://";

        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }

        return $pageURL;
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
		$default_settings =  array(
			'spammer_msg_comment'        => 'There was a problem processing your comment.',
			'spammer_msg_registration'   => '<strong>ERROR</strong>: There was a problem processing your registration.',
			'spammer_msg_contact_form_7' => 'There was a problem processing your comment.',
            'spammer_msg_gf'             => 'There was a problem processing your submission.',
		);

		// Retrieve the settings
		$saved_settings = (array) get_option( 'zerospam_general_settings' );

		$this->settings['zerospam_general_settings'] = array_merge(
			$default_settings,
			$saved_settings
		);
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
        add_action( 'admin_footer', array( &$this, 'admin_footer' ) );
        add_action( 'wp_ajax_block_ip', array( &$this, 'wp_ajax_block_ip' ) );
        add_action( 'wp_ajax_block_ip_form', array( &$this, 'wp_ajax_block_ip_form' ) );
        add_action( 'wp_ajax_get_blocked_ip', array( &$this, 'wp_ajax_get_blocked_ip' ) );
        add_action( 'wp_ajax_trash_ip_block', array( &$this, 'wp_ajax_trash_ip_block' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
        add_action( 'login_footer', array( &$this, 'wp_enqueue_scripts' ) );

        if ( isset( $this->settings['zerospam_general_settings']['comment_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['comment_support'] ) ) {
            add_action( 'preprocess_comment', array( &$this, 'preprocess_comment' ) );
        }

        if ( isset( $this->settings['zerospam_general_settings']['cf7_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['cf7_support'] ) ) {
            add_action( 'wpcf7_validate', array( &$this, 'wpcf7_validate' ) );
        }

	    if ( isset( $this->settings['zerospam_general_settings']['wp_generator'] ) && ( '1' == $this->settings['zerospam_general_settings']['wp_generator'] ) ) {
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

        if ( isset( $this->settings['zerospam_general_settings']['registration_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['registration_support'] ) ) {
            add_filter( 'registration_errors', array( &$this, 'preprocess_registration' ), 10, 3 );
        }

        // Gravity Forms support.
        add_filter( 'gform_validation', array( &$this, 'gform_validation' ) );
    }

    /**
     * Uses admin_footer.
     *
     * Triggered just after closing the <div id="wpfooter"> tag and right before
     * admin_print_footer_scripts action call of the admin-footer.php page.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_footer
     */
    public function admin_footer() {
        $ajax_nonce = wp_create_nonce( 'zero-spam' );
        ?>
        <script>
        jQuery( document ).ready( function( $ ) {
            $(
            	".zero-spam__block-ip, .zero-spam__trash"
            ).click( function( e ) {
                e.preventDefault();

                closeForms();

                var row = $( this ).closest( "tr" ),
                    form_row = $( "<tr class='zero-spam__row-highlight'>" ),
                    btn = $( this );
                    btn_cell = btn.parent(),
                    ip = btn.data( "ip" ),
                    action = '';

                    row.addClass( "zero-spam__loading" );

                if ( btn.hasClass( "zero-spam__trash" ) ) {
                	action = 'trash_ip_block';
                } else {
                	action = 'block_ip_form';
                }

                $.post( ajaxurl, {
                    action: action,
                    security: '<?php echo $ajax_nonce; ?>',
                    ip: ip
                }, function( data ) {
                    row.removeClass( "zero-spam__loading" );

                    if ( btn.hasClass( "zero-spam__trash" ) ) {
                		action = 'trash_ip_block';
                		row.fadeOut( function() {
                			row.remove();

                			if ( $( ".zero-spam__table tbody tr" ).length == 0 ) {
                				$( "#zerospam-id-container" ).after( "No blocked IPs found." );
                				$( "#zerospam-id-container" ).remove();
                			}
                		});
	                } else {
	                	action = 'block_ip_form';

	                	row.addClass( "zero-spam__loaded" );

                    	form_row.append( "<td colspan='10'>" + data + "</td>" );

                    	row.before( form_row );
	                }
                });
            });
        });

        function closeForms() {
            jQuery( ".zero-spam__row-highlight" ).remove();
            jQuery( "tr" ).removeClass( "zero-spam__loading" );
            jQuery( "tr" ).removeClass( "zero-spam__loaded" );
        }

        function updateRow( ip ) {
        	if ( ip ) {
	            jQuery.post( ajaxurl, {
	                action: 'get_blocked_ip',
	                security: '<?php echo $ajax_nonce; ?>',
	                ip: ip
	            }, function( data ) {
	                var d = jQuery.parseJSON( data ),
	                    row = jQuery( "tr[data-ip='" + d.ip + "']" ),
	                    label;

	                if ( true == d.is_blocked ) {
	                    label = '<span class="zero-spam__label zero-spam__bg--primary">Blocked</span>';
	                } else {
	                    label = '<span class="zero-spam__label zero-spam__bg--trinary">Unblocked</span>';
	                }

	                jQuery( ".zero-spam__reason", row ).text( d.reason );
	                jQuery( ".zero-spam__start-date", row ).text( d.start_date_txt );
	                jQuery( ".zero-spam__end-date", row ).text( d.end_date_txt );
	                jQuery( ".zero-spam__status", row ).html( label );
	            });
	        }
        }
        </script>
        <?php
    }

    /**
     * Uses wp_ajax_(action).
     *
     * Deletes a IP block.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
     */
    public function wp_ajax_trash_ip_block() {
    	global $wpdb;
        check_ajax_referer( 'zero-spam', 'security' );

        $ajax_nonce = wp_create_nonce( 'zero-spam' );

        $ip = $_REQUEST['ip'];

        $this->_delete_blocked_ip( $ip );

        die();
    }

    /**
     * Uses wp_ajax_(action).
     *
     * Renders the block IP form.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
     */
    public function wp_ajax_block_ip_form() {
        global $wpdb;
        check_ajax_referer( 'zero-spam', 'security' );

        $ajax_nonce = wp_create_nonce( 'zero-spam' );

        $date = new DateTime();
        $end_date = $date->modify('+1 day');

        $start_date_year = date( 'Y' );
        $start_date_month = date( 'n' );
        $start_date_day = date( 'd' );

        $end_date_year = $end_date->format( 'Y' );
        $end_date_month = $end_date->format( 'n' );
        $end_date_day = $end_date->format( 'd' );

        if ( isset( $_REQUEST['ip'] ) ) {
            $ip = $_REQUEST['ip'];
            $data = $this->_get_blocked_ip( $_REQUEST['ip'] );

            if ( $data ) {
	            if ( $data->start_date ) list( $start_date_year, $start_date_month, $start_date_day ) = explode( '-', $data->start_date );
	            if ( $data->end_date ) list( $end_date_year, $end_date_month, $end_date_day ) = explode( '-', $data->end_date );
	        }
        }

        require_once( ZEROSPAM_ROOT . 'inc/block-ip-form.tpl.php' );

        die();
    }

    /**
     * Uses wp_ajax_(action).
     *
     * Get the blocked IP data.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
     */
    public function wp_ajax_get_blocked_ip() {
        global $wpdb;
        check_ajax_referer( 'zero-spam', 'security' );

        $ajax_nonce = wp_create_nonce( 'zero-spam' );

        $ip = $_REQUEST['ip'];

        $data = $this->_get_blocked_ip( $ip );

        if ( $data ) {
	        $data->is_blocked = $this->_is_blocked( $ip );
	        $data->start_date_txt = date( 'l, F j, Y', strtotime( $data->start_date ) );
	        $data->end_date_txt = date( 'l, F j, Y', strtotime( $data->end_date ) );

	        echo json_encode( (array) $data );
	    }

        die();
    }

    /**
     * Uses wp_ajax_(action).
     *
     * AJAX function to block a user's IP address.
     *
     * @since 1.5.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
     */
    public function wp_ajax_block_ip() {
        check_ajax_referer( 'zero-spam', 'security' );

        if ( ! $_POST['zerospam-type'] == 'temporary' ) {
            $start_date = false;
            $end_date = false;
        } else {
            $start_date = date( 'Y-m-d G:i:s', strtotime(
                $_POST['zerospam-startdate-year'] . '-' .
                $_POST['zerospam-startdate-month'] . '-' .
                $_POST['zerospam-startdate-day']
            ));

            $end_date = date( 'Y-m-d G:i:s', strtotime(
                $_POST['zerospam-enddate-year'] . '-' .
                $_POST['zerospam-enddate-month'] . '-' .
                $_POST['zerospam-enddate-day']
            ));
        }

        $reason = isset( $_POST['zerospam-reason'] ) ? $_POST['zerospam-reason'] : NULL;

        // Add/update the blocked IP.
        $this->_block_ip( array(
            'type' => $_POST['zerospam-type'],
            'start_date' => $start_date,
            'end_date' => $end_date,
            'reason' => $reason
        ));

        die();
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
     * Validate Gravity Form submissions.
     *
     * @since 1.5.0
     *
     * @link http://www.gravityhelp.com/documentation/page/Gform_validation
     */
    public function gform_validation( $result ) {
        if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {

            do_action( 'zero_spam_found_spam_gf_form_submission' );

            $result['is_valid'] = false;

            $this->_log_spam( 'gf' );
        }

        return $result;
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
        if ( ! isset( $_POST['zerospam_key'] ) ||
            (
                $_POST['zerospam_key'] != $this->_get_key() ) &&
                ! current_user_can( 'moderate_comments' ) &&
                is_user_logged_in()
            )
        {
            do_action( 'zero_spam_found_spam_comment', $commentdata );

	        if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && ( '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) ) {
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
        if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {
            do_action( 'zero_spam_found_spam_registration', $errors, $sanitized_user_login, $user_email );

	        if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && ( '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) ) {
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
        if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {
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
            wp_register_script( 'zero-spam', plugins_url( '/build/js-dev/zero-spam.js' , ZEROSPAM_PLUGIN ), array( 'jquery' ), '1.1.0', true );
        } else {
            wp_register_script( 'zero-spam', plugins_url( '/build/js/zero-spam.min.js' , ZEROSPAM_PLUGIN ), array( 'jquery' ), '1.1.0', true );
        }
        wp_localize_script( 'zero-spam', 'zerospam', array(
            'key' => $this->_get_key()
        ) );
        wp_enqueue_script( 'zero-spam' );
    }

    /**
     *  Delete a blocked IP.
     *
     * @since 1.5.0
     *
     * @param $ip string The IP address to block.
     */
    private function _delete_blocked_ip( $ip ) {
    	global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_blocked_ips';

        $query = $wpdb->delete( $table_name, array( 'ip' => $ip ), array( '%s' ) );

        return $query;
    }

    /**
     *  Returns a blocked IP.
     *
     * @since 1.5.0
     *
     * @param $ip string The IP address to get.
     */
    private function _get_blocked_ip( $ip ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_blocked_ips';

        $query = $wpdb->get_row( "SELECT * FROM $table_name WHERE ip = '" . $ip . "'" );

        if ( $query == null ) return false;

        return $query;
    }

    /**
     *  Returns an array of blocked IPs.
     *
     * @since 1.5.0
     *
     * @return array An array of blocked IPs from the database.
     */
    private function _get_blocked_ips() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_blocked_ips';

        $query = $wpdb->get_results( "SELECT * FROM $table_name" );

        if ( $query == null ) return false;

        return $query;
    }

    /**
     * Checks if an IP is blocked.
     *
     * @since 1.5.0
     *
     * @return boolean True if blocked, false if not.
     */
    private function _is_blocked( $ip, $time = true ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zerospam_blocked_ips';

        $check = $this->_get_blocked_ip( $ip );

        if ( $check ) {

            if ( $time ) {
                // Check block type
                if (
                    'temporary' == $check->type &&
                    time() >= strtotime( $check->start_date ) &&
                    time() <= strtotime( $check->end_date )
                ) {
                    return true;
                } elseif( 'permanent' == $check->type ) {
                    return true;
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns number of days since a date.
     *
     * @since 1.5.0
     *
     * @return int Number of days since the specified date.
     */
    private function _num_days( $date ) {
      $datediff = time() - strtotime( $date );
      return floor( $datediff / ( 60 * 60 * 24) );
    }

    /**
     * Retrieve the key, generating if needed.
     *
     * @since 1.5.0
     */
    private function _get_key() {
        if ( ! $key = get_option( 'zerospam_key' ) ) {
            $key = wp_generate_password( 64 );
            update_option( 'zerospam_key', $key );
        }

        return $key;
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
