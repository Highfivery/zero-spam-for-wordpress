<?php
/**
 * Main plugin class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam;

//use ZeroSpam\Core\Access;
//use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Core\Access;

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
	 * Access.
	 *
	 * Holds the user access.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @var Access
	 */
	public $access;

	/**
	 * Settings.
	 *
	 * Holds the plugin settings.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @var Settings
	 */
	public $settings;

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
			 * Elementor loaded.
			 *
			 * Fires when Elementor was fully loaded and instantiated.
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
		//$admin = new Admin();
		$access = new Access();
		//$this->access = new Access();
	}
}

Plugin::instance();
