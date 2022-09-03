<?php
/**
 * Google maps class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Google maps
 */
class Google {
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

		$api_key = \ZeroSpam\Core\Settings::get_settings( 'google_api' );
		if ( ! empty( $api_key ) ) {
			add_action( 'zerospam_google_map', array( $this, 'map' ), 10, 2 );
		}
	}

	/**
	 * Embeds a map
	 */
	public function map( $coordinates ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

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
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['google'] = array(
			'title' => __( 'Google Map', 'zero-spam' ),
			'icon'  => 'assets/img/icon-google.svg'
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-google' );

		$settings['google_api'] = array(
			'title'       => __( 'Google API Key', 'zero-spam' ),
			'section'     => 'google',
			'module'      => 'google',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your Google API key.', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %1$s: Replaced with the Google API key URL */
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">Google API key</a> for Google Maps integration.', 'zero-spam' ),
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
