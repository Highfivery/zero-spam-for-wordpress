<?php
/**
 * Project Honeypot httpBL class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Project Honeypot httpBL
 */
class ProjectHoneypot {
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

		if ( \ZeroSpam\Core\Access::process() ) {
			add_filter( 'zerospam_access_checks', array( $this, 'access_check' ), 10, 2 );
		}
	}

	/**
	 * Project Honeypot access check
	 *
	 * @param array  $access_checks Current access checks array.
	 * @param string $user_ip       IP address to check.
	 */
	public function access_check( $access_checks, $user_ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		$access_checks['project_honeypot'] = array(
			'blocked' => false,
		);

		if (
			empty( $settings['project_honeypot']['value'] ) ||
			'enabled' !== $settings['project_honeypot']['value']
		) {
			return $access_checks;
		}

		$response = self::query( $user_ip );
		if ( $response ) {
			if (
				! empty( $response['threat_score'] ) &&
				! empty( $settings['project_honeypot_score_min']['value'] ) &&
				floatval( $response['threat_score'] ) >= floatval( $settings['project_honeypot_score_min']['value'] )
			) {
				$access_checks['project_honeypot']['blocked'] = true;
				$access_checks['project_honeypot']['type']    = 'blocked';
				$access_checks['project_honeypot']['details'] = $response;
			}
		}

		return $access_checks;
	}

	/**
	 * Query the Project Honeypot API
	 *
	 * @param string $ip IP address to query.
	 */
	public function query( $ip ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		// Check that an access key has been provided.
		if ( empty( $settings['project_honeypot_access_key']['value'] ) || ! $ip ) {
			return false;
		}

		$cache_key = \ZeroSpam\Core\Utilities::cache_key(
			array(
				'project_honeypot',
				$ip,
			)
		);

		$response = wp_cache_get( $cache_key );
		if ( false === $response ) {
			$octets = explode( '.', $ip );
			krsort( $octets );

			$reversed_ip = implode( '.', $octets );
			if ( strlen( $reversed_ip ) > 16 ) {
				\ZeroSpam\Core\Utilities::log( 'Project Honeypot Warning: IPv6 ip addresses not supported: ' . $ip );
				return false;
			}

			$endpoint  = $settings['project_honeypot_access_key']['value'] . '.' . $reversed_ip . '.dnsbl.httpbl.org';
			$dns_array = dns_get_record( $endpoint, DNS_A );

			if ( ! isset( $dns_array[0]['ip'] ) ) {
				\ZeroSpam\Core\Utilities::log( 'Project Honeypot Error: could not get DNS information for ' . $endpoint . ': ' . wp_json_encode( $dns_array ) );
				return false;
			}

			$results = explode( '.', $dns_array[0]['ip'] );
			if ( '127' !== $results[0] ) {
				\ZeroSpam\Core\Utilities::log( 'Project Honeypot Error: query error' );
				return false;
			}

			$response = array(
				'last_activity' => $results[1],
				'threat_score'  => $results[2],
				'categories'    => $results[3],
			);

			switch ( $response['categories'] ) {
				case 0:
					$categories = array( 'Search Engine' );
					break;
				case 1:
					$categories = array( 'Suspicious' );
					break;
				case 2:
					$categories = array( 'Harvester' );
					break;
				case 3:
					$categories = array( 'Suspicious', 'Harvester' );
					break;
				case 4:
					$categories = array( 'Comment Spammer' );
					break;
				case 5:
					$categories = array( 'Suspicious', 'Comment Spammer' );
					break;
				case 6:
					$categories = array( 'Harvester', 'Comment Spammer' );
					break;
				case 7:
					$categories = array( 'Suspicious', 'Harvester', 'Comment Spammer' );
					break;
				default:
					$categories = array( 'Reserved for Future Use' );
					break;
			}

			$response['categories'] = $categories;

			$expiration = 14 * DAY_IN_SECONDS;
			if ( ! empty( $settings['project_honeypot_cache']['value'] ) ) {
				$expiration = $settings['project_honeypot_cache']['value'] * DAY_IN_SECONDS;
			}
			wp_cache_set( $cache_key, $response, 'zerospam', $expiration );
		}

		return $response;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['project_honeypot'] = array(
			'title' => __( 'Project Honeypot', 'zero-spam' ),
			'icon'  => 'assets/img/icon-honeypot.svg',
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-project_honeypot' );

		$settings['project_honeypot'] = array(
			'title'       => __( 'Status', 'zero-spam' ),
			'section'     => 'project_honeypot',
			'module'      => 'project_honeypot',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'desc'        => __( 'Check if visitors are known spammers from Project Honeypot\'s database.', 'zero-spam' ),
			'value'       => ! empty( $options['project_honeypot'] ) ? $options['project_honeypot'] : false,
			'recommended' => 'enabled',
		);

		$settings['project_honeypot_access_key'] = array(
			'title'       => __( 'Access Key', 'zero-spam' ),
			'desc'        => __( 'Enter your Project Honeypot access key (it\'s free to sign up).', 'zero-spam' ),
			'section'     => 'project_honeypot',
			'module'      => 'project_honeypot',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your Project Honeypot access key.', 'zero-spam' ),
			'value'       => ! empty( $options['project_honeypot_access_key'] ) ? $options['project_honeypot_access_key'] : false,
		);

		$settings['project_honeypot_cache'] = array(
			'title'       => __( 'Cache Expiration', 'zero-spam' ),
			'section'     => 'project_honeypot',
			'module'      => 'project_honeypot',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zero-spam' ),
			'placeholder' => WEEK_IN_SECONDS,
			'min'         => 0,
			'desc'        => __( 'How long to remember spam check results. Recommended: 14 days.', 'zero-spam' ),
			'value'       => ! empty( $options['project_honeypot_cache'] ) ? $options['project_honeypot_cache'] : 14,
			'recommended' => 14,
		);

		$settings['project_honeypot_score_min'] = array(
			'title'       => __( 'Threat Score Minimum', 'zero-spam' ),
			'section'     => 'project_honeypot',
			'module'      => 'project_honeypot',
			'type'        => 'number',
			'field_class' => 'small-text',
			'placeholder' => __( '50', 'zero-spam' ),
			'min'         => 0,
			'max'         => 255,
			'step'        => 1,
			'desc'        => __( 'How dangerous a visitor needs to be before blocking. Lower blocks more. Recommended: 50.', 'zero-spam' ),
			'value'       => ! empty( $options['project_honeypot_score_min'] ) ? $options['project_honeypot_score_min'] : 50,
			'recommended' => 50,
		);

		return $settings;
	}
}
