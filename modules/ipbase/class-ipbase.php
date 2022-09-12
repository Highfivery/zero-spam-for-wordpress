<?php
/**
 * Adds integration for ipbase.com IP lookup service
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\ipbase;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * ipbase
 */
class ipbase {
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
		add_filter( 'zero_spam_ip_address_details', array( $this, 'ip_address_details' ), 10, 2 );
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['ipbase'] = array(
			'title' => __( 'ipbase (geolocation)', 'zero-spam' ),
			'icon'  => 'modules/ipbase/icon-ipbase.svg',
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-ipbase' );

		$settings['ipbase_api_key'] = array(
			'title'       => __( 'API Key', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %1$s: Replaced with the ipbase URL */
					__( 'Enter your ipbase API key. Don\'t have an API key? <a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://app.ipbase.com/register' )
			),
			'module'      => 'ipbase',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your ipbase API key.', 'zero-spam' ),
			'value'       => ! empty( $options['ipbase_api_key'] ) ? $options['ipbase_api_key'] : false,
		);

		$settings['ipbase_api_timeout'] = array(
			'title'       => __( 'API Timeout', 'zero-spam' ),
			'module'      => 'ipbase',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zero-spam' ),
			'placeholder' => __( '5', 'zero-spam' ),
			'desc'        => __( 'Controls how long to wait for the api to return a response, 5 seconds is recommended. Too high could result in degraded performance, too low & it won\'t have time to respond.', 'zero-spam' ),
			'value'       => ! empty( $options['ipbase_api_timeout'] ) ? $options['ipbase_api_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['ipbase_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'module'      => 'ipbase',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => __( 'Number of days', 'zero-spam' ),
			'desc'        => __( 'Controls how long IP data is cached, 30 days is recommended. Too low could result in degraded performance.', 'zero-spam' ),
			'value'       => ! empty( $options['ipbase_cache'] ) ? $options['ipbase_cache'] : 30,
			'recommended' => 30,
		);

		return $settings;
	}

	/**
	 * Returns an IP addresses' details
	 *
	 * @param string $ip_address_details IP address details.
	 */
	public function ip_address_details( $ip_address, $ip_address_details ) {
		$response_mapping = array(
			'country_code' => 'country_code',
			'country_name' => 'country_name',
			'region_code'  => 'region_code',
			'region_name'  => 'region_name',
			'city'         => 'city',
			'zip_code'     => 'zip',
			'time_zone'    => 'timezone',
			'latitude'     => 'latitude',
			'longitude'    => 'longitude',
		);

		$api_response = self::query_ip_address( $ip_address );
		if ( $api_response ) {
			foreach ( $response_mapping as $api_key => $details_key ) {
				if ( ! empty( $api_response[ $api_key ] ) ) {
					$ip_address_details[ $details_key ] = sanitize_text_field( $api_response[ $api_key ] );
				}
			}
		}

		return $ip_address_details;
	}

	/**
	 * Query an IP address
	 *
	 * @param string $ip_address IP address to query.
	 */
	public static function query_ip_address( $ip_address ) {
		$plugin_settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $plugin_settings['ipbase_api_key']['value'] ) || ! rest_is_ip_address( $ip_address ) ) {
			return false;
		}

		$queried_cache_key = \ZeroSpam\Core\Utilities::cache_key(
			array(
				'ipinfo',
				$ip,
			)
		);

		$result = wp_cache_get( $queried_cache_key );
		if ( false === $result ) {
			$endpoint  = esc_url( "https://api.ipbase.com/json/$ip_address?apikey=" . $plugin_settings['ipbase_api_key']['value'] );

			$response_timeout = 5;
			if ( ! empty( $settings['ipbase_api_timeout'] ) ) {
				$timeout = intval( $settings['ipbase_api_timeout']['value'] );
			}

			$response = \ZeroSpam\Core\Utilities::remote_get( $endpoint, array( 'timeout' => $timeout ) );
			if ( $response ) {
				$result = json_decode( $response, true );

				if ( empty( $result ) || ! empty( $result['message'] ) ) {
					\ZeroSpam\Core\Utilities::log( 'ipbase_api: ' . $result['message'] );
					return false;
				}

				$cache_expiration = 14 * DAY_IN_SECONDS;
				if ( ! empty( $settings['ipbase_cache']['value'] ) ) {
					$expiration = $settings['ipbase_cache']['value'] * DAY_IN_SECONDS;
				}

				wp_cache_set( $cache_key, $result, 'zerospam', $expiration );
			}
		}

		return $result;
	}
}
