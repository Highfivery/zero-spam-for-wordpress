<?php
/**
 * Main plugin class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam;

use ZeroSpam\Includes\DB;
use ZeroSpam\Core\Access;
use ZeroSpam\Core\User;
use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Modules\Google;
use ZeroSpam\Modules\Zero_Spam;
use ZeroSpam\Modules\Registration\Registration;
use ZeroSpam\Modules\Comments\Comments;
use ZeroSpam\Modules\ContactForm7\ContactForm7;
use ZeroSpam\Modules\WPForms\WPForms;
use ZeroSpam\Modules\Formidable\Formidable;
use ZeroSpam\Modules\FluentForms\FluentForms;
use ZeroSpam\Modules\DavidWalsh\DavidWalsh;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WordPress Zero Spam plugin
 */
class Plugin {

	/**
	 * Instance
	 *
	 * @var Plugin
	 */
	public static $instance = null;

	/**
	 * Plugin constructor
	 */
	private function __construct() {
		$this->register_autoloader();

		add_action( 'init', array( $this, 'init' ), 0 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );
	}

	/**
	 * Register autoloader
	 */
	private function register_autoloader() {
		require_once ZEROSPAM_PATH . 'includes/class-autoloader.php';

		Autoloader::run();
	}

	/**
	 * Instance
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
	 * Init
	 */
	public function init() {
		$this->init_components();

		/**
		 * WordPress Zero Spam init
		 *
		 * Fires on WordPress Zero Spam init, after WordPress Zero Spam has finished
		 * loading but before any headers are sent.
		 *
		 * @since 5.0.0
		 */
		do_action( 'zerospam_init' );
	}

	/**
	 * Init components
	 *
	 * Initialize WordPress Zero Spam components. Register actions, initialize all
	 * the components that run WordPress Zero Spam, and if in admin page
	 * initialize admin components.
	 */
	private function init_components() {
		new Zero_Spam();
		new DB();
		new Registration();
		new Comments();
		new DavidWalsh();

		new \ZeroSpam\Modules\Login\Login();

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			new ContactForm7();
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

		if ( is_plugin_active( 'fluentform/fluentform.php' ) ) {
			new FluentForms();
		}

		/*if ( is_plugin_active( 'ninja-forms/ninja-forms.php' ) ) {
			new \ZeroSpam\Modules\NinjaForms\NinjaForms();
		}*/

		new \ZeroSpam\Modules\StopForumSpam();
		new \ZeroSpam\Modules\ProjectHoneypot();
		new \ZeroSpam\Modules\ipstack();
		new \ZeroSpam\Modules\IPinfoModule();

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
	 */
	public function types( $types ) {
		$types['blocked'] = __( 'Blocked', 'zerospam' );

		return $types;
	}
}

Plugin::instance();
