<?php
/**
 * Debug class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Debug admin module
 */
class Debug {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ), -1 );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_get_ip', array( $this, 'debug_ip' ), 10, 1 );
	}

	/**
	 * Updates the visitor IP to the debug IP
	 *
	 * @param string $ip IP address.
	 */
	public function debug_ip( $ip ) {
		// Read from the registry built once per request: this runs on every User::get_ip().
		$debug_ip = \ZeroSpam\Core\Settings_Schema::value( 'debug_ip' );

		if (
			'enabled' === \ZeroSpam\Core\Settings_Schema::value( 'debug' ) &&
			! empty( $debug_ip )
		) {
			return $debug_ip;
		}

		return $ip;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['debug'] = array(
			'title' => __( 'Debug', 'zero-spam' ),
			'icon'  => 'assets/img/icon-bug.svg',
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-debug' );

		$settings['debug'] = array(
			'title'   => __( 'Debug', 'zero-spam' ),
			'desc'    => __( 'When enabled, provides verbose logging & allows the site admin to test an IP address access.', 'zero-spam' ),
			'section' => 'debug',
			'module'  => 'debug',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => false,
			),
			'value'   => ! empty( $options['debug'] ) ? $options['debug'] : false,
		);

		$settings['debug_ip'] = array(
			'title'       => __( 'Debug IP', 'zero-spam' ),
			'desc'        => __( 'Pretend every visitor has this IP address, to test what Zero Spam does with it. While debug is on, this can block real visitors. Recommended: leave empty unless you are testing.', 'zero-spam' ),
			'section'     => 'debug',
			'module'      => 'debug',
			'type'        => 'text',
			'placeholder' => '127.0.0.1',
			'value'       => ! empty( $options['debug_ip'] ) ? $options['debug_ip'] : false,
		);

		return $settings;
	}
}
