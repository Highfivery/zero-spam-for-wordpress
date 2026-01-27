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
		// DEPRECATED
		self::$sections['general'] = array(
			'title' => __( 'General Settings', 'zero-spam' ),
		);

		// v5.4
		self::$sections['settings'] = array(
			'title' => __( 'Settings', 'zero-spam' ),
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
			if ( update_option( 'disallowed_keys', $text ) ) {
				// Prevent autoloading large options.
				// @see https://10up.github.io/Engineering-Best-Practices/php/#performance
				wp_cache_delete( 'disallowed_keys', 'options' );
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET autoload = %s WHERE option_name = %s", 'no', 'disallowed_keys' ) );
			}
		}
	}

	/**
	 * Updates blocked email domains with recommended settings.
	 */
	public static function update_blocked_email_domains() {
		$modules                           = self::get_settings_by_module();
		$recommended_blocked_email_domains = \ZeroSpam\Core\Utilities::blocked_email_domains();

		$new_settings = array();
		foreach ( $modules['settings'] as $key => $setting ) {
			if ( 'blocked_email_domains' === $key ) {
				$domains = trim( implode( "\n", $recommended_blocked_email_domains ) );
				if ( update_option( 'zerospam_blocked_email_domains', $domains ) ) {
					// Prevent autoloading large options.
					// @see https://10up.github.io/Engineering-Best-Practices/php/#performance
					wp_cache_delete( 'zerospam_blocked_email_domains', 'options' );
					global $wpdb;
					$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET autoload = %s WHERE option_name = %s", 'no', 'zerospam_blocked_email_domains' ) );
				}
			} else {
				$new_settings[ $key ] = isset( $setting['value'] ) ? $setting['value'] : false;
			}
		}

		if ( $new_settings ) {
			update_option( 'zero-spam-settings', $new_settings );
		}
	}

	/**
	 * Configures the plugin's recommended settings.
	 */
	public static function auto_configure() {
		$settings = self::get_settings();
		$modules  = self::get_settings_by_module();

		foreach ( $modules as $module => $setting ) {
			$recommended_settings = array();
			foreach ( $setting as $key => $args ) {
				$recommended_settings[ $key ] = isset( $args['value'] ) ? $args['value'] : false;
				if ( isset( $args['recommended'] ) ) {
					$recommended_settings[ $key ] = $args['recommended'];
				}
			}

			update_option( "zero-spam-$module", $recommended_settings );
		}

		update_option( 'zerospam_configured', 1 );
	}

	/**
	 * Returns settings by modules.
	 */
	public static function get_settings_by_module() {
		$settings = self::get_settings();
		$modules  = array();

		foreach ( $settings as $key => $setting ) {
			if ( ! array_key_exists( $setting['module'], $modules ) ) {
				$modules[ $setting['module'] ] = array(
					$key => $setting,
				);
			} else {
				$modules[ $setting['module'] ][ $key ] = $setting;
			}
		}

		return $modules;
	}

	/**
	 * Returns the plugin settings.
	 *
	 * @param string $key Setting key to retrieve.
	 */
	public static function get_settings( $key = false ) {
		$options = get_option( 'zero-spam-settings' );

		self::$settings['use_recommended_settings'] = array(
			'title'   => __( 'Use Recommended Settings', 'zero-spam' ),
			'desc'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Get maximum protection fast with our one-click configuration setup. Please enter any <a href="%1$s" target="_blank" rel="noreferrer noopener">supported plugins</a> are installed & activated first.  <strong>Performing this action will override all existing settings.', 'zero-spam' ),
					array(
						'rel'    => array(),
						'strong' => array(),
						'a'      => array(
							'href'   => array(),
							'class'  => array(),
							'target' => array(),
						),
					)
				),
				'https://github.com/Highfivery/zero-spam-for-wordpress/wiki/FAQ#what-plugins-are-supported-by-zero-spam-for-wordpress'
			),
			'module'  => 'settings',
			'section' => 'general',
			'type'    => 'html',
			'html'    => '', // Generated dynamically during render to avoid early nonce calls
		);

		self::$settings['share_data'] = array(
			'title'       => __( 'Usage Data Sharing', 'zero-spam' ),
			'desc'        => __( 'Help us catch more spam by sharing anonymous spam data. We never share personal information.', 'zero-spam' ),
			'module'      => 'settings',
			'section'     => 'general',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['share_data'] ) ? $options['share_data'] : false,
			'recommended' => 'enabled',
		);

		global $wp_roles;

		// Ensure roles are initialized
		if ( ! isset( $wp_roles ) || ! $wp_roles ) {
			$wp_roles = wp_roles();
		}

		$roles       = isset( $wp_roles->roles ) ? $wp_roles->roles : array();
		$roles_array = array();

		foreach ( $roles as $role => $data ) {
			$roles_array[ $role ] = $data['name'];
		}

		self::$settings['widget_visibility'] = array(
			'title'       => __( 'Dashboard Widget Visibility', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'select',
			'desc'        => __( 'Choose which admin users can see the spam statistics on the dashboard.', 'zero-spam' ),
			'options'     => $roles_array,
			'value'       => ! empty( $options['widget_visibility'] ) ? $options['widget_visibility'] : false,
			'recommended' => array( 'administrator' ),
			'multiple'    => true,
		);

		self::$settings['block_handler'] = array(
			'title'       => __( 'IP Block Handler', 'zero-spam' ),
			'desc'        => __( 'What happens when we block someone from visiting your site.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'radio',
			'options'     => array(
				'redirect' => __( 'Send them to another website', 'zero-spam' ),
				'403'      => __( 'Show them an error message', 'zero-spam' ),
			),
			'value'       => ! empty( $options['block_handler'] ) ? $options['block_handler'] : 403,
			'recommended' => 403,
		);

		self::$settings['block_method'] = array(
			'title'       => __( 'IP Block Method', 'zero-spam' ),
			'desc'        => __( 'How the plugin blocks bad visitors. PHP is safer and recommended.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'radio',
			'options'     => array(
				'htaccess_legacy' => __( '.htaccess (Apache servers < 2.4)', 'zero-spam' ),
				'htaccess_modern' => __( '.htaccess (Apache servers >= 2.4)', 'zero-spam' ),
				'php'             => __( 'PHP (Recommended)', 'zero-spam' ),
			),
			'value'       => ! empty( $options['block_method'] ) ? $options['block_method'] : 'php',
			'recommended' => 'php',
		);

		$message = __( 'Your IP address has been blocked due to detected spam/malicious activity.', 'zero-spam' );

		self::$settings['blocked_message'] = array(
			'title'       => __( 'Blocked Message', 'zero-spam' ),
			'desc'        => __( 'The message blocked visitors see if you chose "Show them an error message" above.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['blocked_message'] ) ? $options['blocked_message'] : $message,
			'recommended' => $message,
		);

		self::$settings['blocked_redirect_url'] = array(
			'title'       => __( 'Blocked Users Redirect', 'zero-spam' ),
			'desc'        => __( 'The website address to send blocked visitors to if you chose "Send them to another website" above.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'url',
			'field_class' => 'regular-text',
			'placeholder' => 'https://wordpress.org/plugins/zero-spam/',
			'value'       => ! empty( $options['blocked_redirect_url'] ) ? $options['blocked_redirect_url'] : 'https://wordpress.org/plugins/zero-spam/',
			'recommended' => 'https://wordpress.org/plugins/zero-spam/',
		);

		self::$settings['log_blocked_ips'] = array(
			'title'       => __( 'Log Blocked IPs', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of everyone we block. Turn off if you have a busy website to save database space.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_ips'] ) ? $options['log_blocked_ips'] : false,
			'recommended' => 'enabled',
		);

		self::$settings['max_logs'] = array(
			'title'       => __( 'Maximum Log Entries', 'zero-spam' ),
			'desc'        => __( 'How many blocked visitor records to keep. When this number is reached, the oldest records get deleted automatically.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'number',
			'field_class' => 'small-text',
			'placeholder' => 10000,
			'value'       => ! empty( $options['max_logs'] ) ? $options['max_logs'] : 10000,
			'recommended' => 10000,
		);

		self::$settings['ip_whitelist'] = array(
			'title'       => __( 'IP Whitelist', 'zero-spam' ),
			'desc'        => __( 'IP addresses that should never be blocked. Put one IP address per line.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'textarea',
			'field_class' => 'regular-text code',
			'placeholder' => '',
			'value'       => ! empty( $options['ip_whitelist'] ) ? trim( $options['ip_whitelist'] ) : false,
		);

		self::$settings['blocked_email_domains'] = array(
			'title'       => __( 'Blocked Email Domains', 'zero-spam' ),
			'desc'        => __( 'Block email addresses from these domains (like "spam.com"). Put one per line.', 'zero-spam' ),
			'section'     => 'general',
			'module'      => 'settings',
			'type'        => 'textarea',
			'field_class' => 'regular-text code',
			'placeholder' => '',
			'value'       => get_option( 'zerospam_blocked_email_domains', ! empty( $options['blocked_email_domains'] ) ? trim( $options['blocked_email_domains'] ) : false ),
		);

		self::$settings['update_blocked_email_domains'] = array(
			'title'   => __( 'Use Blocked Email Domains Recommendation', 'zero-spam' ),
			'desc'    => sprintf(
				wp_kses(
					__( '<strong>WARNING:</strong> This will override all existing blocked email domains with Zero Spam\'s recommended domains.', 'zero-spam' ),
					array(
						'strong' => array(),
					)
				)
			),
			'section' => 'general',
			'module'  => 'settings',
			'type'    => 'html',
			'html'    => '', // Generated dynamically during render to avoid early nonce calls
		);

		self::$settings['regenerate_honeypot'] = array(
			'title'   => __( 'Regenerate Honeypot ID', 'zero-spam' ),
			'desc'    => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Helpful if spam is getting through. Current honeypot ID: <code>%s</code>', 'zero-spam' ),
					array(
						'code' => array(),
					)
				),
				\ZeroSpam\Core\Utilities::get_honeypot()
			),
			'section' => 'general',
			'module'  => 'settings',
			'type'    => 'html',
			'html'    => '', // Generated dynamically during render
		);

		self::$settings['update_disallowed_words'] = array(
			'title'   => __( 'Override &amp; Update Core Disallowed Words', 'zero-spam' ),
			'desc'    => __( 'Update WP core\'s disallowed words option with <a href="https://github.com/splorp/wordpress-comment-blacklist/" target="_blank" rel="noreferrer noopener">splorp\'s Comment Blacklist for WordPress</a>. <strong>WARNING:</strong> This will override any existing words.', 'zero-spam' ),
			'section' => 'general',
			'module'  => 'settings',
			'type'    => 'html',
			'html'    => '', // Generated dynamically during render
		);

		$settings = apply_filters( 'zerospam_settings', self::$settings );

		if ( $key ) {
			if ( ! empty( $settings[ $key ]['value'] ) ) {
				return $settings[ $key ]['value'];
			}

			return false;
		}

		self::$settings['rescue_mode'] = array(
			'title'   => __( 'Rescue Mode', 'zero-spam' ),
			'section' => 'general',
			'module'  => 'settings',
			'type'    => 'html',
			'html'    => defined( 'ZEROSPAM_RESCUE_KEY' ) ?
				'<span style="color: green; font-weight: bold;">' . __( 'Active', 'zero-spam' ) . '</span> <span class="description">(' . __( 'Key defined in wp-config.php', 'zero-spam' ) . ')</span>' :
				'<span style="color: red;">' . __( 'Inactive', 'zero-spam' ) . '</span> <span class="description">(' . __( 'Define ZEROSPAM_RESCUE_KEY in wp-config.php to enable', 'zero-spam' ) . ')</span>',
			'desc'    => __( 'Rescue Mode allows administrators to bypass blocks by appending ?zerospam_rescue={KEY} to any URL.', 'zero-spam' ),
			'value'   => false,
		);

		return $settings;
	}
}
