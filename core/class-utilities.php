<?php
/**
 * Utilities class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Utilities
 */
class Utilities {

	/**
	 * Returns the time since two dates.
	 *
	 * @param string $date1 First date.
	 * @param string $date2 Second date.
	 * @param string $period Period to return (d = days, y = years, m = months, h = hours, i = minutes, s = seconds, f = microseconds).
	 */
	public static function time_since( $date1, $date2, $period = 'd' ) {
		$first_date  = new \DateTime( $date1 );
		$second_date = new \DateTime( $date2 );
		$diff        = $first_date->diff( $second_date );

		return $diff->$period;
	}

	/**
	 * Recursive sanitation for an array.
	 *
	 * @param array  $array Array to sanitize.
	 * @param string $type  Type of sanitization.
	 */
	public static function sanitize_array( $array, $type = 'sanitize_text_field' ) {
		if ( ! is_array( $array ) ) {
			switch ( $type ) {
				case 'sanitize_text_field':
					$array = sanitize_text_field( $array );
					break;
				case 'esc_html':
					$array = esc_html( $array );
					break;
				default:
					$array = sanitize_text_field( $array );
			}
		} else {
			foreach ( $array as $key => &$value ) {
				if ( is_array( $value ) ) {
					$value = self::sanitize_array( $value );
				} else {
					switch ( $type ) {
						case 'sanitize_text_field':
							$value = sanitize_text_field( $value );
							break;
						case 'esc_html':
							$value = esc_html( $value );
							break;
						default:
							$value = sanitize_text_field( $value );
					}
				}
			}
		}

		return $array;
	}

	/**
	 * Deletes the error log
	 */
	public static function delete_error_log() {
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];
		$file       = $upload_dir . '/zerospam.log';

		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Returns an array from the Zero Spam error log.
	 */
	public static function get_error_log() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];
		$file       = $upload_dir . '/zerospam.log';

		if ( $file && file_exists( $file ) ) {
			$text = $wp_filesystem->get_contents( $file );
			if ( ! $text ) {
				return false;
			}

			return $text;
		}

		return false;
	}

	/**
	 * Determines if an email is valid.
	 *
	 * @param string $email Email address.
	 */
	public static function is_email( $email ) {
		if ( ! is_email( $email ) ) {
			return false;
		}

		// Check the email domain.
		if ( function_exists( 'checkdnsrr' ) ) {
			$email_domain = substr( $email, strpos( $email, '@' ) + 1 );
			if ( ! checkdnsrr( $email_domain, "MX" ) ) {
				if ( ! ( checkdnsrr( $email_domain, "A" ) ) || ! ( checkdnsrr( $email_domain, "AAAA" ) ) ) {
					return false;
				}
			}
		}

		return true;;
	}

	/**
	 * Determines if an email has been blocked by it's domain.
	 *
	 * @param string $email Email address.
	 */
	public static function is_email_domain_blocked( $email ) {
		$blocked_domains = self::get_blocked_email_domains();
		$domain          = explode( '@', $email );
		$domain          = trim( array_pop( $domain ) );

		if ( $blocked_domains && in_array( $domain, $blocked_domains, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the saved blocked email domains.
	 */
	public static function get_blocked_email_domains() {
		$blocked_email_domains = \ZeroSpam\Core\Settings::get_settings( 'blocked_email_domains' );
		if ( ! $blocked_email_domains ) {
			return false;
		}

		$domains = explode( "\n", $blocked_email_domains );
		$domains = array_map( 'trim', $domains );
		$domains = self::sanitize_array( $domains );
		$domains = array_filter( $domains );

		if ( empty( $domains ) ) {
			return false;
		}

		return $domains;
	}

	/**
	 * Returns list of recommended blocked email domains.
	 */
	public static function blocked_email_domains() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$text = $wp_filesystem->get_contents( ZEROSPAM_PATH . 'assets/disposable-email-domains.txt' );
		if ( ! $text ) {
			return false;
		}

		$emails_array = explode( "\n", $text );
		$emails_array = array_map( 'trim', $emails_array );

		return $emails_array;
	}

	/**
	 * Refreshes the .htaccess file
	 */
	public static function refresh_htaccess() {
		// Check IP Block Method setting.
		$settings = \ZeroSpam\Core\Settings::get_settings( 'block_method' );

		if ( ! $settings || 'php' === $settings ) {
			return false;
		}

		$denied_ips    = array();
		$htaccess_file = get_home_path() . '.htaccess';
		if ( is_writable( $htaccess_file ) ) {
			$blocked_ips = \ZeroSpam\Includes\DB::get_blocked();
			if ( $blocked_ips ) {
				foreach ( $blocked_ips as $key => $record ) {
					$details = \ZeroSpam\Core\Access::get_blocked_details( $record );
					if ( $details['blocked'] ) {
						$denied_ips[] = $details['details']['user_ip'];
					}
				}
			}

			if ( $denied_ips ) {
				$lines = array();

				if ( 'htaccess_legacy' === $settings ) {
					$lines[] = 'Deny from ' . implode( $denied_ips, ' ' );
				} elseif ( 'htaccess_modern' === $settings ) {
					$lines[] = '<RequireAll>';
					$lines[] = 'Require all granted';
					$lines[] = 'Require not ip ' . implode( $denied_ips, ' ' );
					$lines[] = '</RequireAll>';
				}
			}

			if ( empty( $lines ) ) {
				return false;
			}

			if ( insert_with_markers( $htaccess_file, 'Zero Spam for WordPress', $lines ) ) {
				return true;
			} else {
				self::log( 'Unable to update the .htacess file, unknown error.' );
			}
		} else {
			self::log( 'Unable to update the .htacess file, unwriteable.' );
		}

		return false;
	}

	/**
	 * Update a plugin settings.
	 *
	 * @param string $key Setting key.
	 * @param string $value Setting value.
	 */
	public static function update_setting( $key, $value ) {
		$settings     = \ZeroSpam\Core\Settings::get_settings();
		$new_settings = array();

		if ( ! isset( $settings[ $key ] ) ) {
			self::log( $key . ' is not a valid setting key.' );
			return false;
		}

		foreach ( $settings as $k => $array ) {
			if ( $key === $k ) {
				$new_settings[ $k ] = $value;
			} else {
				$new_settings[ $k ] = isset( $array['value'] ) ? $array['value'] : false;
			}
		}

		update_option( 'wpzerospam', $new_settings, true );

		return true;
	}

	/**
	 * Write an entry to a log file in the uploads directory.
	 *
	 * @param mixed  $entry String or array of the information to write to the log.
	 * @param string $mode Optional. The type of write. See 'mode' at https://www.php.net/manual/en/function.fopen.php.
	 * @param string $file Optional. The file basename for the .log file.
	 * @return boolean|int Number of bytes written to the lof file, false otherwise.
	 */
	public static function log( $entry, $mode = 'a', $file = 'zerospam' ) {
		// Get WordPress uploads directory.
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];

		// If the entry is array, json_encode.
		if ( is_array( $entry ) ) {
			$entry = json_encode( $entry );
		}

		// Write the log file.
		$file_path  = $upload_dir . '/' . $file . '.log';
		$file       = fopen( $file_path, $mode );
		$bytes      = fwrite( $file, current_time( 'mysql' ) . "::" . $entry . "\n" );
		fclose( $file );

		return $bytes;
	}

	/**
	 * Validates submitted data agaisnt the WP core disallowed list.
	 */
	public static function is_disallowed( $content ) {
		$disallowed_keys = trim( get_option( 'disallowed_keys' ) );
		if ( empty( $disallowed_keys ) ) {
			return false;
		}

		$disallowed_words = explode( "\n", $disallowed_keys );

		// Ensure HTML tags are not being used to bypass the list of disallowed characters and words.
		$content = wp_strip_all_tags( $content );

		foreach ( (array) $disallowed_words as $word ) {
			$word = trim( $word );

			if ( empty( $word ) ) {
				continue;
			}

			// Do some escaping magic so that '#' chars in the spam words don't break things.
			$word = preg_quote( $word, '#' );

			$pattern = "#$word#i";
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the default detection meta title.
	 *
	 * @param string $setting_msg_key Optional. Setting message key.
	 */
	public static function detection_title( $setting_msg_key = false ) {
		$title = __( 'Blocked for Suspected Malicious IP/Spam', 'zero-spam' );

		return apply_filters( 'zerospam_detection_title', $title, $setting_msg_key );
	}

	/**
	 * Returns the default detection message.
	 *
	 * @param string $setting_msg_key Optional. Setting message key.
	 */
	public static function detection_message( $setting_msg_key = false ) {
		$message = __( 'You have been flagged as spam/malicious user.', 'zero-spam' );

		if ( $setting_msg_key && ! empty( ZeroSpam\Core\Settings::get_settings( $setting_msg_key ) ) ) {
			$message = ZeroSpam\Core\Settings::get_settings( $setting_msg_key );
		}

		return apply_filters( 'zerospam_detection_message', $message, $setting_msg_key );
	}

	/**
	 * Returns an array of countries.
	 *
	 * @access public
	 */
	public static function countries( $key = false ) {
		$countries = apply_filters(
			'zerospam_countries',
			array(
				'AF' => 'Afghanistan',
				'AL' => 'Albania',
				'DZ' => 'Algeria',
				'AS' => 'American Samoa',
				'AD' => 'Andorra',
				'AO' => 'Angola',
				'AI' => 'Anguilla',
				'AQ' => 'Antarctica',
				'AG' => 'Antigua and Barbuda',
				'AR' => 'Argentina',
				'AM' => 'Armenia',
				'AW' => 'Aruba',
				'AU' => 'Australia',
				'AT' => 'Austria',
				'AZ' => 'Azerbaijan',
				'BS' => 'Bahamas',
				'BH' => 'Bahrain',
				'BD' => 'Bangladesh',
				'BB' => 'Barbados',
				'BY' => 'Belarus',
				'BE' => 'Belgium',
				'BZ' => 'Belize',
				'BJ' => 'Benin',
				'BM' => 'Bermuda',
				'BT' => 'Bhutan',
				'BO' => 'Bolivia',
				'BA' => 'Bosnia and Herzegovina',
				'BW' => 'Botswana',
				'BV' => 'Bouvet Island',
				'BR' => 'Brazil',
				'IO' => 'British Indian Ocean Territory',
				'BN' => 'Brunei Darussalam',
				'BG' => 'Bulgaria',
				'BF' => 'Burkina Faso',
				'BI' => 'Burundi',
				'KH' => 'Cambodia',
				'CM' => 'Cameroon',
				'CA' => 'Canada',
				'CV' => 'Cape Verde',
				'KY' => 'Cayman Islands',
				'CF' => 'Central African Republic',
				'TD' => 'Chad',
				'CL' => 'Chile',
				'CN' => 'China',
				'CX' => 'Christmas Island',
				'CC' => 'Cocos (Keeling) Islands',
				'CO' => 'Colombia',
				'KM' => 'Comoros',
				'CG' => 'Congo',
				'CD' => 'Congo, the Democratic Republic of the',
				'CK' => 'Cook Islands',
				'CR' => 'Costa Rica',
				'CI' => 'Cote D\'Ivoire',
				'HR' => 'Croatia',
				'CU' => 'Cuba',
				'CY' => 'Cyprus',
				'CZ' => 'Czech Republic',
				'DK' => 'Denmark',
				'DJ' => 'Djibouti',
				'DM' => 'Dominica',
				'DO' => 'Dominican Republic',
				'EC' => 'Ecuador',
				'EG' => 'Egypt',
				'SV' => 'El Salvador',
				'GQ' => 'Equatorial Guinea',
				'ER' => 'Eritrea',
				'EE' => 'Estonia',
				'ET' => 'Ethiopia',
				'FK' => 'Falkland Islands (Malvinas)',
				'FO' => 'Faroe Islands',
				'FJ' => 'Fiji',
				'FI' => 'Finland',
				'FR' => 'France',
				'GF' => 'French Guiana',
				'PF' => 'French Polynesia',
				'TF' => 'French Southern Territories',
				'GA' => 'Gabon',
				'GM' => 'Gambia',
				'GE' => 'Georgia',
				'DE' => 'Germany',
				'GH' => 'Ghana',
				'GI' => 'Gibraltar',
				'GR' => 'Greece',
				'GL' => 'Greenland',
				'GD' => 'Grenada',
				'GP' => 'Guadeloupe',
				'GU' => 'Guam',
				'GT' => 'Guatemala',
				'GN' => 'Guinea',
				'GW' => 'Guinea-Bissau',
				'GY' => 'Guyana',
				'HT' => 'Haiti',
				'HM' => 'Heard Island and Mcdonald Islands',
				'VA' => 'Holy See (Vatican City State)',
				'HN' => 'Honduras',
				'HK' => 'Hong Kong',
				'HU' => 'Hungary',
				'IS' => 'Iceland',
				'IN' => 'India',
				'ID' => 'Indonesia',
				'IR' => 'Iran, Islamic Republic of',
				'IQ' => 'Iraq',
				'IE' => 'Ireland',
				'IL' => 'Israel',
				'IT' => 'Italy',
				'JM' => 'Jamaica',
				'JP' => 'Japan',
				'JO' => 'Jordan',
				'KZ' => 'Kazakhstan',
				'KE' => 'Kenya',
				'KI' => 'Kiribati',
				'KP' => 'Korea, Democratic People\'s Republic of',
				'KR' => 'Korea, Republic of',
				'KW' => 'Kuwait',
				'KG' => 'Kyrgyzstan',
				'LA' => 'Lao People\'s Democratic Republic',
				'LV' => 'Latvia',
				'LB' => 'Lebanon',
				'LS' => 'Lesotho',
				'LR' => 'Liberia',
				'LY' => 'Libyan Arab Jamahiriya',
				'LI' => 'Liechtenstein',
				'LT' => 'Lithuania',
				'LU' => 'Luxembourg',
				'MO' => 'Macao',
				'MK' => 'Macedonia, the Former Yugoslav Republic of',
				'MG' => 'Madagascar',
				'MW' => 'Malawi',
				'MY' => 'Malaysia',
				'MV' => 'Maldives',
				'ML' => 'Mali',
				'MT' => 'Malta',
				'MH' => 'Marshall Islands',
				'MQ' => 'Martinique',
				'MR' => 'Mauritania',
				'MU' => 'Mauritius',
				'YT' => 'Mayotte',
				'MX' => 'Mexico',
				'FM' => 'Micronesia, Federated States of',
				'MD' => 'Moldova, Republic of',
				'MC' => 'Monaco',
				'MN' => 'Mongolia',
				'MS' => 'Montserrat',
				'MA' => 'Morocco',
				'MZ' => 'Mozambique',
				'MM' => 'Myanmar',
				'NA' => 'Namibia',
				'NR' => 'Nauru',
				'NP' => 'Nepal',
				'NL' => 'Netherlands',
				'AN' => 'Netherlands Antilles',
				'NC' => 'New Caledonia',
				'NZ' => 'New Zealand',
				'NI' => 'Nicaragua',
				'NE' => 'Niger',
				'NG' => 'Nigeria',
				'NU' => 'Niue',
				'NF' => 'Norfolk Island',
				'MP' => 'Northern Mariana Islands',
				'NO' => 'Norway',
				'OM' => 'Oman',
				'PK' => 'Pakistan',
				'PW' => 'Palau',
				'PS' => 'Palestinian Territory, Occupied',
				'PA' => 'Panama',
				'PG' => 'Papua New Guinea',
				'PY' => 'Paraguay',
				'PE' => 'Peru',
				'PH' => 'Philippines',
				'PN' => 'Pitcairn',
				'PL' => 'Poland',
				'PT' => 'Portugal',
				'PR' => 'Puerto Rico',
				'QA' => 'Qatar',
				'RE' => 'Reunion',
				'RO' => 'Romania',
				'RU' => 'Russian Federation',
				'RW' => 'Rwanda',
				'SH' => 'Saint Helena',
				'KN' => 'Saint Kitts and Nevis',
				'LC' => 'Saint Lucia',
				'PM' => 'Saint Pierre and Miquelon',
				'VC' => 'Saint Vincent and the Grenadines',
				'WS' => 'Samoa',
				'SM' => 'San Marino',
				'ST' => 'Sao Tome and Principe',
				'SA' => 'Saudi Arabia',
				'SN' => 'Senegal',
				'CS' => 'Serbia and Montenegro',
				'SC' => 'Seychelles',
				'SL' => 'Sierra Leone',
				'SG' => 'Singapore',
				'SK' => 'Slovakia',
				'SI' => 'Slovenia',
				'SB' => 'Solomon Islands',
				'SO' => 'Somalia',
				'ZA' => 'South Africa',
				'GS' => 'South Georgia and the South Sandwich Islands',
				'ES' => 'Spain',
				'LK' => 'Sri Lanka',
				'SD' => 'Sudan',
				'SR' => 'Suriname',
				'SJ' => 'Svalbard and Jan Mayen',
				'SZ' => 'Swaziland',
				'SE' => 'Sweden',
				'CH' => 'Switzerland',
				'SY' => 'Syrian Arab Republic',
				'TW' => 'Taiwan, Province of China',
				'TJ' => 'Tajikistan',
				'TZ' => 'Tanzania, United Republic of',
				'TH' => 'Thailand',
				'TL' => 'Timor-Leste',
				'TG' => 'Togo',
				'TK' => 'Tokelau',
				'TO' => 'Tonga',
				'TT' => 'Trinidad and Tobago',
				'TN' => 'Tunisia',
				'TR' => 'Turkey',
				'TM' => 'Turkmenistan',
				'TC' => 'Turks and Caicos Islands',
				'TV' => 'Tuvalu',
				'UG' => 'Uganda',
				'UA' => 'Ukraine',
				'AE' => 'United Arab Emirates',
				'GB' => 'United Kingdom',
				'US' => 'United States',
				'UM' => 'United States Minor Outlying Islands',
				'UY' => 'Uruguay',
				'UZ' => 'Uzbekistan',
				'VU' => 'Vanuatu',
				'VE' => 'Venezuela',
				'VN' => 'Viet Nam',
				'VG' => 'Virgin Islands, British',
				'VI' => 'Virgin Islands, U.S.',
				'WF' => 'Wallis and Futuna',
				'EH' => 'Western Sahara',
				'YE' => 'Yemen',
				'ZM' => 'Zambia',
				'ZW' => 'Zimbabwe',
			)
		);

		if ( $key ) {
			if ( ! empty( $countries[ $key ] ) ) {
				return $countries[ $key ];
			}

			return false;
		}

		return $countries;
	}

	/**
	 * Returns a country flag image URL.
	 *
	 * @access public
	 */
	public static function country_flag_url( $country_code ) {
		return plugins_url( 'assets/img/flags/' . strtolower( $country_code ) . '.svg', ZEROSPAM );
	}

	/**
	 * Outputs a honeypot field
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @return string Returns a HTML honeypot field.
	 */
	public static function honeypot_field() {
		return '<input type="text" name="' . self::get_honeypot() . '" value="" style="display: none !important;" />';
	}

	/**
	 * Returns the generated key for checking submissions.
	 *
	 * @access public
	 *
	 * @return string A unique key used for the 'honeypot' field.
	 */
	public static function get_honeypot( $regenerate = false ) {
		$key = get_option( 'wpzerospam_honeypot' );
		if ( ! $key || $regenerate ) {
			$key = wp_generate_password( 5, false, false );
			update_option( 'wpzerospam_honeypot', $key );
		}

		return $key;
	}

	/**
	 * Returns a cache key
	 */
	public static function cache_key( $args, $table = false ) {
		if ( is_array( $args ) ) {
			$args = implode( '_', $args );
		}

		return sanitize_title( $table . '_' . $args );
	}

	/**
	 * Performs an HTTP request using the GET method and returns its response.
	 *
	 * @param string $endpoint URL to retrieve.
	 * @param array  $args     Request arguments.
	 */
	public static function remote_get( $endpoint, $args = array() ) {
		$response = wp_remote_get( $endpoint, $args );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			return wp_remote_retrieve_body( $response );
		} elseif ( is_wp_error( $response ) ) {
			self::log( $response->get_error_message() );
		}

		return false;
	}

	/**
	 * Performs an HTTP request using the POST method and returns its response.
	 *
	 * @param string $endpoint URL to retrieve.
	 * @param array  $args     Request arguments.
	 */
	public static function remote_post( $endpoint, $args = array() ) {
		$response = wp_remote_post( $endpoint, $args );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			return wp_remote_retrieve_body( $response );
		} elseif ( is_wp_error( $response ) ) {
			self::log( $response->get_error_message() );
		}

		return false;
	}

	/**
	 * Returns the current URL
	 *
	 * @param array $params Array of URL parameters to append to the URL.
	 */
	public static function current_url( $params = array() ) {
		$request_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;

		$url  = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://';
		$url .= ! empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$url .= $request_uri ? $request_uri : '';

		if ( $request_uri && $params ) {


			if ( strpos( $request_uri, '?' ) ) {
				$url .= '&' . implode( '&', $params );
			} else {
				$url .= '?' . implode( '&', $params );
			}
		}

		return $url;
	}

	/**
	 * Checks if an IP is on the whitelist.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public static function is_whitelisted( $ip ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		// Check whitelist.
		if ( ! empty( $settings['ip_whitelist']['value'] ) ) {
			$whitelisted = explode( PHP_EOL, $settings['ip_whitelist']['value'] );
			if ( $whitelisted ) {
				foreach ( $whitelisted as $key => $whitelisted_ip ) {
					$whitelisted_ip = trim( $whitelisted_ip );
					if ( $whitelisted_ip === $ip ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get an IP address geolocation information.
	 *
	 * @param string $ip IP address to lookup.
	 * @return boolean|array False if geolocation is unavailable or array of location information.
	 */
	public static function geolocation( $ip ) {
		// The standarized location array that will be returned.
		$location_details = array(
			'type'           => false,
			'hostname'       => false,
			'timezone'       => false,
			'organization'   => false,
			'continent_code' => false,
			'continent_name' => false,
			'country_code'   => false,
			'country_name'   => false,
			'zip'            => false,
			'region_code'    => false,
			'region_name'    => false,
			'city'           => false,
			'latitude'       => false,
			'longitude'      => false,
		);

		// 1. Check for the country code via server variables.
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			// Check Cloudflare.
			$location_details['country_code'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ) ) {
			// Check Cloudways.
			$location_details['country_code'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ) );
		}

		// 2. Query the ipstack API.
		$ipstack_location = \ZeroSpam\Modules\ipstack::get_geolocation( $ip );
		if ( ! empty( $ipstack_location ) ) {
			// ipstack API key provided, process the response.
			if ( ! empty( $ipstack_location['error'] ) ) {
				// ipstack returned an error, log it for future reference.
				self::log( wp_json_encode( $ipstack_location['error'] ) );
			} else {
				// Add available location info to the standarized array.
				if ( ! empty( $ipstack_location['type'] ) ) {
					$location_details['type'] = $ipstack_location['type'];
				}

				if ( ! empty( $ipstack_location['continent_code'] ) ) {
					$location_details['continent_code'] = $ipstack_location['continent_code'];
				}

				if ( ! empty( $ipstack_location['continent_name'] ) ) {
					$location_details['continent_name'] = $ipstack_location['continent_name'];
				}

				if ( ! empty( $ipstack_location['country_code'] ) ) {
					$location_details['country_code'] = $ipstack_location['country_code'];
				}

				if ( ! empty( $ipstack_location['country_name'] ) ) {
					$location_details['country_name'] = $ipstack_location['country_name'];
				}

				if ( ! empty( $ipstack_location['region_code'] ) ) {
					$location_details['region_code'] = $ipstack_location['region_code'];
				}

				if ( ! empty( $ipstack_location['region_name'] ) ) {
					$location_details['region_name'] = $ipstack_location['region_name'];
				}

				if ( ! empty( $ipstack_location['city'] ) ) {
					$location_details['city'] = $ipstack_location['city'];
				}

				if ( ! empty( $ipstack_location['zip'] ) ) {
					$location_details['zip'] = $ipstack_location['zip'];
				}

				if ( ! empty( $ipstack_location['latitude'] ) ) {
					$location_details['latitude'] = $ipstack_location['latitude'];
				}

				if ( ! empty( $ipstack_location['longitude'] ) ) {
					$location_details['longitude'] = $ipstack_location['longitude'];
				}
			}
		}

		// 3. Query the IPinfo API.
		$ipinfo_location = ZeroSpam\Modules\IPinfoModule::get_geolocation( $ip );
		if ( ! empty( $ipinfo_location ) ) {
			// IPinfo token provided, process the response.
			// Add available location info to the standarized array.
			if ( ! empty( $ipinfo_location['hostname'] ) ) {
				$location_details['hostname'] = $ipinfo_location['hostname'];
			}

			if ( ! empty( $ipinfo_location['city'] ) ) {
				$location_details['city'] = $ipinfo_location['city'];
			}

			if ( ! empty( $ipinfo_location['region'] ) ) {
				$location_details['region_name'] = $ipinfo_location['region'];
			}

			if ( ! empty( $ipinfo_location['country'] ) ) {
				$location_details['country_code'] = $ipinfo_location['country'];
			}

			if ( ! empty( $ipinfo_location['org'] ) ) {
				$location_details['organization'] = $ipinfo_location['org'];
			}

			if ( ! empty( $ipinfo_location['postal'] ) ) {
				$location_details['zip'] = $ipinfo_location['postal'];
			}

			if ( ! empty( $ipinfo_location['timezone'] ) ) {
				$location_details['timezone'] = $ipinfo_location['timezone'];
			}

			if ( ! empty( $ipinfo_location['country_name'] ) ) {
				$location_details['country_name'] = $ipinfo_location['country_name'];
			}

			if ( ! empty( $ipinfo_location['latitude'] ) ) {
				$location_details['latitude'] = $ipinfo_location['latitude'];
			}

			if ( ! empty( $ipinfo_location['longitude'] ) ) {
				$location_details['longitude'] = $ipinfo_location['longitude'];
			}
		}

		return $location_details;
	}
}
