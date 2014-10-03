<?php
/**
 * Plugin Name: WordPress Zero Spam
 * Plugin URI: http://www.benmarshall.me/wordpress-zero-spam-plugin
 * Description: Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch. <strong>Just install, activate and say goodbye to spam.</strong> Based on work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.
 * Version: 1.5.0
 * Author: Ben Marshall
 * Author URI: http://www.benmarshall.me
 * License: GPL2
 * GitHub Plugin URI: https://github.com/bmarshall511/wordpress-zero-spam
 * GitHub Branch: develop
 */

/*  Copyright 2014  Ben Marshall  (email : me@benmarshall.me)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Define constants
define( 'ZEROSPAM_ROOT', dirname( __FILE__ ) . '/' );

class Zero_Spam {
    /*
     * For easier overriding we declared the keys
     * here as well as our tabs array which is populated
     * when registering settings
     */
    private $settings = array(
        'zerospam_general_settings' => array()
    );
    private $tabs = array(
        'zerospam_general_settings' => 'General Settings'
    );

    /**
     * Plugin initilization.
     *
     * Initializes the plugins functionality.
     *
     * @since 1.0.0
     */
    public function __construct() {
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
        // Merge with defaults
        $this->settings['zerospam_general_settings'] = array_merge( array(
            'wp_generator' => 'remove',
            'spammer_msg_comment' => 'There was a problem processing your comment.',
            'spammer_msg_registration' => '<strong>ERROR</strong>: There was a problem processing your registration.'
        ), $this->settings['zerospam_general_settings'] );
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

        wp_enqueue_style( 'zerospam-fontawesome', plugins_url( 'assets/css/font-awesome.min.css', __FILE__ ) );
        wp_enqueue_style( 'zerospam-admin', plugins_url( 'assets/css/style.css', __FILE__ ) );
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
            <table>
                <tbody>
                    <tr>
                        <td valign="top" style="padding-right: 17px">
                            <h2><?php echo __( 'WordPress Zero Spam Settings', 'zerospam' ); ?></h2>
                            <?php $this->_options_tabs(); ?>
                            <form method="post" action="options.php">
                                <?php wp_nonce_field( 'zerospam-options' ); ?>
                                <?php settings_fields( $tab ); ?>
                                <?php do_settings_sections( $tab ); ?>
                                <?php submit_button(); ?>
                            </form>
                        </td>
                        <td valign="top" width="422">
                            <?php require_once( ZEROSPAM_ROOT . 'inc/admin-sidebar.tpl.php' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
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
    function field_wp_generator() {
        ?>
        <input type="radio" id="wp_generator_remove" name="zerospam_general_settings[wp_generator]" value="remove"<?php if( $this->settings['zerospam_general_settings']['wp_generator'] == 'remove' ): ?> checked="checked"<?php endif; ?>> <label for="wp_generator_remove"><?php echo __( 'Hide', 'zerospam' ); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;

        <input type="radio" id="wp_generator_show" name="zerospam_general_settings[wp_generator]" value="show"<?php if( $this->settings['zerospam_general_settings']['wp_generator'] == 'show' ): ?> checked="checked"<?php endif; ?>> <label for="wp_generator_show"><?php echo __( 'Show', 'zerospam' ); ?></label>

        <p class="description"><?php echo __( 'It can be considered a security risk to make your WordPress version visible and public you should hide it.', 'zerospam' ); ?></p>
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

    /*
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

    /*
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
        $this->settings['zerospam_general_settings'] = (array) get_option( 'zerospam_general_settings' );

        add_action( 'init', array( &$this, 'init' ) );
        add_action( 'admin_init', array( &$this, 'admin_init' ) );
        add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
        add_action( 'login_footer', array( &$this, 'wp_enqueue_scripts' ) );
        add_action( 'preprocess_comment', array( &$this, 'preprocess_comment' ) );

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
        if ( ! wp_verify_nonce( $_POST['zero-spam'], 'zerospam' ) && ! current_user_can( 'moderate_comments' ) ) {
            do_action( 'zero_spam_found_spam_comment', $commentdata );
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
            $errors->add( 'spam_error', __( $this->settings['zerospam_general_settings']['spammer_msg_registration'], 'zerospam' ) );
        }
        return $errors;
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
        wp_register_script( 'zero-spam', plugins_url( '/zero-spam.min.js' , __FILE__ ), array( 'jquery' ), '1.1.0', true );
        wp_localize_script( 'zero-spam', 'zerospam', array(
            'nonce' => wp_create_nonce( 'zerospam' )
        ) );
        wp_enqueue_script( 'zero-spam' );
    }
}

$zero_spam = new Zero_Spam;
