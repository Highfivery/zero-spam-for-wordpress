<?php
/**
 * ipstack class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * ipstack.
 *
 * @since 5.0.0
 */
class ipstack {
	/**
	 * ipstack constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_log_record', array( $this, 'log_record' ) );
	}

	/**
	 * ipstack sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['ipstack'] = array(
			'title' => __( 'ipstack Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Botscout settings.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['ipstack_api'] = array(
			'title'       => __( 'ipstack API Key', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">ipstack API key</a> to enable location-based statistics. Don\'t have an API key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>', 'zerospam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://ipstack.com/#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' ),
				esc_url( 'https://ipstack.com/signup/free#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'section'     => 'ipstack',
			'type'        => 'text',
			'class'       => 'regular-text',
			'placeholder' => __( 'Enter your ipstack API key.', 'zerospam' ),
			'value'       => ! empty( $options['ipstack_api'] ) ? $options['ipstack_api'] : false,
		);

		$settings['ipstack_timeout'] = array(
			'title'       => __( 'ipstack API Timeout', 'zerospam' ),
			'section'     => 'ipstack',
			'type'        => 'number',
			'class'       => 'small-text',
			'suffix'      => __( 'seconds', 'zerospam' ),
			'placeholder' => __( '5', 'zerospam' ),
			'desc'        => __( 'Recommended setting is 5 seconds. Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond.', 'zerospam' ),
			'value'       => ! empty( $options['ipstack_timeout'] ) ? $options['ipstack_timeout'] : 5,
		);

		$settings['ipstack_cache'] = array(
			'title'       => __( 'ipstack Cache Expiration', 'zerospam' ),
			'section'     => 'ipstack',
			'type'        => 'number',
			'class'       => 'small-text',
			'suffix'      => __( 'day(s)', 'zerospam' ),
			'placeholder' => __( '14', 'zerospam' ),
			'desc'        => __( 'Recommended setting is 14 days. Setting to high could result in outdated information, too low could cause a decrease in performance.', 'zerospam' ),
			'value'       => ! empty( $options['ipstack_cache'] ) ? $options['ipstack_cache'] : 14,
		);

		return $settings;
	}

	/**
	 * Log record filter.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function log_record( $record ) {
		$location = self::get_geolocation( ZeroSpam\Core\User::get_ip() );
		if ( $location ) {
			if ( ! empty( $location['country_code'] ) ) {
				$record['country'] = $location['country_code'];
			}

			if ( ! empty( $location['country_name'] ) ) {
				$record['country_name'] = $location['country_name'];
			}

			if ( ! empty( $location['region_code'] ) ) {
				$record['region'] = $location['region_code'];
			}

			if ( ! empty( $location['region_name'] ) ) {
				$record['region_name'] = $location['region_name'];
			}

			if ( ! empty( $location['city'] ) ) {
				$record['city'] = $location['city'];
			}

			if ( ! empty( $location['latitude'] ) ) {
				$record['latitude'] = $location['latitude'];
			}

			if ( ! empty( $location['longitude'] ) ) {
				$record['longitude'] = $location['longitude'];
			}

			if ( ! empty( $location['zip'] ) ) {
				$record['zip'] = $location['zip'];
			}
		}

		return $record;
	}

	/**
	 * Get geolocation.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function get_geolocation( $ip ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['ipstack_api']['value'] ) ) {
			return false;
		}

		$cache_key = ZeroSpam\Core\Utilities::cache_key(
			array(
				'ipstack',
				$ip,
			)
		);

		$result = wp_cache_get( $cache_key );
		if ( false === $result ) {
			$endpoint = 'http://api.ipstack.com/';
			$endpoint .= $ip . '?access_key=' . $settings['ipstack_api']['value'];

			$timeout = 5;
			if ( ! empty( $settings['ipstack_timeout'] ) ) {
				$timeout = intval( $settings['ipstack_timeout']['value'] );
			}

			$response = ZeroSpam\Core\Utilities::remote_get( $endpoint, array( 'timeout' => $timeout ) );
			if ( $response ) {
				$result = json_decode( $response, true );

				$expiration = 14 * DAY_IN_SECONDS;
				if ( ! empty( $settings['ipstack_cache']['value'] ) ) {
					$expiration = $settings['ipstack_cache']['value'] * DAY_IN_SECONDS;
				}
				wp_cache_set( $cache_key, $result, 'zerospam', $expiration );
			}
		}

		return $result;
	}
}
