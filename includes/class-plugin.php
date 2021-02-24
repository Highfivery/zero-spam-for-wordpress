<?php
/**
 * Main plugin class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam;

use ZeroSpam\Includes\DB;
use ZeroSpam\Core\Access;
use ZeroSpam\Core\User;
use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Modules\BotScout;
use ZeroSpam\Modules\StopForumSpam;
use ZeroSpam\Modules\ipstack;
use ZeroSpam\Modules\Google;
use ZeroSpam\Modules\Zero_Spam;
use ZeroSpam\Modules\Registration\Registration;
use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Modules\ContactForm7\ContactForm7;
use ZeroSpam\Modules\WooCommerce\WooCommerce;
use ZeroSpam\Modules\WPForms\WPForms;
use ZeroSpam\Modules\Formidable\Formidable;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WordPress Zero Spam plugin.
 *
 * The main plugin handler class is responsible for initializing WordPress Zero
 * Spam. The class registers and all the components required to run the plugin.
 *
 * @since 5.0.0
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * Holds the plugin instance.
	 *
	 * @since 5.0.0
	 * @access public
	 * @static
	 *
	 * @var Plugin
	 */
	public static $instance = null;

	/**
	 * Plugin constructor.
	 *
	 * Initializing WordPress Zero Spam plugin.
	 *
	 * @since 5.0.0
	 * @access private
	 */
	private function __construct() {
		$this->register_autoloader();

		add_action( 'init', array( $this, 'init' ), 0 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );
	}

	/**
	 * Register autoloader.
	 *
	 * WordPress Zero Spam autoloader loads all the classes needed to run the
	 * plugin.
	 *
	 * @since 5.0.0
	 * @access private
	 */
	private function register_autoloader() {
		require_once ZEROSPAM_PATH . 'includes/class-autoloader.php';

		Autoloader::run();
	}

	/**
	 * Instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			/**
			 * WordPress Zero Spam loaded.
			 *
			 * Fires when WordPress Zero Spam was fully loaded and instantiated.
			 *
			 * @since 1.0.0
			 */
			do_action( 'zerospam_loaded' );
		}

		return self::$instance;
	}

	/**
	 * Init.
	 *
	 * Initialize WordPress Zero Spam Plugin. Checks if the current user should be
	 * blocked.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function init() {
		$this->init_components();

		/**
		 * WordPress Zero Spam init.
		 *
		 * Fires on WordPress Zero Spam init, after WordPress Zero Spam has finished
		 * loading but before any headers are sent.
		 *
		 * @since 5.0.0
		 */
		do_action( 'zerospam_init' );
	}

	/**
	 * Init components.
	 *
	 * Initialize WordPress Zero Spam components. Register actions, initialize all
	 * the components that run WordPress Zero Spam, and if in admin page
	 * initialize admin components.
	 *
	 * @since 5.0.0
	 * @access private
	 */
	private function init_components() {
		new DB();
		new Registration();
		new Comments();

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			new ContactForm7();
		}

		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			new WooCommerce();
		}

		if (
			is_plugin_active( 'wpforms-lite/wpforms.php' ) ||
			is_plugin_active( 'wpforms/wpforms.php' )
		) {
			new WPForms();
		}

		if ( is_plugin_active( 'formidable/formidable.php' ) ) {
			new Formidable();
		}

		//= new BotScout();
		new StopForumSpam();
		new ipstack();
		new Zero_Spam();

		if (
			! is_admin() &&
			is_main_query()
		) {
			new Access();
		}

		if ( is_admin() ) {
			new Google();
			new Admin();
		}
	}

	/**
	 * Add to the types array.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function types( $types ) {
		$types['blocked'] = __( 'Blocked', 'zerospam' );

		return $types;
	}
}

Plugin::instance();
