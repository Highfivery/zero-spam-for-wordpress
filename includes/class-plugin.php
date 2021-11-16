<?php
/**
 * Main plugin class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Main plugin class
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

			// Fires when WordPress Zero Spam was fully loaded and instantiated.
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
		 * Fires on WordPress Zero Spam init, after WordPress Zero Spam has finished
		 * loading but before any headers are sent.
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
		// Database functionality.
		new \ZeroSpam\Includes\DB();

		// Stop Forum Spam module.
		new \ZeroSpam\Modules\StopForumSpam();

		// Project Honeypot module.
		new \ZeroSpam\Modules\ProjectHoneypot();

		// Zero Spam module.
		new \ZeroSpam\Modules\Zero_Spam();

		// ipstack module.
		new \ZeroSpam\Modules\ipstack();

		// IPinfo module.
		new \ZeroSpam\Modules\IPinfoModule();

		// David Walsh module.
		new \ZeroSpam\Modules\DavidWalsh\DavidWalsh();

		// WordPress registration module.
		new \ZeroSpam\Modules\Registration\Registration();

		// WordPress comments module.
		new \ZeroSpam\Modules\Comments\Comments();

		// WordPress login module.
		new \ZeroSpam\Modules\Login\Login();

		// Used to check if a plugin is installed & active.
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		// GiveWP plugin module.
		if ( is_plugin_active( 'give/give.php' ) ) {
			new \ZeroSpam\Modules\Give\Give();
		}

		// Contact Form 7 plugin module.
		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			new \ZeroSpam\Modules\ContactForm7\ContactForm7();
		}

		// WPForms plugin module.
		if (
			is_plugin_active( 'wpforms-lite/wpforms.php' ) ||
			is_plugin_active( 'wpforms/wpforms.php' )
		) {
			new \ZeroSpam\Modules\WPForms\WPForms();
		}

		// Formidable plugin module.
		if ( is_plugin_active( 'formidable/formidable.php' ) ) {
			new \ZeroSpam\Modules\Formidable\Formidable();
		}

		// Fluent Forms plugin module.
		if ( is_plugin_active( 'fluentform/fluentform.php' ) ) {
			new \ZeroSpam\Modules\FluentForms\FluentForms();
		}

		// MemberPress plugin module.
		if ( is_plugin_active( 'memberpress/memberpress.php' ) ) {
			new \ZeroSpam\Modules\MemberPress\MemberPress();
		}

		// Preform the firewall access check.
		if ( ! is_admin() && is_main_query() ) {
			new \ZeroSpam\Core\Access();
		}

		// If in admin, loaded needed classes.
		if ( is_admin() ) {
			// Plugin admin module.
			new \ZeroSpam\Core\Admin\Admin();

			// Google API module.
			new \ZeroSpam\Modules\Google();
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Types of detections.
	 */
	public function types( $types ) {
		$types['blocked'] = __( 'Blocked', 'zerospam' );

		return $types;
	}
}

Plugin::instance();
