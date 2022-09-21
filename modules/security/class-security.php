<?php
/**
 * Site security
 *
 * Implement Zero Spam's recommended WordPress security practices.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Security;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Security class
 */
class Security {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );

		// It can be considered a security risk to make your WP version visible &
		// public you should hide it.
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'opml_head', 'the_generator' );

		// XML-RPC can significantly amplify the brute-force attacks.
		add_filter( 'xmlrpc_enabled', '__return_false' );

		// Fired on detections.
		add_action( 'zero_spam_detection', array( $this, 'handle_detection' ), 10, 2 );

		// Block XMLRPC. Accessing this file can allow an attacker to exhaust your
		// server’s resources quite easily as well as potentially enumerate your
		// WordPress authors and brute force your WordPress logins among other
		// vectors.
		add_action( 'init', array( $this, 'block_xmlrpc' ) );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'remove_resource_query_parameters' )
		) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis' ) );
		}

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'disable_emojis' )
		) {
			add_filter( 'style_loader_src', array( $this, 'remove_resource_query_params' ), 10, 2);
			add_filter( 'script_loader_src', array( $this, 'remove_resource_query_params' ), 10, 2);
		}

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'disable_rss_feed' )
		) {
			add_action( 'do_feed', array( $this, 'disable_rss'), 1 );
			add_action( 'do_feed_rdf', array( $this, 'disable_rss'), 1 );
			add_action( 'do_feed_rss', array( $this, 'disable_rss'), 1 );
			add_action( 'do_feed_rss2', array( $this, 'disable_rss'), 1 );
			add_action( 'do_feed_atom', array( $this, 'disable_rss'), 1 );
			add_action( 'do_feed_rss2_comments', array( $this, 'disable_rss'), 1 );
			add_action( 'do_feed_atom_comments', array( $this, 'disable_rss'), 1 );
			add_filter(
				'the_generator',
				function() {
					return '';
				}
			);
		}
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['security'] = array(
			'title' => __( 'Security', 'zero-spam' ),
			'icon'  => 'modules/security/icon-security.svg',
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-security' );

		$settings['remove_resource_query_parameters'] = array(
			'title'       => __( 'Remove Resource Query Parameters', 'zero-spam' ),
			'desc'    =>  wp_kses(
				__( 'Web scanners love the <code>&ver=x.x.x</code> type arguments that are appended to your CSS and JS files. This is useful for caching systems and implementing this change could affect the quality of your cache. As long as you are aware of the effects or risks, there really shouldn’t be any other detrimental effects.', 'zero-spam' ),
				array(
					'code'   => array(),
					'strong' => array(),
					'a'      => array(
						'target' => array(),
						'href'   => array(),
						'rel'    => array(),
					),
				)
			),
			'module'      => 'security',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['remove_resource_query_parameters'] ) ? $options['remove_resource_query_parameters'] : false,
		);

		$settings['disable_emojis'] = array(
			'title'       => __( 'Disable WordPress Emoj\'s', 'zero-spam' ),
			'desc'    =>  wp_kses(
				__( 'WordPress emoji’s are one of the vectors scanners use in order to enumerate version information, disable them if you\'re not using them.', 'zero-spam' ),
				array(
					'code'   => array(),
					'strong' => array(),
					'a'      => array(
						'target' => array(),
						'href'   => array(),
						'rel'    => array(),
					),
				)
			),
			'module'      => 'security',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['disable_emojis'] ) ? $options['disable_emojis'] : false,
			'recommended' => 'enabled',
		);

		$settings['disable_rss_feed'] = array(
			'title'       => __( 'Disable WordPress RSS Feed', 'zero-spam' ),
			'desc'    =>  wp_kses(
				__( 'Having the RSS feed exposed is another way that scanners use to detect your WordPress version as well as other pertinent information such as authors, disable it if you\'re not using it.', 'zero-spam' ),
				array(
					'code'   => array(),
					'strong' => array(),
					'a'      => array(
						'target' => array(),
						'href'   => array(),
						'rel'    => array(),
					),
				)
			),
			'module'      => 'security',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['disable_rss_feed'] ) ? $options['disable_rss_feed'] : false,
		);

		return $settings;
	}

	/**
	 * Disables emojis
	 */
	public function disable_emojis( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		} else {
			return array();
		}
	}

	/**
	 * Disables RSS feeds
	 */
	public function disable_rss() {
		wp_die( __( 'No feed available.', 'zero-spam' ) );
	}

	/**
	 * Removes resource query parameters
	 */
	public function remove_resource_query_params( $src ) {
		if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Handles detections.
	 *
	 * @param array $details Detection details.
	 */
	public function handle_detection( $details ) {

	}

	/**
	 * Block access to xmlrpc.php
	 */
	public function block_xmlrpc() {
		$current_url = rtrim( $_SERVER['REQUEST_URI'], '/' );
		add_filter(
			'bloginfo_url',
			function( $output, $property ) {
				return ( $property == 'pingback_url' ) ? null : $output;
			},
			11,
			2
		);

		add_filter( 'xmlrpc_enabled', '__return_false' );

		if ( strpos( $current_url, '/xmlrpc.php' ) !== false ) {
			status_header( 404 );
			nocache_headers();
			wp_die( __('This file is not accessible.', 'zero-spam') );
		}
	}
}
