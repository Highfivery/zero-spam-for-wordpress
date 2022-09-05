<?php
/**
 * Ipstack class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Ipstack
 */
class ipstack {
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
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_log_record', array( $this, 'log_record' ) );
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['ipstack'] = array(
			'title' => __( 'ipstack (geolocation)', 'zero-spam' ),
			'icon'  => 'assets/img/icon-ipstack.svg'
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-ipstack' );

		$settings['ipstack_api'] = array(
			'title'       => __( 'API Key', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %1$s: Replaced with the ipstack URL, %2$s: Replaced with the ipstack product URL */
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">ipstack API key</a> to enable geolocation features. Don\'t have an API key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://ipstack.com?fpr=zerospam' ),
				esc_url( 'https://ipstack.com/product?fpr=zerospam' )
			),
			'section'     => 'ipstack',
			'module'      => 'ipstack',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your ipstack API key.', 'zero-spam' ),
			'value'       => ! empty( $options['ipstack_api'] ) ? $options['ipstack_api'] : false,
		);

		$settings['ipstack_timeout'] = array(
			'title'       => __( 'API Timeout', 'zero-spam' ),
			'section'     => 'ipstack',
			'module'      => 'ipstack',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zero-spam' ),
			'placeholder' => __( '5', 'zero-spam' ),
			'desc'        => __( 'Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond; recommended 5 seconds.', 'zero-spam' ),
			'value'       => ! empty( $options['ipstack_timeout'] ) ? $options['ipstack_timeout'] : 5,
			'recommended' => 5,
		);

		$settings['ipstack_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'ipstack',
			'module'      => 'ipstack',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => __( '14', 'zero-spam' ),
			'desc'        => __( 'Setting to high could result in outdated information, too low could cause a decrease in performance; recommended 14 days.', 'zero-spam' ),
			'value'       => ! empty( $options['ipstack_cache'] ) ? $options['ipstack_cache'] : 14,
			'recommended' => 14,
		);

		return $settings;
	}

	/**
	 * Log record filter.
	 *
	 * @param array $record DB record entry.
	 */
	public static function log_record( $record ) {
		$location = self::get_geolocation( $record['user_ip'] );
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
	 * Get geolocation
	 *
	 * @param string $ip IP address.
	 */
	public static function get_geolocation( $ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['ipstack_api']['value'] ) ) {
			return false;
		}

		$cache_key = \ZeroSpam\Core\Utilities::cache_key(
			array(
				'ipstack',
				$ip,
			)
		);

		$result = wp_cache_get( $cache_key );
		if ( false === $result ) {
			$endpoint  = 'http://api.ipstack.com/';
			$endpoint .= $ip . '?access_key=' . $settings['ipstack_api']['value'];

			$timeout = 5;
			if ( ! empty( $settings['ipstack_timeout'] ) ) {
				$timeout = intval( $settings['ipstack_timeout']['value'] );
			}

			$response = \ZeroSpam\Core\Utilities::remote_get( $endpoint, array( 'timeout' => $timeout ) );
			if ( $response ) {
				$result = json_decode( $response, true );

				if ( ! empty( $result ) && ! empty( $result['error'] ) ) {
					\ZeroSpam\Core\Utilities::log( 'ipstack: ' . json_encode( $result['error'] ));

					return false;
				}

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
