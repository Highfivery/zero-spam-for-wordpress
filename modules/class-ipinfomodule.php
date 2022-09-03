<?php
/**
 * IPInfo class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use \ipinfo\ipinfo\IPinfo;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * IPInfo
 */
class IPinfoModule {
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
		$sections['ipinfo'] = array(
			'title' => __( 'IPinfo (geolocation)', 'zero-spam' ),
			'icon'  => 'assets/img/icon-ipinfo.svg'
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-ipinfo' );

		$settings['ipinfo_access_token'] = array(
			'title'       => __( 'Access Token', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %1$s: Replaced with the IPInfo URL, %2$s: Replaced with the IPinfo signup URL */
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">IPinfo access token</a> to enable geolocation features. Don\'t have an API key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>', 'zero-spam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://ipinfo.io/' ),
				esc_url( 'https://ipinfo.io/signup/' )
			),
			'section'     => 'ipinfo',
			'module'      => 'ipinfo',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your IPinfo access token.', 'zero-spam' ),
			'value'       => ! empty( $options['ipinfo_access_token'] ) ? $options['ipinfo_access_token'] : false,
		);

		$settings['ipinfo_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'ipinfo',
			'module'      => 'ipinfo',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => __( '14', 'zero-spam' ),
			'desc'        => __( 'Setting to high could result in outdated information, too low could cause a decrease in performance; recommended 14 days.', 'zero-spam' ),
			'value'       => ! empty( $options['ipinfo_cache'] ) ? $options['ipinfo_cache'] : 14,
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
			$location = json_decode( wp_json_encode( $location ), true );

			if ( ! empty( $location['country'] ) ) {
				$record['country'] = $location['country'];
			}

			if ( ! empty( $location['region'] ) ) {
				$record['region_name'] = $location['region'];
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

			if ( ! empty( $location['postal'] ) ) {
				$record['zip'] = $location['postal'];
			}
		}

		return $record;
	}

	/**
	 * Get geolocation information
	 *
	 * @param string $ip IP address.
	 */
	public static function get_geolocation( $ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['ipinfo_access_token']['value'] ) ) {
			return false;
		}

		$cache_key = \ZeroSpam\Core\Utilities::cache_key(
			array(
				'ipinfo',
				$ip,
			)
		);

		$result = wp_cache_get( $cache_key );
		if ( false === $result ) {
			// Load the IPinfo library.
			require_once ZEROSPAM_PATH . 'vendor/autoload.php';

			try {
				$client = new IPinfo( $settings['ipinfo_access_token']['value'] );
				$result = $client->getDetails( $ip );
			} catch ( \ipinfo\ipinfo\IPinfoException $e ) {
				\ZeroSpam\Core\Utilities::log( 'ipinfo: ' . $e->__toString() );
			} catch ( Exception $e ) {
				\ZeroSpam\Core\Utilities::log( 'ipinfo: ' . $e->__toString() );
			}

			if ( $result ) {
				$result     = json_decode( wp_json_encode( $result ), true );
				$expiration = 14 * DAY_IN_SECONDS;
				if ( ! empty( $settings['ipinfo_cache']['value'] ) ) {
					$expiration = $settings['ipinfo_cache']['value'] * DAY_IN_SECONDS;
				}
				wp_cache_set( $cache_key, $result, 'zerospam', $expiration );
			}
		}

		return $result;
	}
}
