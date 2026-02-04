<?php
/**
 * IPInfo class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

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
			'icon'  => 'assets/img/icon-ipinfo.svg',
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * NOTE: This intentionally does NOT call wp_kses(). The Settings renderer is the single
	 * sanitizer/allowlist gatekeeper for html-type fields (prevents mismatched allowlists).
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-ipinfo' );

		// How It Works section.
		$how_it_works_features = array(
			esc_html__( 'Automatically identifies the location of every visitor to your site.', 'zero-spam' ),
			esc_html__( 'Shows you where spam and malicious activity is coming from.', 'zero-spam' ),
			esc_html__( 'Allows you to block entire countries, regions, or cities if needed.', 'zero-spam' ),
			esc_html__( 'Completely invisible to your visitors — they never know it\'s working.', 'zero-spam' ),
		);

		$how_it_works_features_html = '';
		foreach ( $how_it_works_features as $feature ) {
			$how_it_works_features_html .= '<li>' . $feature . '</li>';
		}

		$settings['ipinfo_how_it_works'] = array(
			'title'   => __( 'How It Works', 'zero-spam' ),
			'desc'    => '',
			'section' => 'ipinfo',
			'module'  => 'ipinfo',
			'type'    => 'html',
			'html'    => sprintf(
				'<p><strong>%1$s</strong></p><ol class="zerospam-list zerospam-list--decimal"><li>%2$s</li><li>%3$s</li><li>%4$s</li></ol><p><strong>%5$s</strong></p><ul class="zerospam-list zerospam-list--features">%6$s</ul>',
				esc_html__( 'Here\'s what happens behind the scenes:', 'zero-spam' ),
				esc_html__( 'When someone submits a form or comment, Zero Spam captures their IP address (a unique number that identifies their internet connection).', 'zero-spam' ),
				esc_html__( 'Zero Spam sends that IP address to IPinfo, which looks it up in their massive database and returns location information.', 'zero-spam' ),
				esc_html__( 'This information is saved with the spam detection log, so you can see patterns and make informed decisions about blocking.', 'zero-spam' ),
				esc_html__( 'Key Features:', 'zero-spam' ),
				$how_it_works_features_html
			),
		);

		// Why Use IPinfo section.
		$why_use_benefits = array(
			esc_html__( 'See where your spam is coming from on a map.', 'zero-spam' ),
			esc_html__( 'Block entire countries known for spam (like blocking all calls from a specific area code).', 'zero-spam' ),
			esc_html__( 'Identify patterns — if all your spam comes from one city, you can block it.', 'zero-spam' ),
			esc_html__( 'Free unlimited API calls with the Lite tier (no monthly limits).', 'zero-spam' ),
			esc_html__( 'More accurate than other free geolocation services.', 'zero-spam' ),
		);

		$why_use_benefits_html = '';
		foreach ( $why_use_benefits as $benefit ) {
			$why_use_benefits_html .= '<li>' . $benefit . '</li>';
		}

		$settings['ipinfo_why_use'] = array(
			'title'   => __( 'Why Use IPinfo?', 'zero-spam' ),
			'desc'    => '',
			'section' => 'ipinfo',
			'module'  => 'ipinfo',
			'type'    => 'html',
			'html'    => sprintf(
				'<p>%1$s</p><ul class="zerospam-list zerospam-list--features">%2$s</ul><p><strong>%3$s</strong> %4$s</p>',
				esc_html__( 'Without geolocation, you\'re fighting spam blindfolded. With IPinfo, you can:', 'zero-spam' ),
				$why_use_benefits_html,
				esc_html__( 'Real-world example:', 'zero-spam' ),
				esc_html__( 'If you run a local business in the United States and notice all your spam comes from overseas, you can block those countries entirely. Legitimate customers won\'t be affected, but spam will drop to zero.', 'zero-spam' )
			),
		);

		// Access Token field.
		$settings['ipinfo_access_token'] = array(
			'title'       => __( 'Access Token', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %1$s: Replaced with the IPInfo URL, %2$s: Replaced with the IPinfo signup URL */
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">IPinfo access token</a> to enable geolocation features. Don\'t have an API key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a> The free tier includes unlimited API calls — no credit card required.', 'zero-spam' ),
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

		// Cache Expiration field.
		$settings['ipinfo_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'ipinfo',
			'module'      => 'ipinfo',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => __( '14', 'zero-spam' ),
			'desc'        => __( 'How long to remember location information before checking again. 14 days is recommended — longer could show outdated data, shorter could slow down your site.', 'zero-spam' ),
			'value'       => ! empty( $options['ipinfo_cache'] ) ? $options['ipinfo_cache'] : 14,
			'recommended' => 14,
		);

		// How to Test section.
		$testing_steps = array(
			array(
				'title' => esc_html__( '1. Get your free API token', 'zero-spam' ),
				'desc'  => sprintf(
					/* translators: %s: IPinfo signup URL. */
					__( 'Visit <a href="%s" target="_blank" rel="noopener noreferrer">ipinfo.io/signup</a> and create a free account. Copy your access token from the dashboard.', 'zero-spam' ),
					esc_url( 'https://ipinfo.io/signup/' )
				),
			),
			array(
				'title' => esc_html__( '2. Enter your token above', 'zero-spam' ),
				'desc'  => esc_html__( 'Paste your access token into the "Access Token" field and save your settings.', 'zero-spam' ),
			),
			array(
				'title' => esc_html__( '3. Submit a test form', 'zero-spam' ),
				'desc'  => esc_html__( 'Leave a test comment or submit a contact form on your site.', 'zero-spam' ),
			),
			array(
				'title' => esc_html__( '4. Check the log', 'zero-spam' ),
				'desc'  => esc_html__( 'Go to Dashboard → Zero Spam → Log. You should see location information (country, city, etc.) for your test submission.', 'zero-spam' ),
			),
		);

		$testing_steps_html = '';
		foreach ( $testing_steps as $step ) {
			$testing_steps_html .= sprintf(
				'<li><strong>%1$s</strong> %2$s</li>',
				$step['title'],
				$step['desc']
			);
		}

		$troubleshooting_items = array(
			esc_html__( 'If location data shows as "Unknown", double-check that your access token is correct.', 'zero-spam' ),
			esc_html__( 'Make sure you\'re using the free Lite API token (not a paid plan token).', 'zero-spam' ),
			sprintf(
				/* translators: %s: zerospam.log file path. */
				__( 'Check %s in your uploads folder for any IPinfo error messages.', 'zero-spam' ),
				'<code>wp-content/uploads/zerospam.log</code>'
			),
		);

		$troubleshooting_html = '';
		foreach ( $troubleshooting_items as $item ) {
			$troubleshooting_html .= '<li>' . $item . '</li>';
		}

		$settings['ipinfo_testing'] = array(
			'title'   => __( 'How to Test', 'zero-spam' ),
			'desc'    => '',
			'section' => 'ipinfo',
			'module'  => 'ipinfo',
			'type'    => 'html',
			'html'    => sprintf(
				'<p><strong>%1$s</strong></p><ol class="zerospam-list zerospam-list--steps">%2$s</ol><p><strong>%3$s</strong></p><ul class="zerospam-list zerospam-list--features">%4$s</ul>',
				esc_html__( 'Follow these simple steps to verify IPinfo is working:', 'zero-spam' ),
				$testing_steps_html,
				esc_html__( 'Troubleshooting:', 'zero-spam' ),
				$troubleshooting_html
			),
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

				$countries = \ZeroSpam\Core\Utilities::countries();
				if ( ! empty( $countries[ $record['country'] ] ) ) {
					$record['country_name'] = $countries[ $record['country'] ];
				}
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
	 * Get geolocation information via IPinfo Lite API.
	 *
	 * Uses the Lite API (unlimited for free tier) instead of Legacy API (50k/month limit).
	 * Implements two-tier caching: persistent transients and object cache.
	 *
	 * @param string $ip IP address.
	 * @return array|false Normalized location array or false on failure.
	 */
	public static function get_geolocation( $ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( empty( $settings['ipinfo_access_token']['value'] ) ) {
			return false;
		}

		// Generate cache keys.
		$cache_key      = \ZeroSpam\Core\Utilities::cache_key(
			array(
				'ipinfo',
				$ip,
			)
		);
		$transient_key = 'zerospam_ipinfo_' . md5( $ip );

		// Check object cache first (non-persistent, fastest).
		$result = wp_cache_get( $cache_key );
		if ( false !== $result ) {
			return $result;
		}

		// Check transient cache (persistent, reduces API calls).
		$result = get_transient( $transient_key );
		if ( false !== $result ) {
			// Store in object cache for this request.
			$expiration = 14 * DAY_IN_SECONDS;
			if ( ! empty( $settings['ipinfo_cache']['value'] ) ) {
				$expiration = $settings['ipinfo_cache']['value'] * DAY_IN_SECONDS;
			}
			wp_cache_set( $cache_key, $result, 'zerospam', $expiration );
			return $result;
		}

		// Make API request to Lite endpoint (unlimited for free tier).
		$url = 'https://api.ipinfo.io/lite/' . rawurlencode( $ip ) . '?token=' . rawurlencode( $settings['ipinfo_access_token']['value'] );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		// Handle errors.
		if ( is_wp_error( $response ) ) {
			\ZeroSpam\Core\Utilities::log( 'IPinfo API error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Handle quota exceeded (429) - should not happen with Lite API, but log if it does.
		if ( 429 === (int) $code ) {
			\ZeroSpam\Core\Utilities::log( 'IPinfo API quota exceeded (429). This should not happen with Lite API. Check your token and endpoint.' );
			return false;
		}

		// Handle other non-200 responses.
		if ( 200 !== (int) $code ) {
			\ZeroSpam\Core\Utilities::log( 'IPinfo API returned status code: ' . $code );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Validate response data.
		if ( ! is_array( $data ) || empty( $data['country_code'] ) ) {
			\ZeroSpam\Core\Utilities::log( 'IPinfo API returned invalid or incomplete data for IP: ' . $ip );
			return false;
		}

		// Normalize response to match existing data structure.
		$result = array(
			'country' => $data['country_code'],
			'region'  => isset( $data['region'] ) ? $data['region'] : '',
			'city'    => isset( $data['city'] ) ? $data['city'] : '',
			'postal'  => isset( $data['postal'] ) ? $data['postal'] : '',
		);

		// Parse latitude and longitude from 'loc' field.
		if ( ! empty( $data['loc'] ) ) {
			$loc = explode( ',', $data['loc'], 2 );
			if ( 2 === count( $loc ) ) {
				$result['latitude']  = trim( $loc[0] );
				$result['longitude'] = trim( $loc[1] );
			}
		}

		// Add optional fields if available.
		if ( ! empty( $data['hostname'] ) ) {
			$result['hostname'] = $data['hostname'];
		}

		if ( ! empty( $data['org'] ) ) {
			$result['org'] = $data['org'];
		}

		if ( ! empty( $data['timezone'] ) ) {
			$result['timezone'] = $data['timezone'];
		}

		// Add country name for consistency with log_record method.
		$countries = \ZeroSpam\Core\Utilities::countries();
		if ( ! empty( $countries[ $result['country'] ] ) ) {
			$result['country_name'] = $countries[ $result['country'] ];
		}

		// Cache the result in both transient and object cache.
		$expiration = 14 * DAY_IN_SECONDS;
		if ( ! empty( $settings['ipinfo_cache']['value'] ) ) {
			$expiration = $settings['ipinfo_cache']['value'] * DAY_IN_SECONDS;
		}

		set_transient( $transient_key, $result, $expiration );
		wp_cache_set( $cache_key, $result, 'zerospam', $expiration );

		return $result;
	}
}
