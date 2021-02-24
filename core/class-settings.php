<?php
/**
 * Settings class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Settings.
 */
class Settings {
	/**
	 * Settings.
	 *
	 * Holds the plugin settings.
	 *
	 * @var Settings
	 */
	public static $settings = array();

	/**
	 * Sections.
	 *
	 * @var Settings
	 */
	public static $sections = array();

	/**
	 * Returns the plugin setting sections.
	 */
	public static function get_sections() {
		self::$sections['improve'] = array(
			'title' => __( 'Improve WordPress Zero Spam', 'zerospam' ),
		);

		self::$sections['general'] = array(
			'title' => __( 'General Settings', 'zerospam' ),
		);

		self::$sections['debug'] = array(
			'title' => __( 'Debug', 'zerospam' ),
		);

		return apply_filters( 'zerospam_setting_sections', self::$sections );
	}

	/**
	 * Returns the plugin settings.
	 *
	 * @param string $key Optional. Get the value for a specific setting.
	 */
	public static function get_settings( $key = false ) {
		$options = get_option( 'wpzerospam' );

		self::$settings['share_data'] = array(
			'title'   => __( 'Usage Data Sharing', 'zerospam' ),
			'section' => 'improve',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => sprintf(
					wp_kses(
						__( 'Become a super contributor by opting in to share non-sensitive plugin data. <a href="%s" target="_blank" rel="noreferrer noopener">Learn more</a>.', 'zerospam' ),
						array(
							'a'    => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
							),
						)
					),
					esc_url( 'https://github.com/bmarshall511/wordpress-zero-spam/wiki/FAQ#what-data-is-shared-when-usage-data-sharing-is-enabled' )
				),
			),
			'value'   => ! empty( $options['share_data'] ) ? $options['share_data'] : false,
		);

		self::$settings['block_handler'] = array(
			'title'   => __( 'IP Block Handler', 'zerospam' ),
			'desc'    => __( 'Determines how blocked IPs are handled when they attempt to access the site.', 'zerospam' ),
			'section' => 'general',
			'type'    => 'radio',
			'options' => array(
				'redirect' => __( 'Redirect user', 'zerospam' ),
				'403'      => sprintf(
					wp_kses(
						__( 'Display a <a href="%s" target="_blank" rel="noreferrer noopener"><code>403 Forbidden</code></a> error', 'zerospam' ),
						array(
							'code' => array(),
							'a'    => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
							),
						)
					),
					esc_url( 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/403' )
				)
			),
			'value'   => ! empty( $options['block_handler'] ) ? $options['block_handler'] : 403,
		);

		switch ( self::$settings['block_handler']['value'] ) {
			case 403:
				$message = __( 'Your IP address has been blocked by WordPress Zero Spam due to detected spam/malicious activity.', 'zerospam' );

				self::$settings['blocked_message'] = array(
					'title'       => __( 'Blocked Message', 'zerospam' ),
					'desc'        => __( 'The message that will be displayed to a blocked user.', 'zerospam' ),
					'section'     => 'general',
					'type'        => 'text',
					'field_class' => 'large-text',
					'placeholder' => $message,
					'value'       => ! empty( $options['blocked_message'] ) ? $options['blocked_message'] : $message,
				);
				break;
			case 'redirect':
				self::$settings['blocked_redirect_url'] = array(
					'title'       => __( 'Redirect for Blocked Users', 'zerospam' ),
					'desc'        => __( 'URL blocked users will be redirected to.', 'zerospam' ),
					'section'     => 'general',
					'type'        => 'url',
					'field_class' => 'regular-text',
					'placeholder' => 'https://wordpress.org/plugins/zero-spam/',
					'value'       => ! empty( $options['blocked_redirect_url'] ) ? $options['blocked_redirect_url'] : 'https://wordpress.org/plugins/zero-spam/',
				);
				break;
		}

		self::$settings['log_blocked_ips'] = array(
			'title'   => __( 'Log Blocked IPs', 'zerospam' ),
			'section' => 'general',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables logging IPs that are blocked from accessing the site. High traffic sites should leave this disabled.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['log_blocked_ips'] ) ? $options['log_blocked_ips'] : false,
		);

		self::$settings['ip_whitelist'] = array(
			'title'       => __( 'IP Whitelist', 'zerospam' ),
			'desc'        => __( 'Enter IPs that should be whitelisted (IPs that should never be blocked), one per line.', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'textarea',
			'field_class' => 'regular-text code',
			'placeholder' => '',
			'value'       => ! empty( $options['ip_whitelist'] ) ? $options['ip_whitelist'] : false,
		);

		self::$settings['debug'] = array(
			'title'   => __( 'Debug', 'zerospam' ),
			'desc'    => __( 'For troubleshooting site issues.', 'zerospam' ),
			'section' => 'debug',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['debug'] ) ? $options['debug'] : false,
		);

		if ( 'enabled' === self::$settings['debug']['value'] ) {
			self::$settings['debug_ip'] = array(
				'title'       => __( 'Debug IP', 'zerospam' ),
				'desc'        => __( 'Mock a IP address for debugging.', 'zerospam' ),
				'section'     => 'debug',
				'type'        => 'text',
				'placeholder' => '127.0.0.1',
				'value'       => ! empty( $options['debug_ip'] ) ? $options['debug_ip'] : false,
			);
		}

		$settings = apply_filters( 'zerospam_settings', self::$settings );

		if ( $key ) {
			if ( ! empty( $settings[ $key ]['value'] ) ) {
				return $settings[ $key ]['value'];
			}

			return false;
		}

		return $settings;
	}
}
