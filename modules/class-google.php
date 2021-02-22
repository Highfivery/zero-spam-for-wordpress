<?php
/**
 * Google maps class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Google maps.
 *
 * @since 5.0.0
 */
class Google {
	/**
	 * Google maps constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );

		$settings = ZeroSpam\Core\Settings::get_settings();
		if ( ! empty( $settings['google_api']['value'] ) ) {
			add_action( 'zerospam_google_map', array( $this, 'map' ), 10, 2 );
		}
	}

	/**
	 * Embeds a map;
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function map( $coordinates ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		if ( ! empty( $settings['google_api']['value'] ) ) {
			$url        = 'https://www.google.com/maps/embed/v1/place?';
			$url_params = array(
				'key' => $settings['google_api']['value'],
				'q'   => $coordinates,
			);
			$url .= http_build_query( $url_params );

			$api_key
			?>
			<iframe
				width="100%"
				height="200"
				style="border:0"
				loading="lazy"
				allowfullscreen
				src="<?php echo esc_url( $url ); ?>">
			</iframe>
			<?php
		}
	}

	/**
	 * Google maps sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['google'] = array(
			'title' => __( 'Google Integration', 'zerospam' ),
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

		$settings['google_api'] = array(
			'title'       => __( 'Google API Key', 'zerospam' ),
			'section'     => 'google',
			'type'        => 'text',
			'class'       => 'regular-text',
			'placeholder' => __( 'Enter your Google API key.', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">Google API key</a> for Google Maps integration.', 'zerospam' ),
					array(
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://developers.google.com/maps/documentation/embed/get-api-key' ),
			),
			'value'       => ! empty( $options['google_api'] ) ? $options['google_api'] : false,
		);

		return $settings;
	}
}
