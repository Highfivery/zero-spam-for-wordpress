<?php
/**
 * Project Honeypot httpBL class.
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
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );

		if ( \ZeroSpam\Core\Access::process() ) {
			add_filter( 'zerospam_access_checks', array( $this, 'access_check' ), 10, 3 );
		}
	}

	/**
	 * Project Honeypot access check
	 *
	 * @param array  $access_checks Current access checks array.
	 * @param string $user_ip       IP address to check.
	 * @param array  $settings      Plugin settings.
	 */
	public function access_check( $access_checks, $user_ip, $settings ) {
		$access_checks['project_honeypot'] = array(
			'blocked' => false,
		);

		if ( empty( $settings['project_honeypot']['value'] ) || 'enabled' !== $settings['project_honeypot']['value'] ) {
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
			$timeout = 5;
			if ( ! empty( $settings['project_honeypot_timeout']['value'] ) ) {
				$timeout = intval( $settings['project_honeypot_timeout']['value'] );
			}

			$octets = explode( '.', $ip );
			krsort( $octets );

			$reversed_ip = implode( '.', $octets );

			$endpoint  = $settings['project_honeypot_access_key']['value'] . '.' . $reversed_ip . '.dnsbl.httpbl.org';
			$dns_array = dns_get_record( $endpoint, DNS_A );

			if ( ! isset( $dns_array[0]['ip'] ) ) {
				return false;
			}

			$results = explode( '.', $dns_array[0]['ip'] );
			if ( '127' !== $results[0] ) {
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
	 * Project Honeypot sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['project_honeypot'] = array(
			'title' => __( 'Project Honeypot Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Project Honeypot settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['project_honeypot'] = array(
			'title'       => __( 'Project Honeypot', 'zerospam' ),
			'section'     => 'project_honeypot',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Checks user IPs against <a href="%s" target="_blank" rel="noopener noreferrer">Project Honeypot</a>\'s blacklist.', 'zerospam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.projecthoneypot.org/index.php' )
			),
			'value'       => ! empty( $options['project_honeypot'] ) ? $options['project_honeypot'] : false,
			'recommended' => 'enabled',
		);

		$settings['project_honeypot_access_key'] = array(
			'title'       => __( 'Project Honeypot Access Key', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Enter your <a href="%1$s" target="_blank" rel="noopener noreferrer">Project Honeypot</a> access key. Don\'t have an access key? <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>', 'zerospam' ),
					array(
						'strong' => array(),
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.projecthoneypot.org/' ),
				esc_url( 'https://www.projecthoneypot.org/create_account.php' )
			),
			'section'     => 'project_honeypot',
			'type'        => 'text',
			'field_class' => 'regular-text',
			'placeholder' => __( 'Enter your Project Honeypot access key.', 'zerospam' ),
			'value'       => ! empty( $options['project_honeypot_access_key'] ) ? $options['project_honeypot_access_key'] : false,
		);

		$settings['project_honeypot_cache'] = array(
			'title'       => __( 'Project Honeypot Cache Expiration', 'zerospam' ),
			'section'     => 'project_honeypot',
			'type'        => 'number',
			'field_class' => 'small-text',
			'suffix'      => __( 'day(s)', 'zerospam' ),
			'placeholder' => __( WEEK_IN_SECONDS, 'zerospam' ),
			'min'         => 0,
			'desc'        => __( 'Recommended setting is 14 days. Setting to high could result in outdated information, too low could cause a decrease in performance.', 'zerospam' ),
			'value'       => ! empty( $options['project_honeypot_cache'] ) ? $options['project_honeypot_cache'] : 14,
			'recommended' => 14,
		);

		$settings['project_honeypot_score_min'] = array(
			'title'       => __( 'Project Honeypot Threat Score Minimum', 'zerospam' ),
			'section'     => 'project_honeypot',
			'type'        => 'number',
			'field_class' => 'small-text',
			'placeholder' => __( '50', 'zerospam' ),
			'min'         => 0,
			'max'         => 255,
			'step'        => 1,
			'desc'      => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Recommended setting is 50. Minimum <a href="%s" target="_blank" rel="noopener noreferrer">threat score</a> an IP must meet before being blocked. Setting this too low could cause users to be blocked that shouldn\'t be.', 'zerospam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.projecthoneypot.org/threat_info.php?utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'value'       => ! empty( $options['project_honeypot_score_min'] ) ? $options['project_honeypot_score_min'] : 50,
			'recommended' => 50,
		);

		return $settings;
	}
}
