<?php
/**
 * Botscout class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * BotScout.
 *
 * @since 5.0.0
 */
class BotScout {
	/**
	 * Botscout constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );

		add_filter( 'zerospam_access_checks', array( $this, 'access_check' ), 10, 3 );
	}

	/**
	 * Botscout access_check.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function access_check( $access_checks, $user_ip, $settings ) {
		$access_checks['botscout'] = array(
			'blocked' => false,
		);

		if ( empty( $settings['botscout_api']['value'] ) ) {
			return $access_checks;
		}

		$cache_key = ZeroSpam\Core\Utilities::cache_key(
			array(
				'botscout',
				$user_ip,
			)
		);

		$result = wp_cache_get( $cache_key );
		if ( false === $result ) {
			$endpoint = 'https://botscout.com/test/?';
			$params   = array(
				'ip'  => $user_ip,
				'key' => $settings['botscout_api']['value'],
			);
			$endpoint = $endpoint . http_build_query( $params );

			$timeout = 5;
			if ( ! empty( $settings['botscout_timeout'] ) ) {
				$timeout = intval( $settings['botscout_timeout']['value'] );
			}

			$response = ZeroSpam\Core\Utilities::remote_get( $endpoint, array( 'timeout' => $timeout ) );


			print_r($response);
		}

		return $access_checks;
	}

	/**
	 * Botscout sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['botscout'] = array(
			'title' => __( 'BotScout Integration', 'zerospam' ),
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

		$settings['botscout_api'] = array(
			'title'       => __( 'BotScout API Key', 'zerospam' ),
			'section'     => 'botscout',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your free BotScout API key.', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					__( 'Enter your BotScout API key to check user IPs against <a href="%1$s" target="_blank" rel="noopener noreferrer">BotScout</a>\'s blacklist. Don\'t have an API key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>', 'zerospam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://botscout.com/#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' ),
				esc_url( 'https://botscout.com/getkey.htm#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'value'       => ! empty( $options['botscout_api'] ) ? $options['botscout_api'] : false,
		);

		$settings['botscout_timeout'] = array(
			'title'       => __( 'BotScout API Timeout', 'zerospam' ),
			'section'     => 'botscout',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'seconds', 'zerospam' ),
			'placeholder' => __( '5', 'zerospam' ),
			'desc'        => __( 'Recommended setting is 5 seconds. Setting to high could result in degraded site performance, too low won\'t allow to API enough time to respond.', 'zerospam' ),
			'value'       => ! empty( $options['botscout_timeout'] ) ? $options['botscout_timeout'] : 5,
		);

		return $settings;
	}
}
