<?php
/**
 * Settings class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Settings
 */
class Settings {

	/**
	 * Settings
	 *
	 * @var Settings
	 */
	public static $settings = array();

	/**
	 * Sections
	 *
	 * @var Sections
	 */
	public static $sections = array();

	/**
	 * Returns the plugin setting sections
	 */
	public static function get_sections() {
		self::$sections['general'] = array(
			'title' => __( 'General Settings', 'zerospam' ),
		);

		return apply_filters( 'zerospam_setting_sections', self::$sections );
	}

	/**
	 * Updates core disallowed words.
	 */
	public static function update_disallowed_words() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$text = $wp_filesystem->get_contents( ZEROSPAM_PATH . 'assets/blacklist.txt' );

		if ( $text ) {
			update_option( 'disallowed_keys', $text );
		}
	}

	/**
	 * Updates blocked email domains with recommended settings.
	 */
	public static function update_blocked_email_domains() {
		$settings                          = self::get_settings();
		$recommended_blocked_email_domains = \ZeroSpam\Core\Utilities::blocked_email_domains();

		$new_settings = array();
		foreach ( $settings as $key => $setting ) {
			if ( 'blocked_email_domains' === $key ) {
				$new_settings[ $key ] = implode( "\n", $recommended_blocked_email_domains );
			} else {
				$new_settings[ $key ] = isset( $setting['value'] ) ? $setting['value'] : false;
			}
		}

		if ( $new_settings ) {
			update_option( 'wpzerospam', $new_settings );
		}
	}

	/**
	 * Configures the plugin's recommended settings.
	 */
	public static function auto_configure() {
		$settings = self::get_settings();

		$recommended_settings = array();
		foreach ( $settings as $key => $setting ) {
			$recommended_settings[ $key ] = isset( $setting['value'] ) ? $setting['value'] : false;
			if ( isset( $setting['recommended'] ) ) {
				$recommended_settings[ $key ] = $setting['recommended'];
			}
		}

		if ( $recommended_settings ) {
			update_option( 'wpzerospam', $recommended_settings );
			update_option( 'zerospam_configured', 1 );
		}
	}

	/**
	 * Returns the plugin settings.
	 *
	 * @param string $key Setting key to retrieve.
	 */
	public static function get_settings( $key = false ) {
		$options = get_option( 'wpzerospam' );

		self::$settings['use_recommended_settings'] = array(
			'title'   => __( 'Use Recommended Settings', 'zerospam' ),
			'desc'    => sprintf(
				wp_kses(
					__( '<strong>WARNING:</strong> This will override all existing settings.', 'zerospam' ),
					array(
						'strong' => array(),
					)
				)
			),
			'section' => 'general',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( '<a href="%s" class="button">Override &amp; Update Settings</a>', 'zerospam' ),
					array(
						'a' => array(
							'href'  => array(),
							'class' => array(),
						),
					)
				),
				wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=autoconfigure' ), 'autoconfigure', 'zerospam' )
			),
		);

		self::$settings['share_data'] = array(
			'title'       => __( 'Usage Data Sharing', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => sprintf(
					wp_kses(
						/* translators: %s: url */
						__( 'Join <a href="%1$s" target="_blank" rel="noreferrer noopener">Zero Spam\'s global community</a> &amp; report detections by opting in to share non-sensitive data. <a href="%2$s" target="_blank" rel="noreferrer noopener">Learn more</a>.', 'zerospam' ),
						array(
							'a'    => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
							),
						)
					),
					esc_url( ZEROSPAM_URL . '?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=data_sharing' ),
					esc_url( 'https://github.com/bmarshall511/wordpress-zero-spam/wiki/FAQ#what-data-is-shared-when-usage-data-sharing-is-enabled' )
				),
			),
			'value'       => ! empty( $options['share_data'] ) ? $options['share_data'] : false,
			'recommended' => 'enabled',
		);

		global $wp_roles;
		$roles       = $wp_roles->roles;
		$roles_array = array();

		foreach ( $roles as $role => $data ) {
			$roles_array[ $role ] = $data['name'];
		}

		self::$settings['widget_visibility'] = array(
			'title'       => __( 'Dashboard Widget Visibility', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'select',
			'desc'        => __( 'Select which user roles have access to the admin dashboard widget. You may control-click (Windows) or command-click (Mac) to select more than one.', 'zerospam' ),
			'options'     => $roles_array,
			'value'       => ! empty( $options['widget_visibility'] ) ? $options['widget_visibility'] : false,
			'recommended' => array( 'administrator' ),
			'multiple'    => true,
		);

		self::$settings['block_handler'] = array(
			'title'       => __( 'IP Block Handler', 'zerospam' ),
			'desc'        => __( 'Determines how blocked IPs are handled when they attempt to access the site.', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'radio',
			'options'     => array(
				'redirect' => __( 'Redirect user', 'zerospam' ),
				'403'      => sprintf(
					wp_kses(
						/* translators: %s: url */
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
				),
			),
			'value'       => ! empty( $options['block_handler'] ) ? $options['block_handler'] : 403,
			'recommended' => 403,
		);

		self::$settings['block_method'] = array(
			'title'       => __( 'IP Block Method', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( '.htaccess is preferred for performance, however <strong>choosing the wrong Apache version or adding <a href="%s" target="_blank" rel="noreferrer noopener">more than 8190 characters</a> could cause the website to crash</strong> and require a manual fix to the .htaccess file. If this happens &amp; you\'re unsure how to fix, contact <a href="%s" target="_blank" rel="noreferrer noopener">Highfivery</a> for a rapid response and resolution.', 'zerospam' ),
					array(
						'strong' => array(),
						'a'    => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://httpd.apache.org/docs/current/en/configuring.html' ),
				esc_url( 'https://www.highfivery.com/?utm_source=' . get_bloginfo( 'url' ) . '&utm_medium=zerospam_plugin_htaccess&utm_campaign=zerospam_plugin' )
			),
			'section'     => 'general',
			'type'        => 'radio',
			'options'     => array(
				'htaccess_legacy' => __( '.htaccess (Apache servers < 2.4)', 'zerospam' ),
				'htaccess_modern' => __( '.htaccess (Apache servers >= 2.4)', 'zerospam' ),
				'php'             => __( 'PHP', 'zerospam' ),
			),
			'value'       => ! empty( $options['block_method'] ) ? $options['block_method'] : 'php',
			'recommended' => 'php',
		);

		$message = __( 'Your IP address has been blocked due to detected spam/malicious activity.', 'zerospam' );

		self::$settings['blocked_message'] = array(
			'title'       => __( 'Blocked Message', 'zerospam' ),
			'desc'        => __( 'The message displayed to blocked users when \'Display a 403 Forbidden error\' is selected.', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['blocked_message'] ) ? $options['blocked_message'] : $message,
			'recommended' => $message,
		);

		self::$settings['blocked_redirect_url'] = array(
			'title'       => __( 'Blocked Users Redirect', 'zerospam' ),
			'desc'        => __( 'The URL blocked users are redirected to when \'Redirect user\' is selected.', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'url',
			'field_class' => 'regular-text',
			'placeholder' => 'https://wordpress.org/plugins/zero-spam/',
			'value'       => ! empty( $options['blocked_redirect_url'] ) ? $options['blocked_redirect_url'] : 'https://wordpress.org/plugins/zero-spam/',
			'recommended' => 'https://wordpress.org/plugins/zero-spam/',
		);

		self::$settings['log_blocked_ips'] = array(
			'title'       => __( 'Log Blocked IPs', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'checkbox',
			'desc'        => __( 'Enables logging IPs that are blocked from accessing the site.', 'zerospam' ),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_ips'] ) ? $options['log_blocked_ips'] : false,
			'recommended' => 'enabled',
		);

		self::$settings['max_logs'] = array(
			'title'       => __( 'Maximum Log Entries', 'zerospam' ),
			'desc'        => __( 'The maximum number of log entries when logging is enabled. When the maximum is reached, the oldest entries will be deleted.', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'number',
			'field_class' => 'small-text',
			'placeholder' => 10000,
			'value'       => ! empty( $options['max_logs'] ) ? $options['max_logs'] : 10000,
			'recommended' => 10000,
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

		self::$settings['blocked_email_domains'] = array(
			'title'       => __( 'Blocked Email Domains', 'zerospam' ),
			'desc'        => __( 'Enter a list of email domains that should be blocked, one per line.', 'zerospam' ),
			'section'     => 'general',
			'type'        => 'textarea',
			'field_class' => 'regular-text code',
			'placeholder' => '',
			'value'       => ! empty( $options['blocked_email_domains'] ) ? $options['blocked_email_domains'] : false,
		);

		self::$settings['update_blocked_email_domains'] = array(
			'title'   => __( 'Use Blocked Email Domains Recommendation', 'zerospam' ),
			'desc'    => sprintf(
				wp_kses(
					__( '<strong>WARNING:</strong> This will override all existing blocked email domains with Zero Spam\'s recommended domains.', 'zerospam' ),
					array(
						'strong' => array(),
					)
				)
			),
			'section' => 'general',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( '<a href="%s" class="button">Override &amp; Update Blocked Email Domains</a>', 'zerospam' ),
					array(
						'a'    => array(
							'href'  => array(),
							'class' => array(),
						),
					)
				),
				wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=update-blocked-emails' ), 'update-blocked-emails', 'zerospam' )
			),
		);

		self::$settings['regenerate_honeypot'] = array(
			'title'   => __( 'Regenerate Honeypot ID', 'zerospam' ),
			'desc'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Helpful if spam is getting through. Current honeypot ID: <code>%s</code>', 'zerospam' ),
					array(
						'code' => array(),
					)
				),
				\ZeroSpam\Core\Utilities::get_honeypot()
			),
			'section' => 'general',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( '<a href="%s" class="button">Regenerate Honeypot ID</a>', 'zerospam' ),
					array(
						'a' => array(
							'href'  => array(),
							'class' => array(),
						),
					)
				),
				wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=regenerate-honeypot' ), 'regenerate-honeypot', 'zerospam' )
			),
		);

		self::$settings['update_disallowed_words'] = array(
			'title'   => __( 'Override &amp; Update Core Disallowed Words', 'zerospam' ),
			'desc'    => __( 'Update WP core\'s disallowed words option with <a href="https://github.com/splorp/wordpress-comment-blacklist/" target="_blank" rel="noreferrer noopener">splorp\'s Comment Blacklist for WordPress</a>. <strong>WARNING:</strong> This will override any existing words.', 'zerospam' ),
			'section' => 'general',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( '<a href="%s" class="button">Override &amp; Update Core Disallowed Words</a>', 'zerospam' ),
					array(
						'a'    => array(
							'href'  => array(),
							'class' => array(),
						),
					)
				),
				wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=update-disallowed-words' ), 'update-disallowed-words', 'zerospam' )
			),
		);

		$settings = apply_filters( 'zerospam_settings', self::$settings, $options );

		if ( $key ) {
			if ( ! empty( $settings[ $key ]['value'] ) ) {
				return $settings[ $key ]['value'];
			}

			return false;
		}

		return $settings;
	}
}
