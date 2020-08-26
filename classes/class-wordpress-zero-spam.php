<?php
/**
 * WordPress Zero Spam class.
 *
 * @package WordPressZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WordPress Zero Spam class.
 */
class WordPress_Zero_Spam {
	/**
	 * Contains all plugin options.
	 *
	 * @var array Array of plugin options.
	 */
	public $options;

	/**
	 * The default core plugin options.
	 *
	 * @var array Array of core plugin options.
	 */
	public $default_options = array(
		'debug'                        => 'disabled',
		'ip_whitelist'                 => '',
		'cookie_expiration'            => 7,
		'log_spam'                     => false,
		'log_blocked_ips'              => false,
		'share_detections'             => true,
		'stopforumspam_confidence_min' => 20,
		'botscout_count_min'           => 5,
		'botscout_api'                 => false,
		'api_timeout'                  => 5,
		'block_handler'                => 403,
		'blocked_redirect_url'         => 'https://google.com',
		'blocked_message'              => false,
		'ipstack_api'                  => false,
	);

	/**
	 * The current user's IP address.
	 *
	 * @var string The current user's IP address.
	 */
	public $current_user_ip;

	/**
	 * The current user's IP address access.
	 *
	 * @var array IP address access details.
	 */
	public $current_user_ip_access;

	/**
	 * Database tables.
	 *
	 * @var array Array of plugin database tables.
	 */
	public $tables;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Triggered on the WP init action.
		add_action( 'init', array( $this, 'wp_init' ) );

		// Triggered on the WP wp_footer action.
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );

		// Handles IPs that have been denied access.
		add_action( 'template_redirect', array( $this, 'access_check' ) );
	}

	/**
	 * Checks is an IP is safe (i.e. from a known bot or crawler)
	 */
	public function is_known_safe_ip() {
		$safe_hosts = array(
			'googlebot.com',
			'google.com',
			'search.msn.com',
			'bing.com',
			'yahoo.com',
			'duckduckgo.com',
			'baidu.com',
			'yandex.com',
			'exabot.com',
			'facebook.com',
			'alexa.com',
		);

		$safe_user_agents = array(
			'Googlebot',
			'Bingbot',
			'Slurp',
			'DuckDuckBot',
			'Baiduspider',
			'YandexBot',
			'facebot',
			'ia_archiver',
		);

		$ip_host    = gethostbyaddr( $this->current_user_ip );
		$user_agent = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? esc_html( $_SERVER['HTTP_USER_AGENT'] ) : false;

		if ( ! $ip_host || ! $user_agent ) {
			return false;
		}

		// 1. Check hostnames.
		foreach ( $safe_hosts as $key => $host ) {
			if ( stripos( $ip_host, $host ) !== false ) {
				return true;
			}
		}

		// 2. Check user agents.
		foreach ( $safe_user_agents as $key => $agent ) {
			if ( stripos( $user_agent, $agent ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks an IP access.
	 *
	 * @param string $ip The IP address to check.
	 */
	public function get_access( $ip ) {
		$access = array(
			'ip_checked'     => $ip,
			'has_access'     => true,
			'access_checked' => false,
			'cached'         => false,
			'blacklist_api'  => false,
			'attempts'       => false,
		);

		// Ignore logged in users.
		if ( is_user_logged_in() ) {
			$access['access_checked'] = 'authenticated';
			return $this->set_access_cookies( $access );
		}

		// 1. Check if an access check has already been ran for this IP.
		if ( $this->get_cookie( 'access_checked' ) && $this->get_cookie( 'ip_checked' ) === $ip ) {
			// IP has already been checked, return the saved access.
			foreach ( $access as $key => $value ) {
				$access[ $key ] = $this->get_cookie( $key );
			}

			$access['cached'] = 'cookie';

			return $this->set_access_cookies( $access );
		}

		// 2. Check known/safe hosts (i.e. Google crawlers, etc.)
		if ( $this->is_known_safe_ip( $ip ) ) {
			$access['access_checked'] = 'safe_ip';

			return $this->set_access_cookies( $access );
		}

		// 3. Check the whitelisted IP addresses.
		$whitelisted_ips = $this->get_whitelisted_ips();

		if ( array_key_exists( $ip, $whitelisted_ips ) ) {
			// IP address found in the whitelist.
			$access['access_checked'] = 'whitelisted';

			return $this->set_access_cookies( $access );
		}

		// 4. Check if IP has been blocked.
		$blocked_ip = $this->get_blocked_ip( $ip );
		if ( $blocked_ip ) {
			$access['attempts'] = $blocked_ip['attempts'];

			// IP has been blocked, check the type.
			if ( 'permanent' === $blocked_ip['blocked_type'] ) {
				// Permanent block.
				$access['access_checked']   = 'permanent_block';
				$access['has_access']       = false;
				return $this->set_access_cookies( $access );
			} else {
				// Temporary block, check if still valid.
				if ( $this->is_blocked_ip_active( $blocked_ip ) ) {
					$access['access_checked']   = 'temporary_block';
					$access['has_access']       = false;
					return $this->set_access_cookies( $access );
				}
			}
		}

		// 5. Check if the IP appears on the blacklist.
		$blacklisted_ip = $this->get_blacklisted_ip( $ip );
		if ( $blacklisted_ip ) {
			// IP has been blacklisted, check if it needs updated.
			$current_datetime = strtotime( current_time( 'mysql' ) );
			$last_updated     = strtotime( $blacklisted_ip['last_updated'] );
			if ( $current_datetime >= ( $last_updated + MONTH_IN_SECONDS ) ) {
				// Expired, update the record.
				$blacklisted_api_ip = $this->get_ip_from_api( $ip, $blacklisted_ip['blacklist_service'] );
				if ( ! $blacklisted_api_ip ) {
					// IP not found, delete.
					$this->delete_blacklisted_ip( $ip );
				} else {
					// IP found, update (or delete depending on plugin settings) it.
					if ( $this->update_blacklisted_ip( $blacklisted_api_ip, 'update' ) ) {
						$access['has_access']     = false;
						$access['access_checked'] = 'blacklist';
						$access['blacklist_api']  = $blacklisted_ip['blacklist_service'];
						$access['attempts']       = $blacklisted_ip['attempts'];

						return $this->set_access_cookies( $access );
					}
				}
			} else {
				// Not expired.
				$access['has_access']     = false;
				$access['access_checked'] = 'blacklist';
				$access['blacklist_api']  = $blacklisted_ip['blacklist_service'];
				$access['attempts']       = $blacklisted_ip['attempts'];

				return $this->set_access_cookies( $access );
			}
		}

		// 6. Check the IP against the StopForumSpam API.
		$blacklisted_api_ip = $this->get_ip_from_api( $ip, 'stopforumspam' );
		if ( $blacklisted_api_ip && $this->update_blacklisted_ip( $blacklisted_api_ip, 'insert' ) ) {
			$access['has_access']     = false;
			$access['access_checked'] = 'blacklist';
			$access['blacklist_api']  = 'stopforumspam';
			$access['attempts']       = $blacklisted_api_ip['attempts'];

			return $this->set_access_cookies( $access );
		}

		// 7. Check the IP against the BotScout API.
		$blacklisted_api_ip = $this->get_ip_from_api( $ip, 'botscout' );
		if ( $blacklisted_api_ip && $this->update_blacklisted_ip( $blacklisted_api_ip, 'insert' ) ) {
			$access['has_access']     = false;
			$access['access_checked'] = 'blacklist';
			$access['blacklist_api']  = 'botscout';
			$access['attempts']       = $blacklisted_api_ip['attempts'];

			return $this->set_access_cookies( $access );
		}

		return $this->set_access_cookies( $access );
	}

	/**
	 * Handles IPs that have been denied access.
	 */
	public function access_check() {
		global $wpdb;

		if ( ! $this->current_user_ip_access['has_access'] ) {
			$block_type = $this->current_user_ip_access['access_checked'];
			$log_data   = array();

			// IP doesn't have access, update attempts.
			switch ( $block_type ) {
				case 'temporary_block':
				case 'permanent_block':
				case 'blacklist':
					$log_data['reason'] = $block_type;

					switch ( $block_type ) {
						case 'temporary_block':
						case 'permanent_block':
							$table = 'blocked';
							break;
						case 'blacklist':
							$table = 'blacklist';
							break;
					}

					if ( ! $this->current_user_ip_access[ 'attempts' ] ) {
						$this->current_user_ip_access[ 'attempts' ] = 1;
					} else {
						$this->current_user_ip_access[ 'attempts' ]++;
					}

					$wpdb->update(
						$this->tables[ $table ],
						array(
							'attempts' => $this->current_user_ip_access[ 'attempts' ],
						),
						array(
							'user_ip' => $this->current_user_ip,
						)
					);

					$this->set_cookie( 'attempts', $this->current_user_ip_access[ 'attempts' ], 0 );
					break;
			}

			// Log the detection.
			$this->log_detection( 'blocked', $log_data );

			if ( 'redirect' === $this->options['block_handler'] ) {
				wp_redirect( esc_url( $this->options['blocked_redirect_url'] ) );
				exit();
			} else {
				status_header( 403 );
				die( $this->options['blocked_message'] );
			}
		}
	}

	/**
	 * Logs a IP detections.
	 */
	public function log_detection( $type, $data ) {
		global $wpdb;

		$record = array(
			'user_ip'       => $this->current_user_ip,
			'log_type'      => $type,
			'date_recorded' => current_time( 'mysql' ),
		);


		// If sharing detections is enabled, send the detection to Zero Spam.
		if ( 'enabled' === $this->options['share_detections'] ) {
			$this->share_detection( $record['user_ip'], $record['log_type'] );
		}

		// Check if logging detections & 'blocks' are enabled.
		if (
			'enabled' !== $this->options['log_spam'] ||
			( 'blocked' === $record['log_type'] && 'enabled' !== $this->options['log_blocked_ips'] )
		) {
			// Logging disabled.
			return false;
		}

		// Logging enabled, get the current URL & IP location information.
		$location    = $this->get_ip_geolocation( $record['user_ip'] );
		$current_url = $this->get_current_url();

		// Add additional information to the detection record.
		$record['page_url']        = $current_url;
		$record['submission_data'] = wp_json_encode( $data );

		if ( $location ) {
			$record['country']   = ! empty( $location['country_code'] ) ? $location['country_code'] : false;
			$record['region']    = ! empty( $location['region_code'] ) ? $location['region_code'] : false;
			$record['city']      = ! empty( $location['city'] ) ? $location['city'] : false;
			$record['latitude']  = ! empty( $location['latitude'] ) ? $location['latitude'] : false;
			$record['longitude'] = ! empty( $location['longitude'] ) ? $location['longitude'] : false;
		}

		return $wpdb->insert( $this->tables['log'], $record );
	}

	/**
	 * Returns the current URL.
	 */
	public function get_current_url() {
		global $wp;

		return home_url( add_query_arg( array(), $wp->request ) );
	}

	/**
	 * Retreives an IP geolocation.
	 */
	public function get_ip_geolocation( $ip ) {
		if ( empty( $this->options['ipstack_api'] ) ) {
			return false;
		}

		$base_url   = 'http://api.ipstack.com/';
		$remote_url = $base_url . $ip . '?access_key=' . $this->options['ipstack_api'];
		$response   = wp_remote_get( $remote_url, array( 'timeout' => $this->options['api_timeout'] ) );

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$info = json_decode( $response['body'], true );

			return array(
				'type'           => ! empty( $info['type'] ) ? sanitize_text_field( $info['type'] ) : false,
				'continent_code' => ! empty( $info['continent_code'] ) ? sanitize_text_field( $info['continent_code'] ) : false,
				'continent_name' => ! empty( $info['continent_name'] ) ? sanitize_text_field( $info['continent_name'] ) : false,
				'country_code'   => ! empty( $info['country_code'] ) ? sanitize_text_field( $info['country_code'] ) : false,
				'country_name'   => ! empty( $info['country_name'] ) ? sanitize_text_field( $info['country_name'] ) : false,
				'region_code'    => ! empty( $info['region_code'] ) ? sanitize_text_field( $info['region_code'] ) : false,
				'region_name'    => ! empty( $info['region_name'] ) ? sanitize_text_field( $info['region_name'] ) : false,
				'city'           => ! empty( $info['city'] ) ? sanitize_text_field( $info['city'] ) : false,
				'zip'            => ! empty( $info['zip'] ) ? sanitize_text_field( $info['zip'] ) : false,
				'latitude'       => ! empty( $info['latitude'] ) ? sanitize_text_field( $info['latitude'] ) : false,
				'longitude'      => ! empty( $info['longitude'] ) ? sanitize_text_field( $info['longitude'] ) : false,
				'flag'           => ! empty( $info['location']['country_flag'] ) ? sanitize_text_field( $info['location']['country_flag'] ) : false,
			);
		}

		return false;
	}

	/**
	 * Share a detection with Zero Spam.
	 */
	public function share_detection( $ip, $type ) {
		// The Zero Spam API endpoint for sharing detections.
		$api_url = 'https://zerospam.org/wp-json/wpzerospamapi/v1/detection/';

		// Setup the request parameters.
		$request_args = array(
			'method'    => 'POST',
			'body'      => array(
				'ip'        => $ip,
				'type'      => $type,
				'site'      => site_url(),
				'email'     => get_bloginfo( 'admin_email' ),
				'wpversion' => get_bloginfo( 'version' ),
				'name'      => get_bloginfo( 'name' ),
				'desc'      => get_bloginfo( 'description' ),
				'language'  => get_bloginfo( 'language' ),
				'version'   => WORDPRESS_ZERO_SPAM_VERSION,
			),
			'sslverify' => true,
		);

		// For debugging purposes only.
		if ( WP_DEBUG ) {
			$request_args['sslverify'] = false;
		}

		// Send the request.
		$request = wp_remote_post( $api_url, $request_args );
		if ( is_wp_error( $request ) ) {
			// Request failed.
			return false;
		}

		// Request succeeded, return the result.
		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Triggered on the WP init action.
	 */
	public function wp_footer() {
		// Display debug info if enabled.
		if ( 'enabled' === $this->options['debug'] ) {
			wp_enqueue_style(
				'wpzerospam-debug',
				plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
					'assets/css/debug.css',
				false,
				WORDPRESS_ZERO_SPAM_VERSION
			);
			?>
			<div class="wpzerospam-debug-overlay">
				<div class="wpzerospam-debug-item">
					<div class="wpzerospam-debug-item-label"><strong><?php esc_html_e( 'Options', 'wpzerospam' ); ?>:</strong></div>
					<div class="wpzerospam-debug-item-value">
						<?php foreach ( $this->options as $key => $value ): ?>
							<div class="wpzerospam-debug-item">
								<div class="wpzerospam-debug-item-label"><?php esc_html_e( $key ); ?></div>
								<div class="wpzerospam-debug-item-value"><?php esc_html_e( $value ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="wpzerospam-debug-item">
					<div class="wpzerospam-debug-item-label"><strong><?php esc_html_e( 'Current User IP', 'wpzerospam' ); ?>:</strong></div>
					<div class="wpzerospam-debug-item-value"><?php echo esc_html( $this->current_user_ip ); ?></div>
				</div>
				<div class="wpzerospam-debug-item">
					<div class="wpzerospam-debug-item-label"><strong><?php esc_html_e( 'Current User IP Access', 'wpzerospam' ); ?>:</strong></div>
					<div class="wpzerospam-debug-item-value">
						<?php foreach ( $this->current_user_ip_access as $key => $value ): ?>
							<div class="wpzerospam-debug-item">
								<div class="wpzerospam-debug-item-label"><?php esc_html_e( $key ); ?></div>
								<div class="wpzerospam-debug-item-value"><?php esc_html_e( $value ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Triggered on the WP init action.
	 */
	public function wp_init() {
		global $wpdb;

		// Set the database tables.
		$this->tables = array(
			'log'       => $wpdb->prefix . 'wpzerospam_log',
			'blocked'   => $wpdb->prefix . 'wpzerospam_blocked',
			'blacklist' => $wpdb->prefix . 'wpzerospam_blacklist',
		);

		// Set the plugin options.
		$this->options = $this->get_options();
		if ( empty( $this->options['blocked_message'] ) ) {
			$this->options['blocked_message'] = __( 'You have been blocked from visiting this site by WordPress Zero Spam due to detected spam activity.', 'wpzerospam' );
		}

		// Set the current user's IP address.
		if ( 'enabled' === $this->options['debug'] && ! empty( $_REQUEST['wpzerospamip'] ) ) {
			$this->current_user_ip = $_REQUEST['wpzerospamip'];
		} else {
			$this->current_user_ip = $this->get_user_ip();
		}

		// Set the current user's IP address access.
		$this->current_user_ip_access = $this->get_access( $this->current_user_ip );
	}

	/**
	 * Returns the saved plugin options.
	 */
	public function get_options() {
		$options = $this->default_options;
		$options = array_merge( $options, get_option( 'wpzerospam' ) );
		$options = apply_filters( 'wpzerospam_options', $options );

		return $options;
	}

	/**
	 * Sets access cookies.
	 *
	 * @param array $access Access details.
	 */
	public function set_access_cookies( $access ) {
		foreach ( $access as $key => $value ) {
			$this->set_cookie( $key, $value, 0 );
		}

		return $access;
	}

	/**
	 * Updates a blacklisted IP.
	 *
	 * @param array $api_data The returned IP data from the API.
	 */
	public function update_blacklisted_ip( $api_data, $type ) {
		global $wpdb;

		if ( ! $api_data ) {
			return false;
		}

		switch ( $api_data['api'] ) {
			case 'stopforumspam':
				if ( ! empty( $api_data['confidence'] ) && $api_data['confidence'] < $this->options['stopforumspam_confidence_min'] ) {
					// Doesn't meet the threshold, delete the IP from the database.
					$this->delete_blacklisted_ip( $api_data['ip_address'] );
					return false;
				}
				break;
			case 'botscout':
				if ( ! empty( $api_data['count'] ) && $api_data['count'] < $this->options['botscout_count_min'] ) {
					// Doesn't meet the threshold, delete the IP from the database.
					$this->delete_blacklisted_ip( $api_data['ip_address'] );
					return false;
				}
				break;
		}

		// Update the record.
		if ( 'update' === $type ) {
			$wpdb->update(
				$this->tables['blacklist'],
				array(
					'last_updated'   => current_time( 'mysql' ),
					'blacklist_data' => wp_json_encode( $api_data ),
				),
				array(
					'user_ip' => $api_data['ip_address'],
				)
			);
		} else {
			$wpdb->insert(
				$this->tables['blacklist'],
				array(
					'user_ip'           => $api_data['ip_address'],
					'last_updated'      => current_time( 'mysql' ),
					'blacklist_service' => $api_data['api'],
					'blacklist_data'    => wp_json_encode( $api_data ),
				)
			);
		}

		return true;
	}

	/**
	 * Deletes an IP from the blacklist.
	 *
	 * @param string $ip The IP address to delete.
	 */
	public function delete_blacklisted_ip( $ip ) {
		global $wpdb;

		$wpdb->delete(
			$this->tables['blacklist'],
			array(
				'user_ip' => $ip
			)
		);
	}

	/**
	 * Gets an IP from an API.
	 *
	 * @param string $ip IP address to query.
	 * @param string $api The API to query.
	 */
	public function get_ip_from_api( $ip, $api ) {
		$cache_key = sanitize_title( $api . '_' . $ip );
		$data      = wp_cache_get( $cache_key );

		if ( false === $data ) {
			switch ( $api ) {
				case 'stopforumspam':
					$api_url = 'https://api.stopforumspam.org/api?';
					$params  = array(
						'ip'   => $ip,
						'json' => '',
					);
					break;
				case 'botscout':
					$api_url = 'https://botscout.com/test/?';
					$params  = array(
						'ip'  => $ip,
						'key' => $this->options['botscout_api'],
					);
					break;
			}

			if ( ! empty( $api_url ) ) {
				$endpoint = $api_url . http_build_query( $params );
				$response = wp_remote_get( $endpoint, array( 'timeout' => $this->options['api_timeout'] ) );
				if ( is_array( $response ) && ! is_wp_error( $response ) ) {
					$body_data = wp_remote_retrieve_body( $response );
					switch ( $api ) {
						case 'stopforumspam':
							$body_data = json_decode( $body_data, true );
							if (
								! empty( $body_data['success'] ) &&
								$body_data['success'] &&
								! empty( $body_data['ip'] ) &&
								! empty( $body_data['ip']['appears'] )
							) {
								$data               = $body_data['ip'];
								$data['api']        = 'stopforumspam';
								$data['ip_address'] = $ip;
							}
							break;
						case 'botscout':
							if ( strpos( $body_data, '!' ) === false ) {
								list( $matched, $type, $count ) = explode( '|', $body_data );
								if ( 'Y' === $matched ) {
									$data = array(
										'type'       => $type,
										'count'      => $count,
										'api'        => 'botscout',
										'ip_address' => $ip,
									);
								}
							}
							break;
					}

					if ( $data ) {
						wp_cache_set( $cache_key, $data );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Checks if a temporary blocked IP is active.
	 *
	 * @param string $blocked_ip The blocked IP record from the DB.
	 */
	public function is_blocked_ip_active( $blocked_ip ) {
		$current_datetime = strtotime( current_time( 'mysql' ) );
		$start_block      = strtotime( $blocked_ip['start_block'] );
		$end_block        = strtotime( $blocked_ip['end_block'] );
		if (
			$current_datetime >= $start_block &&
			$current_datetime < $end_block
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if an IP has been blacklisted.
	 *
	 * @param string $ip The IP address to check.
	 */
	public function get_blacklisted_ip( $ip ) {
		$blacklisted_ip = $this->table_query(
			'blacklist',
			array(
				'type'   => 'row',
				'select' => array(
					'blacklist_service',
					'blacklist_id',
					'last_updated',
					'attempts',
				),
				'where'  => array(
					array(
						array(
							'key'      => 'user_ip',
							'value'    => $ip,
							'relation' => '=',
						),
					),
				),
				'limit'  => 1,
			)
		);

		return $blacklisted_ip;
	}

	/**
	 * Checks if an IP has been blocked.
	 *
	 * @param string $ip The IP address to check.
	 */
	public function get_blocked_ip( $ip ) {
		$blocked_ip = $this->table_query(
			'blocked',
			array(
				'type'   => 'row',
				'select' => array(
					'blocked_type',
					'start_block',
					'end_block',
					'reason',
					'attempts',
				),
				'where'  => array(
					array(
						array(
							'key'      => 'user_ip',
							'value'    => $ip,
							'relation' => '=',
						),
					),
				),
				'limit'  => 1,
			)
		);

		return $blocked_ip;
	}

	/**
	 * Queries a database table.
	 *
	 * @param string $table Table key.
	 * @param array  $args Array of query arguments.
	 */
	public function table_query( $table, $args = array() ) {
		global $wpdb;

		$sql = 'SELECT';

		// Select.
		$select = '';
		if ( ! empty( $args['select'] ) ) {
			foreach ( $args['select'] as $key => $value ) {
				if ( $select ) {
					$select .= ', ';
				}
				$select .= $value;
			}
		} else {
			$select = '*';
		}

		$sql .= ' ' . $select;

		// From.
		$sql .= ' FROM ' . $this->tables[ $table ];

		// Where.
		$where = '';
		if ( ! empty( $args['where'] ) ) {
			foreach ( $args['where'] as $key => $where_stmt ) {
				if ( ! $where ) {
					$where .= 'WHERE ';
				}

				foreach ( $where_stmt as $k => $array ) {
					$where .= $array['key'];
					switch ( $array['relation'] ) {
						case '=':
							$where .= ' = ';
							if ( is_numeric( $array['value'] ) ) {
								$where .= ' ' . $array['value'];
							} else {
								$where .= ' "' . $array['value'] . '"';
							}
							break;
					}
				}
			}
		}

		$sql .= ' ' . $where;

		// Limit.
		if ( ! empty( $args['limit'] ) ) {
			$sql .= ' LIMIT ' . $args['limit'];
		}

		// Offset.
		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . $args['offset'];
		}

		if ( ! empty( $args['type'] ) ) {
			if ( 'row' === $args['type'] ) {
				return $wpdb->get_row( $sql, ARRAY_A );
			}
		} else {
			return $wpdb->get_results( $sql, ARRAY_A );
		}
	}

	/**
	 * Returns the whitelisted IPs.
	 */
	public function get_whitelisted_ips() {
		$whitelist = explode( PHP_EOL, $this->options['ip_whitelist'] );
		if ( ! $whitelist ) {
			return array();
		}

		$whitelisted = array();
		foreach ( $whitelist as $k => $whitelisted_ip ) {
			$whitelisted[ $whitelisted_ip ] = $whitelisted_ip;
		}

		$whitelisted = apply_filters( 'wpzerospam_whitelisted_ips', $whitelisted );

		return $whitelisted;
	}

	/**
	 * Gets a cookie.
	 *
	 * @param string $cookie_key The cookie of the cookie to retrieve.
	 */
	public function get_cookie( $cookie_key ) {
		$cookie_key = 'wpzerospam_' . $cookie_key;

		return ! empty( $_COOKIE[ $cookie_key ] ) ? $_COOKIE[ $cookie_key ] : false;
	}

	/**
	 * Sets a cookie.
	 *
	 * @param string $cookie_key The cookie key.
	 * @param string $value The value of the cookie.
	 */
	public function set_cookie( $cookie_key, $value, $expiration ) {
		$current_time = current_time( 'mysql' );

		setcookie( 'wpzerospam_' . $cookie_key, $value, $expiration, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Returns the current user's IP address.
	 *
	 * @link https://www.benmarshall.me/get-ip-address/
	 */
	public function get_user_ip() {
		foreach (
			array(
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'REMOTE_ADDR',
			)
			as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode(',', $_SERVER[ $key ]) as $ip_address ) {
					$ip_address = trim( $ip_address );

					if (
						filter_var(
							$ip_address,
							FILTER_VALIDATE_IP,
							FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
						) !== false
					) {
						return $ip_address;
					}
				}
			}
		}

		return false;
	}
}
