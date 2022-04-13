<?php
/**
 * Zero Spam class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Zero Spam
 */
class Zero_Spam {

	/**
	 * The zerospam.org API endpoint
	 */
	const API_ENDPOINT = ZEROSPAM_URL . 'wp-json/zero-spam-store/v1/';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 2 );

		// Displays any available admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'check_notice_dismissal' ) );

		// Fires when a user submission has been detected as spam.
		add_action( 'zerospam_share_detection', array( $this, 'share_detection' ), 10, 1 );
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['zerospam'] = array(
			'title' => __( 'Zero Spam Enhanced Protection', 'zero-spam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 * @param array $options  Array of saved database options.
	 */
	public function settings( $settings, $options ) {
		$settings['zerospam_info'] = array(
			'section' => 'zerospam',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					/* translators: %1s: Replaced with the Zero Spam URL, %2$s: Replaced with the DDoD attack wiki URL */
					__( '<h3 style="margin-top: 0">Enabling enhanced protection is highly recommended.</h3><p>Enhanced protection adds additional checks using one of the largest, most comprehensive, constantly-growing global malicious IP, email, and username databases available, the  <a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam Blacklist</a>. Once enabled, all visitors will be checked against this blacklist that includes protected forms containing email and username fields &mdash; giving you the peace of mind that submissions are coming from legitimate. It can also help prevent <a href="%2$s" target="_blank" rel="noopener noreferrer">DDoS attacks</a> &amp; fraudsters looking to test stolen credit card numbers.</p>', 'zero-spam' ),
					array(
						'h3'     => array(
							'style' => array(),
						),
						'p'      => array(),
						'a'      => array(
							'href'  => array(),
							'class' => array(),
						),
						'strong' => array(),
					)
				),
				esc_url( ZEROSPAM_URL . '?utm_source=' . site_url() . '&utm_medium=admin_zerospam_info&utm_campaign=wpzerospam' ),
				esc_url( 'https://en.wikipedia.org/wiki/Denial-of-service_attack' )
			),
		);

		$settings['zerospam'] = array(
			'title'       => __( 'Status', 'zero-spam' ),
			'section'     => 'zerospam',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: Replaced with the Zero Spam URL */
					__( 'Blocks visitor IPs, email addresses &amp; usernames that have been reported to <a href="%s" target="_blank" rel="noopener noreferrer">Zero Spam</a>.', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( ZEROSPAM_URL . '?utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'value'       => ! empty( $options['zerospam'] ) ? $options['zerospam'] : false,
			'recommended' => 'enabled',
		);

		$settings['zerospam_license'] = array(
			'title'       => __( 'License Key', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: 1: the zerospam.org URL 2: the zerospam.org premium product URL */
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam</a> license key or define it in <code>wp-config.php</code>, using the constant <code>ZEROSPAM_LICENSE_KEY</code> to enable enhanced protection. Don\'t have an license key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one now!</strong></a>', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
						'code'   => array(),
					)
				),
				esc_url( ZEROSPAM_URL ),
				esc_url( ZEROSPAM_URL . 'product/premium/' )
			),
			'section'     => 'zerospam',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your Zero Spam license key.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_license'] ) ? $options['zerospam_license'] : false,
		);

		if ( defined( 'ZEROSPAM_LICENSE_KEY' ) && ! $settings['zerospam_license']['value'] ) {
			$settings['zerospam_license']['value'] = ZEROSPAM_LICENSE_KEY;
		}

		$settings['zerospam_timeout'] = array(
			'title'       => __( 'API Timeout', 'zero-spam' ),
			'section'     => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zero-spam' ),
			'placeholder' => __( '5', 'zero-spam' ),
			'min'         => 0,
			'desc'        => __( 'Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond; recommended 5 seconds.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_timeout'] ) ? $options['zerospam_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['zerospam_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => WEEK_IN_SECONDS,
			'min'         => 0,
			'desc'        => __( 'Setting to high could result in outdated information, too low could cause a decrease in performance; recommended 14 days.', 'zero-spam' ),
			'value'       => ! empty( $options['zerospam_cache'] ) ? $options['zerospam_cache'] : 14,
			'recommended' => 14,
		);

		$settings['zerospam_confidence_min'] = array(
			'title'       => __( 'Confidence Minimum', 'zero-spam' ),
			'section'     => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( '%', 'zero-spam' ),
			'placeholder' => __( '30', 'zero-spam' ),
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: Replaced with the Zero Spam API URL */
					__( 'Minimum <a href="%s" target="_blank" rel="noopener noreferrer">confidence score</a> an IP must meet before being blocked. Setting this too low could cause users to be blocked that shouldn\'t be; recommended 20%%.', 'zero-spam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( ZEROSPAM_URL . 'spam-blacklist-api/?utm_source=' . site_url() . '&utm_medium=admin_confidence_score&utm_campaign=wpzerospam' )
			),
			'value'       => ! empty( $options['zerospam_confidence_min'] ) ? $options['zerospam_confidence_min'] : 30,
			'recommended' => 30,
		);

		return $settings;
	}

	/**
	 * Global API data.
	 */
	public function global_api_data() {
		$api_data                         = array();
		$api_data['reporter_email']       = sanitize_email( get_bloginfo( 'admin_email' ) );
		$api_data['domain']               = esc_url( site_url() );
		$api_data['wp_version']           = sanitize_text_field( get_bloginfo( 'version' ) );
		$api_data['wp_admin_email']       = sanitize_email( get_bloginfo( 'admin_email' ) );
		$api_data['wp_zero_spam_version'] = sanitize_text_field( ZEROSPAM_VERSION );
		$api_data['wp_language']          = sanitize_text_field( strtolower( get_bloginfo( 'language' ) ) );
		$api_data['wp_site_name']         = sanitize_text_field( get_bloginfo( 'name' ) );
		$api_data['wp_site_tagline']      = sanitize_text_field( get_bloginfo( 'description' ) );

		return $api_data;
	}

	/**
	 * Share detection details with zerospam.org
	 *
	 * @param array $data Contains all detection details.
	 */
	public function share_detection( $data ) {
		// Check to make sure a report hasn't been submitted recently.
		$last_api_report_submitted = get_site_option( 'zero_spam_last_api_request' );

		if ( $last_api_report_submitted ) {
			$last_api_report_submitted = new \DateTime( $last_api_report_submitted );
			$current_time              = new \DateTime();

			$minutes_diff = $last_api_report_submitted->diff( $current_time );
			if ( $minutes_diff->i < 5 ) {
				return false;
			}
		}

		$endpoint = self::API_ENDPOINT . 'submit-report/';

		$ip = \ZeroSpam\Core\User::get_ip();

		if ( ! $ip || ! $data || ! is_array( $data ) || empty( $data['type'] ) ) {
			return false;
		}

		// Define the data to send to the report API.
		$compliant = sanitize_text_field( $data['type'] );
		if ( ! empty( $data['failed'] ) ) {
			$compliant .= ' - ' . sanitize_text_field( $data['failed'] );
		}

		$api_data = array(
			'report_type' => 'ip_address',
			'ip_address'  => sanitize_text_field( $ip ),
			'compliant'   => sanitize_text_field( $compliant ),
		);

		// Add specially defined data to the API report.

		// From comments.
		if ( ! empty( $data['comment_author_email'] ) && is_email( $data['comment_author_email'] ) ) {
			$api_data['email_address'] = sanitize_email( $data['comment_author_email'] );

			if ( ! empty( $data['comment_author'] ) ) {
				$api_data['email_name'] = sanitize_text_field( $data['comment_author'] );
			}
		}

		// From registration.
		if ( ! empty( $data['user_email'] ) && is_email( $data['user_email'] ) ) {
			$api_data['email_address'] = sanitize_email( $data['user_email'] );
		}

		// From WooCommerce registration.
		if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
			$api_data['email_address'] = sanitize_email( $data['email'] );
		}

		if ( ! empty( $data['post'] ) ) {
			// From MemberPress.
			if ( ! empty( $data['post']['user_email'] ) && is_email( $data['post']['user_email'] ) ) {
				$api_data['email_address'] = sanitize_email( $data['post']['user_email'] );
			}

			// From Mailchimp for WordPress.
			if ( ! empty( $data['post']['EMAIL'] ) && is_email( $data['post']['EMAIL'] ) ) {
				$api_data['email_address'] = sanitize_email( $data['post']['EMAIL'] );
			}
		}

		if ( ! empty( $data['data'] ) ) {
			// From GiveWP.
			if ( ! empty( $data['data']['give_email'] ) && is_email( $data['data']['give_email'] ) ) {
				$api_data['email_address'] = sanitize_email( $data['post']['give_email'] );
			}
		}

		// Add data that should be included in every API report.
		$global_data = self::global_api_data();
		$api_data    = array_merge( $api_data, $global_data );

		// Send the data to zerospam.org.
		$args = array(
			'body' => array( 'data' => $api_data ),
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			//echo $response->get_error_message();
		}

		update_site_option( 'zero_spam_last_api_request', current_time( 'mysql' ) );
	}

	/**
	 * Returns license key data from the API
	 *
	 * @param string $license The license key.
	 */
	public static function get_license( $license ) {
		$cache_key    = sanitize_title( 'license_' . $license );
		$license_data = wp_cache_get( $cache_key );
		if ( false === $license_data ) {
			$endpoint = ZEROSPAM_URL . 'wp-json/v1/get-license';
			$args     = array(
				'body' => array( 'license_key' => $license ),
			);

			$license_data = \ZeroSpam\Core\Utilities::remote_post( $endpoint, $args );

			if ( $license_data ) {
				$license_data = json_decode( $license_data, true );

				if ( ! empty( $license_data['license_key'] ) ) {
					$expiration = 1 * MONTH_IN_SECONDS;
					wp_cache_set( $cache_key, $license_data, 'zero_spam_store', $expiration );
				}
			}
		}

		return $license_data;
	}

	/**
	 * Checks if a notice should be dismissed.
	 */
	public function check_notice_dismissal() {
		// @codingStandardsIgnoreLine
		if ( isset( $_GET['zero-spam-dismiss-notice-enhanced-protection'] ) ) {
			$user_id = get_current_user_id();
			add_user_meta( $user_id, 'zero_spam_dismiss_notice_enhanced_protection', current_time( 'mysql' ), true );
			// @codingStandardsIgnoreLine
		} elseif ( isset( $_GET['zero-spam-dismiss-notice-license'] ) ) {
			add_user_meta( $user_id, 'zero_spam_dismiss_notice_missing_license', current_time( 'mysql' ), true );
		}
	}

	/**
	 * Outputs any available admin notices.
	 */
	public function admin_notices() {
		$settings = \ZeroSpam\Core\Settings::get_settings();
		$user_id  = get_current_user_id();

		$is_zerospam_enabled = 'enabled' === $settings['zerospam']['value'] ? true : false;

		$classes = array();

		if ( $is_zerospam_enabled ) {
			$license = ! empty( $settings['zerospam_license']['value'] ) ? $settings['zerospam_license']['value'] : false;
			if ( ! $license ) {
				$message_dismissed = get_user_meta( $user_id, 'zero_spam_dismiss_notice_missing_license', true );
				if ( $message_dismissed ) {
					$days_since_last_dismissed = \ZeroSpam\Core\Utilities::time_since( $message_dismissed, current_time( 'mysql' ), 'd' );

					if ( $days_since_last_dismissed <= 7 ) {
						return;
					}
				}
			} else {
				// Check license.
				$license_data = self::get_license( $license );
				if ( ! empty( $license_data['license_key'] ) ) {
					return;
				}
			}

			$classes[] = 'notice-error';

			$content = '<p>' . sprintf(
				wp_kses(
					/* translators: %1$s: Zero Spam settings URL, %2$s: dismiss message URL */
					__( '<strong>Your site is vulnerable to attacks.</strong> Please enter a valid <a href="%1$s" target="_blank" rel="noreferrer noopener"><strong>Zero Spam license key</strong></a> under <a href="%2$s">Zero Spam Enhanced Protection</a>. <a href="%3$s">Dismiss</a>', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'href' => array(),
						),
					)
				),
				esc_url( esc_url( ZEROSPAM_URL . 'subscribe/' ), ),
				esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ),
				esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zero-spam-dismiss-notice-license' ) ),
			) . '</p>';
		} else {
			$message_dismissed = get_user_meta( $user_id, 'zero_spam_dismiss_notice_enhanced_protection', true );
			if ( $message_dismissed ) {
				$days_since_last_dismissed = \ZeroSpam\Core\Utilities::time_since( $message_dismissed, current_time( 'mysql' ), 'd' );

				if ( $days_since_last_dismissed <= 7 ) {
					return false;
				}
			}

			$classes[] = 'notice-warning';

			$content = '<p>' . sprintf(
				wp_kses(
					/* translators: %1$s: Zero Spam settings URL, %2$s: dismiss message URL */
					__( '<strong>Your site is vulnerable to attacks.</strong> For enhanced protection, please enable <a href="%1$s"><strong>Zero Spam Enhanced Protection</strong></a>. <a href="%2$s">Dismiss</a>', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'href' => array(),
						),
					)
				),
				esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ),
				esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zero-spam-dismiss-notice-enhanced-protection' ) ),
			) . '</p>';
		}
		?>
		<div class="notice is-dismissible <?php echo implode( ' ', $classes ); ?>">
			<?php
			// @codingStandardsIgnoreLine
			echo $content;
			?>
		</div>
		<?php
	}

	/**
	 * Query the Zero Spam Blacklist API
	 *
	 * @param array $params Array of query parameters.
	 */
	public static function query( $params ) {
		if (
			empty( $params['ip'] ) &&
			empty( $params['username'] ) &&
			empty( $params['email'] )
		) {
			return false;
		}

		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['zerospam_license']['value'] ) ) {
			return false;
		}

		$cache_array = array( 'zero_spam' );
		$cache_array = array_merge( $cache_array, $params );
		$cache_key   = \ZeroSpam\Core\Utilities::cache_key( $cache_array );

		$response = wp_cache_get( $cache_key );
		if ( false === $response ) {
			$endpoint = 'https://www.zerospam.org/wp-json/v1/query';

			$args = array(
				'body' => array(
					'license_key' => $settings['zerospam_license']['value'],
				),
			);

			if ( ! empty( $params['ip'] ) ) {
				$args['body']['ip'] = $params['ip'];
			}

			$args['timeout'] = 5;
			if ( ! empty( $settings['zerospam_timeout'] ) ) {
				$args['timeout'] = intval( $settings['zerospam_timeout']['value'] );
			}

			$response = \ZeroSpam\Core\Utilities::remote_post( $endpoint, $args );
			if ( $response ) {
				// Response should be a JSON string.
				$response = json_decode( $response, true );

				if (
					! is_array( $response ) ||
					empty( $response['status'] ) ||
					'success' !== $response['status'] ||
					empty( $response['result'] )
				) {
					if ( ! empty( $response['result'] ) ) {
						\ZeroSpam\Core\Utilities::log( $response['result'] );
					} else {
						\ZeroSpam\Core\Utilities::log( __( 'There was a problem querying the Zero Spam Blacklist API.', 'zero-spam' ) );
					}

					return false;
				}

				$response = $response['result'];

				$expiration = 14 * DAY_IN_SECONDS;
				if ( ! empty( $settings['zerospam_confidence_min']['value'] ) ) {
					$expiration = $settings['zerospam_confidence_min']['value'] * DAY_IN_SECONDS;
				}

				wp_cache_set( $cache_key, $response, 'zerospam', $expiration );
			}
		}

		return $response;
	}
}
