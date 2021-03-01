<?php
/**
 * Zero Spam class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Zero Spam
 */
class Zero_Spam {

	/**
	 * The zerospam.org API endpoint
	 */
	//const API_ENDPOINT = 'https://www.zerospam.org/wp-json/zerospam/v1/';
	const API_ENDPOINT = 'http://localhost:10023/wp-json/zerospam/v1/';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Fires when a user is blocked from accessing the site.
		add_action( 'zerospam_share_blocked', array( $this, 'share_blocked' ), 10, 1 );
	}

	/**
	 * Share blocked details with zerospam.org
	 *
	 * @param array $access Contains all access details.
	 */
	public function share_blocked( $access ) {
		$endpoint = self::API_ENDPOINT . 'add-blocked/';

		// Only send details if the user was blocked.
		if ( empty( $access['blocked'] ) ) {
			return false;
		}

		$api_data = array( 'checks' => array() );
		$ip       = ! empty( $access['ip'] ) ? $access['ip'] : false;
		$details  = ! empty( $access['details'] ) ? $access['details'] : false;

		// Require an IP address.
		if ( ! $ip ) {
			return false;
		}

		$api_data['user_ip'] = $ip;

		// Attempt to get the geolocation information.
		$api_data['location'] = ZeroSpam\Modules\ipstack::get_geolocation( $ip );

		// Only send $details that were blocked.
		if ( $details && is_array( $details ) ) {
			foreach ( $details as $check_key => $check_details ) {
				if (
					! empty( $check_details['blocked'] ) &&
					! empty( $check_details['type'] )
				) {
					// User didn't pass the $check_key check.
					$api_data['checks'][ $check_key ] = array(
						'type' => $check_details['type'],
					);

					// Add additional details if available.
					if ( ! empty( $check_details['details'] && is_array( $check_details['details'] ) ) ) {
						$details_data = $check_details['details'];

						// Add country if not already set and available.
						if (
							empty( $api_data['location']['country_code'] ) &&
							! empty( $details_data['country'] ) &&
							2 === strlen( $details_data['country'] )
						) {
							$api_data['location']['country_code'] = $details_data['country'];
						}
					}
				}
			}
		}

		// Only query the API if there's data to be sent.
		if ( ! empty( $api_data['checks'] ) ) {
			// Add site details.
			$api_data['site_url']       = site_url();
			$api_data['admin_email']    = get_bloginfo( 'admin_email' );
			$api_data['wp_version']     = get_bloginfo( 'version' );
			$api_data['site_name']      = get_bloginfo( 'name' );
			$api_data['site_desc']      = get_bloginfo( 'description' );
			$api_data['site_language']  = get_bloginfo( 'language' );
			$api_data['plugin_version'] = ZEROSPAM_VERSION;

			// Send the data to zerospam.org.
			$args = array(
				'body' => $api_data,
			);

			$response = wp_remote_post( $endpoint, $args );
			if ( ! is_wp_error( $response ) ) {
				wp_remote_retrieve_body( $response );
			}
		}
	}
}
