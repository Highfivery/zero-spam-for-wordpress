<?php
/**
 * Zero Spam class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Zero Spam
 */
class Zero_Spam {

	/**
	 * The zerospam.org API endpoint
	 */
	const API_ENDPOINT = ZEROSPAM_URL . 'wp-json/zerospam/v1/';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );

		// Fires when a user is blocked from accessing the site.
		add_action( 'zerospam_share_blocked', array( $this, 'share_blocked' ), 10, 1 );

		// Fires when a user submission has been detected as spam.
		add_action( 'zerospam_share_detection', array( $this, 'share_detection' ), 10, 1 );
	}

	/**
	 * Sections
	 */
	public function sections( $sections ) {
		$sections['zerospam'] = array(
			'title' => __( 'Zero Spam Enhanced Protection', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Settings
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['zerospam'] = array(
			'title'       => __( 'Zero Spam', 'zerospam' ),
			'section'     => 'zerospam',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'desc'        => sprintf(
				wp_kses(
					__( 'Checks user IPs & submissions against <a href="%s" target="_blank" rel="noopener noreferrer">Zero Spam</a>\'s blacklist.', 'zerospam' ),
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
			'title'       => __( 'Zero Spam License Key', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam license key</a> to enable enhanced premium protection. Don\'t have an license key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one now!</strong></a>', 'zerospam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( ZEROSPAM_URL ),
				esc_url( ZEROSPAM_URL . 'product/premium/' )
			),
			'section'     => 'zerospam',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your Zero Spam license key.', 'zerospam' ),
			'value'       => ! empty( $options['zerospam_license'] ) ? $options['zerospam_license'] : false,
		);

		$settings['zerospam_timeout'] = array(
			'title'       => __( 'Zero Spam API Timeout', 'zerospam' ),
			'section'     => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zerospam' ),
			'placeholder' => __( '5', 'zerospam' ),
			'min'         => 0,
			'desc'        => __( 'Recommended setting is 5 seconds. Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond.', 'zerospam' ),
			'value'       => ! empty( $options['zerospam_timeout'] ) ? $options['zerospam_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['zerospam_cache'] = array(
			'title'       => __( 'Zero Spam Cache Expiration', 'zerospam' ),
			'section'     => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zerospam' ),
			'placeholder' => __( WEEK_IN_SECONDS, 'zerospam' ),
			'min'         => 0,
			'desc'        => __( 'Recommended setting is 14 days. Setting to high could result in outdated information, too low could cause a decrease in performance.', 'zerospam' ),
			'value'       => ! empty( $options['zerospam_cache'] ) ? $options['zerospam_cache'] : 14,
			'recommended' => 14,
		);

		$settings['zerospam_confidence_min'] = array(
			'title'       => __( 'Zero Spam Confidence Minimum', 'zerospam' ),
			'section'     => 'zerospam',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( '%', 'zerospam' ),
			'placeholder' => __( '30', 'zerospam' ),
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'desc'      => sprintf(
				wp_kses(
					__( 'Recommended setting is 20%%. Minimum <a href="%s" target="_blank" rel="noopener noreferrer">confidence score</a> an IP must meet before being blocked. Setting this too low could cause users to be blocked that shouldn\'t be.', 'zerospam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( ZEROSPAM_URL . 'spam-blacklist-api/#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
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
		$api_data                   = array();
		$api_data['site_url']       = site_url();
		$api_data['admin_email']    = get_bloginfo( 'admin_email' );
		$api_data['wp_version']     = get_bloginfo( 'version' );
		$api_data['site_name']      = get_bloginfo( 'name' );
		$api_data['site_desc']      = get_bloginfo( 'description' );
		$api_data['site_language']  = get_bloginfo( 'language' );
		$api_data['plugin_version'] = ZEROSPAM_VERSION;

		return $api_data;
	}

	/**
	 * Share detection details with zerospam.org
	 *
	 * @param array $data Contains all detection details.
	 */
	public function share_detection( $data ) {
		$endpoint = self::API_ENDPOINT . 'add-detection/';

		$ip = \ZeroSpam\Core\User::get_ip();

		if ( ! $ip || ! $data || ! is_array( $data ) || empty( $data['type'] ) ) {
			return false;
		}

		$api_data = array(
			'user_ip' => $ip,
			'type'    => trim( sanitize_text_field( $data['type'] ) ),
			'data'    => array(),
		);

		// Loop through & clean the data.
		foreach ( $data as $key => $value ) {
			$api_data['data'][ $key ] = trim( sanitize_text_field( $value ) );
		}

		// Attempt to get the geolocation information.
		$api_data['location'] = ZeroSpam\Modules\ipstack::get_geolocation( $ip );

		$global_data = self::global_api_data();
		$api_data    = array_merge( $api_data, $global_data );

		// Send the data to zerospam.org.
		$args = array(
			'body' => $api_data,
		);

		wp_remote_post( $endpoint, $args );
	}

	/**
	 * Share blocked details with zerospam.org
	 *
	 * @param array $access Contains all access details.
	 */
	public function share_blocked( $access ) {
		$endpoint = self::API_ENDPOINT . 'add-blocked/';

		// Only send details if the user was blocked.
		if ( empty( $access['blocked'] ) ) {
			return false;
		}

		$api_data = array( 'checks' => array() );
		$ip       = ! empty( $access['ip'] ) ? $access['ip'] : false;
		$details  = ! empty( $access['details'] ) ? $access['details'] : false;

		// Require an IP address.
		if ( ! $ip ) {
			return false;
		}

		$api_data['user_ip'] = $ip;

		// Attempt to get the geolocation information.
		$api_data['location'] = ZeroSpam\Modules\ipstack::get_geolocation( $ip );

		// Only send $details that were blocked.
		if ( $details && is_array( $details ) ) {
			foreach ( $details as $check_key => $check_details ) {
				if (
					! empty( $check_details['blocked'] ) &&
					! empty( $check_details['type'] )
				) {
					// User didn't pass the $check_key check.
					$api_data['checks'][ $check_key ] = array(
						'type' => $check_details['type'],
					);

					// Add additional details if available.
					if ( ! empty( $check_details['details'] && is_array( $check_details['details'] ) ) ) {
						$details_data = $check_details['details'];

						// Add country if not already set and available.
						if (
							empty( $api_data['location']['country_code'] ) &&
							! empty( $details_data['country'] ) &&
							2 === strlen( $details_data['country'] )
						) {
							$api_data['location']['country_code'] = $details_data['country'];
						}
					}
				}
			}
		}

		// Only query the API if there's data to be sent.
		if ( ! empty( $api_data['checks'] ) ) {
			$global_data = self::global_api_data();
			$api_data    = array_merge( $api_data, $global_data );

			// Send the data to zerospam.org.
			$args = array(
				'body' => $api_data,
			);

			wp_remote_post( $endpoint, $args );
		}
	}
}
